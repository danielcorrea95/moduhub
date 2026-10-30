<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Seu workspace {{ $tenantName }}</title>
    <!--[if mso]>
    <style type="text/css">
        table, td, div, p, a { font-family: Arial, sans-serif !important; }
    </style>
    <![endif]-->
    <style type="text/css">
        /* Reset */
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; display: block; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; }

        /* Dark mode */
        @media (prefers-color-scheme: dark) {
            .bg-page { background-color: #0f172a !important; }
            .bg-card { background-color: #1e293b !important; }
            .text-primary, strong { color: #f1f5f9 !important; }
            .text-muted, p { color: #94a3b8 !important; }
            .border-soft { border-color: #334155 !important; }
            .code-box { background-color: #0f172a !important; color: #cbd5e1 !important; border-color: #334155 !important; }
            .code-box .text-primary { color: #f1f5f9 !important; }
            .code-box .text-muted { color: #cbd5e1 !important; }
            .footer-text { color: #64748b !important; }
            a[href] { color: #a5b4fc !important; }
        }

        /* Mobile */
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .px-mobile { padding-left: 24px !important; padding-right: 24px !important; }
            .h1-mobile { font-size: 22px !important; line-height: 1.3 !important; }
            .btn-mobile { padding: 14px 32px !important; }
        }
    </style>
</head>
<body class="bg-page" style="margin:0; padding:0; background-color:#f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <!-- Preheader (hidden preview text) -->
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:transparent;">
        O workspace {{ $tenantName }} foi criado para você pela equipe da Moduhub.
    </div>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="bg-page" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <!-- Container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="container bg-card" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                    <!-- Top color stripe -->
                    <tr>
                        <td style="height:6px; background-color: #4F46E5; line-height:6px; font-size:6px;">&nbsp;</td>
                    </tr>

                    <!-- Header: logo + tenant name -->
                    <tr>
                        <td class="px-mobile" style="padding: 32px 40px 20px 40px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td valign="middle" style="width:48px;">
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                                <tr>
                                                    <td align="center" valign="middle" style="width:40px; height:40px; background-color: #4F46E5; border-radius:20px; color:#ffffff; font-size:16px; font-weight:700; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;">
                                                        {{ mb_substr($tenantName, 0, 1) }}
                                                    </td>
                                                </tr>
                                            </table>
                                    </td>
                                    <td valign="middle" style="padding-left:14px;">
                                        <div class="text-muted" style="font-size:11px; letter-spacing:0.8px; text-transform:uppercase; color:#64748b; font-weight:600;">
                                            Workspace criado
                                        </div>
                                        <div class="text-primary" style="font-size:16px; font-weight:600; color:#0f172a; margin-top:2px;">
                                            {{ $tenantName }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Main content -->
                    <tr>
                        <td class="px-mobile" style="padding: 12px 40px 8px 40px;">
                            <h1 class="text-primary h1-mobile" style="margin:0; font-size:26px; line-height:1.3; color:#0f172a; font-weight:700;">
                                Olá, {{ $ownerName }}
                            </h1>
                            <p class="text-muted" style="margin:16px 0 0 0; font-size:16px; line-height:1.6; color:#475569;">
                                A equipe da Moduhub criou o workspace <strong style="color:#0f172a;">{{ $tenantName }}</strong>
                                e você é o proprietário.
                                @if($newAccount)
                                    Para entrar, crie a senha da sua conta ({{ $ownerEmail }}).
                                @else
                                    Ele já aparece na sua conta ({{ $ownerEmail }}), na troca de workspace.
                                @endif
                            </p>
                        </td>
                    </tr>

                    <!-- CTA Button (bulletproof) -->
                    <tr>
                        <td class="px-mobile" align="center" style="padding: 32px 40px 8px 40px;">
                            <!--[if mso]>
                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $actionUrl }}" style="height:52px;v-text-anchor:middle;width:260px;" arcsize="12%" strokecolor="#4F46E5" fillcolor="#4F46E5">
                                <w:anchorlock/>
                                <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">{{ $actionLabel }}</center>
                            </v:roundrect>
                            <![endif]-->
                            <!--[if !mso]><!-- -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="border-radius:8px; background-color:#4F46E5;">
                                        <a href="{{ $actionUrl }}" target="_blank" class="btn-mobile" style="display:inline-block; padding:14px 36px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size:16px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:8px; mso-padding-alt:0;">
                                            {{ $actionLabel }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <!--<![endif]-->
                        </td>
                    </tr>

                    <!-- Fallback link -->
                    <tr>
                        <td class="px-mobile" align="center" style="padding: 20px 40px 0 40px;">
                            <p class="text-muted" style="margin:0; font-size:13px; line-height:1.5; color:#64748b;">
                                Se o botão não funcionar, copie e cole este link no navegador:
                            </p>
                            <p style="margin:8px 0 0 0; font-size:12px; line-height:1.5; word-break:break-all;">
                                <a href="{{ $actionUrl }}" style="color:#4F46E5; text-decoration:underline;">{{ $actionUrl }}</a>
                            </p>
                        </td>
                    </tr>

                    @if($newAccount)
                    <!-- Expiry notice -->
                    <tr>
                        <td class="px-mobile" style="padding: 24px 40px 32px 40px;">
                            <p class="text-muted" style="margin:0; font-size:13px; line-height:1.6; color:#64748b;">
                                O link vale por {{ $linkMinutes }} minutos. Depois disso, use <strong style="color:#475569;">Esqueci minha senha</strong>
                                na tela de entrada com este mesmo e-mail.
                            </p>
                        </td>
                    </tr>
                    @else
                    <tr><td style="padding: 0 0 32px 0;">&nbsp;</td></tr>
                    @endif

                    <!-- Divider -->
                    <tr>
                        <td class="px-mobile">
                            <div class="border-soft" style="border-top:1px solid #e2e8f0; margin: 0 40px;"></div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td class="px-mobile" align="center" style="padding: 24px 40px 32px 40px;">
                            <p class="footer-text" style="margin:0; font-size:12px; line-height:1.5; color:#94a3b8;">
                                Enviado por Moduhub · Plataforma de assinaturas e notas fiscais
                            </p>
                        </td>
                    </tr>
                </table>

                <!-- Below-container micro-print -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="container" style="width:600px; max-width:600px; margin-top:16px;">
                    <tr>
                        <td align="center" class="px-mobile" style="padding: 0 40px;">
                            <p class="footer-text" style="margin:0; font-size:11px; line-height:1.5; color:#94a3b8;">
                                Este e-mail foi enviado para {{ $ownerEmail }} porque a equipe da Moduhub criou um workspace em seu nome.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
