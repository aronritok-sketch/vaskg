<?php

	iucb_add_block('vasskisgep/product-documents', [
		'title' => 'Termék dokumentumok',
		'description' => 'A termékhez csatolt dokumentumok (Termékadatok → Dokumentumok) listában; új lapon, a böngészőben nyílnak meg.',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Termék dokumentumok";',
		'template' => function(){
			if (in_array(get_post_type(), ['iu_template', 'revision'])) return '';
			$product = wc_get_product();
			if (!$product) return '';

			$documents = \VasskisgepTheme\Catalog::documents($product);
			if (empty($documents)) return '<p class="iu-text vk-muted">Ehhez a termékhez jelenleg nincs letölthető dokumentum. Kérdés esetén <a href="'.esc_url(get_permalink(get_page_by_path('kapcsolat'))).'">keressen minket</a>.</p>';

			// A fájl a böngészőben nyílik meg új lapon (ügyfélkérés, 2026-10: ne töltődjön le, ne látszódjon a feltöltés dátuma).
			$open = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg>';
			$html = '<ul class="vk-documents">';
			foreach($documents as $id){
				$file = get_attached_file($id);
				$name = get_the_title($id) ?: ($file ? wp_basename($file) : '');
				$html .= '<li>
					<div class="vk-doc-name"><span class="vk-doc-label">Dokumentum</span><a href="'.esc_url(wp_get_attachment_url($id)).'" target="_blank" rel="noopener">'.esc_html($name).'</a></div>
					<a class="iu-button iu-button-outline vk-doc-download" href="'.esc_url(wp_get_attachment_url($id)).'" target="_blank" rel="noopener" aria-label="'.esc_attr('Megnyitás: '.$name).'"><span class="iu-button-text">'.$open.'<span>Megnyitás</span></span></a>
				</li>';
			}
			return $html.'</ul>';
		}
	]);
