'use strict'

// Relay smtp.moduhub.com.br. Este plugin só fala SMTP: o login e a mensagem vão para o Laravel
// (/api/v1/internal/smtp-relay), que confere credencial, domínio, cota e bloqueios e entrega ao SES.
// Nada sai daqui direto para a internet: toda mensagem termina em OK ou recusa, nunca no outbound
// do Haraka (porta 25 bloqueada e IP sem reputação).

const http = require('node:http')
const https = require('node:https')
// O sandbox dos plugins só tem os intrínsecos do JS; URL é global do Node e vem pelo require.
const { URL } = require('node:url')

let inflight = 0
const waiting = []

exports.register = function () {
    this.inherits('auth/auth_base')
    this.load_moduhub_ini()

    this.register_hook('queue', 'deliver')
    this.register_hook('queue_outbound', 'deliver')
}

exports.load_moduhub_ini = function () {
    this.cfg = this.config.get('moduhub.ini', { booleans: ['+main.require_tls'] }, () => this.load_moduhub_ini())

    if (!this.cfg.main.endpoint || !this.cfg.main.secret) {
        this.logerror('config/moduhub.ini sem endpoint ou secret: todo login será recusado')
    }
}

// CRAM-MD5 fica de fora: exige a senha em claro guardada, e só guardamos o hash.
exports.hook_capabilities = function (next, connection) {
    if (connection.tls.enabled || !this.cfg.main.require_tls) {
        const methods = ['PLAIN', 'LOGIN']
        connection.capabilities.push(`AUTH ${methods.join(' ')}`)
        connection.notes.allowed_auth_methods = methods
    }
    next()
}

exports.check_plain_passwd = function (connection, user, passwd, cb) {
    this.backend('auth', { username: user, password: passwd, remote_ip: connection.remote.ip })
        .then(({ status, body }) => {
            if (status === 200 && body.ok) {
                connection.notes.moduhub = { username: user, password: passwd }
                return cb(true)
            }

            if (body.smtp_code) {
                return cb(false, { code: body.smtp_code, message: `${body.enhanced_code} ${body.message}` })
            }

            connection.logerror(this, `auth: HTTP ${status} do Laravel`)
            cb(false, { code: 454, message: '4.7.0 Autenticação indisponível no momento, tente de novo' })
        })
        .catch((err) => {
            connection.logerror(this, `auth: ${err.message}`)
            cb(false, { code: 454, message: '4.7.0 Autenticação indisponível no momento, tente de novo' })
        })
}

exports.hook_rcpt = function (next, connection) {
    // O Haraka já exige AUTH no MAIL das portas 587/465; aqui vale para qualquer porta configurada.
    if (!connection.notes.moduhub) return next(DENY, '5.7.0 Autenticação obrigatória')

    const max = Number(this.cfg.main.max_recipients) || 50

    if (connection.transaction.rcpt_to.length > max) {
        return next(DENYSOFT, `4.5.3 Máximo de ${max} destinatários por mensagem`)
    }
    next(OK)
}

exports.deliver = function (next, connection) {
    const txn = connection.transaction
    const credentials = connection.notes.moduhub

    if (!credentials) return next(DENY, '5.7.0 Autenticação obrigatória')

    read_message(txn.message_stream)
        .then((raw) =>
            with_slot(Number(this.cfg.main.max_inflight) || 3, () =>
                this.backend('messages', {
                    ...credentials,
                    remote_ip: connection.remote.ip,
                    // Propriedade no @haraka/email-address (Haraka 3.3+), método no address-rfc2821 antigo.
                    rcpt_to: txn.rcpt_to.map((rcpt) => (typeof rcpt.address === 'function' ? rcpt.address() : rcpt.address)),
                    raw: raw.toString('base64'),
                }),
            ),
        )
        .then(({ status, body }) => {
            if (status === 200 && body.ok) {
                connection.loginfo(this, `aceita, SES ${body.message_id ?? '-'}`)
                return next(OK, body.message_id ? `2.0.0 Mensagem aceita (${body.message_id})` : '2.0.0 Mensagem aceita')
            }

            if (body.smtp_code) {
                connection.lognotice(this, `recusada: ${body.smtp_code} ${body.message}`)
                return next(body.smtp_code >= 500 ? DENY : DENYSOFT, `${body.enhanced_code} ${body.message}`)
            }

            if (status === 422) return next(DENY, '5.6.0 Mensagem recusada: dados inválidos')

            connection.logerror(this, `deliver: HTTP ${status} do Laravel`)
            next(DENYSOFT, '4.3.0 Falha temporária, tente de novo em alguns minutos')
        })
        .catch((err) => {
            connection.logerror(this, `deliver: ${err.message}`)
            next(DENYSOFT, '4.3.0 Falha temporária, tente de novo em alguns minutos')
        })
}

// node:http em vez de fetch: o sandbox dos plugins do Haraka não expõe AbortSignal para o timeout.
exports.backend = function (path, payload) {
    const url = new URL(`${this.cfg.main.endpoint.replace(/\/$/, '')}/${path}`)
    const client = url.protocol === 'https:' ? https : http
    const data = Buffer.from(JSON.stringify(payload))

    return new Promise((resolve, reject) => {
        const req = client.request(
            url,
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'Content-Length': data.length,
                    'X-Relay-Secret': this.cfg.main.secret ?? '',
                },
                timeout: Number(this.cfg.main.timeout_ms) || 60000,
            },
            (res) => {
                const chunks = []
                res.on('data', (chunk) => chunks.push(chunk))
                res.on('end', () => {
                    let body = {}
                    try {
                        body = JSON.parse(Buffer.concat(chunks).toString('utf8')) ?? {}
                    } catch {
                        // Resposta sem JSON (502 do nginx, página de erro): trata pelo status.
                    }
                    resolve({ status: res.statusCode, body })
                })
                res.on('error', reject)
            },
        )

        req.on('timeout', () => req.destroy(new Error('timeout falando com o Laravel')))
        req.on('error', reject)
        req.end(data)
    })
}

function read_message(stream) {
    return new Promise((resolve, reject) => {
        try {
            stream.get_data((buffer) => resolve(buffer))
        } catch (err) {
            reject(err)
        }
    })
}

// O Laravel atende no mesmo PHP-FPM do painel. Poucas entregas por vez evitam que um cliente com
// pressa ocupe todos os workers; as outras esperam a vez com a conexão SMTP aberta.
function with_slot(max, task) {
    return new Promise((resolve, reject) => {
        const run = () => {
            inflight++
            task()
                .then(resolve, reject)
                .finally(() => {
                    inflight--
                    const nextTask = waiting.shift()
                    if (nextTask) nextTask()
                })
        }

        if (inflight < max) run()
        else waiting.push(run)
    })
}
