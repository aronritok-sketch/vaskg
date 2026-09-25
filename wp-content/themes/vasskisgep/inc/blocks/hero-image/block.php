<?php

	iucb_add_block('vasskisgep/hero-image', [
		'title' => 'Fejléckép',
		'description' => 'Ferde aljú fejléckép archív oldalakra. Kategóriánál a kategória képét, bejegyzésnél és oldalnál a kiemelt képet használja, ha van.',
		'category' => 'widgets',
		'attributes' => [
			'image' => [
				'type' => 'number'
			],
			'height' => [
				'type' => 'string',
				'default' => '15rem'
			]
		],
		'fields' => [
			[
				'panel' => 'Beállítások',
				'fields' => [
					'image' => [
						'type' => 'image',
						'label' => 'Alapértelmezett kép'
					],
					'height' => [
						'type' => 'text',
						'label' => 'Magasság'
					]
				]
			]
		],
		'editJS' => 'return "Fejléckép";',
		'template' => function($attributes){
			$image_id = !empty($attributes['image']) ? (int)$attributes['image'] : 0;

			if (is_tax('product_cat')){
				$thumb = (int)get_term_meta(get_queried_object_id(), 'thumbnail_id', true);
				if ($thumb) $image_id = $thumb;
			} elseif (is_singular() && !is_singular('product') && has_post_thumbnail(get_queried_object_id())){
				$image_id = get_post_thumbnail_id(get_queried_object_id());
			}

			$url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : '';
			$height = !empty($attributes['height']) ? $attributes['height'] : '15rem';

			return '<div class="vk-hero-image clip-down" style="--vk-hero-height:'.esc_attr($height).'">'
				.($url ? '<img src="'.esc_url($url).'" alt="" loading="eager" />' : '')
				.'</div>';
		}
	]);
