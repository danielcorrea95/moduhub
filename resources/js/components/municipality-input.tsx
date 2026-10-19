import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import axios from 'axios';
import { MapPin } from 'lucide-react';
import { useEffect, useId, useRef, useState, type KeyboardEvent } from 'react';

export interface Municipality {
    code: string;
    name: string;
    uf: string;
}

const label = (m: Municipality) => `${m.name} / ${m.uf}`;

async function searchMunicipalities(q: string, signal?: AbortSignal): Promise<Municipality[]> {
    const response = await axios.get<{ data: Municipality[] }>(route('invoices.municipalities'), { params: { q }, signal });
    return response.data.data;
}

/**
 * Campo de município pelo nome, guardando o código IBGE. Também aceita o código de 7 dígitos digitado direto.
 */
export function MunicipalityInput({
    id,
    value,
    onChange,
    invalid,
    placeholder = 'Digite o nome do município',
}: {
    id: string;
    value: string;
    onChange: (code: string, municipality: Municipality | null) => void;
    invalid?: boolean;
    placeholder?: string;
}) {
    const [selected, setSelected] = useState<Municipality | null>(null);
    const [query, setQuery] = useState('');
    const [editing, setEditing] = useState(false);
    const [results, setResults] = useState<Municipality[]>([]);
    const [active, setActive] = useState(0);
    const listId = useId();
    const blurTimer = useRef<ReturnType<typeof setTimeout>>(undefined);

    // Código vindo de fora (rascunho, emitente): resolve o nome para exibir.
    useEffect(() => {
        if (value === '' || selected?.code === value) {
            if (value === '') setSelected(null);
            return;
        }
        if (value.length !== 7) return;

        const controller = new AbortController();
        searchMunicipalities(value, controller.signal)
            .then((found) => setSelected(found[0] ?? null))
            .catch(() => undefined);
        return () => controller.abort();
    }, [value, selected?.code]);

    useEffect(() => {
        if (!editing || query.trim().length < 2) {
            setResults([]);
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            searchMunicipalities(query.trim(), controller.signal)
                .then((found) => {
                    setResults(found);
                    setActive(0);
                })
                .catch(() => undefined);
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [query, editing]);

    const choose = (m: Municipality) => {
        setSelected(m);
        setEditing(false);
        setQuery('');
        setResults([]);
        onChange(m.code, m);
    };

    const onKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (results.length === 0) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive((i) => Math.min(i + 1, results.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive((i) => Math.max(i - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            choose(results[active]);
        } else if (e.key === 'Escape') {
            setEditing(false);
        }
    };

    const open = editing && results.length > 0;
    const display = editing ? query : selected ? label(selected) : value;

    return (
        <div className="relative">
            <Input
                id={id}
                value={display}
                placeholder={placeholder}
                autoComplete="off"
                role="combobox"
                aria-expanded={open}
                aria-controls={listId}
                aria-invalid={invalid}
                onFocus={() => {
                    clearTimeout(blurTimer.current);
                    setEditing(true);
                    setQuery('');
                }}
                onBlur={() => {
                    // Dá tempo do clique na opção chegar antes de fechar a lista.
                    blurTimer.current = setTimeout(() => setEditing(false), 150);
                }}
                onChange={(e) => {
                    setQuery(e.target.value);
                    if (e.target.value === '') {
                        setSelected(null);
                        onChange('', null);
                    }
                }}
                onKeyDown={onKeyDown}
            />
            {open && (
                <ul id={listId} role="listbox" className="absolute z-20 mt-1 max-h-64 w-full overflow-auto rounded-md border bg-popover p-1 text-sm shadow-md">
                    {results.map((m, index) => (
                        <li
                            key={m.code}
                            role="option"
                            aria-selected={index === active}
                            className={cn('flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5', index === active && 'bg-accent text-accent-foreground')}
                            onMouseEnter={() => setActive(index)}
                            onMouseDown={(e) => {
                                e.preventDefault();
                                choose(m);
                            }}
                        >
                            <MapPin className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <span className="flex-1 truncate">{label(m)}</span>
                            <span className="font-mono text-xs text-muted-foreground">{m.code}</span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
