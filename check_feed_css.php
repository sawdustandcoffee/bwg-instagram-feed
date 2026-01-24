<?php
/**
 * Check and update feed styling settings
 */

// Load WordPress
require_once('/var/www/html/wp-load.php');

global $wpdb;

// Get feed 1 styling settings
$feed = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = 1");

echo "Current styling_settings for feed 1:\n";
echo $feed->styling_settings . "\n\n";

// Update with custom CSS
$new_styling = json_encode(array(
    'background_color' => '',
    'border_radius' => 0,
    'hover_effect' => 'none',
    'custom_css' => '.bwg-igf-feed .bwg-igf-item { border: 5px solid #e1306c !important; }'
));

$result = $wpdb->update(
    $wpdb->prefix . 'bwg_igf_feeds',
    array('styling_settings' => $new_styling),
    array('id' => 1),
    array('%s'),
    array('%d')
);

if ($result !== false) {
    echo "Updated styling_settings successfully!\n";
    echo "New value: " . $new_styling . "\n";
} else {
    echo "Failed to update.\n";
}
