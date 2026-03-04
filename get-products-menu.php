<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// Get all menu items from the desktop menu (term_id 1278) under Products
$result = $conn->query("
    SELECT p.ID, p.post_title, p.menu_order,
           MAX(CASE WHEN pm.meta_key='_menu_item_menu_item_parent' THEN pm.meta_value END) as parent_id,
           MAX(CASE WHEN pm.meta_key='_menu_item_object' THEN pm.meta_value END) as object_type,
           MAX(CASE WHEN pm.meta_key='_menu_item_object_id' THEN pm.meta_value END) as object_id,
           MAX(CASE WHEN pm.meta_key='_menu_item_url' THEN pm.meta_value END) as url,
           MAX(CASE WHEN pm.meta_key='_menu_item_classes' THEN pm.meta_value END) as classes,
           MAX(CASE WHEN pm.meta_key='_megamenu' THEN pm.meta_value END) as megamenu
    FROM wp_posts p
    JOIN wp_term_relationships tr ON p.ID = tr.object_id AND tr.term_taxonomy_id = 1278
    JOIN wp_postmeta pm ON p.ID = pm.post_id
    WHERE p.post_type = 'nav_menu_item'
    AND p.post_status = 'publish'
    GROUP BY p.ID
    ORDER BY p.menu_order
");

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

// Print the Products item and its direct children (top-level categories)
echo "=== PRODUCTS MENU ITEM ===\n";
foreach ($items as $item) {
    if ($item['ID'] == 175342) {
        echo "ID:{$item['ID']} title:\"{$item['post_title']}\" megamenu:{$item['megamenu']}\n\n";
    }
}

echo "=== TOP-LEVEL CATEGORIES (direct children of Products) ===\n";
foreach ($items as $item) {
    if ($item['parent_id'] == '175342') {
        echo "ID:{$item['ID']} order:{$item['menu_order']} title:\"{$item['post_title']}\" obj_type:{$item['object_type']} obj_id:{$item['object_id']} classes:{$item['classes']}\n";

        // Print sub-items (grandchildren)
        $count = 0;
        foreach ($items as $sub) {
            if ($sub['parent_id'] == $item['ID']) {
                $count++;
            }
        }
        echo "  -> $count sub-items\n";
    }
}

echo "\n=== ALL ITEMS UNDER 'Vape Batteries & Devices' (175348) ===\n";
foreach ($items as $item) {
    if ($item['parent_id'] == '175348') {
        echo "  ID:{$item['ID']} order:{$item['menu_order']} title:\"{$item['post_title']}\" obj_id:{$item['object_id']}\n";
    }
}

echo "\n=== CURRENT MEGAMENU CONFIG FOR PRODUCTS ===\n";
$result2 = $conn->query("SELECT meta_value FROM wp_postmeta WHERE post_id=175342 AND meta_key='_megamenu'");
$row2 = $result2->fetch_assoc();
echo $row2['meta_value'] . "\n";

$conn->close();
