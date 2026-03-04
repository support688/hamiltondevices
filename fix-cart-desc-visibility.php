<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// The theme hides .term-description globally. Override for cartridge page.
$result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='theme_mods_flatsome-child'");
$row = $result->fetch_assoc();
$mods = unserialize($row['option_value']);

$cart_css = '
/* Show category description on cartridge page */
.tax-product_cat.term-cartridge .term-description {
    display: block !important;
    max-width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
}
/* Hide the default sidebar on cartridge page - let the selector take prominence */
.tax-product_cat.term-cartridge .shop-page-title.featured-title {
    margin-bottom: 0 !important;
}
';

if (strpos($mods['html_custom_css'], 'cartridge page') === false) {
    $mods['html_custom_css'] .= $cart_css;
    $serialized = serialize($mods);
    $stmt = $conn->prepare("UPDATE wp_options SET option_value=? WHERE option_name='theme_mods_flatsome-child'");
    $stmt->bind_param('s', $serialized);
    $stmt->execute();
    echo "Added cartridge CSS override. Affected: " . $stmt->affected_rows . "\n";
} else {
    echo "CSS already exists\n";
}

$conn->close();
