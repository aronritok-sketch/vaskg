<?php

	iucb_add_block('vasskisgep/map', [
		'title' => 'Térkép (kattintásra betöltődő)',
		'description' => 'Google Térkép, amely csak a látogató kattintására töltődik be (adatvédelem: addig nem jut adat a Google-hoz).',
		'category' => 'widgets',
		'attributes' => [
			'query' => [
				'type' => 'string',
				'default' => ''
			],
			'title' => [
				'type' => 'string',
				'default' => 'Térkép'
			]
		],
		'fields' => [
			[
				'panel' => 'Beállítások',
				'fields' => [
					'query' => [
						'type' => 'text',
						'label' => 'Cím vagy hely neve'
					],
					'title' => [
						'type' => 'text',
						'label' => 'Megnevezés'
					]
				]
			]
		],
		'editJS' => 'return "Térkép: " + (attributes.query || "");',
		'template' => function($attributes){
			if (empty($attributes['query'])) return '';

			$embed = 'https://maps.google.com/maps?q='.rawurlencode($attributes['query']).'&z=15&output=embed';
			$link = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($attributes['query']);
			$privacy = get_permalink(get_page_by_path('adatkezelesi-tajekoztato'));

			return '<div class="vk-map" data-src="'.esc_url($embed).'" data-title="'.esc_attr($attributes['title']).'">
				<div class="vk-map-consent">
					<p class="iu-text"><strong>'.esc_html($attributes['title']).'</strong></p>
					<p class="iu-text iu-text-small">A térkép a Google szolgáltatása; betöltésekor a Google adatokat (pl. IP-cím) kap. <a href="'.esc_url($privacy).'">Adatkezelési tájékoztató</a></p>
					<div class="iu-button-group">
						<button type="button" class="iu-button iu-button-highlight vk-map-load"><span class="iu-button-text"><span>Térkép betöltése</span></span></button>
						<a class="iu-button iu-button-default" href="'.esc_url($link).'" target="_blank" rel="noopener"><span class="iu-button-text"><span>Megnyitás a Google Térképen</span></span></a>
					</div>
				</div>
			</div>';
		},
		'style' => [ 'iu-button-style' ]
	]);
