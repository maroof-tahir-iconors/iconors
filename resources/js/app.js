import './bootstrap';
// Namespace import, not a bare `import 'bootstrap'`: the ESM build does not
// set `window.bootstrap` the way the UMD bundle does, so views that need to
// drive a modal programmatically (e.g. reopening one after a validation
// failure) had nothing to call. Declarative data-bs-* usage is unaffected.
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;
import 'bootstrap-icons/font/bootstrap-icons.css';

/**
 * Iconors frontend entry point.
 *
 * Only cheap, always-needed behaviour lives here. Expensive libraries
 * (GSAP, Lottie, Three.js) are dynamically imported by the modules that
 * need them, so a dashboard or checkout page never downloads a 3D engine.
 * Brief §4 and §34.
 */

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// A rough proxy for "can this device afford scroll animation?". Low core
// counts are typically budget mobile hardware where reveal animations
// cost more than they add.
const isLowPowered = (navigator.hardwareConcurrency || 4) <= 2;

const canAnimate = !prefersReducedMotion && !isLowPowered;

/* ---------------------------------------------------------------
 * Sticky header state
 * ------------------------------------------------------------- */
function initHeader() {
    const header = document.querySelector('[data-ico-header]');
    if (!header) return;

    let ticking = false;

    const update = () => {
        header.classList.toggle('is-scrolled', window.scrollY > 8);
        ticking = false;
    };

    window.addEventListener('scroll', () => {
        // rAF-throttled: a raw scroll handler fires far more often than
        // the browser paints, which is wasted work on every frame.
        if (!ticking) {
            window.requestAnimationFrame(update);
            ticking = true;
        }
    }, { passive: true });

    update();
}

/* ---------------------------------------------------------------
 * Colour mode
 * ------------------------------------------------------------- */
function initThemeToggle() {
    const root = document.documentElement;

    // Dark is the Iconors default. A stored preference always wins; we
    // deliberately do not fall back to the OS setting, so the brand's
    // intended look is what a first-time visitor sees.
    root.setAttribute('data-bs-theme', localStorage.getItem('ico-theme') || 'dark');

    document.querySelectorAll('[data-ico-theme-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-bs-theme', next);
            localStorage.setItem('ico-theme', next);
            btn.setAttribute('aria-pressed', String(next === 'dark'));
        });
    });
}

/* ---------------------------------------------------------------
 * Scroll reveal
 *
 * Elements start visible in CSS. We only add the hidden class once we
 * know we're going to animate — so if this script fails to load, the
 * content is still on screen rather than stuck at opacity 0.
 * ------------------------------------------------------------- */
function initReveal() {
    const targets = document.querySelectorAll('[data-ico-reveal]');
    if (!targets.length) return;

    const revealAll = () => targets.forEach((el) => {
        el.classList.remove('reveal-init');
        el.classList.add('reveal-in');
    });

    // Never hide anything we cannot guarantee we can un-hide.
    // A hidden/prerendering document does not run IntersectionObserver
    // callbacks, so opting into opacity:0 there strands the content.
    if (!canAnimate
        || !('IntersectionObserver' in window)
        || document.visibilityState !== 'visible') {
        revealAll();
        return;
    }

    targets.forEach((el) => el.classList.add('reveal-init'));

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;

            const delay = Number(entry.target.dataset.icoRevealDelay || 0);
            setTimeout(() => {
                entry.target.classList.remove('reveal-init');
                entry.target.classList.add('reveal-in');
            }, delay);
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

    targets.forEach((el) => observer.observe(el));

    // Dead-man's switch. Screenshot tools, background tabs and throttled
    // renderers can all suppress the observer indefinitely; without this
    // the whole page sits at opacity 0 and looks broken. Content winning
    // over animation is always the right trade.
    const failSafe = setTimeout(revealAll, 2500);
    window.addEventListener('pagehide', () => clearTimeout(failSafe), { once: true });
}

/* ---------------------------------------------------------------
 * Search: Ctrl/Cmd+K, and AJAX suggestions
 * ------------------------------------------------------------- */
function initSearch() {
    const input = document.querySelector('[data-ico-search-input]');
    const results = document.querySelector('[data-ico-search-results]');

    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            input?.focus();
            input?.select();
        }
        if (e.key === 'Escape' && results) results.hidden = true;
    });

    if (!input || !results) return;

    let timer = null;
    let controller = null;

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();

        if (q.length < 2) {
            results.hidden = true;
            return;
        }

        timer = setTimeout(async () => {
            // Abort the in-flight request before starting another, so a
            // fast typist can't have an older response overwrite a newer one.
            controller?.abort();
            controller = new AbortController();

            try {
                const res = await fetch(`/api/search/suggest?q=${encodeURIComponent(q)}`, {
                    signal: controller.signal,
                    headers: { Accept: 'application/json' },
                });

                if (!res.ok) return;

                const data = await res.json();
                renderSuggestions(results, data.suggestions || []);
            } catch (err) {
                if (err.name !== 'AbortError') results.hidden = true;
            }
        }, 220);
    });

    document.addEventListener('click', (e) => {
        if (!results.contains(e.target) && e.target !== input) results.hidden = true;
    });
}

function renderSuggestions(container, items) {
    if (!items.length) {
        container.hidden = true;
        return;
    }

    container.innerHTML = items
        .map((item) => `
            <a class="d-flex align-items-center gap-2 px-3 py-2 text-decoration-none ico-suggestion"
               href="${escapeAttr(item.url)}">
                <i class="bi bi-search text-muted small"></i>
                <span class="text-truncate">${escapeHtml(item.label)}</span>
                ${item.count != null ? `<span class="ms-auto small text-muted">${item.count}</span>` : ''}
            </a>
        `)
        .join('');

    container.hidden = false;
}

// Suggestions come from user-generated titles and tags, so they are
// escaped before touching innerHTML.
function escapeHtml(value) {
    const el = document.createElement('div');
    el.textContent = String(value ?? '');
    return el.innerHTML;
}

function escapeAttr(value) {
    return escapeHtml(value).replace(/"/g, '&quot;');
}

/* ---------------------------------------------------------------
 * Hero entrance
 *
 * CSS-driven, not GSAP. The previous version used gsap.from({opacity:0}),
 * which sets the element transparent and animates it back in — so the hero
 * headline and stats were genuinely half-invisible for the first ~800ms,
 * and any screenshot or slow paint caught them washed out.
 *
 * A CSS animation with `backwards` fill renders the final state by default
 * and only animates in. Nothing can get stuck invisible, and the homepage
 * no longer downloads ~70KB of GSAP it barely used.
 * ------------------------------------------------------------- */
function initHeroMotion() {
    const hero = document.querySelector('[data-ico-hero]');
    if (!hero) return;

    // Parallax is safe regardless — it only moves decorative blobs and
    // never affects the visibility of content.
    initParallax(hero);

    if (!canAnimate || document.visibilityState !== 'visible') return;

    const items = hero.querySelectorAll('[data-ico-hero-item]');

    items.forEach((el, i) => {
        el.style.animation = `ico-rise .6s cubic-bezier(.22,1,.36,1) ${i * 70}ms backwards`;
    });

    // `backwards` holds the element at opacity 0 until its animation runs.
    // If the renderer throttles animations (background tab, headless
    // capture) that hold never ends and the hero stays invisible. Strip
    // the inline animation once it should have finished.
    const longest = 600 + (items.length * 70) + 400;

    setTimeout(() => {
        items.forEach((el) => { el.style.animation = ''; });
    }, longest);
}

/** Plain transform parallax — no animation library needed. */
function initParallax(hero) {
    if (!window.matchMedia('(pointer: fine)').matches) return;

    const blobs = hero.querySelectorAll('[data-ico-parallax]');
    if (!blobs.length) return;

    let raf = null;
    let tx = 0;
    let ty = 0;

    hero.addEventListener('mousemove', (e) => {
        const rect = hero.getBoundingClientRect();
        tx = (e.clientX - rect.left) / rect.width - 0.5;
        ty = (e.clientY - rect.top) / rect.height - 0.5;

        // rAF-throttled so we transform at most once per painted frame.
        if (raf) return;
        raf = window.requestAnimationFrame(() => {
            blobs.forEach((blob, i) => {
                const depth = (i + 1) * 16;
                blob.style.transform = `translate3d(${tx * depth}px, ${ty * depth}px, 0)`;
            });
            raf = null;
        });
    }, { passive: true });
}

/* ---------------------------------------------------------------
 * Animated statistics (brief §4)
 * ------------------------------------------------------------- */
function initCounters() {
    const els = document.querySelectorAll('[data-ico-count]');
    if (!els.length || !canAnimate || !('IntersectionObserver' in window)) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;

            const el = entry.target;
            const target = Number(el.dataset.icoCount || 0);
            observer.unobserve(el);

            if (!target) return;

            const duration = 900;
            const start = performance.now();

            const step = (now) => {
                const progress = Math.min((now - start) / duration, 1);
                // easeOutExpo — fast start, settles gently on the real number
                const eased = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
                el.textContent = Math.round(target * eased).toLocaleString();
                if (progress < 1) window.requestAnimationFrame(step);
            };

            window.requestAnimationFrame(step);
        });
    }, { threshold: 0.4 });

    els.forEach((el) => observer.observe(el));
}

/* ---------------------------------------------------------------
 * Lottie previews
 *
 * Lottie JSON cannot render in an <img> — doing so produced a grid of
 * broken images, so each preview gets a real player.
 *
 * Animations autoplay as soon as they enter the viewport, and PAUSE the
 * moment they leave it. Running every player on the page at once would
 * mean dozens of concurrent render loops on a long listing; pausing
 * off-screen ones keeps the visible animation lively while the cost stays
 * proportional to what is actually on screen (brief §4).
 * ------------------------------------------------------------- */
function initLottiePreviews() {
    const tiles = document.querySelectorAll('[data-ico-lottie]');
    if (!tiles.length) return;

    let lottiePromise = null;
    const loadLottie = () => (lottiePromise ??= import('lottie-web').then((m) => m.default || m));

    const players = new WeakMap();
    const loading = new WeakSet();

    const start = async (tile) => {
        const existing = players.get(tile);

        if (existing) {
            existing.play();
            return;
        }

        if (loading.has(tile)) return;
        loading.add(tile);

        try {
            const lottie = await loadLottie();

            const animation = lottie.loadAnimation({
                container: tile,
                renderer: 'svg',
                loop: true,
                autoplay: true,
                path: tile.dataset.icoLottie,
                rendererSettings: { progressiveLoad: true },
            });

            players.set(tile, animation);
            tile.classList.add('is-loaded');

            // Remove the loading placeholder outright rather than relying
            // on a CSS class to hide it. The stylesheet rule was being
            // out-competed in the cascade and left every spinner sitting
            // on top of a playing animation — deleting the node cannot lose
            // that argument.
            animation.addEventListener('DOMLoaded', () => {
                tile.querySelector('.ico-lottie__poster')?.remove();
            });
        } catch (e) {
            // A failed player must not leave an empty box — the poster
            // icon already in the markup stays visible.
            tile.classList.add('is-failed');
        } finally {
            loading.delete(tile);
        }
    };

    // Reduced motion: render the first frame so the artwork is still
    // visible, but never animate it.
    if (!canAnimate) {
        tiles.forEach(async (tile) => {
            try {
                const lottie = await loadLottie();
                const animation = lottie.loadAnimation({
                    container: tile, renderer: 'svg', loop: false, autoplay: false,
                    path: tile.dataset.icoLottie,
                });
                animation.addEventListener('DOMLoaded', () => animation.goToAndStop(0, true));
                tile.classList.add('is-loaded');
            } catch (e) {
                tile.classList.add('is-failed');
            }
        });

        return;
    }

    if (!('IntersectionObserver' in window)) {
        tiles.forEach(start);
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                start(entry.target);
            } else {
                players.get(entry.target)?.pause();
            }
        });
    }, { rootMargin: '150px 0px', threshold: 0.01 });

    tiles.forEach((tile) => observer.observe(tile));

    // Dead-man's switch, same reasoning as the scroll reveal: a hidden,
    // prerendering or throttled document never fires IntersectionObserver,
    // which would leave every animation stuck on its loading spinner
    // forever.
    //
    // If the observer has produced NOTHING after a grace period, we stop
    // trusting it and start the tiles ourselves. Capped, because the point
    // of the observer was to avoid running dozens of players at once — but
    // a capped number of running animations beats a page of dead spinners.
    setTimeout(() => {
        const anyStarted = tiles.length > 0
            && Array.prototype.some.call(tiles, (t) => players.has(t) || loading.has(t));

        if (anyStarted) return;

        Array.prototype.slice.call(tiles, 0, 12).forEach(start);
    }, 1500);
}

/* ---------------------------------------------------------------
 * Carousels
 *
 * Swiper is dynamically imported and only when a carousel is actually
 * on the page, so pages without one never download it (brief §34).
 * ------------------------------------------------------------- */
async function initCarousels() {
    const carousels = document.querySelectorAll('[data-ico-swiper]');
    if (!carousels.length) return;

    const [{ default: Swiper }, { Navigation, Pagination, Autoplay, A11y, Keyboard }] = await Promise.all([
        import('swiper'),
        import('swiper/modules'),
    ]);

    await import('swiper/css');
    await import('swiper/css/navigation');
    await import('swiper/css/pagination');

    carousels.forEach((el) => {
        const perView = Number(el.dataset.icoPerView || 6);

        new Swiper(el, {
            modules: [Navigation, Pagination, A11y, Keyboard, ...(canAnimate ? [Autoplay] : [])],
            slidesPerView: 2,
            spaceBetween: 12,
            grabCursor: true,
            // Keyboard + a11y modules give arrow-key control and proper
            // roles, so the carousel is not mouse-only.
            keyboard: { enabled: true },
            a11y: { enabled: true },
            navigation: {
                nextEl: el.querySelector('.swiper-button-next'),
                prevEl: el.querySelector('.swiper-button-prev'),
            },
            pagination: { el: el.querySelector('.swiper-pagination'), clickable: true },
            // Autoplay only when motion is welcome, and it stops the moment
            // the user interacts rather than fighting them.
            ...(canAnimate ? {
                autoplay: { delay: 3800, disableOnInteraction: true, pauseOnMouseEnter: true },
            } : {}),
            breakpoints: {
                576:  { slidesPerView: 3, spaceBetween: 12 },
                768:  { slidesPerView: 4, spaceBetween: 16 },
                1200: { slidesPerView: perView, spaceBetween: 16 },
            },
        });
    });
}

/* ---------------------------------------------------------------
 * Magnetic buttons
 *
 * The button leans a few pixels toward the cursor. Small enough to feel
 * responsive rather than gimmicky, and skipped entirely on touch and
 * under reduced-motion.
 * ------------------------------------------------------------- */
function initMagneticButtons() {
    if (!canAnimate || !window.matchMedia('(pointer: fine)').matches) return;

    document.querySelectorAll('[data-ico-magnetic]').forEach((btn) => {
        let raf = null;

        btn.addEventListener('mousemove', (e) => {
            const rect = btn.getBoundingClientRect();
            const x = (e.clientX - rect.left - rect.width / 2) * 0.22;
            const y = (e.clientY - rect.top - rect.height / 2) * 0.32;

            if (raf) return;
            raf = requestAnimationFrame(() => {
                btn.style.transform = `translate(${x}px, ${y}px)`;
                raf = null;
            });
        });

        btn.addEventListener('mouseleave', () => { btn.style.transform = ''; });
    });
}

/* ---------------------------------------------------------------
 * Card tilt on pointer
 * ------------------------------------------------------------- */
function initTilt() {
    if (!canAnimate || !window.matchMedia('(pointer: fine)').matches) return;

    document.querySelectorAll('[data-ico-tilt]').forEach((card) => {
        let raf = null;

        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const px = (e.clientX - rect.left) / rect.width - 0.5;
            const py = (e.clientY - rect.top) / rect.height - 0.5;

            if (raf) return;
            raf = requestAnimationFrame(() => {
                card.style.transform =
                    `perspective(800px) rotateX(${-py * 7}deg) rotateY(${px * 7}deg) translateY(-4px)`;
                raf = null;
            });
        });

        card.addEventListener('mouseleave', () => { card.style.transform = ''; });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initHeader();
    initThemeToggle();
    initReveal();
    initSearch();
    initHeroMotion();
    initCounters();
    initLottiePreviews();
    initCarousels();
    initMagneticButtons();
    initTilt();
});
