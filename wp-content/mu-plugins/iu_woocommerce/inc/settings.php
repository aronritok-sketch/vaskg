<?php

namespace IUWoocommerce;

class Settings {
	private static $instance;
	static function get_instance(){
		if (is_null(self::$instance)) self::$instance = new self();
		return self::$instance;
	}

	function __construct(){
		add_filter('iu_theme_settings', [$this, 'settings']);
	}

	function settings($fields){
		$fields['woocommerce'] = [
			'type' => 'tab',
			'title' => 'Woocommerce',
			'fields' => [
				'listviews' => [
					'type' => 'checkbox',
					'label' => 'List views'
				]
			]
		];
		return $fields;
	}
}