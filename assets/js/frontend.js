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
        isOnline: navigator.onLine,

        init: function() {
            this.initNetworkErrorHandling();
            this.initAsyncLoading();
            this.initSliders();
            this.initPopups();
            this.initLazyLoading();
        },

        /**
         * Initialize async loading for feeds without cached data
         * This shows a loading state while fetching Instagram data via AJAX
         */
        initAsyncLoading: function() {
            var self = this;
            var feedsNeedingLoad = document.querySelectorAll('.bwg-igf-feed[data-needs-load="true"]');

            feedsNeedingLoad.forEach(function(feed) {
                self.loadFeedAsync(feed);
            });
        },

        /**
         * Load feed data asynchronously via AJAX
         * @param {HTMLElement} feed - The feed container element
         */
        loadFeedAsync: function(feed) {
            var self = this;
            var feedId = feed.dataset.feedId;

            // Check if we have the necessary data
            if (!feedId || typeof bwgIgfFrontend === 'undefined') {
                console.error('BWG IGF: Missing feed ID or frontend config');
                self.showFeedError(feed, 'Configuration error');
                return;
            }

            // Create form data for AJAX request
            var formData = new FormData();
            formData.append('action', 'bwg_igf_load_feed');
            formData.append('nonce', bwgIgfFrontend.nonce);
            formData.append('feed_id', feedId);

            // Make AJAX request
            fetch(bwgIgfFrontend.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success && data.data.posts) {
                    self.renderFeedContent(feed, data.data);
                } else {
                    var errorMsg = data.data && data.data.message ? data.data.message : 'Failed to load feed';
                    self.showFeedError(feed, errorMsg);
                }
            })
            .catch(function(error) {
                console.error('BWG IGF: Error loading feed:', error);
                self.showFeedError(feed, 'Failed to load Instagram feed. Please try again later.');
            });
        },

        /**
         * Render feed content after successful AJAX load
         * @param {HTMLElement} feed - The feed container element
         * @param {Object} data - The feed data from AJAX response
         */
        renderFeedContent: function(feed, data) {
            var self = this;
            var posts = data.posts;
            var displaySettings = data.display_settings || {};
            var layoutType = data.layout_type || feed.dataset.layoutType || 'grid';
            var hoverEffect = feed.dataset.hoverEffect || 'none';
            var showLikes = feed.dataset.showLikes === 'true';
            var showComments = feed.dataset.showComments === 'true';
            var showFollow = feed.dataset.showFollow === 'true';

            // Remove loading state
            var loadingEl = feed.querySelector('.bwg-igf-loading');
            if (loadingEl) {
                loadingEl.remove();
            }

            // Remove loading class
            feed.classList.remove('bwg-igf-loading-feed');

            // Check if we have posts
            if (!posts || posts.length === 0) {
                self.showEmptyState(feed);
                return;
            }

            // Build HTML for posts
            var html = '';

            // Add header with account name and/or follow button (Feature #53 fix)
            var showAccountName = displaySettings.show_account_name;
            if ((showAccountName || showFollow) && data.first_username) {
                html += '<div class="bwg-igf-feed-header">';

                if (showAccountName) {
                    html += '<div class="bwg-igf-account-info">';
                    html += '<span class="bwg-igf-account-icon">';
                    html += '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24">';
                    html += '<path fill="#E1306C" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>';
                    html += '</svg>';
                    html += '</span>';
                    html += '<span class="bwg-igf-account-name">';
                    html += '<a href="https://instagram.com/' + self.escapeAttr(data.first_username) + '" target="_blank" rel="noopener noreferrer">@' + self.escapeHtml(data.first_username) + '</a>';
                    html += '</span>';
                    html += '</div>';
                }

                if (showFollow) {
                    var followText = displaySettings.follow_button_text || 'Follow on Instagram';
                    var buttonStyle = displaySettings.follow_button_style || 'gradient';
                    html += '<a href="https://instagram.com/' + self.escapeAttr(data.first_username) + '"';
                    html += ' class="bwg-igf-follow bwg-igf-follow-' + self.escapeAttr(buttonStyle) + '" target="_blank" rel="noopener noreferrer">';
                    html += self.escapeHtml(followText);
                    html += '</a>';
                }

                html += '</div>';
            }

            if (layoutType === 'slider') {
                html += '<div class="bwg-igf-slider-track">';
            }

            posts.forEach(function(post) {
                var itemClass = 'bwg-igf-item';
                if (layoutType === 'slider') {
                    itemClass += ' bwg-igf-slider-slide';
                }

                // Convert image URLs to proxy URLs to bypass CORS
                var thumbnailUrl = self.getProxyUrl(post.thumbnail || '');
                var fullImageUrl = self.getProxyUrl(post.full_image || '');

                html += '<div class="' + itemClass + '"';
                html += ' data-full-image="' + self.escapeAttr(fullImageUrl) + '"';
                html += ' data-caption="' + self.escapeAttr(post.caption || '') + '"';
                html += ' data-likes="' + (post.likes || 0) + '"';
                html += ' data-comments="' + (post.comments || 0) + '"';
                html += ' data-link="' + self.escapeAttr(post.link || '') + '"';
                html += '>';
                html += '<img src="' + self.escapeAttr(thumbnailUrl) + '"';
                html += ' alt="' + self.escapeAttr(post.caption || 'Instagram post') + '"';
                html += ' loading="lazy">';

                // Add overlay for hover effect
                if (hoverEffect === 'overlay') {
                    html += '<div class="bwg-igf-overlay">';
                    html += '<div class="bwg-igf-overlay-content">';
                    html += '<div class="bwg-igf-stats">';
                    if (showLikes) {
                        html += '<span class="bwg-igf-stat">❤️ ' + (post.likes || 0) + '</span>';
                    }
                    if (showComments) {
                        html += '<span class="bwg-igf-stat">💬 ' + (post.comments || 0) + '</span>';
                    }
                    html += '</div></div></div>';
                }

                html += '</div>';
            });

            if (layoutType === 'slider') {
                html += '</div>'; // Close slider track
            }

            // Insert HTML
            feed.innerHTML = html;

            // Update needs-load attribute
            feed.dataset.needsLoad = 'false';

            // Re-initialize components for this feed
            if (layoutType === 'slider') {
                new BWGIGFSlider(feed);
            }

            if (feed.dataset.popup === 'true') {
                new BWGIGFPopup(feed);
            }

            // Re-initialize image error handling for new images
            self.initImageErrorHandling();
        },

        /**
         * Show error state in feed
         * Admin-Only Error Visibility: Only show detailed errors to admins
         * Non-admins see nothing (graceful hidden state) for a seamless experience
         * @param {HTMLElement} feed - The feed container element
         * @param {string} message - Error message to display
         */
        showFeedError: function(feed, message) {
            // Remove loading state
            var loadingEl = feed.querySelector('.bwg-igf-loading');
            if (loadingEl) {
                loadingEl.remove();
            }

            // Remove loading class
            feed.classList.remove('bwg-igf-loading-feed');

            // Check if current user is an admin - only admins see error messages
            var isAdmin = typeof bwgIgfFrontend !== 'undefined' && bwgIgfFrontend.isAdmin;

            if (!isAdmin) {
                // Non-admins: show nothing (graceful hidden state)
                // The feed container remains empty for a seamless visitor experience
                feed.innerHTML = '';
                return;
            }

            // Admin-only error display with distinctive styling
            var errorHtml = '<div class="bwg-igf-empty-state bwg-igf-admin-notice" data-admin-only="true">';
            errorHtml += '<span class="bwg-igf-admin-notice-badge">Admin Notice</span>';
            errorHtml += '<div class="bwg-igf-empty-state-icon">';
            errorHtml += '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">';
            errorHtml += '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>';
            errorHtml += '</svg></div>';
            errorHtml += '<h3>Unable to Load Feed</h3>';
            errorHtml += '<p>' + this.escapeHtml(message) + '</p>';
            errorHtml += '<p class="bwg-igf-admin-notice-hint">This message is only visible to site administrators. Regular visitors will not see this error.</p>';
            errorHtml += '</div>';

            feed.innerHTML = errorHtml;
        },

        /**
         * Show empty state in feed
         * Admin-Only Error Visibility: Only show detailed messages to admins
         * Non-admins see nothing for a seamless experience
         * @param {HTMLElement} feed - The feed container element
         */
        showEmptyState: function(feed) {
            // Check if current user is an admin
            var isAdmin = typeof bwgIgfFrontend !== 'undefined' && bwgIgfFrontend.isAdmin;

            if (!isAdmin) {
                // Non-admins: show nothing (graceful hidden state)
                feed.innerHTML = '';
                return;
            }

            // Admin-only empty state display
            var emptyHtml = '<div class="bwg-igf-empty-state bwg-igf-admin-notice" data-admin-only="true">';
            emptyHtml += '<span class="bwg-igf-admin-notice-badge">Admin Notice</span>';
            emptyHtml += '<div class="bwg-igf-empty-state-icon">';
            emptyHtml += '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">';
            emptyHtml += '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>';
            emptyHtml += '</svg></div>';
            emptyHtml += '<h3>No Posts Found</h3>';
            emptyHtml += '<p>This feed doesn\'t have any posts to display yet.</p>';
            emptyHtml += '<p class="bwg-igf-admin-notice-hint">This message is only visible to site administrators. Regular visitors will not see this.</p>';
            emptyHtml += '</div>';

            feed.innerHTML = emptyHtml;
        },

        /**
         * Escape HTML special characters
         * @param {string} str - String to escape
         * @return {string} Escaped string
         */
        escapeHtml: function(str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },

        /**
         * Escape attribute value
         * @param {string} str - String to escape
         * @return {string} Escaped string
         */
        escapeAttr: function(str) {
            if (!str) return '';
            return str.replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        },

        /**
         * Convert an Instagram CDN URL to a proxy URL
         * This is needed to bypass CORS restrictions when loading images dynamically
         * @param {string} url - The original Instagram CDN URL
         * @return {string} The proxy URL, or empty string if no URL provided
         */
        getProxyUrl: function(url) {
            if (!url) return '';

            // Check if proxy base URL is configured
            if (typeof bwgIgfFrontend === 'undefined' || !bwgIgfFrontend.proxyBaseUrl) {
                // Fallback: return original URL if proxy not configured
                return url;
            }

            // Encode the URL and append to proxy base
            return bwgIgfFrontend.proxyBaseUrl + '?url=' + encodeURIComponent(url);
        },

        /**
         * Initialize network error handling
         * Handles offline/online state changes and displays user-friendly messages
         */
        initNetworkErrorHandling: function() {
            var self = this;

            // Listen for online/offline events
            window.addEventListener('online', function() {
                self.isOnline = true;
                self.hideNetworkError();
                self.retryFailedImages();
            });

            window.addEventListener('offline', function() {
                self.isOnline = false;
                self.showNetworkError();
            });

            // Check initial state
            if (!navigator.onLine) {
                this.showNetworkError();
            }

            // Handle image loading errors
            this.initImageErrorHandling();
        },

        /**
         * Show network error message on all feeds
         */
        showNetworkError: function() {
            var feeds = document.querySelectorAll('.bwg-igf-feed');

            feeds.forEach(function(feed) {
                // Don't add multiple error messages
                if (feed.querySelector('.bwg-igf-network-error')) {
                    return;
                }

                var errorDiv = document.createElement('div');
                errorDiv.className = 'bwg-igf-network-error';
                errorDiv.setAttribute('role', 'alert');
                errorDiv.innerHTML = [
                    '<div class="bwg-igf-network-error-icon">',
                    '  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">',
                    '    <path d="M1 1l22 22M9 9a3 3 0 1 0 4.2 4.2"></path>',
                    '    <path d="M21.21 15.89A10 10 0 0 0 8.11 2.79"></path>',
                    '    <path d="M16.82 12.42A6 6 0 0 0 7.17 6.64"></path>',
                    '    <path d="M5 12H5.01"></path>',
                    '    <path d="M12 12v.01"></path>',
                    '  </svg>',
                    '</div>',
                    '<div class="bwg-igf-network-error-text">',
                    '  <strong>Connection Lost</strong>',
                    '  <p>Unable to load Instagram feed. Please check your internet connection and try again.</p>',
                    '</div>',
                    '<button class="bwg-igf-retry-btn" type="button">Retry</button>'
                ].join('');

                // Insert at the top of the feed
                feed.insertBefore(errorDiv, feed.firstChild);

                // Add retry button handler
                var retryBtn = errorDiv.querySelector('.bwg-igf-retry-btn');
                retryBtn.addEventListener('click', function() {
                    if (navigator.onLine) {
                        location.reload();
                    } else {
                        // Shake the button to indicate still offline
                        retryBtn.classList.add('bwg-igf-shake');
                        setTimeout(function() {
                            retryBtn.classList.remove('bwg-igf-shake');
                        }, 500);
                    }
                });
            });
        },

        /**
         * Hide network error messages from all feeds
         */
        hideNetworkError: function() {
            var errorMessages = document.querySelectorAll('.bwg-igf-network-error');
            errorMessages.forEach(function(el) {
                el.remove();
            });
        },

        /**
         * Initialize image error handling for graceful degradation
         */
        initImageErrorHandling: function() {
            var self = this;
            var images = document.querySelectorAll('.bwg-igf-feed img');

            images.forEach(function(img) {
                img.addEventListener('error', function() {
                    self.handleImageError(this);
                });

                // Mark images that have already failed (before JS loaded)
                if (img.complete && img.naturalHeight === 0 && img.src) {
                    self.handleImageError(img);
                }
            });
        },

        /**
         * Handle individual image loading errors
         * @param {HTMLImageElement} img - The image element that failed to load
         */
        handleImageError: function(img) {
            // Don't re-process already handled images
            if (img.classList.contains('bwg-igf-img-error')) {
                return;
            }

            img.classList.add('bwg-igf-img-error');

            // Store original src for retry
            if (!img.dataset.originalSrc) {
                img.dataset.originalSrc = img.src;
            }

            // Add placeholder styling
            var item = img.closest('.bwg-igf-item');
            if (item) {
                item.classList.add('bwg-igf-item-error');
            }
        },

        /**
         * Retry loading failed images when connection restored
         */
        retryFailedImages: function() {
            var failedImages = document.querySelectorAll('.bwg-igf-img-error');

            failedImages.forEach(function(img) {
                if (img.dataset.originalSrc) {
                    img.classList.remove('bwg-igf-img-error');
                    var item = img.closest('.bwg-igf-item');
                    if (item) {
                        item.classList.remove('bwg-igf-item-error');
                    }
                    // Force reload by appending cache-busting parameter
                    var originalSrc = img.dataset.originalSrc;
                    var separator = originalSrc.indexOf('?') > -1 ? '&' : '?';
                    img.src = originalSrc + separator + '_retry=' + Date.now();
                }
            });
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
        // Store the original configured value
        this.configuredSlidesToShow = parseInt(element.dataset.slidesToShow) || 3;
        // Calculate responsive slidesToShow based on viewport
        this.slidesToShow = this.getResponsiveSlidesToShow();
        this.autoplay = element.dataset.autoplay === 'true';
        this.autoplaySpeed = parseInt(element.dataset.autoplaySpeed) || 3000;
        this.transitionDuration = parseInt(element.dataset.transitionDuration) || 600;
        this.infinite = element.dataset.infinite === 'true';

        this.init();
    }

    BWGIGFSlider.prototype = {
        init: function() {
            // Clone slides for seamless infinite loop
            if (this.infinite) {
                this.cloneSlides();
            }

            // Set transition duration from configuration
            this.setTransitionDuration();

            this.bindEvents();
            this.updateSlideWidth();

            // Set initial position if infinite mode (account for cloned slides at start)
            if (this.infinite) {
                this.realIndex = 0;
                this.currentIndex = this.slidesToShow; // Start at first real slide
                this.goToImmediate(this.currentIndex);
            }

            if (this.autoplay) {
                this.startAutoplay();
            }

            // Handle touch events
            this.initTouch();

            // Handle resize events for responsive behavior
            this.initResponsive();
        },

        /**
         * Clone slides at beginning and end for seamless infinite loop
         * Clones the first slidesToShow slides and appends to end
         * Clones the last slidesToShow slides and prepends to beginning
         */
        cloneSlides: function() {
            var slideCount = this.slides.length;
            var cloneCount = this.slidesToShow;

            // Clone last slides and prepend
            for (var i = slideCount - 1; i >= slideCount - cloneCount; i--) {
                var clone = this.slides[i].cloneNode(true);
                clone.classList.add('bwg-igf-slider-clone');
                this.track.insertBefore(clone, this.track.firstChild);
            }

            // Clone first slides and append
            for (var j = 0; j < cloneCount; j++) {
                var clone = this.slides[j].cloneNode(true);
                clone.classList.add('bwg-igf-slider-clone');
                this.track.appendChild(clone);
            }

            // Update slides reference to include clones
            this.slides = this.track.querySelectorAll('.bwg-igf-slider-slide');
            this.totalSlides = slideCount; // Original slide count (without clones)
        },

        /**
         * Set the CSS transition duration based on configuration
         * This controls how smooth and fast the slide animation is
         */
        setTransitionDuration: function() {
            var durationSeconds = this.transitionDuration / 1000;
            this.track.style.transition = 'transform ' + durationSeconds + 's cubic-bezier(0.25, 0.1, 0.25, 1)';
        },

        /**
         * Calculate the number of slides to show based on viewport width
         * Uses custom responsive settings from Feature #52 if available
         * Mobile (< 768px): uses data-mobile-columns or defaults to 1
         * Tablet (768px - 1024px): uses data-tablet-columns or defaults to 2
         * Desktop (> 1024px): configured value
         */
        getResponsiveSlidesToShow: function() {
            var viewportWidth = window.innerWidth;
            var configured = this.configuredSlidesToShow;

            // Get custom responsive settings from data attributes (Feature #52)
            var mobileColumns = parseInt(this.element.dataset.mobileColumns) || 1;
            var tabletColumns = parseInt(this.element.dataset.tabletColumns) || 2;

            if (viewportWidth < 768) {
                // Mobile: use custom mobile columns setting
                return mobileColumns;
            } else if (viewportWidth < 1024) {
                // Tablet: use custom tablet columns setting
                return Math.min(tabletColumns, configured);
            } else {
                // Desktop: show configured value
                return configured;
            }
        },

        /**
         * Initialize responsive resize handling
         */
        initResponsive: function() {
            var self = this;
            var resizeTimeout;

            window.addEventListener('resize', function() {
                // Debounce resize events
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    self.handleResize();
                }, 100);
            });
        },

        /**
         * Handle viewport resize - recalculate slides to show
         */
        handleResize: function() {
            var newSlidesToShow = this.getResponsiveSlidesToShow();

            // Only update if the value changed
            if (newSlidesToShow !== this.slidesToShow) {
                this.slidesToShow = newSlidesToShow;

                // If infinite mode, need to recreate clones
                if (this.infinite) {
                    // Remove all clones
                    var clones = this.track.querySelectorAll('.bwg-igf-slider-clone');
                    clones.forEach(function(clone) {
                        clone.remove();
                    });

                    // Get original slides
                    this.slides = this.track.querySelectorAll('.bwg-igf-slider-slide');

                    // Recreate clones with new count
                    this.cloneSlides();

                    // Reset to first slide
                    this.realIndex = 0;
                    this.currentIndex = this.slidesToShow;
                }

                this.updateSlideWidth();

                // Reset to first slide to avoid index issues
                if (this.infinite) {
                    this.goToImmediate(this.slidesToShow);
                } else {
                    this.goTo(0);
                }
            }
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

        goTo: function(index, skipTransition) {
            if (this.infinite) {
                this.goToInfinite(index, skipTransition);
            } else {
                this.goToNormal(index);
            }
        },

        /**
         * Go to slide without transition (instant)
         */
        goToImmediate: function(index) {
            this.currentIndex = index;
            var offset = -(index * (100 / this.slidesToShow));

            // Disable transition
            this.track.style.transition = 'none';
            this.track.style.transform = 'translateX(' + offset + '%)';

            // Force reflow to ensure the transition is disabled
            this.track.offsetHeight;

            // Re-enable transition for next movement
            var self = this;
            setTimeout(function() {
                self.track.style.transition = '';
            }, 50);
        },

        /**
         * Normal (non-infinite) slider navigation
         */
        goToNormal: function(index) {
            var maxIndex = this.slides.length - this.slidesToShow;

            if (index < 0) index = 0;
            if (index > maxIndex) index = maxIndex;

            this.currentIndex = index;
            var offset = -(index * (100 / this.slidesToShow));
            this.track.style.transform = 'translateX(' + offset + '%)';

            this.updateDots();
        },

        /**
         * Infinite slider navigation with seamless loop
         * Uses cloned slides for seamless transitions
         */
        goToInfinite: function(index, skipTransition) {
            var self = this;

            // Calculate offset
            var offset = -(index * (100 / this.slidesToShow));
            this.track.style.transform = 'translateX(' + offset + '%)';

            this.currentIndex = index;

            // Update real index for dots
            this.realIndex = this.getRealIndex(index);
            this.updateDots();

            // Check if we need to reset position after transition
            // We have: [clones of last 3] [real slides 1-12] [clones of first 3]
            // Indices:  [0, 1, 2]          [3, 4, ..., 14]  [15, 16, 17]

            // If we moved to the cloned slides at the end (index >= totalSlides + slidesToShow)
            // Reset to the real slides at the beginning
            if (index >= this.totalSlides + this.slidesToShow) {
                setTimeout(function() {
                    // Map to equivalent position in real slides
                    // Example: index 15 (first clone at end) -> index 3 (first real slide)
                    var resetIndex = self.slidesToShow + (index - (self.totalSlides + self.slidesToShow));
                    self.goToImmediate(resetIndex);
                    self.realIndex = self.getRealIndex(resetIndex);
                }, this.transitionDuration); // Match configured transition duration
            }

            // If we moved to the cloned slides at the beginning (index < slidesToShow)
            // Reset to the real slides at the end
            if (index < this.slidesToShow) {
                setTimeout(function() {
                    // Map to equivalent position in real slides
                    // Example: index 2 (last clone at start) -> index 14 (last real slide)
                    var resetIndex = self.totalSlides + index;
                    self.goToImmediate(resetIndex);
                    self.realIndex = self.getRealIndex(resetIndex);
                }, this.transitionDuration); // Match configured transition duration
            }
        },

        /**
         * Get the real slide index (excluding clones) for dot navigation
         */
        getRealIndex: function(index) {
            // Account for cloned slides at the beginning
            var realIndex = index - this.slidesToShow;

            // Wrap around if needed
            if (realIndex < 0) {
                realIndex = this.totalSlides + realIndex;
            }
            if (realIndex >= this.totalSlides) {
                realIndex = realIndex - this.totalSlides;
            }

            return realIndex;
        },

        next: function() {
            this.goTo(this.currentIndex + 1);
        },

        prev: function() {
            this.goTo(this.currentIndex - 1);
        },

        updateDots: function() {
            var self = this;
            var activeIndex = this.infinite ? this.realIndex : this.currentIndex;

            this.dots.forEach(function(dot, index) {
                dot.classList.toggle('active', index === activeIndex);
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
            this.initTouch();
        },

        createPopup: function() {
            this.popup = document.createElement('div');
            this.popup.className = 'bwg-igf-popup';
            // Add ARIA attributes for accessibility
            this.popup.setAttribute('role', 'dialog');
            this.popup.setAttribute('aria-modal', 'true');
            this.popup.setAttribute('aria-label', 'Instagram post lightbox');
            this.popup.innerHTML = [
                '<button class="bwg-igf-popup-close" aria-label="Close lightbox">&times;</button>',
                '<button class="bwg-igf-popup-nav bwg-igf-popup-prev" aria-label="Previous post">&lsaquo;</button>',
                '<button class="bwg-igf-popup-nav bwg-igf-popup-next" aria-label="Next post">&rsaquo;</button>',
                '<div class="bwg-igf-popup-content">',
                '  <div class="bwg-igf-popup-media-wrapper">',
                '    <div class="bwg-igf-popup-media-container">',
                '      <img class="bwg-igf-popup-image" src="" alt="">',
                '      <video class="bwg-igf-popup-video" controls style="display:none;">',
                '        <source src="" type="video/mp4">',
                '        Your browser does not support the video tag.',
                '      </video>',
                '    </div>',
                '    <a class="bwg-igf-popup-link" href="" target="_blank" rel="noopener">View on Instagram</a>',
                '  </div>',
                '  <div class="bwg-igf-popup-details">',
                '    <p class="bwg-igf-popup-caption"></p>',
                '    <div class="bwg-igf-popup-stats"></div>',
                '  </div>',
                '</div>'
            ].join('');

            document.body.appendChild(this.popup);

            // Store focusable elements for focus trapping
            this.focusableElements = null;
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

            // Keyboard navigation and focus trapping
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
                    case 'Tab':
                        // Focus trapping - keep focus within popup
                        self.handleTabKey(e);
                        break;
                }
            });
        },

        /**
         * Handle Tab key for focus trapping within popup
         * @param {KeyboardEvent} e - The keyboard event
         */
        handleTabKey: function(e) {
            if (!this.focusableElements || this.focusableElements.length === 0) {
                e.preventDefault();
                return;
            }

            var firstFocusable = this.focusableElements[0];
            var lastFocusable = this.focusableElements[this.focusableElements.length - 1];

            if (e.shiftKey) {
                // Shift+Tab: if on first element, wrap to last
                if (document.activeElement === firstFocusable) {
                    e.preventDefault();
                    lastFocusable.focus();
                }
            } else {
                // Tab: if on last element, wrap to first
                if (document.activeElement === lastFocusable) {
                    e.preventDefault();
                    firstFocusable.focus();
                }
            }
        },

        open: function(index) {
            var self = this;
            this.currentIndex = index;
            this.updateContent();
            this.popup.classList.add('active');
            document.body.style.overflow = 'hidden';

            // Focus management - store the trigger element
            this.lastFocusedElement = document.activeElement;

            // Cache focusable elements for focus trapping
            this.focusableElements = this.popup.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );

            // Feature #50: Improved performance - use requestAnimationFrame instead of setTimeout
            // This ensures focus happens on the next paint, smoother than arbitrary delay
            requestAnimationFrame(function() {
                var closeBtn = self.popup.querySelector('.bwg-igf-popup-close');
                if (closeBtn) {
                    closeBtn.focus();
                }
            });
        },

        close: function() {
            // Stop video playback when closing popup (Feature #48)
            var popupVideo = this.popup.querySelector('.bwg-igf-popup-video');
            if (popupVideo && !popupVideo.paused) {
                popupVideo.pause();
            }

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
            var popupImage = this.popup.querySelector('.bwg-igf-popup-image');
            var popupVideo = this.popup.querySelector('.bwg-igf-popup-video');
            var videoSource = popupVideo.querySelector('source');

            // Check if this is a video post (Feature #48)
            var isVideo = item.dataset.mediaType === 'video' && item.dataset.videoUrl;

            if (isVideo) {
                // Show video, hide image
                popupImage.style.display = 'none';
                popupVideo.style.display = 'block';

                // Stop any currently playing video first
                if (!popupVideo.paused) {
                    popupVideo.pause();
                }

                // Set video source and load
                videoSource.src = item.dataset.videoUrl;
                popupVideo.load();

                // Try to autoplay with muted audio (required by browsers)
                popupVideo.muted = true;
                var playPromise = popupVideo.play();

                // Handle autoplay failures gracefully
                if (playPromise !== undefined) {
                    playPromise.catch(function(error) {
                        // Autoplay was prevented, video will require manual play
                        console.log('Video autoplay prevented:', error);
                    });
                }
            } else {
                // Show image, hide video
                popupImage.style.display = 'block';
                popupVideo.style.display = 'none';

                // Stop video if it was playing
                if (!popupVideo.paused) {
                    popupVideo.pause();
                }

                popupImage.src = item.dataset.fullImage || img.src;
                popupImage.alt = img.alt || '';
            }

            // Update caption, stats, and link (same for both image and video)
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
        },

        initTouch: function() {
            var self = this;
            var startX = null;
            var startY = null;
            var moveX = null;
            var moveY = null;

            this.popup.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
                startY = e.touches[0].clientX;
            }, { passive: true });

            this.popup.addEventListener('touchmove', function(e) {
                moveX = e.touches[0].clientX;
                moveY = e.touches[0].clientY;
            }, { passive: true });

            this.popup.addEventListener('touchend', function() {
                if (!self.popup.classList.contains('active')) {
                    return;
                }

                if (startX !== null && moveX !== null) {
                    var diffX = startX - moveX;
                    var diffY = startY - moveY;

                    // Only respond to horizontal swipes (ignore vertical scrolling)
                    if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 50) {
                        if (diffX > 0) {
                            // Swipe left = next
                            self.next();
                        } else {
                            // Swipe right = prev
                            self.prev();
                        }
                    }
                }

                startX = null;
                startY = null;
                moveX = null;
                moveY = null;
            });
        }
    };

    // Export for global access
    window.BWGIGFFrontend = BWGIGFFrontend;

})();
