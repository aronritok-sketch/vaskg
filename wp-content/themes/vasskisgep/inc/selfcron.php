<?php

namespace VasskisgepTheme;

/**
 * Tartalék időzítő (2026-11-09): élesen a WordPress nem tudja magát meghívni (a wp-cron.php-ra küldött saját kérés
 * elakad a tárhelyen), ezért az ütemezett feladatok (kosáremlékeztető 20:00-kor, GLS-állapot óránként, WooCommerce
 * háttérfeladatok) nem futottak le. Ha egy feladat 10 percnél régebben esedékes, egy oldallátogatás a válasz elküldése
 * UTÁN (a látogató nem vár rá) lefuttatja a lejárt feladatokat; legfeljebb 5 percenként. A tartós megoldás a tárhely
 * cronja (5 percenként: wget -q -O - https://…/wp-cron.php?doing_wp_cron), ezzel ez a tartalék magától nem fut.
 */
class SelfCron {
    const LOCK = 'vk_selfcron';
    const LAST = 'vk_selfcron_last';

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('shutdown', [$this, 'maybe_run'], 999);
    }

    static function finish_available(){
        return function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request');
    }

    /** A legrégebben esedékes feladat ideje (Unix), vagy 0. */
    static function oldest_due(){
        $crons = function_exists('_get_cron_array') ? _get_cron_array() : [];
        if (!$crons) return 0;
        $first = min(array_keys($crons));
        return $first <= time() ? (int)$first : 0;
    }

    function maybe_run(){
        if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST) || PHP_SAPI === 'cli') return;
        if (!empty($_POST) || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
        if (!self::finish_available()) return;
        $due = self::oldest_due();
        if (!$due || $due > time() - 10 * MINUTE_IN_SECONDS) return;
        if (get_transient(self::LOCK)) return;
        set_transient(self::LOCK, 1, 5 * MINUTE_IN_SECONDS);
        update_option(self::LAST, time(), false);
        // A válasz kimegy a látogatónak, utána fut a háttérben.
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        else litespeed_finish_request();
        ignore_user_abort(true);
        include ABSPATH.'wp-cron.php';
    }

    /** Állapot a kulcsos státusz-végponthoz (diag=cron). */
    static function diagnose(){
        $due = self::oldest_due();
        $hooks = [];
        foreach([ AbandonedCart::HOOK, Gls::CRON, 'action_scheduler_run_queue', 'woocommerce_cleanup_sessions', 'wp_version_check' ] as $hook){
            $t = wp_next_scheduled($hook);
            $hooks[$hook] = $t ? wp_date('Y-m-d H:i', $t) : null;
        }
        $last = (int)get_option(self::LAST, 0);
        return [
            'now' => wp_date('Y-m-d H:i'),
            'disable_wp_cron' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'alternate_wp_cron' => defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON,
            'finish_request' => self::finish_available(),
            'oldest_due' => $due ? wp_date('Y-m-d H:i', $due) : null,
            'selfcron_last' => $last ? wp_date('Y-m-d H:i', $last) : null,
            'hooks' => $hooks,
        ];
    }
}
