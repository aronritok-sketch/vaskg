<?php

namespace VasskisgepTheme;

/**
 * Termékoldal a design szerint: értékelések (a keretrendszer a hozzászólásokat
 * letiltja, termékeknél itt visszakapcsoljuk), legutóbb megtekintett termékek,
 * galéria kiegészítések, Cofidis áruhitel beállítások és törlesztőrészlet-számítás,
 * termékenkénti tájékoztató doboz.
 */
class ProductPage {
    const RECENT_COOKIE = 'vk_recent';
    const RECENT_LIMIT = 12;
    const COFIDIS_TERMS = [ 6, 10, 12, 18, 24, 36 ];

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        // Értékelések: a keretrendszer (iu_theme/inc/clean.php) 20-as prioritással zár minden hozzászólást.
        add_filter('comments_open', [$this, 'reviews_open'], 30, 2);
        add_filter('comments_array', [$this, 'review_comments'], 20, 2);
        add_action('init', [$this, 'product_comment_support'], 20);
        add_action('admin_init', [$this, 'product_comment_support'], 20);
        add_filter('woocommerce_product_review_comment_form_args', [$this, 'review_form_args']);

        add_action('template_redirect', [$this, 'track_recently_viewed'], 20);

        add_filter('render_block_iu-woocommerce/images', [$this, 'gallery_extras'], 10, 2);
        // A termékleírás (iu/content) lezárva és „vk-description” keretben: a régi webshopból jött leírások
        // egy része lezáratlan <div>-ekkel végződik, ami a füleket és az oldalsávot is a Részletek fülbe húzta.
        add_filter('render_block_iu/content', [$this, 'description'], 20, 2);
        // Változatos termék (pl. ruha méretekkel): magyar felirat a választóban; a gombokat a shop.js teszi mellé.
        add_filter('woocommerce_dropdown_variation_attribute_options_args', [$this, 'variation_select_args']);
        add_filter('woocommerce_reset_variations_link', function(){ return '<a class="reset_variations" href="#">Választás törlése</a>'; });
        add_filter('woocommerce_single_product_carousel_options', [$this, 'gallery_options']);

        add_filter('iu_theme_settings', [$this, 'settings']);

        add_action('woocommerce_product_options_general_product_data', [$this, 'notice_field'], 20);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_notice_field']);

        // Lebegő vásárlási sáv a képernyő alján, ha a termék neve, ára és a Kosárba gomb már kigördült.
        add_action('wp_footer', [$this, 'sticky_buy']);
    }

    function variation_select_args($args){
        $label = wc_attribute_label($args['attribute'], $args['product']);
        $args['show_option_none'] = mb_strtolower($label) === 'méret' ? 'Válasszon méretet' : 'Válasszon: '.mb_strtolower($label);
        return $args;
    }

    /* ---------- Termékleírás ---------- */

    function description($block_content, $block){
        if (is_admin() || wp_is_json_request() || get_post_type() !== 'product') return $block_content;
        return '<div class="vk-description">'.self::tidy_description($block_content).'</div>';
    }

    /**
     * Leírás rendbetétele: a régi webshop „kattints ide” kéz ikonja (5607.jpg) ki, mert a linkek végére a
     * stíluslap tesz ikont (ügyfélkérés, 2026-10); a lezáratlan címkék lezárva.
     */
    static function tidy_description($html){
        $html = preg_replace('~<img\b[^>]*/5607(?:-\d+x\d+)?\.(?:jpe?g|png|gif|webp)[^>]*>~i', '', (string)$html);
        return force_balance_tags($html);
    }

    /* ---------- Lebegő vásárlási sáv ---------- */

    function sticky_buy(){
        if (!is_product()) return;
        $product = wc_get_product(get_queried_object_id());
        if (!$product) return;

        $image = $product->get_image_id() ? wp_get_attachment_image($product->get_image_id(), 'thumbnail', false, [ 'class' => 'vk-sticky-img', 'alt' => '' ]) : '';
        $availability = $product->get_availability();
        $stock_class = $product->is_on_backorder() ? 'available-on-backorder' : ($product->is_in_stock() ? 'in-stock' : 'out-of-stock');
        $stock = !empty($availability['availability']) ? '<span class="vk-sticky-stock stock '.esc_attr($stock_class).'">'.esc_html($availability['availability']).'</span>' : '';

        // Ár: régi ár áthúzva, akciós ár, kedvezmény százalékban (egyszerű terméknél).
        $price = '';
        if ($product->get_price() === ''){
            $price = '<span class="vk-sticky-current">Ár egyeztetés alapján</span>';
        } elseif ($product->is_type('simple') && $product->is_on_sale()){
            $regular = (float)wc_get_price_to_display($product, [ 'price' => $product->get_regular_price() ]);
            $current = (float)wc_get_price_to_display($product);
            $percent = $regular > 0 ? (int)round(($regular - $current) / $regular * 100) : 0;
            $price = '<del class="vk-sticky-regular">'.wc_price($regular).'</del><span class="vk-sticky-current">'.wc_price($current).'</span>'
                .($percent > 0 ? '<span class="vk-sticky-badge">−'.$percent.'%</span>' : '');
        } else {
            $price = '<span class="vk-sticky-current">'.$product->get_price_html().'</span>';
        }

        $cart = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="20" r="1.3"/><circle cx="18" cy="20" r="1.3"/><path d="M2 3h3l2.6 12.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.1L21 8H6.2"/></svg>';
        if ($product->is_type('simple') && $product->is_purchasable() && $product->is_in_stock()){
            // A fő űrlap mennyiségét veszi át (shop.js), a WooCommerce AJAX kosárba rakásával.
            $button = '<a href="'.esc_url($product->add_to_cart_url()).'" rel="nofollow" class="iu-button iu-button-default vk-sticky-button add_to_cart_button ajax_add_to_cart" data-product_id="'.esc_attr($product->get_id()).'" data-product_sku="'.esc_attr($product->get_sku()).'" data-quantity="1"><span class="iu-button-text"><span>Kosárba</span>'.$cart.'</span></a>';
        } elseif (!$product->is_type('simple')){
            $button = '<a href="#" class="iu-button iu-button-default vk-sticky-button vk-sticky-scroll"><span class="iu-button-text"><span>Kiválasztom</span></span></a>';
        } else {
            $quote = get_page_by_path('ajanlatkeres');
            $button = '<a class="iu-button iu-button-outline vk-sticky-button" href="'.esc_url(add_query_arg('termek', rawurlencode($product->get_name()), $quote ? get_permalink($quote) : home_url('/'))).'"><span class="iu-button-text"><span>Ajánlatot kérek</span></span></a>';
        }

        echo '<div class="vk-sticky-buy" aria-hidden="true" inert>'
            .'<div class="vk-sticky-inner">'
                .($image ? '<span class="vk-sticky-thumb">'.$image.'</span>' : '')
                .'<div class="vk-sticky-info"><span class="vk-sticky-name">'.esc_html($product->get_name()).'</span>'.$stock.'</div>'
                .'<div class="vk-sticky-price">'.$price.'</div>'
                .$button
            .'</div>'
        .'</div>';
    }

    /* ---------- Értékelések ---------- */

    function reviews_open($open, $post_id){
        if (get_post_type($post_id) !== 'product') return $open;
        return get_option('woocommerce_enable_reviews', 'yes') === 'yes' && get_post_field('comment_status', $post_id) === 'open';
    }

    function review_comments($comments, $post_id){
        if (get_post_type($post_id) !== 'product') return $comments;
        return get_comments([ 'post_id' => $post_id, 'status' => 'approve', 'type' => 'review', 'order' => 'ASC' ]);
    }

    function product_comment_support(){
        add_post_type_support('product', 'comments');
    }

    function review_form_args($args){
        $args['title_reply'] = have_comments() ? 'Írjon értékelést' : 'Legyen az első, aki értékeli: „'.get_the_title().'”';
        $args['label_submit'] = 'Értékelés elküldése';
        $args['class_submit'] = 'submit iu-button iu-button-default';
        return $args;
    }

    /* ---------- Legutóbb megtekintett termékek ---------- */

    function track_recently_viewed(){
        if (!is_singular('product') || headers_sent()) return;
        $ids = self::recently_viewed();
        $id = get_queried_object_id();
        $ids = array_values(array_diff($ids, [ $id ]));
        array_unshift($ids, $id);
        $ids = array_slice($ids, 0, self::RECENT_LIMIT);
        setcookie(self::RECENT_COOKIE, implode(',', $ids), [
            'expires' => time() + 30 * DAY_IN_SECONDS,
            'path' => COOKIEPATH ?: '/',
            'samesite' => 'Lax'
        ]);
    }

    static function recently_viewed(){
        $raw = isset($_COOKIE[self::RECENT_COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[self::RECENT_COOKIE])) : '';
        return array_values(array_unique(array_filter(array_map('absint', explode(',', $raw)))));
    }

    /* ---------- Galéria ---------- */

    /** Promóciós szöveg és márkajelölés a termékképen (a kedvezmény jelvény a product-badges blokk). */
    function gallery_extras($content, $block){
        $product = wc_get_product();
        if (!$product || !is_product()) return $content;

        $extras = Catalog::promo_html($product, 'vk-promo vk-gallery-promo');
        $brands = get_the_terms($product->get_id(), 'product_brand');
        if (!empty($brands) && !is_wp_error($brands)){
            // A márka eredeti logója (ha van feltöltve), különben a neve.
            $logo = (int)get_term_meta($brands[0]->term_id, 'thumbnail_id', true);
            $logo_html = $logo ? wp_get_attachment_image($logo, 'medium', false, [ 'alt' => $brands[0]->name, 'class' => 'vk-gallery-brand-logo', 'loading' => 'lazy' ]) : '';
            $extras .= $logo_html !== ''
                ? '<a class="vk-gallery-brand vk-gallery-brand-has-logo" href="'.esc_url(get_term_link($brands[0])).'" title="'.esc_attr($brands[0]->name).'">'.$logo_html.'</a>'
                : '<a class="iu-woocommerce-product-brand vk-gallery-brand" href="'.esc_url(get_term_link($brands[0])).'">'.esc_html($brands[0]->name).'</a>';
        }
        if ($extras === '') return $content;
        return '<div class="vk-gallery-wrap">'.$content.'<div class="vk-gallery-extras">'.$extras.'</div></div>';
    }

    function gallery_options($options){
        $options['directionNav'] = true;
        $options['prevText'] = '';
        $options['nextText'] = '';
        return $options;
    }

    /* ---------- Tájékoztató doboz ---------- */

    static function notice($product){
        return trim((string)$product->get_meta('_vk_notice'));
    }

    function notice_field(){
        global $product_object;
        woocommerce_wp_textarea_input([
            'id' => '_vk_notice',
            'label' => 'Tájékoztató doboz',
            'description' => 'A Részletek fül tetején kiemelt dobozban jelenik meg (pl. „Személyes átvétel esetén az ár tartalmazza a gép összeszerelését és beüzemelését.”).',
            'desc_tip' => true,
            'value' => $product_object ? $product_object->get_meta('_vk_notice') : ''
        ]);
    }

    function save_notice_field($product){
        $product->update_meta_data('_vk_notice', isset($_POST['_vk_notice']) ? sanitize_textarea_field(wp_unslash($_POST['_vk_notice'])) : '');
    }

    /* ---------- Cofidis áruhitel ---------- */

    function settings($fields){
        $fields['cofidis'] = [
            'type' => 'tab',
            'title' => 'Cofidis áruhitel',
            'fields' => [
                'cofidis_thm' => [
                    'type' => 'text',
                    'label' => 'THM (%) – a Cofidistól kapott érvényes érték, pl. 14,6. Üresen a kalkulátor nem jelenik meg.'
                ],
                'cofidis_interest' => [
                    'type' => 'text',
                    'label' => 'Éves ügyleti kamat (%) – ha üres, a törlesztőrészlet a THM-ből számolódik'
                ],
                'cofidis_terms' => [
                    'type' => 'text',
                    'label' => 'Választható futamidők hónapban, vesszővel (alapértelmezés: 6,10,12,18,24,36)'
                ],
                'cofidis_min_amount' => [
                    'type' => 'text',
                    'label' => 'Minimális hitelösszeg (Ft)'
                ],
                'cofidis_max_amount' => [
                    'type' => 'text',
                    'label' => 'Maximális hitelösszeg (Ft)'
                ],
                'cofidis_disclaimer' => [
                    'type' => 'textarea',
                    'label' => 'Reprezentatív példa / jogi tájékoztató a kalkulátor alatt (a Cofidis által előírt szöveg)'
                ]
            ]
        ];
        return $fields;
    }

    static function cofidis_config(){
        $number = function($name){
            $value = str_replace([' ', ','], ['', '.'], Reviews::setting($name));
            return is_numeric($value) ? (float)$value : null;
        };
        $thm = $number('cofidis_thm');
        if ($thm === null || $thm < 0) return null;

        $terms = array_filter(array_map('absint', explode(',', Reviews::setting('cofidis_terms'))));
        return [
            'thm' => $thm,
            'interest' => $number('cofidis_interest'),
            'terms' => !empty($terms) ? array_values($terms) : self::COFIDIS_TERMS,
            'min' => (int)($number('cofidis_min_amount') ?? 0),
            'max' => (int)($number('cofidis_max_amount') ?? 0),
            'disclaimer' => Reviews::setting('cofidis_disclaimer')
        ];
    }

    /**
     * Havi törlesztőrészlet annuitással. Ügyleti kamat hiányában a THM-ből számolt
     * havi kamatlábbal (tájékoztató jellegű; a pontos értéket a Cofidis adja).
     */
    static function cofidis_installment($amount, $months, $config){
        if ($amount <= 0 || $months <= 0) return 0;
        $rate = $config['interest'] !== null
            ? $config['interest'] / 100 / 12
            : pow(1 + $config['thm'] / 100, 1 / 12) - 1;
        if ($rate <= 0) return (int)ceil($amount / $months);
        return (int)ceil($amount * $rate / (1 - pow(1 + $rate, -$months)));
    }
}
