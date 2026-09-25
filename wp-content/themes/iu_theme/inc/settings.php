<?php

namespace IUTheme;

class Settings {

	private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
    	add_action('init', [$this, 'init']);
    	add_action('iu_theme_head_end', function(){ echo ius_get('iu_theme','head_end'); });
    	add_action('iu_theme_body_begin', function(){ echo ius_get('iu_theme','body_begin'); });
    	add_action('iu_theme_body_end', function(){ echo ius_get('iu_theme','body_end'); });
    }

    function init(){
    	ius_register_settings('iu_theme', [
    		'title' => 'Sablon beállítások',
    		'menu_title' => 'Beállítások',
    		'location' => 'themes.php',
    		'fields' => apply_filters('iu_theme_settings', [
                'theme' => [
                    'type' => 'tab',
                    'title' => 'Theme',
                    'fields' => [
                        'head_end' => [
                            'type' => 'textarea',
                            'label' => 'Before &lt;/head&gt;'
                        ],
                        'body_begin' => [
                            'type' => 'textarea',
                            'label' => 'After &lt;body&gt;'
                        ],
                        'body_end' => [
                            'type' => 'textarea',
                            'label' => 'Before &lt;/body&gt;'
                        ]
                    ]
                ]
    		])
    	]);
    }
}