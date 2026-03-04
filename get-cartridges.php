<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

// Get the cartridge category and sub-categories
$result = $conn->query("
    SELECT t.term_id, t.name, t.slug, tt.parent, tt.count
    FROM wp_terms t
    JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id
    WHERE tt.taxonomy = 'product_cat'
    AND (t.term_id = 543 OR tt.parent = 543
         OR tt.parent IN (SELECT t2.term_id FROM wp_terms t2 JOIN wp_term_taxonomy tt2 ON t2.term_id = tt2.term_id WHERE tt2.parent = 543 AND tt2.taxonomy = 'product_cat'))
    ORDER BY tt.parent, t.name
");

echo "=== CARTRIDGE CATEGORIES ===\n";
while ($row = $result->fetch_assoc()) {
    $indent = $row['parent'] == 543 ? '  ' : ($row['parent'] != 0 ? '    ' : '');
    echo "{$indent}ID:{$row['term_id']} parent:{$row['parent']} name:\"{$row['name']}\" slug:{$row['slug']} count:{$row['count']}\n";
}

// Get all published products in the cartridge category tree
echo "\n=== PRODUCTS IN CARTRIDGE CATEGORIES ===\n";
$result2 = $conn->query("
    SELECT p.ID, p.post_title, p.post_status,
           GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') as categories
    FROM wp_posts p
    JOIN wp_term_relationships tr ON p.ID = tr.object_id
    JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
    JOIN wp_terms t ON tt.term_id = t.term_id
    WHERE p.post_type = 'product'
    AND p.post_status = 'publish'
    AND tt.taxonomy = 'product_cat'
    AND t.term_id IN (
        SELECT t2.term_id FROM wp_terms t2
        JOIN wp_term_taxonomy tt2 ON t2.term_id = tt2.term_id
        WHERE tt2.taxonomy = 'product_cat'
        AND (t2.term_id = 543 OR tt2.parent = 543
             OR tt2.parent IN (SELECT t3.term_id FROM wp_terms t3 JOIN wp_term_taxonomy tt3 ON t3.term_id = tt3.term_id WHERE tt3.parent = 543 AND tt3.taxonomy = 'product_cat'))
    )
    GROUP BY p.ID
    ORDER BY p.post_title
");

while ($row = $result2->fetch_assoc()) {
    echo "ID:{$row['ID']} [{$row['post_status']}] \"{$row['post_title']}\" cats:[{$row['categories']}]\n";
}

$conn->close();
