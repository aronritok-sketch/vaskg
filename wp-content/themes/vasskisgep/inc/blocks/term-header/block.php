<?php

	iucb_add_block('vasskisgep/term-header', [
		'title' => 'Kategória / márka fejléc',
		'description' => 'A kategória vagy márka leírása, logója és alkategóriái (archív oldalakra).',
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
				$parent = $term->taxonomy === 'product_cat' ? $term->term_id : null;
			} else {
				$parent = 0;
			}

			// Alkategóriák (a Termékek oldalon a főkategóriák).
			if (!is_null($parent)){
				$children = get_terms([ 'taxonomy' => 'product_cat', 'parent' => $parent, 'hide_empty' => false, 'orderby' => 'meta_value_num', 'meta_key' => 'order' ]);
				$children = array_filter($children, function($t){ return $t->slug !== 'egyeb'; });
				if (!empty($children)){
					$html .= '<ul class="vk-subcategories">';
					foreach($children as $child){
						$html .= '<li><a href="'.esc_url(get_term_link($child)).'">'.esc_html($child->name).'</a></li>';
					}
					$html .= '</ul>';
				}
			}

			return $html === '' ? '' : '<div class="vk-term-header">'.$html.'</div>';
		}
	]);
