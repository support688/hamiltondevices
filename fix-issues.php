<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// ============================================================
// 1. Fix CF7 Form - add required metadata
// ============================================================
$form_id = 242983;

// Copy the _form content from post_content
$result = $conn->query("SELECT post_content FROM wp_posts WHERE ID=$form_id");
$form_content = $result->fetch_assoc()['post_content'];

// Get reference data from working form (ID 6) for _messages and _mail_2
$result = $conn->query("SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id=6 AND meta_key IN ('_messages', '_mail_2')");
$ref_meta = [];
while ($row = $result->fetch_assoc()) {
    $ref_meta[$row['meta_key']] = $row['meta_value'];
}

// _form meta
$stmt = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_form', ?)");
$stmt->bind_param('is', $form_id, $form_content);
$stmt->execute();
echo "Added _form meta\n";

// _mail meta
$mail = serialize([
    'active' => true,
    'subject' => 'New Sample Request from [company-name]',
    'sender' => 'Hamilton Devices <mail@hamiltondevices.com>',
    'recipient' => 'support@hamiltondevices.com',
    'body' => '<h2>New Sample Request</h2>
<table>
<tr><td><strong>Company:</strong></td><td>[company-name]</td></tr>
<tr><td><strong>Contact:</strong></td><td>[contact-name]</td></tr>
<tr><td><strong>Email:</strong></td><td>[your-email]</td></tr>
<tr><td><strong>Phone:</strong></td><td>[phone-number]</td></tr>
<tr><td><strong>Website:</strong></td><td>[company-website]</td></tr>
<tr><td><strong>Products:</strong></td><td>[product-interest]</td></tr>
<tr><td><strong>Volume:</strong></td><td>[volume]</td></tr>
<tr><td><strong>Custom Branding:</strong></td><td>[custom-branding]</td></tr>
<tr><td><strong>Details:</strong></td><td>[business-details]</td></tr>
</table>',
    'additional_headers' => 'Reply-To: [your-email]',
    'attachments' => '',
    'use_html' => true,
    'exclude_blank' => false,
]);
$stmt = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_mail', ?)");
$stmt->bind_param('is', $form_id, $mail);
$stmt->execute();
echo "Added _mail meta\n";

// _mail_2 (inactive secondary mail)
$stmt = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_mail_2', ?)");
$m2 = $ref_meta['_mail_2'];
$stmt->bind_param('is', $form_id, $m2);
$stmt->execute();

// _messages
$stmt = $conn->prepare("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (?, '_messages', ?)");
$msgs = $ref_meta['_messages'];
$stmt->bind_param('is', $form_id, $msgs);
$stmt->execute();

// _locale
$conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($form_id, '_locale', 'en_US')");

// _hash
$hash = sha1(uniqid(mt_rand(), true));
$conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($form_id, '_hash', '$hash')");

// _additional_settings
$conn->query("INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES ($form_id, '_additional_settings', '')");

echo "Added all CF7 meta\n";

// ============================================================
// 2. Move Wholesale Login out of nav, into header right side
// ============================================================

// Remove Wholesale Login from the menu (set to draft)
$conn->query("UPDATE wp_posts SET post_status='draft' WHERE ID=142297");
echo "Removed Wholesale Login from nav menu\n";

// Get current theme mods
$result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='theme_mods_flatsome-child'");
$row = $result->fetch_assoc();
$mods = unserialize($row['option_value']);

// Check what's in the current header HTML elements
echo "Current header_elements_right: " . print_r($mods['header_elements_right'], true) . "\n";

// Find what html text blocks exist
foreach ($mods as $key => $val) {
    if (strpos($key, 'html_text') !== false || strpos($key, 'header_html') !== false) {
        echo "Found: $key = " . substr(print_r($val, true), 0, 100) . "\n";
    }
}

// In Flatsome, header HTML blocks use keys like "html_text" (for html), "html_text_2" (for html-2), etc.
// The header_elements_right has html-2, so that uses html_text_2
// Let's set html_text_2 to be the wholesale login button

$wholesale_page_url = 'http://localhost:8080/wholesale-log-in-page/';

// Get the wholesale login page URL
$result2 = $conn->query("SELECT post_name FROM wp_posts WHERE ID=136812");
if ($row2 = $result2->fetch_assoc()) {
    $wholesale_page_url = 'http://localhost:8080/' . $row2['post_name'] . '/';
}
echo "Wholesale login URL: $wholesale_page_url\n";

// Set html-2 content to a styled wholesale login button
$mods['html_text_2'] = '<a href="' . $wholesale_page_url . '" style="background:#c72035;color:#fff;padding:8px 18px;border-radius:4px;font-weight:600;font-size:13px;text-decoration:none;white-space:nowrap;display:inline-block;">Wholesale Login</a>';

// Make sure header_elements_right includes html-2 before account and cart
$mods['header_elements_right'] = ['html-2', 'account', 'cart'];

$new_mods = serialize($mods);
$stmt = $conn->prepare("UPDATE wp_options SET option_value=? WHERE option_name='theme_mods_flatsome-child'");
$stmt->bind_param('s', $new_mods);
$stmt->execute();
echo "Updated header with Wholesale Login button on right side\n";

echo "\nAll fixes applied!\n";
$conn->close();
