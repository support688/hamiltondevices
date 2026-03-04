<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// Build the 2-row grid layout for Products mega menu
$grid_config = array(
    'type' => 'grid',
    'grid' => array(
        // Row 1: CCELL Products (prominent) - 4 equal columns
        array(
            'meta' => array(
                'class' => 'mega-ccell-row',
                'hide-on-desktop' => 'false',
                'hide-on-mobile' => 'false',
                'columns' => '12'
            ),
            'columns' => array(
                array(
                    'meta' => array('span' => '3', 'class' => 'submega', 'hide-on-desktop' => 'false', 'hide-on-mobile' => 'false'),
                    'items' => array(
                        array('id' => '175343', 'type' => 'item')  // CCELL Batteries (cat 542)
                    )
                ),
                array(
                    'meta' => array('span' => '3', 'class' => 'submega', 'hide-on-desktop' => 'false', 'hide-on-mobile' => 'false'),
                    'items' => array(
                        array('id' => '176267', 'type' => 'item')  // CCELL Cartridges (cat 543)
                    )
                ),
                array(
                    'meta' => array('span' => '3', 'class' => 'submega', 'hide-on-desktop' => 'false', 'hide-on-mobile' => 'false'),
                    'items' => array(
                        array('id' => '176278', 'type' => 'item')  // CCELL Pod Systems (cat 1050)
                    )
                ),
                array(
                    'meta' => array('span' => '3', 'class' => 'submega', 'hide-on-desktop' => 'false', 'hide-on-mobile' => 'false'),
                    'items' => array(
                        array('id' => '176286', 'type' => 'item')  // CCELL Disposables (cat 550)
                    )
                ),
            )
        ),
        // Row 2: Other Products (smaller/secondary) - 3 columns
        array(
            'meta' => array(
                'class' => 'mega-other-row',
                'hide-on-desktop' => 'false',
                'hide-on-mobile' => 'false',
                'columns' => '12'
            ),
            'columns' => array(
                array(
                    'meta' => array('span' => '4', 'class' => 'submega', 'hide-on-desktop' => 'false', 'hide-on-mobile' => 'false'),
                    'items' => array(
                        array('id' => '175348', 'type' => 'item')  // Vape Batteries & Devices / Hamilton brands (cat 554)
                    )
                ),
                array(
                    'meta' => array('span' => '4', 'class' => 'submega', 'hide-on-desktop' => 'false', 'hide-on-mobile' => 'false'),
                    'items' => array(
                        array('id' => '176300', 'type' => 'item')  // Auxo Vape (cat 1265)
                    )
                ),
                array(
                    'meta' => array('span' => '4', 'class' => 'submega', 'hide-on-desktop' => 'false', 'hide-on-mobile' => 'false'),
                    'items' => array(
                        array('id' => '176304', 'type' => 'item')  // Vape Accessories (cat 545)
                    )
                ),
            )
        ),
    )
);

$serialized = serialize($grid_config);

// Update the desktop Products menu item (175342)
$stmt = $conn->prepare("UPDATE wp_postmeta SET meta_value=? WHERE post_id=175342 AND meta_key='_megamenu'");
$stmt->bind_param('s', $serialized);
$stmt->execute();
echo "Updated mega menu grid config. Affected rows: " . $stmt->affected_rows . "\n";

// Now add custom CSS to differentiate the two tiers
// We'll add it to the theme's custom CSS in the child theme functions.php or via wp_options
$result = $conn->query("SELECT option_value FROM wp_options WHERE option_name='theme_mods_flatsome-child'");
$row = $result->fetch_assoc();
$mods = unserialize($row['option_value']);

$mega_css = '

/* === MEGA MENU TWO-TIER STYLING === */
#mega-menu-wrap-primary #mega-menu-primary > li.mega-menu-item > ul.mega-sub-menu {
    padding: 0 !important;
}
/* Row 1: CCELL Products - prominent */
.mega-ccell-row {
    padding: 25px 20px 15px !important;
    border-bottom: 1px solid #e8e8e8;
}
.mega-ccell-row > .mega-menu-column > ul.mega-sub-menu > li > a.mega-menu-link {
    font-size: 15px !important;
    font-weight: 700 !important;
    color: #c72035 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.5px !important;
    padding-bottom: 8px !important;
    border-bottom: 2px solid #c72035 !important;
    margin-bottom: 8px !important;
    display: inline-block !important;
}
.mega-ccell-row > .mega-menu-column > ul.mega-sub-menu > li > ul.mega-sub-menu > li > a.mega-menu-link {
    font-size: 13.5px !important;
    color: #333 !important;
    padding: 4px 0 !important;
    font-weight: 400 !important;
}
.mega-ccell-row > .mega-menu-column > ul.mega-sub-menu > li > ul.mega-sub-menu > li > a.mega-menu-link:hover {
    color: #c72035 !important;
}

/* Row 2: Other Products - secondary */
.mega-other-row {
    padding: 15px 20px 20px !important;
    background: #fafafa !important;
}
.mega-other-row > .mega-menu-column > ul.mega-sub-menu > li > a.mega-menu-link {
    font-size: 13px !important;
    font-weight: 700 !important;
    color: #666 !important;
    text-transform: uppercase !important;
    letter-spacing: 0.3px !important;
    padding-bottom: 6px !important;
    margin-bottom: 4px !important;
    display: inline-block !important;
}
.mega-other-row > .mega-menu-column > ul.mega-sub-menu > li > ul.mega-sub-menu > li > a.mega-menu-link {
    font-size: 12px !important;
    color: #888 !important;
    padding: 3px 0 !important;
    font-weight: 400 !important;
}
.mega-other-row > .mega-menu-column > ul.mega-sub-menu > li > ul.mega-sub-menu > li > a.mega-menu-link:hover {
    color: #c72035 !important;
}
';

// Append to existing custom CSS
$existing_css = $mods['html_custom_css'] ?? '';
// Remove any previous mega menu CSS if we've added it before
$existing_css = preg_replace('/\/\* === MEGA MENU TWO-TIER STYLING === \*\/.*$/s', '', $existing_css);
$mods['html_custom_css'] = trim($existing_css) . "\n" . $mega_css;

$serialized_mods = serialize($mods);
$stmt2 = $conn->prepare("UPDATE wp_options SET option_value=? WHERE option_name='theme_mods_flatsome-child'");
$stmt2->bind_param('s', $serialized_mods);
$stmt2->execute();
echo "Updated custom CSS. Affected rows: " . $stmt2->affected_rows . "\n";

$conn->close();
