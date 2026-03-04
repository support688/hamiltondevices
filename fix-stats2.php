<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

$result = $conn->query("SELECT post_content FROM wp_posts WHERE ID=96");
$row = $result->fetch_assoc();
$content = $row['post_content'];

// Replace "1 of 4" card with "Same Day"
$old = '<span style="font-size:42px;font-weight:800;color:#fff;display:block;line-height:1;">1 of 4</span>
<div style="width:30px;height:2px;background:#c72035;margin:12px auto;"></div>
<p style="font-size:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin:0;">Authorized U.S.<br>Distributors</p>';

$new = '<span style="font-size:42px;font-weight:800;color:#fff;display:block;line-height:1;">Same Day</span>
<div style="width:30px;height:2px;background:#c72035;margin:12px auto;"></div>
<p style="font-size:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin:0;">Order Processing<br>&amp; Fulfillment</p>';

$content = str_replace($old, $new, $content);

// Replace "U.S. Based Support" with "Global Shipping"
$old2 = '<span style="font-size:42px;font-weight:800;color:#fff;display:block;line-height:1;">U.S.</span>
<div style="width:30px;height:2px;background:#c72035;margin:12px auto;"></div>
<p style="font-size:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin:0;">Based Support<br>&amp; Fulfillment</p>';

$new2 = '<span style="font-size:42px;font-weight:800;color:#fff;display:block;line-height:1;">Global</span>
<div style="width:30px;height:2px;background:#c72035;margin:12px auto;"></div>
<p style="font-size:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin:0;">Shipping &amp;<br>Logistics</p>';

$content = str_replace($old2, $new2, $content);

$stmt = $conn->prepare("UPDATE wp_posts SET post_content=?, post_modified=NOW(), post_modified_gmt=NOW() WHERE ID=96");
$stmt->bind_param('s', $content);
$stmt->execute();

echo "Updated! Affected rows: " . $stmt->affected_rows . "\n";
$conn->close();
