<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Certificado digital de {{ $issuerName }}</title>
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
            .border-soft { border-color: #334155 !important; }
        }
        @media only screen and (max-width: 620px) {
            .container { width: 100% !important; }
            .px-mobile { padding-left: 24px !important; padding-right: 24px !important; }
        }
    </style>
</head>
@php($accent = $expired || $daysLeft <= 7 ? '#b42318' : '#b54708')
<body class="bg-page" style="margin:0; padding:0; background-color:#f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:transparent;">
        @if($expired)
            O e-CNPJ de {{ $issuerName }} venceu. Envie um certificado novo para voltar a emitir notas.
        @else
            O e-CNPJ de {{ $issuerName }} vence em {{ $validUntil?->format('d/m/Y') }}. Renove antes para não parar a emissão de notas.
        @endif
    </div>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="bg-page" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="container bg-card" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr><td style="height:6px; background-color: {{ $accent }}; line-height:6px; font-size:6px;">&nbsp;</td></tr>

                    <tr>
                        <td class="px-mobile" style="padding: 32px 40px 8px 40px;">
                            <div style="font-size:11px; letter-spacing:0.8px; text-transform:uppercase; color:{{ $accent }}; font-weight:600;">
                                Certificado digital · {{ $workspaceName }}
                            </div>
                            <h1 class="text-primary" style="margin:8px 0 0 0; font-size:24px; line-height:1.3; color:#0f172a; font-weight:700;">
                                @if($expired)
                                    O certificado de {{ $issuerName }} venceu
                                @elseif($daysLeft === 1)
                                    O certificado de {{ $issuerName }} vence amanhã
                                @else
                                    O certificado de {{ $issuerName }} vence em {{ $daysLeft }} dias
                                @endif
                            </h1>
                            <p class="text-muted" style="margin:16px 0 0 0; font-size:16px; line-height:1.6; color:#475569;">
                                @if($expired)
                                    Sem um e-CNPJ válido a Moduhub não consegue assinar as DPS nem falar com a Sefin Nacional: a emissão de notas está parada e as notas recebidas deixaram de ser sincronizadas.
                                @else
                                    Quando ele vencer, a emissão de notas para e as notas recebidas deixam de ser sincronizadas. Renove o e-CNPJ A1 com a sua certificadora e envie o arquivo novo antes disso.
                                @endif
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td class="px-mobile" style="padding: 24px 40px 0 40px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="info-box" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                                <tr>
                                    <td style="padding:16px 18px; font-size:14px; line-height:1.7; color:#0f172a;" class="text-primary">
                                        <strong>{{ $subject ?? $issuerName }}</strong><br>
                                        <span class="text-muted" style="color:#64748b;">
                                            {{ $expired ? 'Venceu em' : 'Válido até' }} {{ $validUntil?->format('d/m/Y \à\s H:i') }}
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="px-mobile" style="padding: 24px 40px 0 40px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td style="border-radius:8px; background-color:#0f172a;">
                                        <a href="{{ $issuerUrl }}" style="display:inline-block; padding:12px 20px; font-size:14px; font-weight:600; color:#ffffff; text-decoration:none;">Enviar o certificado novo</a>
                                    </td>
                                </tr>
                            </table>
                            <p class="text-muted" style="margin:12px 0 0 0; font-size:13px; line-height:1.5; color:#64748b;">
                                Em Notas fiscais › Emitente › Certificado digital. Só donos e administradores do workspace podem trocar o certificado.
                            </p>
                        </td>
                    </tr>

                    <tr><td class="px-mobile"><div class="border-soft" style="border-top:1px solid #e2e8f0; margin: 28px 40px 0 40px;"></div></td></tr>
                    <tr>
                        <td class="px-mobile" align="center" style="padding: 20px 40px 28px 40px;">
                            <p style="margin:0; font-size:12px; line-height:1.5; color:#94a3b8;">
                                Você recebe este aviso por administrar {{ $workspaceName }} ou ser o contato fiscal do emitente.<br>
                                Enviado por Moduhub · Plataforma de assinaturas e notas fiscais
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
