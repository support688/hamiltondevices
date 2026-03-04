<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// Get Products item children from menu 67
$result = $conn->query("
    SELECT p.ID, p.post_title, p.menu_order,
           MAX(CASE WHEN pm.meta_key='_menu_item_menu_item_parent' THEN pm.meta_value END) as parent_id,
           MAX(CASE WHEN pm.meta_key='_menu_item_object' THEN pm.meta_value END) as object_type,
           MAX(CASE WHEN pm.meta_key='_menu_item_object_id' THEN pm.meta_value END) as object_id
    FROM wp_posts p
    JOIN wp_term_relationships tr ON p.ID = tr.object_id AND tr.term_taxonomy_id = 67
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

echo "=== PRODUCTS ITEM (74568) DIRECT CHILDREN ===\n";
foreach ($items as $item) {
    if ($item['parent_id'] == '74568') {
        $cat_name = '';
        if ($item['object_type'] == 'product_cat') {
            $r = $conn->query("SELECT name FROM wp_terms WHERE term_id={$item['object_id']}");
            $c = $r->fetch_assoc();
            $cat_name = $c['name'];
        }
        $count = 0;
        foreach ($items as $sub) {
            if ($sub['parent_id'] == $item['ID']) $count++;
        }
        echo "ID:{$item['ID']} order:{$item['menu_order']} title:\"{$item['post_title']}\" cat_name:\"{$cat_name}\" obj_id:{$item['object_id']} -> {$count} sub-items\n";
    }
}

$conn->close();
