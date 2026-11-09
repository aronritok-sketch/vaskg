<?php

namespace VasskisgepTheme;

/**
 * Kosárban hagyott termékek emlékeztetője (ügyfélkérés, 2026-11, Dávid 7. pontja; a felépítés a küldött minta
 * szerint): ha valaki termékeket hagyott a kosárban, és nem rendelt, aznap 20:00-kor (magyar idő) egy levelet kap
 * a termékekkel, „Vissza a kosárhoz” gombbal (visszatölti a kosarat), „Miért minket” sávval és „Ez is érdekelhet”
 * ajánlatokkal. Ugyanarról a kosártartalomról csak egyszer; ha később más termékeket hagy benne, arról újra.
 *
 * Csak az kap ilyet, akinek tudjuk az e-mail címét: belépett vásárló, vagy aki a pénztárban beírta. Leiratkozó link
 * minden levélben. A levél WooCommerce levél („Kosáremlékeztető”), a WooCommerce → Beállítások → E-mailek alatt
 * kikapcsolható.
 */
class AbandonedCart {
    const TABLE = 'vk_carts';
    const DB_VERSION = '1';
    const HOOK = 'vasskisgep_abandoned_carts';
    const SEND_HOUR = 20;
    const MIN_AGE = 30 * MINUTE_IN_SECONDS;

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('init', [$this, 'install']);
        add_action('init', [$this, 'schedule']);
        add_action(self::HOOK, [__CLASS__, 'send_due']);
        add_action('woocommerce_cart_updated', [$this, 'sync'], 99);
        add_action('wc_ajax_vk_cart_email', [$this, 'ajax_email']);
        add_action('wp_footer', [$this, 'checkout_script'], 99);
        add_action('woocommerce_checkout_order_processed', [$this, 'ordered'], 10, 3);
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'ordered']);
        add_action('template_redirect', [$this, 'links'], 1);
        add_filter('woocommerce_email_classes', [$this, 'email_class']);
        add_action('admin_post_vk_cart_test', [$this, 'test_mail']);
    }

    /** „Próbalevél nekem”: a kosáremlékeztető a bejelentkezett munkatársnak, néhány mostani termékkel. */
    function test_mail(){
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('vk_cart_test')) wp_die('', '', [ 'response' => 403 ]);
        $user = wp_get_current_user();
        $ids = wc_get_products([ 'status' => 'publish', 'limit' => 3, 'orderby' => 'date', 'order' => 'DESC', 'stock_status' => 'instock', 'return' => 'ids' ]);
        $items = array_map(function($id){ return [ 'product_id' => (int)$id, 'variation_id' => 0, 'qty' => 1, 'variation' => [] ]; }, $ids);
        $row = (object)[ 'email' => $user->user_email, 'items' => wp_json_encode($items), 'token' => 'proba', 'hash' => '' ];
        $emails = WC()->mailer()->get_emails();
        $email = $emails['VK_Email_Cart'] ?? null;
        $ok = $email && $email->is_enabled() ? $email->trigger($row) : false;
        wp_safe_redirect(add_query_arg([ 'vk_cart_test' => $ok ? 'ok' : 'hiba' ], wp_get_referer() ?: admin_url('admin.php?page=wc-settings&tab=email')));
        exit;
    }

    /** Állapot a kulcsos státusz-végponthoz (diag=carts): darabszámok, a legutóbbi kosarak (e-mail címek kitakarva), a következő küldés. */
    static function diagnose(){
        global $wpdb;
        $mask = function($email){ $at = strpos($email, '@'); return $at === false ? '?' : substr($email, 0, 2).'…'.substr($email, $at); };
        $rows = (array)$wpdb->get_results('SELECT email, status, hash, sent_hash, updated, sent_at, items FROM '.self::table().' ORDER BY updated DESC LIMIT 8');
        $emails = function_exists('WC') ? WC()->mailer()->get_emails() : [];
        $next = wp_next_scheduled(self::HOOK);
        return [
            'table' => (bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', self::table())),
            'counts' => $wpdb->get_results('SELECT status, COUNT(*) n FROM '.self::table().' GROUP BY status', ARRAY_A),
            'email_enabled' => isset($emails['VK_Email_Cart']) ? $emails['VK_Email_Cart']->is_enabled() : null,
            'next_run' => $next ? wp_date('Y-m-d H:i', $next) : null,
            'now' => wp_date('Y-m-d H:i'),
            'latest' => array_map(function($r) use ($mask){ return [ 'email' => $mask($r->email), 'status' => $r->status, 'items' => count((array)json_decode((string)$r->items, true)), 'pending' => $r->hash !== '' && $r->hash !== $r->sent_hash, 'updated_utc' => $r->updated, 'sent_utc' => $r->sent_at ]; }, $rows),
        ];
    }

    static function table(){
        global $wpdb;
        return $wpdb->prefix.self::TABLE;
    }

    function install(){
        if (get_option('vk_carts_db') === self::DB_VERSION) return;
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        dbDelta('CREATE TABLE '.self::table().' (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            email varchar(190) NOT NULL,
            user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            items longtext NOT NULL,
            hash char(32) NOT NULL DEFAULT \'\',
            sent_hash char(32) NOT NULL DEFAULT \'\',
            token char(32) NOT NULL,
            status varchar(20) NOT NULL DEFAULT \'open\',
            updated datetime NOT NULL,
            sent_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY status_updated (status, updated)
        ) '.$wpdb->get_charset_collate().';');
        update_option('vk_carts_db', self::DB_VERSION, false);
    }

    /** Naponta 20:00-kor (a webhely időzónája szerint). */
    function schedule(){
        if (wp_next_scheduled(self::HOOK)) return;
        $now = new \DateTime('now', wp_timezone());
        $next = (clone $now)->setTime(self::SEND_HOUR, 0);
        if ($next <= $now) $next->modify('+1 day');
        wp_schedule_event($next->getTimestamp(), 'daily', self::HOOK);
    }

    /* ---------- Kosár és e-mail cím ---------- */

    private function email(){
        if (is_user_logged_in()){
            $user = wp_get_current_user();
            if ($user && is_email($user->user_email)) return strtolower($user->user_email);
        }
        $email = function_exists('WC') && WC()->session ? (string)WC()->session->get('vk_cart_email') : '';
        return is_email($email) ? strtolower($email) : '';
    }

    static function cart_items(){
        $items = [];
        if (!function_exists('WC') || !WC()->cart) return $items;
        foreach(WC()->cart->get_cart() as $line){
            $items[] = [ 'product_id' => (int)$line['product_id'], 'variation_id' => (int)($line['variation_id'] ?? 0), 'qty' => (int)$line['quantity'], 'variation' => (array)($line['variation'] ?? []) ];
        }
        return $items;
    }

    function sync(){
        if (is_admin() && !wp_doing_ajax()) return;
        $email = $this->email();
        if ($email === '') return;
        $items = self::cart_items();
        $hash = $items ? md5(wp_json_encode($items)) : '';
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT id, hash, status FROM '.self::table().' WHERE email = %s', $email));
        if ($row && $row->status === 'unsub') return;
        if ($row && $row->hash === $hash && $row->status === 'open') return;
        $data = [ 'email' => $email, 'user_id' => get_current_user_id(), 'items' => wp_json_encode($items), 'hash' => $hash, 'status' => 'open', 'updated' => current_time('mysql', true) ];
        if ($row) $wpdb->update(self::table(), $data, [ 'id' => $row->id ]);
        else $wpdb->insert(self::table(), $data + [ 'token' => wp_generate_password(32, false), 'sent_hash' => '' ]);
    }

    /** A pénztárban beírt e-mail cím (vendég vásárló). */
    function ajax_email(){
        $email = sanitize_email(wp_unslash($_REQUEST['email'] ?? ''));
        if (!is_email($email) || !WC()->session){ wp_send_json_error(); }
        WC()->session->set('vk_cart_email', strtolower($email));
        $this->sync();
        wp_send_json_success();
    }

    function checkout_script(){
        if (!function_exists('is_checkout') || !is_checkout() || is_user_logged_in()) return;
        $url = \WC_AJAX::get_endpoint('vk_cart_email');
        echo '<script>(function($){if(!$)return;var last="";$(document.body).on("change blur","#billing_email",function(){var v=$.trim(this.value);if(!v||v===last||!/^[^@\\s]+@[^@\\s]+\\.[^@\\s]+$/.test(v))return;last=v;$.get('.wp_json_encode($url).',{email:v});});})(window.jQuery);</script>';
    }

    /** Leadott rendelés: a kosár nem „hagyott”. */
    function ordered($order_id, $posted = [], $order = null){
        $order = $order instanceof \WC_Order ? $order : wc_get_order($order_id instanceof \WC_Order ? $order_id->get_id() : $order_id);
        if (!$order) return;
        $email = strtolower((string)$order->get_billing_email());
        if ($email === '') return;
        global $wpdb;
        $wpdb->update(self::table(), [ 'status' => 'ordered', 'items' => '[]', 'hash' => '', 'updated' => current_time('mysql', true) ], [ 'email' => $email ]);
    }

    /* ---------- Levél: kosár visszatöltése, leiratkozás ---------- */

    function links(){
        if (empty($_GET['vk_cart']) && empty($_GET['vk_cart_unsub'])) return;
        global $wpdb;
        $token = preg_replace('/[^A-Za-z0-9]/', '', (string)wp_unslash($_GET['vk_cart'] ?? $_GET['vk_cart_unsub']));
        $row = $token !== '' ? $wpdb->get_row($wpdb->prepare('SELECT * FROM '.self::table().' WHERE token = %s', $token)) : null;
        if (!$row){ wp_safe_redirect(home_url('/')); exit; }
        if (!empty($_GET['vk_cart_unsub'])){
            $wpdb->update(self::table(), [ 'status' => 'unsub' ], [ 'id' => $row->id ]);
            wp_die('<p>Leiratkoztál: több kosáremlékeztetőt nem küldünk erre a címre.</p><p><a href="'.esc_url(home_url('/')).'">Vissza a webshopba</a></p>', 'Leiratkozás', [ 'response' => 200 ]);
        }
        if (function_exists('WC') && WC()->cart){
            if (WC()->cart->is_empty()){
                foreach((array)json_decode((string)$row->items, true) as $item){
                    WC()->cart->add_to_cart((int)$item['product_id'], max(1, (int)$item['qty']), (int)$item['variation_id'], (array)($item['variation'] ?? []));
                }
            }
            if (WC()->session) WC()->session->set('vk_cart_email', $row->email);
        }
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }

    /* ---------- Küldés ---------- */

    /** A ma (az előző küldés óta) kosárban hagyott, még nem jelzett kosarak. */
    static function send_due(){
        global $wpdb;
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();
        $email = $emails['VK_Email_Cart'] ?? null;
        if (!$email || !$email->is_enabled()) return 0;
        $since = gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS);
        $until = gmdate('Y-m-d H:i:s', time() - self::MIN_AGE);
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM '.self::table()." WHERE status = 'open' AND hash <> '' AND hash <> sent_hash AND updated BETWEEN %s AND %s LIMIT 200", $since, $until));
        $sent = 0;
        foreach((array)$rows as $row){
            // Közben rendelt (más címmel indult, de azonos e-maillel)?
            $recent = wc_get_orders([ 'billing_email' => $row->email, 'date_created' => '>'.(strtotime($row->updated.' UTC') - HOUR_IN_SECONDS), 'limit' => 1, 'return' => 'ids' ]);
            if ($recent){
                $wpdb->update(self::table(), [ 'status' => 'ordered' ], [ 'id' => $row->id ]);
                continue;
            }
            if ($email->trigger($row)){
                $wpdb->update(self::table(), [ 'sent_hash' => $row->hash, 'sent_at' => current_time('mysql', true) ], [ 'id' => $row->id ]);
                $sent++;
            }
        }
        return $sent;
    }

    function email_class($emails){
        require_once __DIR__.'/abandoned-cart-email.php';
        $emails['VK_Email_Cart'] = new VK_Email_Cart();
        return $emails;
    }
}
