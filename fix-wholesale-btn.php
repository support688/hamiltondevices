<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

$result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='theme_mods_flatsome-child'");
$row = $result->fetch_assoc();
$mods = unserialize($row['option_value']);

// Flatsome mapping:
// html   -> topbar_left (top bar left)
// html-2 -> topbar_right (top bar right)
// html-3 -> top_right_text
// html-4 -> nav_position_text_top
// html-5 -> nav_position_text

// Current header_elements_right = [html-2, account, cart]
// html-2 maps to topbar_right - but that's used in the topbar!
// We need to use html-3 which maps to top_right_text

// Put the wholesale login button in html-3 (top_right_text)
$mods['top_right_text'] = '<a href="/wholesale-log-in-page/" style="background:#c72035;color:#fff;padding:8px 18px;border-radius:4px;font-weight:600;font-size:13px;text-decoration:none;white-space:nowrap;display:inline-block;">Wholesale Login</a>';

// Update header_elements_right to use html-3 instead of html-2
$mods['header_elements_right'] = ['html-3', 'account', 'cart'];

// Clean up the old html_text_2 key we mistakenly set
unset($mods['html_text_2']);

$new_mods = serialize($mods);
$stmt = $conn->prepare("UPDATE wp_options SET option_value=? WHERE option_name='theme_mods_flatsome-child'");
$stmt->bind_param('s', $new_mods);
$stmt->execute();

echo "header_elements_right: " . print_r($mods['header_elements_right'], true);
echo "top_right_text: " . $mods['top_right_text'] . "\n";
echo "Done! Wholesale Login button should now appear on the far right.\n";

$conn->close();
