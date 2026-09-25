<?php

namespace IUTheme;

class Clean {

    private static $instance;
    public static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_filter('the_generator', '__return_false');
 
        remove_action('wp_head', 'rest_output_link_wp_head', 10);
        remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
        remove_action('template_redirect', 'rest_output_link_header', 11, 0);
         
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'wp_resource_hints', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
        remove_action('wp_head', 'feed_links', 2);

        add_filter('script_loader_src', [$this, 'query_string'], 15); 
        add_filter('style_loader_src', [$this, 'query_string'], 15);

        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles'); 
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji'); 
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('tiny_mce_plugins', [$this, 'disable_emojis_tinymce']);
        add_filter('wp_resource_hints', [$this, 'disable_emojis_remove_dns_prefetch'], 10, 2);

        add_action('do_feed', [$this, 'disable_feed'], 1);
        add_action('do_feed_rdf', [$this, 'disable_feed'], 1);
        add_action('do_feed_rss', [$this, 'disable_feed'], 1);
        add_action('do_feed_rss2', [$this, 'disable_feed'], 1);
        add_action('do_feed_atom', [$this, 'disable_feed'], 1);
        add_action('do_feed_rss2_comments', [$this, 'disable_feed'], 1);
        add_action('do_feed_atom_comments', [$this, 'disable_feed'], 1);

        remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );

        $this->disable_comments();
    }

    function query_string($src){ 
        $parts = explode( '?', $src ); 
        return $parts[0]; 
    }

    function disable_emojis_tinymce($plugins){
        return is_array($plugins) ? array_diff($plugins, ['wpemoji']) : [];
    }

    function disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
        if ('dns-prefetch' == $relation_type){
            $emoji_svg_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/');
            $urls = array_diff($urls, [$emoji_svg_url]);
        }
        return $urls;
    }

    function disable_feed(){
        global $wp_query;
        $wp_query->is_feed = false;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
        die();
    }

    private function disable_comments(){
        add_action('admin_init', function () {
            global $pagenow;
            
            if ($pagenow === 'edit-comments.php' || $pagenow === 'options-discussion.php') {
                wp_redirect(admin_url());
                exit;
            }

            remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');

            foreach (get_post_types() as $post_type) {
                if (post_type_supports($post_type, 'comments')) {
                    remove_post_type_support($post_type, 'comments');
                    remove_post_type_support($post_type, 'trackbacks');
                }
            }
        });

        add_filter('comments_open', '__return_false', 20, 2);
        add_filter('pings_open', '__return_false', 20, 2);

        add_filter('comments_array', '__return_empty_array', 10, 2);
 
        add_action('admin_menu', function () {
            remove_menu_page('edit-comments.php');
            remove_submenu_page('options-general.php', 'options-discussion.php');
        });

        add_action('add_admin_bar_menus', function () {
            if (is_admin_bar_showing()) {
                remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
            }
        });
    }

}