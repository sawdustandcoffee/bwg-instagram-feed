/**
 * BWG Instagram Feed - Frontend JavaScript
 *
 * @package BWG_Instagram_Feed
 */

(function() {
    'use strict';

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        BWGIGFFrontend.init();
    });

    var BWGIGFFrontend = {
        init: function() {
            this.initSliders();
            this.initPopups();
            this.initLazyLoading();
        },

        initSliders: function() {
            var sliders = document.querySelectorAll('.bwg-igf-slider');

            sliders.forEach(function(slider) {
                new BWGIGFSlider(slider);
            });
        },

        initPopups: function() {
            var feeds = document.querySelectorAll('.bwg-igf-feed[data-popup="true"]');

            feeds.forEach(function(feed) {
                new BWGIGFPopup(feed);
            });
        },

        initLazyLoading: function() {
            if ('IntersectionObserver' in window) {
                var images = document.querySelectorAll('.bwg-igf-item img[data-src]');

                var observer = new IntersectionObserver(function(entries) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;
                            img.src = img.dataset.src;
                            img.removeAttribute('data-src');
                            observer.unobserve(img);
                        }
                    });
                });

                images.forEach(function(img) {
                    observer.observe(img);
                });
            }
        }
    };

    // Slider class
    function BWGIGFSlider(element) {
        this.element = element;
        this.track = element.querySelector('.bwg-igf-slider-track');
        this.slides = element.querySelectorAll('.bwg-igf-slider-slide');
        this.prevBtn = element.querySelector('.bwg-igf-slider-prev');
        this.nextBtn = element.querySelector('.bwg-igf-slider-next');
        this.dots = element.querySelectorAll('.bwg-igf-slider-dot');

        this.currentIndex = 0;
        this.slidesToShow = parseInt(element.dataset.slidesToShow) || 1;
        this.autoplay = element.dataset.autoplay === 'true';
        this.autoplaySpeed = parseInt(element.dataset.autoplaySpeed) || 3000;
        this.infinite = element.dataset.infinite === 'true';

        this.init();
    }

    BWGIGFSlider.prototype = {
        init: function() {
            this.bindEvents();
            this.updateSlideWidth();

            if (this.autoplay) {
                this.startAutoplay();
            }

            // Handle touch events
            this.initTouch();
        },

        bindEvents: function() {
            var self = this;

            if (this.prevBtn) {
                this.prevBtn.addEventListener('click', function() {
                    self.prev();
                });
            }

            if (this.nextBtn) {
                this.nextBtn.addEventListener('click', function() {
                    self.next();
                });
            }

            this.dots.forEach(function(dot, index) {
                dot.addEventListener('click', function() {
                    self.goTo(index);
                });
            });

            // Pause autoplay on hover
            if (this.autoplay) {
                this.element.addEventListener('mouseenter', function() {
                    self.stopAutoplay();
                });

                this.element.addEventListener('mouseleave', function() {
                    self.startAutoplay();
                });
            }
        },

        updateSlideWidth: function() {
            var width = 100 / this.slidesToShow;

            this.slides.forEach(function(slide) {
                slide.style.width = width + '%';
            });
        },

        goTo: function(index) {
            var maxIndex = this.slides.length - this.slidesToShow;

            if (this.infinite) {
                if (index < 0) index = maxIndex;
                if (index > maxIndex) index = 0;
            } else {
                if (index < 0) index = 0;
                if (index > maxIndex) index = maxIndex;
            }

            this.currentIndex = index;
            var offset = -(index * (100 / this.slidesToShow));
            this.track.style.transform = 'translateX(' + offset + '%)';

            this.updateDots();
        },

        next: function() {
            this.goTo(this.currentIndex + 1);
        },

        prev: function() {
            this.goTo(this.currentIndex - 1);
        },

        updateDots: function() {
            var self = this;

            this.dots.forEach(function(dot, index) {
                dot.classList.toggle('active', index === self.currentIndex);
            });
        },

        startAutoplay: function() {
            var self = this;

            this.autoplayInterval = setInterval(function() {
                self.next();
            }, this.autoplaySpeed);
        },

        stopAutoplay: function() {
            clearInterval(this.autoplayInterval);
        },

        initTouch: function() {
            var self = this;
            var startX, moveX;

            this.element.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
            });

            this.element.addEventListener('touchmove', function(e) {
                moveX = e.touches[0].clientX;
            });

            this.element.addEventListener('touchend', function() {
                if (startX && moveX) {
                    var diff = startX - moveX;

                    if (Math.abs(diff) > 50) {
                        if (diff > 0) {
                            self.next();
                        } else {
                            self.prev();
                        }
                    }
                }

                startX = null;
                moveX = null;
            });
        }
    };

    // Popup class
    function BWGIGFPopup(feed) {
        this.feed = feed;
        this.items = feed.querySelectorAll('.bwg-igf-item');
        this.currentIndex = 0;

        this.init();
    }

    BWGIGFPopup.prototype = {
        init: function() {
            this.createPopup();
            this.bindEvents();
        },

        createPopup: function() {
            this.popup = document.createElement('div');
            this.popup.className = 'bwg-igf-popup';
            this.popup.innerHTML = [
                '<button class="bwg-igf-popup-close" aria-label="Close">&times;</button>',
                '<button class="bwg-igf-popup-nav bwg-igf-popup-prev" aria-label="Previous">&lsaquo;</button>',
                '<button class="bwg-igf-popup-nav bwg-igf-popup-next" aria-label="Next">&rsaquo;</button>',
                '<div class="bwg-igf-popup-content">',
                '  <img class="bwg-igf-popup-image" src="" alt="">',
                '  <div class="bwg-igf-popup-details">',
                '    <p class="bwg-igf-popup-caption"></p>',
                '    <div class="bwg-igf-popup-stats"></div>',
                '    <a class="bwg-igf-popup-link" href="" target="_blank" rel="noopener">View on Instagram</a>',
                '  </div>',
                '</div>'
            ].join('');

            document.body.appendChild(this.popup);
        },

        bindEvents: function() {
            var self = this;

            // Open popup on item click
            this.items.forEach(function(item, index) {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    self.open(index);
                });

                // Keyboard support
                item.setAttribute('tabindex', '0');
                item.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        self.open(index);
                    }
                });
            });

            // Close button
            this.popup.querySelector('.bwg-igf-popup-close').addEventListener('click', function() {
                self.close();
            });

            // Click outside to close
            this.popup.addEventListener('click', function(e) {
                if (e.target === self.popup) {
                    self.close();
                }
            });

            // Navigation
            this.popup.querySelector('.bwg-igf-popup-prev').addEventListener('click', function() {
                self.prev();
            });

            this.popup.querySelector('.bwg-igf-popup-next').addEventListener('click', function() {
                self.next();
            });

            // Keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (!self.popup.classList.contains('active')) return;

                switch (e.key) {
                    case 'Escape':
                        self.close();
                        break;
                    case 'ArrowLeft':
                        self.prev();
                        break;
                    case 'ArrowRight':
                        self.next();
                        break;
                }
            });
        },

        open: function(index) {
            this.currentIndex = index;
            this.updateContent();
            this.popup.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Focus management
            this.lastFocusedElement = document.activeElement;
            this.popup.querySelector('.bwg-igf-popup-close').focus();
        },

        close: function() {
            this.popup.classList.remove('active');
            document.body.style.overflow = '';

            // Restore focus
            if (this.lastFocusedElement) {
                this.lastFocusedElement.focus();
            }
        },

        updateContent: function() {
            var item = this.items[this.currentIndex];
            var img = item.querySelector('img');

            this.popup.querySelector('.bwg-igf-popup-image').src = item.dataset.fullImage || img.src;
            this.popup.querySelector('.bwg-igf-popup-image').alt = img.alt || '';
            this.popup.querySelector('.bwg-igf-popup-caption').textContent = item.dataset.caption || '';
            this.popup.querySelector('.bwg-igf-popup-stats').innerHTML = [
                item.dataset.likes ? '<span>❤️ ' + item.dataset.likes + '</span>' : '',
                item.dataset.comments ? '<span>💬 ' + item.dataset.comments + '</span>' : ''
            ].join(' ');
            this.popup.querySelector('.bwg-igf-popup-link').href = item.dataset.link || '#';
        },

        next: function() {
            this.currentIndex = (this.currentIndex + 1) % this.items.length;
            this.updateContent();
        },

        prev: function() {
            this.currentIndex = (this.currentIndex - 1 + this.items.length) % this.items.length;
            this.updateContent();
        }
    };

    // Export for global access
    window.BWGIGFFrontend = BWGIGFFrontend;

})();
