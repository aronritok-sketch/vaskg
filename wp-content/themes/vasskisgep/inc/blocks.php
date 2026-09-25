<?php

namespace VasskisgepTheme;

class Blocks {
	private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
    	add_action('init', [$this, 'register_blocks']);
    }

    function register_blocks(){
    	$dirs = array_filter(glob(__DIR__.'/blocks/*'), 'is_dir');
    	foreach($dirs as $dir){
    		require_once($dir.'/block.php');
    	}
    }
}