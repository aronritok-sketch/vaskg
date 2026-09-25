<?php

	iucb_add_block('vasskisgep/product-actions', [
		'title' => 'Kedvencek / összehasonlítás gombok',
		'description' => 'A termék kedvencekhez adása és összehasonlítása.',
		'category' => 'iu-woocommerce',
		'attributes' => [
			'compact' => [
				'type' => 'boolean',
				'default' => false
			]
		],
		'fields' => [
			[
				'panel' => 'Beállítások',
				'fields' => [
					'compact' => [
						'type' => 'toggle',
						'label' => 'Csak ikonok (elavult: a kártyán automatikusan megjelenik)'
					]
				]
			]
		],
		'editJS' => 'return "Kedvencek / összehasonlítás gombok";',
		'template' => function($attributes){
			global $product;
			$current = $product instanceof \WC_Product ? $product : wc_get_product();
			if (!$current) return '';

			// Kártyán a gombokat a Lists osztály a termékképre teszi (régebbi mintákban maradt blokk).
			if (!empty($attributes['compact'])) return '';
			return \VasskisgepTheme\Lists::buttons($current->get_id());
		}
	]);
