<?php
$db = new SQLite3('features.db');
$result = $db->query("SELECT * FROM features WHERE id = 149");
$row = $result->fetchArray(SQLITE3_ASSOC);
if ($row) {
    echo "Feature #149:\n";
    echo "ID: " . $row['id'] . "\n";
    echo "Priority: " . $row['priority'] . "\n";
    echo "Category: " . $row['category'] . "\n";
    echo "Name: " . $row['name'] . "\n";
    echo "Description: " . $row['description'] . "\n";
    echo "Steps: " . $row['steps'] . "\n";
    echo "Passes: " . $row['passes'] . "\n";
    echo "In Progress: " . $row['in_progress'] . "\n";
} else {
    echo "Feature #149 not found\n";
}
$db->close();
