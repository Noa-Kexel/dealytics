import type { Directive, DirectiveBinding } from 'vue';

/** Options pour l'animation d'apparition au scroll (`v-reveal`). */
export interface RevealOptions {
    y?: number;
    x?: number;
    duration?: number;
    delay?: number;
    once?: boolean;
    threshold?: number;
    scale?: number;
}

interface RevealEl extends HTMLElement {
    __revealObserver?: IntersectionObserver;
}

const EASE = 'cubic-bezier(0.22, 1, 0.36, 1)';

function prefersReducedMotion(): boolean {
    return (
        typeof window !== 'undefined' &&
        typeof window.matchMedia === 'function' &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );
}

function hide(el: HTMLElement, o: Required<RevealOptions>): void {
    el.style.opacity = '0';
    el.style.transform = `translate3d(${o.x}px, ${o.y}px, 0) scale(${o.scale})`;
    el.style.willChange = 'opacity, transform';
}

function show(el: HTMLElement, o: Required<RevealOptions>): void {
    el.style.transition =
        `opacity ${o.duration}ms ${EASE} ${o.delay}ms, transform ${o.duration}ms ${EASE} ${o.delay}ms`;

    requestAnimationFrame(() => {
        el.style.opacity = '1';
        el.style.transform = 'translate3d(0, 0, 0) scale(1)';
    });
}

export const vReveal: Directive<RevealEl, RevealOptions | undefined> = {
    mounted(el, binding: DirectiveBinding<RevealOptions | undefined>) {
        const o: Required<RevealOptions> = {
            y: binding.value?.y ?? 24,
            x: binding.value?.x ?? 0,
            duration: binding.value?.duration ?? 600,
            delay: binding.value?.delay ?? 0,
            once: binding.value?.once ?? true,
            threshold: binding.value?.threshold ?? 0.12,
            scale: binding.value?.scale ?? 1,
        };

        // Pas d'anim si reduced-motion ou IntersectionObserver indisponible.
        if (prefersReducedMotion() || typeof IntersectionObserver === 'undefined') {
            return;
        }

        hide(el, o);

        const observer = new IntersectionObserver(
            (entries) => {
                for (const entry of entries) {
                    if (entry.isIntersecting) {
                        show(el, o);

                        if (o.once) {
                            observer.unobserve(el);
                        }
                    } else if (!o.once) {
                        hide(el, o);
                    }
                }
            },
            { threshold: o.threshold, rootMargin: '0px 0px -6% 0px' },
        );

        observer.observe(el);
        el.__revealObserver = observer;

        el.addEventListener(
            'transitionend',
            () => {
                el.style.willChange = 'auto';
            },
            { once: true },
        );
    },
    unmounted(el) {
        el.__revealObserver?.disconnect();
    },
};

export default vReveal;
