<?php

	iucb_add_block('vasskisgep/compare', [
		'title' => 'Összehasonlító táblázat',
		'description' => 'Az összehasonlításra kijelölt termékek egymás mellett.',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Összehasonlító táblázat";',
		'template' => function(){
			if (is_admin() || wp_is_json_request()) return '<p class="iu-text">Összehasonlító táblázat</p>';

			$ids = \VasskisgepTheme\Lists::get('compare');
			$shop = get_permalink(wc_get_page_id('shop'));

			if (count($ids) === 0){
				return '<div class="vk-empty-list">
					<p class="iu-text">Még nem jelölt ki terméket összehasonlításra. A termékeknél az összehasonlítás ikonnal legfeljebb '.\VasskisgepTheme\Lists::COMPARE_LIMIT.' terméket tehet egymás mellé.</p>
					<div class="iu-button-group"><a class="iu-button iu-button-default" href="'.esc_url($shop).'"><span class="iu-button-text"><span>Termékek böngészése</span></span></a></div>
				</div>';
			}

			$products = array_values(array_filter(array_map('wc_get_product', $ids)));

			// Az összes termék tulajdonságainak uniója, első előfordulás szerinti sorrendben.
			$attribute_rows = [];
			$values = [];
			foreach($products as $product){
				foreach($product->get_attributes() as $key => $attribute){
					if (!$attribute->get_visible()) continue;
					$label = $attribute->is_taxonomy() ? wc_attribute_label($attribute->get_name()) : $attribute->get_name();
					$value = $attribute->is_taxonomy()
						? implode(', ', wc_get_product_terms($product->get_id(), $attribute->get_name(), [ 'fields' => 'names' ]))
						: implode(', ', $attribute->get_options());
					$attribute_rows[$label] = true;
					$values[$product->get_id()][$label] = $value;
				}
			}

			$cell = function($html){ return '<td>'.$html.'</td>'; };

			$html = '<div class="vk-compare" data-list-page="compare"><div class="vk-compare-scroll"><table class="vk-compare-table">';

			$html .= '<thead><tr><th></th>';
			foreach($products as $product){
				$html .= '<th><div class="vk-compare-product">
					<button type="button" class="vk-compare-remove vk-list-toggle" data-list="compare" data-product="'.$product->get_id().'" aria-pressed="true" title="Eltávolítás">&times;</button>
					<a href="'.esc_url($product->get_permalink()).'">'.$product->get_image('woocommerce_thumbnail').'</a>
					<a class="vk-compare-title" href="'.esc_url($product->get_permalink()).'">'.esc_html($product->get_name()).'</a>
				</div></th>';
			}
			$html .= '</tr></thead><tbody>';

			$rows = [
				'Ár' => function($p){ return '<span class="price">'.$p->get_price_html().'</span>'; },
				'Elérhetőség' => function($p){ return wc_get_stock_html($p) ?: esc_html($p->is_in_stock() ? 'Készleten' : 'Jelenleg nem rendelhető'); },
				'Márka' => function($p){ $b = get_the_terms($p->get_id(), 'product_brand'); return (!empty($b) && !is_wp_error($b)) ? esc_html($b[0]->name) : '–'; },
				'Cikkszám' => function($p){ return $p->get_sku() ? esc_html($p->get_sku()) : '–'; },
			];
			foreach($rows as $label => $render){
				$html .= '<tr><th>'.esc_html($label).'</th>';
				foreach($products as $product) $html .= $cell($render($product));
				$html .= '</tr>';
			}
			foreach(array_keys($attribute_rows) as $label){
				$html .= '<tr><th>'.esc_html($label).'</th>';
				foreach($products as $product){
					$html .= $cell(isset($values[$product->get_id()][$label]) ? wp_kses_post($values[$product->get_id()][$label]) : '–');
				}
				$html .= '</tr>';
			}
			if (!isset($attribute_rows['Súly'])){
				$html .= '<tr><th>Súly</th>';
				foreach($products as $product) $html .= $cell($product->has_weight() ? esc_html(wc_format_weight($product->get_weight())) : '–');
				$html .= '</tr>';
			}

			$html .= '<tr class="vk-compare-actions"><th></th>';
			foreach($products as $product){
				global $post;
				$GLOBALS['product'] = $product;
				ob_start();
				woocommerce_template_loop_add_to_cart();
				$html .= $cell(ob_get_clean());
			}
			$html .= '</tr>';

			return $html.'</tbody></table></div></div>';
		}
	]);
