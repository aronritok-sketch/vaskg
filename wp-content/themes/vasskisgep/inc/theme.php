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
require_once(__DIR__.'/orders.php');
require_once(__DIR__.'/checkout.php');
require_once(__DIR__.'/shipping.php');
require_once(__DIR__.'/feed.php');

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
        Orders::get_instance();
        Checkout::get_instance();
        Shipping::get_instance();
        Feed::get_instance();
        Pages::get_instance();
        Search::get_instance();
        Cofidis::get_instance();
        Migration::get_instance();

        add_action('after_setup_theme', [$this, 'editor_styles'], 999);

        // A keretrendszer (iu_theme/inc/clean.php) minden CSS/JS címről leveszi a ?ver= részt, így a böngésző (főleg
        // iPhone-on) frissítés után is a régi fájlt használhatta. A téma saját fájljai módosítási idővel kapnak verziót.
        add_filter('script_loader_src', [$this, 'asset_version'], 20);
        add_filter('style_loader_src', [$this, 'asset_version'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 99);

        add_filter('pre_get_posts', [$this ,'limit_search_to_posts']);
        
        //add_action('woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_add_to_cart', 998);
        remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart');
    }

    function asset_version($src){
        $base = get_stylesheet_directory_uri().'/';
        if (strpos($src, $base) !== 0 || strpos($src, '?') !== false) return $src;
        $file = get_stylesheet_directory().'/'.substr($src, strlen($base));
        return is_file($file) ? $src.'?v='.filemtime($file) : $src;
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