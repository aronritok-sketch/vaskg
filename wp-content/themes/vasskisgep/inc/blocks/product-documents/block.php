<?php

	iucb_add_block('vasskisgep/product-documents', [
		'title' => 'Termék dokumentumok',
		'description' => 'A termékhez csatolt letölthető dokumentumok (Termékadatok → Dokumentumok) táblázatban.',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Termék dokumentumok";',
		'template' => function(){
			if (in_array(get_post_type(), ['iu_template', 'revision'])) return '';
			$product = wc_get_product();
			if (!$product) return '';

			$documents = \VasskisgepTheme\Catalog::documents($product);
			if (empty($documents)) return '<p class="iu-text vk-muted">Ehhez a termékhez jelenleg nincs letölthető dokumentum. Kérdés esetén <a href="'.esc_url(get_permalink(get_page_by_path('kapcsolat'))).'">keressen minket</a>.</p>';

			$download = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M4 21h16"/></svg>';
			$html = '<ul class="vk-documents">';
			foreach($documents as $id){
				$file = get_attached_file($id);
				$name = $file ? wp_basename($file) : get_the_title($id);
				$size = $file && file_exists($file) ? size_format(filesize($file)) : '';
				$html .= '<li>
					<div class="vk-doc-name"><span class="vk-doc-label">Dokumentum</span><span>'.esc_html(get_the_title($id) ?: $name).'</span></div>
					<div class="vk-doc-date"><span class="vk-doc-label">Feltöltve</span><span>'.esc_html(get_the_date('Y-m-d', $id)).'</span></div>
					<a class="iu-button iu-button-outline vk-doc-download" href="'.esc_url(wp_get_attachment_url($id)).'" download><span class="iu-button-text">'.$download.'<span>Letöltés</span></span></a>
				</li>';
				unset($size);
			}
			return $html.'</ul>';
		}
	]);
