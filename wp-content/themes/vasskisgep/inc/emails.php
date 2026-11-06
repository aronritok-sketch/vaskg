<?php

namespace VasskisgepTheme;

/**
 * WooCommerce levelek (ügyfélkérés, 2026-11): a narancssárga „Vass Kisgép” felirat helyett a logó (PNG, mert az SVG-t
 * a levelezők nagy része nem mutatja), barátságosabb szöveg a visszaigazolásban, a rendelt termékek neve kattintható
 * (a termékoldalra visz), a feladó neve „Vass Kisgép”.
 */
class Emails {
    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    private $in_order_table = false;

    const TEXTS = [
        'Thank you for your order' => 'Köszönjük a rendelésed!',
        'Just to let you know &mdash; we’ve received your order, and it is now being processed.' => 'Köszönjük, hogy minket választottál! Megkaptuk a rendelésed, és már dolgozunk rajta. Amint a csomagod útnak indul – vagy átveheted az üzletünkben –, e-mailben jelentkezünk.',
        'Just to let you know &mdash; we\'ve received your order #%s, and it is now being processed:' => 'Köszönjük, hogy minket választottál! Megkaptuk a(z) #%s számú rendelésed, és már dolgozunk rajta:',
        'Here’s a reminder of what you’ve ordered:' => 'A rendelésed részletei:',
    ];

    function __construct(){
        add_filter('pre_option_woocommerce_email_header_image', [$this, 'header_image']);
        add_filter('gettext_woocommerce', [$this, 'texts'], 20, 2);
        add_filter('woocommerce_email_heading_customer_processing_order', function(){ return self::TEXTS['Thank you for your order']; });
        add_filter('woocommerce_email_from_name', function(){ return 'Vass Kisgép'; });
        add_action('woocommerce_email_before_order_table', function(){ $this->in_order_table = true; }, 1);
        add_action('woocommerce_email_after_order_table', function(){ $this->in_order_table = false; }, 99);
        add_filter('woocommerce_order_item_name', [$this, 'item_link'], 20, 3);
    }

    function header_image(){
        return get_stylesheet_directory_uri().'/assets/email-logo.png';
    }

    function texts($translation, $text){
        return self::TEXTS[$text] ?? $translation;
    }

    /** A levél tételsorában a termék neve link a termékoldalra. */
    function item_link($name, $item, $is_visible = false){
        if (!$this->in_order_table || !$item instanceof \WC_Order_Item_Product || strpos((string)$name, '<a ') !== false) return $name;
        $product = $item->get_product();
        if (!$product || !$product->is_visible()) return $name;
        return '<a href="'.esc_url($product->get_permalink()).'" style="color:inherit;text-decoration:underline;" target="_blank">'.$name.'</a>';
    }
}
