export interface SmtpSampleInput {
    host: string;
    username: string;
    password: string;
    fromAddress: string;
    fromName: string;
}

/** Configuração do relay SMTP em cada linguagem, já com os dados do workspace. Porta 587 + STARTTLS. */
export function buildSmtpSamples({ host, username, password, fromAddress, fromName }: SmtpSampleInput) {
    return [
        {
            id: 'laravel',
            label: 'Laravel (.env)',
            code: `MAIL_MAILER=smtp
MAIL_HOST=${host}
MAIL_PORT=587
MAIL_SCHEME=smtp
MAIL_USERNAME=${username}
MAIL_PASSWORD=${password}
MAIL_FROM_ADDRESS="${fromAddress}"
MAIL_FROM_NAME="${fromName}"`,
        },
        {
            id: 'php',
            label: 'PHP (PHPMailer)',
            code: `<?php
// composer require phpmailer/phpmailer
use PHPMailer\\PHPMailer\\PHPMailer;

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host       = '${host}';
$mail->Port       = 587;
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->SMTPAuth   = true;
$mail->Username   = '${username}';
$mail->Password   = '${password}';
$mail->CharSet    = 'UTF-8';

$mail->setFrom('${fromAddress}', '${fromName}');
$mail->addAddress('cliente@exemplo.com.br');
$mail->Subject = 'Seu pedido foi confirmado';
$mail->isHTML(true);
$mail->Body    = '<p>Olá! Seu pedido foi confirmado.</p>';
$mail->AltBody = 'Olá! Seu pedido foi confirmado.';

$mail->send();`,
        },
        {
            id: 'node',
            label: 'Node.js (Nodemailer)',
            code: `// npm install nodemailer
import nodemailer from 'nodemailer';

const transporter = nodemailer.createTransport({
    host: '${host}',
    port: 587,
    secure: false, // STARTTLS na porta 587
    auth: {
        user: '${username}',
        pass: '${password}',
    },
});

await transporter.sendMail({
    from: '"${fromName}" <${fromAddress}>',
    to: 'cliente@exemplo.com.br',
    subject: 'Seu pedido foi confirmado',
    text: 'Olá! Seu pedido foi confirmado.',
    html: '<p>Olá! Seu pedido foi confirmado.</p>',
});`,
        },
        {
            id: 'python',
            label: 'Python',
            code: `import smtplib
from email.message import EmailMessage

msg = EmailMessage()
msg["From"] = "${fromName} <${fromAddress}>"
msg["To"] = "cliente@exemplo.com.br"
msg["Subject"] = "Seu pedido foi confirmado"
msg.set_content("Olá! Seu pedido foi confirmado.")
msg.add_alternative("<p>Olá! Seu pedido foi confirmado.</p>", subtype="html")

with smtplib.SMTP("${host}", 587) as smtp:
    smtp.starttls()
    smtp.login("${username}", "${password}")
    smtp.send_message(msg)`,
        },
        {
            id: 'dotnet',
            label: 'C# (.NET)',
            code: `// dotnet add package MailKit
using MailKit.Net.Smtp;
using MailKit.Security;
using MimeKit;

var message = new MimeMessage();
message.From.Add(new MailboxAddress("${fromName}", "${fromAddress}"));
message.To.Add(MailboxAddress.Parse("cliente@exemplo.com.br"));
message.Subject = "Seu pedido foi confirmado";
message.Body = new BodyBuilder {
    HtmlBody = "<p>Olá! Seu pedido foi confirmado.</p>",
    TextBody = "Olá! Seu pedido foi confirmado.",
}.ToMessageBody();

using var client = new SmtpClient();
await client.ConnectAsync("${host}", 587, SecureSocketOptions.StartTls);
await client.AuthenticateAsync("${username}", "${password}");
await client.SendAsync(message);
await client.DisconnectAsync(true);`,
        },
        {
            id: 'wordpress',
            label: 'WordPress',
            code: `Plugin "WP Mail SMTP" → Configurações → Mailer: "Other SMTP"

From Email ........ ${fromAddress}
From Name ......... ${fromName}
SMTP Host ......... ${host}
Encryption ........ TLS
SMTP Port ......... 587
Auto TLS .......... On
Authentication .... On
SMTP Username ..... ${username}
SMTP Password ..... ${password}`,
        },
    ];
}
