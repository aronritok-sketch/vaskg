<?php

	iucb_add_block('vasskisgep/product-buy', [
		'title' => 'Kosárba rakás (mennyiséggel)',
		'description' => 'Mennyiségválasztó, Kosárba gomb, kedvencek és összehasonlítás a termékoldalon.',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Mennyiség + Kosárba + kedvencek / összehasonlítás";',
		'template' => function(){
			if (in_array(get_post_type(), ['iu_template', 'revision'])) return '';
			global $product;
			$current = $product instanceof \WC_Product ? $product : wc_get_product();
			if (!$current) return '';

			$id = $current->get_id();
			$heart = '<svg width="22" height="19" viewBox="0 0 18 15" fill="none" aria-hidden="true"><path d="M16.75 5.33824C16.75 9.16177 8.75 13.75 8.75 13.75C8.75 13.75 0.75 9.16177 0.75 5.33824C0.75 -0.397053 8.75 -0.397059 8.75 4.30394C8.75 -0.397059 16.75 -0.205885 16.75 5.33824Z" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
			$compare = '<svg width="18" height="20" viewBox="0 0 14 16" fill="none" aria-hidden="true"><rect x="0.75" y="3.375" width="9.625" height="11.375" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M3.375 0.75H11.25C12.3546 0.75 13.25 1.64543 13.25 2.75V12.125" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>';
			$lists = '<button type="button" class="vk-list-toggle vk-buy-wishlist" data-list="wishlist" data-product="'.$id.'" aria-pressed="false" title="Kedvencekhez adom" aria-label="Kedvencekhez adom">'.$heart.'</button>'
				.'<button type="button" class="vk-list-toggle vk-buy-compare" data-list="compare" data-product="'.$id.'" aria-pressed="false" title="Összehasonlítom">'.$compare.'<span>Összehasonlítás</span></button>';

			// Változatos / csoportos termék: a WooCommerce saját űrlapja.
			if (!$current->is_type('simple')){
				ob_start();
				woocommerce_template_single_add_to_cart();
				return '<div class="vk-buy vk-buy-native">'.ob_get_clean().'<div class="vk-buy-lists">'.$lists.'</div></div>';
			}

			if (!$current->is_purchasable() || !$current->is_in_stock()){
				$quote = get_permalink(get_page_by_path('ajanlatkeres'));
				return '<div class="vk-buy vk-buy-unavailable">'
					.'<a class="iu-button iu-button-outline" href="'.esc_url(add_query_arg('termek', rawurlencode($current->get_name()), $quote ?: home_url('/'))).'"><span class="iu-button-text"><span>Ajánlatot kérek</span></span></a>'
					.'<div class="vk-buy-lists">'.$lists.'</div></div>';
			}

			$min = max(1, $current->get_min_purchase_quantity());
			$max = $current->get_max_purchase_quantity();
			$cart = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/><path d="M2 3h3l2.6 12.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.1L21 8H6.2"/></svg>';

			// Űrlap JS nélkül is működik; JS-sel a WooCommerce AJAX kosárba rakása (ajax_add_to_cart) fut.
			return '<form class="vk-buy cart" action="'.esc_url($current->get_permalink()).'" method="post" enctype="multipart/form-data">
				<div class="vk-qty">
					<button type="button" class="vk-qty-btn" data-step="-1" aria-label="Kevesebb">−</button>
					<input type="number" class="qty" name="quantity" value="'.esc_attr($min).'" min="'.esc_attr($min).'"'.($max > 0 ? ' max="'.esc_attr($max).'"' : '').' step="1" inputmode="numeric" aria-label="Mennyiség" />
					<button type="button" class="vk-qty-btn" data-step="1" aria-label="Több">+</button>
				</div>
				<button type="submit" name="add-to-cart" value="'.esc_attr($id).'" class="iu-button iu-button-default vk-buy-button add_to_cart_button ajax_add_to_cart" data-product_id="'.esc_attr($id).'" data-product_sku="'.esc_attr($current->get_sku()).'" data-quantity="'.esc_attr($min).'"><span class="iu-button-text"><span>Kosárba</span>'.$cart.'</span></button>
				<div class="vk-buy-lists">'.$lists.'</div>
			</form>';
		}
	]);
