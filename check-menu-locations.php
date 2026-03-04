<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// Check both parent and child theme mods for nav_menu_locations
foreach (['theme_mods_flatsome', 'theme_mods_flatsome-child'] as $opt) {
    $result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='$opt'");
    $row = $result->fetch_assoc();
    if ($row) {
        $mods = unserialize($row['option_value']);
        echo "=== $opt ===\n";
        if (isset($mods['nav_menu_locations'])) {
            echo "nav_menu_locations: " . json_encode($mods['nav_menu_locations']) . "\n";
        } else {
            echo "No nav_menu_locations set\n";
        }
    }
}

// Check megamenu settings to see which location is enabled
$result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='megamenu_settings'");
$row = $result->fetch_assoc();
$settings = unserialize($row['option_value']);
echo "\n=== MEGA MENU SETTINGS ===\n";
echo json_encode($settings, JSON_PRETTY_PRINT) . "\n";

// Check which menu is at the primary location - may be overridden
$result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='megamenu_menu_locations'");
$row = $result->fetch_assoc();
if ($row) {
    echo "\n=== MEGA MENU LOCATIONS ===\n";
    echo $row['option_value'] . "\n";
}

$conn->close();
