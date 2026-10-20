import { CopyButton } from '@/components/copy-button';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import axios from 'axios';
import { QrCode } from 'lucide-react';
import { useState } from 'react';

interface PixData {
    image: string;
    payload: string;
    expires_at: string | null;
}

/** QR Code e copia-e-cola de uma cobrança em aberto, buscados só quando o dialog abre. */
export function PixDialog({ paymentId, amount }: { paymentId: string; amount: string }) {
    const [pix, setPix] = useState<PixData | null>(null);
    const [error, setError] = useState<string | null>(null);

    const load = (open: boolean) => {
        if (!open || pix) return;
        setError(null);
        axios
            .get<PixData>(route('billing.pix', paymentId))
            .then(({ data }) => setPix(data))
            .catch((e) => setError(e.response?.data?.errors?.payment?.[0] ?? 'Não foi possível gerar o Pix agora. Tente de novo.'));
    };

    return (
        <Dialog onOpenChange={load}>
            <DialogTrigger asChild>
                <Button size="sm" variant="outline">
                    <QrCode /> Pagar com Pix
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Pagar {amount} com Pix</DialogTitle>
                    <DialogDescription>
                        Leia o QR Code no app do banco ou use o copia e cola. A confirmação chega em instantes.
                    </DialogDescription>
                </DialogHeader>

                {error && <p className="text-sm text-destructive">{error}</p>}
                {!pix && !error && (
                    <div className="flex h-56 items-center justify-center">
                        <Spinner />
                    </div>
                )}
                {pix && (
                    <div className="flex flex-col items-center gap-4">
                        <img
                            src={`data:image/png;base64,${pix.image}`}
                            alt="QR Code do Pix"
                            className="size-56 rounded-md border bg-white p-2"
                        />
                        <code className="w-full break-all rounded-md bg-muted px-3 py-2 text-xs">{pix.payload}</code>
                        <CopyButton value={pix.payload} label="Copiar código Pix" className="w-full" />
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
