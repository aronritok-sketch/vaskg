<?php

namespace VasskisgepTheme;

/**
 * Fejléc kereső: a keretrendszer (iu-woocommerce/search) mezője megmarad, a találatok
 * viszont egy megamenü-szerű, teljes szélességű panelben jelennek meg a fejléc alatt:
 * termékek (kép, ár, készlet), kategóriák, Tudástár cikkek, „Összes találat” link.
 * Enter: a keresés oldalra visz (?s=…).
 */
class Search {
    const PRODUCTS = 8;
    const MIN_LENGTH = 2;

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('wp_ajax_vasskisgep_search', [$this, 'ajax']);
        add_action('wp_ajax_nopriv_vasskisgep_search', [$this, 'ajax']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 110);
    }

    function enqueue(){
        $dir = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();
        wp_enqueue_script('vasskisgep-search', $uri.'/assets/search.js', ['jquery'], filemtime($dir.'/assets/search.js'), true);
        wp_localize_script('vasskisgep-search', 'vasskisgepSearch', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'search_url' => home_url('/'),
            'min' => self::MIN_LENGTH
        ]);
    }

    function ajax(){
        $s = isset($_GET['s']) ? trim(wc_clean(wp_unslash($_GET['s']))) : '';
        if (mb_strlen($s) < self::MIN_LENGTH) wp_send_json_success([ 'html' => '', 'total' => 0 ]);

        nocache_headers();
        wp_send_json_success(self::results($s));
    }

    static function results($s){
        /* Termékek (név, leírás, cikkszám). */
        $data_store = \WC_Data_Store::load('product');
        $ids = array_filter(array_map('absint', $data_store->search_products($s, '', false, false, 200)));
        $products = array_values(array_filter(array_map('wc_get_product', $ids), function($p){
            return $p && $p->is_visible() && $p->get_status() === 'publish' && !$p->get_parent_id();
        }));
        $total = count($products);

        /* Kategóriák, amelyek neve illeszkedik. */
        $categories = get_terms([ 'taxonomy' => 'product_cat', 'name__like' => $s, 'hide_empty' => false, 'number' => 8 ]);
        $categories = is_wp_error($categories) ? [] : array_values(array_filter($categories, function($t){ return $t->slug !== 'egyeb' && $t->slug !== 'uncategorized'; }));

        /* Tudástár cikkek. */
        $posts = get_posts([ 'post_type' => 'post', 'post_status' => 'publish', 's' => $s, 'numberposts' => 3, 'suppress_filters' => false ]);

        if (!$total && empty($categories) && empty($posts)){
            return [ 'total' => 0, 'html' => '<div class="vk-search-empty"><strong>Nincs találat erre: „'.esc_html($s).'”</strong><span>Próbáljon rövidebb kifejezést vagy cikkszámot, vagy <a href="'.esc_url(wc_get_page_permalink('shop')).'">böngésszen a termékek között</a>.</span></div>' ];
        }

        $cards = '';
        foreach(array_slice($products, 0, self::PRODUCTS) as $product){
            $url = $product->get_permalink();
            $stock = $product->is_in_stock() ? ($product->is_on_backorder() ? 'Rendelésre' : 'Készleten') : 'Nem rendelhető';
            $stock_class = $product->is_in_stock() ? ($product->is_on_backorder() ? 'on-backorder' : 'in-stock') : 'out-of-stock';
            $cards .= '<a class="vk-search-card" href="'.esc_url($url).'">
                <span class="vk-search-card-image">'.$product->get_image('woocommerce_thumbnail').'</span>
                <span class="vk-search-card-body">
                    <span class="vk-search-card-title">'.esc_html($product->get_name()).'</span>
                    <span class="vk-search-card-stock '.$stock_class.'">'.$stock.'</span>
                    <span class="vk-search-card-price">'.$product->get_price_html().'</span>
                </span>
            </a>';
        }

        $side = '';
        if (!empty($categories)){
            $side .= '<h4 class="vk-search-heading">Kategóriák</h4><ul class="vk-search-links">';
            foreach($categories as $term){
                $parent = $term->parent ? get_term($term->parent, 'product_cat') : null;
                $side .= '<li><a href="'.esc_url(get_term_link($term)).'">'.esc_html($term->name).($parent && !is_wp_error($parent) ? '<small>'.esc_html($parent->name).'</small>' : '').'</a></li>';
            }
            $side .= '</ul>';
        }
        if (!empty($posts)){
            $side .= '<h4 class="vk-search-heading">Tudástár</h4><ul class="vk-search-links">';
            foreach($posts as $post) $side .= '<li><a href="'.esc_url(get_permalink($post)).'">'.esc_html(get_the_title($post)).'</a></li>';
            $side .= '</ul>';
        }

        $all = add_query_arg('s', rawurlencode($s), home_url('/'));
        $html = '<div class="vk-search-grid'.($side === '' ? ' vk-search-noside' : '').'">'
            .($side !== '' ? '<div class="vk-search-side">'.$side.'</div>' : '')
            .'<div class="vk-search-main">'
                .'<h4 class="vk-search-heading">Termékek'.($total ? ' <small>('.$total.')</small>' : '').'</h4>'
                .($cards !== '' ? '<div class="vk-search-cards">'.$cards.'</div>' : '<p class="vk-search-none">Nincs a keresésnek megfelelő termék.</p>')
            .'</div>'
        .'</div>'
        .'<div class="vk-search-foot"><a class="vk-search-all" href="'.esc_url($all).'">Összes találat megtekintése'.($total ? ' ('.$total.' termék)' : '').' <span aria-hidden="true">›</span></a></div>';

        return [ 'total' => $total, 'html' => $html ];
    }
}
