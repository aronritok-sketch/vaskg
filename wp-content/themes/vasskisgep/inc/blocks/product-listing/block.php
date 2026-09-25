<?php

	iucb_add_block('vasskisgep/product-listing', [
		'title' => 'Terméklista szűrőkkel',
		'description' => 'Felső szűrősáv (kategória, gyártó, ár, készlet, nézet, rendezés), részletes szűrés tulajdonságok szerint, „Továbbiak mutatása” gomb. Termékek oldalra és kategória/márka archívumra.',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return `<div style="background:#eee;display:flex;align-items:center;justify-content:center;width:100%;padding:6rem 0;font-family:monospace;">Terméklista szűrőkkel</div>`;',
		'template' => function(){
			if (is_admin() || wp_is_json_request()) return '';

			$L = '\VasskisgepTheme\Listing';
			wp_enqueue_script('vasskisgep-listing');

			$ids = $L::context_product_ids();
			$results = $L::render_results();
			$total = $L::total();

			$chevron = '<svg class="vk-dd-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';

			$pill = function($name, $value, $label, $checked, $type = 'checkbox'){
				return '<label class="vk-pill"><input type="'.$type.'" name="'.esc_attr($name).'" value="'.esc_attr($value).'"'.($checked ? ' checked' : '').' /><span>'.$label.'</span></label>';
			};

			$foot = '<div class="vk-dd-foot">
				<button type="button" class="vk-dd-apply iu-button iu-button-default"><span class="iu-button-text"><span>(<span class="vk-dd-total">'.esc_html($total).'</span>) termék megtekintése</span></span></button>
				<button type="button" class="vk-dd-clear iu-button iu-button-outline"><span class="iu-button-text"><span>Kijelölések törlése</span></span></button>
			</div>';

			$dropdown = function($key, $label, $options_html, $selected_count, $class = '') use ($chevron, $foot){
				return '<div class="vk-dd '.esc_attr($class).'" data-key="'.esc_attr($key).'">
					<button type="button" class="vk-dd-toggle" aria-expanded="false"><span class="vk-dd-label">'.esc_html($label).'</span><span class="vk-dd-count">'.($selected_count ? ' ('.$selected_count.')' : '').'</span>'.$chevron.'</button>
					<div class="vk-dd-panel">
						<div class="vk-dd-options">'.$options_html.'</div>
						'.$foot.'
					</div>
				</div>';
			};

			/* ---------- Felső sáv ---------- */

			$bar = '';

			$categories = $L::category_options($ids);
			if (!empty($categories)){
				$selected = $L::list_param('product-categories');
				$html = '';
				foreach($categories as $c) $html .= $pill('product-categories', $c['slug'], esc_html($c['name']).' <small>'.$c['count'].'</small>', in_array($c['slug'], $selected, true));
				$bar .= $dropdown('product-categories', 'Kategória', $html, count(array_intersect($selected, wp_list_pluck($categories, 'slug'))));
			}

			if (!is_tax('product_brand')){
				$brands = $L::context_terms('product_brand', $ids);
				if (!empty($brands)){
					$selected = $L::list_param('product-brand');
					$html = '';
					foreach($brands as $b) $html .= $pill('product-brand', $b['slug'], esc_html(mb_strtoupper($b['name'])).' <small>'.$b['count'].'</small>', in_array($b['slug'], $selected, true));
					$bar .= $dropdown('product-brand', 'Gyártó', $html, count($selected));
				}
			}

			[$min, $max] = $L::price_range($ids);
			$price_min = $L::param('price-min');
			$price_max = $L::param('price-max');
			$price_html = '<div class="vk-price-fields">
				<label><span>Minimum</span><input type="number" name="price-min" min="0" step="1000" placeholder="'.esc_attr(number_format($min, 0, ',', ' ')).'" value="'.esc_attr($price_min).'" /><em>Ft</em></label>
				<span class="vk-price-sep">–</span>
				<label><span>Maximum</span><input type="number" name="price-max" min="0" step="1000" placeholder="'.esc_attr(number_format($max, 0, ',', ' ')).'" value="'.esc_attr($price_max).'" /><em>Ft</em></label>
			</div>';
			$bar .= $dropdown('price', 'Ár', $price_html, ($price_min !== '' || $price_max !== '') ? 1 : 0, 'vk-dd-price');

			$stock = $L::list_param('stock');
			$onsale = $L::param('onsale') === 'yes';
			$stock_html = $pill('stock', 'instock', 'Készleten', in_array('instock', $stock, true))
				.$pill('stock', 'onbackorder', 'Rendelhető (1-5 munkanap)', in_array('onbackorder', $stock, true))
				.$pill('onsale', 'yes', 'Akciós', $onsale);
			$bar .= $dropdown('stock', 'Készlet', $stock_html, count($stock) + ($onsale ? 1 : 0));

			$view = isset($_COOKIE['iu-woocommerce-list-view']) && $_COOKIE['iu-woocommerce-list-view'] === 'list' ? 'list' : 'grid';
			$orderby = $L::param('orderby');
			$sorts = [ '' => 'Alapértelmezett', 'popularity' => 'Népszerűség', 'date' => 'Legújabb', 'price' => 'Ár szerint növekvő', 'price-desc' => 'Ár szerint csökkenő' ];
			$sort_html = '';
			foreach($sorts as $value => $label) $sort_html .= $pill('orderby', $value, $label, $orderby === $value, 'radio');

			$bar_right = '<div class="vk-view">
					<button type="button" class="vk-view-btn'.($view === 'grid' ? ' vk-active' : '').'" data-view="grid" aria-label="Rácsnézet"><svg width="22" height="20" viewBox="0 0 26 24" aria-hidden="true"><rect x="14.4" width="10.8" height="9.9" rx="1" fill="currentColor"/><rect width="10.8" height="9.9" rx="1" fill="currentColor"/><rect y="13.2" width="10.8" height="9.9" rx="1" fill="currentColor"/><rect x="14.4" y="13.2" width="10.8" height="9.9" rx="1" fill="currentColor"/></svg></button>
					<button type="button" class="vk-view-btn'.($view === 'list' ? ' vk-active' : '').'" data-view="list" aria-label="Listanézet"><svg width="23" height="19" viewBox="0 0 27 22" aria-hidden="true"><rect width="6" height="6" rx="1" fill="currentColor"/><rect y="8" width="6" height="6" rx="1" fill="currentColor"/><rect y="16" width="6" height="6" rx="1" fill="currentColor"/><rect x="8" width="19" height="6" rx="1" fill="currentColor"/><rect x="8" y="8" width="19" height="6" rx="1" fill="currentColor"/><rect x="8" y="16" width="19" height="6" rx="1" fill="currentColor"/></svg></button>
				</div>'
				.'<div class="vk-dd vk-dd-sort" data-key="orderby"><button type="button" class="vk-dd-toggle" aria-expanded="false"><span class="vk-dd-label">Rendezés</span><span class="vk-dd-count"></span>'.$chevron.'</button><div class="vk-dd-panel"><div class="vk-dd-options">'.$sort_html.'</div></div></div>';

			/* ---------- Részletes szűrés ---------- */

			$side = '';
			foreach($L::filter_attributes() as $slug => $attribute){
				$terms = $L::context_terms(wc_attribute_taxonomy_name($slug), $ids);
				$selected = $L::list_param('attr-'.$slug);
				$unit = $attribute['unit'] !== '' ? ' '.$attribute['unit'] : '';
				$html = '';
				foreach($terms as $t) $html .= $pill('attr-'.$slug, $t['slug'], esc_html($t['name'].$unit).' <small>'.$t['count'].'</small>', in_array($t['slug'], $selected, true));
				if ($html === '') $html = '<p class="vk-dd-empty">Ebben a listában nincs ilyen tulajdonságú termék.</p>';
				$label = $attribute['label'].($attribute['unit'] !== '' ? ' ('.$attribute['unit'].')' : '');
				$side .= $dropdown('attr-'.$slug, $label, $html, count($selected), 'vk-dd-side'.(empty($terms) ? ' vk-dd-disabled' : ''));
			}
			if ($side === '') $side = '<p class="vk-dd-empty">Nincs szűrhető tulajdonság. Termékek → Tulajdonságok alatt vehetők fel.</p>';

			$shown = min($total, \VasskisgepTheme\Listing::PER_PAGE * max(1, (int)$L::param('product-page')));

			return '<div class="vk-listing" data-total="'.esc_attr($total).'">
				<div class="vk-filter-bar">
					<div class="vk-filter-bar-left">'.$bar.'</div>
					<div class="vk-filter-bar-right">'.$bar_right.'</div>
				</div>
				<div class="vk-listing-body">
					<aside class="vk-filter-side"><button type="button" class="vk-side-toggle">Részletes szűrés</button><div class="vk-side-inner"><h4 class="iu-heading">Részletes szűrés</h4>'.$side.'</div></aside>
					<div class="vk-results-wrap">
						<div class="vk-results">'.$results.'</div>
						<div class="vk-more"'.($total <= $shown ? ' hidden' : '').'>
							<p class="vk-more-count"><span class="vk-shown">'.esc_html($shown).'</span> a <span class="vk-total">'.esc_html($total).'</span> termékből</p>
							<button type="button" class="vk-more-btn"><span class="vk-more-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></span><span>Továbbiak mutatása</span></button>
						</div>
					</div>
				</div>
			</div>';
		}
	]);
