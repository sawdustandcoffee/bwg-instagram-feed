/**
 * BWG Instagram Feed Gutenberg Block.
 *
 * @package BWG_Instagram_Feed
 */

( function( wp ) {
    'use strict';

    const { registerBlockType } = wp.blocks;
    const { createElement, Fragment } = wp.element;
    const { InspectorControls, useBlockProps } = wp.blockEditor || wp.editor;
    const { PanelBody, SelectControl, Placeholder, Spinner } = wp.components;
    const { __ } = wp.i18n;
    const ServerSideRender = wp.serverSideRender || wp.components.ServerSideRender;

    // Get block data from PHP.
    const blockData = window.bwgIgfBlockData || {
        feeds: [],
        i18n: {
            blockTitle: 'BWG Instagram Feed',
            blockDescription: 'Display an Instagram feed on your page.',
            selectFeed: 'Select a Feed',
            noFeedsMessage: 'No feeds found. Please create a feed first.',
            createFeedLink: 'Create a Feed',
            feedLabel: 'Feed',
            previewLabel: 'Preview'
        },
        adminUrl: ''
    };

    // Instagram icon for block.
    const instagramIcon = createElement(
        'svg',
        {
            width: 24,
            height: 24,
            viewBox: '0 0 24 24',
            fill: 'none',
            xmlns: 'http://www.w3.org/2000/svg'
        },
        createElement( 'path', {
            d: 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073z',
            fill: '#E4405F'
        } ),
        createElement( 'path', {
            d: 'M12 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8z',
            fill: '#E4405F'
        } ),
        createElement( 'circle', {
            cx: '18.406',
            cy: '5.594',
            r: '1.44',
            fill: '#E4405F'
        } )
    );

    // Build feed options for SelectControl.
    const getFeedOptions = function() {
        const options = [
            { value: '', label: blockData.i18n.selectFeed }
        ];

        if ( blockData.feeds && blockData.feeds.length > 0 ) {
            blockData.feeds.forEach( function( feed ) {
                options.push( {
                    value: feed.value,
                    label: feed.label
                } );
            } );
        }

        return options;
    };

    // Register the block.
    registerBlockType( 'bwg-igf/instagram-feed', {
        title: blockData.i18n.blockTitle,
        description: blockData.i18n.blockDescription,
        icon: instagramIcon,
        category: 'widgets',
        keywords: [
            __( 'instagram', 'bwg-instagram-feed' ),
            __( 'feed', 'bwg-instagram-feed' ),
            __( 'social', 'bwg-instagram-feed' ),
            __( 'bwg', 'bwg-instagram-feed' ),
            __( 'gallery', 'bwg-instagram-feed' )
        ],
        supports: {
            align: [ 'wide', 'full' ],
            anchor: true,
            html: false
        },
        attributes: {
            feedId: {
                type: 'string',
                default: ''
            }
        },

        /**
         * Edit function for block editor.
         *
         * @param {Object} props Block properties.
         * @return {Object} Block editor component.
         */
        edit: function( props ) {
            const { attributes, setAttributes } = props;
            const { feedId } = attributes;
            const blockProps = useBlockProps ? useBlockProps() : {};

            // Handler for feed selection change.
            const onFeedChange = function( newFeedId ) {
                setAttributes( { feedId: newFeedId } );
            };

            // No feeds available.
            if ( ! blockData.feeds || blockData.feeds.length === 0 ) {
                return createElement(
                    'div',
                    blockProps,
                    createElement( Placeholder, {
                        icon: instagramIcon,
                        label: blockData.i18n.blockTitle,
                        instructions: blockData.i18n.noFeedsMessage
                    },
                    createElement(
                        'a',
                        {
                            href: blockData.adminUrl,
                            className: 'components-button is-primary',
                            target: '_blank',
                            rel: 'noopener noreferrer'
                        },
                        blockData.i18n.createFeedLink
                    ) )
                );
            }

            // No feed selected yet.
            if ( ! feedId ) {
                return createElement(
                    'div',
                    blockProps,
                    createElement( Placeholder, {
                        icon: instagramIcon,
                        label: blockData.i18n.blockTitle,
                        instructions: blockData.i18n.blockDescription
                    },
                    createElement( SelectControl, {
                        value: feedId,
                        options: getFeedOptions(),
                        onChange: onFeedChange
                    } ) )
                );
            }

            // Feed selected - show preview with inspector controls.
            return createElement(
                Fragment,
                null,
                createElement(
                    InspectorControls,
                    null,
                    createElement(
                        PanelBody,
                        {
                            title: blockData.i18n.feedLabel,
                            initialOpen: true
                        },
                        createElement( SelectControl, {
                            label: blockData.i18n.selectFeed,
                            value: feedId,
                            options: getFeedOptions(),
                            onChange: onFeedChange
                        } )
                    )
                ),
                createElement(
                    'div',
                    blockProps,
                    createElement( ServerSideRender, {
                        block: 'bwg-igf/instagram-feed',
                        attributes: attributes,
                        LoadingResponsePlaceholder: function() {
                            return createElement(
                                Placeholder,
                                {
                                    icon: instagramIcon,
                                    label: blockData.i18n.blockTitle
                                },
                                createElement( Spinner )
                            );
                        },
                        ErrorResponsePlaceholder: function() {
                            return createElement(
                                Placeholder,
                                {
                                    icon: instagramIcon,
                                    label: blockData.i18n.blockTitle,
                                    instructions: __( 'Error loading feed preview.', 'bwg-instagram-feed' )
                                }
                            );
                        }
                    } )
                )
            );
        },

        /**
         * Save function - returns null for dynamic blocks.
         *
         * @return {null} Returns null as this is a dynamic block.
         */
        save: function() {
            // Dynamic block - rendered on server.
            return null;
        }
    } );

} )( window.wp );
