<?php
function filter_woocommerce_cart_crosssell_ids( $cross_sells, $cart ) {
    // Initialize
    $product_cats_ids = array();
    $product_cats_ids_unique = array();

    foreach ( $cart->get_cart() as $cart_item ) {       
        // Get product id
        $product_id = $cart_item['product_id'];

        // Get current product categories id(s) & add to array
        $product_cats_ids = array_merge( $product_cats_ids, wc_get_product_term_ids( $product_id, 'product_cat' ) );
    }

    // Not empty
    if ( !empty( $product_cats_ids ) ) {
        // Removes duplicate values
        $product_cats_ids_unique = array_unique( $product_cats_ids, SORT_REGULAR );

        // Get product id(s) from a certain category, by category-id
        $product_ids_from_cats_ids = get_posts( array(
            'post_type'   => 'product',
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields'      => 'ids',
            'tax_query'   => array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'id',
                    'terms'    => $product_cats_ids_unique,
                    'operator' => 'IN',
                )
            ),
        ) );

        // Removes duplicate values
        $cross_sells = array_unique( $product_ids_from_cats_ids, SORT_REGULAR );
    }

    return $cross_sells;
}
add_filter( 'woocommerce_cart_crosssell_ids', 'filter_woocommerce_cart_crosssell_ids', 10, 2 );
function filter_woocommerce_cross_sells_total( $limit ) {
    // Set limit
    $limit = 2;
    
    return $limit;
}
add_filter( 'woocommerce_cross_sells_total', 'filter_woocommerce_cross_sells_total', 10, 1 );
function redirect_wholesale_customer(){
wp_redirect('https://hamiltondevices.com/wholesale-registration-page-pending/');
exit();
}

function logout_wholesale_customer($wuser_id){
$sessions = WP_Session_Tokens::get_instance($wuser_id);
exit();
}	

update_user_meta( '11980', 'wholesale_pending', 'false' );


add_action('rest_pre_dispatch', 'restrict_api_request_for_non_logged_in_users', 10, 3);
function restrict_api_request_for_non_logged_in_users($result, $server, $request) {

if ($request->get_route() === '/wp/v2/users/me' && !is_user_logged_in()) {

	status_header(200);
exit;
}

}

add_action('admin_head', 'my_custom_css1');
function my_custom_css1() {
	$user = wp_get_current_user();
if ( in_array( 'reviewchecker', (array) $user->roles ) ) {
    echo '<style>
	.update-nag.notice.notice-warning.inline,li#wp-admin-bar-flatsome-activate,li#wp-admin-bar-flatsome_panel, div#setting-error-tgmpa,.updated.woocommerce-message,div#message,li#toplevel_page_mwb-wocuf-pro-setting,.notice.js-wc-plugin-framework-admin-notice.error.is-dismissible,li#toplevel_page_woocommerce,li#menu-users,li#toplevel_page_newaffiliate,li#menu-dashboard,div#wooccm-admin-rating,.notice.updated.editorsFlatRate-notice,.cr-notice-auto-download.notice.notice-info,li#wp-admin-bar-new-content,div#wpadminbar,div#adminmenumain{display:none !important;}li.developer,.update-message.notice.inline.notice-warning.notice-alt,li#toplevel_page_wp_file_manager {
    display: none !important;
}span.update-plugins {
    display: none !important;
}td.referer.column-referer,th#referer {  display: none !important;}.error,div#wfls-woocommerce-integration-notice,.notice.js-wc-plugin-framework-admin-notice.error.is-dismissible {
    display: none;
}
  </style>';
}
  echo '<style>
	.update-nag.notice.notice-warning.inline,li#wp-admin-bar-flatsome-activate,li#wp-admin-bar-flatsome_panel, div#setting-error-tgmpa,.updated.woocommerce-message,div#message,.notice.notice-error.is-dismissible.wwlc-activate-license-notice,.notice.notice-error.is-dismissible.wwof-activate-license-notice,.notice.notice-error.is-dismissible.wwpp-activate-license-notice,.update-nag.rightpress-updates-update-nag{display:none;}.update-message.notice.inline.notice-warning.notice-alt,li#toplevel_page_wp_file_manager,li.developer,.notice.js-wc-plugin-framework-admin-notice.error.is-dismissible {
    display: none !important;
}span.update-plugins {
    display: none !important;
}.notice.js-wc-plugin-framework-admin-notice.error.is-dismissible,td.referer.column-referer,th#referer {  display: none !important; }.error,div#wfls-woocommerce-integration-notice,.notice.js-wc-plugin-framework-admin-notice.updated.is-dismissible {
    display: none;
}  </style>';
 
}

add_filter( 'woocommerce_product_add_to_cart_text', 'bbloomer_change_select_options_button_text', 9999, 2 );
 
function bbloomer_change_select_options_button_text( $label, $product ) {
   if ( $product->is_type( 'variable' ) ) {
      return 'Buy Now';
   }
   return $label;
}

function TRIM_ADMIN_MENU() {
global $current_user;
if(current_user_can('author')) {
?><style>
li#toplevel_page_wc-admin-path--analytics-revenue,li#toplevel_page_woocommerce,li#menu-comments,li#menu-media,li#menu-posts,.notice.js-wc-plugin-framework-admin-notice.updated.is-dismissible{display:none;}
</style>
<?php
}
$user = wp_get_current_user();
$user_id=$user->ID;
$user_meta=get_userdata($user_id);

$user_roles=$user_meta->roles;
if (current_user_can('administrator')){
?>
<style>
li#menu-appearance,li#toplevel_page_woocommerce-marketing,li#toplevel_page_smush,li#toplevel_page_maxmegamenu,li#toplevel_page_WP-Optimize,li#toplevel_page_Wordfence,li#toplevel_page_w3tc_dashboard,li#toplevel_page_developermode,li#menu-posts-snp_popups,li#toplevel_page_wp-mail-smtp,li#toplevel_page_edit-post_type-acf-field-group,li#menu-posts-blocks,li#toplevel_page_dots_store,li#toplevel_page_flatsome-panel,li#menu-settings,li#toplevel_page_woocommerce-checkout-manager,li#toplevel_page_mwb-wocuf-pro-setting,li#menu-pages,li#menu-posts-featured_item,li#menu-posts,li#menu-posts-product,li#toplevel_page_woo-variation-swatches-settings,li#menu-users {display:none;}li#toplevel_page_wpam-affiliates ul.wp-submenu.wp-submenu-wrap,li#wp-admin-bar-flatsome-activate,li.developer,div#wfls-woocommerce-integration-notice,.notice.js-wc-plugin-framework-admin-notice.updated.is-dismissible {
    display: none !important;
}.error,.notice.js-wc-plugin-framework-admin-notice.error.is-dismissible {
    display: none;
}nav.nav-tab-wrapper.woo-nav-tab-wrapper a:last-child {
    display: none !important;
}
</style>
<?php
}
}
//add_action('admin_init', 'TRIM_ADMIN_MENU');

add_filter( 'woocommerce_cross_sells_columns', 'bbloomer_change_cross_sells_columns' );
 
function bbloomer_change_cross_sells_columns( $columns ) {
return 3;
}

add_filter( 'woocommerce_checkout_fields' , 'custom_checkout_fields' );
function custom_checkout_fields( $fields ) {
unset($fields['billing']['billing_company']);
unset($fields['shipping']['shipping_phone']);
unset($fields['shipping']['shipping_company']);
return $fields;
}




add_action( 'woocommerce_after_order_notes', 'my_custom_checkout_field' );
function my_custom_checkout_field() {
    echo '<div id="my_custom_checkout_field">';

    woocommerce_form_field( 'my_field_name', array(
        'type'      => 'checkbox',
        'class'     => array('input-checkbox'),
        'label'     => __(' I agree to receive text messages about marketing information and sales'),
         'default' => 1 
    ),  WC()->checkout->get_value( 'my_field_name' ) );
    
    woocommerce_form_field( 'my_field_name1', array(
        'type'      => 'checkbox',
        'class'     => array('input-checkbox'),
        'label'     => __('I want to receive information about my order delivery tracking'),
         'default' => 1 
    ),  WC()->checkout->get_value( 'my_field_name1' ) );
    echo '</div>';
}

// Save the custom checkout field in the order meta, when checkbox has been checked
add_action( 'woocommerce_checkout_update_order_meta', 'custom_checkout_field_update_order_meta', 10, 1 );
function custom_checkout_field_update_order_meta( $order_id ) {

    if ( ! empty( $_POST['my_field_name'] ) )
        update_post_meta( $order_id, 'my_field_name', $_POST['my_field_name'] );
}

// Display the custom field result on the order edit page (backend) when checkbox has been checked
add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_custom_field_on_order_edit_pages', 10, 1 );
function display_custom_field_on_order_edit_pages( $order ){
    $my_field_name = get_post_meta( $order->get_id(), 'my_field_name', true );
    if( $my_field_name == 1 )
        echo '<p><strong> I agree to receive text messages about marketing information and sales </strong> <span style="color:red;">Is enabled</span></p>';
}



// Save the custom checkout field in the order meta, when checkbox has been checked
add_action( 'woocommerce_checkout_update_order_meta', 'custom_checkout_field_update_order_meta1', 10, 1 );
function custom_checkout_field_update_order_meta1( $order_id ) {

    if ( ! empty( $_POST['my_field_name1'] ) )
        update_post_meta( $order_id, 'my_field_name1', $_POST['my_field_name1'] );
}

// Display the custom field result on the order edit page (backend) when checkbox has been checked
add_action( 'woocommerce_admin_order_data_after_billing_address', 'display_custom_field_on_order_edit_pages1', 10, 1 );
function display_custom_field_on_order_edit_pages1( $order ){
    $my_field_name1 = get_post_meta( $order->get_id(), 'my_field_name1', true );
    if( $my_field_name1 == 1 )
        echo '<p><strong> I want to receive information about my order delivery tracking </strong> <span style="color:red;">Is enabled</span></p>';
}





add_action('woocommerce_after_checkout_validation','check_new_customer_coupon', 0);

function check_new_customer_coupon(){
	global $woocommerce;
	// you might change the firstlove to your coupon
	$new_cust_coupon_code = 'firsthamorder';
	
	$has_apply_coupon = false;

	foreach ( WC()->cart->get_coupons() as $code => $coupon ) {
		if($code == $new_cust_coupon_code) {
			$has_apply_coupon = true;
		}
	}

	if($has_apply_coupon) {
			
		if(is_user_logged_in()) {
			$user_id = get_current_user_id();

			// retrieve all orders
			$customer_orders = get_posts( array(
					'meta_key'    => '_customer_user',
					'meta_value'  => $user_id,
					'post_type'   => 'shop_order',
					'numberposts'=> -1
			) );

			if(count($customer_orders) > 0) {
				$has_ordered = false;
					
				$statuses = array('wc-failed', 'wc-cancelled', 'wc-refunded');
					
				// loop thru orders, if the order is not falled into failed, cancelled or refund then it consider valid
				foreach($customer_orders as $tmp_order) {

					$order = wc_get_order($tmp_order->ID);
					if(!in_array($order->get_status(), $statuses)) {
						$has_ordered = true;
					}
				}
					
				// if this customer already ordered, we remove the coupon
				if($has_ordered == true) {
					WC()->cart->remove_coupon( $new_cust_coupon_code );
					wc_add_notice( sprintf( "Coupon code: %s is only applicable for new customer." , $new_cust_coupon_code), 'error' );
					return false;
				}
			} else {
				// customer has no order, so valid to use this coupon
				return true;
			}

		} else {
			// new user is valid
			return true;
		}
	}

}





add_filter( 'woocommerce_default_address_fields', 'customising_checkout_fields', 1000, 1 );
function customising_checkout_fields( $address_fields ) {
  
   
    $address_fields['address_1']['required'] = true;
    $address_fields['city']['required'] = true;

    $address_fields['state']['required'] = true;
    $address_fields['postcode']['required'] = true;

    return $address_fields;
}






add_filter( 'woocommerce_email_headers', 'add_reply_to_wc_admin_new_order', 10, 3 );

function add_reply_to_wc_admin_new_order( $headers = '', $id = '', $order ) {
    if ( $id == 'new_order' ) {
        $reply_to_email = "no_reply@hamiltondevices.com";
        $headers = "Reply-to: <$reply_to_email>\r\n";
    }
    return $headers;
}
/*function sv_conditional_email_recipient( $recipient, $order ) {
    // Bail on WC settings pages since the order object isn't yet set yet
    // Not sure why this is even a thing, but shikata ga nai
    $page = $_GET['page'] = isset( $_GET['page'] ) ? $_GET['page'] : '';
    if ( 'wc-settings' === $page ) {
        return $recipient; 
    }

    // just in case
    if ( ! $order instanceof WC_Order ) {
        return $recipient; 
    }
    $items = $order->get_items();

    // check if a shipped product is in the order   
    foreach ( $items as $item ) {
        $product = $order->get_product_from_item( $item );

        // add our extra recipient if there's a shipped product - commas needed!
        // we can bail if we've found one, no need to add the recipient more than once
        if ( $product && $product->needs_shipping() ) {
            $recipient .= ', orders@hamiltondevices.com';
            return $recipient;
        }
    }

    return $recipient;
}
add_filter( 'woocommerce_email_recipient_new_order', 'sv_conditional_email_recipient', 10, 2 );

*/
function sv_conditional_email_recipient( $recipient, $order ) {
    // Bail on WC settings pages since the order object isn't yet set yet
    $page = isset( $_GET['page'] ) ? $_GET['page'] : '';
    if ( 'wc-settings' === $page ) {
        return $recipient; 
    }

    // just in case
    if ( ! $order instanceof WC_Order ) {
        return $recipient; 
    }

    $items = $order->get_items();

    // check if a shipped product is in the order   
    foreach ( $items as $item ) {
        $product = $item->get_product(); // ✅ modern WooCommerce method

        // add our extra recipient if there's a shipped product - commas needed!
        if ( $product && $product->needs_shipping() ) {
            $recipient .= ', orders@hamiltondevices.com';
            return $recipient;
        }
    }

    return $recipient;
}
add_filter( 'woocommerce_email_recipient_new_order', 'sv_conditional_email_recipient', 10, 2 );






/**
 * woocommerce_single_product_summary hook
 *
 * @hooked woocommerce_template_single_title - 5
 * @hooked woocommerce_template_single_price - 10
 * @hooked woocommerce_template_single_excerpt - 20
 * @hooked woocommerce_template_single_add_to_cart - 30
 * @hooked woocommerce_template_single_meta - 40
 * @hooked woocommerce_template_single_sharing - 50
 */

remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
add_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 15);



/**

function mytheme_admin_bar_render() {
    global $wp_admin_bar;
    // we can add a submenu item too
    $wp_admin_bar->add_menu( array(
        'id' => 'print_orders',
        'title' => __('Text Message'),
        'href' => get_bloginfo('url').'/message-details/',
        'meta'  => array(
            //'title' => __('Print Orders'),
            'target' => '_blank',
            'class' => 'woo_print_orders'
        ),
    ) );
}
// and we hook our function via, just for admin use role
if( current_user_can('administrator') ) {
    add_action( 'wp_before_admin_bar_render', 'mytheme_admin_bar_render' );
}





add_filter( 'page_template', 'my_function' );
function my_function( $page_template ){
  global $post;
  $page_id = $post->ID;
  if ( $page_id == '39667' ) {
    $page_template = get_template_directory() . '/template-print-processing-orders.php'; 
    
  }  
  return $page_template;
}



*/

   add_filter('woocommerce_show_variation_price',      function() { return TRUE;});





// Disable the woocommerce_hold_stock_for_checkout.
add_filter('woocommerce_hold_stock_for_checkout',  'mwb_disable_hold_stock_for_checkout');
/**
* Disable the filter
* @name mwb_disable_hold_stock_for_checkout
*/
function mwb_disable_hold_stock_for_checkout(){
 return false;
}



/* ===============================
 * SHOW "YOU SAVE %" ON PRODUCT PAGE
 * SIMPLE + VARIABLE PRODUCTS
 * =============================== */

function ts_you_save() {
    global $product;

    if ( ! $product ) return;

    // SIMPLE / EXTERNAL / GROUPED PRODUCTS
    if ( $product->is_type( array( 'simple', 'external', 'grouped' ) ) ) {

        $regular_price = (float) $product->get_regular_price();
        $sale_price    = (float) $product->get_sale_price();

        if ( $sale_price && $regular_price > $sale_price ) {
            $percentage = round( ( ( $regular_price - $sale_price ) / $regular_price ) * 100 );
            echo '<p class="savesale"><b>You Save: ' . $percentage . '%</b></p>';
        }
    }

    // VARIABLE PRODUCT PLACEHOLDER (updated via JS)
    if ( $product->is_type( 'variable' ) ) {
        echo '<p class="savesale" id="you-save-variable" style="display:none;"><b></b></p>';
    }
}
add_action( 'woocommerce_single_product_summary', 'ts_you_save', 14 );


/* ===============================
 * VARIABLE PRODUCT JS LOGIC
 * =============================== */
add_action( 'wp_footer', 'ts_you_save_variable_js' );
function ts_you_save_variable_js() {
    if ( ! is_product() ) return;
    ?>
    <script>
    jQuery(function($){
        $('form.variations_form').on('found_variation', function(event, variation){
            if(
                variation.display_regular_price &&
                variation.display_price &&
                variation.display_regular_price > variation.display_price
            ) {
                let percentage = Math.round(
                    ((variation.display_regular_price - variation.display_price) / variation.display_regular_price) * 100
                );

                $('#you-save-variable')
                    .html('<b>You Save: ' + percentage + '%</b>')
                    .show();
            } else {
                $('#you-save-variable').hide();
            }
        });

        $('form.variations_form').on('reset_data', function(){
            $('#you-save-variable').hide();
        });
    });
    </script>
    <?php
}


class iWC_Orderby_Stock_Status {
public function __construct() {
    $active = apply_filters('active_plugins', get_option('active_plugins'));
    if (is_array($active) && in_array('woocommerce/woocommerce.php', $active)) {
        add_filter('posts_clauses', array($this, 'order_by_stock_status'), 2000);
    }
}
public function order_by_stock_status($posts_clauses) {
    global $wpdb;   
    if (is_woocommerce() && (is_shop() || is_product_category() || is_product_tag())) {
        $posts_clauses['join'] .= " INNER JOIN $wpdb->postmeta istockstatus ON ($wpdb->posts.ID = istockstatus.post_id) ";
        $posts_clauses['orderby'] = " istockstatus.meta_value ASC, " . $posts_clauses['orderby'];
        $posts_clauses['where'] = " AND istockstatus.meta_key = '_stock_status' AND istockstatus.meta_value <> '' " . $posts_clauses['where'];
    }
    return $posts_clauses;
    }
}
new iWC_Orderby_Stock_Status;









// Create and display the custom field in product general setting tab
add_action( 'woocommerce_product_options_general_product_data', 'add_custom_field_general_product_fields' );
function add_custom_field_general_product_fields(){
    global $post;

    echo '<div class="product_custom_field">';

    // Custom Product Checkbox Field
    woocommerce_wp_checkbox( array(
        'id'        => '_disabled_for_coupons',
        'label'     => __('Disabled for coupons', 'woocommerce'),
        'description' => __('Disable this products from coupon discounts', 'woocommerce'),
        'desc_tip'  => 'true',
    ) );

    echo '</div>';;
}

// Save the custom field and update all excluded product Ids in option WP settings
add_action( 'woocommerce_process_product_meta', 'save_custom_field_general_product_fields', 10, 1 );
function save_custom_field_general_product_fields( $post_id ){

    $current_disabled = isset( $_POST['_disabled_for_coupons'] ) ? 'yes' : 'no';

    $disabled_products = get_option( '_products_disabled_for_coupons' );
    if( empty($disabled_products) ) {
        if( $current_disabled == 'yes' )
            $disabled_products = array( $post_id );
    } else {
        if( $current_disabled == 'yes' ) {
            $disabled_products[] = $post_id;
            $disabled_products = array_unique( $disabled_products );
        } else {
            if ( ( $key = array_search( $post_id, $disabled_products ) ) !== false )
                unset( $disabled_products[$key] );
        }
    }

    update_post_meta( $post_id, '_disabled_for_coupons', $current_disabled );
    update_option( '_products_disabled_for_coupons', $disabled_products );
}

// Make coupons invalid at product level
add_filter('woocommerce_coupon_is_valid_for_product', 'set_coupon_validity_for_excluded_products', 12, 4);
function set_coupon_validity_for_excluded_products($valid, $product, $coupon, $values ){
    if( ! count(get_option( '_products_disabled_for_coupons' )) > 0 ) return $valid;

    $disabled_products = get_option( '_products_disabled_for_coupons' );
    if( in_array( $product->get_id(), $disabled_products ) )
        $valid = false;

    return $valid;
}

// Set the product discount amount to zero
add_filter( 'woocommerce_coupon_get_discount_amount', 'zero_discount_for_excluded_products', 12, 5 );
function zero_discount_for_excluded_products($discount, $discounting_amount, $cart_item, $single, $coupon ){
    if( ! count(get_option( '_products_disabled_for_coupons' )) > 0 ) return $discount;

    $disabled_products = get_option( '_products_disabled_for_coupons' );
    if( in_array( $cart_item['product_id'], $disabled_products ) )
        $discount = 0;

    return $discount;
}

add_filter( 'woocommerce_product_related_posts_query', 'alter_product_related_posts_query', 10, 3 );
function alter_product_related_posts_query( $query, $product_id, $args ){
    global $wpdb;

    $query['join']  .= " INNER JOIN {$wpdb->postmeta} as pm ON p.ID = pm.post_id ";
    $query['where'] .= " AND pm.meta_key = '_stock_status' AND meta_value = 'instock' ";

    return $query;
}

function prefix_nav_description( $item_output, $item, $depth, $args ) {
    if ( !empty( $item->description ) ) {
        $item_output = str_replace( '">' . $args->link_before . $item->title, '">'  . $item->title, $item_output . $args->link_before . '<span class="menu-item-description">' . $item->description . '</span>');
    }
 
    return $item_output;
}
add_filter( 'walker_nav_menu_start_el', 'prefix_nav_description', 10, 4 );




/*function mytheme_admin_bar_render() {
    global $wp_admin_bar;
    // we can add a submenu item too
    $wp_admin_bar->add_menu( array(
        'id' => 'print_orders',
        'title' => __('Print Orders'),
        'href' => get_bloginfo('url').'/print-orders/',
        'meta'  => array(
            //'title' => __('Print Orders'),
            'target' => '_blank',
            'class' => 'woo_print_orders'
        ),
    ) );
}
// and we hook our function via, just for admin use role
if( current_user_can('administrator') ) {
    add_action( 'wp_before_admin_bar_render', 'mytheme_admin_bar_render' );
}*/



// show new product reviews first
add_filter( 'woocommerce_product_review_list_args', 'new_reviews_first' );
function new_reviews_first($args) {
    $args['reverse_top_level'] = true;
    return $args;
}

add_action( 'init', 'move_related_products_before_tabs' );
function move_related_products_before_tabs( ) {
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 5 );
    add_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
}


add_filter( 'woocommerce_ship_to_different_address_checked', '__return_false' );




function so_57838961_filter_woocommerce_states( $states ) { 
    unset( $states['DE'] );
   unset( $states['NO'] );
    return $states;
};
add_filter( 'woocommerce_states', 'so_57838961_filter_woocommerce_states', 10, 1 );

function so_57838961_filter_woocommerce_get_country_locale( $locale ) { 
    $locale['DE']['state']['required'] = true;
	$locale['NO']['state']['required'] = true;
    return $locale; 
};
add_filter( 'woocommerce_get_country_locale', 'so_57838961_filter_woocommerce_get_country_locale', 10, 1 );


add_filter( 'woocommerce_order_number', 'change_woocommerce_order_number', 10, 2 );
function change_woocommerce_order_number( $order_number, $order ) {
    $prefix = 'Wholesale_'; // The Prefix

    // Make sure $order is a WC_Order object
    if ( is_numeric( $order ) ) {
        $order = wc_get_order( $order );
    }

    if ( ! $order instanceof WC_Order ) {
        return $order_number;
    }

    // Get the wholesale order type meta
    $order_type = $order->get_meta( '_wwpp_order_type' );

    if ( $order_type === 'wholesale' ) {
        $order_number = $prefix . $order->get_id();
    }

    return $order_number;
}





if (!current_user_can('wholesale_customer')){



add_action( 'woocommerce_before_checkout_billing_form', 'bbloomer_echo_notice_shipping' );
  
function bbloomer_echo_notice_shipping() {
   echo '<div class="shipping-notice woocommerce-info oregon" style="display:none">Attention Oregon Retail Customers: Due to specific state regulations, we are unable to fulfill retail orders in Oregon at this time. However, Wholesalers and Distributors are encouraged to contact us directly at <a href="mailto:wholesale@hamiltondevices.com">wholesale@hamiltondevices.com</a> for dedicated service. We apologize for any inconvenience this may cause. Notice: Due to the PACT Act, we currently cannot service orders in your state. We are actively seeking solutions and invite you to check back soon for updates.  
 </div>';
	 echo '<div class="shipping-notices woocommerce-info hawaii" style="display:none">Due to regulations set forth by the state of Hawaii effective as of January 1, 2024, we are no longer able to make sales or ship to Hawaii. Notice: Due to the PACT Act, we currently cannot service orders in your state. We are actively seeking solutions and invite you to check back soon for updates. 
 </div>';
	 echo '<div class="shipping-noticesk woocommerce-info kentucky" style="display:none">Attention Kentucky Retail Customers: Due to specific state regulations, we are unable to fulfill retail orders in Kentucky at this time. However, Wholesalers and Distributors are encouraged to contact us directly at <a href="mailto:wholesale@hamiltondevices.com">wholesale@hamiltondevices.com</a> for dedicated service. We apologize for any inconvenience this may cause. Notice: Due to the PACT Act, we currently cannot service orders in your state. We are actively seeking solutions and invite you to check back soon for updates. 
 </div>';
	 echo '<div class="shipping-noticesv woocommerce-info vermont" style="display:none">Attention Vermont Retail Customers: Due to specific state regulations, we are unable to fulfill retail orders in Vermont at this time. However, Wholesalers and Distributors are encouraged to contact us directly at <a href="mailto:wholesale@hamiltondevices.com">wholesale@hamiltondevices.com</a> for dedicated service. We apologize for any inconvenience this may cause. Notice: Due to the PACT Act, we currently cannot service orders in your state. We are actively seeking solutions and invite you to check back soon for updates. 
 </div>';
}

}
// Part 2
// Show or hide message based on billing country
  
add_action( 'woocommerce_after_checkout_form', 'bbloomer_show_notice_shipping' );
  
function bbloomer_show_notice_shipping(){
     
   wc_enqueue_js( "
  
      // Set the country code that will display the message
      var stateCode = 'OR';
  var stateCodes = 'HI';
   var stateCodesk = 'KY';
   var stateCodesv = 'VT';
      // Get country code from checkout
      selectedState = $('select#billing_state').val();
 
      // Function to toggle message
      function toggle_upsell( selectedState ) {   
         if( selectedState == stateCode ){
            $('.shipping-notice').show();
			 $('.shipping-notices').hide(); $('.shipping-noticesk').hide(); $('.shipping-noticesv').hide();
            $('#customer_details').scrollTop(100);
         }
		  else if( selectedState == stateCodes ){
            $('.shipping-notices').show(); $('.shipping-noticesk').hide(); $('.shipping-notice').hide();$('.shipping-noticesv').hide();
            $('#customer_details').scrollTop(100);
         }
		 else if( selectedState == stateCodesk ){
            $('.shipping-noticesk').show(); $('.shipping-notices').hide();$('.shipping-noticesv').hide(); $('.shipping-notice').hide();
            $('#customer_details').scrollTop(100);
         }
		  else if( selectedState == stateCodesv ){
           $('.shipping-noticesv').show(); $('.shipping-noticesk').hide(); $('.shipping-notices').hide(); $('.shipping-notice').hide();
            $('#customer_details').scrollTop(100);
         }
         else {
            $('.shipping-notice').hide();
			 $('.shipping-notices').hide(); $('.shipping-noticesk').hide();$('.shipping-noticesv').hide();
         }
      }
 
      // Call function
      toggle_upsell( selectedState );
      $('select#billing_state').change(function(){
         toggle_upsell( this.value );         
      });
  
   " );
     
}



add_filter( 'woocommerce_countries', 'wc_remove_pr_country', 10, 1 );
 
function wc_remove_pr_country ( $country ) {
   unset($country["PR"]);
   return $country; 
}
 
add_filter( 'woocommerce_states', 'wc_us_states_mods' );
 
function wc_us_states_mods ( $states ) {
 
  $states['US'] = array(
          'AL' => __( 'Alabama', 'woocommerce' ),
          'AK' => __( 'Alaska', 'woocommerce' ),
          'AZ' => __( 'Arizona', 'woocommerce' ),
          'AR' => __( 'Arkansas', 'woocommerce' ),
          'CA' => __( 'California', 'woocommerce' ),
          'CO' => __( 'Colorado', 'woocommerce' ),
          'CT' => __( 'Connecticut', 'woocommerce' ),
          'DE' => __( 'Delaware', 'woocommerce' ),
          'DC' => __( 'District Of Columbia', 'woocommerce' ),
          'FL' => __( 'Florida', 'woocommerce' ),
          'GA' => _x( 'Georgia', 'US state of Georgia', 'woocommerce' ),
          'HI' => __( 'Hawaii', 'woocommerce' ),
          'ID' => __( 'Idaho', 'woocommerce' ),
          'IL' => __( 'Illinois', 'woocommerce' ),
          'IN' => __( 'Indiana', 'woocommerce' ),
          'IA' => __( 'Iowa', 'woocommerce' ),
          'KS' => __( 'Kansas', 'woocommerce' ),
          'KY' => __( 'Kentucky', 'woocommerce' ),
          'LA' => __( 'Louisiana', 'woocommerce' ),
          'ME' => __( 'Maine', 'woocommerce' ),
          'MD' => __( 'Maryland', 'woocommerce' ),
          'MA' => __( 'Massachusetts', 'woocommerce' ),
          'MI' => __( 'Michigan', 'woocommerce' ),
          'MN' => __( 'Minnesota', 'woocommerce' ),
          'MS' => __( 'Mississippi', 'woocommerce' ),
          'MO' => __( 'Missouri', 'woocommerce' ),
          'MT' => __( 'Montana', 'woocommerce' ),
          'NE' => __( 'Nebraska', 'woocommerce' ),
          'NV' => __( 'Nevada', 'woocommerce' ),
          'NH' => __( 'New Hampshire', 'woocommerce' ),
          'NJ' => __( 'New Jersey', 'woocommerce' ),
          'NM' => __( 'New Mexico', 'woocommerce' ),
          'NY' => __( 'New York', 'woocommerce' ),
          'NC' => __( 'North Carolina', 'woocommerce' ),
          'ND' => __( 'North Dakota', 'woocommerce' ),
          'OH' => __( 'Ohio', 'woocommerce' ),
          'OK' => __( 'Oklahoma', 'woocommerce' ),
          'OR' => __( 'Oregon', 'woocommerce' ),
          'PA' => __( 'Pennsylvania', 'woocommerce' ),
          'PR' => __( 'Puerto Rico', 'woocommerce' ),
          'RI' => __( 'Rhode Island', 'woocommerce' ),
          'SC' => __( 'South Carolina', 'woocommerce' ),
          'SD' => __( 'South Dakota', 'woocommerce' ),
          'TN' => __( 'Tennessee', 'woocommerce' ),
          'TX' => __( 'Texas', 'woocommerce' ),
          'UT' => __( 'Utah', 'woocommerce' ),
          'VT' => __( 'Vermont', 'woocommerce' ),
          'VA' => __( 'Virginia', 'woocommerce' ),
          'WA' => __( 'Washington', 'woocommerce' ),
          'WV' => __( 'West Virginia', 'woocommerce' ),
          'WI' => __( 'Wisconsin', 'woocommerce' ),
          'WY' => __( 'Wyoming', 'woocommerce' ),
          'AA' => __( 'Armed Forces (AA)', 'woocommerce' ),
          'AE' => __( 'Armed Forces (AE)', 'woocommerce' ),
          'AP' => __( 'Armed Forces (AP)', 'woocommerce' ),
  );
 
  return $states;
}


function remove_page_from_query_string($query_string)
{ 
    if (isset($query_string['name']) && $query_string['name'] == 'page') {
        unset($query_string['name']);
        $query_string['paged'] = $query_string['page'];
    }      
    return $query_string;
}
add_filter('request', 'remove_page_from_query_string');


add_filter( 'wpcf7_verify_nonce', '__return_true' );

add_action( 'woocommerce_after_single_product_summary', 'review_replacing_reviews_position', 11 );
 
function review_replacing_reviews_position()
{
  comments_template();
}
 
add_filter( 'woocommerce_product_tabs', 'woo_remove_product_tabs', 98 );
 
function woo_remove_product_tabs( $tabs )
{
    unset( $tabs['reviews'] );
 
    return $tabs;
}
add_filter('woocommerce_default_catalog_orderby', 'custom_catalog_ordering_args', 10, 1);
function custom_catalog_ordering_args( $orderby )
{
    $product_category = array('cartridge','disposable','deals'); // <== HERE define your product category slug 

    // For all other archives pages
    if ( ! is_product_category($product_category)) {
        return $orderby; // <====  <====  <====  <====  <====  HERE
    }
    // For the defined product category archive page
    return 'date'; 
}
add_action('woocommerce_cart_calculate_fees', 'add_percentage_surcharge', 20, 1);

function add_percentage_surcharge($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    // Define surcharge percentage
    $surcharge_percentage = 0.02; // 2%

    // Calculate surcharge based on cart subtotal + shipping
    $subtotal = $cart->cart_contents_total;
    $shipping_total = $cart->get_shipping_total();

    // Optional: include fees in calculation
    $fees_total = 0;
    foreach ($cart->get_fees() as $fee) {
        $fees_total += $fee->amount;
    }

    // Total for surcharge calculation
    $total_for_surcharge = $subtotal + $shipping_total + $fees_total;

    $surcharge = $total_for_surcharge * $surcharge_percentage;

    // Add surcharge as a fee
    $cart->add_fee(__('Surcharge (2%)', 'woocommerce'), $surcharge, true);
}
add_filter( 'woocommerce_shipping_package_name', 'custom_woocommerce_shipping_package_name', 10, 3 );
function custom_woocommerce_shipping_package_name( $package_name, $i, $package ) {
    return 'Shipping & Handling';
}
add_action('woocommerce_before_checkout_form', 'custom_international_order_note');

function custom_international_order_note() {
    ?>
    <div id="international-legal-note" style="display:none; border: 2px solid #d93025; background-color: #fce8e6; color: #d93025; padding: 15px 20px; margin-bottom: 20px; border-radius: 5px; font-weight: 600; font-family: Arial, sans-serif; box-shadow: 0 0 5px rgba(217, 48, 37, 0.5);">
        ⚠️ <strong>Legal note —</strong> International orders may come with customs or duty fees, depending on your country’s laws. These charges are determined by your government. We kindly request that you prepare for these charges upon delivery. In the rare event a package is refused due to unpaid fees, we may only be able to offer a partial refund or may need to cover additional shipping costs. We’re happy to assist if you have any questions. Thank you for your understanding!
    </div>
    <script type="text/javascript">
    jQuery(function($){
        function toggleLegalNote() {
            let billingCountry = $('#billing_country').val() || '';
            billingCountry = billingCountry.toUpperCase();

            console.log('[DEBUG] Billing Country:', billingCountry);

            if (billingCountry && billingCountry !== 'US') {
                console.log('[DEBUG] Showing legal note');
                $('#international-legal-note').css('display', 'block');
            } else {
                console.log('[DEBUG] Hiding legal note');
                $('#international-legal-note').css('display', 'none');
            }
        }

        // Run on page load
        toggleLegalNote();

        // Run on billing country change
        $(document).on('change', '#billing_country', function() {
            toggleLegalNote();
        });

        // WooCommerce AJAX updates (if any)
        $(document.body).on('updated_checkout', function() {
            toggleLegalNote();
        });
    });
    </script>
    <?php
}
add_action('admin_head', function () {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'product') {
        $current_user = wp_get_current_user();
        $allowed_username = 'sura_dev';

        if ($current_user->user_login !== $allowed_username) {
            echo '<style>
                /* Hide Product Description */
                #postdivrich { display: none !important; }

                /* Hide Short Description */
                #postexcerpt { display: none !important; }

                /* Hide SKU Field */
                #_sku, .sku_field, ._sku_field, .form-field._sku_field { display: none !important; }

                /* Hide Regular & Sale Price Fields */
                .pricing, ._regular_price_field, ._sale_price_field { display: none !important; }

                /* Hide Yoast SEO Metabox */
                #wpseo_meta { display: none !important; }

                /* Hide Publish/Update button */
                #publish { display: none !important; }

                /* Hide Add Variation / Save Changes buttons */
                .save-variation-changes, 
                .add_variation, 
                .do_variation_action,
                .bulk_edit,
                .save_variations_button {
                    display: none !important;
                }
            </style>';
        }
		 // Hide WooCommerce Coupon fields
        if ($screen && $screen->id === 'shop_coupon') {
            echo '<style>
                /* Hide Role Restriction and Percentage Discount Cap */
                .role_restriction_field,
                .percentage_discount_cap_field {
                    display: none !important;
                }
            </style>';
        }
    }
	 if ( isset($_GET['page']) && $_GET['page'] === 'wc-reports' ) {
        ?>
        <style>
            /* Hide Gift Cards tab */
            .woo-nav-tab-wrapper .nav-tab[href*="tab=gc"] {
                display: none !important;
            }

            /* Hide Wholesale tab */
            .woo-nav-tab-wrapper .nav-tab[href*="tab=wwpp_reports"] {
                display: none !important;
            }
        </style>
        <?php
    }
});


/*add_filter('woocommerce_get_stock_html', 'add_notify_me_button_after_stock_text', 10, 2);
function add_notify_me_button_after_stock_text($html, $product) {
    if ($product && $product->get_stock_status() === 'outofstock') {
        $html .= '<div id="notify-me-wrapper" style="margin-top:10px;">
                    <button id="notify-me-btn" class="button alt" style="background:#333;color:#fff;">
                        Notify Me When Available
                    </button>
                  </div>';
    }
    return $html;
}

add_action('wp_footer', 'enqueue_notify_me_js');
function enqueue_notify_me_js() {
    ?>
    <script>
    document.addEventListener('click', function(event) {
        if (event.target && event.target.id === 'notify-me-btn') {
            var attempts = 0;
            var maxAttempts = 30; // retry for 3 seconds
            var interval = setInterval(function() {
                var teaser = document.querySelector('#omnisend-form-688790c17969e83973098b58-teaser-btn');
                if (teaser) {
                    teaser.click();
                    clearInterval(interval);
                } else {
                    attempts++;
                    if (attempts >= maxAttempts) {
                        clearInterval(interval);
                        alert('Please wait a moment and try again.');
                    }
                }
            }, 100);
        }
    });
    </script>
    <?php
}
*/

add_filter( 'wp_mail', function( $args ) {
    if ( strpos( $args['subject'], 'password changed' ) !== false ) {
        $args['to'] = 'marketing@hamiltondevices.com'; // your custom admin email
    }
    return $args;
});



add_action('admin_head', function() {
   
    if (current_user_can('developer')) {
        return; 
    }

   
    $screen = get_current_screen();

   
    if ($screen && $screen->id === 'product') {
        echo '<style>
            #publish, #save-post { 
                pointer-events: none !important; 
                opacity: 0.5 !important; 
            }
            #publish::after {
                content: " ()";
                color: red;
                font-weight: bold;
                margin-left: 5px;
            }
        </style>';
    }
});
function load_cf7_turnstile_script() {
    // Only load on pages where CF7 forms are present
    if ( function_exists( 'wpcf7_enqueue_scripts' ) ) {
        wp_enqueue_script(
            'cloudflare-turnstile',
            'https://challenges.cloudflare.com/turnstile/v0/api.js',
            array(),
            null,
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'load_cf7_turnstile_script', 5); // priority 5 to load early

// Optional: Force CF7 to wait until Turnstile is loaded
function fix_cf7_turnstile_submission() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof turnstile === 'undefined') {
            var script = document.createElement('script');
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
            script.async = true;
            script.defer = true;
            document.head.appendChild(script);
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'fix_cf7_turnstile_submission');

//add_filter( 'woocommerce_terms_is_checked_default', '__return_true' );
add_filter( 'woocommerce_admin_reports', function( $reports ) {
    foreach ( $reports as $key => $report ) {
        if ( $key !== 'custom_taxes' ) {
            unset( $reports[ $key ] );
			
        }
    }
    return $reports;
});

add_action( 'admin_init', function () {
    if (
        isset($_GET['page']) &&
        $_GET['page'] === 'wc-reports' &&
        ! isset($_GET['tab'])
    ) {
        wp_redirect( admin_url('admin.php?page=wc-reports&tab=custom_taxes') );
        exit;
    }
});

/**
 * Improve Flatsome Category Hero Banners
 * Better gradient backgrounds, hide the ugly stretched thumbnails, add subtitles
 */
add_action('wp_head', function() {
    if (!is_product_category('cartridge') && !is_product_category('disposable')) return;
    ?>
    <style>
    /* Override the Flatsome hero background — hide the stretched product thumbnail */
    .shop-page-title.featured-title .title-bg {
        background-image: none !important;
    }
    .shop-page-title.featured-title .title-bg::before {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(135deg, #b00810 0%, #d50912 30%, #E50914 60%, #c00810 100%);
        z-index: 0;
    }
    /* Subtle light accent for depth */
    .shop-page-title.featured-title .title-bg::after {
        content: '';
        position: absolute;
        top: -30%;
        right: -5%;
        width: 55%;
        height: 160%;
        background: radial-gradient(ellipse at center, rgba(255,255,255,0.06) 0%, transparent 70%);
        z-index: 1;
        pointer-events: none;
    }
    /* Subtle overlay for depth */
    .shop-page-title.featured-title .title-overlay {
        background-color: rgba(0,0,0,0.05) !important;
    }
    /* Bigger, bolder title */
    .shop-page-title h1.shop-page-title.is-xlarge,
    .shop-page-title h1.is-xlarge {
        font-size: 42px !important;
        font-weight: 800 !important;
        letter-spacing: -0.5px !important;
        text-shadow: 0 2px 15px rgba(0,0,0,0.2);
    }
    /* Add subtitle styling */
    .shop-page-title .hero-subtitle {
        font-size: 16px;
        color: rgba(255,255,255,0.8);
        font-weight: 400;
        margin-top: 10px;
        line-height: 1.6;
        max-width: 550px;
        text-shadow: 0 1px 8px rgba(0,0,0,0.15);
    }
    .page-title-inner .flex-col.flex-center { max-width: 700px; }
    /* Give the hero more breathing room */
    .shop-page-title.featured-title .page-title-inner {
        padding-top: 30px !important;
        padding-bottom: 30px !important;
    }
    </style>
    <?php
});

/**
 * Add subtitle text to category hero banners
 */
add_action('flatsome_category_title', function() {
    if (is_product_category('cartridge')) {
        echo '<p class="hero-subtitle">510-thread cartridges in four heating platforms — from economy SE to next-gen 3.0 Bio-Heating. Glass and poly bodies, all with snap-fit mouthpieces for custom branding.</p>';
    } elseif (is_product_category('disposable')) {
        echo '<p class="hero-subtitle">All-in-one disposable vaporizers with three heating platforms. Capacities from 0.3ml to 3.0ml with USB-C charging and child-lock options.</p>';
    }
}, 5);

/**
 * Cartridge Technology Selector
 * Shows a visual selector at the top of the cartridge category page
 */
add_action('woocommerce_before_shop_loop', function() {
    if (!is_product_category('cartridge')) return;
    ?>
    <style>
    /* Hide sidebar on cartridge page, make content full-width */
    .tax-product_cat.term-cartridge .shop-sidebar { display:none !important; }
    .tax-product_cat.term-cartridge .content-area { width:100% !important; max-width:100% !important; flex:0 0 100% !important; }
    .tax-product_cat.term-cartridge .row.category-page-row > .col:first-child { display:none !important; }
    .tax-product_cat.term-cartridge .row.category-page-row > .large-9 { width:100% !important; max-width:100% !important; flex:0 0 100% !important; }
    .cart-selector-wrap { max-width:1400px; margin:0 auto 40px; padding:0 15px; }
    .cart-selector-title { text-align:center; margin-bottom:25px; }
    .cart-selector-title h2 { font-size:26px; font-weight:700; color:#1a1a1a; margin:0 0 8px; }
    .cart-selector-title p { font-size:15px; color:#666; max-width:620px; margin:0 auto; line-height:1.6; }
    .cart-tech-grid { display:grid; grid-template-columns:repeat(4, 1fr); gap:14px; margin-bottom:18px; }
    @media (max-width:768px) { .cart-tech-grid { grid-template-columns:repeat(2, 1fr); } }
    .cart-tech-card {
        background:#fff; border-radius:10px; overflow:hidden; text-align:center;
        box-shadow:0 2px 12px rgba(0,0,0,0.05); border:2px solid transparent;
        transition:all 0.3s ease; text-decoration:none !important; display:block; color:inherit;
    }
    .cart-tech-card:hover {
        border-color:#E50914; box-shadow:0 6px 25px rgba(0,0,0,0.1);
        transform:translateY(-3px); text-decoration:none !important; color:inherit;
    }
    .cart-tech-card .card-badge {
        display:inline-block; padding:0; border-radius:0; background:none;
        font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.8px; margin-top:14px;
        color:#888;
    }
    .cart-tech-card .card-img-area {
        padding:30px 25px 15px; background:linear-gradient(180deg, #fafafa 0%, #fff 100%);
        min-height:260px; display:flex; align-items:center; justify-content:center;
    }
    .cart-tech-card .card-img-area img { max-height:220px; max-width:80%; object-fit:contain; }
    .card-img-duo { display:flex; align-items:flex-end; justify-content:center; gap:18px; width:100%; }
    .card-img-duo .duo-item { text-align:center; flex:1; }
    .card-img-duo .duo-item img { max-height:200px; max-width:100%; object-fit:contain; }
    .card-img-duo .duo-label { font-size:10px; color:#999; text-transform:uppercase; letter-spacing:0.5px; margin-top:6px; font-weight:600; }
    .cart-tech-card .card-content { padding:12px 15px 18px; border-top:1px solid #f0f0f0; }
    .cart-tech-card .card-content h3 { font-size:15px; font-weight:700; margin:0 0 3px; color:#1a1a1a; }
    .cart-tech-card .coil-name { font-size:11px; color:#999; margin:0 0 6px; text-transform:uppercase; letter-spacing:0.5px; }
    .cart-tech-card .card-content p { font-size:12.5px; color:#666; line-height:1.5; margin:0 0 8px; }
    .cart-tech-card .oil-compat { font-size:12px; color:#444; margin:0 0 10px; }
    .cart-tech-card .oil-compat strong { color:#1a1a1a; }
    .cart-tech-card .card-specs { display:flex; justify-content:center; gap:5px; flex-wrap:wrap; }
    .cart-tech-card .spec-tag { background:#f5f5f5; padding:2px 7px; border-radius:3px; font-size:10px; color:#555; font-weight:500; }
    .cart-tech-card .card-cta { color:#E50914; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.3px; margin-top:10px; display:inline-block; }
    .cart-compare-bar {
        background:#f8f8f8; border-radius:8px; padding:15px 22px;
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;
    }
    .cart-compare-bar p { margin:0; font-size:13px; color:#555; }
    .cart-compare-bar p strong { color:#333; }
    .cart-compare-bar .cta-btn {
        background:#E50914; color:#fff !important; padding:9px 22px; border-radius:4px;
        font-weight:600; font-size:13px; text-decoration:none !important; transition:background 0.2s; white-space:nowrap;
    }
    .cart-compare-bar .cta-btn:hover { background:#c30812; }
    </style>

    <div class="cart-selector-wrap">
        <div class="cart-selector-title">
            <h2>Choose Your Cartridge Technology</h2>
            <p>CCELL® offers four cartridge platforms, each engineered for different performance requirements and price points.</p>
        </div>

        <div class="cart-tech-grid">
            <a href="/product-category/cartridge/ccell-easy/" class="cart-tech-card">
                <span class="card-badge badge-economy">Best Value</span>
                <div class="card-img-area">
                    <div class="card-img-duo">
                        <div class="duo-item"><img src="/wp-content/uploads/2026/02/ccell-easy-se-glass.jpg" alt="CCELL Easy TH2 Glass"><div class="duo-label">TH2 Glass</div></div>
                        <div class="duo-item"><img src="/wp-content/uploads/2026/02/ccell-easy-se-etp.jpg" alt="CCELL Easy M6T Poly"><div class="duo-label">M6T Poly</div></div>
                    </div>
                </div>
                <div class="card-content">
                    <h3>CCELL Easy / SE</h3>
                    <div class="coil-name">SE Heating Coil</div>
                    <p>Reliable performance at the best price. Snap-fit and screw-on options for high-volume distillate programs.</p>
                    <div class="oil-compat">Best for: <strong>Distillates</strong></div>
                    <div class="card-specs">
                        <span class="spec-tag">Glass (TH2)</span>
                        <span class="spec-tag">Poly (M6T)</span>
                        <span class="spec-tag">Snap-Fit / Screw-On</span>
                    </div>
                    <div class="card-cta">View All 15 Products →</div>
                </div>
            </a>

            <a href="/product-category/cartridge/ccell-evo-max/" class="cart-tech-card">
                <span class="card-badge badge-premium">Premium</span>
                <div class="card-img-area">
                    <div class="card-img-duo">
                        <div class="duo-item"><img src="/wp-content/uploads/2026/02/ccell-evomax-glass.jpg" alt="CCELL EVO MAX TH2 Glass"><div class="duo-label">TH2 Glass</div></div>
                        <div class="duo-item"><img src="/wp-content/uploads/2026/02/ccell-evomax-etp.jpg" alt="CCELL EVO MAX M6T Poly"><div class="duo-label">M6T Poly</div></div>
                    </div>
                </div>
                <div class="card-content">
                    <h3>CCELL EVO MAX</h3>
                    <div class="coil-name">EVO MAX Heating Coil</div>
                    <p>Advanced coil for superior vapor quality and oil efficiency across a wide viscosity range.</p>
                    <div class="oil-compat">Best for: <strong>Distillates, Live Resin, Live Rosin</strong></div>
                    <div class="card-specs">
                        <span class="spec-tag">Glass (TH2)</span>
                        <span class="spec-tag">Poly (M6T)</span>
                        <span class="spec-tag">Snap-Fit</span>
                    </div>
                    <div class="card-cta">View All 23 Products →</div>
                </div>
            </a>

            <a href="/product-category/cartridge/ccell-ceramic-evo-max/" class="cart-tech-card">
                <span class="card-badge badge-ceramic">Ceramic Body</span>
                <div class="card-img-area">
                    <img src="/wp-content/uploads/2026/02/ccell-evomax-ceramic.jpg" alt="CCELL Ceramic EVO MAX Kera">
                </div>
                <div class="card-content">
                    <h3>Ceramic EVO MAX</h3>
                    <div class="coil-name">EVO MAX Heating Coil</div>
                    <p>Full ceramic body — zero metal contact with oil for the purest flavor.</p>
                    <div class="oil-compat">Best for: <strong>Distillates, Live Resin, Live Rosin</strong></div>
                    <div class="card-specs">
                        <span class="spec-tag">Ceramic Body</span>
                        <span class="spec-tag">Snap-Fit</span>
                    </div>
                    <div class="card-cta">View Product →</div>
                </div>
            </a>

            <a href="/product-category/cartridge/ccell-3-postless/" class="cart-tech-card">
                <span class="card-badge badge-nextgen">Next-Gen</span>
                <div class="card-img-area">
                    <img src="/wp-content/uploads/2026/02/ccell-postless-vita.png" alt="CCELL 3.0 Postless Vita">
                </div>
                <div class="card-content">
                    <h3>CCELL 3.0 Postless</h3>
                    <div class="coil-name">CCELL 3.0 Technology</div>
                    <p>Postless design for the cleanest oil path and easiest filling.</p>
                    <div class="oil-compat">Best for: <strong>Distillates, Liquid Diamonds</strong></div>
                    <div class="card-specs">
                        <span class="spec-tag">Postless</span>
                        <span class="spec-tag">Klean Series</span>
                    </div>
                    <div class="card-cta">View All 3 Products →</div>
                </div>
            </a>
        </div>

        <div class="cart-compare-bar">
            <p><strong>Not sure which technology fits your brand?</strong> Our team can help match the right cartridge to your formulation and program size.</p>
            <a href="/request-samples/" class="cta-btn">Request Samples</a>
        </div>
    </div>
    <?php
}, 5);

/**
 * AIO Disposables Technology Selector
 * Shows a visual selector at the top of the disposables category page
 */
add_action('woocommerce_before_shop_loop', function() {
    if (!is_product_category('disposable')) return;
    ?>
    <style>
    /* Hide sidebar on disposables page, make content full-width */
    .tax-product_cat.term-disposable .shop-sidebar { display:none !important; }
    .tax-product_cat.term-disposable .content-area { width:100% !important; max-width:100% !important; flex:0 0 100% !important; }
    .tax-product_cat.term-disposable .row.category-page-row > .col:first-child { display:none !important; }
    .tax-product_cat.term-disposable .row.category-page-row > .large-9 { width:100% !important; max-width:100% !important; flex:0 0 100% !important; }
    .aio-selector-wrap { max-width:1400px; margin:0 auto 40px; padding:0 15px; }
    .aio-selector-title { text-align:center; margin-bottom:25px; }
    .aio-selector-title h2 { font-size:26px; font-weight:700; color:#1a1a1a; margin:0 0 8px; }
    .aio-selector-title p { font-size:15px; color:#666; max-width:650px; margin:0 auto; line-height:1.6; }
    .aio-tech-grid { display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:18px; }
    @media (max-width:768px) { .aio-tech-grid { grid-template-columns:1fr; } }
    .aio-tech-card {
        background:#fff; border-radius:10px; overflow:hidden; text-align:center;
        box-shadow:0 2px 12px rgba(0,0,0,0.05); border:2px solid transparent;
        transition:all 0.3s ease; text-decoration:none !important; display:block; color:inherit;
    }
    .aio-tech-card:hover {
        border-color:#E50914; box-shadow:0 6px 25px rgba(0,0,0,0.1);
        transform:translateY(-3px); text-decoration:none !important; color:inherit;
    }
    .aio-tech-card .card-badge {
        display:inline-block; padding:0; border-radius:0; background:none;
        font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:0.8px; margin-top:14px;
        color:#888;
    }
    .aio-tech-card .card-img-area {
        padding:30px 25px 15px; background:linear-gradient(180deg, #fafafa 0%, #fff 100%);
        min-height:260px; display:flex; align-items:center; justify-content:center;
    }
    .aio-tech-card .card-img-area img { max-height:220px; max-width:60%; object-fit:contain; }
    .aio-tech-card .card-img-duo { display:flex; align-items:flex-end; justify-content:center; gap:18px; width:100%; }
    .aio-tech-card .card-img-duo .duo-item { text-align:center; flex:1; }
    .aio-tech-card .card-img-duo .duo-item img { max-height:200px; max-width:100%; object-fit:contain; }
    .aio-tech-card .card-img-duo .duo-label { font-size:10px; color:#999; text-transform:uppercase; letter-spacing:0.3px; margin-top:6px; font-weight:600; }
    .aio-tech-card .card-content { padding:16px 20px 22px; border-top:1px solid #f0f0f0; }
    .aio-tech-card .card-content h3 { font-size:18px; font-weight:700; margin:0 0 4px; color:#1a1a1a; }
    .aio-tech-card .coil-name { font-size:11px; color:#999; margin:0 0 10px; text-transform:uppercase; letter-spacing:0.5px; }
    .aio-tech-card .card-content p { font-size:13.5px; color:#666; line-height:1.5; margin:0 0 12px; }
    .aio-tech-card .oil-compat { font-size:12.5px; color:#444; margin:0 0 12px; }
    .aio-tech-card .oil-compat strong { color:#1a1a1a; }
    .aio-tech-card .card-specs { display:flex; justify-content:center; gap:6px; flex-wrap:wrap; }
    .aio-tech-card .spec-tag { background:#f5f5f5; padding:3px 8px; border-radius:3px; font-size:10px; color:#555; font-weight:500; }
    .aio-tech-card .card-cta { color:#E50914; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.3px; margin-top:12px; display:inline-block; }
    .aio-compare-bar {
        background:#f8f8f8; border-radius:8px; padding:15px 22px;
        display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;
    }
    .aio-compare-bar p { margin:0; font-size:13px; color:#555; }
    .aio-compare-bar p strong { color:#333; }
    .aio-compare-bar .cta-btn {
        background:#E50914; color:#fff !important; padding:9px 22px; border-radius:4px;
        font-weight:600; font-size:13px; text-decoration:none !important; transition:background 0.2s; white-space:nowrap;
    }
    .aio-compare-bar .cta-btn:hover { background:#c30812; }
    </style>

    <div class="aio-selector-wrap">
        <div class="aio-selector-title">
            <h2>Choose Your AIO Technology</h2>
            <p>CCELL® all-in-one disposables come in three heating platforms, each designed for different oil types and performance requirements.</p>
        </div>

        <div class="aio-tech-grid">
            <a href="/product-category/disposable/aio-se-standard/" class="aio-tech-card">
                <span class="card-badge">Best Value</span>
                <div class="card-img-area">
                    <div class="card-img-duo">
                        <div class="duo-item"><img src="/wp-content/uploads/2026/02/ccell-aio-easybar.jpg" alt="CCELL Easy Bar"><div class="duo-label">Easy Bar</div></div>
                        <div class="duo-item"><img src="/wp-content/uploads/2019/07/ds0103.png" alt="CCELL DS01 Pen"><div class="duo-label">DS01 Pen</div></div>
                    </div>
                </div>
                <div class="card-content">
                    <h3>SE Standard</h3>
                    <div class="coil-name">SE Ceramic Heating</div>
                    <p>Proven ceramic coil technology at the best price. Reliable performance for distillate programs of any size.</p>
                    <div class="oil-compat">Best for: <strong>Distillates</strong></div>
                    <div class="card-specs">
                        <span class="spec-tag">Distillates</span>
                        <span class="spec-tag">0.3–2.0ml</span>
                        <span class="spec-tag">USB-C</span>
                    </div>
                    <div class="card-cta">View All 30 Products →</div>
                </div>
            </a>

            <a href="/product-category/disposable/aio-evo-max/" class="aio-tech-card">
                <span class="card-badge">Premium</span>
                <div class="card-img-area">
                    <div class="card-img-duo">
                        <div class="duo-item"><img src="/wp-content/uploads/2026/02/ccell-aio-tank.jpg" alt="CCELL Tank"><div class="duo-label">Tank</div></div>
                        <div class="duo-item"><img src="/wp-content/uploads/2026/02/ccell-aio-vocapromax.jpg" alt="CCELL Voca Pro Max"><div class="duo-label">Voca Pro Max</div></div>
                    </div>
                </div>
                <div class="card-content">
                    <h3>EVO MAX</h3>
                    <div class="coil-name">EVO MAX Heating</div>
                    <p>Oversized ceramic element for all oil types. Variable voltage, preheat, and child-lock features built in.</p>
                    <div class="oil-compat">Best for: <strong>Distillates, Live Resin, Live Rosin</strong></div>
                    <div class="card-specs">
                        <span class="spec-tag">All Oils</span>
                        <span class="spec-tag">0.5–3.0ml</span>
                        <span class="spec-tag">Variable Voltage</span>
                    </div>
                    <div class="card-cta">View All 5 Products →</div>
                </div>
            </a>

            <a href="/product-category/disposable/aio-3-bio-heating/" class="aio-tech-card">
                <span class="card-badge">Next-Gen</span>
                <div class="card-img-area">
                    <div class="card-img-duo">
                        <div class="duo-item"><img src="/wp-content/uploads/2026/02/ccell-aio-gembar.jpg" alt="CCELL GemBar"><div class="duo-label">GemBar</div></div>
                        <div class="duo-item"><img src="/wp-content/uploads/2026/02/ccell-aio-mixjoy.jpg" alt="CCELL MixJoy"><div class="duo-label">MixJoy</div></div>
                    </div>
                </div>
                <div class="card-content">
                    <h3>3.0 Bio-Heating</h3>
                    <div class="coil-name">VeinMesh Technology</div>
                    <p>Postless design with biomimetic heating. 30% lower peak temp preserves terpenes for the purest flavor profile.</p>
                    <div class="oil-compat">Best for: <strong>Distillates, Liquid Diamonds</strong></div>
                    <div class="card-specs">
                        <span class="spec-tag">All Oils</span>
                        <span class="spec-tag">1.0–2.0ml</span>
                        <span class="spec-tag">Postless</span>
                    </div>
                    <div class="card-cta">View All 5 Products →</div>
                </div>
            </a>
        </div>

        <div class="aio-compare-bar">
            <p><strong>Need help choosing the right AIO for your brand?</strong> Our team can match the right device to your formulation, volume, and compliance requirements.</p>
            <a href="/request-samples/" class="cta-btn">Request Samples</a>
        </div>
    </div>
    <?php
}, 5);

/**
 * CCELL Heating Technology Page
 * Renders technology comparison content on the heating-technology page
 */
add_filter('the_content', function($content) {
    if (!is_page('ccell-heating-technology')) return $content;
    ob_start();
    ?>
    <style>
    .tech-page-wrap { max-width:1200px; margin:0 auto; }
    .tech-hero { text-align:center; padding:0 20px 50px; }
    .tech-hero h1 { font-size:36px; font-weight:800; color:#1a1a1a; margin:0 0 15px; }
    .tech-hero .hero-sub { font-size:17px; color:#555; max-width:700px; margin:0 auto 25px; line-height:1.7; }
    .tech-hero .hero-note { font-size:14px; color:#888; max-width:600px; margin:0 auto; line-height:1.6; }

    .tech-jump-nav { display:flex; justify-content:center; gap:10px; flex-wrap:wrap; margin-bottom:50px; }
    .tech-jump-nav a {
        padding:10px 20px; border-radius:6px; font-size:13px; font-weight:600; text-transform:uppercase;
        letter-spacing:0.5px; text-decoration:none; border:2px solid #e0e0e0; color:#555; transition:all 0.2s;
    }
    .tech-jump-nav a:hover { border-color:#E50914; color:#E50914; }

    .tech-section {
        display:grid; grid-template-columns:1fr 1fr; gap:50px; align-items:center;
        padding:50px 0; border-top:1px solid #eee;
    }
    .tech-section:nth-child(even) .tech-img-col { order:-1; }
    @media (max-width:768px) {
        .tech-section { grid-template-columns:1fr; gap:30px; padding:35px 0; }
        .tech-section:nth-child(even) .tech-img-col { order:0; }
    }
    .tech-img-col { text-align:center; }
    .tech-img-col img { max-width:85%; max-height:350px; object-fit:contain; }
    .tech-text-col .tech-tier {
        display:inline-block; font-size:11px; font-weight:600; text-transform:uppercase;
        letter-spacing:0.8px; color:#888; margin-bottom:8px;
    }
    .tech-text-col h2 { font-size:28px; font-weight:800; color:#1a1a1a; margin:0 0 5px; }
    .tech-text-col .tech-tagline { font-size:16px; color:#E50914; font-weight:600; margin:0 0 15px; }
    .tech-text-col p { font-size:14.5px; color:#555; line-height:1.7; margin:0 0 18px; }

    .tech-specs { display:grid; grid-template-columns:1fr 1fr; gap:10px 20px; margin-bottom:20px; }
    .tech-spec-item { display:flex; align-items:flex-start; gap:8px; }
    .tech-spec-item .spec-icon { color:#E50914; font-weight:700; font-size:14px; margin-top:1px; flex-shrink:0; }
    .tech-spec-item .spec-text { font-size:13px; color:#444; line-height:1.4; }
    .tech-spec-item .spec-label { font-weight:600; display:block; font-size:11px; text-transform:uppercase; letter-spacing:0.3px; color:#999; }

    .tech-oil-compat { margin-bottom:20px; }
    .tech-oil-compat h4 { font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.5px; color:#999; margin:0 0 8px; }
    .tech-oil-tags { display:flex; gap:6px; flex-wrap:wrap; }
    .tech-oil-tags .oil-tag { padding:4px 10px; border-radius:4px; font-size:11px; font-weight:600; }
    .tech-oil-tags .oil-tag.primary { background:#e8f5e9; color:#2e7d32; }
    .tech-oil-tags .oil-tag.secondary { background:#fff3e0; color:#e65100; }
    .tech-oil-tags .oil-tag.neutral { background:#f5f5f5; color:#666; }

    .tech-cta-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:5px; }
    .tech-cta-row a {
        padding:10px 22px; border-radius:5px; font-size:13px; font-weight:600;
        text-decoration:none; transition:all 0.2s;
    }
    .tech-cta-row .btn-primary { background:#E50914; color:#fff; }
    .tech-cta-row .btn-primary:hover { background:#c30812; }
    .tech-cta-row .btn-secondary { background:#f5f5f5; color:#333; border:1px solid #ddd; }
    .tech-cta-row .btn-secondary:hover { background:#eee; }

    .tech-compare-section { padding:60px 0 40px; border-top:1px solid #eee; }
    .tech-compare-section h2 { text-align:center; font-size:26px; font-weight:800; color:#1a1a1a; margin:0 0 8px; }
    .tech-compare-section > p { text-align:center; font-size:14px; color:#888; margin:0 0 30px; }
    .tech-compare-table { width:100%; border-collapse:collapse; font-size:13px; }
    .tech-compare-table thead th {
        background:#1a1a1a; color:#fff; padding:12px 15px; text-align:center; font-weight:600;
        font-size:12px; text-transform:uppercase; letter-spacing:0.5px;
    }
    .tech-compare-table thead th:first-child { text-align:left; border-radius:6px 0 0 0; }
    .tech-compare-table thead th:last-child { border-radius:0 6px 0 0; }
    .tech-compare-table tbody td { padding:11px 15px; border-bottom:1px solid #eee; text-align:center; color:#444; }
    .tech-compare-table tbody td:first-child { text-align:left; font-weight:600; color:#333; }
    .tech-compare-table tbody tr:hover { background:#fafafa; }
    .tech-compare-table .check { color:#2e7d32; font-weight:700; }
    .tech-compare-table .dash { color:#ccc; }
    @media (max-width:768px) {
        .tech-compare-table { font-size:11px; }
        .tech-compare-table thead th, .tech-compare-table tbody td { padding:8px 6px; }
    }

    .tech-bottom-cta {
        text-align:center; background:#f8f8f8; border-radius:10px;
        padding:40px 30px; margin:40px 0 0;
    }
    .tech-bottom-cta h3 { font-size:22px; font-weight:700; color:#1a1a1a; margin:0 0 10px; }
    .tech-bottom-cta p { font-size:14px; color:#666; max-width:500px; margin:0 auto 20px; line-height:1.6; }
    .tech-bottom-cta a {
        display:inline-block; background:#E50914; color:#fff; padding:12px 30px; border-radius:5px;
        font-weight:600; font-size:14px; text-decoration:none; transition:background 0.2s;
    }
    .tech-bottom-cta a:hover { background:#c30812; }
    </style>

    <div class="tech-page-wrap">
        <div class="tech-hero">
            <h1>CCELL® Heating Technology</h1>
            <p class="hero-sub">Every CCELL® device is powered by a ceramic heating element — but not all ceramics are the same. Each platform is engineered for specific oil types, viscosities, and performance goals.</p>
            <p class="hero-note">As an authorized CCELL distributor, Hamilton Devices carries every heating platform so you can match the right hardware to your formulation.</p>
        </div>

        <div class="tech-jump-nav">
            <a href="#tech-se">SE</a>
            <a href="#tech-evo">EVO</a>
            <a href="#tech-evo-max">EVO MAX</a>
            <a href="#tech-3bio">CCELL 3.0</a>
            <a href="#tech-hero">HeRo</a>
        </div>

        <!-- SE -->
        <div class="tech-section" id="tech-se">
            <div class="tech-text-col">
                <span class="tech-tier">Best Value</span>
                <h2>SE Atomizer</h2>
                <div class="tech-tagline">Reliable &amp; Consistent</div>
                <p>The original CCELL ceramic formulation that set the industry standard. SE uses a microporous ceramic core with thinner walls for faster heating — delivering smooth, even vapor with no harsh hits from first puff to last.</p>
                <p>SE is the most cost-effective CCELL platform, making it the go-to choice for high-volume distillate programs where reliability and price matter most.</p>
                <div class="tech-specs">
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Heating Element</span>Original CCELL ceramic</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Viscosity Range</span>10,000 – 700,000 cP</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Available In</span>Cartridges &amp; AIO Disposables</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Best For</span>Distillates</div>
                    </div>
                </div>
                <div class="tech-oil-compat">
                    <h4>Oil Compatibility</h4>
                    <div class="tech-oil-tags">
                        <span class="oil-tag primary">Distillate</span>
                    </div>
                </div>
                <div class="tech-cta-row">
                    <a href="/product-category/cartridge/ccell-easy/" class="btn-primary">SE Cartridges</a>
                    <a href="/product-category/disposable/aio-se-standard/" class="btn-secondary">SE Disposables</a>
                </div>
            </div>
            <div class="tech-img-col">
                <img src="/wp-content/uploads/2026/02/ccell-atomizer-se.png" alt="CCELL SE Atomizer">
            </div>
        </div>

        <!-- EVO -->
        <div class="tech-section" id="tech-evo">
            <div class="tech-text-col">
                <span class="tech-tier">Flavor-Forward</span>
                <h2>EVO Atomizer</h2>
                <div class="tech-tagline">Efficient &amp; Flavor-Forward</div>
                <p>EVO takes the SE foundation and refines it with a proprietary pore distribution optimized for thicker oils. Thinner ceramic walls heat faster and more evenly, bringing out the natural flavor profiles that cannabis connoisseurs demand.</p>
                <p>EVO is the step up from SE for brands working with thicker distillates and live resins that need better flavor extraction without the full cost of EVO MAX.</p>
                <div class="tech-specs">
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Heating Element</span>EVO ceramic core</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Viscosity Range</span>20,000 – 700,000 cP</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Available In</span>Cartridges</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Best For</span>Thick distillates &amp; live resins</div>
                    </div>
                </div>
                <div class="tech-oil-compat">
                    <h4>Oil Compatibility</h4>
                    <div class="tech-oil-tags">
                        <span class="oil-tag primary">Distillate</span>
                        <span class="oil-tag primary">Live Resin</span>
                    </div>
                </div>
                <div class="tech-cta-row">
                    <a href="/product-category/cartridge/ccell-evo-max/" class="btn-primary">EVO Cartridges</a>
                </div>
            </div>
            <div class="tech-img-col">
                <img src="/wp-content/uploads/2026/02/ccell-atomizer-evo.png" alt="CCELL EVO Atomizer">
            </div>
        </div>

        <!-- EVO MAX -->
        <div class="tech-section" id="tech-evo-max">
            <div class="tech-text-col">
                <span class="tech-tier">Premium</span>
                <h2>EVO MAX Atomizer</h2>
                <div class="tech-tagline">Loudest Hits, Every Oil</div>
                <p>EVO MAX features an oversized ceramic heating element with scientifically optimized pore distribution that handles everything from thin distillates to thick live rosins and liquid diamonds. The larger surface area produces denser clouds and better flavor extraction across the full viscosity spectrum.</p>
                <p>Activates on the very first puff — no break-in period. With variable voltage and preheat capabilities in AIO formats, EVO MAX gives brands the flexibility to dial in the perfect experience for any formulation.</p>
                <div class="tech-specs">
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Heating Element</span>Oversized EVO MAX ceramic</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Viscosity Range</span>10,000 – 2,000,000 cP</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Available In</span>Cartridges &amp; AIO Disposables</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Best For</span>All oil types</div>
                    </div>
                </div>
                <div class="tech-oil-compat">
                    <h4>Oil Compatibility</h4>
                    <div class="tech-oil-tags">
                        <span class="oil-tag primary">Distillate</span>
                        <span class="oil-tag primary">Live Resin</span>
                        <span class="oil-tag primary">Live Rosin</span>
                        <span class="oil-tag primary">Liquid Diamonds</span>
                    </div>
                </div>
                <div class="tech-cta-row">
                    <a href="/product-category/cartridge/ccell-evo-max/" class="btn-primary">EVO MAX Cartridges</a>
                    <a href="/product-category/disposable/aio-evo-max/" class="btn-secondary">EVO MAX Disposables</a>
                </div>
            </div>
            <div class="tech-img-col">
                <img src="/wp-content/uploads/2026/02/ccell-atomizer-evomax.png" alt="CCELL EVO MAX Atomizer">
            </div>
        </div>

        <!-- CCELL 3.0 Bio-Heating -->
        <div class="tech-section" id="tech-3bio">
            <div class="tech-text-col">
                <span class="tech-tier">Next-Gen</span>
                <h2>CCELL 3.0 Bio-Heating</h2>
                <div class="tech-tagline">Ultra-Low Temperature, Maximum Flavor</div>
                <p>CCELL 3.0 introduces VeinMesh technology — a biomimetic heating element inspired by the vein structure of cannabis leaves. Combined with a Stomata Core that has 10x more consistent micropores and is 100% cotton-free, this platform achieves atomization temperatures 30% lower than conventional ceramic coils.</p>
                <p>The result: delicate terpene profiles that other platforms burn off are preserved intact. Combined with a postless cartridge design for the cleanest oil path and easiest filling, 3.0 represents the most advanced vaporization technology available.</p>
                <div class="tech-specs">
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Heating Element</span>VeinMesh biomimetic</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Viscosity Range</span>700,000 – 6,000,000 cP</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Available In</span>Cartridges &amp; AIO Disposables</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Design</span>Postless, Stomata Core, cotton-free</div>
                    </div>
                </div>
                <div class="tech-oil-compat">
                    <h4>Oil Compatibility</h4>
                    <div class="tech-oil-tags">
                        <span class="oil-tag primary">Distillate</span>
                        <span class="oil-tag primary">Liquid Diamonds</span>
                    </div>
                </div>
                <div class="tech-cta-row">
                    <a href="/product-category/cartridge/ccell-3-postless/" class="btn-primary">3.0 Cartridges</a>
                    <a href="/product-category/disposable/aio-3-bio-heating/" class="btn-secondary">3.0 Disposables</a>
                </div>
            </div>
            <div class="tech-img-col">
                <img src="/wp-content/uploads/2026/02/ccell-atomizer-3.0.png" alt="CCELL 3.0 Bio-Heating Atomizer">
            </div>
        </div>

        <!-- HeRo -->
        <div class="tech-section" id="tech-hero">
            <div class="tech-text-col">
                <span class="tech-tier">Solventless Specialist</span>
                <h2>HeRo Atomizer</h2>
                <div class="tech-tagline">Designed for Live Rosins</div>
                <p>HeRo is purpose-built for the solventless market. Its low-temperature ceramic heating element with thinner walls preserves the full terpene and flavonoid profiles that make live rosin extracts special — without burning off the delicate compounds.</p>
                <p>If your brand specializes in live rosin or solventless concentrates, HeRo is the only CCELL platform engineered specifically for your formulation.</p>
                <div class="tech-specs">
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Heating Element</span>Low-temp ceramic</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Viscosity Range</span>10,000 – 500,000 cP</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Available In</span>AIO Disposables (Rosin Bar)</div>
                    </div>
                    <div class="tech-spec-item">
                        <span class="spec-icon">●</span>
                        <div class="spec-text"><span class="spec-label">Best For</span>Live rosins &amp; solventless</div>
                    </div>
                </div>
                <div class="tech-oil-compat">
                    <h4>Oil Compatibility</h4>
                    <div class="tech-oil-tags">
                        <span class="oil-tag primary">Live Rosin</span>
                    </div>
                </div>
                <div class="tech-cta-row">
                    <a href="/product-category/disposable/" class="btn-primary">Shop HeRo Devices</a>
                </div>
            </div>
            <div class="tech-img-col">
                <img src="/wp-content/uploads/2026/02/ccell-hero-atomizer.png" alt="CCELL HeRo Atomizer">
            </div>
        </div>

        <!-- Comparison Table -->
        <div class="tech-compare-section">
            <h2>Compare Heating Platforms</h2>
            <p>Side-by-side comparison of every CCELL heating technology we carry.</p>
            <table class="tech-compare-table">
                <thead>
                    <tr>
                        <th>Feature</th>
                        <th>SE</th>
                        <th>EVO</th>
                        <th>EVO MAX</th>
                        <th>CCELL 3.0</th>
                        <th>HeRo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Heating Element</td>
                        <td>Ceramic</td>
                        <td>EVO ceramic</td>
                        <td>Oversized ceramic</td>
                        <td>VeinMesh</td>
                        <td>Low-temp ceramic</td>
                    </tr>
                    <tr>
                        <td>Distillate</td>
                        <td class="check">✓</td>
                        <td class="check">✓</td>
                        <td class="check">✓</td>
                        <td class="check">✓</td>
                        <td class="dash">—</td>
                    </tr>
                    <tr>
                        <td>Live Resin</td>
                        <td class="dash">—</td>
                        <td class="check">✓</td>
                        <td class="check">✓</td>
                        <td class="dash">—</td>
                        <td class="dash">—</td>
                    </tr>
                    <tr>
                        <td>Live Rosin</td>
                        <td class="dash">—</td>
                        <td class="dash">—</td>
                        <td class="check">✓</td>
                        <td class="dash">—</td>
                        <td class="check">✓</td>
                    </tr>
                    <tr>
                        <td>Liquid Diamonds</td>
                        <td class="dash">—</td>
                        <td class="dash">—</td>
                        <td class="check">✓</td>
                        <td class="check">✓</td>
                        <td class="dash">—</td>
                    </tr>
                    <tr>
                        <td>Viscosity Range</td>
                        <td>10K–700K cP</td>
                        <td>20K–700K cP</td>
                        <td>10K–2M cP</td>
                        <td>700K–6M cP</td>
                        <td>10K–500K cP</td>
                    </tr>
                    <tr>
                        <td>Cartridges</td>
                        <td class="check">✓</td>
                        <td class="check">✓</td>
                        <td class="check">✓</td>
                        <td class="check">✓</td>
                        <td class="dash">—</td>
                    </tr>
                    <tr>
                        <td>AIO Disposables</td>
                        <td class="check">✓</td>
                        <td class="dash">—</td>
                        <td class="check">✓</td>
                        <td class="check">✓</td>
                        <td class="check">✓</td>
                    </tr>
                    <tr>
                        <td>Postless Design</td>
                        <td class="dash">—</td>
                        <td class="dash">—</td>
                        <td class="dash">—</td>
                        <td class="check">✓</td>
                        <td class="dash">—</td>
                    </tr>
                    <tr>
                        <td>Price Tier</td>
                        <td>$</td>
                        <td>$$</td>
                        <td>$$$</td>
                        <td>$$$$</td>
                        <td>$$$</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Bottom CTA -->
        <div class="tech-bottom-cta">
            <h3>Not Sure Which Technology Is Right?</h3>
            <p>Tell us about your oil formulation and program goals. Our team will recommend the best CCELL platform for your brand.</p>
            <a href="/request-samples/">Request Samples</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

/**
 * Product Catalog Page (shop-hamilton, ID 62)
 * CCELL-focused layout with dynamic category data
 */
add_filter('the_content', function($content) {
    if (!is_page('shop-hamilton')) return $content;

    // Pre-fetch all 9 category terms by slug (portable across environments)
    $cat_slugs = array('cartridge', 'disposable', 'batteries', 'pod-systems', 'vaporizers', 'kits', 'package', 'pipes', 'packaging');
    $cats = array();
    foreach ($cat_slugs as $slug) {
        $term = get_term_by('slug', $slug, 'product_cat');
        if ($term && !is_wp_error($term)) {
            $thumb_id = get_term_meta($term->term_id, 'thumbnail_id', true);
            $cats[$slug] = array(
                'name'  => $term->name,
                'count' => $term->count,
                'url'   => get_term_link($term),
                'img'   => $thumb_id ? wp_get_attachment_url($thumb_id) : '',
            );
        }
    }

    ob_start();
    ?>
    <style>
    .pc-wrap{max-width:1200px;margin:0 auto;padding:0 20px}
    .pc-header{text-align:center;padding:0 0 45px}
    .pc-header h1{font-size:36px;font-weight:800;color:#1a1a1a;margin:0 0 12px}
    .pc-header p{font-size:17px;color:#555;max-width:680px;margin:0 auto;line-height:1.7}
    .pc-hero-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px}
    .pc-hero-card{position:relative;display:flex;flex-direction:column;border:2px solid #e8e8e8;border-radius:12px;overflow:hidden;text-decoration:none;color:inherit;transition:border-color .25s,box-shadow .25s,transform .25s;background:#fff}
    .pc-hero-card:hover{border-color:#E50914;transform:translateY(-4px);box-shadow:0 12px 35px rgba(0,0,0,.12)}
    .pc-hero-img{height:280px;display:flex;align-items:center;justify-content:center;background:#f8f8f8;padding:25px}
    .pc-hero-img img{max-height:230px;max-width:90%;object-fit:contain}
    .pc-hero-body{padding:28px 28px 24px;flex:1;display:flex;flex-direction:column}
    .pc-label{display:inline-block;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.8px;color:#E50914;background:rgba(229,9,20,.08);padding:4px 10px;border-radius:4px;margin-bottom:12px;width:fit-content}
    .pc-hero-body h2{font-size:24px;font-weight:700;color:#1a1a1a;margin:0 0 10px}
    .pc-hero-body .pc-desc{font-size:14.5px;color:#555;line-height:1.65;margin:0 0 15px;flex:1}
    .pc-count{font-size:13px;color:#888;margin:0 0 18px}
    .pc-btn{display:inline-block;background:#E50914;color:#fff;font-size:14px;font-weight:600;padding:12px 28px;border-radius:6px;text-decoration:none;text-align:center;transition:background .2s;width:fit-content}
    .pc-btn:hover{background:#c30812;color:#fff}
    .pc-mid-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:45px}
    .pc-mid-card{display:flex;align-items:center;gap:24px;border:2px solid #e8e8e8;border-radius:12px;padding:24px;text-decoration:none;color:inherit;transition:border-color .25s,box-shadow .25s,transform .25s;background:#fff}
    .pc-mid-card:hover{border-color:#E50914;transform:translateY(-3px);box-shadow:0 8px 25px rgba(0,0,0,.1)}
    .pc-mid-img{width:120px;height:120px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:#f8f8f8;border-radius:8px;overflow:hidden}
    .pc-mid-img img{max-width:100%;max-height:100%;object-fit:contain}
    .pc-mid-body h3{font-size:20px;font-weight:700;color:#1a1a1a;margin:0 0 8px}
    .pc-mid-body .pc-desc{font-size:14px;color:#555;line-height:1.6;margin:0 0 8px}
    .pc-mid-body .pc-count{margin:0}
    .pc-tech-bar{display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,#1a1a1a,#2d2d2d);border-radius:12px;padding:28px 35px;margin-bottom:50px;color:#fff;flex-wrap:wrap;gap:15px}
    .pc-tech-bar p{font-size:16px;font-weight:600;margin:0;flex:1}
    .pc-tech-bar a{display:inline-block;background:#E50914;color:#fff;font-size:14px;font-weight:600;padding:12px 28px;border-radius:6px;text-decoration:none;transition:background .2s;white-space:nowrap}
    .pc-tech-bar a:hover{background:#c30812;color:#fff}
    .pc-divider{border:0;border-top:2px solid #eee;margin:0 0 40px}
    .pc-section-title{text-align:center;margin-bottom:30px}
    .pc-section-title h2{font-size:26px;font-weight:800;color:#1a1a1a;margin:0 0 8px}
    .pc-section-title p{font-size:15px;color:#888;margin:0}
    .pc-add-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:20px;margin-bottom:50px}
    .pc-add-card{display:flex;flex-direction:column;align-items:center;border:2px solid #e8e8e8;border-radius:10px;padding:20px 15px;text-decoration:none;color:inherit;transition:border-color .25s,box-shadow .25s,transform .25s;background:#fff;text-align:center}
    .pc-add-card:hover{border-color:#E50914;transform:translateY(-3px);box-shadow:0 8px 25px rgba(0,0,0,.1)}
    .pc-add-img{width:90px;height:90px;display:flex;align-items:center;justify-content:center;background:#f8f8f8;border-radius:8px;margin-bottom:14px;overflow:hidden}
    .pc-add-img img{max-width:100%;max-height:100%;object-fit:contain}
    .pc-add-card h4{font-size:15px;font-weight:700;color:#1a1a1a;margin:0 0 5px}
    .pc-add-card .pc-count{font-size:12px;margin:0}
    .pc-bottom-cta{text-align:center;background:#f9f9f9;border-radius:12px;padding:45px 30px;margin-bottom:20px}
    .pc-bottom-cta h3{font-size:24px;font-weight:800;color:#1a1a1a;margin:0 0 10px}
    .pc-bottom-cta p{font-size:15px;color:#555;margin:0 0 22px;max-width:550px;margin-left:auto;margin-right:auto}
    .pc-bottom-cta .pc-btn{font-size:15px;padding:14px 35px}
    @media(max-width:1024px){
        .pc-add-grid{grid-template-columns:repeat(3,1fr)}
    }
    @media(max-width:768px){
        .pc-hero-grid{grid-template-columns:1fr}
        .pc-mid-grid{grid-template-columns:1fr}
        .pc-tech-bar{flex-direction:column;text-align:center}
        .pc-add-grid{grid-template-columns:repeat(2,1fr)}
    }
    @media(max-width:480px){
        .pc-header h1{font-size:28px}
        .pc-hero-img{height:200px}
        .pc-mid-card{flex-direction:column;text-align:center}
        .pc-mid-img{width:100px;height:100px}
        .pc-add-grid{grid-template-columns:1fr 1fr}
        .pc-add-img{width:70px;height:70px}
    }
    </style>

    <div class="pc-wrap">

        <!-- Page Header -->
        <div class="pc-header">
            <h1>Product Catalog</h1>
            <p>Authorized CCELL distributor with wholesale pricing on cartridges, disposables, batteries, and accessories.</p>
        </div>

        <!-- CCELL Products: Hero Cards -->
        <div class="pc-hero-grid">
            <?php if (isset($cats['cartridge'])): ?>
            <a href="<?php echo esc_url($cats['cartridge']['url']); ?>" class="pc-hero-card">
                <div class="pc-hero-img">
                    <img src="<?php echo esc_url(content_url('/uploads/2026/02/ccell-evomax-glass.jpg')); ?>" alt="CCELL Cartridges">
                </div>
                <div class="pc-hero-body">
                    <span class="pc-label">Most Popular</span>
                    <h2>Cartridges</h2>
                    <p class="pc-desc">Four heating platforms — from value-driven ceramic to premium CCELL 3.0 VeinMesh — for every oil viscosity and price point.</p>
                    <p class="pc-count"><?php echo esc_html($cats['cartridge']['count']); ?> products</p>
                    <span class="pc-btn">Shop Cartridges</span>
                </div>
            </a>
            <?php endif; ?>

            <?php if (isset($cats['disposable'])): ?>
            <a href="<?php echo esc_url($cats['disposable']['url']); ?>" class="pc-hero-card">
                <div class="pc-hero-img">
                    <img src="<?php echo esc_url(content_url('/uploads/2026/02/ccell-aio-tank.jpg')); ?>" alt="CCELL Disposables">
                </div>
                <div class="pc-hero-body">
                    <span class="pc-label">All-In-One</span>
                    <h2>Disposables</h2>
                    <p class="pc-desc">Ready-to-fill AIO devices with integrated battery and mouthpiece. No assembly, no charging — just fill and sell.</p>
                    <p class="pc-count"><?php echo esc_html($cats['disposable']['count']); ?> products</p>
                    <span class="pc-btn">Shop Disposables</span>
                </div>
            </a>
            <?php endif; ?>
        </div>

        <!-- Medium Cards: Batteries & Pod Systems -->
        <div class="pc-mid-grid">
            <?php if (isset($cats['batteries'])): ?>
            <a href="<?php echo esc_url($cats['batteries']['url']); ?>" class="pc-mid-card">
                <div class="pc-mid-img">
                    <?php if ($cats['batteries']['img']): ?>
                        <img src="<?php echo esc_url($cats['batteries']['img']); ?>" alt="<?php echo esc_attr($cats['batteries']['name']); ?>">
                    <?php endif; ?>
                </div>
                <div class="pc-mid-body">
                    <h3><?php echo esc_html($cats['batteries']['name']); ?></h3>
                    <p class="pc-desc">510-thread and proprietary batteries designed to pair with CCELL cartridges and pods.</p>
                    <p class="pc-count"><?php echo esc_html($cats['batteries']['count']); ?> products</p>
                </div>
            </a>
            <?php endif; ?>

            <?php if (isset($cats['pod-systems'])): ?>
            <a href="<?php echo esc_url($cats['pod-systems']['url']); ?>" class="pc-mid-card">
                <div class="pc-mid-img">
                    <?php if ($cats['pod-systems']['img']): ?>
                        <img src="<?php echo esc_url($cats['pod-systems']['img']); ?>" alt="<?php echo esc_attr($cats['pod-systems']['name']); ?>">
                    <?php endif; ?>
                </div>
                <div class="pc-mid-body">
                    <h3><?php echo esc_html($cats['pod-systems']['name']); ?></h3>
                    <p class="pc-desc">Closed-loop pod systems with magnetic connections for a sleek, leak-resistant vape experience.</p>
                    <p class="pc-count"><?php echo esc_html($cats['pod-systems']['count']); ?> products</p>
                </div>
            </a>
            <?php endif; ?>
        </div>

        <!-- Technology Comparison Bar -->
        <div class="pc-tech-bar">
            <p>Not sure which CCELL technology is right?</p>
            <a href="/ccell-heating-technology/">Compare Technologies</a>
        </div>

        <!-- Additional Products -->
        <hr class="pc-divider">
        <div class="pc-section-title">
            <h2>Additional Products</h2>
            <p>Vaporizers, kits, packaging supplies, and more.</p>
        </div>

        <div class="pc-add-grid">
            <?php
            $additional = array('vaporizers', 'kits', 'package', 'pipes', 'packaging');
            foreach ($additional as $aslug):
                if (!isset($cats[$aslug])) continue;
                $c = $cats[$aslug];
            ?>
            <a href="<?php echo esc_url($c['url']); ?>" class="pc-add-card">
                <div class="pc-add-img">
                    <?php if ($c['img']): ?>
                        <img src="<?php echo esc_url($c['img']); ?>" alt="<?php echo esc_attr($c['name']); ?>">
                    <?php endif; ?>
                </div>
                <h4><?php echo esc_html($c['name']); ?></h4>
                <p class="pc-count"><?php echo esc_html($c['count']); ?> products</p>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Bottom CTA -->
        <div class="pc-bottom-cta">
            <h3>Need Help Building Your Wholesale Order?</h3>
            <p>Our team can help you choose the right products, quantities, and configurations for your brand.</p>
            <a href="/request-samples/" class="pc-btn">Request Samples</a>
        </div>

    </div>
    <?php
    return ob_get_clean();
});

// =============================================================================
// CCELL Sidebar Navigation — Replace generic category widget on CCELL pages
// =============================================================================
add_filter('widget_display_callback', function($instance, $widget, $args) {
    if ($widget->id_base !== 'woocommerce_product_categories') return $instance;
    if (!is_product_category()) return $instance;

    $current = get_queried_object();
    if (!$current) return $instance;

    // All CCELL category slugs (parents + subcategories) — no hardcoded IDs
    $cartridge_sub_slugs = ['ccell-easy', 'ccell-se', 'ccell-evo-max', 'ccell-ceramic-evo-max', 'ccell-3-postless'];
    $disposable_sub_slugs = ['aio-se-standard', 'aio-evo-max', 'aio-3-bio-heating', 'aio-hero'];
    $ccell_slugs = array_merge(['cartridge', 'disposable', 'ccell'], $cartridge_sub_slugs, $disposable_sub_slugs);

    // Only replace widget on CCELL category pages
    if (!in_array($current->slug, $ccell_slugs)) return $instance;

    $current_slug = $current->slug;
    $base = '/product-category/';

    // Build nav items: slug => display name
    $cart_items = [
        'ccell-easy'             => 'CCELL Easy Cart',
        'ccell-se'               => 'CCELL SE Glass',
        'ccell-evo-max'          => 'CCELL EVO MAX',
        'ccell-ceramic-evo-max'  => 'CCELL Ceramic EVO MAX',
        'ccell-3-postless'       => 'CCELL 3.0 Postless',
    ];

    $aio_items = [
        'aio-se-standard'  => 'AIO SE (Standard)',
        'aio-evo-max'      => 'AIO EVO MAX',
        'aio-3-bio-heating' => 'AIO 3.0 Bio-Heating',
        'aio-hero'         => 'AIO HeRo',
    ];

    // Output custom CCELL nav
    echo $args['before_widget'];
    echo $args['before_title'] . 'CCELL Products' . $args['after_title'];

    echo '<style>
    .ccell-sidebar-nav { list-style:none; margin:0; padding:0; }
    .ccell-sidebar-nav .ccell-nav-group { margin-bottom:18px; }
    .ccell-sidebar-nav .ccell-nav-group-title a {
        font-size:14px; font-weight:700; color:#1a1a1a; text-decoration:none;
        display:block; padding:6px 0; transition:color .2s;
    }
    .ccell-sidebar-nav .ccell-nav-group-title a:hover { color:#E50914; }
    .ccell-sidebar-nav .ccell-nav-group-title a.active { color:#E50914; }
    .ccell-sidebar-nav .ccell-nav-sub { list-style:none; margin:0; padding:0 0 0 14px; }
    .ccell-sidebar-nav .ccell-nav-sub li a {
        font-size:13px; font-weight:400; color:#555; text-decoration:none;
        display:block; padding:5px 0; border-left:2px solid transparent;
        padding-left:12px; transition:all .2s;
    }
    .ccell-sidebar-nav .ccell-nav-sub li a:hover { color:#E50914; border-left-color:#E50914; }
    .ccell-sidebar-nav .ccell-nav-sub li a.active {
        color:#E50914; font-weight:600; border-left-color:#E50914;
    }
    </style>';

    echo '<ul class="ccell-sidebar-nav">';

    // Cartridges group
    $cart_active = ($current_slug === 'cartridge' || in_array($current_slug, $cartridge_sub_slugs)) ? ' active' : '';
    echo '<li class="ccell-nav-group">';
    echo '<div class="ccell-nav-group-title"><a href="' . $base . 'cartridge/" class="' . $cart_active . '">Cartridges</a></div>';
    echo '<ul class="ccell-nav-sub">';
    foreach ($cart_items as $slug => $name) {
        $cls = ($current_slug === $slug) ? ' class="active"' : '';
        echo '<li><a href="' . $base . $slug . '/"' . $cls . '>' . $name . '</a></li>';
    }
    echo '</ul></li>';

    // Disposables group
    $aio_active = ($current_slug === 'disposable' || in_array($current_slug, $disposable_sub_slugs)) ? ' active' : '';
    echo '<li class="ccell-nav-group">';
    echo '<div class="ccell-nav-group-title"><a href="' . $base . 'disposable/" class="' . $aio_active . '">All-In-One Disposables</a></div>';
    echo '<ul class="ccell-nav-sub">';
    foreach ($aio_items as $slug => $name) {
        $cls = ($current_slug === $slug) ? ' class="active"' : '';
        echo '<li><a href="' . $base . $slug . '/"' . $cls . '>' . $name . '</a></li>';
    }
    echo '</ul></li>';

    echo '</ul>';
    echo $args['after_widget'];

    return false; // Prevent default widget output
}, 10, 3);

// =============================================================================
// PDP Informational Sections — Data-driven system for all CCELL technology tiers
// See inc/pdp-sections.php for tier data, rendering engine, and hook registration
// =============================================================================
require_once get_stylesheet_directory() . '/inc/pdp-sections.php';
