<?php
/**
 * Checkout terms and conditions area.
 *
 * @package          WooCommerce/Templates
 * @version          3.4.0
 * @flatsome-version 3.18.0
 */

defined( 'ABSPATH' ) || exit;

if ( apply_filters( 'woocommerce_checkout_show_terms', true ) && function_exists( 'wc_terms_and_conditions_checkbox_enabled' ) ) {
	do_action( 'woocommerce_checkout_before_terms_and_conditions' );

	?>
	<div class="woocommerce-terms-and-conditions-wrapper mb-half">
		<?php
		/**
		 * Terms and conditions hook used to inject content.
		 *
		 * @since 3.4.0.
		 * @hooked wc_checkout_privacy_policy_text() Shows custom privacy policy text. Priority 20.
		 * @hooked wc_terms_and_conditions_page_content() Shows t&c page content. Priority 30.
		 */
		do_action( 'woocommerce_checkout_terms_and_conditions' );
		?>

		<?php if ( wc_terms_and_conditions_checkbox_enabled() ) : ?>
		<script>jQuery( "#billing_country" ).change(function() {
 var cntry=jQuery("#billing_country").val();

 if(cntry=='US')
 {
   
 jQuery('#termy').html('<h3 class="chg">Shipping & Delivery: Please allow 1-2 business days for processing (excluding weekends/holidays). Shipping timelines are estimates provided by the carrier; we are not responsible for carrier delays.</h3>	<h3 class="chg" style="color:red;">Note: In compliance with the PACT Act, an adult signature is required for delivery on all orders.</h3>');
 }
 else
 {
     jQuery('#termy').html('<h3 class="chg">All orders take 1-2 business days ( 2-4 business days during the week of a holiday ) for processing, excluding weekends and holidays. Once an order is processed and a label is created, the tracking information provided by Route will update with an estimated delivery date.</h3><h3 class="chg">Due to the impact of COVID-19 and the recent PACT Act, delays may occur and UPS ( 6-10 business days ) shipping is not guaranteed. Due to the recent PACT Act, a signature is required for ALL orders; no exception.</h3>	<h3 class="chg">Also, all international customers may be subjected to pay custom &#47; duty fees, which is out of our control.</h3>');
 }
});

</script>
<div id="termy">

<h3 class="chg">Shipping & Delivery: Please allow 1-2 business days for processing (excluding weekends/holidays). Shipping timelines are estimates provided by the carrier; we are not responsible for carrier delays.

</h3>	<h3 class="chg" style="color:red;">Note: In compliance with the PACT Act, an adult signature is required for delivery on all orders.




</h3>
	<h5>Returned Package Policy/Terms of Use - <a href="/terms-of-use/" target="_blank">Click Here</a></h5>
</div>
		
			<p class="form-row validate-required">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
				<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="terms" <?php checked( apply_filters( 'woocommerce_terms_is_checked_default', isset( $_POST['terms'] ) ), true ); // WPCS: input var ok, csrf ok. ?> id="terms" />
					<?php if ( $link_style = get_theme_mod( 'checkout_terms_and_conditions' ) ) : ?>
						<span class="woocommerce-terms-and-conditions-checkbox-text"><?php flatsome_terms_and_conditions_checkbox_text( $link_style ); ?></span>&nbsp;<span class="required">*</span>
					<?php else : ?>
						<span class="woocommerce-terms-and-conditions-checkbox-text"><?php wc_terms_and_conditions_checkbox_text(); ?></span>&nbsp;<span class="required">*</span>
					<?php endif; ?>
				</label>
				<input type="hidden" name="terms-field" value="1" />
			</p>
		<?php endif; ?>
	</div>
	<?php

	do_action( 'woocommerce_checkout_after_terms_and_conditions' );
}
