<?php

namespace IUCustomBlocks;

require_once(__DIR__.'/blocks.php');
require_once(__DIR__.'/functions.php');

class Plugin {

	private static $instance;
	static function get_instance(){
		if (is_null(self::$instance)){
			self::$instance = new self();
		}
		return self::$instance;
	}

	function __construct(){
		$this->hooks();
	}

	private function hooks(){
		add_action('admin_enqueue_scripts', [$this, 'hook_admin_enqueue_scripts']);
		add_action('init', [$this, 'hook_register_block_types'], 999);

		/*add_action('init', function(){
			iucb_add_block('test/testblock', [
				'title' => 'Test block',
				'description' => 'Test block',
				'category' => 'text',
				'icon' => 'admin-post',
				'attributes' => [
					'test_text' => [
						'type' => 'string',
						'default' => 'test'
					],
					'test_toggle' => [
						'type' => 'boolean',
						'default' => false
					],
					'test_image' => [
						'type' => 'string'
					],
					'test_select' => [
						'type' => 'string',
						'default' => 'a'
					]
				],
				'fields' => [
					[
						'panel' => 'Settings',
						'fields' => [
							'test_text' => [
								'label' => 'Test text',
								'type' => 'text'
							],
							'test_image' => [
								'label' => 'Test image',
								'type' => 'image',
								'button_label' => 'Select image',
								'remove_label' => 'Remove image'
							],
							'test_toggle' => [
								'label' => 'Test toggle',
								'type' => 'toggle'
							],
							'test_select' => [
								'label' => 'Test select',
								'type' => 'select',
								'options' => [
									[ 'label' => 'a', 'value' => 'a' ],
									[ 'label' => 'b', 'value' => 'b' ]
								]
							]
						]
					]
				],
				'template' => function($atts){
					$atts = wp_parse_args($atts, [
						'test_text' => '',
						'test_image' => ''
					]);

					return '<div>'.var_export($atts, true).'</div>';
				}
			]);
		});*/
	}

	function hook_admin_enqueue_scripts(){
		global $pagenow, $iucb_custom_blocks;

		if ('post.php' == $pagenow || 'post-new.php' == $pagenow){
			$current_screen = get_current_screen();
    		if (method_exists( $current_screen, 'is_block_editor' ) && $current_screen->is_block_editor()){
    			wp_register_script('iucb-script', plugins_url('blocks.js', __FILE__));
				wp_localize_script('iucb-script', 'iucb_custom_blocks', $iucb_custom_blocks);
				wp_enqueue_script('iucb-script');
    		}
		}
	}

	function hook_register_block_types(){
		global $iucb_custom_blocks;

		if (is_null($iucb_custom_blocks)) return;

		foreach($iucb_custom_blocks as $name => $args){
			register_block_type($name, $args);
		}
	}

}