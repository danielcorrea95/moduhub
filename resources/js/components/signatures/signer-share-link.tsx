import { CopyButton } from '@/components/copy-button';
import type { Signer } from '@/components/signatures/signer-fields';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { router } from '@inertiajs/react';
import { Mail, MessageCircle, Send } from 'lucide-react';
import { useState } from 'react';

/** wa.me exige DDI + número só com dígitos; telefones brasileiros sem DDI ganham o 55. */
function whatsappNumber(phone: string | null): string | null {
    if (!phone) return null;
    const digits = phone.replace(/\D/g, '');
    if (digits.length < 10) return null;
    return digits.length <= 11 ? `55${digits}` : digits;
}

function whatsappUrl(signer: Signer, documentName: string, signUrl: string): string {
    const text = `Olá, ${signer.name.split(' ')[0]}! Segue o link para assinar o documento "${documentName}": ${signUrl}`;
    const number = whatsappNumber(signer.phone);
    return `https://wa.me/${number ?? ''}?text=${encodeURIComponent(text)}`;
}

/**
 * Link de assinatura de quem ainda precisa assinar: dá para copiar e mandar por outro canal,
 * reenviar o e-mail ou abrir o WhatsApp com a mensagem pronta.
 */
export function SignerShareLink({
    documentId,
    documentName,
    signer,
    signUrl,
}: {
    documentId: string;
    documentName: string;
    signer: Signer;
    signUrl: string;
}) {
    const [sending, setSending] = useState(false);

    const remind = () => {
        router.post(
            route('signatures.signers.remind', [documentId, signer.id]),
            {},
            {
                preserveScroll: true,
                onStart: () => setSending(true),
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <div className="mt-3 space-y-1.5">
            <p className="text-xs text-muted-foreground">
                {signer.status === 'viewed' ? 'Abriu o link, ainda não assinou.' : 'Ainda não abriu o link.'}
            </p>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div className="flex min-w-0 flex-1 items-center rounded-lg border bg-muted/50 px-3 py-2">
                    <span className="truncate font-mono text-xs text-foreground/80" title={signUrl}>
                        {signUrl}
                    </span>
                </div>
                <div className="flex shrink-0 gap-2">
                    <CopyButton value={signUrl} label="Copiar link" copiedLabel="Link copiado" />
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button type="button" variant="outline" size="sm" disabled={sending}>
                                <Send /> Reenviar
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-60">
                            <DropdownMenuItem onSelect={remind} className="gap-2">
                                <Mail className="size-4" />
                                Enviar lembrete por e-mail
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild className="gap-2">
                                <a
                                    href={whatsappUrl(signer, documentName, signUrl)}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <MessageCircle className="size-4" />
                                    Enviar pelo WhatsApp
                                </a>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>
    );
}
