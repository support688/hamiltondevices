<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');
$result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='theme_mods_flatsome-child'");
$row = $result->fetch_assoc();
$mods = unserialize($row['option_value']);

// Dump ALL keys that contain a number (like _2, _3, _5) to find the pattern
echo "=== Keys with numbers (likely HTML block settings) ===\n";
foreach ($mods as $k => $v) {
    if (preg_match('/[_\-][2345]/', $k) && !is_array($v) && !empty($v) && strlen($v) < 500) {
        echo "$k => " . substr($v, 0, 300) . "\n---\n";
    }
}

echo "\n=== ALL keys (just names) ===\n";
foreach ($mods as $k => $v) {
    if (!is_array($v)) {
        echo "$k\n";
    }
}
$conn->close();
