<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');
$result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='theme_mods_flatsome-child'");
$row = $result->fetch_assoc();
$mods = unserialize($row['option_value']);

foreach ($mods as $k => $v) {
    if (stripos($k, 'logo') !== false || stripos($k, 'header') !== false || stripos($k, 'html') !== false) {
        echo $k . ' => ' . (is_array($v) ? json_encode($v) : $v) . "\n";
    }
}
$conn->close();
