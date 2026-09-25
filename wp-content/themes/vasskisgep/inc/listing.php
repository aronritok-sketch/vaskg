<?php

namespace VasskisgepTheme;

/**
 * Terméklista a design szerint: felső szűrősáv (Kategória, Gyártó, Ár, Készlet, nézet,
 * rendezés), oldalsó „Részletes szűrés” (tulajdonságok), „Továbbiak mutatása”.
 *
 * A lista a WooCommerce [products class="mainquery"] shortcode-ja, így a keretrendszer
 * (iu_woocommerce) szűrői – kategória, ár, akciós, kiemelt – is érvényesek; a márka,
 * a készlet és a tulajdonságok szűrése itt történik. Szűrés URL-paraméterekkel:
 *   product-categories, product-brand, price-min, price-max, stock, onsale,
 *   attr-{slug} (vesszővel elválasztott slugok), orderby, product-page
 * A lista frissítése ugyanarra az URL-re küldött `vk_listing=results|count` kéréssel történik.
 */
class Listing {
    const PER_PAGE = 12;
    const COLUMNS = 3;

    private static $total = null;

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        // A Termékek oldal régi (iu-woocommerce/filter + [products]) tartalma helyett az új lista.
        add_filter('the_content', [$this, 'replace_legacy_shop_content'], 1);
        add_action('template_redirect', [$this, 'endpoint'], 5);
        add_filter('woocommerce_shortcode_products_query', [$this, 'query_args'], 30, 2);
        add_filter('woocommerce_shortcode_products_query_results', [$this, 'capture_total'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 110);
    }

    function enqueue(){
        $dir = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();
        wp_register_script('vasskisgep-listing', $uri.'/assets/listing.js', ['jquery'], filemtime($dir.'/assets/listing.js'), true);
    }

    /**
     * Szűrhető tulajdonságok: minden globális WooCommerce attribútum. Elöl a
     * setup/catalog/categories.json tulajdonságai a megadott sorrendben, mértékegységgel.
     * @return array [slug => ['label' => …, 'unit' => …]]
     */
    static function filter_attributes(){
        $out = [];
        foreach(Catalog::attributes() as $slug => $attribute){
            if (taxonomy_exists(wc_attribute_taxonomy_name($slug))) $out[$slug] = [ 'label' => $attribute['label'], 'unit' => $attribute['unit'] ?? '' ];
        }
        foreach(wc_get_attribute_taxonomies() as $tax){
            if (isset($out[$tax->attribute_name])) continue;
            $out[$tax->attribute_name] = [ 'label' => $tax->attribute_label, 'unit' => '' ];
        }
        return $out;
    }

    function replace_legacy_shop_content($content){
        if (strpos($content, 'wp:iu-woocommerce/filter') === false || strpos($content, 'vasskisgep/product-listing') !== false) return $content;
        $shop = function_exists('wc_get_page_id') ? wc_get_page_id('shop') : 0;
        if (!$shop || !(is_shop() || is_page($shop))) return $content;
        $managed = Setup::content_file('managed/termekek.html');
        return $managed !== '' ? $managed : '<!-- wp:vasskisgep/product-listing /-->';
    }

    /* ---------- Szűrési paraméterek ---------- */

    static function param($name){
        return isset($_GET[$name]) ? sanitize_text_field(wp_unslash($_GET[$name])) : '';
    }

    static function list_param($name){
        $value = self::param($name);
        return $value === '' ? [] : array_values(array_filter(array_map('sanitize_title', explode(',', $value))));
    }

    /** Márka, készlet és tulajdonság szűrés a lista lekérdezésébe. */
    function query_args($query_args, $atts){
        if (!isset($atts['class']) || $atts['class'] !== 'mainquery') return $query_args;
        if (!isset($query_args['tax_query'])) $query_args['tax_query'] = [];
        if (!isset($query_args['meta_query'])) $query_args['meta_query'] = [];

        $brands = self::list_param('product-brand');
        if (!empty($brands)){
            $query_args['tax_query'][] = [ 'taxonomy' => 'product_brand', 'field' => 'slug', 'terms' => $brands ];
        }

        foreach(array_keys(self::filter_attributes()) as $slug){
            $terms = self::list_param('attr-'.$slug);
            if (empty($terms)) continue;
            $query_args['tax_query'][] = [ 'taxonomy' => wc_attribute_taxonomy_name($slug), 'field' => 'slug', 'terms' => $terms ];
        }

        $stock = self::list_param('stock');
        if (!empty($stock)){
            $statuses = array_intersect($stock, ['instock', 'onbackorder']);
            if (!empty($statuses)){
                $query_args['meta_query'][] = [ 'key' => '_stock_status', 'value' => array_values($statuses), 'compare' => 'IN' ];
            }
        }

        return $query_args;
    }

    function capture_total($results, $shortcode){
        $atts = $shortcode->get_attributes();
        if (isset($atts['class']) && $atts['class'] === 'mainquery') self::$total = (int)$results->total;
        return $results;
    }

    /* ---------- AJAX: ugyanarra az URL-re, vk_listing=results|count ---------- */

    function endpoint(){
        if (empty($_GET['vk_listing'])) return;

        $html = self::render_results();
        $response = [ 'total' => (int)self::$total ];
        if ($_GET['vk_listing'] === 'results') $response['html'] = $html;

        nocache_headers();
        wp_send_json_success($response);
    }

    /**
     * A terméklista HTML-je. A kategória-archívumon a keretrendszer AJAX kérésnél
     * nem szűr az aktuális kategóriára, ezért azt itt adjuk át.
     */
    static function render_results(){
        if (is_tax('product_cat') && self::param('product-categories') === ''){
            $_GET['product-categories'] = get_queried_object()->slug;
        }

        // Az eredményszám, a rendezés és a lapozó a saját szűrősávban/gombban van.
        remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
        remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
        remove_action('woocommerce_after_shop_loop', 'woocommerce_pagination', 10);
        if (class_exists('\IUWoocommerce\Plugin')){
            remove_action('woocommerce_before_shop_loop', [\IUWoocommerce\Plugin::get_instance(), 'list_views']);
        }

        self::$total = null;
        return do_shortcode('[products columns="'.self::COLUMNS.'" limit="'.self::PER_PAGE.'" paginate="true" class="mainquery"]');
    }

    static function total(){
        return (int)self::$total;
    }

    /* ---------- Szűrő-opciók az aktuális környezetben ---------- */

    /** Az aktuális kategória / márka / összes termék azonosítói (a szűrők opcióihoz). */
    static function context_product_ids(){
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'fields' => 'ids',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'tax_query' => [
                [ 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => [ 'exclude-from-catalog' ], 'operator' => 'NOT IN' ]
            ]
        ];
        if (is_tax(['product_cat', 'product_brand', 'product_tag'])){
            $term = get_queried_object();
            $args['tax_query'][] = [ 'taxonomy' => $term->taxonomy, 'field' => 'term_id', 'terms' => [ $term->term_id ], 'include_children' => true ];
        }

        $key = 'vk_ctx_'.md5(wp_json_encode($args)).'_'.\WC_Cache_Helper::get_transient_version('product');
        $ids = get_transient($key);
        if (!is_array($ids)){
            $ids = get_posts($args);
            set_transient($key, $ids, DAY_IN_SECONDS);
        }
        return $ids;
    }

    /** A környezetben előforduló kifejezések egy taxonómiából, darabszámmal. */
    static function context_terms($taxonomy, $ids){
        if (empty($ids) || !taxonomy_exists($taxonomy)) return [];
        $terms = wp_get_object_terms($ids, $taxonomy, [ 'fields' => 'all_with_object_id' ]);
        if (is_wp_error($terms)) return [];

        $out = [];
        foreach($terms as $term){
            if (!isset($out[$term->slug])) $out[$term->slug] = [ 'name' => $term->name, 'slug' => $term->slug, 'count' => 0 ];
            $out[$term->slug]['count']++;
        }

        uasort($out, function($a, $b){
            $na = str_replace(',', '.', $a['name']);
            $nb = str_replace(',', '.', $b['name']);
            if (is_numeric($na) && is_numeric($nb)) return (float)$na <=> (float)$nb;
            return strcoll($a['name'], $b['name']);
        });
        return array_values($out);
    }

    /** Kategória-opciók: az aktuális kategória gyerekei (levélnél a testvérei), a Termékek oldalon a főkategóriák. */
    static function category_options($ids){
        $parent = 0;
        if (is_tax('product_cat')){
            $term = get_queried_object();
            $children = get_terms([ 'taxonomy' => 'product_cat', 'parent' => $term->term_id, 'hide_empty' => false ]);
            $parent = empty($children) ? $term->parent : $term->term_id;
        }

        $terms = get_terms([ 'taxonomy' => 'product_cat', 'parent' => $parent, 'hide_empty' => false, 'orderby' => 'meta_value_num', 'meta_key' => 'order' ]);
        $counts = [];
        foreach(self::context_terms('product_cat', $ids) as $t) $counts[$t['slug']] = $t['count'];

        $out = [];
        foreach($terms as $term){
            if ($term->slug === 'egyeb') continue;
            // A darabszám az alkategóriákkal együtt (a WC term count a szülőkre összesít).
            $count = (int)get_term_meta($term->term_id, 'product_count_product_cat', true);
            if (!$count) $count = (int)$term->count;
            if (!$count && empty($counts[$term->slug])) continue;
            $out[] = [ 'name' => $term->name, 'slug' => $term->slug, 'count' => $count ];
        }
        return $out;
    }

    static function price_range($ids){
        if (empty($ids)) return [0, 0];
        global $wpdb;
        $in = implode(',', array_map('absint', $ids));
        $row = $wpdb->get_row("SELECT MIN(min_price) AS min_price, MAX(max_price) AS max_price FROM {$wpdb->prefix}wc_product_meta_lookup WHERE product_id IN ($in)");
        return [ (int)floor($row->min_price ?? 0), (int)ceil($row->max_price ?? 0) ];
    }
}
