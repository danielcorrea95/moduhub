/** Formatação de documentos e valores fiscais para exibição (pt-BR). */

export function formatCnpj(value: string): string {
    const v = value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
    if (v.length !== 14) return value;
    return `${v.slice(0, 2)}.${v.slice(2, 5)}.${v.slice(5, 8)}/${v.slice(8, 12)}-${v.slice(12)}`;
}

export function formatCpf(value: string): string {
    const v = value.replace(/\D/g, '');
    if (v.length !== 11) return value;
    return `${v.slice(0, 3)}.${v.slice(3, 6)}.${v.slice(6, 9)}-${v.slice(9)}`;
}

/** CPF ou CNPJ conforme o tamanho. */
export function formatTaxDocument(value: string | null | undefined): string {
    if (!value) return '—';
    // NIF de estrangeiro também cai aqui (taker_document): sem máscara.
    if (value.length === 11 && /^\d+$/.test(value)) return formatCpf(value);
    if (value.length === 14) return formatCnpj(value);
    return value;
}

const money = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

export function formatMoney(value: string | number | null | undefined): string {
    if (value === null || value === undefined || value === '') return '—';
    return money.format(Number(value));
}

/** Chave de acesso de 50 dígitos em blocos de 4, como no DANFSe. */
export function formatAccessKey(value: string | null | undefined): string {
    if (!value) return '—';
    return value.replace(/(\d{4})(?=\d)/g, '$1 ');
}
