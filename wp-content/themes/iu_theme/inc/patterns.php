<?php

namespace IUTheme;

class Patterns {

	private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
    	add_action('init', [$this, 'register_post_type']);
    	add_action('admin_menu', [$this, 'remove_submenu']);
    	add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    	add_action('admin_footer', [$this, 'style_fix']);
    	add_action('save_post_iu_pattern', [$this, 'save_meta']);
    	add_action('rest_api_init', [$this, 'rest_meta_field']);

    	add_filter('block_categories_all', [$this, 'block_category']);
    	add_filter('should_load_block_editor_scripts_and_styles', [$this, 'pattern_variations']);
    }

    function register_post_type(){
    	register_post_type('iu_pattern', [
    		'labels' => [
    			'name' => 'Pattern-ek',
    			'singular' => 'Pattern',
    			'add_new' => 'Pattern hozzáadása',
                'add_new_item' => 'Pattern hozzáadása',
                'edit_item' => 'Pattern szerkesztése',
                'new_item' => 'Új pattern'
    		],
    		'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'themes.php',
            'show_in_rest' => true,
            'supports' => ['title', 'editor'],
            'list_columns' => [
                'synced' => [
                    'label' => 'Synced',
                    'position' => 2,
                    'render_callback' => [$this, 'list_column_synced']
                ]
            ],
            'list_views' => [
            	'unsynced' => [
            		'label' => 'Unsynced',
            		'query_var' => 'status',
         			'count_callback' => [$this, 'views_unsynced_count'],
            		'query_callback' => [$this, 'views_unsynced_query']
            	],
            	'synced' => [
            		'label' => 'Synced',
            		'query_var' => 'status',
         			'count_callback' => [$this, 'views_synced_count'],
            		'query_callback' => [$this, 'views_synced_query']
            	]
            ]
    	]);
    }

    function list_column_synced($post_id){
    	echo !empty(get_post_meta($post_id, 'iu_pattern_synced', true)) ? 'Synced' : 'Unsynced';
    }

    function views_synced_count(){
		global $wpdb;

		$sql = "SELECT COUNT(*) as count FROM {$wpdb->posts} AS p JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id AND pm.meta_key = 'iu_pattern_synced' WHERE p.post_type = 'iu_pattern' AND (p.post_status != 'auto-draft' AND p.post_status != 'trash')";
		return $wpdb->get_var($sql);
	}

	function views_synced_query($vars){
		if (!isset($vars['meta_query'])) $vars['meta_query'] = [];
		$vars['meta_query'][] = [
			'synced' => [
				'key' => 'iu_pattern_synced',
				'compare' => 'EXISTS'
			]
		];
		return $vars;
	}

	function views_unsynced_count(){
		global $wpdb;

		$sql = "SELECT COUNT(*) as count FROM {$wpdb->posts} AS p LEFT JOIN {$wpdb->postmeta} AS pm ON p.ID = pm.post_id AND pm.meta_key = 'iu_pattern_synced' WHERE p.post_type = 'iu_pattern' AND (p.post_status != 'auto-draft' AND p.post_status != 'trash') AND pm.post_id IS null";
		return $wpdb->get_var($sql);
	}

	function views_unsynced_query($vars){
		if (!isset($vars['meta_query'])) $vars['meta_query'] = [];
		$vars['meta_query'][] = [
			'unsynced' => [
				'key' => 'iu_pattern_synced',
				'compare' => 'NOT EXISTS'
			]
		];
		return $vars;
	}

    function remove_submenu(){
    	remove_submenu_page('themes.php', 'site-editor.php?path=/patterns');
    }

    function enqueue_scripts(){
    	global $current_screen;
    	if (!$current_screen->is_block_editor()) return;
    	
    	wp_enqueue_script('iu_patterns_script', get_template_directory_uri().'/inc/patterns.js');
    }

    function style_fix(){
    	?><style>
    		.iu-pattern-create-button-modal {
	    		z-index: 1000001;
	    	}
	    	.block-editor-block-types-list__list-item:has(.editor-block-list-item-iu-pattern) {
	    		display: none;
	    	}
	    	.components-menu-item__button[href="site-editor.php?path=%2Fpatterns"] {
	    		display: none;
	    	}
	    </style><?php
    }

    function save_meta($post_id){
    	if (!wp_is_rest_endpoint()) return;

    	$json = json_decode(file_get_contents('php://input'), true);
    	if (empty($json['meta']) || empty($json['meta']['iu_pattern_synced'])) return;

    	update_post_meta($post_id, 'iu_pattern_synced', 1);
    }

    function rest_meta_field(){
    	register_rest_field('iu_pattern','meta',[
    		'get_callback' => function($object){
    			return [ 'iu_pattern_synced' => get_post_meta($object['id'], 'iu_pattern_synced', true) ];
    		},
    		'schema' => null
    	]);
    }

    function block_category($categories){
		$categories[] = [
			'slug' => 'patterns',
			'title' => 'Patterns'
		];

		return $categories;
    }

    function pattern_variations($is_block_editor_screen){
    	if (!$is_block_editor_screen) return $is_block_editor_screen;

    	$posts = get_posts([
    		'post_type' => 'iu_pattern',
    		'posts_per_page' => -1
    	]);

    	$data = [];
    	foreach($posts as $post){
    		$data[] = [
    			'id' => $post->ID,
    			'name' => $post->post_name,
    			'title' => $post->post_title
    		];
    	}

	    wp_register_script('iu-patterns-script', false);
	    wp_localize_script('iu-patterns-script', 'iuPatterns', $data);
	    wp_enqueue_script('iu-patterns-script');

	    remove_filter('should_load_block_editor_scripts_and_styles', [$this, 'pattern_variations']);
	    return $is_block_editor_screen;
    }
}