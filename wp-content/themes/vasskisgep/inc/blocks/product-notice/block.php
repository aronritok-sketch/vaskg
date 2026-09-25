<?php

	iucb_add_block('vasskisgep/product-notice', [
		'title' => 'Termék tájékoztató doboz',
		'description' => 'A termék „Tájékoztató doboz” mezőjének szövege kiemelt dobozban (üresen nem jelenik meg).',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Termék tájékoztató doboz";',
		'template' => function(){
			if (in_array(get_post_type(), ['iu_template', 'revision'])) return '';
			$product = wc_get_product();
			if (!$product) return '';
			$notice = \VasskisgepTheme\ProductPage::notice($product);
			if ($notice === '') return '';

			$lines = array_filter(array_map('trim', preg_split('/\R/', $notice)));
			return '<div class="vk-notice"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5" stroke-linecap="round"/></svg><p>'.implode('<br>', array_map('esc_html', $lines)).'</p></div>';
		}
	]);
