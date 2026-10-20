<?php

namespace VasskisgepTheme;

/**
 * Admin rendeléslista: „Termékek” oszlop a rendelt tételekkel (kép, mennyiség, név, cikkszám), hogy a rendelést
 * ne kelljen megnyitni. Sok tételnél kis, görgethető dobozban. HPOS és a régi (bejegyzés alapú) tárolás is.
 */
class Orders {
    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_filter('manage_woocommerce_page_wc-orders_columns', [$this, 'columns']);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'column'], 10, 2);
        add_filter('manage_edit-shop_order_columns', [$this, 'columns']);
        add_action('manage_shop_order_posts_custom_column', [$this, 'column'], 10, 2);
        add_action('admin_head', [$this, 'styles']);
        // A „Feldolgozás alatt” rendelés tételei is szerkeszthetők (tétel hozzáadása, törlése, mennyiség);
        // a WooCommerce alapból csak a függő és a visszatartott rendelést engedi.
        add_filter('wc_order_is_editable', [$this, 'editable'], 10, 2);
    }

    const EDITABLE_STATUSES = [ 'pending', 'on-hold', 'processing', 'auto-draft', 'checkout-draft' ];

    function editable($editable, $order){
        return $editable || ($order instanceof \WC_Order && in_array($order->get_status(), self::EDITABLE_STATUSES, true));
    }

    function columns($columns){
        $out = [];
        foreach($columns as $key => $label){
            $out[$key] = $label;
            if ($key === 'order_number') $out['vk_items'] = 'Termékek';
        }
        if (!isset($out['vk_items'])) $out['vk_items'] = 'Termékek';
        return $out;
    }

    function column($column, $order){
        if ($column !== 'vk_items') return;
        $order = $order instanceof \WC_Order ? $order : wc_get_order($order);
        if (!$order) return;
        $items = $order->get_items();
        if (!$items){
            echo '<span class="na">–</span>';
            return;
        }
        $count = array_sum(array_map(function($item){ return (int)$item->get_quantity(); }, $items));
        if (count($items) > 1) echo '<div class="vk-order-items-count">'.esc_html(sprintf('%d tétel · %d db', count($items), $count)).'</div>';
        echo '<div class="vk-order-items">';
        foreach($items as $item){
            $product = $item->get_product();
            $image = $product && $product->get_image_id() ? wp_get_attachment_image($product->get_image_id(), [ 32, 32 ], false, [ 'alt' => '' ]) : '<span class="vk-order-item-noimg"></span>';
            $sku = $product ? $product->get_sku() : '';
            echo '<div class="vk-order-item">'.$image
                .'<span class="vk-order-item-text"><b>'.(int)$item->get_quantity().' ×</b> '.esc_html($item->get_name())
                .($sku !== '' ? ' <small>'.esc_html($sku).'</small>' : '').'</span></div>';
        }
        echo '</div>';
    }

    function styles(){
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->id, [ 'woocommerce_page_wc-orders', 'edit-shop_order' ], true)) return;
        echo '<style>
            .wp-list-table .column-vk_items { width: 32%; }
            .vk-order-items-count { margin-bottom: .2rem; color: #6b7280; font-size: 12px; }
            .vk-order-items { max-height: 7.6rem; overflow-y: auto; padding-right: .3rem; overscroll-behavior: contain; }
            .vk-order-item { display: flex; align-items: center; gap: .5rem; padding: .2rem 0; line-height: 1.3; }
            .vk-order-item + .vk-order-item { border-top: 1px solid #f0f0f1; }
            .vk-order-item img, .vk-order-item-noimg { flex: 0 0 32px; width: 32px; height: 32px; object-fit: contain; border: 1px solid #e3e5e8; border-radius: 3px; background: #fff; }
            .vk-order-item small { color: #6b7280; }
        </style>';
    }
}
