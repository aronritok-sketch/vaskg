<?php

	iucb_add_block('vasskisgep/cross-sells', [
		'title' => 'Kapcsolódó termékek (oldalsáv)',
		'description' => 'A termék keresztértékesítési termékei (Termékadatok → Összekapcsolt termékek → Keresztértékesítés) függőleges listában, kosárba gombbal.',
		'category' => 'iu-woocommerce',
		'attributes' => [
			'title' => [
				'type' => 'string',
				'default' => 'Kapcsolódó termékek'
			]
		],
		'fields' => [
			[
				'panel' => 'Beállítások',
				'fields' => [
					'title' => [
						'type' => 'text',
						'label' => 'Cím'
					]
				]
			]
		],
		'editJS' => 'return "Kapcsolódó termékek (oldalsáv)";',
		'template' => function($attributes){
			if (in_array(get_post_type(), ['iu_template', 'revision'])) return '';
			$product = wc_get_product();
			if (!$product) return '';

			$items = array_filter(array_map('wc_get_product', $product->get_cross_sell_ids()), function($p){
				return $p && $p->is_visible();
			});
			if (empty($items)) return '';

			$cart = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/><path d="M2 3h3l2.6 12.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.1L21 8H6.2"/></svg>';
			$arrow = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 15 6-6 6 6"/></svg>';

			$html = '';
			foreach($items as $item){
				$url = $item->get_permalink();
				$stock = $item->is_in_stock() ? ($item->is_on_backorder() ? 'Rendelésre' : 'Készleten') : 'Nem rendelhető';
				$stock_class = $item->is_in_stock() ? ($item->is_on_backorder() ? 'on-backorder' : 'in-stock') : 'out-of-stock';

				if ($item->is_type('simple') && $item->is_purchasable() && $item->is_in_stock()){
					$button = '<a href="'.esc_url($item->add_to_cart_url()).'" data-quantity="1" data-product_id="'.esc_attr($item->get_id()).'" data-product_sku="'.esc_attr($item->get_sku()).'" class="vk-xs-cart add_to_cart_button ajax_add_to_cart" rel="nofollow" aria-label="'.esc_attr('Kosárba: '.$item->get_name()).'">'.$cart.'</a>';
				} else {
					$button = '<a href="'.esc_url($url).'" class="vk-xs-cart" aria-label="'.esc_attr($item->get_name()).'"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></a>';
				}

				$html .= '<li class="vk-xs-item">
					<a class="vk-xs-image" href="'.esc_url($url).'">'.$item->get_image('woocommerce_gallery_thumbnail').'</a>
					<div class="vk-xs-body">
						<a class="vk-xs-title" href="'.esc_url($url).'">'.esc_html($item->get_name()).'</a>
						<span class="vk-xs-stock '.$stock_class.'">'.$stock.'</span>
						<div class="vk-xs-bottom"><span class="vk-xs-price">'.$item->get_price_html().'</span>'.$button.'</div>
					</div>
				</li>';
			}

			$nav = count($items) > 4
				? '<div class="vk-xs-nav"><button type="button" class="vk-xs-prev" aria-label="Előző">'.$arrow.'</button><button type="button" class="vk-xs-next" aria-label="Következő">'.$arrow.'</button></div>'
				: '';

			return '<div class="vk-cross-sells">'
				.($attributes['title'] !== '' ? '<h4 class="iu-heading vk-xs-heading">'.esc_html($attributes['title']).'</h4>' : '')
				.'<ul class="vk-xs-list">'.$html.'</ul>'.$nav
			.'</div>';
		}
	]);
