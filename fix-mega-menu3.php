<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// Get all sub-items under each CCELL category to decide what to keep
$categories = [
    '35611' => 'CCELL Batteries',
    '35604' => 'CCELL Cartridges',
    '64376' => 'CCELL Pod Systems',
    '35606' => 'CCELL Disposables',
    '35610' => 'Hamilton Devices',
    '159064' => 'Auxo Vape',
    '159188' => 'Vape Accessories',
];

foreach ($categories as $parent_id => $label) {
    $result = $conn->query("
        SELECT p.ID, p.post_title, p.menu_order,
               MAX(CASE WHEN pm.meta_key='_menu_item_object' THEN pm.meta_value END) as obj_type,
               MAX(CASE WHEN pm.meta_key='_menu_item_object_id' THEN pm.meta_value END) as obj_id
        FROM wp_posts p
        JOIN wp_term_relationships tr ON p.ID = tr.object_id AND tr.term_taxonomy_id = 67
        JOIN wp_postmeta pm ON p.ID = pm.post_id
        WHERE p.post_type = 'nav_menu_item'
        AND p.post_status = 'publish'
        GROUP BY p.ID
        HAVING MAX(CASE WHEN pm.meta_key='_menu_item_menu_item_parent' THEN pm.meta_value END) = '$parent_id'
        ORDER BY p.menu_order
    ");

    echo "\n=== $label (parent: $parent_id) ===\n";
    while ($row = $result->fetch_assoc()) {
        $name = $row['post_title'];
        if (empty($name) && $row['obj_type'] == 'product_cat') {
            $r = $conn->query("SELECT name FROM wp_terms WHERE term_id={$row['obj_id']}");
            $c = $r->fetch_assoc();
            $name = $c['name'];
        } elseif (empty($name)) {
            $r = $conn->query("SELECT post_title FROM wp_posts WHERE ID={$row['obj_id']}");
            $c = $r->fetch_assoc();
            $name = $c['post_title'];
        }
        echo "  ID:{$row['ID']} type:{$row['obj_type']} obj:{$row['obj_id']} \"{$name}\"\n";
    }
}

$conn->close();
