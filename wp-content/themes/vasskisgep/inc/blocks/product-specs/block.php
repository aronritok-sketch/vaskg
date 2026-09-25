<?php

	iucb_add_block('vasskisgep/product-specs', [
		'title' => 'Műszaki adatok',
		'description' => 'A termék összes tulajdonsága táblázatban (cikkszám, márka, súly, méretek, jellemzők).',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Műszaki adatok táblázat";',
		'template' => function(){
			if (in_array(get_post_type(), ['iu_template', 'revision'])) return '';
			$product = wc_get_product();
			if (!$product) return '';

			$rows = [];
			if ($product->get_sku()) $rows['Cikkszám'] = esc_html($product->get_sku());

			$brands = get_the_terms($product->get_id(), 'product_brand');
			if (!empty($brands) && !is_wp_error($brands)){
				$rows['Márka'] = '<a href="'.esc_url(get_term_link($brands[0])).'">'.esc_html($brands[0]->name).'</a>';
			}

			// Tulajdonságok mértékegységgel (a szűrhetők a megadott sorrendben, utána a többi).
			foreach(\VasskisgepTheme\Catalog::product_attributes($product) as $row){
				$rows[$row['label']] = wp_kses_post(\VasskisgepTheme\Catalog::format_unit($row['text']));
			}
			$has_mass = isset($rows['Tömeg']);

			if ($product->has_weight() && !isset($rows['Súly']) && !$has_mass) $rows['Súly'] = esc_html(wc_format_weight($product->get_weight()));
			if ($product->has_dimensions()) $rows['Méretek'] = esc_html(wc_format_dimensions($product->get_dimensions(false)));

			if (empty($rows)) return '<p class="iu-text vk-muted">Ehhez a termékhez nincs megadva műszaki adat.</p>';

			$html = '<table class="vk-specs"><tbody>';
			foreach($rows as $label => $value){
				$html .= '<tr><th>'.esc_html($label).'</th><td>'.$value.'</td></tr>';
			}
			return $html.'</tbody></table>';
		}
	]);
