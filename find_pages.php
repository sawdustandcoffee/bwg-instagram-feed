<?php
$db = new mysqli('127.0.0.1', 'wordpress', 'wordpress', 'wordpress', 3307);
$result = $db->query("SELECT ID, post_title FROM wp_posts WHERE post_content LIKE '%bwg_igf%' AND post_status='publish' AND post_type='page'");
while ($row = $result->fetch_assoc()) {
    echo "Page ID: " . $row['ID'] . " - " . $row['post_title'] . "\n";
}
$db->close();
