<?php

namespace VasskisgepTheme;

require_once(__DIR__.'/blocks.php');
require_once(__DIR__.'/setup.php');
require_once(__DIR__.'/shop.php');
require_once(__DIR__.'/lists.php');
require_once(__DIR__.'/forms.php');
require_once(__DIR__.'/reviews.php');
require_once(__DIR__.'/catalog.php');
require_once(__DIR__.'/listing.php');
require_once(__DIR__.'/megamenu.php');
require_once(__DIR__.'/product.php');
require_once(__DIR__.'/pages.php');
require_once(__DIR__.'/search.php');
require_once(__DIR__.'/cofidis.php');
require_once(__DIR__.'/migration.php');

class Theme {
    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        Blocks::get_instance();
        Setup::get_instance();
        Shop::get_instance();
        Lists::get_instance();
        Forms::get_instance();
        Reviews::get_instance();
        Catalog::get_instance();
        Listing::get_instance();
        MegaMenu::get_instance();
        ProductPage::get_instance();
        Pages::get_instance();
        Search::get_instance();
        Cofidis::get_instance();
        Migration::get_instance();

        add_action('after_setup_theme', [$this, 'editor_styles'], 999);
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 99);

        add_filter('pre_get_posts', [$this ,'limit_search_to_posts']);
        
        //add_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_add_to_cart', 998);
        remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart');
    }

    function editor_styles(){
        add_theme_support('editor-styles');

        add_editor_style('vars.css');
        add_editor_style('style.css');
    }

    function enqueue(){
        wp_enqueue_style('vars', get_stylesheet_directory_uri().'/vars.css');
        wp_enqueue_script('script', get_stylesheet_directory_uri().'/script.js', ['jquery'], false, true);
    }

    function limit_search_to_posts($query) {
        if (!is_admin() && $query->is_search()){
            $query->set('post_type', 'post');
        }
        return $query;
    }

}