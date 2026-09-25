<?php

namespace IUWoocommerce;

require_once('blocks.php');
require_once('settings.php');

class Plugin {
	private static $instance;
	static function get_instance(){
		if (is_null(self::$instance)){
			self::$instance = new self();
		}
		return self::$instance;
	}

	function __construct(){
		add_action('after_setup_theme', [$this, 'theme_support']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue']);
		// add_action('admin_enqueue_scripts', [$this, 'admin_enqueue']);

		add_filter('woocommerce_enqueue_styles', '__return_empty_array');
		// add_filter('woocommerce_single_product_zoom_enabled', '__return_false');
		add_filter('wc_add_to_cart_message_html', '__return_false');
		// add_filter('use_block_editor_for_post_type', [$this, 'enable_editor'], 10, 2);
		add_filter('woocommerce_apply_user_tracking', '__return_false', 999);
		add_filter('woocommerce_shipping_estimate_html', '__return_false');

		// remove_action('woocommerce_cart_collaterals', 'woocommerce_cross_sell_display');
		// add_action('woocommerce_after_cart_table', 'woocommerce_cross_sell_display');
		
		add_action('woocommerce_before_shop_loop', [$this, 'list_views']);
		add_filter('shortcode_atts_products', [$this, 'force_columns_in_products_shortcode']);
		add_action('woocommerce_before_shop_loop_item', [$this, 'shop_loop_product'], 1);

		add_filter('wc_get_template', [$this, 'wc_templates'], 10, 2);
		add_filter('woocommerce_shipping_package_name', function($shipping_package_name){ return $shipping_package_name == 'Shipment' ? 'Szállítás' : $shipping_package_name; });

		add_action('woocommerce_checkout_before_order_review_heading', function(){ echo '<div class="order-review">'; });
		add_action('woocommerce_checkout_after_order_review', function(){ echo '</div>'; });
		add_filter('woocommerce_form_field_args', [$this, 'form_field_args'], 10, 3);
		add_filter('woocommerce_get_country_locale_default', [$this, 'remove_phone_from_default']);
		add_filter('woocommerce_checkout_fields', [$this, 'manage_checkout_fields'], 99);
		add_filter('woocommerce_cart_shipping_method_full_label', [$this, 'shipping_method_name']);
		add_filter('woocommerce_default_address_fields', [$this, 'account_fields']);

		// add_filter('woocommerce_product_cross_sells_products_heading', function(){ return 'Érdekelheti még&hellip;'; });
		add_filter('woocommerce_order_button_text', function(){ return 'Megrendelem'; });

		add_filter('do_shortcode_tag', function($output, $tag){
			if (strpos($tag, 'woocommerce') === 0) return '<div class="iu-woocommerce-'.(is_cart() ? 'cart' : '').(is_checkout() ? 'checkout' : '').(is_account_page() ? 'account' : '').'">'.$output.'</div>';
			return $output;
		}, 10, 2);

		add_filter('woocommerce_form_field_checkbox', function($field){
			return str_replace(['<span>', '</label>'], ['<span class="inner"><span>', '</span></label>'], $field);
		});

		remove_action('woocommerce_checkout_terms_and_conditions', 'wc_terms_and_conditions_page_content', 30);

		add_action('template_redirect',function(){
			remove_action( 'template_redirect', ['WC_Template_Loader', 'unsupported_theme_init']);
		}, 5);

		add_action('woocommerce_edit_account_form_start', function(){ echo '<h2 style="margin-top:0">Fiókadatok</h2>'; });
		add_action('woocommerce_before_account_orders', function(){ echo '<h2 style="margin-top:0">Rendelések</h2>'; });
		add_filter('woocommerce_order_details_status', function($status, $order){
			return '<span class="iu-woocommerce-badge order-date">'.wc_format_datetime($order->get_date_created()).'</span>
				<span class="iu-woocommerce-badge order-status">'.wc_get_order_status_name($order->get_status()).'</span>';
		}, 10, 2);

		add_filter('woocommerce_get_order_item_totals', function($totals){
			foreach($totals as &$total){
				$total['label'] = str_replace(':','',$total['label']);
			}
			return $totals;
		});

		add_filter('iu_title', function($title){ 
			if (!is_wc_endpoint_url('order-received') || in_the_loop() || !is_main_query()) return $title;
			return 'Köszönjük, rendelésed rögzítettük';
		});

		remove_action('woocommerce_thankyou', 'woocommerce_order_details_table', 10);

		Blocks::get_instance();
		Settings::get_instance();
	}

	function theme_support(){
		add_theme_support('wc-product-gallery-slider');
        add_theme_support('wc-product-gallery-lightbox');
        //add_theme_support('wc-product-gallery-zoom');
	}

	function enqueue(){
		wp_enqueue_style('iu-woocommerce', plugins_url('style.css', __FILE__));
		wp_enqueue_script('iu-woocommerce', plugins_url('script.js', __FILE__), false, true, true);
	}

	function admin_enqueue(){
		wp_enqueue_style('iu-woocommerce', plugins_url('style.css', __FILE__));
	}

	function enable_editor($can_edit, $post_type){
		if ($post_type === 'product') return true;
		return $can_edit;
	}

	function list_views(){
		if (!ius_get('iu_theme', 'listviews')) return;

		$cookie = isset($_COOKIE['iu-woocommerce-list-view']) ? $_COOKIE['iu-woocommerce-list-view'] : 'grid';
		
		echo '<div class="iu-woocommerce-list-views">';
			echo '<a class="grid'.($cookie === 'grid' ? ' active' : '').'">
				<svg width="26" height="24" viewBox="0 0 26 24" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="14.4" width="10.8" height="9.9" rx="1" fill="currentColor"/><rect width="10.8" height="9.9" rx="1" fill="currentColor"/><rect y="13.2" width="10.8" height="9.9" rx="1" fill="currentColor"/><rect x="14.4" y="13.2" width="10.8" height="9.9" rx="1" fill="currentColor"/></svg>
			</a>';
			echo '<a class="list'.($cookie !== 'list' ? ' active' : '').'">
				<svg width="27" height="22" viewBox="0 0 27 22" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="6" height="6" rx="1" fill="currentColor"/><rect y="8" width="6" height="6" rx="1" fill="currentColor"/><rect y="16" width="6" height="6" rx="1" fill="currentColor"/><rect x="8" width="19" height="6" rx="1" fill="currentColor"/><rect x="8" y="8" width="19" height="6" rx="1" fill="currentColor"/><rect x="8" y="16" width="19" height="6" rx="1" fill="currentColor"/></svg>
			</a>';
		echo '</div>';
	}

	function force_columns_in_products_shortcode($out){
		if (!!ius_get('iu_theme', 'listviews') && isset($_COOKIE['iu-woocommerce-list-view']) && $_COOKIE['iu-woocommerce-list-view'] === 'list'){
			$out['columns'] = 1;
		}
		return $out;
	}

	function shop_loop_product(){
		$loop_pattern = get_posts([ 'post_type' => 'iu_pattern', 'title' => 'Loop Product' ]);
		if (empty($loop_pattern)) return;

		remove_action('woocommerce_before_shop_loop_item', 'woocommerce_template_loop_product_link_open');
		remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_show_product_loop_sale_flash');
		remove_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail');
		remove_action('woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title');		
		remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating');	
		remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price');	
		remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_product_link_close');	
		remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart');

		echo '<div class="iu-woocommerce-loop-product">'.do_blocks($loop_pattern[0]->post_content).'</div>';
	}

    function shop_loop_badges(){
    	$product = wc_get_product();

    	echo '<div class="iu-woocommerce-badges">';
    	$created_date = $product->get_date_created();
	    
	    if ($product->is_featured()){
	    	echo '<span class="iu-woocommerce-badge featured">Kiemelt</span>';
	    }
	    if ($product->is_on_sale()){
	    	echo '<span class="iu-woocommerce-badge onsale">Akció!</span>';
	    }
	    if ($created_date){
	        $days_ago = (time() - $created_date->getTimestamp()) / DAY_IN_SECONDS;

	        if ($days_ago <= 10) {
	            echo '<span class="iu-woocommerce-badge new">Új</span>';
	        }
	    }
	    echo '</div>';
    }

    function shop_loop_category(){
    	$product = wc_get_product();
    	$ids = $product->get_category_ids();

    	echo '<div class="iu-woocommerce-categories">';
    	foreach($ids as $id){
    		$category = get_term($id, 'product_cat');
    		echo '<a href="'.get_term_link($category, 'product_cat').'">'.$category->name.'</a>';
    	}
    	echo '</div>';
    }

    function wc_templates($located, $template_name){
    	$template = __DIR__.'/templates/'.$template_name;
    	if (file_exists($template)) $located = $template;
    	return $located;
    }

    function form_field_args($args, $key){
    	$args['class'][] = 'iu-form-field';
    	switch($args['type']){
    		case 'textarea': $args['class'][] = 'iu-form-textarea'; break;
    		case 'country':
    		case 'select': $args['class'][] = 'iu-form-select'; break;
    		default: $args['class'][] = 'iu-form-text';
    	}
    	return $args;
    }

    function remove_phone_from_default($fields){
    	if (isset($fields['phone'])) {
    		$fields['phone']['priority'] = 9;
    		$fields['phone']['required'] = true;
    	}
    	return $fields;
    }

    function manage_checkout_fields($fields){
    	unset($fields['billing']['billing_state']);
    	unset($fields['shipping']['shipping_state']);
    	unset($fields['shipping']['shipping_phone']);

    	$fields['billing']['billing_email']['priority'] = 8;
    	$fields['billing']['billing_phone']['required'] = true;
    	$fields['billing']['billing_phone']['priority'] = 9;

    	$fields['billing']['billing_company'] = [
    		'type' => 'text',
    		'label' => 'Cégnév',
	        'placeholder' => '',
	        'required' => false,
	        'class' => ['form-row-wide'],
	        'priority' => 21
    	];

    	$fields['billing']['billing_tax_number'] = [
    		'type' => 'text',
    		'label' => 'Adószám',
	        'placeholder' => '',
	        'required' => false,
	        'class' => ['form-row-wide'],
	        'priority' => 22
    	];

    	$fields['shipping']['shipping_company'] = $fields['billing']['billing_company'];

    	return $fields;
    }

    function shipping_method_name($name){
    	return str_replace(': ', '', $name);
    }

    function account_fields($fields){
    	if (isset($fields['state'])) unset($fields['state']);
    	return $fields;
    }
}