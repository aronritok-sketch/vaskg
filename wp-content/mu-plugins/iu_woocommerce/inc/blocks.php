<?php

namespace IUWoocommerce;

class Blocks {
	private static $instance;
	static function get_instance(){
		if (is_null(self::$instance)){
			self::$instance = new self();
		}
		return self::$instance;
	}

	function __construct(){
		add_action('init', [$this, 'add_blocks']);
		add_action('woocommerce_before_quantity_input_field', [$this, 'before_quantity']);
		add_action('woocommerce_after_quantity_input_field', [$this, 'after_quantity']);

		add_filter('block_categories_all', [$this, 'block_category']);
		add_filter('woocommerce_loop_add_to_cart_link', [$this, 'add_to_cart_html']);
		add_filter('woocommerce_loop_add_to_cart_args', [$this, 'button_classes']);

		add_action('woocommerce_blocks_loaded', function(){
		    remove_action('init', [\Automattic\WooCommerce\Blocks\Package::container()->get( \Automattic\WooCommerce\Blocks\BlockTypesController::class ), 'register_blocks']);
		});

		add_action('enqueue_block_assets', function(){
		    wp_deregister_style('wc-blocks-style');
		    wp_dequeue_style('wc-blocks-style');
		});

		add_filter('woocommerce_cart_item_name', [$this, 'remove_product_title_in_mini_cart'], 10, 3);
		add_filter('woocommerce_widget_cart_item_quantity', [$this, 'add_product_title_in_mini_cart'], 10, 3);

		remove_action('woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_button_view_cart', 10);
    	remove_action('woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_proceed_to_checkout', 20);
    	add_action('woocommerce_widget_shopping_cart_buttons', [$this, 'mini_cart_buttons']);

    	add_action('wp_ajax_iu_woocommerce_search', [$this, 'perform_search']);
		add_action('wp_ajax_nopriv_iu_woocommerce_search', [$this, 'perform_search']);

		add_action('template_redirect', [$this, 'perform_filter']);
		add_filter('shortcode_atts_products', [$this, 'filter_atts'], 10, 3);
		add_filter('woocommerce_shortcode_products_query', [$this, 'filter_params'], 10, 2);
		add_action('woocommerce_shortcode_products_loop_no_results', [$this, 'empty_results']);
		add_filter('woocommerce_default_catalog_orderby',function(){ return 'popularity'; });
	}

	function add_blocks(){
		$block_dirs = array_filter(glob(__DIR__.'/blocks/*'), 'is_dir');
		foreach($block_dirs as $block_dir){
            if (basename($block_dir)[0] !== '_'){
                register_block_type($block_dir);
            }
        }

        iucb_add_block('iu-woocommerce/loop-product-title', [
        	'title' => 'Loop Product Title',
			'description' => 'Loop Product Title',
			'category' => 'iu-woocommerce',
			'attributes' => [],
			'editJS' => 'return "Loop Product Title";',
			'template' => function($attributes){
				ob_start(); woocommerce_template_loop_product_link_open();
				$link_open = ob_get_clean();
					
				ob_start(); woocommerce_template_loop_product_link_close();
				$link_close = ob_get_clean();

				return '<h4 class="'.esc_attr( apply_filters( 'woocommerce_product_loop_title_classes', 'iu-woocommerce-loop-title woocommerce-loop-product__title' ) ).'">'.$link_open.get_the_title().$link_close.'</h4>';
			}
        ]);

        iucb_add_block('iu-woocommerce/loop-product-image', [
        	'title' => 'Loop Product Image',
			'description' => 'Loop Product Image',
			'category' => 'iu-woocommerce',
			'attributes' => [],
			'editJS' => 'return "Loop Product Image";',
			'template' => function($attributes){
				ob_start(); woocommerce_template_loop_product_link_open();
				$link_open = ob_get_clean();
					
				ob_start(); woocommerce_template_loop_product_link_close();
				$link_close = ob_get_clean();

				ob_start(); woocommerce_template_loop_product_thumbnail();
				$thumbnail = ob_get_clean();

				global $product;
				if (!$product) return;

		        $ids = $product->get_gallery_image_ids();
		        if (!empty($ids)) $thumbnail .= wp_get_attachment_image($ids[0], 'woocommerce_thumbnail', false, []);
				
				return '<div class="iu-woocommerce-loop-gallery">'.$link_open.$thumbnail.$link_close.'</div>';
			}
        ]);

        iucb_add_block('iu-woocommerce/loop-product-pirce', [
        	'title' => 'Loop Product Price',
			'description' => 'Loop Product Price',
			'category' => 'iu-woocommerce',
			'attributes' => [],
			'editJS' => 'return "Loop Product Price";',
			'template' => function($attributes){
				global $product;
				if (!$product) return;

				ob_start(); woocommerce_template_loop_price();
        		return '<div class="iu-woocommerce-loop-price">'.ob_get_clean().'</div>';
        	}
        ]);

        iucb_add_block('iu-woocommerce/product-badges', [
        	'title' => 'Product Badges',
			'description' => 'Product Badges',
			'category' => 'iu-woocommerce',
			'attributes' => [
				'featured' => [
					'type' => 'boolean',
					'default' => true
				],
				'new' => [
					'type' => 'boolean',
					'default' => true
				],
				'sale' => [
					'type' => 'boolean',
					'default' => true
				],
				'discount' => [
					'type' => 'boolean',
					'default' => true
				]
			],
			'fields' => [
				[
					'panel' => 'Settings',
					'fields' => [
						'featured' => [
							'type' => 'toggle',
							'label' => 'Show featured'
						],
						'new' => [
							'type' => 'toggle',
							'label' => 'Show new'
						],
						'sale' => [
							'type' => 'toggle',
							'label' => 'Show sale'
						],
						'discount' => [
							'type' => 'toggle',
							'label' => 'Show discount'
						]
					]
				]
			],
			'editJS' => 'return "Loop Product Badges";',
			'template' => function($attributes){
				global $product;
				if (!$product) return;

				$badges = '';

				if ($attributes['featured'] && $product->is_featured()){
					$badges .= '<span class="iu-woocommerce-badge featured">Kiemelt</span>';
				}

				if ($attributes['new']){
					$created_date = $product->get_date_created();
					if ($created_date){
					    $days_ago = (time() - $created_date->getTimestamp()) / DAY_IN_SECONDS;

					    if ($days_ago <= 10) {
					        $badges .= '<span class="iu-woocommerce-badge new">Új</span>';
					    }
					}
				}

				if ($product->is_on_sale()){
					if ($attributes['sale']) $badges .= '<span class="iu-woocommerce-badge onsale">Akció!</span>';
					if ($attributes['discount']){
						$discount = 100-round($product->get_sale_price()/$product->get_regular_price()*100);
						$badges .= '<span class="iu-woocommerce-badge discount">-'.$discount.'%</span>';
					}
				}

				return '<div class="iu-woocommerce-badges">'.$badges.'</div>';
        	}
        ]);

        iucb_add_block('iu-woocommerce/product-attributes', [
        	'title' => 'Product Attributes',
			'description' => 'Product Attributes',
			'category' => 'iu-woocommerce',
			'attributes' => [
				'count' => [
					'type' => 'string',
					'defautl' => ''
				]
			],
			'editJS' => ' return "Product Attributes";',
			'template' => function($attributes){
				global $product;
				if (!$product) return;

				$attrs = $product->get_attributes();
				$attrs = array_map(function($attr, $name){
					return '<div class="iu-woocommerce-product-attribute '.$name.'">
						<span class="iu-woocommerce-product-attribute-name">'.$attr->get_name().'</span>
						<span class="iu-woocommerce-product-attribute-value">'.$attr->get_options()[0].'</span>
					</div>';
				}, $attrs, array_keys($attrs));

				return '<div class="iu-woocommerce-product-attributes">'.implode('', $attrs).'</div>';
			}
		]);

		iucb_add_block('iu-woocommerce/product-brand', [
        	'title' => 'Product Brand',
			'description' => 'Product Brand',
			'category' => 'iu-woocommerce',
			'attributes' => [],
			'editJS' => ' return "Product Brand";',
			'template' => function($attributes){
				global $product;
				if (!$product) return;

				$brands = get_the_terms($product->get_id(), 'product_brand');
				if (empty($brands)) return;

				return '<a class="iu-woocommerce-product-brand" href="'.get_term_link($brands[0]->term_id).'">'.$brands[0]->name.'</a>';
			}
		]);

		wp_register_script('iu-woocommerce-product-carousel-script', plugins_url('assets/product-carousel.js', __FILE__), ['iu-slick']);
		wp_register_style('iu-woocommerce-product-carousel-style', plugins_url('assets/product-carousel.css', __FILE__));
		iucb_add_block('iu-woocommerce/product-carousel', [
			'title' => 'Product Carousel',
			'description' => 'Product Carousel',
			'category' => 'iu-woocommerce',
			'attributes' => [
				'params' => [
					'type' => 'string',
					'default' => "\"slidesToShow\": 4,\n\"slidesToScroll\": 4,\n\"infinite\": false"
				]
			],
			'fields' => [
				[
					'panel' => 'Settings',
					'fields' => [
						'params' => [
							'label' => 'Params',
							'type' => 'textarea'
						]
					]
				]
			],
			'editJS' => '
				return `<div style="background:#eee;display:flex;align-items:center;justify-content:center;width:100%;padding:6rem 0;font-family:monospace;">Product Carousel</div>`;
			',
			'template' => function($attributes){
				$products = wc_get_products([
					'limit' => 12,
					'status' => 'publish',
					'orderby' => 'date',
    				'order' => 'DESC'
				]);
				if (empty($products)) return '';

				$params = json_decode('{'.$attributes['params'].'}', true);

				ob_start();
				global $wp_query;
				$wp_query->in_the_loop = true;

				echo '<div class="iu-woocommerce-product-carousel">';
					echo '<div class="iu-woocommerce-product-carousel-slider" data-slick=\''.json_encode($params).'\'>';
					
					foreach ($products as $product_obj) {
						global $product, $post;
						$product = $product_obj;
						$post = get_post($product->get_id());

						setup_postdata($post);

				        wc_get_template_part('content', 'product');
				    }

				    wp_reset_postdata();
				    
				    echo '</div>';
				    echo '<div class="iu-woocommerce-product-carousel-arrows"></div>';
				echo '</div>';

			    return ob_get_clean();
			},
			'style' => 'iu-woocommerce-product-carousel-style',
			'view_script' => 'iu-woocommerce-product-carousel-script'
		]);

		wp_register_script('iu-woocommerce-product-feature-script', plugins_url('assets/product-feature.js', __FILE__), ['iu-slick']);
		wp_register_style('iu-woocommerce-product-feature-style', plugins_url('assets/product-feature.css', __FILE__));
		iucb_add_block('iu-woocommerce/product-feature', [
			'title' => 'Product Feature',
			'description' => 'Product Feature',
			'category' => 'iu-woocommerce',
			'attributes' => [
				'before' => [
					'type' => 'string',
					'default' => ''
				]
			],
			'fields' => [
				[
					'panel' => 'Settings',
					'fields' => [
						'before' => [
							'label' => 'Before slider',
							'type' => 'textarea'
						]
					]
				]
			],
			'editHTML' => '
				return `<div style="background:#eee;display:flex;align-items:center;justify-content:center;width:100%;padding:6rem 0;font-family:monospace;">Product Feature</div>`;
			',
			'supports' => [
				'iu' => [
					'background' => true
				]
			],
			'template' => function($attributes){
				$products = wc_get_products([
					'featured' => true,
					'limit' => 6,
					'status' => 'publish',
					'orderby' => 'date',
    				'order' => 'DESC'
				]);
				if (empty($products)) return '';

				ob_start();

				$style = '';
				if (!empty($attributes['bgColor'])) $style .= 'background-color:var(--wp--preset--color--'.$attributes['bgColor'].');';
				if (!empty($attributes['bgGradient'])) $style .= 'background-image:'.$attributes['bgGradient'].';';
				if (!empty($attributes['bgImage'])){
					$style .= 'background-image:url('.$attributes['bgImage'].');background-repeat:no-repeat;';
					if (!empty($attributes['bgImageSize'])) $style .= 'background-size:'.$attributes['bgImageSize'].';';
					if (!empty($attributes['bgImagePosition'])) $style .= 'background-position:'.$attributes['bgImagePosition'].';';
				}

				echo '<div class="iu-woocommerce-product-feature"'.(!empty($style) ? ' style="'.$style.'"' : '').'>';
					echo '<div class="iu-woocommerce-product-feature-image">';
						foreach ($products as $i => $product_obj) {
							$ids = $product_obj->get_gallery_image_ids();
							$img = !empty($ids) ? $ids[0] : $product_obj->get_image_id();
							echo '<img src="'.wp_get_attachment_image_url($img, 'full').'"'.($i == 0 ? ' class="iu-woocommerce-product-feature-active"' : '').' />';
						}
					echo '</div>';
					echo '<div class="iu-woocommerce-product-feature-right">';
						if (!empty($attributes['before'])) echo $attributes['before'];
						echo '<div class="iu-woocommerce-product-feature-slider-wrap">';
							echo '<div class="iu-woocommerce-product-feature-slider">';
						
							foreach ($products as $product_obj) {
								global $product, $post;
								$product = $product_obj;
								$post = get_post($product->get_id());

								setup_postdata($post);

						        wc_get_template_part('content', 'product');
						    }

						    wp_reset_postdata();
						    
						    echo '</div>';
						    echo '<div class="iu-woocommerce-product-feature-slider-arrows"></div>';
						echo '</div>';
					echo '</div>';
				echo '</div>';

				return ob_get_clean();
			},
			'style' => 'iu-woocommerce-product-feature-style',
			'view_script' => 'iu-woocommerce-product-feature-script'
		]);

		iucb_add_block('iu-woocommerce/price', [
			'title' => 'Product Price',
			'description' => 'Product Price',
			'category' => 'iu-woocommerce',
			'editJS' => '
				return `<div style="background:#eee;display:flex;align-items:center;justify-content:center;width:100%;padding:6rem 0;font-family:monospace;">Product Price</div>`;
			',
			'template' => function(){
				if (in_array(get_post_type(), ['iu_template', 'revision'])) {
					return;
				}

				global $product;
				$product = wc_get_product();
				
				ob_start();

				echo '<div class="iu-woocommerce-price">';
					woocommerce_template_single_price();
				echo '</div>';
				
				return ob_get_clean();
			}
		]);

		wp_enqueue_block_style('iu-woocommerce/add-to-cart', [ 'handle' => 'iu-button-style', 'src' => get_theme_file_uri( 'inc/blocks/button/style.css' ), 'path' => get_theme_file_path( 'inc/blocks/button/style.css' ) ]);
		iucb_add_block('iu-woocommerce/add-to-cart', [
			'title' => 'Add To Cart',
			'description' => 'Add To Cart',
			'category' => 'iu-woocommerce',
			'editHTML' => '
				return `<div style="background:#eee;display:flex;align-items:center;justify-content:center;width:100%;padding:6rem 0;font-family:monospace;">Add To Cart</div>`;
			',
			'template' => function(){
				if (in_array(get_post_type(), ['iu_template', 'revision'])) {
					return;
				}

				global $product;
				$product = wc_get_product();
				
				ob_start();
				woocommerce_template_loop_add_to_cart();
				return ob_get_clean();
			}
		]);

		iucb_add_block('iu-woocommerce/loop-product-button', [
			'title' => 'Loop Product Button',
			'description' => 'Loop Product Button',
			'category' => 'iu-woocommerce',
			'editJS' => 'return "Loop Product Button";',
			'template' => function(){
				global $product;
				if (!$product) return;
				
				return '<a href="'.get_permalink($product->get_id()).'" class="iu-button iu-button-default iu-button-small"><span class="dashicons dashicons-arrow-right-alt2"></span></a>';
			}
		]);

		iucb_add_block('iu-woocommerce/images', [
			'title' => 'Product Images',
			'description' => 'Product Images',
			'category' => 'iu-woocommerce',
			'editHTML' => '
				return `<div style="background:#eee;display:flex;align-items:center;justify-content:center;width:100%;padding:6rem 0;font-family:monospace;">Product Images</div>`;
			',
			'template' => function(){
				if (in_array(get_post_type(), ['iu_template', 'revision'])) {
					return;
				}

				global $product;
				$product = wc_get_product();
				
				ob_start();
				woocommerce_show_product_images();
				return ob_get_clean();
			}
		]);

		iucb_add_block('iu-woocommerce/short-description', [
			'title' => 'Product Short Description',
			'description' => 'Product Short Description',
			'category' => 'iu-woocommerce',
			'attributes' => [
				'words' => [
					'type' => 'string',
					'default' => ''
				]
			],
			'fields' => [
				[
					'panel' => 'Settings',
					'fields' => [
						'words' => [
							'type' => 'text',
							'label' => 'Words'
						]
					]
				]
			],
			'editJS' => '
				return "Product Short Description";
			',
			'template' => function($attributes){
				global $product;
				$product = wc_get_product();
				if (empty($product) || empty($product->get_short_description())) return '';

				$short_description = $product->get_short_description();
				if (!empty($attributes['words'])) $short_description = wp_trim_words($short_description, (int)$attributes['words'], '&hellip;');
			
				return '<div class="iu-woocommerce-short-description"><p>'.$short_description.'</p></div>';
			}
		]);

		wp_register_script('iu-woocommerce-mini-cart-script', plugins_url('assets/mini-cart.js', __FILE__), ['jquery']);
		wp_register_style('iu-woocommerce-mini-cart-style', plugins_url('assets/mini-cart.css', __FILE__));
		wp_enqueue_block_style('iu-woocommerce/mini-cart', [ 'handle' => 'iu-button-style', 'src' => get_theme_file_uri( 'inc/blocks/button/style.css' ), 'path' => get_theme_file_path( 'inc/blocks/button/style.css' ) ]);
		iucb_add_block('iu-woocommerce/mini-cart', [
			'title' => 'Mini kosár',
			'description' => 'Mini kosár',
			'category' => 'iu-woocommerce',
			'attributes' => [
				'svg' => [
					'type' => 'string',
					'default' => ''
				],
				'type' => [
					'type' => 'string',
					'default' => ''
				]
			],
			'fields' => [
				[
					'panel' => 'Settings',
					'fields' => [
						'svg' => [
							'label' => 'SVG',
							'type' => 'textarea'
						],
						'type' => [
							'label' => 'Type',
							'type' => 'select',
							'options' => [
								[ 'label' => 'Dropdown', 'value' => '' ],
								[ 'label' => 'Drawer', 'value' => 'drawer' ]
							]
						]
					]
				]
			],
			'editHTML' => '
				const svg = attributes.svg !== "" ? attributes.svg : `<span class="dashicons dashicons-cart"></span>`;
				return `<div class="iu-woocommerce-mini-cart">
					<a class="iu-woocommerce-mini-cart__trigger">
						`+svg+`
						<span class="iu-woocommerce-mini-cart__badge">1</span>
					</a>
				</div>`;
			',
			'template' => function($attributes){
				if (in_array(get_post_type(), ['iu_template', 'revision']))
					return '<div class="iu-woocommerce-mini-cart">
						<a href="#">
							'.(!empty($attributes['image']) ? '<img src="'.wp_get_attachment_image_url($attributes['image']).'" />' : '<span class="dashicons dashicons-cart"></span>').'
							<span class="iu-woocommerce-mini-cart__badge">0</span>
						</a>
					</div>';

				ob_start();
				woocommerce_mini_cart();
				$mini_cart = ob_get_clean();

				$cart_count = WC()->cart->get_cart_contents_count();
				$is_cart_or_checkout = is_cart() || is_checkout();

				return '<div class="iu-woocommerce-mini-cart">
					<a href="'.(!$is_cart_or_checkout ? '#' : wc_get_cart_url()).'" class="iu-woocommerce-mini-cart__trigger">
						'.(!empty($attributes['svg']) ? $attributes['svg'] : '<span class="dashicons dashicons-cart"></span>').'
						<span class="iu-woocommerce-mini-cart__badge">'.($cart_count > 0 ? $cart_count : '').'</span>
					</a>'.
					(!$is_cart_or_checkout ? '<div class="iu-woocommerce-mini-cart__'.($attributes['type'] == '' ? 'dropdown' : 'drawer' ).'">
						<div class="iu-woocommerce-mini-cart__content">
							<a class="iu-woocommerce-mini-cart__close">
								<span class="dashicons dashicons-no-alt"></span>
							</a>
							<h4 class="iu-heading">
								Kosár tartalma
								<span class="iu-woocommerce-mini-cart__badge">'.($cart_count > 0 ? $cart_count : '').'</span>
							</h4>
							<div class="inner">
								'.$mini_cart.'
							</div>
						</div>
					</div>' : '').'
				</div>';
			},
			'style' => 'iu-woocommerce-mini-cart-style',
			'view_script' => 'iu-woocommerce-mini-cart-script'
		]);

		iucb_add_block('iu-woocommerce/stock', [
			'title' => 'Product Stock',
			'description' => 'Product Stock',
			'category' => 'iu-woocommerce',
			'editJS' => '
				return "Product Stock";
			',
			'template' => function(){
				global $product;
				if (!$product) return;
				
				$stock = wc_get_stock_html($product);
				$stock = str_replace(__( 'Available on backorder', 'woocommerce' ), '1-5 munkanap visszaigazolástól', $stock);
				return !empty($stock) ? $stock : '<p class="stock in-stock">'.__( 'In stock', 'woocommerce' ).'</p>';
			}
		]);

		wp_register_style('iu-woocommerce-account-menu-style', plugins_url('assets/account-menu.css', __FILE__));
		iucb_add_block('iu-woocommerce/account-menu', [
			'title' => 'Account Menu',
			'description' => 'Account Menu',
			'category' => 'iu-woocommerce',
			'attributes' => [
				'svg' => [
					'type' => 'string',
					'default' => ''
				],
				'height' => [
					'type' => 'string',
					'default' => ''
				],
				'show' => [
					'type' => 'boolean',
					'default' => false
				]
			],
			'fields' => [
				[
					'panel' => 'Settings',
					'fields' => [
						'svg' => [
							'label' => 'SVG',
							'type' => 'textarea'
						],
						'height' => [
							'label' => 'Height',
							'type' => 'text'
						],
						'show' => [
							'label' => 'Show text',
							'type' => 'toggle'
						]
					]
				]
			],
			'editHTML' => '
				const svg = attributes.svg !== "" ? attributes.svg : `<span class="dashicons dashicons-admin-users"></span>`;
				return `<div class="iu-woocommerce-account-menu iu-icon-text">
					<span class="iu-icon iu-icon-icon">`+svg+`</span>
					`+(attributes.show ? `<span class="iu-icon-text-text">Bejelentkezés</span>` : ``)+`
				</div>`;
			',
			'template' => function($attributes){
				return '<a class="iu-woocommerce-account-menu iu-icon-text" href="'.get_permalink(wc_get_page_id('myaccount')).'">
					<span class="iu-icon iu-icon-icon">'.(!empty($attributes['svg']) ? $attributes['svg'] : '<span class="dashicons dashicons-admin-users"></span>').'</span>
					'.($attributes['show'] ? '<span class="iu-icon-text-text">'.(is_user_logged_in() ? get_post(wc_get_page_id('myaccount'))->post_title : 'Bejelentkezés').'</span>' : '').'
				</a>';
			},
			'style' => 'iu-woocommerce-account-menu-style',
		]);

		wp_register_script('iu-woocommerce-search-script', plugins_url('assets/search.js', __FILE__), ['jquery']);
		wp_localize_script('iu-woocommerce-search-script', 'iu_woocommerce_search', [
        	'ajax_url' => admin_url('admin-ajax.php'),
        	'nonce' => wp_create_nonce('iu-woocommerce-search-nonce')
    	]);
		wp_register_style('iu-woocommerce-search-style', plugins_url('assets/search.css', __FILE__));
		iucb_add_block('iu-woocommerce/search', [
			'title' => 'Product Search',
			'description' => 'Product Search',
			'category' => 'iu-woocommerce',
			'attributes' => [
				'svg' => [
					'type' => 'string',
					'default' => ''
				],
				'height' => [
					'type' => 'string',
					'default' => ''
				],
				'type' => [
					'type' => 'string',
					'default' => ''
				]
			],
			'fields' => [
				[
					'panel' => 'Settings',
					'fields' => [
						'svg' => [
							'label' => 'SVG',
							'type' => 'textarea'
						],
						'height' => [
							'label' => 'Height',
							'type' => 'text'
						],
						'type' => [
							'label' => 'Type',
							'type' => 'select',
							'options' => [
								[ 'label' => 'Icon', 'value' => '' ],
								[ 'label' => 'Input', 'value' => 'input' ]
							]
						]
					]
				]
			],
			'editHTML' => '
				const svg = attributes.svg !== "" ? attributes.svg : `<span class="dashicons dashicons-search"></span>`;
				return `<div class="iu-woocommerce-search">`+svg+`</div>`;
			',
			'template' => function($attributes){
				$form = '<div class="iu-woocommerce-search-form">
					<div class="iu-woocommerce-search-input iu-form-field iu-form-text">
						<input type="text" placeholder="Termékek keresése..." />
						<a class="iu-button iu-button-default" '.(!empty($attributes['height']) ? 'style="--iu-woocommerce-search-icon-height:'.$attributes['height'].'"' : '').'>
							'.(!empty($attributes['svg']) ? $attributes['svg'] : '<span class="dashicons dashicons-search"></span>').'
							<span class="iu-woocommerce-search-loader"></span>
						</a>
					</div>
					<div class="iu-woocommerce-search-results"></div>
				</div>';

				if (!empty($attributes['type'])) return '<div class="iu-woocommerce-search">'.$form.'</div';

				return '<div class="iu-woocommerce-search">
					<a class="iu-woocommerce-search-trigger" '.(!empty($attributes['height']) ? 'style="--iu-woocommerce-search-icon-height:'.$attributes['height'].'"' : '').'>
						'.(!empty($attributes['svg']) ? $attributes['svg'] : '<span class="dashicons dashicons-search"></span>').'
					</a>
					<div class="iu-woocommerce-search-panel">
						<a class="iu-woocommerce-search-close"><span class="dashicons dashicons-no-alt"></span></a>
						'.$form.'
					</div>
				</div>';
			},
			'style' => [ 'iu-form-text-style', 'iu-button-style', 'iu-woocommerce-search-style' ],
			'view_script' => 'iu-woocommerce-search-script'
		]);

		/*iucb_add_block('iu-woocommerce/message', [
			'title' => 'Üzenetek',
			'description' => 'Üzenetek',
			'category' => 'iu-woocommerce',
			'template' => function(){
				return '<div class="woocommerce-notices-wrapper">'.wc_print_notices(true).'</div>';
			}
		]);*/

		wp_register_script('iu-woocommerce-product-filter-script', plugins_url('assets/product-filter.js', __FILE__), ['jquery']);
		wp_localize_script('iu-woocommerce-product-filter-script', 'iu_woocommerce_product_filter', [
        	'ajax_url' => admin_url('admin-ajax.php'),
        	'nonce' => wp_create_nonce('iu-woocommerce-product-filter-nonce')
    	]);
		wp_register_style('iu-woocommerce-product-filter-style', plugins_url('assets/product-filter.css', __FILE__));
		iucb_add_block('iu-woocommerce/filter', [
			'title' => 'Product Filter',
			'description' => 'Product Filter',
			'category' => 'iu-woocommerce',
			'editHTML' => '
				return `<div style="background:#eee;display:flex;align-items:center;justify-content:center;width:100%;padding:6rem 0;font-family:monospace;">Product Filter</div>`;
			',
			'template' => function(){
				ob_start();
				
				echo '<form class="iu-woocommerce-product-filter">';
					$this->display_category_filter();
					echo '<div class="iu-woocommerce-product-filter-featured">';
						echo '<label><input type="checkbox" name="featured" value="yes"'.(isset($_GET['featured']) && $_GET['featured'] === 'yes' ? ' checked' : '').' /><span>Kiemelt termékek</span></label>';
					echo '</div>';
					echo '<div class="iu-woocommerce-product-filter-sale">';
						echo '<label><input type="checkbox" name="onsale" value="yes"'.(isset($_GET['onsale']) && $_GET['onsale'] === 'yes' ? ' checked' : '').' /><span>Akciós termékek</span></label>';
					echo '</div>';
					$this->display_price_filter();
				echo '</form>';

				return ob_get_clean();
			},
			'style' => 'iu-woocommerce-product-filter-style',
			'view_script' => 'iu-woocommerce-product-filter-script'
		]);
	}

	function display_category_filter(){
		$categories = get_terms([
	        'taxonomy'   => 'product_cat',
	        'orderby'    => 'name',
	        'order'      => 'ASC',
	        'hide_empty' => true
    	]);

    	$parent_map = [];
	    foreach($categories as $category){
	        $parent_map[$category->parent][] = $category;
	    }

	    $slugs = !empty($_GET['product-categories']) ? explode(',',$_GET['product-categories']) : [];
	    if (empty($slugs) && is_archive('product_cat') && !defined('DOING_AJAX')) $slugs[] = get_queried_object()->slug;

	    $find_is_ancestor = function($parent_id) use (&$find_is_ancestor, $parent_map, $slugs){
	    	if (empty($parent_map[$parent_id])) return false;
	    	
	    	$is_ancestor = false;
	    	foreach($parent_map[$parent_id] as $category){
	    		if (in_array($category->slug, $slugs)) return true;

	    		$is_ancestor = $find_is_ancestor($category->term_id);
	    		if ($is_ancestor) return true;
	    	}

	    	return $is_ancestor;
	    };

		$build_tree = function($parent_id) use (&$build_tree, $parent_map, $slugs, $find_is_ancestor) {
			foreach($parent_map[$parent_id] as $category){
				$is_ancestor = $find_is_ancestor($category->term_id);

				$is_checked = in_array($category->slug, $slugs) || (is_archive('product_cat') && !defined('DOING_AJAX') && get_queried_object()->slug == $category->slug);

				echo '<li'.($is_ancestor ? ' class="open"' : '').'>';
					echo '<label>';
						echo '<input type="checkbox" name="product-categories" value="'.$category->slug.'"'.(in_array($category->slug, $slugs) ? ' checked' : '').' />';
						echo '<span>'.$category->name.'</span>';
					echo '</label>';
					
					if (isset($parent_map[$category->term_id])){
						echo '<span class="dashicons dashicons-plus"></span>';
						echo '<span class="dashicons dashicons-minus"></span>';
						echo '<ul style="display: '.($is_ancestor ? 'block' : 'none').'"">';
							$build_tree($category->term_id);
						echo '</ul>';
					}
				echo '</li>';
			}
		};

		echo '<h4 class="iu-heading">Kategóriák</h4>';
		echo '<ul class="iu-woocommerce-product-filter-categories">';
			$build_tree(0);
		echo '</ul>';
	}

	function display_price_filter(){
		global $wpdb;

		$prices = $wpdb->get_row( "SELECT MIN(min_price) as min_price, MAX(max_price) as max_price FROM {$wpdb->prefix}wc_product_meta_lookup" );
		$min = floor( $prices->min_price );
	    $max = ceil( $prices->max_price );
	    $currency = get_woocommerce_currency_symbol();
	    $separator = get_option('woocommerce_price_thousand_sep', ' ');

	    $min_val = !empty($_GET['price-min']) ? $_GET['price-min'] : 0;
	    $max_val = !empty($_GET['price-max']) ? $_GET['price-max'] : 0;
	    
	    echo '<h4 class="iu-heading">Szűrés ár szerint</h4>';
	    echo '<div class="iu-woocommerce-product-filter-price" data-separator="'.$separator.'">';
	    	echo '<div class="iu-woocommerce-product-filter-price-range" style="--left: 0;--width: 100%;">';
	    		echo '<span class="iu-woocommerce-product-filter-price-track"></span>';
		    	echo '<input type="range" class="iu-woocommerce-product-filter-price-input-min" name="price-min" min="'.$min.'" max="'.$max.'" value="'.(!$min_val ? $min : $min_val).'" />';
		    	echo '<input type="range" class="iu-woocommerce-product-filter-price-input-max" name="price-max" min="'.$min.'" max="'.$max.'" value="'.(!$max_val ? $max : $max_val).'" />';
		    echo '</div>';
		    echo '<div class="iu-woocommerce-product-filter-price-label">';
		    	echo 'Ár: <span class="iu-woocommerce-product-filter-price-label-min">'.$min.'</span> '.$currency.' — <span class="iu-woocommerce-product-filter-price-label-max">'.$max.'</span> '.$currency; 
		    echo '</div>';
		    echo '<a class="iu-button">Szűrés</a>';
	    echo '</div>';
	}

	function before_quantity(){
		echo '<a class="iu-woocommerce-quantity-control minus">-</a>';
	}
	function after_quantity(){
		echo '<a class="iu-woocommerce-quantity-control plus">+</a>';
	}

	function remove_blocks($allowed_block_types){
        foreach($allowed_block_types as $index => $block_name){
            if (strpos($block_name, 'woocommerce/') === 0){
                unset($allowed_block_types[$index]);
            }
        }
        return array_values($allowed_block_types);
	}

	function block_category($categories){
		$categories[] = [
			'slug' => 'iu-woocommerce',
			'title' => 'Woocommerce'
		];

		return $categories;
    }

    function add_to_cart_html($html){
    	ob_start();

    	$product = wc_get_product();
    	if (is_product() && !in_the_loop()){
    		if ($product->is_in_stock()){
	    		$min = $product->get_min_purchase_quantity();
	    		$max = $product->get_max_purchase_quantity();
		    	$qi = woocommerce_quantity_input(
					[
						'min_value'   => $product->get_min_purchase_quantity(),
						'max_value'   => $product->get_max_purchase_quantity(),
						'readonly'    => $min == $max,
						'input_value' => isset($_POST['quantity']) ? wc_stock_amount(wp_unslash($_POST['quantity'])) : $min,
					],
					null,
					false
				);
				if ($max == $min) $qi = str_replace('type="hidden"', 'type="text"', $qi);
				echo $qi;
			} else {
				return '';
			}
    	} 
    	return '<div class="iu-woocommerce-add-to-cart'.(is_product() && !in_the_loop() ? ' single' : '').'">'.ob_get_clean().$html.'</div>';
    }

    function button_classes($args){
    	$args['class'] = preg_replace('/^button /', '', $args['class']).' iu-button iu-button-highlight';
    	return $args;
    }

    function remove_product_title_in_mini_cart($product_name) {
    	$is_inside_mini_cart = did_action( 'woocommerce_before_mini_cart' ) !== did_action( 'woocommerce_after_mini_cart' );
    	$is_mini_cart_ajax = ( defined( 'DOING_AJAX' ) && DOING_AJAX && isset( $_POST['action'] ) && 'woocommerce_get_refreshed_fragments' === $_POST['action'] );
	    if ($is_inside_mini_cart || $is_mini_cart_ajax) {
	    	return '';
	        $product_name = '<div class="product_title">' . $product_name . '</div>'.$quantity;
	    }
	    
	    return $product_name;
	}

	function add_product_title_in_mini_cart($quantity, $cart_item){
		$product = $cart_item['data'];
		return '<div class="product_details"><a class="product_title" href="'.$product->get_permalink().'">'.$product->get_title().'</a>'.$quantity.'</div>';
	}

	function mini_cart_buttons(){
		echo '<div class="iu-button-group iu-button-group-justify-center">';
			echo '<a href="' . esc_url( wc_get_cart_url() ) . '" class="button wc-forward iu-button iu-button-default">' . esc_html__( 'View cart', 'woocommerce' ) . '</a>';
			echo '<a href="' . esc_url( wc_get_checkout_url() ) . '" class="button checkout wc-forward iu-button iu-button-highlight">' . esc_html__( 'Proceed to checkout', 'woocommerce' ) . '</a>';
		echo '</div>';
	}

	function perform_search(){
		check_ajax_referer('iu-woocommerce-search-nonce', 'security');

		$s = sanitize_text_field($_POST['s']);

		global $wpdb;
		$escaped_term = esc_sql($wpdb->esc_like($s));
		$limit = 12;

		$query = "
	        SELECT DISTINCT SQL_CALC_FOUND_ROWS p.ID
	        FROM {$wpdb->posts} p
	        WHERE p.post_type = 'product' 
	        AND p.post_status = 'publish'
	        AND (
	            (p.post_title LIKE '%{$escaped_term}%') OR 
	            (p.post_content LIKE '%{$escaped_term}%') OR 
	            (p.post_excerpt LIKE '%{$escaped_term}%') OR 
	            (p.ID IN (
	                SELECT post_id FROM {$wpdb->postmeta} 
	                WHERE meta_key = '_sku' AND meta_value LIKE '%{$escaped_term}%'
	            ))
	        )
	        ORDER BY p.post_title ASC
	        LIMIT {$limit}
	    ";

    	$results = $wpdb->get_results($query);
    	if (empty($results)) wp_send_json_success([ 'html' => 'Nincsenek találatok.', 'total_count' => 0 ]);

    	$total_found = $wpdb->get_var("SELECT FOUND_ROWS()");

    	$suggestions = '';
    	foreach($results as $row){
            $product = wc_get_product($row->ID);
            if ($product) {
            	$link = esc_url($product->get_permalink());
            	$img = $product->get_image('thumbnail');
                $suggestions .= '<div class="iu-woocommerce-search-item">
                	'.($img ? '<a href="'.$link.'">'.$img.'</a>' : '').'
                	<div class="iu-woocommerce-search-item-details">
                		<h4 class="iu-heading"><a href="'.$link.'">'.$product->get_title().'</a></h4>
                		<span class="price">'.$product->get_price_html().'</span>
                	</div>
                </div>';
            }
        }
        
        wp_send_json_success([ 
            'html' => '<div class="iu-woocommerce-search-items">'.$suggestions.'</div>'.($total_found > $limit ? '<a class="iu-woocommerce-search-more" href="'.home_url('?s='.$s).'">További '.($total_found-$limit).' termék megjelenítése</a>' : ''),
            'total_count' => (int)$total_found
        ]);
	}

	function perform_filter(){
		if (!isset($_GET['action']) || $_GET['action'] !== 'iu_woocommerce_product_filter') return;
		check_ajax_referer('iu-woocommerce-product-filter-nonce', 'security');

		$html = do_shortcode('[products columns="3" limit="12" paginate="true" class="mainquery"]');
		
		global $wp_query;
		wp_send_json_success([
			'html' => $html,
			'asd' => $wp_query->query_vars
		]);
	}

	function filter_atts($out, $pairs, $atts){
		if (!isset($atts['class']) || $atts['class'] !== 'mainquery') return $out;

		if (isset($_GET['featured']) && $_GET['featured'] === 'yes'){
            $out['visibility'] = 'featured';
        }
        if (!empty($_GET['product-categories'])){
        	$out['category'] = $_GET['product-categories'];
        }

        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (is_archive('product_cat') && !$is_ajax){
        	$out['category'] = get_queried_object()->slug;
        }

		return $out;
	}

	function filter_params($query_args, $atts){
		if (!isset($atts['class']) || $atts['class'] !== 'mainquery') return $query_args;

		if (isset($_GET['onsale']) && $_GET['onsale'] === 'yes'){
			$ids = wc_get_product_ids_on_sale();
			if (!empty($ids)){
				if (!empty($query_args['post__id'])) $query_args = array_merge($query_args['post__in'], $ids);
				else $query_args['post__in'] = $ids;
			} else {
				$query_args['post__in'] = [0];
			}
		}

		if (isset($_GET['price-min']) || isset($_GET['price-max'])){
			if ( ! isset( $query_args['meta_query'] ) ) {
		        $query_args['meta_query'] = ['relation' => 'AND'];
		    }

		    $min_price = !empty($_GET['price-min']) ? intval($_GET['price-min']) : 0;
		    $max_price = !empty($_GET['price-max']) ? intval($_GET['price-max']) : 0;

		    if ( $min_price > 0 || $max_price > 0 ) {
		        $price_filter = array(
		            'key'     => '_price',
		            'type'    => 'NUMERIC',
		        );

		        if ( $min_price > 0 && $max_price > 0 ) {
		            $price_filter['value'] = array( $min_price, $max_price );
		            $price_filter['compare'] = 'BETWEEN';
		        } elseif ( $min_price > 0 ) {
		            $price_filter['value'] = $min_price;
		            $price_filter['compare'] = '>=';
		        } else {
		            $price_filter['value'] = $max_price;
		            $price_filter['compare'] = '<=';
		        }

		        $query_args['meta_query'][] = $price_filter;
		    }
		}


		return $query_args;
	}

	function empty_results($attributes){
		echo '<div class="iu-woocommerce-no-products-found">';
			esc_html_e('No products were found matching your selection.', 'woocommerce');
		echo '</div>';
	}
}