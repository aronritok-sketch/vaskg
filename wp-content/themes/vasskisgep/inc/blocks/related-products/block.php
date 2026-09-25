<?php

	iucb_add_block('vasskisgep/related-products', [
		'title' => 'Hasonló termékek',
		'description' => 'A termékkel azonos kategóriájú termékek karusszelben (termékoldalra).',
		'category' => 'iu-woocommerce',
		'attributes' => [
			'limit' => [
				'type' => 'string',
				'default' => '8'
			]
		],
		'fields' => [
			[
				'panel' => 'Beállítások',
				'fields' => [
					'limit' => [
						'type' => 'text',
						'label' => 'Termékek száma'
					]
				]
			]
		],
		'editJS' => 'return "Hasonló termékek karusszel";',
		'template' => function($attributes){
			if (in_array(get_post_type(), ['iu_template', 'revision'])) return '';
			$product = wc_get_product();
			if (!$product) return '';

			$limit = max(1, (int)$attributes['limit']);
			$ids = array_merge($product->get_upsell_ids(), wc_get_related_products($product->get_id(), $limit));
			$ids = array_slice(array_unique(array_diff($ids, [ $product->get_id() ])), 0, $limit);
			$products = array_filter(array_map('wc_get_product', $ids), function($p){ return $p && $p->is_visible(); });
			if (empty($products)) return '';

			return '<div class="vk-related">'.\VasskisgepTheme\Shop::render_product_carousel(array_values($products), [ 'slidesToShow' => 3, 'slidesToScroll' => 1 ]).'</div>';
		}
	]);
