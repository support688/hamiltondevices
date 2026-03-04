<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

$result = $conn->query("SELECT post_content FROM wp_posts WHERE ID=96");
$row = $result->fetch_assoc();
$content = $row['post_content'];

// Fix 1: Replace the oversized CCELL logo with a constrained inline image
$old_logo = '[ux_image id="240284" width="160" margin="0px 0px 10px 0px"]
[ux_text font_size="1.5" text_align="center"]
<h2>Product Lines</h2>
[/ux_text]';

$new_logo = '[ux_text font_size="1.5" text_align="center"]
<img src="/wp-content/uploads/2025/12/ccellnewlogo.png" alt="CCELL" style="max-width:160px;height:auto;margin-bottom:10px;">
<h2>Product Lines</h2>
[/ux_text]';

$content = str_replace($old_logo, $new_logo, $content);

// Fix 2: Replace the disposables card - constrain all images to same height
$old_cards = '[row h_align="center"]
[col span="3" span__sm="6" padding="10px"]
<div style="background:#f8f8f8;border-radius:12px;overflow:hidden;text-align:center;height:100%;">
<div style="padding:30px 20px 15px;background:#fff;">
[ux_image id="221053" width="55"]
</div>
<div style="padding:20px 20px 25px;">
<h3 style="font-size:18px;margin:0 0 8px;">510 Cartridges</h3>
<p style="color:#666;font-size:14px;line-height:1.5;margin:0 0 15px;">Industry standard 510 thread cartridges in multiple sizes and configurations.</p>
[button text="View Products" style="outline" size="small" link="http://localhost:8080/product-category/cartridge/"]
</div>
</div>
[/col]
[col span="3" span__sm="6" padding="10px"]
<div style="background:#f8f8f8;border-radius:12px;overflow:hidden;text-align:center;height:100%;">
<div style="padding:30px 20px 15px;background:#fff;">
[ux_image id="219324" width="45"]
</div>
<div style="padding:20px 20px 25px;">
<h3 style="font-size:18px;margin:0 0 8px;">All-In-One Disposables</h3>
<p style="color:#666;font-size:14px;line-height:1.5;margin:0 0 15px;">Complete disposable units ready for your oil and your branding.</p>
[button text="View Products" style="outline" size="small" link="http://localhost:8080/product-category/vaporizers/"]
</div>
</div>
[/col]
[col span="3" span__sm="6" padding="10px"]
<div style="background:#f8f8f8;border-radius:12px;overflow:hidden;text-align:center;height:100%;">
<div style="padding:30px 20px 15px;background:#fff;">
[ux_image id="224082" width="45"]
</div>
<div style="padding:20px 20px 25px;">
<h3 style="font-size:18px;margin:0 0 8px;">Pod Systems</h3>
<p style="color:#666;font-size:14px;line-height:1.5;margin:0 0 15px;">Closed-loop pod systems for a proprietary branded experience.</p>
[button text="View Products" style="outline" size="small" link="http://localhost:8080/product-category/pod-systems/"]
</div>
</div>
[/col]
[col span="3" span__sm="6" padding="10px"]
<div style="background:#f8f8f8;border-radius:12px;overflow:hidden;text-align:center;height:100%;">
<div style="padding:30px 20px 15px;background:#fff;">
[ux_image id="225019" width="45"]
</div>
<div style="padding:20px 20px 25px;">
<h3 style="font-size:18px;margin:0 0 8px;">Batteries &amp; Power Supplies</h3>
<p style="color:#666;font-size:14px;line-height:1.5;margin:0 0 15px;">CCELL® batteries for retail and branded programs.</p>
[button text="View Products" style="outline" size="small" link="http://localhost:8080/product-category/batteries/"]
</div>
</div>
[/col]
[/row]';

$new_cards = '[row h_align="center"]
[col span="3" span__sm="6" padding="10px"]
<div style="background:#f8f8f8;border-radius:12px;overflow:hidden;text-align:center;">
<div style="padding:20px;background:#fff;height:200px;display:flex;align-items:center;justify-content:center;">
<img src="/wp-content/uploads/2024/09/hamilton_devices_ccell_th210-y_cartridge_-_black.png" alt="CCELL 510 Cartridges" style="max-height:170px;max-width:80%;object-fit:contain;">
</div>
<div style="padding:20px 20px 25px;">
<h3 style="font-size:18px;margin:0 0 8px;">510 Cartridges</h3>
<p style="color:#666;font-size:14px;line-height:1.5;margin:0 0 15px;">Industry standard 510 thread cartridges in multiple sizes and configurations.</p>
<a href="/product-category/cartridge/" style="border:1px solid #c72035;color:#c72035;padding:8px 20px;border-radius:4px;font-size:13px;font-weight:600;text-decoration:none;display:inline-block;">View Products</a>
</div>
</div>
[/col]
[col span="3" span__sm="6" padding="10px"]
<div style="background:#f8f8f8;border-radius:12px;overflow:hidden;text-align:center;">
<div style="padding:20px;background:#fff;height:200px;display:flex;align-items:center;justify-content:center;">
<img src="/wp-content/uploads/2024/08/listo.png" alt="CCELL All-In-One Disposables" style="max-height:170px;max-width:80%;object-fit:contain;">
</div>
<div style="padding:20px 20px 25px;">
<h3 style="font-size:18px;margin:0 0 8px;">All-In-One Disposables</h3>
<p style="color:#666;font-size:14px;line-height:1.5;margin:0 0 15px;">Complete disposable units ready for your oil and your branding.</p>
<a href="/product-category/vaporizers/" style="border:1px solid #c72035;color:#c72035;padding:8px 20px;border-radius:4px;font-size:13px;font-weight:600;text-decoration:none;display:inline-block;">View Products</a>
</div>
</div>
[/col]
[col span="3" span__sm="6" padding="10px"]
<div style="background:#f8f8f8;border-radius:12px;overflow:hidden;text-align:center;">
<div style="padding:20px;background:#fff;height:200px;display:flex;align-items:center;justify-content:center;">
<img src="/wp-content/uploads/2024/11/g2.png" alt="CCELL Pod Systems" style="max-height:170px;max-width:80%;object-fit:contain;">
</div>
<div style="padding:20px 20px 25px;">
<h3 style="font-size:18px;margin:0 0 8px;">Pod Systems</h3>
<p style="color:#666;font-size:14px;line-height:1.5;margin:0 0 15px;">Closed-loop pod systems for a proprietary branded experience.</p>
<a href="/product-category/pod-systems/" style="border:1px solid #c72035;color:#c72035;padding:8px 20px;border-radius:4px;font-size:13px;font-weight:600;text-decoration:none;display:inline-block;">View Products</a>
</div>
</div>
[/col]
[col span="3" span__sm="6" padding="10px"]
<div style="background:#f8f8f8;border-radius:12px;overflow:hidden;text-align:center;">
<div style="padding:20px;background:#fff;height:200px;display:flex;align-items:center;justify-content:center;">
<img src="/wp-content/uploads/2024/12/Group-109.png" alt="CCELL Batteries" style="max-height:170px;max-width:80%;object-fit:contain;">
</div>
<div style="padding:20px 20px 25px;">
<h3 style="font-size:18px;margin:0 0 8px;">Batteries &amp; Power Supplies</h3>
<p style="color:#666;font-size:14px;line-height:1.5;margin:0 0 15px;">CCELL® batteries for retail and branded programs.</p>
<a href="/product-category/batteries/" style="border:1px solid #c72035;color:#c72035;padding:8px 20px;border-radius:4px;font-size:13px;font-weight:600;text-decoration:none;display:inline-block;">View Products</a>
</div>
</div>
[/col]
[/row]';

$content = str_replace($old_cards, $new_cards, $content);

$stmt = $conn->prepare("UPDATE wp_posts SET post_content=?, post_modified=NOW(), post_modified_gmt=NOW() WHERE ID=96");
$stmt->bind_param('s', $content);
$stmt->execute();

echo "Updated! Affected rows: " . $stmt->affected_rows . "\n";
$conn->close();
