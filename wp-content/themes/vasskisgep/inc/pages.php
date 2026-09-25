<?php

namespace VasskisgepTheme;

/**
 * Oldal-szintű beállítások.
 *
 * „Saját oldalfejléc”: a keretrendszer minden oldalra ugyanazt a „Single Oldal” sablont
 * teszi (kiemelt kép + cím + morzsamenü). Ha az oldalon ez be van kapcsolva, a sablonból
 * csak az oldal tartalma marad, így az oldal a saját fejlécét hozhatja (pl. Rólunk).
 */
class Pages {
    const CUSTOM_HEADER = '_vk_custom_header';

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('init', [$this, 'register_meta']);
        add_action('add_meta_boxes_page', [$this, 'meta_box']);
        add_action('save_post_page', [$this, 'save'], 10, 2);
        add_filter('the_content', [$this, 'strip_template_header'], 0);
        add_filter('body_class', [$this, 'body_class']);
    }

    static function has_custom_header($post_id = null){
        $post_id = $post_id ?: (is_page() ? get_queried_object_id() : 0);
        return $post_id && get_post_meta($post_id, self::CUSTOM_HEADER, true) === '1';
    }

    function register_meta(){
        register_post_meta('page', self::CUSTOM_HEADER, [ 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => function(){ return current_user_can('edit_pages'); } ]);
    }

    function meta_box(){
        add_meta_box('vk-page-options', 'Oldal megjelenése', function($post){
            wp_nonce_field('vk_page_options', 'vk_page_options_nonce');
            echo '<label><input type="checkbox" name="vk_custom_header" value="1"'.checked(self::has_custom_header($post->ID), true, false).' /> Saját oldalfejléc (a sablon kiemelt képe, címe és morzsamenüje nélkül; a fejlécet az oldal tartalma adja)</label>';
        }, 'page', 'side');
    }

    function save($post_id, $post){
        if (!isset($_POST['vk_page_options_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vk_page_options_nonce'])), 'vk_page_options')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_page', $post_id)) return;
        if (!empty($_POST['vk_custom_header'])) update_post_meta($post_id, self::CUSTOM_HEADER, '1');
        else delete_post_meta($post_id, self::CUSTOM_HEADER);
    }

    /** A „Single Oldal” sablonból csak az oldal tartalma (iu/content) marad. */
    function strip_template_header($content){
        if (!is_page() || !self::has_custom_header()) return $content;
        if (strpos($content, '<!-- wp:iu/content /-->') === false || strpos($content, '<!-- wp:iu/title') === false) return $content;
        return '<!-- wp:iu/content /-->';
    }

    function body_class($classes){
        if (is_page() && self::has_custom_header()) $classes[] = 'vk-custom-header';
        return $classes;
    }
}
