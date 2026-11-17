<?php

namespace VasskisgepTheme;

/**
 * Megjegyzések a rendelés adatlapján (Dávid, 2026-11-17): a „Szállítás” oszlop üres része alatt egy helyen látszik
 * a vevő megjegyzése és a munkatársak által írt rendelés-megjegyzések (a rendszer automatikus bejegyzései nélkül),
 * hogy ne kelljen a jobb oldali dobozban keresni. Új megjegyzés felvétele / törlése után a doboz magától frissül.
 */
class OrderNotes {
    const LIMIT = 8;

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'box'], 30);
        add_action('wp_ajax_vk_order_notes_box', [$this, 'ajax_box']);
    }

    /** A munkatársak által írt megjegyzések (újabb elöl). */
    static function staff_notes($order_id){
        if (!function_exists('wc_get_order_notes')) return [];
        $notes = wc_get_order_notes(['order_id' => $order_id, 'orderby' => 'date_created', 'order' => 'DESC']);
        $out = [];
        foreach($notes as $note){
            if (($note->added_by ?? 'system') === 'system') continue;
            $out[] = $note;
            if (count($out) >= self::LIMIT) break;
        }
        return $out;
    }

    static function inner_html($order){
        $note = trim((string)$order->get_customer_note());
        $staff = self::staff_notes($order->get_id());
        if ($note === '' && !$staff) return '';
        $html = '<strong class="vk-order-notes-title">Megjegyzések</strong>';
        if ($note !== ''){
            $html .= '<div class="vk-order-note vk-order-note-customer"><span class="vk-order-note-meta">Vevő megjegyzése</span>'
                .wp_kses(nl2br(esc_html(wc_wptexturize_order_note($note))), ['br' => []]).'</div>';
        }
        foreach($staff as $n){
            $meta = trim(($n->added_by ?? '').' · '.($n->date_created ? wc_format_datetime($n->date_created, 'Y.m.d. H:i') : ''), ' ·');
            if (!empty($n->customer_note)) $meta .= ' · a vevőnek elküldve';
            $html .= '<div class="vk-order-note'.(!empty($n->customer_note) ? ' vk-order-note-sent' : '').'"><span class="vk-order-note-meta">'.esc_html($meta).'</span>'
                .wpautop(wptexturize(wp_kses_post($n->content))).'</div>';
        }
        return $html;
    }

    function box($order){
        if (!$order instanceof \WC_Order) return;
        echo '<div class="vk-order-notes" data-order="'.(int)$order->get_id().'" data-nonce="'.esc_attr(wp_create_nonce('vk_order_notes_box')).'">'.self::inner_html($order).'</div>';
        static $assets = false;
        if ($assets) return;
        $assets = true;
        // A WooCommerce saját „Vásárló által megadott megjegyzés” sora helyett a dobozban látszik (ne legyen kétszer).
        ?>
        <style>
            #order_data .order_data_column p.order_note{display:none}
            .vk-order-notes:empty{display:none}
            .vk-order-notes{clear:both;margin:14px 0 4px;padding-top:12px;border-top:1px solid #e2e4e7}
            .vk-order-notes-title{display:block;margin-bottom:6px}
            .vk-order-note{margin:0 0 8px;padding:7px 10px;border-radius:4px;background:#f0f0f1;font-size:13px;line-height:1.45;word-wrap:break-word}
            .vk-order-note p{margin:0 0 4px}
            .vk-order-note p:last-child{margin:0}
            .vk-order-note-customer{background:#fcf3d9;border-left:3px solid #dba617}
            .vk-order-note-sent{background:#e7f2f8}
            .vk-order-note-meta{display:block;margin-bottom:2px;color:#646970;font-size:12px}
        </style>
        <script>
        jQuery(function($){
            $(document).ajaxComplete(function(e, xhr, s){
                var data = typeof s.data === 'string' ? s.data : '';
                if (!/action=woocommerce_(add|delete)_order_note/.test(data)) return;
                $('.vk-order-notes').each(function(){
                    var box = $(this);
                    $.post(ajaxurl, { action: 'vk_order_notes_box', order: box.data('order'), _wpnonce: box.data('nonce') }, function(r){
                        if (r && r.success) box.html(r.data.html);
                    });
                });
            });
        });
        </script>
        <?php
    }

    function ajax_box(){
        check_ajax_referer('vk_order_notes_box');
        $order = wc_get_order(absint($_POST['order'] ?? 0));
        if (!$order || !current_user_can('edit_shop_orders')) wp_send_json_error(null, 403);
        wp_send_json_success(['html' => self::inner_html($order)]);
    }
}
