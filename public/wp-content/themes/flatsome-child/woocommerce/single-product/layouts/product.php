<?php
/**
 * Product.
 *
 * @package          Flatsome/WooCommerce/Templates
 * @flatsome-version 3.19.0
 */

?>
<?php if(get_theme_mod('product_display') == 'sections'){
	wc_get_template_part( 'single-product/tabs/sections' );
	return;
}

// Get accordian instead of tabs if set
if(get_theme_mod('product_display') == 'accordian'){
	wc_get_template_part( 'single-product/tabs/accordian' );
	return;
}

/**
 * Filter tabs and allow third parties to add their own
 *
 * Each tab is an array containing title, callback and priority.
 * @see woocommerce_default_product_tabs()
 */
$tabs = apply_filters( 'woocommerce_product_tabs', array() );
$count_tabs = 0;
$count_panel = 0;
?>
<div class="product-container">

<div class="product-main">
	<div class="row content-row mb-0">

		<div class="product-gallery col large-<?php echo flatsome_option('product_image_width'); ?>">
		<?php
			/**
			 * woocommerce_before_single_product_summary hook
			 *
			 * @hooked woocommerce_show_product_images - 20
			 */
			do_action( 'woocommerce_before_single_product_summary' );
		?>
		
	

	<div class="woocommerce-tabs container tabbed-content desktop">
		<ul class="product-tabs  nav small-nav-collapse tabs <?php flatsome_product_tabs_classes() ?>">
			<?php
				foreach ( $tabs as $key => $tab ) : ?>
				<li class="<?php echo esc_attr( $key ); ?>_tab  <?php if($count_tabs == 0) echo 'active';?>">
				
				</li>
			<?php $count_tabs++; endforeach; ?>
		</ul>
		<div class="tab-panels">
		<?php foreach ( $tabs as $key => $tab ) : ?>

			<div class="panel entry-content <?php if($count_panel == 0) echo 'active';?>" id="tab-<?php echo $key ?>">
        <?php if($key == 'description' && ux_builder_is_active()) { echo flatsome_dummy_text(); } ?>
				<?php call_user_func( $tab['callback'], $key, $tab ) ?>
			</div>

		<?php $count_panel++; endforeach; ?>
		</div><!-- .tab-panels -->
	</div><!-- .tabbed-content -->


		</div>

		<div class="product-info summary col-fit col entry-summary <?php flatsome_product_summary_classes();?>">
			<?php
				/**
				 * woocommerce_single_product_summary hook
				 *
				 * @hooked woocommerce_template_single_title - 5
				 * @hooked woocommerce_template_single_rating - 10
				 * @hooked woocommerce_template_single_price - 10
				 * @hooked woocommerce_template_single_excerpt - 20
				 * @hooked woocommerce_template_single_add_to_cart - 30
				 * @hooked woocommerce_template_single_meta - 40
				 * @hooked woocommerce_template_single_sharing - 50
				 */
				do_action( 'woocommerce_single_product_summary' );
			?>

		</div><!-- .summary -->


		<div id="product-sidebar" class="col large-2 hide-for-medium product-sidebar-small">
			<?php
				do_action('flatsome_before_product_sidebar');
				/**
				 * woocommerce_sidebar hook
				 *
				 * @hooked woocommerce_get_sidebar - 10
				 */
				if (is_active_sidebar( 'product-sidebar' ) ) {
					dynamic_sidebar('product-sidebar');
				}
			?>
		</div>

	</div><!-- .row -->
</div><!-- .product-main -->
	<?php	if ( ! empty( $tabs ) ) : ?>

	<div class="woocommerce-tabs container tabbed-content mob">
		<ul class="product-tabs  nav small-nav-collapse tabs <?php flatsome_product_tabs_classes() ?>">
			<?php
				foreach ( $tabs as $key => $tab ) : ?>
				<li class="<?php echo esc_attr( $key ); ?>_tab  <?php if($count_tabs == 0) echo 'active';?>">
				
				</li>
			<?php $count_tabs++; endforeach; ?>
		</ul>
		<div class="tab-panels">
		<?php foreach ( $tabs as $key => $tab ) : ?>

			<div class="panel entry-content <?php if($count_panel == 0) echo 'active';?>" id="tab-<?php echo $key ?>">
        <?php if($key == 'description' && ux_builder_is_active()) { echo flatsome_dummy_text(); } ?>
				<?php call_user_func( $tab['callback'], $key, $tab ) ?>
			</div>

		<?php $count_panel++; endforeach; ?>
		</div><!-- .tab-panels -->
	</div><!-- .tabbed-content -->

<?php endif; ?>
<?php $value = get_field( "videomain" );
if( $value ) {
    
   

?>
<div class="mbvideo"style="position: relative;padding: 20px;
    display: flex;
    justify-content: center;
    margin-bottom: 74px;"><iframe width="560" height="315" src="<?php  echo $value; ?>" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div> 
<?php	} else {

    echo '';
    
} ?>
<?php $value = get_field( "videomainshiv" );

if( $value ) {
    
   

?>
<div class="mbvideo1"style="position: relative;padding: 20px;
    display: flex;
    justify-content: center;
    margin-bottom: 74px; margin-top: -74px;"><iframe width="560" height="315" src="<?php  echo $value; ?>" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div> 
<?php	} else {

    echo '';
    
} ?>
<?php if(is_product() && get_the_id() == 89145){  
?>
<div class="praddon">
<div class="container">
<h4 class="rec">Customers Also Bought</h4>
	<?php echo do_shortcode('

[ux_products type="row" columns="2" ids="105519,89163"]


')?>

</div></div><?php } ?>
<?php if(is_product() && get_the_id() == 43853){  
?>
<div class="praddon">
<div class="container">
<h4 class="rec">Customers Also Bought</h4>
	<?php echo do_shortcode('

[ux_products type="row" columns="2" ids="116980"]


')?>

</div></div><?php } ?>
<?php if(is_product() && get_the_id() == 89153){  
?>
<div class="praddon">
<div class="container">
<h4 class="rec">Customers Also Bought</h4>
	<?php echo do_shortcode('

[ux_products type="row" columns="3" ids="116980,105524,89163"]


')?>

</div></div><?php } ?>


<div class="product-footer">
	<div class="container">
		<?php
			/**
			 * woocommerce_after_single_product_summary hook
			 *
			 * @hooked woocommerce_output_product_data_tabs - 10
			 * @hooked woocommerce_upsell_display - 15
			 * @hooked woocommerce_output_related_products - 20
			 */
			do_action( 'woocommerce_after_single_product_summary' );
		?>
	</div><!-- .container -->
</div><!-- .product-footer -->
</div><!-- .product-container -->