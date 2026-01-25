<?php
// Re-enable arrows for feed 29
require_once '/var/www/html/wp-load.php';
global $wpdb;

$feed_id = 29;
$feed = $wpdb->get_row($wpdb->prepare(
    "SELECT layout_settings FROM {$wpdb->prefix}bwg_igf_feeds WHERE id = %d",
    $feed_id
));

$layout_settings = json_decode($feed->layout_settings, true);
$layout_settings['show_arrows'] = true;

$result = $wpdb->update(
    $wpdb->prefix . 'bwg_igf_feeds',
    ['layout_settings' => json_encode($layout_settings)],
    ['id' => $feed_id]
);

echo $result !== false ? "SUCCESS: Arrows re-enabled for feed $feed_id" : "FAILED";
