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
        add_filter('woocommerce_single_product_carousel_options', [$this, 'gallery_options']);

        add_filter('iu_theme_settings', [$this, 'settings']);

        add_action('woocommerce_product_options_general_product_data', [$this, 'notice_field'], 20);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_notice_field']);
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
            $extras .= '<a class="iu-woocommerce-product-brand vk-gallery-brand" href="'.esc_url(get_term_link($brands[0])).'">'.esc_html($brands[0]->name).'</a>';
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
