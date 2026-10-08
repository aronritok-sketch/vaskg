<?php
	
	// Verzió a fájl módosítási idejéből, hogy frissítés után ne a böngésző régi példánya fusson.
	wp_register_style('vasskisgep-pager-hero-style', get_stylesheet_directory_uri().'/inc/blocks/pager-hero/style.css', [], filemtime(__DIR__.'/style.css'));
	wp_register_script('vasskisgep-pager-hero-script', get_stylesheet_directory_uri().'/inc/blocks/pager-hero/script.js', [], filemtime(__DIR__.'/script.js'));
	iucb_add_block('vasskisgep/pager-hero', [
		'title' => 'Pager Hero',
		'description' => 'Pager Hero',
		'category' => 'widgets',
		'attributes' => [],
		'editJS' => 'return el( wp.blockEditor.InnerBlocks, { allowedBlocks: ["vasskisgep/pager-hero-slide"] } );',
		'saveJS' => 'return el( wp.blockEditor.InnerBlocks.Content, null );',
		'template' => function($attributes, $children){
			$children[0] = str_replace('class="vasskisgep-pager-hero-slide ', 'class="vasskisgep-pager-hero-slide open ', $children[0]);
			return '<div class="vasskisgep-pager-hero">
				'.implode('', $children).'
			</div>';
		},
		'style' => [ 'iu-button-style', 'vasskisgep-pager-hero-style' ],
		'view_script_handles' => [ 'jquery', 'vasskisgep-pager-hero-script' ]
	]);