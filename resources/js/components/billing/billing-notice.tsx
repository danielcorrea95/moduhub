import { formatDate } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

/** Faixa no topo do painel: teste grátis acabando, atraso, bloqueio ou fim do acesso após cancelar. */
export function BillingNotice() {
    const { billingNotice: n } = usePage<SharedData>().props;
    if (!n) return null;

    const message = n.blocked
        ? n.status === 'suspended' || n.status === 'incomplete'
            ? 'Workspace só para consulta: a assinatura não está paga. Nada novo pode ser criado ou alterado.'
            : 'A assinatura foi cancelada e o workspace está só para consulta.'
        : n.status === 'past_due'
          ? `Há uma cobrança da Moduhub vencida. Pague até ${formatDate(n.grace_ends_at)} para não ter o workspace bloqueado.`
          : n.status === 'cancelled'
            ? `Assinatura cancelada. O workspace fica liberado até ${formatDate(n.access_ends_at)}.`
            : `O teste grátis acaba em ${formatDate(n.trial_ends_at)}. Escolha um plano para continuar usando.`;

    const danger = n.blocked || n.status === 'past_due';

    return (
        <div
            role="status"
            className={
                danger
                    ? 'flex flex-wrap items-center gap-x-3 gap-y-1 border-b bg-rejected-soft px-4 py-2.5 text-sm text-rejected md:px-8'
                    : 'flex flex-wrap items-center gap-x-3 gap-y-1 border-b bg-pending-soft px-4 py-2.5 text-sm text-pending md:px-8'
            }
        >
            <AlertTriangle className="size-4 shrink-0" />
            <span className="flex-1">{message}</span>
            {n.can_manage ? (
                <Link href={route('billing.show')} className="font-medium underline underline-offset-4">
                    {n.status === 'trial' ? 'Ver planos' : 'Ir para a assinatura'}
                </Link>
            ) : (
                <span className="text-xs opacity-80">Fale com o dono do workspace.</span>
            )}
        </div>
    );
}
