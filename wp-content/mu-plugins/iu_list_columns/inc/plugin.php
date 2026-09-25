<?php

namespace IUListColumns;

class Plugin {

	private static $instance;
	static function get_instance(){
		if (is_null(self::$instance)){
			self::$instance = new self();
		}
		return self::$instance;
	}

	function __construct(){
		add_action('admin_init', [$this, 'columns'], 999);
	}

	function columns(){
        $post_types = get_post_types([ 'show_ui' => true, 'list_columns' => true ], 'objects');

        foreach($post_types as $post_type){
            add_filter('manage_'.$post_type->name.'_posts_columns', function($columns) use ($post_type){
                foreach($post_type->list_columns as $name => $args){
                    if (in_array($name, ['title', 'date'])){
                        $columns[$name] = $args['label'];
                        continue;
                    }

                    $position = isset($args['position']) ? $args['position'] : 1;
                    $columns = array_slice($columns, 0, $position, true) + [ $name => $args['label'] ] + array_slice($columns, $position, count($columns)-1, true);
                }
                return $columns;
            });

            add_action('manage_'.$post_type->name.'_posts_custom_column', function($column_name, $post_id) use ($post_type){
                foreach($post_type->list_columns as $name => $args){
                    if ($column_name == $name){
                        if (isset($args['render_callback']) && is_callable($args['render_callback'])){
                            call_user_func_array($args['render_callback'], [ $post_id ]);
                        }
                    }
                }
            }, 10, 2);

            add_filter('manage_edit-'.$post_type->name.'_sortable_columns', function($columns) use ($post_type){
                foreach($post_type->list_columns as $name => $args){
                    if (empty($args['sortable'])) continue;

                    $columns[$name] = $name;
                }
                return $columns;
            });

            add_filter('request', function($vars) use ($post_type){
                foreach($post_type->list_columns as $name => $args){
                    if (!isset($vars['orderby']) || $vars['orderby'] != $name) continue;

                    if (isset($args['query_callback']) && is_callable($args['query_callback'])){
                        $vars = call_user_func_array($args['query_callback'], [ $vars ]);
                    }
                }
                return $vars;
            });

            $style = '';
            foreach($post_type->list_columns as $name => $args){
                if (isset($args['width'])){
                    $style .= '.column-'.$name.'{width:'.$args['width'].'}';
                }
            }
        
            if (!empty($style)){
                add_action('admin_print_styles-edit.php',function() use ($style, $post_type){
                    if (get_post_type() == $post_type->name){
                        echo '<style>'.$style.'</style>';
                    }
                });
            }
        }
    }

}