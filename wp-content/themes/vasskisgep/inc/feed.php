<?php

namespace VasskisgepTheme;

/**
 * Árukereső termékfeed (XML): /arukereso.xml (vagy /?vk_feed=arukereso). Ezt a címet kell az Árukereső
 * Partner Portálon megadni (a régi bolt feedje helyett, élesítéskor).
 *
 * Csak a látható, áras, rendelhető termékek kerülnek bele; a változatos termék (pl. ruha méretekkel) változatonként
 * egy sor, közös groupid-vel és a méret (size) mezővel. Készlet: delivery_time 1 = „Raktáron”,
 * a „visszaigazolástól 1-5 munkanap” (utánrendelhető) termék 7 (max. 1 hét). Szállítási díj (delivery_cost, csak
 * szám): a súly szerinti díjtábla alapján egy darabra, 0 a díjmentes határ felett; ismeretlen súlynál a 10 kg-os díj.
 * Óránként újragenerálódik (a termék mentése után a következő lekéréskor). A kész feed fájlban van
 * (uploads/vk-feed/arukereso.xml); ha az újragenerálás elakad, az utolsó jó változat megy ki. Az építés
 * adagonként üríti a memória-gyorsítótárat (~1900 termék egy kérésben: a WordPress futásidejű gyorsítótára
 * termékenként ~40 KB-tal nőne, ez az élesen kifutott a memóriakorlátból → 500).
 *
 * Google Merchant Center termékfeed (RSS 2.0, g: mezők): /google.xml (vagy /?vk_feed=google) – Dávid, 2026-11-16:
 * a Merchant a régi webshop feedjét kereste. Ugyanazokból a termékekből, ugyanígy fájlba generálva (uploads/vk-feed/google.xml).
 */
class Feed {
    const QUERY_VAR = 'vk_feed';
    const CACHE = 'vk_feed_arukereso';
    const MAX_AGE = HOUR_IN_SECONDS;
    const BACKORDER_DAYS = 7; // az Árukereső értékei: 1 = Raktáron, 3, 7 (max. 1 hét), 14, 17
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

    const TYPES = [ 'arukereso', 'google' ];

    static function rewrite(){
        add_rewrite_rule('^arukereso\.xml$', 'index.php?'.self::QUERY_VAR.'=arukereso', 'top');
        add_rewrite_rule('^google\.xml$', 'index.php?'.self::QUERY_VAR.'=google', 'top');
        // Az új szabály egyszer a permalinkek közé kerül.
        if (get_option('vk_feed_rules') !== '2'){ update_option('vk_feed_rules', '2'); flush_rewrite_rules(false); }
    }

    static function url($type = 'arukereso'){
        return get_option('permalink_structure') ? home_url('/'.$type.'.xml') : add_query_arg(self::QUERY_VAR, $type, home_url('/'));
    }

    /** A kész feed fájlja. */
    static function file($type = 'arukereso'){
        $dir = trailingslashit(wp_upload_dir()['basedir']).'vk-feed';
        if (!is_dir($dir)) wp_mkdir_p($dir);
        return $dir.'/'.$type.'.xml';
    }

    private static function stale_key($type){ return $type === 'arukereso' ? self::CACHE.'_stale' : 'vk_feed_'.$type.'_stale'; }

    /** Termék mentésekor: a következő lekérés újragenerálja (addig a mostani megy ki). */
    static function flush(){
        delete_transient(self::CACHE);
        foreach(self::TYPES as $type) update_option(self::stale_key($type), time(), false);
    }

    /** A feed (újra)generálása fájlba. true, ha sikerült. */
    static function generate($type = 'arukereso'){
        $file = self::file($type);
        $xml = self::build($type);
        $mark = $type === 'google' ? '<item>' : '<product>';
        if (substr_count($xml, $mark) === 0 && is_file($file) && filesize($file) > 1000) return false;
        $tmp = $file.'.tmp';
        if (file_put_contents($tmp, $xml) === false) return false;
        rename($tmp, $file);
        delete_option(self::stale_key($type));
        return true;
    }

    function serve(){
        $type = (string)get_query_var(self::QUERY_VAR);
        if (!in_array($type, self::TYPES, true)) return;
        $file = self::file($type);
        $fresh = is_file($file) && filemtime($file) > time() - self::MAX_AGE && !get_option(self::stale_key($type));
        $lock = 'vk_feed_'.$type.'_lock';
        if (!$fresh && !get_transient($lock)){
            set_transient($lock, 1, 10 * MINUTE_IN_SECONDS);
            try {
                self::generate($type);
            } catch (\Throwable $e){
                error_log('Termékfeed ('.$type.'): '.$e->getMessage());
            }
            delete_transient($lock);
        }
        if (!is_file($file)){
            status_header(503);
            header('Retry-After: 120');
            exit;
        }
        status_header(200);
        nocache_headers();
        header('Content-Type: application/xml; charset=UTF-8');
        header('X-Robots-Tag: noindex');
        header('Content-Length: '.filesize($file));
        readfile($file);
        exit;
    }

    /** A futásidejű gyorsítótár ürítése az adagok között (a tartós gyorsítótárhoz nem nyúl). */
    private static function free_memory(){
        if (function_exists('wp_cache_flush_runtime')) wp_cache_flush_runtime();
        elseif (!wp_using_ext_object_cache()) wp_cache_flush();
    }

    private static function tag($name, $value, $cdata = false){
        $value = (string)$value;
        if ($value === '') return '';
        return '<'.$name.'>'.($cdata ? '<![CDATA['.str_replace(']]>', ']]]]><![CDATA[>', $value).']]>' : esc_xml($value)).'</'.$name.'>';
    }

    /** A termék legmélyebb kategóriájának útvonala: „Kerti és erdészeti gépek > Fűnyírók > Benzines fűnyírók”. */
    private static function category($product){
        $best = [];
        $ids = $product->get_category_ids();
        // Csak gyártói oldali / akció-kategória csak akkor, ha nincs más.
        $normal = array_values(array_filter($ids, function($id){ return !Catalog::special_cat($id); }));
        foreach($normal ?: $ids as $term_id){
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
        if (!$product || !$product->is_type([ 'simple', 'variation' ])) return null;
        $parent = $product->is_type('variation') ? wc_get_product($product->get_parent_id()) : $product;
        if (!$parent || $parent->get_status() !== 'publish' || !$parent->is_visible()) return null;
        if ($product->get_price() === '' || !$product->is_purchasable() || !$product->is_in_stock()) return null;

        $gross = (float)wc_get_price_including_tax($product);
        if ($gross <= 0) return null;
        $net = (float)wc_get_price_excluding_tax($product);

        $brands = taxonomy_exists('product_brand') ? get_the_terms($parent->get_id(), 'product_brand') : [];
        $images = array_slice(array_unique(array_filter(array_merge([ $product->get_image_id() ?: $parent->get_image_id() ], $parent->get_gallery_image_ids()))), 0, self::IMAGES);
        $variant = $product->is_type('variation') ? implode(' ', array_filter(array_values($product->get_attributes()))) : '';

        $cost = '';
        if ($shipping){
            // Ismeretlen súlynál a legkisebb (10 kg-ig) díj; ingyenesnél 0 (az Árukereső csak számot fogad el).
            $weight = Shipping::product_weight($product);
            $value = Shipping::is_free($product) ? 0.0 : Shipping::gross_cost($shipping, $weight > 0 ? $weight : 1, $gross);
            if ($value !== null) $cost = (string)max(0, round($value));
        }
        $ean = method_exists($product, 'get_global_unique_id') ? $product->get_global_unique_id() : '';

        $out = '<product>'
            .self::tag('identifier', $product->get_sku() !== '' ? $product->get_sku() : 'vk-'.$product->get_id())
            .self::tag('manufacturer', !empty($brands) && !is_wp_error($brands) ? $brands[0]->name : '')
            .self::tag('name', trim($parent->get_name().' '.$variant))
            .self::tag('category', self::category($parent))
            .self::tag('product_url', $product->get_permalink())
            .self::tag('price', round($gross))
            .self::tag('net_price', round($net))
            .self::tag('currency', 'HUF');
        foreach(array_values($images) as $i => $image){
            $out .= self::tag($i ? 'image_url_'.($i + 1) : 'image_url', wp_get_attachment_image_url($image, 'full'));
        }
        $out .= self::tag('description', self::text($parent->get_short_description() ?: $parent->get_description()), true)
            .self::tag('delivery_time', $product->is_on_backorder() ? self::BACKORDER_DAYS : 1)
            .self::tag('delivery_cost', $cost)
            .self::tag('ProductNumber', $product->get_sku())
            .self::tag('EAN_code', $ean)
            .($variant !== '' ? self::tag('size', $variant).self::tag('groupid', 'vk-'.$parent->get_id()) : '')
            .($product->get_weight() !== '' ? self::tag('ProductWeight', wc_format_decimal($product->get_weight()).' '.get_option('woocommerce_weight_unit', 'kg')) : '')
            .'</product>';
        return $out;
    }

    static function build($type = 'arukereso'){
        @set_time_limit(600);
        wp_raise_memory_limit('admin');
        $shipping = Shipping::method();
        $google = $type === 'google';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".($google
            ? '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel><title>'.esc_xml(get_bloginfo('name')).'</title><link>'.esc_url(home_url('/')).'</link><description>Termékfeed</description>'
            : '<products>')."\n";
        $page = 1;
        do {
            $ids = wc_get_products([ 'status' => 'publish', 'type' => [ 'simple', 'variable' ], 'limit' => 200, 'page' => $page, 'return' => 'ids', 'orderby' => 'ID', 'order' => 'ASC' ]);
            foreach($ids as $id){
                $product = wc_get_product($id);
                foreach($product && $product->is_type('variable') ? $product->get_children() : [ $id ] as $child){
                    try {
                        $obj = $child === $id ? $product : wc_get_product($child);
                        $item = $google ? self::google_item($obj, $shipping) : self::item($obj, $shipping);
                    } catch (\Throwable $e){
                        // Egy hibás termék ne vigye el az egész feedet.
                        error_log('Árukereső feed, termék '.$child.': '.$e->getMessage());
                        $item = null;
                    }
                    if ($item !== null) $xml .= $item."\n";
                }
            }
            $page++;
            self::free_memory();
        } while (count($ids) === 200);
        return $xml.($google ? '</channel></rss>' : '</products>')."\n";
    }

    private static function gtag($name, $value){
        $value = (string)$value;
        return $value === '' ? '' : '<g:'.$name.'>'.esc_xml($value).'</g:'.$name.'>';
    }

    /** Egy termék a Google Merchant feedben (ugyanazok a termékek, mint az Árukeresőben). */
    static function google_item($product, $shipping = null){
        if (!$product || !$product->is_type([ 'simple', 'variation' ])) return null;
        $parent = $product->is_type('variation') ? wc_get_product($product->get_parent_id()) : $product;
        if (!$parent || $parent->get_status() !== 'publish' || !$parent->is_visible()) return null;
        if ($product->get_price() === '' || !$product->is_purchasable() || !$product->is_in_stock()) return null;
        $gross = (float)wc_get_price_including_tax($product);
        if ($gross <= 0) return null;
        $regular = $product->get_regular_price() !== '' ? (float)wc_get_price_including_tax($product, [ 'price' => $product->get_regular_price() ]) : $gross;

        $brands = taxonomy_exists('product_brand') ? get_the_terms($parent->get_id(), 'product_brand') : [];
        $brand = !empty($brands) && !is_wp_error($brands) ? $brands[0]->name : '';
        $images = array_values(array_slice(array_unique(array_filter(array_merge([ $product->get_image_id() ?: $parent->get_image_id() ], $parent->get_gallery_image_ids()))), 0, 11));
        $variant = $product->is_type('variation') ? implode(' ', array_filter(array_values($product->get_attributes()))) : '';
        $ean = preg_replace('/\D/', '', method_exists($product, 'get_global_unique_id') ? (string)$product->get_global_unique_id() : '');
        if (!in_array(strlen($ean), [ 8, 12, 13, 14 ], true)) $ean = '';
        $sku = (string)$product->get_sku();

        $out = '<item>'
            .self::gtag('id', $sku !== '' ? $sku : 'vk-'.$product->get_id())
            .self::tag('title', mb_substr(trim($parent->get_name().' '.$variant), 0, 150))
            .self::tag('description', self::text($parent->get_description() ?: $parent->get_short_description(), 4900), true)
            .self::tag('link', $product->get_permalink());
        foreach($images as $i => $image){
            $url = wp_get_attachment_image_url($image, 'full');
            if ($url) $out .= self::gtag($i ? 'additional_image_link' : 'image_link', $url);
        }
        if ($product->is_on_backorder()){
            $out .= self::gtag('availability', 'backorder').self::gtag('availability_date', gmdate('Y-m-d\TH:iO', time() + self::BACKORDER_DAYS * DAY_IN_SECONDS));
        } else {
            $out .= self::gtag('availability', 'in_stock');
        }
        $out .= self::gtag('price', round($regular).' HUF');
        if ($product->is_on_sale() && $gross < $regular){
            $out .= self::gtag('sale_price', round($gross).' HUF');
            $from = $product->get_date_on_sale_from(); $to = $product->get_date_on_sale_to();
            if ($from && $to) $out .= self::gtag('sale_price_effective_date', $from->date('c').'/'.$to->date('c'));
        }
        $out .= self::gtag('condition', 'new')
            .self::gtag('brand', $brand)
            .self::gtag('gtin', $ean)
            .self::gtag('mpn', $sku);
        if ($ean === '' && ($brand === '' || $sku === '')) $out .= self::gtag('identifier_exists', 'no');
        $out .= self::gtag('product_type', self::category($parent));
        if ($variant !== '') $out .= self::gtag('item_group_id', 'vk-'.$parent->get_id());
        if ($shipping){
            $weight = Shipping::product_weight($product);
            $value = Shipping::is_free($product) ? 0.0 : Shipping::gross_cost($shipping, $weight > 0 ? $weight : 1, $gross);
            if ($value !== null) $out .= '<g:shipping><g:country>HU</g:country><g:price>'.esc_xml(max(0, round($value)).' HUF').'</g:price></g:shipping>';
        }
        if ($product->get_weight() !== '') $out .= self::gtag('shipping_weight', wc_format_decimal($product->get_weight()).' '.get_option('woocommerce_weight_unit', 'kg'));
        return $out.'</item>';
    }
}
