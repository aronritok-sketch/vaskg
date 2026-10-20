<?php

	iucb_add_block('vasskisgep/search-results', [
		'title' => 'Termék találatok',
		'description' => 'A keresésnek megfelelő termékek (keresés oldalra).',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Termék találatok";',
		'template' => function(){
			if (!is_search()) return '';
			$s = get_search_query(false);
			if ($s === '') return '';

			$ids = \VasskisgepTheme\Search::product_ids(wc_clean($s), 48);
			$products = array_values(array_filter(array_map('wc_get_product', $ids), function($p){
				return $p && $p->is_visible() && $p->get_status() === 'publish' && !$p->get_parent_id();
			}));

			if (empty($products)){
				return '<p class="iu-text">Nincs a keresésnek megfelelő termék. Próbáljon rövidebb kifejezést, vagy <a href="'.esc_url(get_permalink(wc_get_page_id('shop'))).'">böngésszen a kategóriák között</a>.</p>';
			}

			return '<p class="iu-text vk-result-count">'.count($products).' termék</p>'.\VasskisgepTheme\Shop::render_product_list($products, 'vk-search-products');
		}
	]);
