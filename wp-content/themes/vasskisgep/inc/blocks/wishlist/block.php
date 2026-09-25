<?php

	iucb_add_block('vasskisgep/wishlist', [
		'title' => 'Kedvencek lista',
		'description' => 'A látogató kedvencnek jelölt termékei.',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Kedvencek lista";',
		'template' => function(){
			if (is_admin() || wp_is_json_request()) return '<p class="iu-text">Kedvencek lista</p>';

			$ids = \VasskisgepTheme\Lists::get('wishlist');
			$shop = get_permalink(wc_get_page_id('shop'));

			if (empty($ids)){
				return '<div class="vk-empty-list">
					<p class="iu-text">Még nincs kedvenc terméke. A termékeknél a szív ikonnal jelölheti meg, ami tetszik – így később könnyen megtalálja.</p>
					<div class="iu-button-group"><a class="iu-button iu-button-default" href="'.esc_url($shop).'"><span class="iu-button-text"><span>Termékek böngészése</span></span></a></div>
				</div>';
			}

			$products = array_filter(array_map('wc_get_product', $ids));
			return '<div class="vk-wishlist" data-list-page="wishlist">'.\VasskisgepTheme\Shop::render_product_list(array_values($products), 'vk-wishlist-products').'</div>';
		}
	]);
