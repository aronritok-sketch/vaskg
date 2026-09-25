<?php

namespace IUListViews;

class Plugin {

	private static $instance;
	static function get_instance(){
		if (is_null(self::$instance)){
			self::$instance = new self();
		}
		return self::$instance;
	}

	function __construct(){
		add_action('admin_init', [$this, 'views'], 999);
	}

	function views(){
        $post_types = get_post_types([ 'show_ui' => true ], 'objects');

        foreach($post_types as $post_type){
            if (!isset($post_type->list_views)) continue;

            add_filter('views_edit-'.$post_type->name, function($views) use ($post_type){
                foreach($post_type->list_views as $name => $args){
                    $count = isset($args['count_callback']) && is_callable($args['count_callback']) ? call_user_func_array($args['count_callback'], []) : -1;

                    if (empty($count)) continue;

                    $link = add_query_arg([ $args['query_var'] => $name ]);
                    if (isset($_GET['post_status']) && $_GET['post_status'] == 'trash') {
                        $link = remove_query_arg('post_status', $link);
                    }
                    $current = isset($_GET[$args['query_var']]) && $_GET[$args['query_var']] == $name;
                    
                    $views[$name] = '<a href="'.$link.'" '.($current ? 'class="current"' : '').'>'.$args['label'].'<span class="count">('.$count.')</span></a>';
                }
                return $views;
            });

            add_filter('request', function($vars) use ($post_type){
                foreach($post_type->list_views as $name => $args){
                    if (!isset($_GET[$args['query_var']]) || $_GET[$args['query_var']] !== $name) continue;

                    if (isset($args['query_callback']) && is_callable($args['query_callback'])){
                        $vars = call_user_func_array($args['query_callback'], [ $vars ]);
                    }
                }
                return $vars;
            });
        }
    }

}