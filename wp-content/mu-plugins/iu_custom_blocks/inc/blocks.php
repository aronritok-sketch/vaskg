<?php

namespace IUCustomBlocks;

class Blocks {

	static function add_block($name, $args){
		global $iucb_custom_blocks;

		if (!is_array($iucb_custom_blocks)){
			$iucb_custom_blocks = [];
		}

		$args['render_callback'] = ['IUCustomBlocks\Blocks', 'render_callback'];
		$iucb_custom_blocks[$name] = $args;
	}

	static function render_callback($args, $content, $block){
		$callable = $block->block_type->template;
		return do_shortcode(call_user_func_array($callable, [ $args, array_map(function($item){ return render_block( $item ); }, $block->parsed_block['innerBlocks']) ]));
	}

}