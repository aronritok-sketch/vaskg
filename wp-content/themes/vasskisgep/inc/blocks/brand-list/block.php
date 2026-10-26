<?php

	iucb_add_block('vasskisgep/brand-list', [
		'title' => 'Gyártók listája',
		'description' => 'Az összes márka (Termékek → Márkák) logóval és termékszámmal, a márka oldalára linkelve. A Gyártók oldalon van.',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Gyártók listája";',
		'template' => function(){
			if (!taxonomy_exists('product_brand')) return '';
			$brands = get_terms([ 'taxonomy' => 'product_brand', 'hide_empty' => true, 'orderby' => 'name' ]);
			if (is_wp_error($brands) || empty($brands)) return '';

			$html = '';
			foreach($brands as $brand){
				$logo = (int)get_term_meta($brand->term_id, 'thumbnail_id', true);
				$image = $logo ? wp_get_attachment_image($logo, 'medium', false, [ 'alt' => '', 'loading' => 'lazy' ]) : '';
				$html .= '<li><a class="vk-brand-card" href="'.esc_url(get_term_link($brand)).'">'
					.'<span class="vk-brand-card-logo">'.($image !== '' ? $image : '<span>'.esc_html(mb_strtoupper($brand->name)).'</span>').'</span>'
					.($image !== '' ? '<span class="vk-brand-card-name">'.esc_html($brand->name).'</span>' : '')
					.'<span class="vk-brand-card-count">'.esc_html(sprintf('%d termék', $brand->count)).'</span>'
				.'</a></li>';
			}
			return '<ul class="vk-brand-list">'.$html.'</ul>';
		}
	]);
