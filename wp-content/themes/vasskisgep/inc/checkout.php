<?php

namespace VasskisgepTheme;

/**
 * Pénztár és címszerkesztés kényelmi funkciói:
 *   – irányítószám → település (Magyar Posta irányítószám-jegyzék, assets/iranyitoszamok.json; ha egy irányítószámhoz
 *     több település tartozik, választólista jelenik meg),
 *   – adószám → cégnév és székhely (NAV Online Számla queryTaxpayer; a bolt NAV technikai felhasználójával működik,
 *     beállítás: WooCommerce → Adószám-lekérdezés, vagy wp-config.php konstansok).
 */
class Checkout {
    const OPTION = 'vasskisgep_nav';
    const ENDPOINT = 'https://api.onlineszamla.nav.gov.hu/invoiceService/v3/queryTaxpayer';
    const ENDPOINT_TEST = 'https://api-test.onlineszamla.nav.gov.hu/invoiceService/v3/queryTaxpayer';
    /** Lekérdezések száma IP-címenként 10 percen belül (visszaélés ellen). */
    const RATE_LIMIT = 20;

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 120);
        add_action('wp_ajax_vasskisgep_taxpayer', [$this, 'ajax_taxpayer']);
        add_action('wp_ajax_nopriv_vasskisgep_taxpayer', [$this, 'ajax_taxpayer']);
        add_action('admin_menu', [$this, 'admin_menu'], 60);
        add_action('admin_post_vasskisgep_nav', [$this, 'save_settings']);

        // Végösszeg három sorban (ügyfélkérés, 2026-10): nettó érték, ÁFA (27%), bruttó érték – a zárójeles
        // „Adótartalom” helyett, a pénztárban és a kosár oldalon.
        foreach([ 'woocommerce_review_order_before_order_total', 'woocommerce_cart_totals_before_order_total' ] as $hook){
            add_action($hook, [$this, 'net_and_tax_rows']);
        }
        foreach([ 'woocommerce_review_order_after_order_total', 'woocommerce_cart_totals_after_order_total' ] as $hook){
            add_action($hook, [$this, 'total_label_off']);
        }
        add_filter('woocommerce_cart_totals_order_total_html', [$this, 'order_total_html']);
    }

    /* ---------- Végösszeg: nettó, ÁFA, bruttó ---------- */

    function net_and_tax_rows(){
        $cart = WC()->cart;
        if (!$cart || !wc_tax_enabled()) return;
        $tax = (float)$cart->get_total_tax();
        $gross = (float)$cart->get_total('edit');
        echo '<tr class="vk-total-net"><th>Nettó érték (áfa nélkül)</th><td data-title="Nettó érték (áfa nélkül)">'.wc_price($gross - $tax).'</td></tr>';
        foreach($cart->get_tax_totals() as $code => $row){
            $percent = \WC_Tax::get_rate_percent((int)$row->tax_rate_id);
            $label = 'ÁFA'.($percent !== '' ? ' ('.str_replace('.', ',', $percent).')' : '');
            echo '<tr class="vk-total-tax tax-rate-'.esc_attr(sanitize_title($code)).'"><th>'.esc_html($label).'</th><td data-title="'.esc_attr($label).'">'.wp_kses_post($row->formatted_amount).'</td></tr>';
        }
        // A következő „Total” (Összeg) sor felirata: Bruttó érték.
        add_filter('gettext_woocommerce', [$this, 'total_label'], 10, 2);
    }

    function total_label($translation, $text){
        return $text === 'Total' ? 'Bruttó érték' : $translation;
    }

    function total_label_off(){
        remove_filter('gettext_woocommerce', [$this, 'total_label'], 10);
    }

    /** A végösszeg mellől a zárójeles „(Adótartalom: … ÁFA)” ki: külön sorban van. */
    function order_total_html($html){
        if (!wc_tax_enabled() || !WC()->cart) return $html;
        return '<strong>'.WC()->cart->get_total().'</strong>';
    }

    function enqueue(){
        if (!function_exists('is_checkout') || !(is_checkout() || is_account_page())) return;
        $dir = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();
        wp_enqueue_script('vasskisgep-checkout', $uri.'/assets/checkout.js', ['jquery'], filemtime($dir.'/assets/checkout.js'), true);
        wp_localize_script('vasskisgep-checkout', 'vasskisgepCheckout', [
            'postcodes' => $uri.'/assets/iranyitoszamok.json?ver='.filemtime($dir.'/assets/iranyitoszamok.json'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'taxpayer' => self::configured(),
            'nonce' => wp_create_nonce('vasskisgep-taxpayer'),
        ]);
    }

    /* ---------- NAV beállítások ---------- */

    /** A NAV technikai felhasználó adatai: wp-config.php konstansok (VK_NAV_*) vagy az admin oldalon mentett értékek. */
    static function settings(){
        $saved = (array)get_option(self::OPTION, []);
        $get = function($key, $constant) use ($saved){
            return defined($constant) ? (string)constant($constant) : (string)($saved[$key] ?? '');
        };
        return [
            'login' => $get('login', 'VK_NAV_LOGIN'),
            'password' => $get('password', 'VK_NAV_PASSWORD'),
            'sign_key' => $get('sign_key', 'VK_NAV_SIGN_KEY'),
            'tax_number' => substr(preg_replace('/\D/', '', $get('tax_number', 'VK_NAV_TAX_NUMBER')), 0, 8),
            'test' => defined('VK_NAV_TEST') ? (bool)VK_NAV_TEST : !empty($saved['test']),
        ];
    }

    static function configured(){
        $s = self::settings();
        return $s['login'] !== '' && $s['password'] !== '' && $s['sign_key'] !== '' && strlen($s['tax_number']) === 8;
    }

    function admin_menu(){
        add_submenu_page('woocommerce', 'Adószám-lekérdezés (NAV)', 'Adószám-lekérdezés', 'manage_woocommerce', 'vasskisgep-nav', [$this, 'settings_page']);
    }

    function settings_page(){
        $s = self::settings();
        $saved = (array)get_option(self::OPTION, []);
        $test = null;
        if (isset($_GET['vk_test']) && self::configured()){
            $test = self::query_taxpayer($s['tax_number'], true);
        }
        echo '<div class="wrap"><h1>Adószám-lekérdezés (NAV Online Számla)</h1>';
        echo '<p>A pénztárban az adószám beírása után a cégnév és a székhely címe automatikusan kitöltődik. A lekérdezés a NAV Online Számla rendszerén keresztül történik, ehhez a bolt <strong>NAV technikai felhasználója</strong> kell (onlineszamla.nav.gov.hu → Felhasználók → Technikai felhasználó: login, jelszó, XML aláírókulcs). A lekérdezés ingyenes.</p>';
        if (isset($_GET['updated'])) echo '<div class="notice notice-success"><p>Mentve.</p></div>';
        if ($test !== null){
            echo is_wp_error($test)
                ? '<div class="notice notice-error"><p>Próba sikertelen: '.esc_html($test->get_error_message()).'</p></div>'
                : '<div class="notice notice-success"><p>Próba sikeres: '.esc_html($test['company'].' – '.$test['postcode'].' '.$test['city'].', '.$test['address']).'</p></div>';
        }
        $constants = defined('VK_NAV_LOGIN') || defined('VK_NAV_PASSWORD') || defined('VK_NAV_SIGN_KEY') || defined('VK_NAV_TAX_NUMBER');
        if ($constants) echo '<p><em>A wp-config.php VK_NAV_* konstansai felülírják az itt megadott értékeket.</em></p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('vasskisgep_nav');
        echo '<input type="hidden" name="action" value="vasskisgep_nav"><table class="form-table">';
        echo '<tr><th><label for="vk-nav-tax">A bolt adószáma</label></th><td><input id="vk-nav-tax" class="regular-text" name="tax_number" value="'.esc_attr($saved['tax_number'] ?? '').'" placeholder="12345678-2-15"></td></tr>';
        echo '<tr><th><label for="vk-nav-login">Technikai felhasználó login</label></th><td><input id="vk-nav-login" class="regular-text" name="login" value="'.esc_attr($saved['login'] ?? '').'" autocomplete="off"></td></tr>';
        echo '<tr><th><label for="vk-nav-pass">Jelszó</label></th><td><input id="vk-nav-pass" class="regular-text" type="password" name="password" value="" autocomplete="new-password" placeholder="'.(!empty($saved['password']) ? 'mentve – üresen hagyva marad' : '').'"></td></tr>';
        echo '<tr><th><label for="vk-nav-sign">XML aláírókulcs</label></th><td><input id="vk-nav-sign" class="regular-text" type="password" name="sign_key" value="" autocomplete="off" placeholder="'.(!empty($saved['sign_key']) ? 'mentve – üresen hagyva marad' : '').'"></td></tr>';
        echo '<tr><th>Tesztrendszer</th><td><label><input type="checkbox" name="test" value="1" '.checked(!empty($saved['test']), true, false).'> NAV teszt környezet (api-test) – csak a NAV tesztrendszerében regisztrált technikai felhasználóval</label></td></tr>';
        echo '</table>';
        submit_button('Mentés');
        echo '</form>';
        if (self::configured()) echo '<p><a class="button" href="'.esc_url(add_query_arg('vk_test', 1)).'">Próba: a bolt saját adószámának lekérdezése</a></p>';
        else echo '<p><strong>Állapot:</strong> nincs beállítva – a pénztárban az adószám mező kézi kitöltésű.</p>';
        echo '</div>';
    }

    function save_settings(){
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('vasskisgep_nav')) wp_die('Nincs jogosultság.');
        $saved = (array)get_option(self::OPTION, []);
        $saved['tax_number'] = sanitize_text_field(wp_unslash($_POST['tax_number'] ?? ''));
        $saved['login'] = sanitize_text_field(wp_unslash($_POST['login'] ?? ''));
        foreach([ 'password', 'sign_key' ] as $key){
            $value = trim((string)wp_unslash($_POST[$key] ?? ''));
            if ($value !== '') $saved[$key] = $value;
        }
        $saved['test'] = !empty($_POST['test']);
        update_option(self::OPTION, $saved, false);
        wp_safe_redirect(admin_url('admin.php?page=vasskisgep-nav&updated=1'));
        exit;
    }

    /* ---------- Lekérdezés ---------- */

    function ajax_taxpayer(){
        check_ajax_referer('vasskisgep-taxpayer', 'nonce');
        if (!self::configured()) wp_send_json_error([ 'message' => 'disabled' ]);
        $digits = substr(preg_replace('/\D/', '', (string)wp_unslash($_POST['tax_number'] ?? '')), 0, 8);
        if (strlen($digits) !== 8) wp_send_json_error([ 'message' => 'Az adószám első 8 számjegye kell.' ]);

        $ip = preg_replace('/[^0-9a-f.:]/i', '', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $rate = 'vk_taxpayer_rate_'.md5($ip);
        $count = (int)get_transient($rate);
        if ($count >= self::RATE_LIMIT) wp_send_json_error([ 'message' => 'Túl sok lekérdezés, kérjük, töltse ki kézzel.' ]);
        set_transient($rate, $count + 1, 10 * MINUTE_IN_SECONDS);

        $result = self::query_taxpayer($digits);
        if (is_wp_error($result)) wp_send_json_error([ 'message' => $result->get_error_message() ]);
        wp_send_json_success($result);
    }

    /**
     * NAV queryTaxpayer (Online Számla API 3.0). Eredmény: cégnév, teljes adószám, székhely (irányítószám, város,
     * utca-házszám), 1 napig gyorsítótárazva.
     * @return array|\WP_Error
     */
    static function query_taxpayer($digits, $fresh = false){
        $cache = 'vk_taxpayer_'.$digits;
        if (!$fresh){
            $cached = get_transient($cache);
            if (is_array($cached)) return $cached;
        }
        $s = self::settings();
        $request_id = 'VK'.gmdate('YmdHis').wp_rand(100000, 999999);
        $now = microtime(true);
        $timestamp = gmdate('Y-m-d\TH:i:s', (int)$now).sprintf('.%03dZ', (int)(($now - floor($now)) * 1000));
        $signature = strtoupper(hash('sha3-512', $request_id.gmdate('YmdHis', (int)$now).$s['sign_key']));
        $password = strtoupper(hash('sha512', $s['password']));
        $software_id = substr('HU'.$s['tax_number'].'-VKWEB01', 0, 18);
        $esc = function($v){ return htmlspecialchars((string)$v, ENT_XML1 | ENT_QUOTES, 'UTF-8'); };

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<QueryTaxpayerRequest xmlns="http://schemas.nav.gov.hu/OSA/3.0/api" xmlns:common="http://schemas.nav.gov.hu/NTCA/1.0/common">'
            .'<common:header><common:requestId>'.$request_id.'</common:requestId><common:timestamp>'.$timestamp.'</common:timestamp>'
            .'<common:requestVersion>3.0</common:requestVersion><common:headerVersion>1.0</common:headerVersion></common:header>'
            .'<common:user><common:login>'.$esc($s['login']).'</common:login>'
            .'<common:passwordHash cryptoType="SHA-512">'.$password.'</common:passwordHash>'
            .'<common:taxNumber>'.$s['tax_number'].'</common:taxNumber>'
            .'<common:requestSignature cryptoType="SHA3-512">'.$signature.'</common:requestSignature></common:user>'
            .'<software><softwareId>'.$software_id.'</softwareId><softwareName>Vass Kisgep webshop</softwareName>'
            .'<softwareOperation>ONLINE_SERVICE</softwareOperation><softwareMainVersion>1.0</softwareMainVersion>'
            .'<softwareDevName>HelloProVision</softwareDevName><softwareDevContact>'.$esc(get_option('admin_email')).'</softwareDevContact>'
            .'<softwareDevCountryCode>HU</softwareDevCountryCode></software>'
            .'<taxNumber>'.$digits.'</taxNumber></QueryTaxpayerRequest>';

        $response = wp_remote_post($s['test'] ? self::ENDPOINT_TEST : self::ENDPOINT, [
            'timeout' => 15,
            'headers' => [ 'Content-Type' => 'application/xml', 'Accept' => 'application/xml' ],
            'body' => $xml,
        ]);
        if (is_wp_error($response)) return new \WP_Error('nav', 'A NAV nem érhető el, kérjük, töltse ki kézzel.');
        $body = wp_remote_retrieve_body($response);
        $doc = @simplexml_load_string($body);
        if (!$doc) return new \WP_Error('nav', 'A NAV válasza nem értelmezhető.');
        $first = function($node, $name){
            $found = $node->xpath('.//*[local-name()="'.$name.'"]');
            return $found ? trim((string)$found[0]) : '';
        };

        if ($first($doc, 'funcCode') !== 'OK'){
            $message = $first($doc, 'message') ?: $first($doc, 'errorCode');
            if (function_exists('wc_get_logger')) wc_get_logger()->warning('NAV queryTaxpayer: '.$message, [ 'source' => 'vasskisgep-nav' ]);
            return new \WP_Error('nav', 'A NAV lekérdezés nem sikerült'.($message !== '' ? ' ('.$message.')' : '').'.');
        }
        if ($first($doc, 'taxpayerValidity') === 'false' || !$doc->xpath('.//*[local-name()="taxpayerData"]')){
            return new \WP_Error('invalid', 'Ilyen érvényes adószám nem található.');
        }

        $data = $doc->xpath('.//*[local-name()="taxpayerData"]')[0];
        $name = $first($data, 'taxpayerShortName') ?: $first($data, 'taxpayerName');
        $vat = $first($data, 'vatCode');
        $county = $first($data, 'countyCode');
        $address = null;
        foreach($data->xpath('.//*[local-name()="taxpayerAddressItem"]') as $item){
            if (!$address || $first($item, 'taxpayerAddressType') === 'HQ') $address = $item;
            if ($first($item, 'taxpayerAddressType') === 'HQ') break;
        }
        $result = [
            'company' => $name,
            'tax_number' => $digits.($vat !== '' && $county !== '' ? '-'.$vat.'-'.$county : ''),
            'postcode' => '', 'city' => '', 'address' => '',
        ];
        if ($address){
            // A NAV nagybetűvel adja a címet (pl. „KISVÁRDA”, „VÁROSMAJOR UTCA”): szokásos írásmód.
            $title = function($text){ return mb_convert_case(mb_strtolower($text), MB_CASE_TITLE); };
            $line = trim(implode(' ', array_filter([ $title($first($address, 'streetName')), mb_strtolower($first($address, 'publicPlaceCategory')), $first($address, 'number') ])));
            $extra = array_filter([
                $first($address, 'building') !== '' ? $first($address, 'building').' ép.' : '',
                $first($address, 'staircase') !== '' ? $first($address, 'staircase').' lh.' : '',
                $first($address, 'floor') !== '' ? $first($address, 'floor').' em.' : '',
                $first($address, 'door') !== '' ? $first($address, 'door').' ajtó' : '',
            ]);
            $result['postcode'] = $first($address, 'postalCode');
            $result['city'] = $title($first($address, 'city'));
            $result['address'] = $line.($line !== '' && substr($line, -1) !== '.' && preg_match('/\d$/', $line) ? '.' : '').($extra ? ' '.implode(' ', $extra) : '');
        }
        set_transient($cache, $result, DAY_IN_SECONDS);
        return $result;
    }
}
