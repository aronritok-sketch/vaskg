<?php

namespace VasskisgepTheme;

/**
 * Kosár oldalpanel (iu_woocommerce mini-cart, „Kosár tartalma”) tételsora (ügyfélkérés, 2026-10): darabár,
 * mennyiség módosítása (−/+ és beírás) és a tétel végösszege. Az iu_woocommerce a nevet és a mennyiséget a
 * woocommerce_widget_cart_item_quantity szűrőben rakja össze (10); ez utána (20) a teljes sort adja.
 * A mennyiség a wc-ajax=vk_cart_qty végponton mentődik, a válasz a WooCommerce kosár-töredékei.
 */
class MiniCart {
    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_filter('woocommerce_widget_cart_item_quantity', [$this, 'item'], 20, 3);
        add_action('wc_ajax_vk_cart_qty', [$this, 'set_quantity']);
    }

    function item($html, $cart_item, $cart_item_key){
        $product = $cart_item['data'] ?? null;
        if (!$product instanceof \WC_Product) return $html;
        $qty = (int)$cart_item['quantity'];
        $cart = WC()->cart;
        $max = $product->get_max_purchase_quantity();
        $unit = $cart->get_product_price($product);
        $total = $cart->get_product_subtotal($product, $qty);

        // A rejtett „N × ár” az iu_woocommerce kosárszámlálójához kell (abból számolja a jelvényt).
        return '<div class="product_details vk-mc">'
            .'<a class="product_title" href="'.esc_url($product->get_permalink($cart_item)).'">'.esc_html($product->get_name()).'</a>'
            .'<span class="vk-mc-unit">Egységár: '.$unit.'</span>'
            .'<div class="vk-mc-row">'
                .'<div class="vk-qty vk-mc-qty" data-key="'.esc_attr($cart_item_key).'">'
                    .'<button type="button" class="vk-mc-step" data-step="-1" aria-label="Kevesebb">−</button>'
                    .'<input type="number" class="vk-mc-input" value="'.$qty.'" min="0"'.($max > 0 ? ' max="'.(int)$max.'"' : '').' step="1" inputmode="numeric" aria-label="Mennyiség: '.esc_attr($product->get_name()).'">'
                    .'<button type="button" class="vk-mc-step" data-step="1" aria-label="Több">+</button>'
                .'</div>'
                .'<span class="vk-mc-total">Összesen: <strong>'.$total.'</strong></span>'
            .'</div>'
            .'<span class="quantity" hidden>'.$qty.' &times; '.$unit.'</span>'
        .'</div>';
    }

    /** Mennyiség módosítása (0: törlés), válaszul a frissített kosár-töredékek. */
    function set_quantity(){
        $key = wc_clean(wp_unslash($_POST['key'] ?? ''));
        $qty = max(0, (int)($_POST['qty'] ?? 0));
        $cart = WC()->cart;
        if (!$cart || $key === '' || !$cart->get_cart_item($key)) wp_send_json_error([ 'message' => 'A tétel már nincs a kosárban.' ], 404);
        $item = $cart->get_cart_item($key);
        $max = $item['data']->get_max_purchase_quantity();
        if ($max > 0) $qty = min($qty, $max);
        if ($qty === 0) $cart->remove_cart_item($key);
        else $cart->set_quantity($key, $qty, true);
        \WC_AJAX::get_refreshed_fragments();
    }
}
