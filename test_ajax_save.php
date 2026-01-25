<?php
/**
 * Test AJAX save functionality
 */
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Log in as admin
$user = get_user_by('login', 'admin');
if ($user) {
    wp_set_current_user($user->ID);
}

echo "User logged in: " . (is_user_logged_in() ? 'YES' : 'NO') . "\n";
echo "Can manage_options: " . (current_user_can('manage_options') ? 'YES' : 'NO') . "\n";

// Create a valid nonce
$nonce = wp_create_nonce('bwg_igf_admin_nonce');
echo "Generated nonce: $nonce\n";

// Verify the nonce
echo "Nonce valid: " . (wp_verify_nonce($nonce, 'bwg_igf_admin_nonce') ? 'YES' : 'NO') . "\n";

// Simulate the AJAX request
$_POST = array(
    'nonce' => $nonce,
    'feed_id' => 1,
    'name' => 'Test Feed',
    'feed_type' => 'public',
    'instagram_usernames' => 'testuser',
    'layout_type' => 'slider',
    'columns' => 3,
    'gap' => 10,
    'slides_to_show' => 4,
    'slides_to_scroll' => 1,
    'autoplay' => 1,
    'autoplay_speed' => 3000,
    'show_arrows' => 1,
    'show_dots' => 1,
    'infinite_loop' => 1,
    'post_count' => 9,
    'show_likes' => 1,
    'show_comments' => 1,
    'show_follow_button' => 1,
    'background_color' => '#ff5733',
    'border_radius' => 0,
    'hover_effect' => 'zoom',
    'custom_css' => '',
    'popup_enabled' => 1,
    'ordering' => 'newest',
    'cache_duration' => 3600,
);

// Create instance and call save_feed
$ajax = new BWG_IGF_Admin_Ajax();

// Capture the JSON response
ob_start();
try {
    $ajax->save_feed();
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
$output = ob_get_clean();

echo "Output: $output\n";
