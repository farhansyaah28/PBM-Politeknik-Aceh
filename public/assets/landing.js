(() => {
    'use strict';

    const nav = document.querySelector('[data-landing-nav]');
    const hero = document.querySelector('[data-hero]');
    const revealItems = Array.from(document.querySelectorAll('[data-reveal]'));
    const sectionLinks = Array.from(document.querySelectorAll('.landing-nav a[href^="#"]'));
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!('IntersectionObserver' in window)) {
        revealItems.forEach((item) => item.classList.add('is-visible'));
        return;
    }

    if (nav && hero) {
        const heroObserver = new IntersectionObserver(([entry]) => {
            nav.classList.toggle('is-condensed', !entry.isIntersecting);
        }, { rootMargin: '-72px 0px 0px 0px', threshold: 0.08 });

        heroObserver.observe(hero);
    }

    if (reducedMotion) {
        revealItems.forEach((item) => item.classList.add('is-visible'));
    } else {
        revealItems.forEach((item) => item.classList.add('is-reveal-pending'));

        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            });
        }, { rootMargin: '0px 0px -3% 0px', threshold: 0.06 });

        revealItems.forEach((item) => revealObserver.observe(item));
    }

    if (sectionLinks.length > 0) {
        const linkById = new Map(sectionLinks.map((link) => [link.hash.slice(1), link]));
        const sections = Array.from(linkById.keys())
            .map((id) => document.getElementById(id))
            .filter(Boolean);
        const visibleSections = new Map();

        const sectionObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    visibleSections.set(entry.target.id, entry);
                } else {
                    visibleSections.delete(entry.target.id);
                }
            });

            const visible = Array.from(visibleSections.values())
                .sort((a, b) => Math.abs(a.boundingClientRect.top - 72) - Math.abs(b.boundingClientRect.top - 72))[0];

            if (!visible) {
                return;
            }

            sectionLinks.forEach((link) => {
                const active = link.hash === `#${visible.target.id}`;
                link.classList.toggle('is-active', active);
                if (active) {
                    link.setAttribute('aria-current', 'location');
                } else {
                    link.removeAttribute('aria-current');
                }
            });
        }, { rootMargin: '-22% 0px -58% 0px', threshold: [0.05, 0.2, 0.5] });

        sections.forEach((section) => sectionObserver.observe(section));
    }
})();
