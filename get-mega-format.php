<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// Get the mobile Products menu item (74568) to see the grid format
$result = $conn->query("SELECT meta_value FROM wp_postmeta WHERE post_id=74568 AND meta_key='_megamenu'");
$row = $result->fetch_assoc();
$config = unserialize($row['meta_value']);
echo "=== MOBILE PRODUCTS MEGAMENU CONFIG ===\n";
echo json_encode($config, JSON_PRETTY_PRINT) . "\n";

// Get category names for the objects
echo "\n=== CATEGORY NAMES ===\n";
$cats = [542, 554, 543, 1050, 550, 1265, 545];
foreach ($cats as $cat_id) {
    $r = $conn->query("SELECT name FROM wp_terms WHERE term_id=$cat_id");
    $c = $r->fetch_assoc();
    echo "cat $cat_id => {$c['name']}\n";
}

$conn->close();
