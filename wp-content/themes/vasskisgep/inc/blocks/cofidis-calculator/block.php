<?php

	iucb_add_block('vasskisgep/cofidis-calculator', [
		'title' => 'Cofidis áruhitel kalkulátor',
		'description' => 'Havi törlesztőrészlet kalkulátor a Cofidis áruhitellel megvásárolható termékekhez. Csak akkor jelenik meg, ha a termék „Cofidis áruhitel” jelölést kapott és a Beállítások → Cofidis áruhitel fülön meg van adva a THM.',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Cofidis áruhitel kalkulátor";',
		'template' => function(){
			if (in_array(get_post_type(), ['iu_template', 'revision'])) return '';
			$product = wc_get_product();
			if (!$product || !\VasskisgepTheme\Catalog::has_cofidis($product)) return '';
			$config = \VasskisgepTheme\ProductPage::cofidis_config();
			if (!$config) return '';

			$price = (int)round(wc_get_price_to_display($product));
			if ($price <= 0) return '';
			if ($config['min'] > 0 && $price < $config['min']) return '';

			$terms = $config['terms'];
			$term = in_array(12, $terms, true) ? 12 : $terms[0];
			$down = 0;
			$loan = $price - $down;
			if ($config['max'] > 0 && $loan > $config['max']){
				$down = $price - $config['max'];
				$loan = $config['max'];
			}
			$monthly = \VasskisgepTheme\ProductPage::cofidis_installment($loan, $term, $config);
			$format = function($value){ return number_format((float)$value, 0, ',', ' ').' Ft'; };

			$buttons = '';
			foreach($terms as $months){
				$buttons .= '<label class="vk-pill vk-cof-term"><input type="radio" name="vk-cof-term" value="'.(int)$months.'"'.($months === $term ? ' checked' : '').' /><span>'.(int)$months.' hó</span></label>';
			}

			$data = [
				'price' => $price,
				'thm' => $config['thm'],
				'interest' => $config['interest'],
				'min' => $config['min'],
				'max' => $config['max']
			];

			return '<div class="vk-cofidis" data-config="'.esc_attr(wp_json_encode($data)).'">
				<div class="vk-cof-form">
					<h3 class="vk-cof-title"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2.5" width="16" height="19" rx="2"/><path d="M7.5 6h9v3.5h-9zM8 13h.01M12 13h.01M16 13h.01M8 17h.01M12 17h.01M16 17h.01"/></svg>Cofidis Áruhitel Kalkulátor</h3>
					<p class="vk-cof-lead">Állítsd be az önerőt és válaszd ki a futamidőt, hogy lásd a várható havi törlesztőrészletet.</p>
					<div class="vk-cof-grid">
						<div class="vk-cof-field"><span class="vk-cof-label">Termék ára</span><div class="vk-cof-static">'.$format($price).'</div></div>
						<div class="vk-cof-field vk-cof-terms"><span class="vk-cof-label">Futamidő</span><div class="vk-cof-term-list">'.$buttons.'</div></div>
						<div class="vk-cof-field"><label class="vk-cof-label" for="vk-cof-down">Önerő</label><div class="vk-cof-input"><input id="vk-cof-down" type="number" min="0" max="'.$price.'" step="1000" value="'.$down.'" inputmode="numeric" /><span>Ft</span></div><small>0 Ft – '.$format($price).' között adható meg</small></div>
						<div class="vk-cof-field"><span class="vk-cof-label">Hitelösszeg</span><div class="vk-cof-static vk-cof-loan">'.$format($loan).'</div></div>
					</div>
				</div>
				<div class="vk-cof-result">
					<div class="vk-cof-result-head">
						<div><span class="vk-cof-label">Várható havi törlesztőrészlet</span><strong class="vk-cof-monthly">'.$format($monthly).'</strong><span class="vk-cof-per">/ hó</span></div>
						<span class="vk-cof-logo" aria-label="Cofidis">'.\VasskisgepTheme\Catalog::cofidis_badge().'</span>
					</div>
					<dl class="vk-cof-summary">
						<div><dt>Hitelösszeg</dt><dd class="vk-cof-loan">'.$format($loan).'</dd></div>
						<div><dt>Futamidő</dt><dd class="vk-cof-months">'.$term.' hó</dd></div>
						<div><dt>THM</dt><dd>'.esc_html(number_format($config['thm'], 2, ',', ' ')).' %</dd></div>
						<div><dt>Teljes visszafizetendő összeg</dt><dd class="vk-cof-total">'.$format($monthly * $term + $down).'</dd></div>
					</dl>
					<p class="vk-cof-note"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7.5v.5" stroke-linecap="round"/></svg>A kalkuláció tájékoztató jellegű, nem minősül hitelajánlatnak.</p>
					'.($config['disclaimer'] !== '' ? '<p class="vk-cof-disclaimer">'.nl2br(esc_html($config['disclaimer'])).'</p>' : '').'
				</div>
			</div>';
		}
	]);
