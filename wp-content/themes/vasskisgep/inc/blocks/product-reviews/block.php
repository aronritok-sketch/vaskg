<?php

	iucb_add_block('vasskisgep/product-reviews', [
		'title' => 'Termék értékelések',
		'description' => 'Vásárlói értékelések összesítővel, listával és értékelő űrlappal (WooCommerce értékelések).',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Termék értékelések";',
		'template' => function(){
			if (in_array(get_post_type(), ['iu_template', 'revision'])) return '';
			$product = wc_get_product();
			if (!$product) return '';
			if (get_option('woocommerce_enable_reviews', 'yes') !== 'yes') return '';

			$count = $product->get_review_count();
			$summary = '';
			if ($count > 0){
				$average = (float)$product->get_average_rating();
				$summary = '<div class="vk-reviews-summary"><strong>'.esc_html(number_format_i18n($average, 1)).'</strong>'.\VasskisgepTheme\Reviews::stars($average).'<span>'.sprintf('%d értékelés alapján', $count).'</span></div>';
			}

			// A WooCommerce értékelés-sablonja (single-product-reviews.php) a comments_template()-en keresztül.
			// Blokk-témánál a WooCommerce nem cseréli le a sablont, ezért itt adjuk meg.
			global $withcomments;
			$withcomments = true;
			$template = function(){ return wc_locate_template('single-product-reviews.php'); };
			add_filter('comments_template', $template, 99);
			ob_start();
			comments_template();
			remove_filter('comments_template', $template, 99);
			return '<div class="vk-product-reviews" id="vk-reviews">'.$summary.ob_get_clean().'</div>';
		}
	]);
