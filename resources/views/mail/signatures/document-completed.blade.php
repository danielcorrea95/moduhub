<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $document->name }} · Documento assinado</title>
    <!--[if mso]>
    <style type="text/css">
        table, td, div, p, a { font-family: Arial, sans-serif !important; }
    </style>
    <![endif]-->
    <style type="text/css">
        body, table, td, p, a, li, blockquote { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; display: block; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; }

        @media (prefers-color-scheme: dark) {
            .bg-page { background-color: #0f172a !important; }
            .bg-card { background-color: #1e293b !important; }
            .text-primary, strong { color: #f1f5f9 !important; }
            .text-muted, p { color: #94a3b8 !important; }
            .border-soft { border-color: #334155 !important; }
            .info-box { background-color: #0f172a !important; border-color: #334155 !important; }
            .info-box .text-primary { color: #f1f5f9 !important; }
            .info-box .text-muted { color: #cbd5e1 !important; }
            .footer-text { color: #64748b !important; }
            a[href] { color: #a5b4fc !important; }
        }

        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .px-mobile { padding-left: 24px !important; padding-right: 24px !important; }
            .h1-mobile { font-size: 22px !important; line-height: 1.3 !important; }
            .btn-mobile { padding: 14px 32px !important; }
        }
    </style>
</head>
<body class="bg-page" style="margin:0; padding:0; background-color:#f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:transparent;">
        Todos assinaram {{ $document->name }}. {{ $attached ? 'A cópia assinada está em anexo.' : 'Baixe a cópia assinada pelo link.' }}
    </div>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="bg-page" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="container bg-card" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.06);">
                    <tr>
                        <td style="height:6px; background-color: {{ $primaryColor }}; line-height:6px; font-size:6px;">&nbsp;</td>
                    </tr>

                    <tr>
                        <td class="px-mobile" style="padding: 32px 40px 20px 40px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td valign="middle" style="width:48px;">
                                        @if($logoUrl)
                                            <img src="{{ $logoUrl }}" alt="{{ $tenantName ?? 'Moduhub' }}" width="40" height="40" style="width:40px; height:40px; border-radius:8px; display:block;">
                                        @else
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                                <tr>
                                                    <td align="center" valign="middle" style="width:40px; height:40px; background-color: {{ $primaryColor }}; border-radius:20px; color:#ffffff; font-size:16px; font-weight:700; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;">
                                                        {{ mb_substr($tenantName ?? 'M', 0, 1) }}
                                                    </td>
                                                </tr>
                                            </table>
                                        @endif
                                    </td>
                                    <td valign="middle" style="padding-left:14px;">
                                        <div class="text-muted" style="font-size:11px; letter-spacing:0.8px; text-transform:uppercase; color:#1f7a4d; font-weight:600;">
                                            Documento assinado
                                        </div>
                                        <div class="text-primary" style="font-size:16px; font-weight:600; color:#0f172a; margin-top:2px;">
                                            {{ $tenantName ?? 'Moduhub' }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="px-mobile" style="padding: 12px 40px 8px 40px;">
                            <h1 class="text-primary h1-mobile" style="margin:0; font-size:26px; line-height:1.3; color:#0f172a; font-weight:700;">
                                Olá, {{ $recipientName }}
                            </h1>
                            <p class="text-muted" style="margin:16px 0 0 0; font-size:16px; line-height:1.6; color:#475569;">
                                Todas as pessoas assinaram
                                <strong class="text-primary" style="color:#0f172a;">{{ $document->name }}</strong>.
                                @if($attached)
                                    A cópia final está em anexo, com o relatório de assinaturas na última página.
                                @else
                                    O arquivo é grande demais para ir em anexo: baixe a cópia final pelo botão abaixo.
                                @endif
                                Guarde este arquivo: ele é o seu comprovante.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td class="px-mobile" style="padding: 24px 40px 0 40px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="info-box border-soft" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                                <tr>
                                    <td style="padding:16px 18px;">
                                        <div class="text-primary" style="font-size:16px; font-weight:600; color:#0f172a;">
                                            {{ $document->name }}
                                        </div>
                                        @if($completedAt)
                                            <div class="text-muted" style="margin-top:4px; font-size:13px; color:#64748b;">
                                                Concluído em {{ $completedAt->format('d/m/Y') }} às {{ $completedAt->format('H:i') }}
                                            </div>
                                        @endif
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top:12px;">
                                            @foreach($signers as $signer)
                                                <tr>
                                                    <td valign="top" style="width:22px; padding:4px 0; font-size:14px; line-height:1.4; color:#1f7a4d;">&#10003;</td>
                                                    <td valign="top" class="text-primary" style="padding:4px 0; font-size:14px; line-height:1.4; color:#0f172a;">
                                                        {{ $signer->name }}@if($signer->role)<span class="text-muted" style="color:#64748b;">, {{ $signer->role }}</span>@endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="px-mobile" align="center" style="padding: 28px 40px 8px 40px;">
                            <!--[if mso]>
                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="{{ $downloadUrl }}" style="height:52px;v-text-anchor:middle;width:280px;" arcsize="12%" strokecolor="{{ $primaryColor }}" fillcolor="{{ $primaryColor }}">
                                <w:anchorlock/>
                                <center style="color:#ffffff;font-family:Arial,sans-serif;font-size:16px;font-weight:bold;">Baixar documento assinado</center>
                            </v:roundrect>
                            <![endif]-->
                            <!--[if !mso]><!-- -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" style="border-radius:8px; background-color: {{ $primaryColor }};">
                                        <a href="{{ $downloadUrl }}" target="_blank" class="btn-mobile" style="display:inline-block; padding:14px 36px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; font-size:16px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:8px; mso-padding-alt:0;">
                                            Baixar documento assinado
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <!--<![endif]-->
                        </td>
                    </tr>

                    <tr>
                        <td class="px-mobile" align="center" style="padding: 16px 40px 0 40px;">
                            <p class="text-muted" style="margin:0; font-size:13px; line-height:1.5; color:#64748b;">
                                @if($sealed)
                                    O PDF foi selado com certificado digital ICP-Brasil: qualquer alteração no arquivo invalida o selo.
                                @endif
                                Qualquer pessoa pode conferir a autenticidade em
                                <a href="{{ $verifyUrl }}" style="color: {{ $primaryColor }}; text-decoration:underline;">{{ $verifyUrl }}</a>.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td class="px-mobile">
                            <div class="border-soft" style="border-top:1px solid #e2e8f0; margin: 28px 40px 0 40px;"></div>
                        </td>
                    </tr>

                    <tr>
                        <td class="px-mobile" align="center" style="padding: 20px 40px 28px 40px;">
                            <p class="footer-text" style="margin:0; font-size:12px; line-height:1.5; color:#94a3b8;">
                                Enviado por Moduhub · Plataforma de assinaturas e notas fiscais
                            </p>
                        </td>
                    </tr>
                </table>

                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="container" style="width:600px; max-width:600px; margin-top:16px;">
                    <tr>
                        <td align="center" class="px-mobile" style="padding: 0 40px;">
                            <p class="footer-text" style="margin:0; font-size:11px; line-height:1.5; color:#94a3b8;">
                                Este e-mail foi enviado para {{ $recipientEmail }} porque você participou deste documento.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
