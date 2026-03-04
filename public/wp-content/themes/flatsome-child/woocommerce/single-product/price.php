<?php
/**
 * Single Product Price, including microdata for SEO
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/price.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see              https://docs.woocommerce.com/document/template-structure/
 * @author           WooThemes
 * @package          WooCommerce/Templates
 * @version          3.0.0
 * @flatsome-version 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $product;

$classes = array();
if($product->is_on_sale()) $classes[] = 'price-on-sale';
if(!$product->is_in_stock()) $classes[] = 'price-not-in-stock'; ?>


 
<div class="product_meta mains">
<?php
	
 if ( !$product->get_manage_stock() && $product->is_in_stock() ) {
        echo '<p class="stock in-stock"><img src="/wp-content/uploads/2021/12/Group-41425.png"> In Stock</p>';
    }   
	 echo wc_get_product_category_list( $product->get_id(), ', ', '<span class="posted_in">' . _n( 'Category:', 'Categories:', count( $product->get_category_ids() ), 'woocommerce' ) . ' ', '</span>' ); 

	 echo wc_get_product_tag_list( $product->get_id(), ', ', '<span class="tagged_as">' . _n( 'Tag:', 'Tags:', count( $product->get_tag_ids() ), 'woocommerce' ) . ' ', '</span>' ); 

	 do_action( 'woocommerce_product_meta_end' ); ?>

</div>
<?php  $user = wp_get_current_user();

 
if ( in_array( 'wholesale_customer', (array) $user->roles )  ) { ?>


	<div class="ort" style="text-align: left;
    color: #000;font-size: 18px;text-transform: lowercase;"> <?php if( get_field('wprice_text') )the_field ('wprice_text');?> <b style="color:#E50914;"><?php if( get_field('wlowest_price') ){echo '$';}the_field ('wlowest_price');?></b></div> 
	
<?php if(get_theme_mod('product_title_divider', 1)) { ?>
	<div class="is-divider small"></div>
<?php } ?>

 <div class="tablenamte who"style="color:#000;"><b>	<?php the_field('wtable_name'); ?></b></div> 
 
 
 <?php	$wtable_name = get_field('wtable_name');

 if($wtable_name){ ?>
                          
      <style>
table.pricing {
 width: 67%;
    border-radius: 13px;
        margin-top: 14px;
}
.single-product .price-wrapper {
    display: none;
}
table.pricing,table.pricing tr
{
  border: 2px solid #ebebeb;
}
table.pricing th,table.pricing td {
  
        text-transform: initial;
    padding: 11px 19px;
}
</style>                   
<table class="pricing whos">
  <tr>
    <th>	<?php the_field('1st_wcolumn_name'); ?></th>
    <th><?php the_field('2nd_wcolumn_name'); ?></th>
  </tr>
 <?php	$wquantity_limit_1 = get_field('wquantity_limit_1');?>

 <?php if($wquantity_limit_1){ ?> <tr>
    <td><?php the_field('wquantity_limit_1'); ?></td>
    <td style="color:#E50914;font-weight:bold;"><?php the_field('wprice_per_unit_1'); ?></td>
  </tr>
   <?php } ?>
   <?php	$wquantity_limit_2 = get_field('wquantity_limit_2');?>

 <?php if($wquantity_limit_2){ ?><tr>
    <td><?php the_field('wquantity_limit_2'); ?></td>
    <td style="color:#E50914;font-weight:bold;"><?php the_field('wprice_per_unit_2'); ?></td>
  </tr><?php } 
  
  $wquantity_limit_3 = get_field('wquantity_limit_3');?>

 <?php if($wquantity_limit_3){ ?><tr>
    <td><?php the_field('wquantity_limit_3'); ?></td>
    <td style="color:#E50914;font-weight:bold;"><?php the_field('wprice_per_unit_3'); ?></td>
  </tr><?php } 
  $wquantity_limit_4 = get_field('wquantity_limit_4');?>

 <?php if($wquantity_limit_4){ ?><tr>
    <td><?php the_field('wquantity_limit_4'); ?></td>
    <td style="color:#E50914;font-weight:bold;"><?php the_field('wprice_per_unit_4'); ?></td>
  </tr><?php } 
  
   	$msrp = get_field('msrp');
  
    if($msrp){ ?><tr>
    <td style="color:#000;font-weight:bold;"><?php the_field('msrp'); ?></td>
    <td style="color:#E50914;font-weight:bold;"><?php the_field('msrpprice'); ?></td>
  </tr><?php } ?>
</table>
 <?php }} 
else { ?>



 	<div class="ort" style="text-align: left;
    color: #000;font-size: 18px;text-transform: lowercase;"> <?php if( get_field('price_text') )the_field ('price_text');?> <b style="color:#E50914;"><?php if( get_field('lowest_price') ){echo '$';}the_field ('lowest_price');?></b></div> 
	
<?php if(get_theme_mod('product_title_divider', 1)) { ?>
	<div class="is-divider small"></div>
<?php } ?>


	
<?php
if ( in_array( 'wholesale_customer', (array) $user->roles )) {
	
	$table_name = 'Wholesale Pricing';
}
else
{
	 $table_name = get_field('table_name');
}
?>
<div class="tablenamte"style="color:#000;"><b>	<?php echo $table_name; ?></b></div>
<?php
if($table_name){ ?>
                          
      <style>
table.pricing {
 width: 67%;
    border-radius: 13px;
        margin-top: 14px;
}
.single-product .price-wrapper {
    display: none;
}
table.pricing,table.pricing tr
{
  border: 2px solid #ebebeb;
}
table.pricing th,table.pricing td {
  
        text-transform: initial;
    padding: 11px 19px;
}
</style>                   
<table class="pricing">
  <tr>
    <th>	<?php the_field('1st_column_name'); ?></th>
    <th><?php the_field('2nd_column_name'); ?></th>
  </tr>
 <?php	$quantity_limit_1 = get_field('quantity_limit_1');?>

 <?php if($quantity_limit_1){ ?> <tr>
    <td><?php the_field('quantity_limit_1'); ?></td>
    <td style="color:#E50914;font-weight:bold;"><?php the_field('price_per_unit_1'); ?></td>
  </tr>
   <?php } ?>
   <?php	$quantity_limit_2 = get_field('quantity_limit_2');?>

 <?php if($quantity_limit_2){ ?><tr>
    <td><?php the_field('quantity_limit_2'); ?></td>
    <td style="color:#E50914;font-weight:bold;"><?php the_field('price_per_unit_2'); ?></td>
  </tr><?php } ?>
  <?php	$quantity_limit_3 = get_field('quantity_limit_3');?>

 <?php if($quantity_limit_3){ ?>
   <tr>
    <td><?php the_field('quantity_limit_3'); ?></td>
    <td style="color:#E50914;font-weight:bold;"><?php the_field('price_per_unit_3'); ?></td>
  </tr> <?php } ?>
  <?php	$quantity_limit_4 = get_field('quantity_limit_4');?>

 <?php if($quantity_limit_4){ ?>
 <tr>
    <td><?php the_field('quantity_limit_4'); ?></td>
    <td style="color:#E50914;font-weight:bold;"><?php the_field('price_per_unit_4'); ?></td>
  </tr> <?php } ?>
  <?php	$quantity_limit_5 = get_field('quantity_limit_5');?>

 <?php if($quantity_limit_5){ ?>
 <tr>
    <td><?php the_field('quantity_limit_5'); ?></td>
    <td style="color:#E50914;font-weight:bold;"><?php the_field('price_per_unit_5'); ?></td>
  </tr> <?php } ?>
</table>
 <?php } }