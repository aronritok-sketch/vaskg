<?php

namespace IUCustomFields;

require_once(__DIR__.'/fields.php');
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
		add_action('init', [$this, 'add_shortcode']);
		add_action('add_meta_boxes', [$this, 'hook_add_meta_boxes']);
		add_action('save_post', [$this, 'hook_save_post']);
		add_action('enqueue_block_editor_assets', [$this, 'enqueue']);
		add_action('rest_api_init', [$this, 'rest_meta_fields']);

		add_filter('posts_where', [$this, 'hook_posts_where']);

	}

	function add_shortcode(){
		add_shortcode('iucf_field', [$this, 'shortcode_field']);
		iusjs_add_shortcode('iucf_field', '
            function(meta, params){
                return meta[params.name];
            }
        ');
	}

	function hook_add_meta_boxes($post_type){
		global $iucf_post_type_custom_fields;

		if (empty($iucf_post_type_custom_fields[$post_type])) return;
		if (use_block_editor_for_post_type($post_type)) return;

		foreach($iucf_post_type_custom_fields[$post_type] as $name => $meta_box){
			$context = empty($meta_box['context']) ? 'side' : $meta_box['context'];
			$priority = empty($meta_box['priority']) ? 'default' : $meta_box['priority'];
			add_meta_box('iu'.$name.'div', $meta_box['label'], [$this, 'render'], null, $context, $priority, ['name' => $name]);
		}
	}

	function render($post, $meta_box){
		global $iucf_post_type_custom_fields;

		$name = $meta_box['args']['name'];
		$fields = $iucf_post_type_custom_fields[$post->post_type][$name]['fields'];

		$html = '';
		foreach($fields as $field_name => $field){
			$html .= Fields::get_html($field_name, $field, $post->ID);
		}

		echo $html;
	}

	function hook_save_post($post_id){
		global $iucf_post_type_custom_fields;

		$post_type = get_post_type($post_id);
		if (empty($iucf_post_type_custom_fields[$post_type])) return;

		if (use_block_editor_for_post_type($post_type) && $_SERVER['REQUEST_METHOD'] === 'POST'){
			$json = json_decode(file_get_contents('php://input'), true);
			$_POST = !empty($json['meta']) ? $json['meta'] : $_POST;
		}

		if (empty($_POST)) return;

		foreach($iucf_post_type_custom_fields[$post_type] as $meta_box){
			foreach($meta_box['fields'] as $name => $field){
				if (!isset($_POST[$name])) continue;

				Fields::save($post_id, $name, $field, $_POST[$name]);
			}
		}	
	}

	function enqueue(){
		global $pagenow, $iucf_post_type_custom_fields;
		
		$post_type = get_post_type();

		if (($pagenow !== 'post.php' && $pagenow !== 'post-new.php') || !use_block_editor_for_post_type($post_type) || empty($iucf_post_type_custom_fields[$post_type])) return;

		wp_enqueue_script('iucf-script', plugins_url('fields.js', __FILE__), [], false, true);

		$custom_fields = $iucf_post_type_custom_fields[$post_type];
		foreach($custom_fields as &$panel){
			foreach($panel['fields'] as $field_name => &$args){
				if ($args['type'] === 'select' && is_callable($args['options'])){
					$args['options'] = call_user_func($args['options']);
				}
			}
		}
		wp_localize_script('iucf-script', 'iucf_custom_fields', $custom_fields);
	}

	function rest_meta_fields(){
		global $iucf_post_type_custom_fields;

		if (!$iucf_post_type_custom_fields) return;

		foreach($iucf_post_type_custom_fields as $post_type => $args){
	    	register_rest_field($post_type, 'meta', [
	    		'get_callback' => function($object) use ($args){
	    			$meta = [];
	    			foreach($args as $panel){
	    				foreach($panel['fields'] as $field_name => $args){
	    					if ($args['type'] === 'callback') continue;
	    					$field = Fields::field_instance($field_name, $args);
	    					$meta[$field_name] = $field->get_value($object['id']);
	    				}
	    			}
	    			return $meta;
	    		},
	    		'schema' => null
	    	]);
	    }
    }

	function hook_posts_where($where){
		$where = preg_replace("/meta_key = \'([^_]+)_[\$]_/i", 'meta_key LIKE \'${1}_%_', $where);
		return $where;
	}

	function shortcode_field($atts){
		return get_post_meta(get_the_ID(), $atts['name'], true);
	}

}