<?php

namespace VasskisgepTheme;

/**
 * A REST API a szabványos /wp-json/ címen (Dávid, 2026-11-17: az App Store-ból letöltött WooCommerce appba nem
 * lehetett belépni). Az iu_theme véletlen előtagra teszi a REST-et, és leveszi a felfedező linket (fejléc + <head>),
 * így a mobilapp és a külső összekötések (WooCommerce app, Jetpack, Google / Facebook bővítmény) nem találják.
 * Ugyanez a megoldás a helloprovision.com témájában (inc/compat.php). Az iu_theme ensure_rest_rewrite() és az alábbi
 * egyszeri frissítés újraírja a szabályokat.
 */
class RestCompat {
    const PREFIX = 'wp-json';

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_filter('rest_url_prefix', fn() => self::PREFIX, 100);
        add_action('after_setup_theme', [$this, 'restore_discovery'], 100);
        add_action('init', [$this, 'ensure_rules'], 100);
    }

    function restore_discovery(){
        if (!has_action('wp_head', 'rest_output_link_wp_head')) add_action('wp_head', 'rest_output_link_wp_head', 10, 0);
        if (!has_action('template_redirect', 'rest_output_link_header')) add_action('template_redirect', 'rest_output_link_header', 11, 0);
    }

    function ensure_rules(){
        if (!get_option('permalink_structure')) return;
        $rules = get_option('rewrite_rules');
        if (is_array($rules) && $rules && !isset($rules['^'.self::PREFIX.'/?$'])) flush_rewrite_rules(false);
    }
}
