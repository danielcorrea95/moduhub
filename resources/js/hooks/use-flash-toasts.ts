import type { Flash } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

/** Mostra as flash messages da sessão (success/error) como toast. */
export function useFlashToasts(flashOverride?: Flash) {
    const page = usePage<{ flash?: Flash }>();
    const flash = flashOverride ?? page.props.flash;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash]);
}
