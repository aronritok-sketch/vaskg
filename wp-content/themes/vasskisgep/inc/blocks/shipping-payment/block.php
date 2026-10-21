<?php

	iucb_add_block('vasskisgep/shipping-payment', [
		'title' => 'Szállítási és fizetési módok',
		'description' => 'A bolt aktív szállítási módjai (díjjal) és fizetési módjai a WooCommerce beállításaiból.',
		'category' => 'iu-woocommerce',
		'attributes' => [],
		'editJS' => 'return "Szállítási és fizetési módok";',
		'template' => function(){
			if (in_array(get_post_type(), ['iu_template', 'revision'])) return '';
			$product = wc_get_product();

			$icons = [
				'truck' => '<path d="M1 5h13v10H1zM14 8h4l3 3v4h-7z"/><circle cx="5.5" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/>',
				'pin' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
				'card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>',
				'doc' => '<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h8M9 17h8"/>',
				'cash' => '<path d="M12 2v20M17 6.5C17 4.6 14.8 4 12 4S7 5 7 7.5s3 3 5 3.5 5 1.2 5 4-2.5 3-5 3-5-.8-5-3"/>',
				'loan' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9h10M7 13h4M13 13h4M7 17h4M13 17h4"/>'
			];
			$icon = function($name) use ($icons){
				return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$icons[$name].'</svg>';
			};

			/* Szállítás: a magyarországi zóna aktív módjai. */
			$shipping = '';
			$zone = WC_Shipping_Zones::get_zone_matching_package([ 'destination' => [ 'country' => 'HU', 'state' => '', 'postcode' => '' ] ]);
			foreach($zone->get_shipping_methods(true) as $method){
				$cost = '';
				if ($method->id === 'free_shipping' || $method->id === 'local_pickup' || $method->id === 'pickup_location'){
					$cost = $method->id === 'free_shipping' || (float)$method->get_option('cost') == 0 ? '0 Ft' : wc_price((float)$method->get_option('cost'));
				} elseif ($method->id === \VasskisgepTheme\Shipping::METHOD){
					// Súly szerint: ennek a terméknek (1 db) a díja.
					if ($product){
						$gross = \VasskisgepTheme\Shipping::gross_cost($method, \VasskisgepTheme\Shipping::product_weight($product), (float)wc_get_price_including_tax($product));
						$cost = $gross === null ? 'Egyedi egyeztetés' : ($gross > 0 ? wc_price($gross) : 'Ingyenes');
					} else {
						$cost = 'Súly szerint';
					}
				} elseif ($method->id === 'flat_rate'){
					$raw = str_replace([' ', ','], ['', '.'], (string)$method->get_option('cost'));
					if (is_numeric($raw)){
						$net = (float)$raw;
						$tax = 0;
						if (wc_tax_enabled() && $method->get_option('tax_status') === 'taxable'){
							$rates = WC_Tax::find_shipping_rates([ 'country' => 'HU', 'state' => '', 'postcode' => '', 'city' => '', 'tax_class' => '' ]);
							$tax = array_sum(WC_Tax::calc_shipping_tax($net, $rates));
						}
						$cost = wc_price($net + $tax);
					} else {
						$cost = 'Súly szerint';
					}
				}
				$shipping .= '<li>'.$icon(in_array($method->id, [ 'flat_rate', 'free_shipping', \VasskisgepTheme\Shipping::METHOD ], true) ? 'truck' : 'pin').'<span>'.esc_html($method->get_title()).'</span><strong>'.wp_kses_post($cost).'</strong></li>';
			}

			/* Fizetés: az engedélyezett fizetési módok. */
			$payment = '';
			$map = [ 'barion' => 'card', 'bacs' => 'doc', 'cod' => 'cash', 'cheque' => 'doc' ];
			foreach(WC()->payment_gateways()->payment_gateways() as $gateway){
				if ($gateway->enabled !== 'yes') continue;
				$type = 'card';
				foreach($map as $key => $value){ if (strpos($gateway->id, $key) === 0) $type = $value; }
				$payment .= '<li>'.$icon($type).'<span>'.esc_html(wp_strip_all_tags($gateway->get_title())).'</span></li>';
			}
			if ($product && \VasskisgepTheme\Catalog::has_cofidis($product)){
				$payment .= '<li>'.$icon('loan').'<span>Cofidis áruhitel</span></li>';
			}

			if ($shipping === '' && $payment === '') return '';
			$more = get_permalink(get_page_by_path('szallitas-es-fizetes'));

			return '<div class="vk-ship-pay">'
				.($shipping !== '' ? '<h4 class="iu-heading">Szállítási módok:</h4><ul>'.$shipping.'</ul>' : '')
				.($payment !== '' ? '<h4 class="iu-heading">Fizetési módok:</h4><ul>'.$payment.'</ul>' : '')
				.($more ? '<a class="vk-ship-pay-more" href="'.esc_url($more).'">Részletek a szállításról és fizetésről ›</a>' : '')
			.'</div>';
		}
	]);
