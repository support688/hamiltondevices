<?php
$conn = new mysqli('db', 'hamiltondevices', '6oiVitjQl3RbkQG', 'hamiltondevices');
$conn->set_charset('utf8mb4');

$result = $conn->query("SELECT post_content FROM wp_posts WHERE ID=96");
$row = $result->fetch_assoc();
$content = $row['post_content'];

// Replace the old stats section with a cleaner version
$old_stats = '[section bg_color="rgb(0,0,0)" padding="45px"]
[row style="small" h_align="center" v_align="middle"]
[col span="12" span__sm="12" align="center"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<p style="text-transform:uppercase;letter-spacing:4px;color:#c72035;font-weight:700;font-size:13px;margin-bottom:20px;">Authorized CCELL® Distributor</p>
[/ux_text]
[/col]
[/row]
[row style="small" h_align="center"]
[col span="2" span__sm="6" align="center" padding="10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="margin-bottom:5px;">
<span style="font-size:36px;font-weight:700;color:#fff;">2016</span>
</div>
<p style="font-size:13px;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:1px;">Partners Since</p>
[/ux_text]
[/col]
[col span="2" span__sm="6" align="center" padding="10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="margin-bottom:5px;">
<span style="font-size:36px;font-weight:700;color:#fff;">5,000+</span>
</div>
<p style="font-size:13px;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:1px;">Wholesale Customers</p>
[/ux_text]
[/col]
[col span="2" span__sm="6" align="center" padding="10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="margin-bottom:5px;">
<span style="font-size:36px;font-weight:700;color:#fff;">Full</span>
</div>
<p style="font-size:13px;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:1px;">Custom Branding</p>
[/ux_text]
[/col]
[col span="2" span__sm="6" align="center" padding="10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="margin-bottom:5px;">
<span style="font-size:36px;font-weight:700;color:#fff;">U.S.</span>
</div>
<p style="font-size:13px;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:1px;">Based Support</p>
[/ux_text]
[/col]
[col span="2" span__sm="6" align="center" padding="10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="margin-bottom:5px;">
<span style="font-size:36px;font-weight:700;color:#fff;">Direct</span>
</div>
<p style="font-size:13px;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:1px;">Factory Relationship</p>
[/ux_text]
[/col]
[/row]
[/section]';

$new_stats = '[section bg_color="rgb(0,0,0)" padding="50px"]
[row h_align="center"]
[col span="12" span__sm="12" align="center"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<p style="text-transform:uppercase;letter-spacing:5px;color:#c72035;font-weight:700;font-size:12px;margin-bottom:30px;">Why Brands Choose Hamilton Devices</p>
[/ux_text]
[/col]
[/row]
[row h_align="center"]
[col span="2" span__sm="6" align="center" padding="15px 10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:25px 15px;">
<span style="font-size:42px;font-weight:800;color:#fff;display:block;line-height:1;">2016</span>
<div style="width:30px;height:2px;background:#c72035;margin:12px auto;"></div>
<p style="font-size:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin:0;">CCELL® Partners<br>Since Day One</p>
</div>
[/ux_text]
[/col]
[col span="2" span__sm="6" align="center" padding="15px 10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:25px 15px;">
<span style="font-size:42px;font-weight:800;color:#fff;display:block;line-height:1;">5,000+</span>
<div style="width:30px;height:2px;background:#c72035;margin:12px auto;"></div>
<p style="font-size:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin:0;">Wholesale<br>Customers Served</p>
</div>
[/ux_text]
[/col]
[col span="2" span__sm="6" align="center" padding="15px 10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:25px 15px;">
<span style="font-size:42px;font-weight:800;color:#fff;display:block;line-height:1;">1 of 4</span>
<div style="width:30px;height:2px;background:#c72035;margin:12px auto;"></div>
<p style="font-size:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin:0;">Authorized U.S.<br>Distributors</p>
</div>
[/ux_text]
[/col]
[col span="2" span__sm="6" align="center" padding="15px 10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:25px 15px;">
<span style="font-size:42px;font-weight:800;color:#fff;display:block;line-height:1;">Full</span>
<div style="width:30px;height:2px;background:#c72035;margin:12px auto;"></div>
<p style="font-size:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin:0;">Custom Branding<br>&amp; White Label</p>
</div>
[/ux_text]
[/col]
[col span="2" span__sm="6" align="center" padding="15px 10px"]
[ux_text text_align="center" text_color="rgb(255,255,255)"]
<div style="border:1px solid rgba(255,255,255,0.15);border-radius:8px;padding:25px 15px;">
<span style="font-size:42px;font-weight:800;color:#fff;display:block;line-height:1;">U.S.</span>
<div style="width:30px;height:2px;background:#c72035;margin:12px auto;"></div>
<p style="font-size:12px;color:rgba(255,255,255,0.6);text-transform:uppercase;letter-spacing:1.5px;margin:0;">Based Support<br>&amp; Fulfillment</p>
</div>
[/ux_text]
[/col]
[/row]
[/section]';

$content = str_replace($old_stats, $new_stats, $content);

$stmt = $conn->prepare("UPDATE wp_posts SET post_content=?, post_modified=NOW(), post_modified_gmt=NOW() WHERE ID=96");
$stmt->bind_param('s', $content);
$stmt->execute();

echo "Updated! Affected rows: " . $stmt->affected_rows . "\n";
$conn->close();
