import type { CodeLanguage } from '@/components/docs/code-block';

type Json = string | number | boolean | null | Json[] | { [key: string]: Json };

export interface SampleRequest {
    method: 'GET' | 'POST' | 'PUT' | 'DELETE';
    path: string;
    query?: Record<string, string>;
    /** Corpo JSON. */
    json?: { [key: string]: Json };
    /**
     * Upload de arquivo: o arquivo vai no campo `field` (padrão `file`, um PDF) e o resto como
     * campos do formulário.
     */
    multipart?: { file: string; fields: { [key: string]: Json }; field?: string; contentType?: string };
    /** Resposta binária (PDF) gravada em arquivo. */
    saveAs?: string;
}

function uploadField(req: SampleRequest): { field: string; contentType: string } {
    return { field: req.multipart?.field ?? 'file', contentType: req.multipart?.contentType ?? 'application/pdf' };
}

const KEY_ENV = 'MODUHUB_API_KEY';

function url(baseUrl: string, req: SampleRequest): string {
    const qs = req.query ? `?${new URLSearchParams(req.query).toString()}` : '';
    return `${baseUrl}${req.path}${qs}`;
}

/** Achata objetos/arrays no formato de formulário do PHP: signers[0][name]. */
function flattenFields(fields: { [key: string]: Json }, prefix = ''): [string, string][] {
    return Object.entries(fields).flatMap(([k, v]) => {
        const key = prefix ? `${prefix}[${k}]` : k;
        if (v !== null && typeof v === 'object') {
            return flattenFields(Array.isArray(v) ? Object.fromEntries(v.map((x, i) => [i, x])) : v, key);
        }
        return [[key, v === true ? '1' : v === false ? '0' : String(v ?? '')]];
    });
}

function curl(baseUrl: string, req: SampleRequest): string {
    const lines = [`curl -X ${req.method} "${url(baseUrl, req)}"`, `  -H "Authorization: Bearer $${KEY_ENV}"`, '  -H "Accept: application/json"'];

    if (req.json) {
        lines.push('  -H "Content-Type: application/json"');
        lines.push(`  -d '${JSON.stringify(req.json, null, 2).replace(/\n/g, '\n  ')}'`);
    }
    if (req.multipart) {
        lines.push(`  -F "${uploadField(req).field}=@${req.multipart.file}"`);
        for (const [k, v] of flattenFields(req.multipart.fields)) lines.push(`  -F "${k}=${v}"`);
    }
    if (req.saveAs) lines.push(`  -o ${req.saveAs}`);

    return lines.join(' \\\n');
}

function javascript(baseUrl: string, req: SampleRequest): string {
    const head = [`const response = await fetch('${url(baseUrl, req)}', {`, `  method: '${req.method}',`, '  headers: {', `    Authorization: \`Bearer \${process.env.${KEY_ENV}}\`,`, "    Accept: 'application/json',"];
    const out: string[] = [];

    if (req.multipart) {
        const { field, contentType } = uploadField(req);
        out.push("import { readFile } from 'node:fs/promises';", '', 'const form = new FormData();');
        out.push(`form.append('${field}', new Blob([await readFile('${req.multipart.file}')], { type: '${contentType}' }), '${req.multipart.file}');`);
        for (const [k, v] of flattenFields(req.multipart.fields)) out.push(`form.append('${k}', '${v}');`);
        out.push('', ...head, '  },', '  body: form,', '});');
    } else if (req.json) {
        out.push(...head, "    'Content-Type': 'application/json',", '  },');
        out.push(`  body: JSON.stringify(${JSON.stringify(req.json, null, 2).replace(/\n/g, '\n  ')}),`, '});');
    } else {
        out.push(...head, '  },', '});');
    }

    if (req.saveAs) {
        out.unshift("import { writeFile } from 'node:fs/promises';", '');
        out.push('', `await writeFile('${req.saveAs}', Buffer.from(await response.arrayBuffer()));`);
    } else if (req.method === 'DELETE') {
        out.push('', '// 204 sem corpo quando der certo');
        out.push('if (!response.ok) console.error(await response.json());');
    } else {
        out.push('', 'const body = await response.json();');
    }

    return out.join('\n');
}

function phpValue(v: Json, indent: string): string {
    if (v === null) return 'null';
    if (typeof v === 'boolean') return v ? 'true' : 'false';
    if (typeof v === 'number') return String(v);
    if (typeof v === 'string') return `'${v.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
    const inner = indent + '    ';
    if (Array.isArray(v)) {
        return `[\n${v.map((x) => `${inner}${phpValue(x, inner)},`).join('\n')}\n${indent}]`;
    }
    return `[\n${Object.entries(v).map(([k, x]) => `${inner}'${k}' => ${phpValue(x, inner)},`).join('\n')}\n${indent}]`;
}

function php(baseUrl: string, req: SampleRequest): string {
    const out = ['use Illuminate\\Support\\Facades\\Http;', '', `$response = Http::withToken(env('${KEY_ENV}'))`, '    ->acceptJson()'];
    const target = `'${baseUrl}${req.path}'`;
    const verb = req.method.toLowerCase();

    if (req.multipart) {
        out.push(`    ->attach('${uploadField(req).field}', file_get_contents('${req.multipart.file}'), '${req.multipart.file}')`);
        out.push(`    ->post(${target}, ${phpValue(req.multipart.fields, '    ')});`);
    } else if (req.json) {
        out.push(`    ->${verb}(${target}, ${phpValue(req.json, '    ')});`);
    } else if (req.query) {
        out.push(`    ->${verb}(${target}, ${phpValue(req.query, '    ')});`);
    } else if (req.saveAs) {
        out.push(`    ->sink('${req.saveAs}')`, `    ->${verb}(${target});`);
    } else {
        out.push(`    ->${verb}(${target});`);
    }

    if (!req.saveAs) out.push('', req.method === 'DELETE' ? '$response->throw(); // 204 quando der certo' : '$body = $response->throw()->json();');

    return out.join('\n');
}

export function buildSamples(baseUrl: string, req: SampleRequest): Record<CodeLanguage, string> {
    return {
        curl: curl(baseUrl, req),
        javascript: javascript(baseUrl, req),
        php: php(baseUrl, req),
    };
}
