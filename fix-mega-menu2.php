<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// Build the 2-row grid layout for Products mega menu (item 74568 in menu 67)
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
                        array('id' => '35611', 'type' => 'item')  // CCELL Batteries (cat 542)
                    )
                ),
                array(
                    'meta' => array('span' => '3', 'class' => 'submega', 'hide-on-desktop' => 'false', 'hide-on-mobile' => 'false'),
                    'items' => array(
                        array('id' => '35604', 'type' => 'item')  // CCELL Cartridges (cat 543)
                    )
                ),
                array(
                    'meta' => array('span' => '3', 'class' => 'submega', 'hide-on-desktop' => 'false', 'hide-on-mobile' => 'false'),
                    'items' => array(
                        array('id' => '64376', 'type' => 'item')  // CCELL Pod Systems (cat 1050)
                    )
                ),
                array(
                    'meta' => array('span' => '3', 'class' => 'submega', 'hide-on-desktop' => 'false', 'hide-on-mobile' => 'false'),
                    'items' => array(
                        array('id' => '35606', 'type' => 'item')  // CCELL Disposables (cat 550)
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
                        array('id' => '35610', 'type' => 'item')  // Hamilton brands / 510 Thread (cat 554)
                    )
                ),
                array(
                    'meta' => array('span' => '4', 'class' => 'submega', 'hide-on-desktop' => 'false', 'hide-on-mobile' => 'false'),
                    'items' => array(
                        array('id' => '159064', 'type' => 'item')  // Auxo Vape (cat 1265)
                    )
                ),
                array(
                    'meta' => array('span' => '4', 'class' => 'submega', 'hide-on-desktop' => 'false', 'hide-on-mobile' => 'false'),
                    'items' => array(
                        array('id' => '159188', 'type' => 'item')  // Vape Accessories (cat 545)
                    )
                ),
            )
        ),
    )
);

$serialized = serialize($grid_config);

// Update the Products menu item (74568) in menu 67
$stmt = $conn->prepare("UPDATE wp_postmeta SET meta_value=? WHERE post_id=74568 AND meta_key='_megamenu'");
$stmt->bind_param('s', $serialized);
$stmt->execute();
echo "Updated mega menu grid for item 74568. Affected rows: " . $stmt->affected_rows . "\n";

// Also update the category header titles for cleaner display
// CCELL Batteries header
$conn->query("UPDATE wp_posts SET post_title='CCELL® Batteries' WHERE ID=35611");
echo "35611 title update: " . $conn->affected_rows . "\n";

// Hamilton Devices / 510 Thread section
$conn->query("UPDATE wp_posts SET post_title='Hamilton Devices' WHERE ID=35610");
echo "35610 title update: " . $conn->affected_rows . "\n";

// Pod Systems - capitalize properly
$conn->query("UPDATE wp_posts SET post_title='CCELL® Pod Systems' WHERE ID=64376");
echo "64376 title update: " . $conn->affected_rows . "\n";

// Clear any mega menu transient cache
$conn->query("DELETE FROM wp_options WHERE option_name LIKE '%megamenu%transient%'");
$conn->query("DELETE FROM wp_options WHERE option_name LIKE '%transient%megamenu%'");

echo "Done!\n";
$conn->close();
