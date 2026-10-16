import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Eraser } from 'lucide-react';
import { useEffect, useRef, useState, type MouseEvent, type TouchEvent } from 'react';

// A cor da tinta vai para dentro do PNG que o backend carimba no PDF — não trocar por token de tema.
const INK_COLOR = '#111827';

type PointerEvt = MouseEvent<HTMLCanvasElement> | TouchEvent<HTMLCanvasElement>;

/**
 * Área para desenhar a assinatura com dedo ou mouse.
 * Emite `data:image/png;base64,...` (ou null ao limpar) — formato validado em VerifyAndSignRequest.
 */
export default function SignaturePad({
    onChange,
    disabled = false,
}: {
    onChange?: (dataUrl: string | null) => void;
    disabled?: boolean;
}) {
    const canvasRef = useRef<HTMLCanvasElement>(null);
    const [isDrawing, setIsDrawing] = useState(false);
    const [hasInk, setHasInk] = useState(false);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;

        const ratio = window.devicePixelRatio || 1;
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;

        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        ctx.scale(ratio, ratio);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.lineWidth = 2;
        ctx.strokeStyle = INK_COLOR;
    }, []);

    const pointerPosition = (event: PointerEvt, canvas: HTMLCanvasElement) => {
        const rect = canvas.getBoundingClientRect();
        if ('touches' in event) {
            const touch = event.touches[0];
            return {
                x: (touch?.clientX ?? 0) - rect.left,
                y: (touch?.clientY ?? 0) - rect.top,
            };
        }
        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    };

    const startDrawing = (event: PointerEvt) => {
        const canvas = canvasRef.current;
        if (disabled || !canvas) return;
        event.preventDefault();
        const { x, y } = pointerPosition(event, canvas);
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        ctx.beginPath();
        ctx.moveTo(x, y);
        setIsDrawing(true);
    };

    const draw = (event: PointerEvt) => {
        const canvas = canvasRef.current;
        if (!isDrawing || disabled || !canvas) return;
        event.preventDefault();
        const { x, y } = pointerPosition(event, canvas);
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        ctx.lineTo(x, y);
        ctx.stroke();
        if (!hasInk) {
            setHasInk(true);
        }
    };

    const stopDrawing = (event: PointerEvt) => {
        if (!isDrawing) return;
        event.preventDefault();
        setIsDrawing(false);
        if (hasInk && canvasRef.current) {
            onChange?.(canvasRef.current.toDataURL('image/png'));
        }
    };

    const clear = () => {
        const canvas = canvasRef.current;
        const ctx = canvas?.getContext('2d');
        if (!canvas || !ctx) return;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        setHasInk(false);
        onChange?.(null);
    };

    return (
        <div>
            <div className="relative">
                {/* touch-none: os listeners de toque do React são passivos, então sem isso a página rola enquanto a pessoa desenha. */}
                <canvas
                    ref={canvasRef}
                    aria-label="Área para desenhar a assinatura"
                    className={cn(
                        'block h-40 w-full touch-none rounded-lg border bg-card',
                        disabled ? 'cursor-not-allowed opacity-70' : 'cursor-crosshair border-input',
                    )}
                    onMouseDown={startDrawing}
                    onMouseMove={draw}
                    onMouseUp={stopDrawing}
                    onMouseLeave={stopDrawing}
                    onTouchStart={startDrawing}
                    onTouchMove={draw}
                    onTouchEnd={stopDrawing}
                />
                {!hasInk && (
                    <span className="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-muted-foreground">
                        Desenhe sua assinatura aqui
                    </span>
                )}
                <span
                    className="pointer-events-none absolute inset-x-6 bottom-8 border-b border-dashed border-input"
                    aria-hidden="true"
                />
            </div>
            <div className="mt-2 flex items-start justify-between gap-3">
                <p className="text-xs text-muted-foreground">
                    Opcional. Se você desenhar, a imagem aparece na página de assinaturas do PDF.
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={clear}
                    disabled={disabled || !hasInk}
                    className="-mt-1 shrink-0"
                >
                    <Eraser /> Limpar
                </Button>
            </div>
        </div>
    );
}
