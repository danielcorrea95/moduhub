<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>NFS-e nº {{ $invoice->nfse_number }} · {{ $issuerName }}</title>
    <style type="text/css">
        body, table, td, p, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; outline: none; text-decoration: none; display: block; }
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
<body class="bg-page" style="margin:0; padding:0; background-color:#f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all; font-size:1px; line-height:1px; color:transparent;">
        @if($kind === 'cancelled')
            A NFS-e nº {{ $invoice->nfse_number }} de {{ $issuerName }} foi {{ $invoice->isReplaced() ? 'substituída' : 'cancelada' }}.
        @else
            NFS-e nº {{ $invoice->nfse_number }} de {{ $issuerName }}, no valor de {{ $amount }}. DANFSe e XML em anexo.
        @endif
    </div>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="bg-page" style="background-color:#f1f5f9;">
        <tr>
            <td align="center" style="padding: 32px 16px;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="container bg-card" style="width:600px; max-width:600px; background-color:#ffffff; border-radius:12px; overflow:hidden;">
                    <tr><td style="height:6px; background-color: {{ $primaryColor }}; line-height:6px; font-size:6px;">&nbsp;</td></tr>

                    <tr>
                        <td class="px-mobile" style="padding: 32px 40px 8px 40px;">
                            @if($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $issuerName }}" width="40" height="40" style="width:40px; height:40px; border-radius:8px; margin-bottom:16px;">
                            @endif
                            <div style="font-size:11px; letter-spacing:0.8px; text-transform:uppercase; color:{{ $kind === 'cancelled' ? '#b42318' : '#1f7a4d' }}; font-weight:600;">
                                @if($kind === 'cancelled')
                                    Nota fiscal {{ $invoice->isReplaced() ? 'substituída' : 'cancelada' }}
                                @else
                                    Nota fiscal de serviço
                                @endif
                            </div>
                            <h1 class="text-primary" style="margin:8px 0 0 0; font-size:24px; line-height:1.3; color:#0f172a; font-weight:700;">
                                Olá, {{ $takerName }}
                            </h1>
                            <p class="text-muted" style="margin:16px 0 0 0; font-size:16px; line-height:1.6; color:#475569;">
                                @if($kind === 'cancelled' && $invoice->isReplaced())
                                    A NFS-e nº {{ $invoice->nfse_number }} emitida por <strong style="color:#0f172a;">{{ $issuerName }}</strong> foi substituída por uma nota corrigida, que você recebe em outro e-mail. Desconsidere a anterior.
                                @elseif($kind === 'cancelled')
                                    A NFS-e nº {{ $invoice->nfse_number }} emitida por <strong style="color:#0f172a;">{{ $issuerName }}</strong> foi cancelada e não tem mais validade.
                                @else
                                    <strong style="color:#0f172a;">{{ $issuerName }}</strong> emitiu uma nota fiscal de serviço para você. O DANFSe (PDF) e o XML estão em anexo.
                                @endif
                            </p>
                            @if($restricted)
                                <p style="margin:12px 0 0 0; font-size:13px; line-height:1.5; color:#b42318;">
                                    Nota de teste (produção restrita), sem validade fiscal.
                                </p>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td class="px-mobile" style="padding: 24px 40px 0 40px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" class="info-box" style="background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                                <tr>
                                    <td style="padding:16px 18px; font-size:14px; line-height:1.7; color:#0f172a;" class="text-primary">
                                        <strong>NFS-e nº {{ $invoice->nfse_number }}</strong> · {{ $amount }}<br>
                                        @if($issuedAt)
                                            <span class="text-muted" style="color:#64748b;">Emitida em {{ $issuedAt->format('d/m/Y') }} às {{ $issuedAt->format('H:i') }}</span><br>
                                        @endif
                                        <span class="text-muted" style="color:#64748b; font-family: Menlo, Consolas, monospace; font-size:12px; word-break:break-all;">Chave {{ $invoice->access_key }}</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td class="px-mobile" style="padding: 20px 40px 0 40px;">
                            <p class="text-muted" style="margin:0; font-size:13px; line-height:1.5; color:#64748b;">
                                Confira a nota no portal nacional da NFS-e:
                                <a href="{{ $consultUrl }}" style="color: {{ $primaryColor }}; text-decoration:underline;">consulta pública</a>.
                            </p>
                        </td>
                    </tr>

                    <tr><td class="px-mobile"><div class="border-soft" style="border-top:1px solid #e2e8f0; margin: 28px 40px 0 40px;"></div></td></tr>
                    <tr>
                        <td class="px-mobile" align="center" style="padding: 20px 40px 28px 40px;">
                            <p style="margin:0; font-size:12px; line-height:1.5; color:#94a3b8;">
                                Dúvidas sobre a nota? Responda este e-mail para falar com {{ $issuerName }}.<br>
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
