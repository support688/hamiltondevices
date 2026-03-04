<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');
$result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='theme_mods_flatsome-child'");
$row = $result->fetch_assoc();
$mods = unserialize($row['option_value']);

$css = $mods['html_custom_css'] ?? '';
// Extract lines with cel, ccell, flaka, logo
$lines = explode("\n", $css);
$capture = false;
$buffer = '';
foreach ($lines as $line) {
    if (preg_match('/\.cel|\.ccell|\.flaka|#logo|header.logo|header_logo/i', $line)) {
        $capture = true;
    }
    if ($capture) {
        $buffer .= $line . "\n";
        if (strpos($line, '}') !== false) {
            echo $buffer;
            $buffer = '';
            $capture = false;
        }
    }
}
$conn->close();
