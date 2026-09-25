<?php

	iucb_add_block('vasskisgep/pager-hero-slide', [
		'title' => 'Pager Hero Slide',
		'description' => 'Pager Hero Slide',
		'category' => 'widgets',
		'parent' => [ 'vasskisgep/pager-hero' ],
		'attributes' => [
			'bg' => [
				'type' => 'number'
			],
			'closed' => [
				'type' => 'string',
				'default' => ''
			],
			'open' => [
				'type' => 'string',
				'default' => ''
			]
		],
		'fields' => [
			[
				'panel' => 'Settings',
				'fields' => [
					'bg' => [
						'type' => 'image',
						'label' => 'Háttérkép'
 					],
 					'closed' => [
 						'type' => 'textarea',
 						'label' => 'Zárva'
 					],
 					'open' => [
 						'type' => 'textarea',
 						'label' => 'Nyitva'
 					]
				]
			]
		],
		'editJS' => 'return "Pager Hero Slide";',
		'template' => function($attributes){
			return '<div class="vasskisgep-pager-hero-slide">
				<div class="vasskisgep-pager-hero-slide-inner">
					<div class="vasskisgep-pager-hero-slide-bg" style="background-image: url('.wp_get_attachment_image_url($attributes['bg'], 'full').')"></div>
					<div class="vasskisgep-pager-hero-slide-content">
						<div class="vasskisgep-pager-hero-slide-closed">'.$attributes['closed'].'</div>
						<div class="vasskisgep-pager-hero-slide-open">'.$attributes['open'].'</div>
					</div>
				</div>
			</div>';
		}
	]);