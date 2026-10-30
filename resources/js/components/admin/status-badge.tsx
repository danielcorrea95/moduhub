import { cn } from '@/lib/utils';

const TONE_CLASSES = {
    signed: 'bg-signed-soft text-signed',
    pending: 'bg-pending-soft text-pending',
    rejected: 'bg-rejected-soft text-rejected',
    sent: 'bg-sent-soft text-sent',
    idle: 'bg-idle-soft text-idle',
};

export function StatusBadge({ label, tone }: { label: string; tone: keyof typeof TONE_CLASSES }) {
    return (
        <span className={cn('inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium', TONE_CLASSES[tone])}>
            {label}
        </span>
    );
}
