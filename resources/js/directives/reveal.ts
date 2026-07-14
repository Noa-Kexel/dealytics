import type { Directive, DirectiveBinding } from 'vue';

/**
 * `v-reveal` — AOS-style scroll reveal, built on IntersectionObserver so it plays
 * nicely with Inertia page transitions (no global re-init needed) and degrades
 * gracefully (content stays visible when motion is reduced or IO is missing).
 *
 * Usage:
 *   <div v-reveal />                       // fade + rise with defaults
 *   <div v-reveal="{ delay: 120 }" />      // stagger a card
 *   <div v-reveal="{ y: 0, scale: 0.96 }" />  // zoom-in
 */
export interface RevealOptions {
    /** Vertical offset to rise from, in px (default 24). */
    y?: number;
    /** Horizontal offset to slide from, in px (default 0). */
    x?: number;
    /** Transition duration in ms (default 600). */
    duration?: number;
    /** Delay before playing in ms — use to stagger siblings (default 0). */
    delay?: number;
    /** Play once then stop observing (default true). */
    once?: boolean;
    /** Visibility ratio that triggers the reveal (default 0.12). */
    threshold?: number;
    /** Initial scale to grow from (default 1 = no scaling). */
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

        // No animation when the user opts out of motion, or when IO is absent
        // (old browsers / SSR): leave the element in its natural visible state.
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

        // Release the compositor hint once the entrance has played.
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
