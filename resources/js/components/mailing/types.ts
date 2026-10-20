export type DomainStatus = 'pending' | 'verified' | 'failed';
export type RecordStatus = 'ok' | 'pending' | 'failed' | 'recommended';
export type RecordPurpose = 'dkim' | 'mail_from' | 'dmarc';

export interface DnsRecord {
    type: string;
    name: string;
    value: string;
    purpose: RecordPurpose;
    status: RecordStatus;
}

export interface SendingDomainData {
    domain: string;
    status: DomainStatus;
    from_name: string;
    from_local_part: string;
    reply_to: string | null;
    verified_at: string | null;
    last_checked_at: string | null;
    records: DnsRecord[];
}

export interface SmtpCredentialRow {
    id: string;
    name: string;
    username: string;
    last_four: string;
    created_by: string | null;
    created_at: string | null;
    last_used_at: string | null;
}

export interface SmtpData {
    enabled: boolean;
    host: string;
    ports: { port: number; encryption: string; label: string }[];
    max_credentials: number;
    credentials: SmtpCredentialRow[];
    reveal: { id: string; name: string; username: string; password: string } | null;
}

export interface EmailUsageData {
    plan: string | null;
    free: boolean;
    daily: { used: number; limit: number | null; remaining: number | null; resets_at: string };
    monthly: { used: number; limit: number | null; remaining: number | null; period_start: string; resets_at: string };
}

export interface SenderForm {
    from_name: string;
    from_local_part: string;
    reply_to: string;
}
