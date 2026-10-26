<?php

namespace VasskisgepTheme;

/**
 * Árukereső termékfeed (XML): /arukereso.xml (vagy /?vk_feed=arukereso). Ezt a címet kell az Árukereső
 * Partner Portálon megadni (a régi bolt feedje helyett, élesítéskor).
 *
 * Csak a látható, áras, rendelhető egyszerű termékek kerülnek bele. Készlet: Delivery_Time 1 = „Raktáron”,
 * a „visszaigazolástól 1-5 munkanap” (utánrendelhető) termék 5. Szállítási díj (Delivery_Cost): a súly
 * szerinti díjtábla alapján egy darabra, „ingyenes” a díjmentes határ felett; ismeretlen súlynál vagy a
 * táblán kívül (csak személyes átvétel) üresen marad, akkor a Partner Portál díjtáblája számít.
 * Óránként újragenerálódik (a termék mentésekor azonnal).
 */
class Feed {
    const QUERY_VAR = 'vk_feed';
    const CACHE = 'vk_feed_arukereso';
    const BACKORDER_DAYS = 5;
    const IMAGES = 5;

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('init', [__CLASS__, 'rewrite']);
        add_filter('query_vars', function($vars){ $vars[] = self::QUERY_VAR; return $vars; });
        add_action('template_redirect', [$this, 'serve'], 1);
        add_filter('redirect_canonical', function($redirect){ return get_query_var(self::QUERY_VAR) ? false : $redirect; });
        foreach([ 'woocommerce_update_product', 'woocommerce_new_product', 'woocommerce_delete_product', 'woocommerce_trash_product' ] as $hook){
            add_action($hook, [__CLASS__, 'flush']);
        }
    }

    static function rewrite(){
        add_rewrite_rule('^arukereso\.xml$', 'index.php?'.self::QUERY_VAR.'=arukereso', 'top');
    }

    static function url(){
        return get_option('permalink_structure') ? home_url('/arukereso.xml') : add_query_arg(self::QUERY_VAR, 'arukereso', home_url('/'));
    }

    static function flush(){
        delete_transient(self::CACHE);
    }

    function serve(){
        if (get_query_var(self::QUERY_VAR) !== 'arukereso') return;
        $xml = get_transient(self::CACHE);
        if (!is_string($xml) || $xml === ''){
            $xml = self::build();
            set_transient(self::CACHE, $xml, HOUR_IN_SECONDS);
        }
        status_header(200);
        nocache_headers();
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');
        echo $xml;
        exit;
    }

    private static function tag($name, $value, $cdata = false){
        $value = (string)$value;
        if ($value === '') return '';
        return '<'.$name.'>'.($cdata ? '<![CDATA['.str_replace(']]>', ']]]]><![CDATA[>', $value).']]>' : esc_xml($value)).'</'.$name.'>';
    }

    /** A termék legmélyebb kategóriájának útvonala: „Kerti és erdészeti gépek > Fűnyírók > Benzines fűnyírók”. */
    private static function category($product){
        $best = [];
        foreach($product->get_category_ids() as $term_id){
            $path = [];
            for ($term = get_term($term_id, 'product_cat'); $term && !is_wp_error($term); $term = $term->parent ? get_term($term->parent, 'product_cat') : null){
                array_unshift($path, $term->name);
            }
            if (count($path) > count($best)) $best = $path;
        }
        return implode(' > ', $best);
    }

    private static function text($html, $limit = 1000){
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags(str_replace('<', ' <', (string)$html)), ENT_QUOTES, 'UTF-8')));
        return mb_strlen($text) > $limit ? rtrim(mb_substr($text, 0, $limit - 1)).'…' : $text;
    }

    /** Egy termék sora; null, ha nem kerül a feedbe. */
    static function item($product, $shipping = null){
        if (!$product || !$product->is_type('simple') || $product->get_status() !== 'publish' || !$product->is_visible()) return null;
        if ($product->get_price() === '' || !$product->is_purchasable() || !$product->is_in_stock()) return null;

        $gross = (float)wc_get_price_including_tax($product);
        if ($gross <= 0) return null;
        $net = (float)wc_get_price_excluding_tax($product);

        $brands = taxonomy_exists('product_brand') ? get_the_terms($product->get_id(), 'product_brand') : [];
        $images = array_slice(array_filter(array_merge([ $product->get_image_id() ], $product->get_gallery_image_ids())), 0, self::IMAGES);

        $cost = '';
        if ($shipping){
            $weight = Shipping::product_weight($product);
            $value = $weight > 0 ? Shipping::gross_cost($shipping, $weight, $gross) : null;
            if ($value !== null) $cost = $value > 0 ? (string)round($value) : 'ingyenes';
        }
        $ean = method_exists($product, 'get_global_unique_id') ? $product->get_global_unique_id() : '';

        $out = '<product>'
            .self::tag('identifier', $product->get_sku() !== '' ? $product->get_sku() : 'vk-'.$product->get_id())
            .self::tag('manufacturer', !empty($brands) && !is_wp_error($brands) ? $brands[0]->name : '')
            .self::tag('name', $product->get_name())
            .self::tag('category', self::category($product))
            .self::tag('product_url', $product->get_permalink())
            .self::tag('price', round($gross))
            .self::tag('net_price', round($net))
            .self::tag('currency', 'HUF');
        foreach(array_values($images) as $i => $image){
            $out .= self::tag($i ? 'image_url_'.($i + 1) : 'image_url', wp_get_attachment_image_url($image, 'full'));
        }
        $out .= self::tag('description', self::text($product->get_short_description() ?: $product->get_description()), true)
            .self::tag('Delivery_Time', $product->is_on_backorder() ? self::BACKORDER_DAYS : 1)
            .self::tag('Delivery_Cost', $cost)
            .self::tag('ProductNumber', $product->get_sku())
            .self::tag('EAN_code', $ean)
            .($product->get_weight() !== '' ? self::tag('ProductWeight', wc_format_decimal($product->get_weight()).' '.get_option('woocommerce_weight_unit', 'kg')) : '')
            .'</product>';
        return $out;
    }

    static function build(){
        @set_time_limit(300);
        $shipping = Shipping::method();
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<products>'."\n";
        $page = 1;
        do {
            $ids = wc_get_products([ 'status' => 'publish', 'type' => 'simple', 'limit' => 200, 'page' => $page, 'return' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ]);
            foreach($ids as $id){
                $item = self::item(wc_get_product($id), $shipping);
                if ($item !== null) $xml .= $item."\n";
            }
            $page++;
        } while (count($ids) === 200);
        return $xml.'</products>'."\n";
    }
}
