<?php

	iucb_add_block('vasskisgep/recently-viewed', [
		'title' => 'Legutóbb megtekintett termékek',
		'description' => 'A látogató által legutóbb megnézett termékek karusszelben, bevezető kártyával (az aktuális termék nélkül).',
		'category' => 'iu-woocommerce',
		'attributes' => [
			'text' => [
				'type' => 'string',
				'default' => 'Könnyebb a döntés, ha van mihez visszatérni – itt újra megnézheted a korábban megtekintett gépeket, és folytathatod a választást.'
			],
			'limit' => [
				'type' => 'string',
				'default' => '8'
			]
		],
		'fields' => [
			[
				'panel' => 'Beállítások',
				'fields' => [
					'text' => [ 'type' => 'textarea', 'label' => 'Bevezető szöveg' ],
					'limit' => [ 'type' => 'text', 'label' => 'Termékek száma' ]
				]
			]
		],
		'editJS' => 'return "Legutóbb megtekintett termékek";',
		'template' => function($attributes){
			if (in_array(get_post_type(), ['iu_template', 'revision'])) return '';

			$current = is_singular('product') ? get_queried_object_id() : 0;
			$ids = array_diff(\VasskisgepTheme\ProductPage::recently_viewed(), [ $current ]);
			$products = array_filter(array_map('wc_get_product', array_slice($ids, 0, max(1, (int)$attributes['limit']))), function($p){
				return $p && $p->is_visible();
			});
			if (empty($products)) return '';

			$shop = wc_get_page_permalink('shop');
			$intro = '<div class="vk-recent-intro"><p>'.esc_html($attributes['text']).'</p><a class="iu-button iu-button-text vk-recent-all" href="'.esc_url($shop).'"><span class="iu-button-text"><span>Összes termék megtekintése</span><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg></span></a></div>';

			return '<div class="vk-recent">'.$intro.'<div class="vk-recent-list">'.\VasskisgepTheme\Shop::render_product_carousel(array_values($products), [ 'slidesToShow' => 3, 'slidesToScroll' => 1 ]).'</div></div>';
		}
	]);
