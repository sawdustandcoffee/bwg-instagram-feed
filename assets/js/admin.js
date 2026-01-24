/**
 * BWG Instagram Feed - Admin JavaScript
 *
 * @package BWG_Instagram_Feed
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        BWGIGFAdmin.init();
    });

    var BWGIGFAdmin = {
        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initColorPickers();
            this.initLivePreview();
        },

        bindEvents: function() {
            // Delete feed confirmation
            $(document).on('click', '.bwg-igf-delete-feed', this.handleDeleteFeed);

            // Save feed
            $(document).on('submit', '#bwg-igf-feed-form', this.handleSaveFeed);

            // Copy shortcode
            $(document).on('click', '.bwg-igf-copy-shortcode', this.handleCopyShortcode);

            // Refresh cache
            $(document).on('click', '.bwg-igf-refresh-cache', this.handleRefreshCache);

            // Clear all cache
            $(document).on('click', '.bwg-igf-clear-cache', this.handleClearCache);

            // Validate username
            $(document).on('blur', '#bwg-igf-username', this.handleValidateUsername);
        },

        initTabs: function() {
            $('.bwg-igf-editor-tab').on('click', function() {
                var target = $(this).data('tab');

                $('.bwg-igf-editor-tab').removeClass('active');
                $(this).addClass('active');

                $('.bwg-igf-tab-content').removeClass('active');
                $('#bwg-igf-tab-' + target).addClass('active');
            });
        },

        initColorPickers: function() {
            if ($.fn.wpColorPicker) {
                $('.bwg-igf-color-picker').wpColorPicker({
                    change: function(event, ui) {
                        // Small delay to allow the color picker to update the input value
                        setTimeout(function() {
                            BWGIGFAdmin.updatePreview();
                        }, 10);
                    },
                    clear: function() {
                        setTimeout(function() {
                            BWGIGFAdmin.updatePreview();
                        }, 10);
                    }
                });
            }
        },

        initLivePreview: function() {
            // Update preview on setting changes
            $('.bwg-igf-editor-content input, .bwg-igf-editor-content select, .bwg-igf-editor-content textarea').on('change input', function() {
                BWGIGFAdmin.updatePreview();
            });

            // Create style element for custom CSS preview
            if (!$('#bwg-igf-custom-css-preview').length) {
                $('head').append('<style id="bwg-igf-custom-css-preview" type="text/css"></style>');
            }

            // Initial preview update
            this.updatePreview();
        },

        updatePreview: function() {
            // Get current settings and update preview
            var settings = this.getFormSettings();

            // Apply settings to preview
            var $preview = $('.bwg-igf-preview-content');

            // Build classes array
            var classes = ['bwg-igf-preview-content', 'bwg-igf-grid', 'bwg-igf-grid-' + settings.columns];

            // Add hover effect class if not 'none'
            if (settings.hoverEffect && settings.hoverEffect !== 'none') {
                classes.push('bwg-igf-hover-' + settings.hoverEffect);
            }

            // Update classes
            $preview.attr('class', classes.join(' '));

            // Update gap
            $preview.css('--bwg-igf-gap', settings.gap + 'px');

            // Update border radius
            $preview.find('.bwg-igf-item').css('border-radius', settings.borderRadius + 'px');

            // Handle overlay effect - add/remove overlay elements
            if (settings.hoverEffect === 'overlay') {
                $preview.find('.bwg-igf-item').each(function() {
                    if (!$(this).find('.bwg-igf-overlay').length) {
                        $(this).append('<div class="bwg-igf-overlay"><div class="bwg-igf-overlay-content"><div class="bwg-igf-stats"><span class="bwg-igf-stat">❤️ 123</span><span class="bwg-igf-stat">💬 45</span></div></div></div>');
                    }
                });
            } else {
                $preview.find('.bwg-igf-overlay').remove();
            }

            // Update background color
            if (settings.backgroundColor) {
                $preview.css('background-color', settings.backgroundColor);
                $preview.css('padding', '15px');
                $preview.css('border-radius', '8px');
            } else {
                $preview.css('background-color', '');
                $preview.css('padding', '');
                $preview.css('border-radius', '');
            }

            // Apply custom CSS to preview
            var customCSS = settings.customCSS || '';
            // Replace .bwg-igf-feed with .bwg-igf-preview-content for admin preview
            var previewCSS = customCSS.replace(/\.bwg-igf-feed/g, '.bwg-igf-preview-content');
            $('#bwg-igf-custom-css-preview').text(previewCSS);
        },

        getFormSettings: function() {
            // Get background color from color picker (may need to check the hidden input)
            var bgColor = $('#bwg-igf-background-color').val();

            return {
                columns: $('#bwg-igf-columns').val() || 3,
                gap: $('#bwg-igf-gap').val() || 10,
                borderRadius: $('#bwg-igf-border-radius').val() || 0,
                hoverEffect: $('#bwg-igf-hover-effect').val() || 'none',
                backgroundColor: bgColor || '',
                customCSS: $('#bwg-igf-custom-css').val() || ''
            };
        },

        handleDeleteFeed: function(e) {
            e.preventDefault();

            if (!confirm(bwgIgfAdmin.i18n.confirmDelete)) {
                return;
            }

            var $button = $(this);
            var feedId = $button.data('feed-id');

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bwg_igf_delete_feed',
                    nonce: bwgIgfAdmin.nonce,
                    feed_id: feedId
                },
                success: function(response) {
                    if (response.success) {
                        $button.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || bwgIgfAdmin.i18n.error);
                    }
                },
                error: function() {
                    alert(bwgIgfAdmin.i18n.error);
                }
            });
        },

        handleSaveFeed: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();

            $button.prop('disabled', true).text(bwgIgfAdmin.i18n.saving);

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: $form.serialize() + '&action=bwg_igf_save_feed&nonce=' + bwgIgfAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        $button.text(bwgIgfAdmin.i18n.saved);

                        // Show success notice
                        BWGIGFAdmin.showNotice('success', response.data.message);

                        // Redirect to feeds list if creating new feed
                        if (response.data.redirect) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1500);
                        } else {
                            setTimeout(function() {
                                $button.text(originalText).prop('disabled', false);
                            }, 2000);
                        }
                    } else {
                        $button.text(originalText).prop('disabled', false);
                        BWGIGFAdmin.showNotice('error', response.data.message || bwgIgfAdmin.i18n.error);
                    }
                },
                error: function() {
                    $button.text(originalText).prop('disabled', false);
                    BWGIGFAdmin.showNotice('error', bwgIgfAdmin.i18n.error);
                }
            });
        },

        handleCopyShortcode: function(e) {
            e.preventDefault();

            var shortcode = $(this).data('shortcode');

            navigator.clipboard.writeText(shortcode).then(function() {
                BWGIGFAdmin.showNotice('success', 'Shortcode copied to clipboard!');
            });
        },

        handleRefreshCache: function(e) {
            e.preventDefault();

            var $button = $(this);
            var feedId = $button.data('feed-id');

            $button.prop('disabled', true);

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bwg_igf_refresh_cache',
                    nonce: bwgIgfAdmin.nonce,
                    feed_id: feedId
                },
                success: function(response) {
                    $button.prop('disabled', false);
                    if (response.success) {
                        BWGIGFAdmin.showNotice('success', 'Cache refreshed successfully!');
                    } else {
                        BWGIGFAdmin.showNotice('error', response.data.message || bwgIgfAdmin.i18n.error);
                    }
                },
                error: function() {
                    $button.prop('disabled', false);
                    BWGIGFAdmin.showNotice('error', bwgIgfAdmin.i18n.error);
                }
            });
        },

        handleClearCache: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to clear all cached data?')) {
                return;
            }

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bwg_igf_clear_all_cache',
                    nonce: bwgIgfAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        BWGIGFAdmin.showNotice('success', 'All cache cleared successfully!');
                    } else {
                        BWGIGFAdmin.showNotice('error', response.data.message || bwgIgfAdmin.i18n.error);
                    }
                }
            });
        },

        handleValidateUsername: function() {
            var username = $(this).val().trim();

            if (!username) {
                return;
            }

            var $indicator = $(this).siblings('.bwg-igf-validation-indicator');
            $indicator.html('<span class="spinner is-active"></span>');

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bwg_igf_validate_username',
                    nonce: bwgIgfAdmin.nonce,
                    username: username
                },
                success: function(response) {
                    if (response.success) {
                        $indicator.html('<span class="dashicons dashicons-yes" style="color: green;"></span>');
                    } else {
                        $indicator.html('<span class="dashicons dashicons-no" style="color: red;"></span> ' + response.data.message);
                    }
                }
            });
        },

        showNotice: function(type, message) {
            var $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');

            $('.wrap h1').first().after($notice);

            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Export for global access if needed
    window.BWGIGFAdmin = BWGIGFAdmin;

})(jQuery);
