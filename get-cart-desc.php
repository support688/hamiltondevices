<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');
$result = $conn->query("SELECT description FROM wp_term_taxonomy WHERE term_id = 543");
$row = $result->fetch_assoc();
echo "CARTRIDGE CATEGORY DESCRIPTION:\n" . $row['description'] . "\n";
$conn->close();
