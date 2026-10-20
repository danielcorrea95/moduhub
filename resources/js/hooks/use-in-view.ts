import { useEffect, useRef, useState } from 'react';

/**
 * Vira true na primeira vez que o elemento aparece na tela e não volta. Com movimento reduzido,
 * já nasce true: a animação é pulada e o estado final aparece direto.
 */
export function useInView<T extends Element>(threshold = 0.35) {
    const ref = useRef<T>(null);
    const [inView, setInView] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el) return;
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
            setInView(true);
            return;
        }
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setInView(true);
                    observer.disconnect();
                }
            },
            { threshold },
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, [threshold]);

    return [ref, inView] as const;
}
