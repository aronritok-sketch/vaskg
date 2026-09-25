<?php

	iucb_add_block('vasskisgep/products-carousel', [
		'title' => 'Termék karusszel (válogatással)',
		'description' => 'Kiemelt, népszerű, akciós, legújabb vagy szezonális termékek karusszelben.',
		'category' => 'iu-woocommerce',
		'attributes' => [
			'source' => [
				'type' => 'string',
				'default' => 'featured'
			],
			'category' => [
				'type' => 'string',
				'default' => ''
			],
			'limit' => [
				'type' => 'string',
				'default' => '12'
			],
			'columns' => [
				'type' => 'string',
				'default' => '4'
			]
		],
		'fields' => [
			[
				'panel' => 'Beállítások',
				'fields' => [
					'source' => [
						'type' => 'select',
						'label' => 'Termékek',
						'options' => [
							[ 'label' => 'Kiemelt termékek', 'value' => 'featured' ],
							[ 'label' => 'Népszerű (legtöbbet eladott)', 'value' => 'popular' ],
							[ 'label' => 'Akciós termékek', 'value' => 'sale' ],
							[ 'label' => 'Legújabb termékek', 'value' => 'newest' ],
							[ 'label' => 'Szezonális (évszak fülekkel)', 'value' => 'season' ],
							[ 'label' => 'Kategória', 'value' => 'category' ]
						]
					],
					'category' => [
						'type' => 'text',
						'label' => 'Kategória slug (Kategória esetén)'
					],
					'limit' => [
						'type' => 'text',
						'label' => 'Termékek száma'
					],
					'columns' => [
						'type' => 'text',
						'label' => 'Egyszerre látható termékek'
					]
				]
			]
		],
		'editJS' => 'const labels = { featured: "Kiemelt", popular: "Népszerű", sale: "Akciós", newest: "Legújabb", season: "Szezonális", category: "Kategória" }; return `<div style="background:#eee;display:flex;align-items:center;justify-content:center;width:100%;padding:6rem 0;font-family:monospace;">Termék karusszel: ${labels[attributes.source] || attributes.source}</div>`;',
		'template' => function($attributes){
			$limit = max(1, (int)$attributes['limit']);
			$columns = max(1, (int)$attributes['columns']);
			$params = [ 'slidesToShow' => $columns, 'slidesToScroll' => $columns, 'infinite' => false, 'dots' => true ];

			$query = function($args) use ($limit){
				return wc_get_products(array_merge([
					'status' => 'publish',
					'visibility' => 'catalog',
					'limit' => $limit,
					'orderby' => 'date',
					'order' => 'DESC'
				], $args));
			};

			if ($attributes['source'] === 'season'){
				$seasons = \VasskisgepTheme\Shop::SEASONS;
				$current = \VasskisgepTheme\Shop::current_season();

				$panes = [];
				foreach($seasons as $slug => $label){
					$products = $query([ 'tag' => [ $slug ] ]);
					if (!empty($products)) $panes[$slug] = [ $label, $products ];
				}

				// Ha még egy termék sincs évszakhoz rendelve, a legújabbak jelennek meg.
				if (empty($panes)){
					return \VasskisgepTheme\Shop::render_product_carousel($query([]), $params);
				}
				if (!isset($panes[$current])) $current = array_key_first($panes);

				$html = '<div class="vk-season-products">';
				$html .= '<div class="vk-season-tabs" role="tablist">';
				foreach($panes as $slug => [$label]){
					$html .= '<button type="button" role="tab" data-season="'.esc_attr($slug).'" aria-selected="'.($slug === $current ? 'true' : 'false').'" class="'.($slug === $current ? 'vk-active' : '').'">'.esc_html($label).'</button>';
				}
				$html .= '</div>';
				foreach($panes as $slug => [$label, $products]){
					$html .= '<div class="vk-season-pane'.($slug === $current ? ' vk-active' : '').'" data-season="'.esc_attr($slug).'" role="tabpanel">'.\VasskisgepTheme\Shop::render_product_carousel($products, $params).'</div>';
				}
				return $html.'</div>';
			}

			switch($attributes['source']){
				case 'popular':
					$products = $query([ 'orderby' => 'popularity' ]);
					break;
				case 'sale':
					$ids = wc_get_product_ids_on_sale();
					$products = empty($ids) ? [] : $query([ 'include' => $ids ]);
					break;
				case 'newest':
					$products = $query([]);
					break;
				case 'category':
					$products = $query([ 'category' => array_filter([ sanitize_title($attributes['category']) ]) ]);
					break;
				case 'featured':
				default:
					$products = $query([ 'featured' => true ]);
					// Kiemelt termék hiányában ne maradjon üresen a kezdőlap.
					if (empty($products)) $products = $query([]);
			}

			return \VasskisgepTheme\Shop::render_product_carousel($products, $params);
		}
	]);
