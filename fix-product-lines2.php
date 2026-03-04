<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

$result = $conn->query("SELECT post_content FROM wp_posts WHERE ID=96");
$row = $result->fetch_assoc();
$content = $row['post_content'];

// Replace the entire product lines section with a clean card layout
$old_section = '[section padding="60px"]
[row]
[col span__sm="12" align="center"]
[ux_image id="240284" width="180" margin="0px 0px 15px 0px"]
[ux_text font_size="1.5" text_align="center"]
<h2>Product Lines</h2>
[/ux_text]
[ux_text text_align="center"]
<p style="font-size:17px;max-width:650px;margin:0 auto 35px;color:#555;">The complete CCELL® hardware lineup, available for wholesale purchase with custom branding options on every product.</p>
[/ux_text]
[/col]
[/row]

[row style="small" h_align="center"]
[col span="3" span__sm="6" padding="0px"]
[ux_image_box img="221053" image_width="70" image_size="original" image_overlay="rgba(0, 0, 0, 0.85)" link="product-category/cartridge/" text_align="left" class="dsdjyrt"]
[ux_text font_size="1.2" text_align="left" text_color="rgb(255, 255, 255)"]
<h3>510 Cartridges</h3>
[/ux_text]
<p style="color:rgba(255,255,255,0.7);font-size:14px;">Industry standard 510 thread cartridges. Multiple sizes and configurations available.</p>
[button text="View Products" letter_case="lowercase" color="white" style="link" size="large" expand="0" icon="icon-angle-right" link="product-category/cartridge/" class="vc"]
[/ux_image_box]
[/col]
[col span="3" span__sm="6" padding="0px"]
[ux_image_box img="219324" image_width="70" image_size="original" image_overlay="rgba(0, 0, 0, 0.85)" link="product-category/vaporizers/" text_align="left" class="dsdjyrt"]
[ux_text font_size="1.2" text_align="left" text_color="rgb(255, 255, 255)"]
<h3>All-In-One Disposables</h3>
[/ux_text]
<p style="color:rgba(255,255,255,0.7);font-size:14px;">Complete disposable units ready for your oil and your branding.</p>
[button text="View Products" letter_case="lowercase" color="white" style="link" size="large" expand="0" icon="icon-angle-right" link="product-category/vaporizers/" class="vc"]
[/ux_image_box]
[/col]
[col span="3" span__sm="6" padding="0px"]
[ux_image_box img="224082" image_width="70" image_size="original" image_overlay="rgba(0, 0, 0, 0.85)" link="product-category/pod-systems/" text_align="left" class="dsdjyrt"]
[ux_text font_size="1.2" text_align="left" text_color="rgb(255, 255, 255)"]
<h3>Pod Systems</h3>
[/ux_text]
<p style="color:rgba(255,255,255,0.7);font-size:14px;">Closed-loop pod systems for a proprietary branded experience.</p>
[button text="View Products" letter_case="lowercase" color="white" style="link" size="large" expand="0" icon="icon-angle-right" link="product-category/pod-systems/" class="vc"]
[/ux_image_box]
[/col]
[col span="3" span__sm="6" padding="0px"]
[ux_image_box img="76542" image_width="70" image_size="original" image_overlay="rgba(0, 0, 0, 0.85)" link="product-category/batteries/" text_align="left" class="dsdjyrt"]
[ux_text font_size="1.2" text_align="left" text_color="rgb(255, 255, 255)"]
<h3>Batteries &amp; Power Supplies</h3>
[/ux_text]
<p style="color:rgba(255,255,255,0.7);font-size:14px;">CCELL® Palm, Rizo, and M3 series batteries for retail and branded programs.</p>
[button text="View Products" letter_case="lowercase" color="white" style="link" size="large" expand="0" icon="icon-angle-right" link="product-category/batteries/" class="vc"]
[/ux_image_box]
[/col]
[/row]
[/section]';

$new_section = '[section padding="60px"]
[row]
[col span__sm="12" align="center"]
[ux_image id="240284" width="160" margin="0px 0px 10px 0px"]
[ux_text font_size="1.5" text_align="center"]
<h2>Product Lines</h2>
[/ux_text]
[ux_text text_align="center"]
<p style="font-size:17px;max-width:650px;margin:0 auto 35px;color:#555;">The complete CCELL® hardware lineup, available for wholesale purchase with custom branding options on every product.</p>
[/ux_text]
[/col]
[/row]

[row h_align="center"]
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
[/row]
[/section]';

$content = str_replace($old_section, $new_section, $content);

$stmt = $conn->prepare("UPDATE wp_posts SET post_content=?, post_modified=NOW(), post_modified_gmt=NOW() WHERE ID=96");
$stmt->bind_param('s', $content);
$stmt->execute();

echo "Updated! Affected rows: " . $stmt->affected_rows . "\n";
$conn->close();
