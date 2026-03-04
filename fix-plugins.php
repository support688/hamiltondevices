<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$row = $conn->query("SELECT option_value FROM wp_options WHERE option_name='active_plugins'")->fetch_assoc();
$plugins = @unserialize($row['option_value']);
if (!is_array($plugins)) {
    echo "Corrupted array, rebuilding from known good list...\n";
    $plugins = [
        'acf-content-analysis-for-yoast-seo/yoast-acf-analysis.php',
        'add-search-to-menu/add-search-to-menu.php',
        'advanced-coupons-for-woocommerce-free/advanced-coupons-for-woocommerce-free.php',
        'advanced-coupons-for-woocommerce/advanced-coupons-for-woocommerce.php',
        'advanced-custom-fields/acf.php',
        'classic-editor/classic-editor.php',
        'classic-widgets/classic-widgets.php',
        'contact-form-7/wp-contact-form-7.php',
        'customer-reviews-woocommerce/ivole.php',
        'megamenu/megamenu.php',
        'user-registration/user-registration.php',
        'user-role-editor/user-role-editor.php',
        'woo-variation-swatches/woo-variation-swatches.php',
        'woocommerce/woocommerce.php',
        'woocommerce-wholesale-lead-capture/woocommerce-wholesale-lead-capture.bootstrap.php',
        'woocommerce-wholesale-order-form/woocommerce-wholesale-order-form.bootstrap.php',
        'woocommerce-wholesale-prices/woocommerce-wholesale-prices.bootstrap.php',
        'woocommerce-wholesale-prices-premium/woocommerce-wholesale-prices-premium.bootstrap.php',
        'wordpress-seo/wp-seo.php',
        'wordpress-seo-premium/wp-seo-premium.php',
    ];
}
$remove = [
    'really-simple-ssl/rlrsssl-really-simple-ssl.php',
    'wps-hide-login/wps-hide-login.php',
    'wordfence/wordfence.php',
    'simple-cloudflare-turnstile/simple-cloudflare-turnstile.php',
];
$plugins = array_values(array_filter($plugins, fn($p) => !in_array($p, $remove)));
$serialized = serialize($plugins);
$stmt = $conn->prepare("UPDATE wp_options SET option_value=? WHERE option_name='active_plugins'");
$stmt->bind_param('s', $serialized);
$stmt->execute();
echo "Fixed! " . count($plugins) . " plugins active.\n";
$conn->close();
