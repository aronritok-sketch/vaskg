<?php

namespace IUListCopy;

class Plugin {

	private static $instance;
	static function get_instance(){
		if (is_null(self::$instance)){
			self::$instance = new self();
		}
		return self::$instance;
	}

	function __construct(){
		add_action('admin_init', [$this, 'list_table_action_copy'], 999);
	}

	function list_table_action_copy(){
        $post_types = get_post_types([ 'show_ui' => true, 'copyable' => true ], 'objects');

        foreach($post_types as $post_type){
            add_filter('post_row_actions', function($actions, $post) use ($post_type){
                return $this->display($actions, $post, $post_type);
            }, 10, 2);
            add_filter('page_row_actions', function($actions, $post) use ($post_type){
                return $this->display($actions, $post, $post_type);
            }, 10, 2);
        }

        global $pagenow;
        if (!($pagenow == 'edit.php' && isset($_GET['action']) && $_GET['action'] == 'iu-copy' && isset($_GET['post']))) return;

        $post_id = $this->duplicate($_GET['post']);

        wp_redirect(admin_url('post.php?post='.$post_id.'&action=edit'));
        exit;
    }

    private function display($actions, $post, $post_type){
        if ($post->post_type != $post_type->name) return $actions;

        $url = add_query_arg([ 'action' => 'iu-copy', 'post' => $post->ID ]);
        $actions['copy'] = '<a href="'.$url.'">'.__('Copy').'</a>';
        
        return $actions;
    }

    private function duplicate($post_id){
        $old_post = get_post($post_id);

        $post = [
            'post_title' => $old_post->post_title,
            'post_content' => addslashes($old_post->post_content),
            'post_status' => 'draft',
            'post_type' => $old_post->post_type,
            'post_excerpt' => $old_post->post_excerpt
        ];

        $new_id = wp_insert_post($post);

        $post_type_copy = get_post_types([ 'show_ui' => true], 'objects')[$old_post->post_type]->copyable;
        
        if (true === $post_type_copy || is_array($post_type_copy)){
            if (!isset($post_type_copy['ignore_meta']) || $post_type_copy['ignore_meta'] !== 'all'){

                $ignore_meta = '';
                if (is_array($post_type_copy['ignore_meta'])){
                    $post_type_copy['ignore_meta'] = array_map(function($item){
                        return "'".$item."'";
                    }, $post_type_copy['ignore_meta']);
                    $ignore_meta = " AND meta_key NOT IN (".implode(', ', $post_type_copy['ignore_meta']).")";
                }
                
                global $wpdb;
                $meta_data = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d".$ignore_meta,
                        $post_id,
                    )
                );

                foreach ($meta_data as $meta) {
                    $meta_key = $meta->meta_key;
                    $meta_value = maybe_unserialize($meta->meta_value);
                    add_post_meta($new_id, $meta_key, $meta_value);
                }
            }
        }

        $taxonomies = get_post_taxonomies($post_id);
        foreach ($taxonomies as $taxonomy) {
            wp_set_object_terms(
                $new_id,
                wp_get_object_terms(
                    $post_id,
                    $taxonomy,
                    ['fields' => 'ids']
                ),
                $taxonomy
            );
        }

        return $new_id;
    }

}