<?php

namespace VasskisgepTheme;

/**
 * Cofidis áruhitel fizetési mód (WooCommerce → Beállítások → Fizetés → Cofidis áruhitel).
 *
 * Jelenlegi működés (kézi hitelügyintézés, a Cofidis webshop API dokumentációja nélkül):
 *   – csak akkor választható, ha a kosár minden terméke „Cofidis áruhitel” jelölésű, és a
 *     végösszeg a beállított hitelösszeg-határokon belül van;
 *   – a rendelés „Függőben (on-hold)” állapotba kerül („Cofidis hitelbírálatra vár”), a készlet
 *     lefoglalódik, a vásárló a köszönőoldalon és e-mailben tájékoztatást kap (és ha van, a
 *     Cofidis hitelkérelmi linket);
 *   – az adminban a rendelésnél: „Cofidis: hitel jóváhagyva” → Feldolgozás alatt,
 *     „Cofidis: hitel elutasítva” → Törölve (a készlet visszaáll).
 *
 * Online hitelkérelem (API): a `vasskisgep_cofidis_start_application` szűrő kapja meg a rendelést;
 * ha egy URL-t ad vissza, a vásárló oda irányítódik. A Cofidis visszahívása a
 * /wp-json/vasskisgep/v1/cofidis/callback címre érkezhet; a feldolgozása a
 * `vasskisgep_cofidis_callback` szűrőben történik (lásd README). Amíg ezek nincsenek bekötve,
 * a kézi folyamat működik.
 */
class Cofidis {
    const ID = 'vk_cofidis';

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_filter('woocommerce_payment_gateways', [$this, 'register']);
        add_filter('woocommerce_order_actions', [$this, 'order_actions'], 10, 2);
        add_action('woocommerce_order_action_vk_cofidis_approved', [$this, 'approved']);
        add_action('woocommerce_order_action_vk_cofidis_rejected', [$this, 'rejected']);
        add_action('rest_api_init', [$this, 'rest']);
    }

    function register($gateways){
        if (class_exists('\WC_Payment_Gateway')){
            require_once(__DIR__.'/cofidis-gateway.php');
            $gateways[] = CofidisGateway::class;
        }
        return $gateways;
    }

    /** A kosár Cofidis-szel fizethető-e: minden termék jelölt, és az összeg a határokon belül. */
    static function cart_eligible($total = null){
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) return false;
        foreach(WC()->cart->get_cart() as $item){
            $product = wc_get_product($item['product_id']);
            if (!$product || !Catalog::has_cofidis($product)) return false;
        }
        return self::amount_eligible($total ?? (float)WC()->cart->get_total('edit'));
    }

    static function amount_eligible($amount){
        $config = ProductPage::cofidis_config();
        $min = $config ? $config['min'] : 0;
        $max = $config ? $config['max'] : 0;
        if ($min > 0 && $amount < $min) return false;
        if ($max > 0 && $amount > $max) return false;
        return $amount > 0;
    }

    /* ---------- Admin: rendelésműveletek ---------- */

    function order_actions($actions, $order = null){
        if ($order instanceof \WC_Order && $order->get_payment_method() === self::ID && $order->has_status(['on-hold', 'pending'])){
            $actions['vk_cofidis_approved'] = 'Cofidis: hitel jóváhagyva (→ Feldolgozás alatt)';
            $actions['vk_cofidis_rejected'] = 'Cofidis: hitel elutasítva (→ Törölve)';
        }
        return $actions;
    }

    function approved($order){
        $order->update_meta_data('_vk_cofidis_status', 'approved');
        $order->payment_complete();
        $order->add_order_note('Cofidis: a hitelkérelmet jóváhagyták.');
        $order->save();
    }

    function rejected($order){
        $order->update_meta_data('_vk_cofidis_status', 'rejected');
        $order->update_status('cancelled', 'Cofidis: a hitelkérelmet elutasították.');
        $order->save();
    }

    /* ---------- Visszahívás (online hitelkérelemhez) ---------- */

    function rest(){
        register_rest_route('vasskisgep/v1', '/cofidis/callback', [
            'methods' => ['POST', 'GET'],
            'permission_callback' => '__return_true',
            'callback' => function(\WP_REST_Request $request){
                /*
                 * A Cofidis dokumentáció alapján ide kell bekötni: az üzenet hitelességének
                 * ellenőrzése (aláírás / token) és a rendelés + állapot kiolvasása. A szűrő
                 * ['order_id' => int, 'status' => 'approved'|'rejected'|'pending'] tömböt adjon,
                 * vagy WP_Error-t. Bekötés nélkül a hívás elutasításra kerül.
                 */
                $result = apply_filters('vasskisgep_cofidis_callback', new \WP_Error('not_configured', 'A Cofidis visszahívás nincs bekötve.', [ 'status' => 501 ]), $request);
                if (is_wp_error($result)) return $result;

                $order = wc_get_order((int)($result['order_id'] ?? 0));
                if (!$order || $order->get_payment_method() !== self::ID) return new \WP_Error('order', 'Ismeretlen rendelés.', [ 'status' => 404 ]);

                $status = $result['status'] ?? '';
                if ($status === 'approved' && !$order->is_paid()) $this->approved($order);
                elseif ($status === 'rejected' && !$order->has_status('cancelled')) $this->rejected($order);
                elseif ($status === 'pending') $order->add_order_note('Cofidis: a hitelkérelem bírálat alatt.');
                return [ 'ok' => true ];
            }
        ]);
    }
}
