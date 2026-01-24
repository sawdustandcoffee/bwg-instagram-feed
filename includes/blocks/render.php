<?php
/**
 * Server-side rendering of the BWG Instagram Feed block.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$feed_id = isset( $attributes['feedId'] ) ? absint( $attributes['feedId'] ) : 0;

if ( empty( $feed_id ) ) {
    return '';
}

// Use the existing shortcode rendering.
echo do_shortcode( '[bwg_igf id="' . $feed_id . '"]' );
