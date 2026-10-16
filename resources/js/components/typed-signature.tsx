import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useEffect, useId, useRef, useState } from 'react';

// As famílias são carregadas no Blade raiz (app.blade.php). Os ids são nomes de fonte reais.
const FONTS = [
    { id: 'Dancing Script', label: 'Script', weight: 700 },
    { id: 'Great Vibes', label: 'Elegante', weight: 400 },
    { id: 'Allura', label: 'Formal', weight: 400 },
    { id: 'Satisfy', label: 'Casual', weight: 400 },
    { id: 'Caveat', label: 'Manual', weight: 700 },
] as const;

type FontId = (typeof FONTS)[number]['id'];

const CANVAS_HEIGHT = 120;
const PADDING_X = 24;
const FONT_SIZE = 64;
// Vai para dentro do PNG carimbado no PDF — não trocar por token de tema.
const INK_COLOR = '#111827';

/**
 * Assinatura digitada: renderiza o nome numa fonte manuscrita num canvas
 * e emite `data:image/png;base64,...` (ou null quando o nome está vazio).
 */
export default function TypedSignature({
    defaultName = '',
    onChange,
    disabled = false,
}: {
    defaultName?: string;
    onChange?: (dataUrl: string | null) => void;
    disabled?: boolean;
}) {
    const inputId = useId();
    const [name, setName] = useState(defaultName);
    const [fontId, setFontId] = useState<FontId>(FONTS[0].id);
    const canvasRef = useRef<HTMLCanvasElement>(null);
    // O pai usa useForm do Inertia, cuja callback muda a cada render; deixá-la nas deps do effect gera loop.
    const onChangeRef = useRef(onChange);
    onChangeRef.current = onChange;

    useEffect(() => {
        let cancelled = false;

        async function render() {
            const trimmed = name.trim();
            const canvas = canvasRef.current;
            if (!canvas) return;

            if (!trimmed) {
                const ctx = canvas.getContext('2d');
                ctx?.clearRect(0, 0, canvas.width, canvas.height);
                onChangeRef.current?.(null);
                return;
            }

            const spec = `${FONTS.find((f) => f.id === fontId)?.weight ?? 400} ${FONT_SIZE}px "${fontId}"`;

            try {
                await document.fonts.load(spec, trimmed);
            } catch {
                // Sem a fonte, o canvas cai numa fonte do sistema — a assinatura continua válida.
            }
            if (cancelled) return;

            const dpr = window.devicePixelRatio || 1;
            const measureCtx = document.createElement('canvas').getContext('2d');
            if (!measureCtx) return;
            measureCtx.font = spec;
            const metrics = measureCtx.measureText(trimmed);
            const textWidth = Math.max(200, Math.ceil(metrics.width + PADDING_X * 2));

            canvas.width = textWidth * dpr;
            canvas.height = CANVAS_HEIGHT * dpr;
            canvas.style.width = `${Math.min(textWidth, 560)}px`;
            canvas.style.height = `${CANVAS_HEIGHT}px`;

            const ctx = canvas.getContext('2d');
            if (!ctx) return;
            ctx.scale(dpr, dpr);
            ctx.clearRect(0, 0, textWidth, CANVAS_HEIGHT);
            ctx.font = spec;
            ctx.fillStyle = INK_COLOR;
            ctx.textBaseline = 'middle';
            ctx.textAlign = 'left';
            ctx.fillText(trimmed, PADDING_X, CANVAS_HEIGHT / 2);

            onChangeRef.current?.(canvas.toDataURL('image/png'));
        }

        render();
        return () => {
            cancelled = true;
        };
    }, [name, fontId]);

    return (
        <div className="space-y-4">
            <div className="space-y-2">
                <Label htmlFor={inputId}>Nome na assinatura</Label>
                <Input
                    id={inputId}
                    type="text"
                    value={name}
                    disabled={disabled}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="Digite seu nome completo"
                    maxLength={80}
                    autoComplete="name"
                    className="h-11 bg-card"
                />
            </div>

            <div className="space-y-2">
                <p id={`${inputId}-style`} className="text-sm font-medium">
                    Estilo da letra
                </p>
                <div
                    role="radiogroup"
                    aria-labelledby={`${inputId}-style`}
                    className="grid grid-cols-3 gap-2 sm:grid-cols-5"
                >
                    {FONTS.map((f) => {
                        const selected = f.id === fontId;
                        return (
                            <button
                                key={f.id}
                                type="button"
                                role="radio"
                                aria-checked={selected}
                                onClick={() => setFontId(f.id)}
                                disabled={disabled}
                                className={cn(
                                    'flex min-w-0 flex-col items-center gap-1.5 rounded-lg border bg-card px-2 py-2.5 text-center transition-colors',
                                    'focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-60',
                                    selected
                                        ? 'border-primary bg-accent ring-1 ring-primary'
                                        : 'hover:border-foreground/25',
                                )}
                            >
                                <span
                                    className="w-full truncate text-lg leading-tight text-foreground"
                                    style={{ fontFamily: `"${f.id}", cursive`, fontWeight: f.weight }}
                                >
                                    {(name || 'Abc').slice(0, 10)}
                                </span>
                                <span
                                    className={cn(
                                        'text-xs',
                                        selected ? 'text-accent-foreground' : 'text-muted-foreground',
                                    )}
                                >
                                    {f.label}
                                </span>
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className="space-y-2">
                <p className="text-sm font-medium">Como vai ficar</p>
                <div className="overflow-x-auto rounded-lg border bg-card p-3">
                    <canvas
                        ref={canvasRef}
                        className="block max-w-none"
                        aria-label="Pré-visualização da assinatura"
                    />
                    {!name.trim() && (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            Digite seu nome para ver a pré-visualização
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
