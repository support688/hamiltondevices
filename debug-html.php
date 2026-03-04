<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');
$result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='theme_mods_flatsome-child'");
$row = $result->fetch_assoc();
$mods = unserialize($row['option_value']);

echo "=== All HTML-related keys ===\n";
foreach ($mods as $k => $v) {
    if (stripos($k, 'html') !== false && !is_array($v)) {
        echo "$k => " . substr($v, 0, 200) . "\n---\n";
    }
}

echo "\n=== Header elements ===\n";
foreach ($mods as $k => $v) {
    if (stripos($k, 'header_elements') !== false) {
        echo "$k => " . print_r($v, true) . "\n";
    }
}

echo "\n=== nav_position_text ===\n";
echo $mods['nav_position_text'] ?? 'NOT SET';
echo "\n\n=== nav_position_text_top ===\n";
echo $mods['nav_position_text_top'] ?? 'NOT SET';
echo "\n";

$conn->close();
