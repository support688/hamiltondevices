<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');
$conn->query("UPDATE wp_term_taxonomy SET description='' WHERE term_id=543 AND taxonomy='product_cat'");
echo "Cleared cartridge description. Affected: " . $conn->affected_rows . "\n";
$conn->close();
