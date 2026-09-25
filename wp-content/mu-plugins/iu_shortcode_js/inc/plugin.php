<?php

namespace IUShortcodeJs;

require_once('functions.php');

class Plugin {

	private static $instance;
	static function get_instance(){
		if (is_null(self::$instance)){
			self::$instance = new self();
		}
		return self::$instance;
	}

	function __construct(){
		add_action('enqueue_block_editor_assets', [$this, 'enqueue'], 999);
	}

	function enqueue(){
        global $iu_shortcode_js;

        if (empty($iu_shortcode_js)) return;

        $script = 'const iu_shortcode_js = {';
        $functions = [];
        foreach($iu_shortcode_js as $var => $func){
            $functions[] = $var.':'.$func;
        }
        $script .= implode(',', $functions).'};';

        wp_register_script('iu-shortcode-js-extra', false);
        wp_enqueue_script('iu-shortcode-js-extra');
        wp_add_inline_script('iu-shortcode-js-extra', $script);

        wp_enqueue_script('iu-shortcode-js-script', plugins_url('script.js', __FILE__));
    }

    static function add($shortcode, $js){
    	global $iu_shortcode_js;
        $iu_shortcode_js[$shortcode] = $js;
    }

}