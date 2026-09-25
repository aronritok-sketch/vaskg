<?php

	iucb_add_block('vasskisgep/term-header', [
		'title' => 'Kategória / márka fejléc',
		'description' => 'A kategória vagy márka leírása és logója (archív oldalakra).',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Kategória / márka fejléc";',
		'template' => function(){
			$html = '';
			$shop = get_permalink(wc_get_page_id('shop'));

			if (is_tax(['product_cat', 'product_brand'])){
				$term = get_queried_object();
				$logo = get_term_meta($term->term_id, 'thumbnail_id', true);
				if ($term->taxonomy === 'product_brand' && $logo){
					$html .= '<div class="vk-term-logo">'.wp_get_attachment_image($logo, 'medium').'</div>';
				}
				if (!empty($term->description)){
					$html .= '<div class="vk-term-description iu-text">'.wpautop(wp_kses_post($term->description)).'</div>';
				}
			}

			return $html === '' ? '' : '<div class="vk-term-header">'.$html.'</div>';
		}
	]);
