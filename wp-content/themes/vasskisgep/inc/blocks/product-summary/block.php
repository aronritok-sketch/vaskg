<?php

	/*
	 * A termékoldal összefoglaló blokkjai (design: Termékoldal):
	 *   vasskisgep/product-sku     – „Cikkszám: …”
	 *   vasskisgep/product-rating  – készlet + csillagos értékelés (a Vélemények fülre mutat)
	 *   vasskisgep/price-box       – régi/új ár, „(Az ár az áfát tartalmazza)”, Cofidis jelvény
	 *   vasskisgep/product-tiles   – a fő tulajdonságok csempékben, ikonnal (a régi webshop piktogramjai, ahol volt)
	 */

	$vk_current_product = function(){
		if (in_array(get_post_type(), ['iu_template', 'revision'])) return null;
		global $product;
		$current = $product instanceof \WC_Product ? $product : wc_get_product();
		return $current ?: null;
	};

	iucb_add_block('vasskisgep/product-sku', [
		'title' => 'Cikkszám',
		'description' => 'A termék cikkszáma („Cikkszám: …”).',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Cikkszám: …";',
		'template' => function() use ($vk_current_product){
			$product = $vk_current_product();
			if (!$product) return '';
			// Változatos terméknél (méretek) a kiválasztott változat cikkszáma jelenik meg (shop.js), ezért mindig ott van.
			if ($product->is_type('variable')){
				return '<p class="vk-sku" data-sku="'.esc_attr($product->get_sku()).'"'.($product->get_sku() ? '' : ' hidden').'>Cikkszám: <span>'.esc_html($product->get_sku()).'</span></p>';
			}
			if (!$product->get_sku()) return '';
			return '<p class="vk-sku">Cikkszám: <span>'.esc_html($product->get_sku()).'</span></p>';
		}
	]);

	iucb_add_block('vasskisgep/product-rating', [
		'title' => 'Készlet és értékelés',
		'description' => 'Készletinformáció és a vásárlói értékelések átlaga csillagokkal.',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Készlet és értékelés";',
		'template' => function() use ($vk_current_product){
			$product = $vk_current_product();
			if (!$product) return '';

			$stock = wc_get_stock_html($product);
			if ($stock === '') $stock = '<p class="stock in-stock">'.esc_html__('In stock', 'woocommerce').'</p>';

			$rating = '';
			$count = $product->get_review_count();
			if ($count > 0){
				$rating = '<a class="vk-rating" href="#vk-reviews" data-vk-tab="reviews">'.\VasskisgepTheme\Reviews::stars((float)$product->get_average_rating()).'<span>('.(int)$count.')</span></a>';
			}
			return '<div class="vk-stock-rating">'.$stock.$rating.'</div>';
		}
	]);

	iucb_add_block('vasskisgep/price-box', [
		'title' => 'Ár doboz',
		'description' => 'Régi és akciós ár, áfa megjegyzés, Cofidis áruhitel jelvény.',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Ár doboz";',
		'template' => function() use ($vk_current_product){
			$product = $vk_current_product();
			if (!$product) return '';

			$regular = '';
			if ($product->is_on_sale() && $product->is_type('simple')){
				$regular = '<del class="vk-price-regular">'.wc_price(wc_get_price_to_display($product, [ 'price' => $product->get_regular_price() ])).'</del>';
				$current = wc_price(wc_get_price_to_display($product));
			} else {
				$current = $product->get_price_html();
			}
			if ($product->get_price() === ''){
				$current = '<span class="vk-price-request">Ár egyeztetés alapján</span>';
			}

			$cofidis = \VasskisgepTheme\Catalog::has_cofidis($product) ? \VasskisgepTheme\Catalog::cofidis_badge() : '';

			return '<div class="vk-price-box">
				<div class="vk-price-box-top">'.$regular.$cofidis.'</div>
				<div class="vk-price-box-main"><span class="vk-price-current">'.$current.'</span>'
				.($product->get_price() !== '' ? '<span class="vk-price-vat">(Az ár az áfát tartalmazza)</span>' : '').'</div>
			</div>';
		}
	]);

	iucb_add_block('vasskisgep/product-tiles', [
		'title' => 'Fő tulajdonságok csempékben',
		'description' => 'A termék összes tulajdonsága ikonos csempékben (elöl a szűrhetők: lökettérfogat, teljesítmény, tömeg, vágásszélesség, garancia stb., utána a többi, pl. vágáshossz).',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Fő tulajdonságok csempékben";',
		'template' => function() use ($vk_current_product){
			$product = $vk_current_product();
			if (!$product) return '';

			// Minden tulajdonság csempében, mint a régi webshopon (ügyfélkérés, 2026-10): elöl a szűrhetők, utána a többi
			// (pl. Vágáshossz, Kapasor szélesség). A hosszú szöveges érték csak a Műszaki adatok táblában marad.
			$rows = array_values(array_filter(\VasskisgepTheme\Catalog::product_attributes($product), function($row){
				return mb_strlen(wp_strip_all_tags($row['text'])) <= 40;
			}));
			if (empty($rows)) return '';

			$icons = [
				'loketterfogat' => '<rect x="5" y="9" width="10" height="11" rx="1.5"/><path d="M8 9V5h4v4M15 12h3l2 2v3h-5M7 13h6M7 16h6"/>',
				'teljesitmeny' => '<path d="M4 17a8 8 0 1 1 16 0"/><path d="M12 17l4-5"/><circle cx="12" cy="17" r="1.2"/>',
				'tomeg' => '<path d="M6 9h12l2 11H4z"/><circle cx="12" cy="6" r="2.2"/>',
				'vagasszelesseg' => '<path d="M3 12h18M3 8v8M21 8v8M7 10l-2 2 2 2M17 10l2 2-2 2"/>',
				'garancia' => '<path d="M12 3l7 3v5c0 4.5-3 8.2-7 10-4-1.8-7-5.5-7-10V6z"/><path d="M9 12l2 2 4-4"/>',
				'hasitoero' => '<path d="M9 2.5h6L12 10z"/><path d="M12 10v2"/><rect x="4" y="12" width="16" height="9" rx="1.5"/><path d="M12 12l-1.2 4.5L12 21"/>',
				'max-agatmero' => '<circle cx="12" cy="12" r="8.5"/><path d="M8 16l8-8M12.5 8H16v3.5M11.5 16H8v-3.5"/>'
			];
			// A régi webshop piktogramjai (lökettérfogat, max. teljesítmény, munkanyomás, vízáteresztés): assets/spec-icons/<slug>.svg
			$icon_dir = get_stylesheet_directory().'/assets/spec-icons/';

			$html = '<div class="vk-tiles vk-tiles-'.count($rows).'">';
			foreach($rows as $row){
				$file = $icon_dir.sanitize_file_name($row['slug']).'.svg';
				$svg = file_exists($file)
					? str_replace('<svg ', '<svg width="18" height="18" aria-hidden="true" ', trim(file_get_contents($file)))
					: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($icons[$row['slug']] ?? '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h8M8 17h5"/>').'</svg>';
				$info = '';
				if ($row['slug'] === 'garancia'){
					$terms = get_permalink(get_page_by_path('aszf'));
					$info = '<a class="vk-tile-info" href="'.esc_url($terms ?: home_url('/')).'" title="A jótállás feltételei az ÁSZF-ben" aria-label="A jótállás feltételei"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5" stroke-linecap="round"/></svg></a>';
				}
				$html .= '<div class="vk-tile vk-tile-'.esc_attr($row['slug']).'">'
					.'<span class="vk-tile-label">'.$svg.esc_html($row['label']).'</span>'
					.'<span class="vk-tile-value">'.wp_kses_post(\VasskisgepTheme\Catalog::format_unit($row['text'])).'</span>'
					.$info
				.'</div>';
			}
			return $html.'</div>';
		}
	]);
