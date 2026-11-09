<?php

namespace VasskisgepTheme;

/**
 * Munkatársi fiók csak az adminban (Dávid, 2026-11-09: „ha egy böngészőben belépek az adminba, a sima webshopon ne lépjen
 * be automatikusan ugyanabba a fiókba, mert a böngészőt többen használjuk”): a webshop oldalain (és a kosár / pénztár
 * háttérkéréseinél) az adminisztrátor, boltkezelő, szerkesztő és szerző fiók vendégként látszik. Az admin felület, az
 * admin háttérkérései (admin-ajax, admin-post), a REST (blokkszerkesztő, WooCommerce admin), a belépés és az előnézet
 * változatlan. Vásárlói fiókra nem vonatkozik.
 */
class StaffFrontend {
    const ROLES = [ 'administrator', 'shop_manager', 'editor', 'author' ];

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_filter('determine_current_user', [$this, 'frontend_user'], 99);
        // Ha egy bővítmény már a téma előtt megállapította a felhasználót, induláskor (az admin-sáv és a kosár előtt) kiléptetjük.
        add_action('init', [$this, 'reset'], -1000);
        if (did_action('init')) $this->reset();
    }

    function reset(){
        $id = get_current_user_id();
        if ($id && $this->frontend_user($id) === 0) wp_set_current_user(0);
    }

    /** admin-ajax kérés (a WordPress a webshop oldalairól is ezt használja, pl. NAV-lekérdezés). */
    private static function ajax_request(){
        return (defined('DOING_AJAX') && DOING_AJAX) || strpos((string)($_SERVER['REQUEST_URI'] ?? ''), '/admin-ajax.php') !== false;
    }

    /** Az admin-ajax kérés az admin felületről jön-e (a hivatkozó oldal szerint; ha nincs hivatkozó: igen). */
    private static function ajax_from_admin(){
        $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
        return $referer === '' || strpos($referer, '/wp-admin/') !== false;
    }

    /** Admin-oldali kérés: itt a munkatárs bejelentkezve marad. */
    private static function backend_request(){
        // A webshop oldalairól jövő admin-ajax (pl. NAV-lekérdezés a pénztárban) vendégként fut, mint maga az oldal:
        // különben a biztonsági kód (nonce) nem egyezik, és a kosár munkamenete is összekeveredik (Dávid, 2026-11-09).
        if (self::ajax_request()) return self::ajax_from_admin();
        if (is_admin() || (defined('WP_CLI') && WP_CLI) || (defined('DOING_CRON') && DOING_CRON)) return true;
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if (strpos($uri, 'wp-login.php') !== false) return true;
        // REST: az admin (blokkszerkesztő, WooCommerce admin) igen, a webshop kosara (wc/store) nem.
        $rest = strpos($uri, '/wp-json/') !== false ? substr($uri, strpos($uri, '/wp-json/') + 9) : (isset($_GET['rest_route']) ? ltrim((string)$_GET['rest_route'], '/') : null);
        if ($rest !== null) return strpos($rest, 'wc/store') !== 0;
        // Előnézet és testreszabó.
        if (isset($_GET['preview']) || isset($_GET['preview_id']) || isset($_GET['customize_changeset_uuid']) || isset($_GET['elementor-preview'])) return true;
        return false;
    }

    function frontend_user($user_id){
        if (!$user_id) return $user_id;
        $user = get_userdata((int)$user_id);
        $staff = $user && array_intersect(self::ROLES, (array)$user->roles);
        if (!$staff) return $user_id;
        if (self::backend_request()){
            // Az admin háttérkérései (pl. heartbeat) ne vegyék át a webshopban vendégként használt kosarat: a WooCommerce
            // a vendég-munkamenetet a felhasználóéba költöztetné (és törölné), a webshopban pedig kiürülne a kosár.
            if (self::ajax_request() && defined('COOKIEHASH')) unset($_COOKIE['wp_woocommerce_session_'.COOKIEHASH]);
            return $user_id;
        }
        return 0;
    }
}
