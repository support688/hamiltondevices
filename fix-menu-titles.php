<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// Set custom titles on the menu items that are blank (they inherit from the category name)
// We can set post_title on the nav_menu_item posts to override the category name

// CCELL Batteries - item 175343 (currently uses cat name "CCELL® & Vape Batteries")
$conn->query("UPDATE wp_posts SET post_title='CCELL® Batteries' WHERE ID=175343");
echo "175343 (Batteries): " . $conn->affected_rows . "\n";

// Hamilton Devices brands - item 175348 (currently "Vape Batteries & Devices")
$conn->query("UPDATE wp_posts SET post_title='Hamilton Devices' WHERE ID=175348");
echo "175348 (Hamilton): " . $conn->affected_rows . "\n";

// CCELL Pod Systems - item 176278 (currently uses cat name "CCell® Pod Systems")
$conn->query("UPDATE wp_posts SET post_title='CCELL® Pod Systems' WHERE ID=176278");
echo "176278 (Pods): " . $conn->affected_rows . "\n";

$conn->close();
