document.addEventListener('DOMContentLoaded', function() {
    initRevealOnScroll();
    initStickyHeaderShadow();
    initPremiumVitrinRotator();
    initHeroMockupVideo();
});

function initStickyHeaderShadow() {
    var nav = document.querySelector('.main-nav');
    if (!nav) {
        return;
    }

    var updateState = function() {
        if (window.scrollY > 24) {
            nav.classList.add('is-scrolled');
        } else {
            nav.classList.remove('is-scrolled');
        }
    };

    updateState();
    window.addEventListener('scroll', updateState, { passive: true });
}

function initRevealOnScroll() {
    var elements = document.querySelectorAll('.reveal-on-scroll');
    if (!elements.length) {
        return;
    }

    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        for (var i = 0; i < elements.length; i++) {
            elements[i].classList.add('is-visible');
        }
        return;
    }

    if (!('IntersectionObserver' in window)) {
        for (var j = 0; j < elements.length; j++) {
            elements[j].classList.add('is-visible');
        }
        return;
    }

    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0,
        rootMargin: '100px 0px 300px 0px'
    });

    for (var k = 0; k < elements.length; k++) {
        var el = elements[k];
        var rect = el.getBoundingClientRect();
        if (rect.top < window.innerHeight + 250) {
            el.classList.add('is-visible');
        } else {
            observer.observe(el);
        }
    }
}

function initPremiumVitrinRotator() {
    var root = document.getElementById('premiumVitrinRotator');
    if (!root) {
        return;
    }

    var slides = root.querySelectorAll('.premium-vitrin-slide');
    var dots = root.querySelectorAll('.premium-vitrin-rotator__dot');
    if (slides.length <= 1) {
        return;
    }

    var intervalMs = parseInt(root.getAttribute('data-interval'), 10) || 10000;
    var current = 0;
    var timer = null;

    function goTo(index) {
        if (index === current || index < 0 || index >= slides.length) {
            return;
        }

        slides[current].classList.remove('is-active');
        if (dots[current]) {
            dots[current].classList.remove('is-active');
        }

        current = index;

        slides[current].classList.add('is-active');
        if (dots[current]) {
            dots[current].classList.add('is-active');
        }
    }

    function next() {
        goTo((current + 1) % slides.length);
    }

    function startTimer() {
        if (timer) {
            clearInterval(timer);
        }
        timer = setInterval(next, intervalMs);
    }

    for (var i = 0; i < dots.length; i++) {
        (function(index) {
            dots[index].addEventListener('click', function() {
                goTo(index);
                startTimer();
            });
        })(i);
    }

    startTimer();
}

function initHeroMockupVideo() {
    var video = document.querySelector('.hero-mockup-video');
    if (!video || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    var attachSource = function() {
        if (video.querySelector('source')) {
            return;
        }
        var src = video.getAttribute('data-src');
        if (!src) {
            return;
        }
        var source = document.createElement('source');
        source.src = src;
        source.type = 'video/mp4';
        video.appendChild(source);
    };

    var startPlayback = function() {
        attachSource();
        video.preload = 'auto';
        if (video.readyState === 0) {
            video.load();
        }
        video.play().catch(function() {});
    };

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function(entries) {
            if (entries[0].isIntersecting) {
                startPlayback();
                observer.disconnect();
            }
        }, { threshold: 0.2 });
        observer.observe(video);
        return;
    }

    window.addEventListener('load', startPlayback);
}
