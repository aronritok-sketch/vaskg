<?php

	iucb_add_block('vasskisgep/brand-list', [
		'title' => 'Gyártók listája',
		'description' => 'Az összes márka (Termékek → Márkák) logóval és termékszámmal, a márka oldalára linkelve. A Gyártók oldalon van.',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Gyártók listája";',
		'template' => function(){
			if (!taxonomy_exists('product_brand')) return '';
			$brands = \VasskisgepTheme\Catalog::sorted_brands(true);
			if (empty($brands)) return '';

			$html = '';
			foreach($brands as $brand){
				$logo = (int)get_term_meta($brand->term_id, 'thumbnail_id', true);
				$image = $logo ? wp_get_attachment_image($logo, 'medium', false, [ 'alt' => '', 'loading' => 'lazy' ]) : '';
				$html .= '<li><a class="vk-brand-card" href="'.esc_url(get_term_link($brand)).'">'
					.'<span class="vk-brand-card-logo">'.($image !== '' ? $image : '<span>'.esc_html(mb_strtoupper(wp_specialchars_decode($brand->name, ENT_QUOTES))).'</span>').'</span>'
					.($image !== '' ? '<span class="vk-brand-card-name">'.esc_html($brand->name).'</span>' : '')
					.'<span class="vk-brand-card-count">'.esc_html(sprintf('%d termék', $brand->count)).'</span>'
				.'</a></li>';
			}
			// Kiemelt akció (pl. Orange Week) a gyártók fölött: telefonon a Termékek menü ide visz (Dávid, 2026-11-19).
			$promo = \VasskisgepTheme\MegaMenu::promo_links();
			return ($promo !== '' ? '<div class="vk-promo-strip">'.$promo.'</div>' : '').'<ul class="vk-brand-list">'.$html.'</ul>';
		}
	]);
