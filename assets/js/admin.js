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
        // Flag to prevent double-click on save (Feature #110)
        isSaving: false,
        // Flag to prevent double-click on delete (Feature #111)
        isDeleting: {},

        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initColorPickers();
            this.initLivePreview();
            this.initCacheDurationWarning();
            this.initLayoutTypeToggle();
            this.initAutoplaySpeedToggle();
            this.initPostCountValidation();
            this.initColumnCountValidation();
            this.initBeforeUnloadWarning(); // Feature #112: Warn before page refresh during save
            this.initFeedSearch(); // Feature #117: Search feeds by name
            this.initFeedTypeToggle(); // Feature #130: Toggle between public and connected account
            this.initFollowButtonToggle(); // Feature #27: Toggle follow button options
            this.initPopupToggle(); // Feature #29: Toggle popup options visibility
            this.initImageHeightModeToggle(); // Feature #165: Toggle fixed height field visibility
        },

        bindEvents: function() {
            // Delete feed confirmation
            $(document).on('click', '.bwg-igf-delete-feed', this.handleDeleteFeed);

            // Duplicate feed
            $(document).on('click', '.bwg-igf-duplicate-feed', this.handleDuplicateFeed);

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

            // Connect Instagram account (test mode)
            $(document).on('click', '.bwg-igf-connect-account', this.handleConnectAccount);

            // Disconnect Instagram account
            $(document).on('click', '.bwg-igf-disconnect-account', this.handleDisconnectAccount);

            // Verify token encryption
            $(document).on('click', '.bwg-igf-verify-encryption', this.handleVerifyEncryption);

            // Clear validation errors on input (Feature #88)
            $(document).on('input', '.bwg-igf-field-error', this.handleClearValidationOnInput);

            // Check for GitHub updates (Feature #133)
            $(document).on('click', '#bwg-igf-check-updates', this.handleCheckGitHubUpdates);
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

        initCacheDurationWarning: function() {
            var $select = $('#bwg-igf-cache-duration');
            var $warning = $('#bwg-igf-cache-warning');

            if (!$select.length || !$warning.length) {
                return;
            }

            // Show/hide warning based on cache duration value
            function updateWarning() {
                var value = parseInt($select.val(), 10);
                // Show warning for 15 minutes (900) or 30 minutes (1800)
                if (value <= 1800) {
                    $warning.slideDown(200);
                } else {
                    $warning.slideUp(200);
                }
            }

            // Check on page load
            updateWarning();

            // Check on change
            $select.on('change', updateWarning);
        },

        /**
         * Feature #130: Toggle between public username field and connected account selector
         * Feature #131: Also toggle hashtag filters visibility in Advanced tab
         */
        initFeedTypeToggle: function() {
            var $feedType = $('#bwg-igf-feed-type');
            var $usernameField = $('#bwg-igf-username-field');
            var $connectedAccountField = $('#bwg-igf-connected-account-field');
            var $connectedFilters = $('#bwg-igf-connected-filters');

            if (!$feedType.length) {
                return;
            }

            function toggleFeedTypeFields() {
                var value = $feedType.val();
                if (value === 'connected') {
                    $usernameField.slideUp(200);
                    $connectedAccountField.slideDown(200);
                    // Show hashtag filters for connected feeds
                    $connectedFilters.slideDown(200);
                } else {
                    $usernameField.slideDown(200);
                    $connectedAccountField.slideUp(200);
                    // Hide hashtag filters for public feeds
                    $connectedFilters.slideUp(200);
                }
            }

            // Check on page load
            toggleFeedTypeFields();

            // Check on change
            $feedType.on('change', toggleFeedTypeFields);
        },

        initLayoutTypeToggle: function() {
            var $layoutType = $('#bwg-igf-layout-type');
            var $gridOptions = $('.bwg-igf-grid-options');
            var $sliderOptions = $('.bwg-igf-slider-options');

            if (!$layoutType.length) {
                return;
            }

            function toggleLayoutOptions() {
                var value = $layoutType.val();
                if (value === 'slider') {
                    $gridOptions.slideUp(200);
                    $sliderOptions.slideDown(200);
                } else {
                    $gridOptions.slideDown(200);
                    $sliderOptions.slideUp(200);
                }
                // Update preview
                BWGIGFAdmin.updatePreview();
            }

            // Check on page load
            toggleLayoutOptions();

            // Check on change
            $layoutType.on('change', toggleLayoutOptions);
        },

        initAutoplaySpeedToggle: function() {
            var $autoplay = $('#bwg-igf-autoplay');
            var $speedField = $('.bwg-igf-autoplay-speed-field');

            if (!$autoplay.length) {
                return;
            }

            function toggleAutoplaySpeed() {
                if ($autoplay.is(':checked')) {
                    $speedField.slideDown(200);
                } else {
                    $speedField.slideUp(200);
                }
            }

            // Check on page load
            toggleAutoplaySpeed();

            // Check on change
            $autoplay.on('change', toggleAutoplaySpeed);
        },

        initFollowButtonToggle: function() {
            var $followButton = $('#bwg-igf-show-follow-button');
            var $followOptions = $('#bwg-igf-follow-button-options');

            if (!$followButton.length) {
                return;
            }

            function toggleFollowButtonOptions() {
                if ($followButton.is(':checked')) {
                    $followOptions.slideDown(200);
                } else {
                    $followOptions.slideUp(200);
                }
                // Update preview to reflect follow button visibility
                BWGIGFAdmin.updatePreview();
            }

            // Check on page load
            toggleFollowButtonOptions();

            // Check on change
            $followButton.on('change', toggleFollowButtonOptions);

            // Update preview when button text or style changes
            $('#bwg-igf-follow-button-text, #bwg-igf-follow-button-style').on('change input', function() {
                BWGIGFAdmin.updatePreview();
            });
        },

        /**
         * Feature #29: Toggle popup options visibility based on popup enable checkbox
         */
        initPopupToggle: function() {
            var $popupEnabled = $('#bwg-igf-popup-enabled');
            var $popupOptions = $('#bwg-igf-popup-options');

            if (!$popupEnabled.length) {
                return;
            }

            function togglePopupOptions() {
                if ($popupEnabled.is(':checked')) {
                    $popupOptions.slideDown(200);
                } else {
                    $popupOptions.slideUp(200);
                }
            }

            // Check on page load
            togglePopupOptions();

            // Check on change
            $popupEnabled.on('change', togglePopupOptions);
        },

        /**
         * Feature #165: Toggle fixed height field visibility based on image height mode selection
         */
        initImageHeightModeToggle: function() {
            var $imageHeightMode = $('#bwg-igf-image-height-mode');
            var $fixedHeightField = $('#bwg-igf-fixed-height-field');

            if (!$imageHeightMode.length) {
                return;
            }

            function toggleFixedHeightField() {
                var value = $imageHeightMode.val();
                if (value === 'fixed') {
                    $fixedHeightField.slideDown(200);
                } else {
                    $fixedHeightField.slideUp(200);
                }
                // Update preview
                BWGIGFAdmin.updatePreview();
            }

            // Check on page load
            toggleFixedHeightField();

            // Check on change
            $imageHeightMode.on('change', toggleFixedHeightField);
        },

        initPostCountValidation: function() {
            var $postCount = $('#bwg-igf-post-count');
            var minValue = 1;
            var maxValue = 50;

            if (!$postCount.length) {
                return;
            }

            // Validate on blur (when user leaves the field)
            $postCount.on('blur', function() {
                var value = parseInt($(this).val(), 10);

                // Handle empty or non-numeric values
                if (isNaN(value) || value < minValue) {
                    $(this).val(minValue);
                    BWGIGFAdmin.showPostCountWarning('Post count set to minimum (' + minValue + ')');
                } else if (value > maxValue) {
                    $(this).val(maxValue);
                    BWGIGFAdmin.showPostCountWarning('Post count limited to maximum (' + maxValue + ')');
                }
            });

            // Validate on input (real-time as user types)
            $postCount.on('input', function() {
                var value = parseInt($(this).val(), 10);
                var $field = $(this).closest('.bwg-igf-field');
                var $warning = $field.find('.bwg-igf-post-count-warning');

                // Remove existing warning
                $warning.remove();

                // Show warning if value is out of range
                if (!isNaN(value)) {
                    if (value < minValue) {
                        BWGIGFAdmin.showPostCountInlineWarning($field, 'Minimum is ' + minValue);
                    } else if (value > maxValue) {
                        BWGIGFAdmin.showPostCountInlineWarning($field, 'Maximum is ' + maxValue);
                    }
                }
            });
        },

        showPostCountWarning: function(message) {
            // Show a brief notice that the value was auto-corrected
            var $notice = $('<div class="notice notice-warning is-dismissible bwg-igf-post-count-notice"><p>' + message + '</p></div>');
            $('.wrap h1').first().after($notice);

            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        },

        showPostCountInlineWarning: function($field, message) {
            var $warning = $('<span class="bwg-igf-post-count-warning" style="color: #d63638; margin-left: 10px; font-size: 12px;"><span class="dashicons dashicons-warning" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span> ' + message + '</span>');
            $field.find('input').after($warning);
        },

        /**
         * Feature #112: Warn user before page refresh during save operation.
         * This prevents data corruption by warning users that a save is in progress.
         */
        initBeforeUnloadWarning: function() {
            window.addEventListener('beforeunload', function(e) {
                // Only show warning if a save is in progress
                if (BWGIGFAdmin.isSaving) {
                    // Standard way to show a confirmation dialog
                    e.preventDefault();
                    // For older browsers, you need to set returnValue
                    e.returnValue = 'A save operation is in progress. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });
        },

        /**
         * Feature #117: Initialize feed search/filter functionality
         * Filters the feeds list table based on search input
         * Feature #119: Show "No feeds found" message when search returns no results
         */
        initFeedSearch: function() {
            var $searchInput = $('#bwg-igf-feed-search');
            var $feedsTable = $('#bwg-igf-feeds-table');
            var $searchCount = $('#bwg-igf-feed-search-count');

            if (!$searchInput.length || !$feedsTable.length) {
                return;
            }

            // Handle search input
            $searchInput.on('input keyup', function() {
                var searchTerm = $(this).val().toLowerCase().trim();
                var $tbody = $feedsTable.find('tbody');
                var $rows = $tbody.find('tr:not(.bwg-igf-no-results-row)');
                var visibleCount = 0;
                var totalCount = $rows.length;

                // Remove any existing "no results" message
                $tbody.find('.bwg-igf-no-results-row').remove();

                $rows.each(function() {
                    var $row = $(this);
                    var feedName = $row.find('td:first-child').text().toLowerCase();

                    if (searchTerm === '' || feedName.indexOf(searchTerm) !== -1) {
                        $row.show();
                        visibleCount++;
                    } else {
                        $row.hide();
                    }
                });

                // Update count display
                if (searchTerm !== '') {
                    $searchCount.text(visibleCount + ' of ' + totalCount + ' feeds');

                    // Feature #119: Show "No feeds found" message when no matches
                    if (visibleCount === 0) {
                        var $noResultsRow = $('<tr class="bwg-igf-no-results-row"><td colspan="5" style="text-align: center; padding: 20px; color: #666;"><span class="dashicons dashicons-search" style="font-size: 24px; width: 24px; height: 24px; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto;"></span>No feeds found matching "<strong>' + BWGIGFAdmin.escapeHtml(searchTerm) + '</strong>".<br><small>Try a different search term or <a href="#" class="bwg-igf-clear-search">clear the search</a>.</small></td></tr>');
                        $tbody.append($noResultsRow);
                    }
                } else {
                    $searchCount.text('');
                }
            });

            // Clear search on Escape key
            $searchInput.on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $(this).val('').trigger('input');
                }
            });

            // Feature #119: Clear search when clicking the "clear the search" link
            $(document).on('click', '.bwg-igf-clear-search', function(e) {
                e.preventDefault();
                $searchInput.val('').trigger('input').focus();
            });
        },

        /**
         * Helper function to escape HTML special characters
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        },

        initColumnCountValidation: function() {
            var $columns = $('#bwg-igf-columns');
            var minValue = 1;
            var maxValue = 6;

            if (!$columns.length) {
                return;
            }

            // Validate on blur (when user leaves the field)
            $columns.on('blur', function() {
                var value = parseInt($(this).val(), 10);

                // Handle empty or non-numeric values
                if (isNaN(value) || value < minValue) {
                    $(this).val(minValue);
                    BWGIGFAdmin.showColumnCountWarning('Columns set to minimum (' + minValue + ')');
                } else if (value > maxValue) {
                    $(this).val(maxValue);
                    BWGIGFAdmin.showColumnCountWarning('Columns limited to maximum (' + maxValue + ')');
                }

                // Update preview after correction
                BWGIGFAdmin.updatePreview();
            });

            // Validate on input (real-time as user types)
            $columns.on('input', function() {
                var value = parseInt($(this).val(), 10);
                var $field = $(this).closest('.bwg-igf-field');
                var $warning = $field.find('.bwg-igf-column-count-warning');

                // Remove existing warning
                $warning.remove();

                // Show warning if value is out of range
                if (!isNaN(value)) {
                    if (value < minValue) {
                        BWGIGFAdmin.showColumnCountInlineWarning($field, 'Minimum is ' + minValue);
                    } else if (value > maxValue) {
                        BWGIGFAdmin.showColumnCountInlineWarning($field, 'Maximum is ' + maxValue);
                    }
                }
            });
        },

        showColumnCountWarning: function(message) {
            // Show a brief notice that the value was auto-corrected
            var $notice = $('<div class="notice notice-warning is-dismissible bwg-igf-column-count-notice"><p>' + message + '</p></div>');
            $('.wrap h1').first().after($notice);

            // Auto-dismiss after 3 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        },

        showColumnCountInlineWarning: function($field, message) {
            var $warning = $('<span class="bwg-igf-column-count-warning" style="color: #d63638; margin-left: 10px; font-size: 12px;"><span class="dashicons dashicons-warning" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span> ' + message + '</span>');
            $field.find('input').after($warning);
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

            // Build classes array based on layout type
            var classes = ['bwg-igf-preview-content'];

            if (settings.layoutType === 'slider') {
                classes.push('bwg-igf-slider-preview');
                classes.push('bwg-igf-slider-' + settings.slidesToShow);
            } else {
                classes.push('bwg-igf-grid');
                classes.push('bwg-igf-grid-' + settings.columns);
            }

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

            // Handle account name header visibility (Feature #24)
            var $previewWrapper = $preview.closest('.bwg-igf-preview');
            var $accountHeader = $previewWrapper.find('.bwg-igf-preview-account-header');
            if (settings.showAccountName) {
                if (!$accountHeader.length) {
                    // Create account header element
                    var username = $('#bwg-igf-username').val() || 'instagram';
                    // Parse comma-separated usernames
                    var usernames = username.split(',').map(function(u) { return u.trim(); }).filter(function(u) { return u.length > 0; });
                    var displayName = usernames.length > 0 ? '@' + usernames[0] : '@instagram';
                    if (usernames.length > 1) {
                        displayName = usernames.map(function(u) { return '@' + u; }).join(', ');
                    }
                    var headerHtml = '<div class="bwg-igf-preview-account-header" style="display: flex; align-items: center; padding: 10px 15px; margin-bottom: 10px; background: #fafafa; border-radius: 8px; border: 1px solid #efefef;">' +
                        '<span style="display: flex; align-items: center; gap: 8px;">' +
                        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" style="fill: #e1306c;"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>' +
                        '<span style="font-size: 14px; font-weight: 600; color: #262626;">' + BWGIGFAdmin.escapeHtml(displayName) + '</span>' +
                        '</span></div>';
                    $preview.before(headerHtml);
                } else {
                    // Update username in existing header
                    var username = $('#bwg-igf-username').val() || 'instagram';
                    var usernames = username.split(',').map(function(u) { return u.trim(); }).filter(function(u) { return u.length > 0; });
                    var displayName = usernames.length > 0 ? '@' + usernames[0] : '@instagram';
                    if (usernames.length > 1) {
                        displayName = usernames.map(function(u) { return '@' + u; }).join(', ');
                    }
                    $accountHeader.find('span:last-child').text(displayName);
                    $accountHeader.show();
                }
            } else {
                $accountHeader.hide();
            }

            // Handle caption visibility in preview (Feature #26)
            if (settings.showCaption) {
                $preview.find('.bwg-igf-item').each(function() {
                    if (!$(this).find('.bwg-igf-caption').length) {
                        $(this).append('<div class="bwg-igf-caption" style="padding: 8px 10px; font-size: 12px; line-height: 1.4; color: #262626; background: #fafafa; border-top: 1px solid #efefef;">Sample caption text for preview...</div>');
                    } else {
                        $(this).find('.bwg-igf-caption').show();
                    }
                });
                // Adjust item aspect ratio when caption is shown
                $preview.find('.bwg-igf-item').css('aspect-ratio', 'auto');
                $preview.find('.bwg-igf-item img').css({'aspect-ratio': '1 / 1', 'height': 'auto'});
            } else {
                $preview.find('.bwg-igf-caption').hide();
                // Restore default aspect ratio
                $preview.find('.bwg-igf-item').css('aspect-ratio', '');
                $preview.find('.bwg-igf-item img').css({'aspect-ratio': '', 'height': ''});
            }

            // Handle post count in preview (Feature #28)
            var maxPreviewItems = 50; // Maximum items we can show in preview
            var $items = $preview.find('.bwg-igf-item');
            var currentItemCount = $items.length;
            var desiredCount = Math.min(Math.max(settings.postCount, 1), maxPreviewItems);

            // Add or remove items to match the post count
            if (currentItemCount < desiredCount) {
                // Need to add more items - use realistic placeholder images (Feature #37)
                for (var i = currentItemCount; i < desiredCount; i++) {
                    // Use unique seeds for variety - add offset based on index to get different images
                    var seed = 100 + (i * 13) % 200; // Generate varied seeds between 100-299
                    var placeholderUrl = 'https://picsum.photos/seed/' + seed + '/400/400';
                    var newItem = '<div class="bwg-igf-item" data-placeholder-seed="' + seed + '"><img src="' + placeholderUrl + '" alt="Preview placeholder" loading="lazy"></div>';
                    $preview.append(newItem);
                }
            } else if (currentItemCount > desiredCount) {
                // Need to remove items
                $items.slice(desiredCount).remove();
            }

            // Re-apply caption visibility to any new items
            if (settings.showCaption) {
                $preview.find('.bwg-igf-item').each(function() {
                    if (!$(this).find('.bwg-igf-caption').length) {
                        $(this).append('<div class="bwg-igf-caption" style="padding: 8px 10px; font-size: 12px; line-height: 1.4; color: #262626; background: #fafafa; border-top: 1px solid #efefef;">Sample caption text for preview...</div>');
                    }
                });
            }

            // Re-apply border radius to any new items
            $preview.find('.bwg-igf-item').css('border-radius', settings.borderRadius + 'px');

            // Handle follow button visibility (Feature #27)
            var $followButtonWrapper = $previewWrapper.find('.bwg-igf-preview-follow-wrapper');
            if (settings.showFollowButton) {
                var buttonText = settings.followButtonText || 'Follow on Instagram';
                var buttonStyle = settings.followButtonStyle || 'gradient';
                var username = $('#bwg-igf-username').val() || 'instagram';
                var firstUsername = username.split(',')[0].trim() || 'instagram';

                if (!$followButtonWrapper.length) {
                    // Create follow button element
                    var followHtml = '<div class="bwg-igf-preview-follow-wrapper" style="text-align: center; margin-top: 15px;">' +
                        '<a href="#" class="bwg-igf-follow bwg-igf-follow-' + BWGIGFAdmin.escapeHtml(buttonStyle) + '" onclick="return false;" style="pointer-events: none;">' +
                        BWGIGFAdmin.escapeHtml(buttonText) +
                        '</a></div>';
                    $preview.after(followHtml);
                } else {
                    // Update existing button text and style
                    var $button = $followButtonWrapper.find('.bwg-igf-follow');
                    $button.text(buttonText);
                    $button.attr('class', 'bwg-igf-follow bwg-igf-follow-' + buttonStyle);
                    $followButtonWrapper.show();
                }
            } else {
                $followButtonWrapper.hide();
            }
        },

        getFormSettings: function() {
            // Get background color from color picker (may need to check the hidden input)
            var bgColor = $('#bwg-igf-background-color').val();

            return {
                layoutType: $('#bwg-igf-layout-type').val() || 'grid',
                columns: $('#bwg-igf-columns').val() || 3,
                gap: $('#bwg-igf-gap').val() || 10,
                slidesToShow: $('#bwg-igf-slides-to-show').val() || 3,
                slidesToScroll: $('#bwg-igf-slides-to-scroll').val() || 1,
                autoplay: $('#bwg-igf-autoplay').is(':checked'),
                autoplaySpeed: $('#bwg-igf-autoplay-speed').val() || 3000,
                showArrows: $('#bwg-igf-show-arrows').is(':checked'),
                showDots: $('#bwg-igf-show-dots').is(':checked'),
                infiniteLoop: $('#bwg-igf-infinite-loop').is(':checked'),
                borderRadius: $('#bwg-igf-border-radius').val() || 0,
                hoverEffect: $('#bwg-igf-hover-effect').val() || 'none',
                backgroundColor: bgColor || '',
                customCSS: $('#bwg-igf-custom-css').val() || '',
                showAccountName: $('#bwg-igf-show-account-name').is(':checked'),
                showCaption: $('input[name="show_caption"]').is(':checked'),
                showFollowButton: $('#bwg-igf-show-follow-button').is(':checked'),
                followButtonText: $('#bwg-igf-follow-button-text').val() || 'Follow on Instagram',
                followButtonStyle: $('#bwg-igf-follow-button-style').val() || 'gradient',
                postCount: parseInt($('#bwg-igf-post-count').val(), 10) || 9
            };
        },

        /**
         * Feature #16: Duplicate feed handler
         * Creates a copy of an existing feed with '(Copy)' suffix
         */
        handleDuplicateFeed: function(e) {
            e.preventDefault();

            var $button = $(this);
            var feedId = $button.data('feed-id');

            // Prevent double-click
            if ($button.data('duplicating')) {
                return;
            }

            // Mark as duplicating
            $button.data('duplicating', true);
            $button.css('pointer-events', 'none').css('opacity', '0.5');

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bwg_igf_duplicate_feed',
                    nonce: bwgIgfAdmin.nonce,
                    feed_id: feedId
                },
                success: function(response) {
                    if (response.success) {
                        // Show success notification
                        BWGIGFAdmin.showNotice('success', response.data.message || 'Feed duplicated successfully!');

                        // Redirect to feeds list to show the new copy
                        if (response.data.redirect) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1000);
                        } else {
                            // Reload to show new feed
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        }
                    } else {
                        // Reset button state on failure
                        $button.data('duplicating', false);
                        $button.css('pointer-events', '').css('opacity', '');
                        BWGIGFAdmin.showNotice('error', response.data.message || bwgIgfAdmin.i18n.error);
                    }
                },
                error: function() {
                    // Reset button state on error
                    $button.data('duplicating', false);
                    $button.css('pointer-events', '').css('opacity', '');
                    BWGIGFAdmin.showNotice('error', bwgIgfAdmin.i18n.error);
                }
            });
        },

        handleDeleteFeed: function(e) {
            e.preventDefault();

            var $button = $(this);
            var feedId = $button.data('feed-id');

            // Feature #111: Prevent double-click on delete
            // Check if this specific feed is already being deleted
            if (BWGIGFAdmin.isDeleting[feedId]) {
                return;
            }

            if (!confirm(bwgIgfAdmin.i18n.confirmDelete)) {
                return;
            }

            // Feature #111: Mark this feed as being deleted BEFORE the AJAX call
            BWGIGFAdmin.isDeleting[feedId] = true;

            // Disable the delete button to provide visual feedback
            $button.css('pointer-events', 'none').css('opacity', '0.5');

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
                        // Show success notification (Feature #90)
                        BWGIGFAdmin.showNotice('success', response.data.message || bwgIgfAdmin.i18n.feedDeleted);

                        $button.closest('tr').fadeOut(function() {
                            $(this).remove();
                            // Clean up the deletion flag
                            delete BWGIGFAdmin.isDeleting[feedId];
                        });
                    } else {
                        // Feature #111: Reset state on failure so user can try again
                        delete BWGIGFAdmin.isDeleting[feedId];
                        $button.css('pointer-events', '').css('opacity', '');
                        BWGIGFAdmin.showNotice('error', response.data.message || bwgIgfAdmin.i18n.error);
                    }
                },
                error: function() {
                    // Feature #111: Reset state on error so user can try again
                    delete BWGIGFAdmin.isDeleting[feedId];
                    $button.css('pointer-events', '').css('opacity', '');
                    BWGIGFAdmin.showNotice('error', bwgIgfAdmin.i18n.error);
                }
            });
        },

        handleSaveFeed: function(e) {
            e.preventDefault();

            // Feature #110: Prevent double-click / rapid clicks from creating multiple feeds
            if (BWGIGFAdmin.isSaving) {
                return;
            }

            var $form = $(this);
            var $button = $form.find('button[type="submit"]');
            var originalText = $button.text();
            var originalHtml = $button.html();

            // Client-side validation for required feed name
            var $nameField = $form.find('#bwg-igf-name');
            var feedName = $nameField.val().trim();

            // Clear any existing validation error styling
            BWGIGFAdmin.clearFieldError($nameField);

            if (!feedName) {
                // Show error message and highlight the field
                BWGIGFAdmin.showFieldError($nameField, bwgIgfAdmin.i18n.feedNameRequired || 'Feed name is required.');
                $nameField.focus();
                return;
            }

            // Feature #110: Set the saving flag BEFORE any async operations
            BWGIGFAdmin.isSaving = true;

            // Show loading state with spinner (Feature #92)
            $button.prop('disabled', true).addClass('bwg-igf-saving');
            $button.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>' + bwgIgfAdmin.i18n.saving);

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: $form.serialize() + '&action=bwg_igf_save_feed&nonce=' + bwgIgfAdmin.nonce,
                success: function(response) {
                    if (response.success) {
                        // Show saved state (no spinner)
                        $button.html(bwgIgfAdmin.i18n.saved).removeClass('bwg-igf-saving');

                        // Feature #150: Check if there are validation warnings
                        var noticeType = 'success';
                        if (response.data.validation_warnings && response.data.validation_warnings.length > 0) {
                            // Show as warning (yellow) instead of success (green)
                            noticeType = 'warning';
                        }

                        // Show success/warning notice
                        BWGIGFAdmin.showNotice(noticeType, response.data.message);

                        // Redirect to feeds list if creating new feed
                        if (response.data.redirect) {
                            // Give more time to read warning if present
                            var delay = response.data.validation_warnings && response.data.validation_warnings.length > 0 ? 3000 : 1500;
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, delay);
                        } else {
                            setTimeout(function() {
                                $button.html(originalHtml).prop('disabled', false);
                                // Feature #110: Reset saving flag after button re-enabled
                                BWGIGFAdmin.isSaving = false;
                            }, 2000);
                        }
                    } else {
                        $button.html(originalHtml).prop('disabled', false).removeClass('bwg-igf-saving');
                        // Feature #110: Reset saving flag on failure
                        BWGIGFAdmin.isSaving = false;
                        BWGIGFAdmin.showNotice('error', response.data.message || bwgIgfAdmin.i18n.error);
                    }
                },
                error: function(xhr) {
                    $button.html(originalHtml).prop('disabled', false).removeClass('bwg-igf-saving');
                    // Feature #110: Reset saving flag on error
                    BWGIGFAdmin.isSaving = false;

                    // Try to parse the error response from wp_send_json_error
                    var errorMessage = bwgIgfAdmin.i18n.error;
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response && response.data && response.data.message) {
                            errorMessage = response.data.message;
                        }
                    } catch (e) {
                        // If parsing fails, use the default error message
                    }

                    BWGIGFAdmin.showNotice('error', errorMessage);
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
            var originalText = $button.text();

            // Show loading state with spinner
            $button.prop('disabled', true);
            $button.html('<span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span> Refreshing...');

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
                    $button.text(originalText);
                    if (response.success) {
                        BWGIGFAdmin.showNotice('success', 'Cache refreshed successfully!');
                        // Update cache timestamp display if present
                        if (response.data && response.data.timestamp) {
                            $('.bwg-igf-cache-timestamp').text('Last refreshed: ' + response.data.timestamp);
                        } else {
                            // Update with current time
                            var now = new Date();
                            var timeStr = now.toLocaleString();
                            $('.bwg-igf-cache-timestamp').text('Last refreshed: ' + timeStr);
                        }
                    } else {
                        BWGIGFAdmin.showNotice('error', response.data.message || bwgIgfAdmin.i18n.error);
                    }
                },
                error: function() {
                    $button.prop('disabled', false);
                    $button.text(originalText);
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
                        // Feature #150: Check if this is a warning (uncertain validation) or full success
                        if (response.data.warning) {
                            // Show warning icon (yellow) - validation uncertain but save is allowed
                            $indicator.html('<span class="dashicons dashicons-warning" style="color: #dba617;"></span> ' + response.data.message);
                        } else {
                            // Show success icon (green) - fully validated
                            $indicator.html('<span class="dashicons dashicons-yes" style="color: green;"></span>');
                        }
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
        },

        showFieldError: function($field, message) {
            // Add error class to the field
            $field.addClass('bwg-igf-field-error');

            // Create or update error message below the field
            var $parent = $field.closest('.bwg-igf-field');
            var $errorMsg = $parent.find('.bwg-igf-field-error-message');

            if (!$errorMsg.length) {
                $errorMsg = $('<p class="bwg-igf-field-error-message" style="color: #d63638; margin-top: 4px; font-size: 13px;"></p>');
                $field.after($errorMsg);
            }

            $errorMsg.html('<span class="dashicons dashicons-warning" style="font-size: 16px; width: 16px; height: 16px; margin-right: 4px; vertical-align: middle;"></span>' + message);
        },

        clearFieldError: function($field) {
            // Remove error class from the field
            $field.removeClass('bwg-igf-field-error');

            // Remove error message
            var $parent = $field.closest('.bwg-igf-field');
            $parent.find('.bwg-igf-field-error-message').remove();
        },

        handleClearValidationOnInput: function() {
            var $field = $(this);
            var fieldValue = $field.val().trim();

            // If the field now has a value, clear the error
            if (fieldValue) {
                BWGIGFAdmin.clearFieldError($field);
            }
        },

        handleConnectAccount: function(e) {
            // Check if this button has a real OAuth URL (from data attribute)
            var $button = $(this);
            var oauthUrl = $button.data('oauth-url');

            // If we have a real OAuth URL, let the browser navigate to it naturally
            // (don't prevent default - the href will handle the navigation)
            if (oauthUrl && oauthUrl.indexOf('api.instagram.com') !== -1) {
                // Real OAuth flow - allow the link to work normally
                // The button's href already points to the OAuth URL
                return true;
            }

            // Fallback: Test mode for when OAuth is not properly configured
            // This allows testing the flow without real Instagram credentials
            e.preventDefault();

            // For testing purposes, we'll prompt for test data
            var username = prompt('Enter Instagram username (for testing):');
            if (!username) {
                return;
            }

            // Generate test data
            var testData = {
                username: username,
                instagram_user_id: Math.floor(Math.random() * 9000000000) + 1000000000,
                access_token: 'IGQVJWZADJLazNHUm1jM2' + BWGIGFAdmin.generateRandomString(100),
                account_type: 'basic'
            };

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bwg_igf_connect_account',
                    nonce: bwgIgfAdmin.nonce,
                    username: testData.username,
                    instagram_user_id: testData.instagram_user_id,
                    access_token: testData.access_token,
                    account_type: testData.account_type
                },
                success: function(response) {
                    if (response.success) {
                        BWGIGFAdmin.showNotice('success', response.data.message);
                        // Reload page to see new account
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        BWGIGFAdmin.showNotice('error', response.data.message || bwgIgfAdmin.i18n.error);
                    }
                },
                error: function() {
                    BWGIGFAdmin.showNotice('error', bwgIgfAdmin.i18n.error);
                }
            });
        },

        handleDisconnectAccount: function(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to disconnect this Instagram account?')) {
                return;
            }

            var $button = $(this);
            var accountId = $button.data('account-id');

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bwg_igf_disconnect_account',
                    nonce: bwgIgfAdmin.nonce,
                    account_id: accountId
                },
                success: function(response) {
                    if (response.success) {
                        BWGIGFAdmin.showNotice('success', response.data.message);
                        $button.closest('tr').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        BWGIGFAdmin.showNotice('error', response.data.message || bwgIgfAdmin.i18n.error);
                    }
                },
                error: function() {
                    BWGIGFAdmin.showNotice('error', bwgIgfAdmin.i18n.error);
                }
            });
        },

        handleVerifyEncryption: function(e) {
            e.preventDefault();

            var $button = $(this);
            var accountId = $button.data('account-id');
            var $result = $button.siblings('.bwg-igf-encryption-result');

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bwg_igf_verify_token_encryption',
                    nonce: bwgIgfAdmin.nonce,
                    account_id: accountId
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        var html = '<div class="bwg-igf-encryption-info">';
                        html += '<strong>Token Preview:</strong> ' + data.token_preview + '<br>';
                        html += '<strong>Encrypted:</strong> ' + (data.is_encrypted ? '<span style="color:green;">✓ Yes</span>' : '<span style="color:red;">✗ No</span>') + '<br>';
                        html += '<strong>Method:</strong> ' + data.encryption_method + '<br>';
                        if (data.is_plaintext) {
                            html += '<span style="color:red;">⚠ Warning: Token appears to be stored in plaintext!</span>';
                        }
                        html += '</div>';
                        $result.html(html).show();
                    } else {
                        BWGIGFAdmin.showNotice('error', response.data.message || bwgIgfAdmin.i18n.error);
                    }
                },
                error: function() {
                    BWGIGFAdmin.showNotice('error', bwgIgfAdmin.i18n.error);
                }
            });
        },

        handleCheckGitHubUpdates: function(e) {
            e.preventDefault();

            var $button = $(this);
            var $statusSpan = $('#bwg-igf-check-updates-status');
            var $lastCheckedSpan = $('#bwg-igf-last-checked');
            var originalText = $button.text();

            // Disable button and show loading state
            $button.prop('disabled', true).text('Checking...');
            $statusSpan.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');

            $.ajax({
                url: bwgIgfAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'bwg_igf_check_github_updates',
                    nonce: bwgIgfAdmin.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false).text(originalText);
                    $statusSpan.html('');

                    if (response.success) {
                        var data = response.data;
                        var noticeType = data.update_available ? 'info' : 'success';
                        BWGIGFAdmin.showNotice(noticeType, data.message);

                        // Feature #191: Update the last checked timestamp display
                        if (data.last_checked_formatted && $lastCheckedSpan.length) {
                            $lastCheckedSpan.text(data.last_checked_formatted);
                        }

                        // If update available, refresh the page to show WordPress update notice
                        if (data.update_available) {
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        }
                    } else {
                        BWGIGFAdmin.showNotice('error', response.data.message || bwgIgfAdmin.i18n.error);
                    }
                },
                error: function() {
                    $button.prop('disabled', false).text(originalText);
                    $statusSpan.html('');
                    BWGIGFAdmin.showNotice('error', bwgIgfAdmin.i18n.error);
                }
            });
        },

        generateRandomString: function(length) {
            var result = '';
            var characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            for (var i = 0; i < length; i++) {
                result += characters.charAt(Math.floor(Math.random() * characters.length));
            }
            return result;
        }
    };

    // Export for global access if needed
    window.BWGIGFAdmin = BWGIGFAdmin;

})(jQuery);
