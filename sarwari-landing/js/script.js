/* ==================================================================
   SAWARI LANDING — SCRIPT v2
   Vanilla JS · No frameworks · Modular IIFE
   ================================================================== */
;(function () {
    'use strict';

    /* ── 0. Feather Icons ── */
    if (window.feather) {
        feather.replace({ 'stroke-width': 2 });
    }

    /* ── 1. Scroll Progress Bar ── */
    const scrollProgress = document.getElementById('scrollProgress');

    function updateScrollProgress() {
        const scrollTop = window.scrollY;
        const docHeight = document.documentElement.scrollHeight - window.innerHeight;
        const pct = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
        if (scrollProgress) {
            scrollProgress.style.width = pct + '%';
        }
    }

    /* ── 2. Sticky Header ── */
    const header = document.getElementById('siteHeader');

    function updateHeader() {
        if (!header) return;
        if (window.scrollY > 60) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    }

    /* ── 3. Active Nav Link Highlighting ── */
    const navLinks = document.querySelectorAll('.nav-link');
    const sections = [];

    navLinks.forEach(function (link) {
        const href = link.getAttribute('href');
        if (href && href.startsWith('#')) {
            const target = document.querySelector(href);
            if (target) sections.push({ el: target, link: link });
        }
    });

    function updateActiveNav() {
        const scrollPos = window.scrollY + 160;
        let current = null;

        for (let i = 0; i < sections.length; i++) {
            if (sections[i].el.offsetTop <= scrollPos) {
                current = sections[i];
            }
        }

        navLinks.forEach(function (l) { l.classList.remove('active'); });
        if (current) current.link.classList.add('active');
    }

    /* ── Combined scroll handler ── */
    let ticking = false;

    function onScroll() {
        if (!ticking) {
            window.requestAnimationFrame(function () {
                updateScrollProgress();
                updateHeader();
                updateActiveNav();
                ticking = false;
            });
            ticking = true;
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });

    // Initial call
    updateScrollProgress();
    updateHeader();
    updateActiveNav();

    /* ── 4. Mobile Menu ── */
    const hamburger = document.getElementById('hamburger');
    const navLinksContainer = document.getElementById('navLinks');
    const mobileOverlay = document.getElementById('mobileOverlay');

    function toggleMobile() {
        const isOpen = hamburger.classList.toggle('active');
        navLinksContainer.classList.toggle('mobile-active');
        mobileOverlay.classList.toggle('active');
        hamburger.setAttribute('aria-expanded', isOpen);
        mobileOverlay.setAttribute('aria-hidden', !isOpen);
        document.body.style.overflow = isOpen ? 'hidden' : '';
    }

    function closeMobile() {
        hamburger.classList.remove('active');
        navLinksContainer.classList.remove('mobile-active');
        mobileOverlay.classList.remove('active');
        hamburger.setAttribute('aria-expanded', 'false');
        mobileOverlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    if (hamburger) {
        hamburger.addEventListener('click', toggleMobile);
    }

    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', closeMobile);
    }

    // Close menu when clicking nav link
    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            if (navLinksContainer.classList.contains('mobile-active')) {
                closeMobile();
            }
        });
    });

    /* ── 5. Smooth Scroll for Anchor Links ── */
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            const target = document.querySelector(targetId);
            if (!target) return;

            e.preventDefault();
            const headerHeight = header ? header.offsetHeight : 0;
            const targetPos = target.getBoundingClientRect().top + window.scrollY - headerHeight - 16;

            window.scrollTo({
                top: targetPos,
                behavior: 'smooth'
            });
        });
    });

    /* ── 6. Intersection Observer — Scroll Reveals ── */
    const revealElements = document.querySelectorAll('.reveal');

    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.12,
            rootMargin: '0px 0px -40px 0px'
        });

        revealElements.forEach(function (el) {
            revealObserver.observe(el);
        });
    } else {
        // Fallback — show everything
        revealElements.forEach(function (el) {
            el.classList.add('visible');
        });
    }

    /* ── 7. Gallery Lightbox ── */
    const lightbox = document.getElementById('lightbox');
    const lbImg = document.getElementById('lbImg');
    const lbCaption = document.getElementById('lbCaption');
    const galleryItems = document.querySelectorAll('.gallery-item');
    let currentLightboxIndex = 0;

    function openLightbox(index) {
        if (!lightbox || !galleryItems.length) return;
        currentLightboxIndex = index;
        const item = galleryItems[index];
        const img = item.querySelector('img');
        const caption = item.getAttribute('data-caption') || '';

        lbImg.src = img.src;
        lbImg.alt = img.alt;
        lbCaption.textContent = caption;

        lightbox.hidden = false;
        // Force reflow, then add active
        void lightbox.offsetWidth;
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        if (!lightbox) return;
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(function () {
            lightbox.hidden = true;
            lbImg.src = '';
        }, 300);
    }

    function navigateLightbox(direction) {
        const total = galleryItems.length;
        currentLightboxIndex = (currentLightboxIndex + direction + total) % total;
        openLightbox(currentLightboxIndex);
    }

    galleryItems.forEach(function (item, i) {
        item.addEventListener('click', function () {
            openLightbox(i);
        });
    });

    var lbClose = lightbox ? lightbox.querySelector('.lb-close') : null;
    var lbPrev = lightbox ? lightbox.querySelector('.lb-prev') : null;
    var lbNext = lightbox ? lightbox.querySelector('.lb-next') : null;

    if (lbClose) lbClose.addEventListener('click', closeLightbox);
    if (lbPrev)  lbPrev.addEventListener('click', function () { navigateLightbox(-1); });
    if (lbNext)  lbNext.addEventListener('click', function () { navigateLightbox(1); });

    // Close on backdrop click
    if (lightbox) {
        lightbox.addEventListener('click', function (e) {
            if (e.target === lightbox || e.target.classList.contains('lb-body')) {
                closeLightbox();
            }
        });
    }

    // Keyboard navigation
    document.addEventListener('keydown', function (e) {
        if (!lightbox || !lightbox.classList.contains('active')) return;
        if (e.key === 'Escape') closeLightbox();
        if (e.key === 'ArrowLeft')  navigateLightbox(-1);
        if (e.key === 'ArrowRight') navigateLightbox(1);
    });

    /* ── 8. Animated Counters ── */
    const counterElements = document.querySelectorAll('[data-count]');

    function animateCounter(el) {
        const target = parseInt(el.getAttribute('data-count'), 10);
        if (isNaN(target)) return;

        const duration = 1800;
        const start = performance.now();

        function tick(now) {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            // Ease out
            const eased = 1 - Math.pow(1 - progress, 3);
            const current = Math.round(eased * target);
            el.textContent = current;

            if (progress < 1) {
                requestAnimationFrame(tick);
            } else {
                el.textContent = target;
            }
        }

        requestAnimationFrame(tick);
    }

    if ('IntersectionObserver' in window && counterElements.length) {
        var counterObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    counterObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counterElements.forEach(function (el) {
            counterObserver.observe(el);
        });
    }

})();
