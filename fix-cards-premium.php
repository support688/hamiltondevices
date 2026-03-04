<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

$result = $conn->query("SELECT post_content FROM wp_posts WHERE ID=96");
$row = $result->fetch_assoc();
$content = $row['post_content'];

// Replace the product cards with premium styled versions
$old_cards = '[row h_align="center"]
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

$new_cards = '<style>
.product-line-card {
  background:#fff;
  border-radius:12px;
  overflow:hidden;
  text-align:center;
  box-shadow:0 2px 20px rgba(0,0,0,0.06);
  transition:all 0.3s ease;
  border:1px solid rgba(0,0,0,0.04);
}
.product-line-card:hover {
  box-shadow:0 8px 40px rgba(0,0,0,0.12);
  transform:translateY(-4px);
}
.product-line-card .card-img {
  padding:35px 25px;
  background:linear-gradient(180deg, #fafafa 0%, #fff 100%);
  height:220px;
  display:flex;
  align-items:center;
  justify-content:center;
}
.product-line-card .card-img img {
  max-height:180px;
  max-width:75%;
  object-fit:contain;
}
.product-line-card .card-body {
  padding:22px 22px 28px;
  border-top:1px solid #f0f0f0;
}
.product-line-card .card-body h3 {
  font-size:17px;
  font-weight:700;
  margin:0 0 8px;
  color:#1a1a1a;
}
.product-line-card .card-body p {
  color:#888;
  font-size:13.5px;
  line-height:1.6;
  margin:0 0 18px;
}
.product-line-card .card-btn {
  color:#c72035;
  font-size:13px;
  font-weight:600;
  text-decoration:none;
  display:inline-flex;
  align-items:center;
  gap:6px;
  letter-spacing:0.3px;
  text-transform:uppercase;
}
.product-line-card .card-btn:hover {
  color:#a01a2c;
}
.product-line-card .card-btn:after {
  content:"\2192";
  font-size:16px;
  transition:transform 0.2s;
}
.product-line-card .card-btn:hover:after {
  transform:translateX(3px);
}
</style>
[row h_align="center"]
[col span="3" span__sm="6" padding="12px"]
<div class="product-line-card">
<div class="card-img">
<img src="/wp-content/uploads/2024/09/hamilton_devices_ccell_th210-y_cartridge_-_black.png" alt="CCELL 510 Cartridges">
</div>
<div class="card-body">
<h3>510 Cartridges</h3>
<p>Industry standard 510 thread cartridges in multiple sizes and configurations.</p>
<a href="/product-category/cartridge/" class="card-btn">View Products</a>
</div>
</div>
[/col]
[col span="3" span__sm="6" padding="12px"]
<div class="product-line-card">
<div class="card-img">
<img src="/wp-content/uploads/2024/08/listo.png" alt="CCELL All-In-One Disposables">
</div>
<div class="card-body">
<h3>All-In-One Disposables</h3>
<p>Complete disposable units ready for your oil and your branding.</p>
<a href="/product-category/vaporizers/" class="card-btn">View Products</a>
</div>
</div>
[/col]
[col span="3" span__sm="6" padding="12px"]
<div class="product-line-card">
<div class="card-img">
<img src="/wp-content/uploads/2024/11/g2.png" alt="CCELL Pod Systems">
</div>
<div class="card-body">
<h3>Pod Systems</h3>
<p>Closed-loop pod systems for a proprietary branded experience.</p>
<a href="/product-category/pod-systems/" class="card-btn">View Products</a>
</div>
</div>
[/col]
[col span="3" span__sm="6" padding="12px"]
<div class="product-line-card">
<div class="card-img">
<img src="/wp-content/uploads/2024/12/Group-109.png" alt="CCELL Batteries">
</div>
<div class="card-body">
<h3>Batteries &amp; Power Supplies</h3>
<p>CCELL® batteries for retail and branded programs.</p>
<a href="/product-category/batteries/" class="card-btn">View Products</a>
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
