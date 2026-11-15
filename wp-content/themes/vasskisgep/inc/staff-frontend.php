<?php

namespace VasskisgepTheme;

/**
 * Munkatársi fiók csak az adminban (Dávid, 2026-11-09: „ha egy böngészőben belépek az adminba, a sima webshopon ne lépjen
 * be automatikusan ugyanabba a fiókba, mert a böngészőt többen használjuk”): a webshop oldalain (és a kosár / pénztár
 * háttérkéréseinél) az adminisztrátor, boltkezelő, szerkesztő és szerző fiók vendégként látszik. Az admin felület, az
 * admin háttérkérései (admin-ajax, admin-post), a REST (blokkszerkesztő, WooCommerce admin), a belépés és az előnézet
 * változatlan. Vásárlói fiókra nem vonatkozik.
 *
 * Ha a munkatárs a webshop saját belépő űrlapján (Fiókom, pénztár) lép be, a webshopban is bejelentkezve marad
 * (Levi, 2026-11-13: „nem tudok belépni a saját fiókomba, nem az adminba, hanem a sima weboldalra”). Ezt egy külön
 * süti jelzi, ami az adott belépéshez (munkamenet-tokenhez) kötött: az admin-belépés (wp-login.php) továbbra sem
 * viszi át a fiókot a webshopba, kilépéskor pedig érvényét veszti.
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

    const FRONT_COOKIE = 'vk_front_login';

    function __construct(){
        add_filter('determine_current_user', [$this, 'frontend_user'], 99);
        add_action('set_logged_in_cookie', [$this, 'remember_front_login'], 10, 6);
        add_action('clear_auth_cookie', [$this, 'forget_front_login']);
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
        // A REST előtagot az iu_theme átnevezi (rest_url_prefix), ezért nem csak a /wp-json/ számít (különben az Analitika
        // és a WooCommerce admin REST-kérései vendégként futnának és elutasulnának; Dávid, 2026-11-12).
        $rest = null;
        $prefixes = array_unique(array_filter([ 'wp-json', function_exists('rest_get_url_prefix') ? trim((string)rest_get_url_prefix(), '/') : '' ]));
        $path = (string)parse_url($uri, PHP_URL_PATH);
        foreach($prefixes as $prefix){
            $pos = strpos($path, '/'.$prefix.'/');
            if ($pos !== false){ $rest = substr($path, $pos + strlen($prefix) + 2); break; }
        }
        if ($rest === null && isset($_GET['rest_route'])) $rest = ltrim((string)$_GET['rest_route'], '/');
        if ($rest !== null) return strpos($rest, 'wc/store') !== 0;
        // Előnézet és testreszabó.
        if (isset($_GET['preview']) || isset($_GET['preview_id']) || isset($_GET['customize_changeset_uuid']) || isset($_GET['elementor-preview'])) return true;
        return false;
    }

    /** A webshop belépő űrlapjáról (WooCommerce Fiókom / pénztár) jövő belépés-e. */
    private static function front_login_request(){
        return isset($_POST['woocommerce-login-nonce']);
    }

    private static function cookie_name(){
        return self::FRONT_COOKIE.'_'.(defined('COOKIEHASH') ? COOKIEHASH : md5(home_url()));
    }

    private static function sign($user_id, $token){
        return hash_hmac('sha256', (int)$user_id.'|'.$token, wp_salt('logged_in'));
    }

    function remember_front_login($cookie, $expire, $expiration, $user_id, $scheme, $token){
        $name = self::cookie_name();
        if (!self::front_login_request()){
            // Admin-belépés: a korábbi webshop-belépés jelzése nem vihető át az új munkamenetre.
            if (isset($_COOKIE[$name]) && !headers_sent()) setcookie($name, '', time() - YEAR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
            return;
        }
        $user = get_userdata((int)$user_id);
        if (!$user || !array_intersect(self::ROLES, (array)$user->roles) || headers_sent()) return;
        $value = (int)$user_id.'|'.self::sign($user_id, $token);
        setcookie($name, $value, $expire, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE[$name] = $value;
    }

    function forget_front_login(){
        $name = self::cookie_name();
        if (!headers_sent()) setcookie($name, '', time() - YEAR_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        unset($_COOKIE[$name]);
    }

    /** A munkatárs a webshopban lépett be (érvényes, a mostani munkamenethez kötött jelzés). */
    private static function front_login_valid($user_id){
        $raw = (string)($_COOKIE[self::cookie_name()] ?? '');
        if ($raw === '' || !defined('LOGGED_IN_COOKIE') || empty($_COOKIE[LOGGED_IN_COOKIE])) return false;
        [$id, $sig] = array_pad(explode('|', $raw, 2), 2, '');
        if ((int)$id !== (int)$user_id) return false;
        $parts = wp_parse_auth_cookie($_COOKIE[LOGGED_IN_COOKIE], 'logged_in');
        if (!$parts || empty($parts['token'])) return false;
        return hash_equals(self::sign($user_id, $parts['token']), $sig);
    }

    function frontend_user($user_id){
        if (!$user_id) return $user_id;
        $user = get_userdata((int)$user_id);
        $staff = $user && array_intersect(self::ROLES, (array)$user->roles);
        if (!$staff) return $user_id;
        if (self::front_login_valid($user_id)) return $user_id;
        if (self::backend_request()){
            // Az admin háttérkérései (pl. heartbeat) ne vegyék át a webshopban vendégként használt kosarat: a WooCommerce
            // a vendég-munkamenetet a felhasználóéba költöztetné (és törölné), a webshopban pedig kiürülne a kosár.
            if (self::ajax_request() && defined('COOKIEHASH')) unset($_COOKIE['wp_woocommerce_session_'.COOKIEHASH]);
            return $user_id;
        }
        return 0;
    }
}
