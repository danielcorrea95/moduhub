import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';
import { route as ziggyRoute, type Config as ZiggyConfig } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Moduhub';

/**
 * Renderização no servidor das páginas públicas (vendas, produtos, documentação), para buscadores e
 * robôs de IA lerem o texto sem executar JavaScript. Só roda para os componentes liberados em
 * App\Http\Ssr\PublicPagesSsrGateway; o painel continua renderizando no navegador.
 */
createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
        setup: ({ App, props }) => {
            // No navegador o route() vem do @routes; aqui vem da prop `ziggy`, com a URL desta requisição.
            const ziggy = page.props.ziggy as ZiggyConfig & { location: string };
            (globalThis as unknown as { route: unknown }).route = (name?: string, params?: unknown, absolute?: boolean) =>
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                ziggyRoute(name as any, params as any, absolute, { ...ziggy, location: new URL(ziggy.location) } as any);

            return <App {...props} />;
        },
    }),
);
