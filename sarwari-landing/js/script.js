/* ==================================================================
   SAWARI LANDING — SCRIPT v3  ★ Premium
   Vanilla JS · Parallax · Card Tilt · Counters · Lightbox
   ================================================================== */
;(function () {
    'use strict';

    /* ── 0. Feather Icons ── */
    if (window.feather) {
        feather.replace({ 'stroke-width': 2 });
    }

    /* ── Helpers ── */
    var raf = window.requestAnimationFrame || function (cb) { setTimeout(cb, 16); };

    function lerp(a, b, t) { return a + (b - a) * t; }

    /* ── 1. Scroll Progress Bar ── */
    var scrollProgress = document.getElementById('scrollProgress');

    function updateScrollProgress() {
        var scrollTop = window.scrollY;
        var docHeight = document.documentElement.scrollHeight - window.innerHeight;
        var pct = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
        if (scrollProgress) scrollProgress.style.width = pct + '%';
    }

    /* ── 2. Sticky Header ── */
    var header = document.getElementById('siteHeader');

    function updateHeader() {
        if (!header) return;
        header.classList.toggle('scrolled', window.scrollY > 60);
    }

    /* ── 3. Active Nav Link Highlighting ── */
    var navLinks = document.querySelectorAll('.nav-link');
    var sectionAnchors = [];

    navLinks.forEach(function (link) {
        var href = link.getAttribute('href');
        if (href && href.charAt(0) === '#') {
            var target = document.querySelector(href);
            if (target) sectionAnchors.push({ el: target, link: link });
        }
    });

    function updateActiveNav() {
        var scrollPos = window.scrollY + 160;
        var current = null;
        for (var i = 0; i < sectionAnchors.length; i++) {
            if (sectionAnchors[i].el.offsetTop <= scrollPos) current = sectionAnchors[i];
        }
        navLinks.forEach(function (l) { l.classList.remove('active'); });
        if (current) current.link.classList.add('active');
    }

    /* ── 4. Parallax — Hero & banner images ── */
    var heroImg   = document.querySelector('.hero-bg-image');
    var bannerImg = document.querySelector('.how-img-banner img');
    var statsImg  = document.querySelector('.stats-bg img');
    var ctaImg    = document.querySelector('.cta-bg img');
    var perspImg  = document.querySelector('.perspectives-bg img');

    function updateParallax() {
        var scrollY = window.scrollY;
        var winH    = window.innerHeight;

        // Hero: gentle zoom + shift
        if (heroImg) {
            var heroFactor = Math.min(scrollY / winH, 1);
            heroImg.style.transform = 'scale(' + (1 + heroFactor * 0.08) + ') translateY(' + (heroFactor * 30) + 'px)';
        }

        // Banner parallax
        [bannerImg, statsImg, ctaImg, perspImg].forEach(function (img) {
            if (!img) return;
            var rect = img.parentElement.getBoundingClientRect();
            if (rect.bottom < 0 || rect.top > winH) return;
            var factor = (rect.top / winH - 0.5) * -30;
            img.style.transform = 'translateY(' + factor + 'px) scale(1.05)';
        });
    }

    /* ── Combined scroll handler ── */
    var ticking = false;

    function onScroll() {
        if (!ticking) {
            raf(function () {
                updateScrollProgress();
                updateHeader();
                updateActiveNav();
                updateParallax();
                ticking = false;
            });
            ticking = true;
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    updateScrollProgress();
    updateHeader();
    updateActiveNav();

    /* ── 5. Mobile Menu ── */
    var hamburger = document.getElementById('hamburger');
    var navLinksContainer = document.getElementById('navLinks');
    var mobileOverlay = document.getElementById('mobileOverlay');

    function toggleMobile() {
        var isOpen = hamburger.classList.toggle('active');
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

    if (hamburger) hamburger.addEventListener('click', toggleMobile);
    if (mobileOverlay) mobileOverlay.addEventListener('click', closeMobile);

    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            if (navLinksContainer && navLinksContainer.classList.contains('mobile-active')) closeMobile();
        });
    });

    /* ── 6. Smooth Scroll ── */
    document.querySelectorAll('a[href^="#"]').forEach(function (anchor) {
        anchor.addEventListener('click', function (e) {
            var targetId = this.getAttribute('href');
            if (targetId === '#') return;
            var target = document.querySelector(targetId);
            if (!target) return;
            e.preventDefault();
            var headerHeight = header ? header.offsetHeight : 0;
            var targetPos = target.getBoundingClientRect().top + window.scrollY - headerHeight - 16;
            window.scrollTo({ top: targetPos, behavior: 'smooth' });
        });
    });

    /* ── 7. Intersection Observer — Reveals ── */
    var revealElements = document.querySelectorAll('.reveal');

    if ('IntersectionObserver' in window) {
        var revealObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        revealElements.forEach(function (el) { revealObserver.observe(el); });
    } else {
        revealElements.forEach(function (el) { el.classList.add('visible'); });
    }

    /* ── 8. Card Tilt Effect (3D hover) ── */
    var tiltCards = document.querySelectorAll('.feature-bullet, .step-card, .need-card:not(.need-card--wide), .perspective-card');
    var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!prefersReduced) {
        tiltCards.forEach(function (card) {
            card.addEventListener('mousemove', function (e) {
                var rect = card.getBoundingClientRect();
                var x = e.clientX - rect.left;
                var y = e.clientY - rect.top;
                var cx = rect.width / 2;
                var cy = rect.height / 2;
                var rotateX = ((y - cy) / cy) * -4;
                var rotateY = ((x - cx) / cx) * 4;
                card.style.transform = 'perspective(800px) rotateX(' + rotateX + 'deg) rotateY(' + rotateY + 'deg) translateY(-4px)';
            });

            card.addEventListener('mouseleave', function () {
                card.style.transform = '';
                card.style.transition = 'transform .5s cubic-bezier(.16,1,.3,1)';
                setTimeout(function () { card.style.transition = ''; }, 500);
            });
        });
    }

    /* ── 9. Gallery Lightbox ── */
    var lightbox = document.getElementById('lightbox');
    var lbImg    = document.getElementById('lbImg');
    var lbCaption = document.getElementById('lbCaption');
    var galleryItems = document.querySelectorAll('.gallery-item');
    var currentLbIdx = 0;

    function openLightbox(index) {
        if (!lightbox || !galleryItems.length) return;
        currentLbIdx = index;
        var item = galleryItems[index];
        var img  = item.querySelector('img');
        var cap  = item.getAttribute('data-caption') || '';
        lbImg.src = img.src;
        lbImg.alt = img.alt;
        lbCaption.textContent = cap;
        lightbox.hidden = false;
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

    function navigateLb(dir) {
        var total = galleryItems.length;
        currentLbIdx = (currentLbIdx + dir + total) % total;
        openLightbox(currentLbIdx);
    }

    galleryItems.forEach(function (item, i) {
        item.addEventListener('click', function () { openLightbox(i); });
    });

    var lbClose = lightbox ? lightbox.querySelector('.lb-close') : null;
    var lbPrev  = lightbox ? lightbox.querySelector('.lb-prev')  : null;
    var lbNext  = lightbox ? lightbox.querySelector('.lb-next')  : null;

    if (lbClose) lbClose.addEventListener('click', closeLightbox);
    if (lbPrev)  lbPrev.addEventListener('click', function () { navigateLb(-1); });
    if (lbNext)  lbNext.addEventListener('click', function () { navigateLb(1); });

    if (lightbox) {
        lightbox.addEventListener('click', function (e) {
            if (e.target === lightbox || e.target.classList.contains('lb-body')) closeLightbox();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (!lightbox || !lightbox.classList.contains('active')) return;
        if (e.key === 'Escape')     closeLightbox();
        if (e.key === 'ArrowLeft')  navigateLb(-1);
        if (e.key === 'ArrowRight') navigateLb(1);
    });

    /* ── 10. Animated Counters ── */
    var counterEls = document.querySelectorAll('[data-count]');

    function animateCounter(el) {
        var target = parseInt(el.getAttribute('data-count'), 10);
        if (isNaN(target)) return;
        var duration = 2000;
        var start = performance.now();

        function tick(now) {
            var elapsed = now - start;
            var progress = Math.min(elapsed / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 4);
            el.textContent = Math.round(eased * target);
            if (progress < 1) raf(tick);
            else el.textContent = target;
        }
        raf(tick);
    }

    if ('IntersectionObserver' in window && counterEls.length) {
        var counterObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animateCounter(entry.target);
                    counterObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        counterEls.forEach(function (el) { counterObserver.observe(el); });
    }

    /* ── 11. Comparison Bar Animation ── */
    var compareBars = document.querySelectorAll('.compare-bar');

    if ('IntersectionObserver' in window && compareBars.length) {
        // Set initial width to 0
        compareBars.forEach(function (bar) {
            bar.style.width = '0';
        });

        var barObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    var bars = entry.target.querySelectorAll('.compare-bar');
                    bars.forEach(function (bar, i) {
                        setTimeout(function () {
                            bar.style.width = bar.style.getPropertyValue('--bar-width');
                        }, i * 200);
                    });
                    barObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });

        var infographic = document.querySelector('.infographic-compare');
        if (infographic) barObserver.observe(infographic);
    }

})();
