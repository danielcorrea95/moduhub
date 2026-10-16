import { cn } from '@/lib/utils';
import { Check, Copy } from 'lucide-react';
import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';

export type CodeLanguage = 'curl' | 'javascript' | 'php';

export const LANGUAGE_LABEL: Record<CodeLanguage, string> = {
    curl: 'cURL',
    javascript: 'JavaScript',
    php: 'PHP',
};

const STORAGE_KEY = 'moduhub.docs.language';

/** A linguagem escolhida vale para a página toda: trocar num exemplo troca em todos. */
const LanguageContext = createContext<{
    language: CodeLanguage;
    setLanguage: (l: CodeLanguage) => void;
}>({ language: 'curl', setLanguage: () => {} });

export function CodeLanguageProvider({ children }: { children: ReactNode }) {
    const [language, setLanguageState] = useState<CodeLanguage>('curl');

    useEffect(() => {
        try {
            const saved = localStorage.getItem(STORAGE_KEY) as CodeLanguage | null;
            if (saved && saved in LANGUAGE_LABEL) setLanguageState(saved);
        } catch {
            // Sem storage (aba privada, bloqueio): fica no padrão.
        }
    }, []);

    const setLanguage = (l: CodeLanguage) => {
        setLanguageState(l);
        try {
            localStorage.setItem(STORAGE_KEY, l);
        } catch {
            // idem
        }
    };

    return <LanguageContext.Provider value={{ language, setLanguage }}>{children}</LanguageContext.Provider>;
}

function CopyCode({ value }: { value: string }) {
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        if (!copied) return;
        const t = setTimeout(() => setCopied(false), 2000);
        return () => clearTimeout(t);
    }, [copied]);

    return (
        <button
            type="button"
            onClick={() => navigator.clipboard.writeText(value).then(() => setCopied(true))}
            className="flex items-center gap-1.5 rounded-md px-2 py-1 text-xs text-white/60 transition-colors hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-white/60"
            aria-label={copied ? 'Código copiado' : 'Copiar código'}
        >
            {copied ? <Check className="size-3.5" /> : <Copy className="size-3.5" />}
            {copied ? 'Copiado' : 'Copiar'}
        </button>
    );
}

function Frame({ title, toolbar, code }: { title?: ReactNode; toolbar?: ReactNode; code: string }) {
    return (
        <div className="overflow-hidden rounded-xl bg-[#1b2230] text-[#e6e9f0] shadow-xs">
            <div className="flex min-h-10 items-center justify-between gap-2 border-b border-white/10 px-2 pl-4">
                <div className="flex min-w-0 items-center gap-1 text-xs text-white/60">{toolbar ?? title}</div>
                <CopyCode value={code} />
            </div>
            <pre className="overflow-x-auto px-4 py-3.5 font-mono text-[0.8125rem] leading-relaxed">
                <code>{code}</code>
            </pre>
        </div>
    );
}

/** Exemplo de requisição nas três linguagens, com abas sincronizadas na página inteira. */
export function CodeSample({ samples }: { samples: Record<CodeLanguage, string> }) {
    const { language, setLanguage } = useContext(LanguageContext);

    return (
        <Frame
            code={samples[language]}
            toolbar={
                <div role="tablist" aria-label="Linguagem do exemplo" className="-ml-2 flex gap-0.5">
                    {(Object.keys(LANGUAGE_LABEL) as CodeLanguage[]).map((l) => (
                        <button
                            key={l}
                            type="button"
                            role="tab"
                            aria-selected={language === l}
                            onClick={() => setLanguage(l)}
                            className={cn(
                                'rounded-md px-2.5 py-1 text-xs transition-colors focus-visible:outline-2 focus-visible:outline-white/60',
                                language === l ? 'bg-white/12 text-white' : 'text-white/55 hover:text-white',
                            )}
                        >
                            {LANGUAGE_LABEL[l]}
                        </button>
                    ))}
                </div>
            }
        />
    );
}

/** Bloco de código simples (resposta JSON, payload de webhook, etc.). */
export function CodeBlock({ title, code }: { title: string; code: string }) {
    return <Frame title={title} code={code} />;
}
