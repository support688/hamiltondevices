<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');
$result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='theme_mods_flatsome-child'");
$row = $result->fetch_assoc();
$mods = unserialize($row['option_value']);

// Check all html text values
$html_keys = ['html_text', 'html_text_2', 'top_right_text', 'nav_position_text_top', 'nav_position_text'];
foreach ($html_keys as $k) {
    echo "[$k] => " . ($mods[$k] ?? '(not set)') . "\n\n";
}

// Check site logo
echo "site_logo => " . ($mods['site_logo'] ?? '(not set)') . "\n";
echo "logo_width => " . ($mods['logo_width'] ?? '(not set)') . "\n";
echo "dav_logo => " . ($mods['dav_logo'] ?? '(not set)') . "\n";

// Get the site logo URL
$logo_id = $mods['site_logo'];
$result2 = $conn->query("SELECT guid, post_title FROM wp_posts WHERE ID=$logo_id");
$logo = $result2->fetch_assoc();
echo "logo URL => " . $logo['guid'] . "\n";
echo "logo title => " . $logo['post_title'] . "\n";

// Check if there are other logo images in header elements
echo "\nheader_elements_left => " . json_encode($mods['header_elements_left']) . "\n";
echo "header_elements_right => " . json_encode($mods['header_elements_right']) . "\n";

$conn->close();
