<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>E-mail de teste · {{ $tenantName }}</title>
    <style type="text/css">
        body, table, td, p, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; }

        @media (prefers-color-scheme: dark) {
            .bg-page { background-color: #0f172a !important; }
            .bg-card { background-color: #1e293b !important; }
            .text-primary, strong { color: #f1f5f9 !important; }
            .text-muted, p { color: #94a3b8 !important; }
            .info-box { background-color: #0f172a !important; border-color: #334155 !important; }
            a[href] { color: #a5b4fc !important; }
        }

        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .px-mobile { padding-left: 24px !important; padding-right: 24px !important; }
        }
    </style>
</head>
<body class="bg-page" style="margin:0; padding:0; background-color:#f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:transparent;">
        Se você recebeu esta mensagem, os e-mails de {{ $tenantName }} estão chegando.
    </div>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="bg-page" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="container bg-card" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                    <tr>
                        <td style="height:6px; background-color: {{ $primaryColor }}; line-height:6px; font-size:6px;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="px-mobile" style="padding: 32px 40px 8px 40px;">
                            <h1 class="text-primary" style="margin:0; font-size:24px; line-height:1.3; color:#0f172a; font-weight:600;">
                                O e-mail chegou
                            </h1>
                            <p class="text-muted" style="margin:12px 0 0 0; font-size:15px; line-height:1.6; color:#475569;">
                                Este é um teste do workspace <strong style="color:#0f172a;">{{ $tenantName }}</strong> no Moduhub.
                                Os convites de assinatura, os códigos de verificação e as NFS-e saem com este mesmo remetente.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-mobile" style="padding: 16px 40px 8px 40px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="info-box" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                                <tr>
                                    <td style="padding:16px 20px; font-size:14px; line-height:1.6; color:#475569;">
                                        <span class="text-muted">Remetente:</span>
                                        <strong style="color:#0f172a; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;">{{ $fromAddress }}</strong>
                                        <br>
                                        @if($usesOwnDomain)
                                            <span class="text-muted">Domínio próprio verificado. Os destinatários veem o endereço da sua empresa.</span>
                                        @else
                                            <span class="text-muted">Endereço da Moduhub. Cadastre e verifique o domínio da sua empresa para enviar com o seu endereço.</span>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td class="px-mobile" style="padding: 16px 40px 32px 40px;">
                            <p class="text-muted" style="margin:0; font-size:14px; line-height:1.6; color:#475569;">
                                Se esta mensagem caiu no spam, marque como "não é spam" e confira os registros DNS em
                                <a href="{{ $settingsUrl }}" style="color: {{ $primaryColor }};">Domínio de envio</a>.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
