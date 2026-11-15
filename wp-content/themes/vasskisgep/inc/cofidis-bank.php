<?php

namespace VasskisgepTheme;

/**
 * Cofidis expressz online áruhitel – a Magyar Cofidis Bank E-direkt rendszere (a bank „E-direkt Kezdő Csomag”-ja
 * szerint; Dávid, 2026-11-15).
 *
 * – Kalkulátor: a bank beágyazott kalkulátora (iframe, `eshop-simulator/widget/sdk/v1.js`) a termékoldalon és a
 *   pénztárban, a boltkóddal (shopCode) és a hiteltermék azonosítójával (barem).
 * – Fizetés: a rendelés „Függőben” marad, a vásárló a bank hiteligénylő oldalára megy (POST: shopCode,
 *   purchaseAmount, és – ha van tanúsítvány – az initProcess-ből kapott customerKey, különben barem + orderId).
 * – Visszajelzés (cbZold / cbNarancs / cbPiros / cbTimeout, csak initProcess-szel): aláírt REST cím, a rendelésbe
 *   jegyzet; piros → visszamondva.
 * – Státusz (getDossierStatusV2) és „áru kiszállítva” (setDeliveryDoneStateV2): rendelésműveletként és óránként /
 *   teljesítéskor magától – ehhez a bank által aláírt kliens-tanúsítvány és a technikai felhasználó kell.
 * – Tanúsítvány: WooCommerce → Cofidis tanúsítvány: kulcs + CSR készítése (a privát kulcs az adatbázisban marad),
 *   a CSR-t a banknak kell elküldeni, a visszakapott tanúsítványt ide kell bemásolni.
 * – Élesítésig a kalkulátor és a fizetési mód csak tesztlinkkel látszik (a bank kérése: a fejlesztés végéig ne legyen
 *   hozzáférhető); a „Élesítve” pipa után mindenkinek.
 *
 * Titok (technikai jelszó, privát kulcs) nem kerül a kódba: a beállításokban / az adatbázisban van.
 */
class CofidisBank {
    const SOCIETY = 100;
    const TEST_COOKIE = 'vk_cofidis_test';
    const PAGE = 'vk-cofidis-cert';
    const POLL = 'vk_cofidis_poll';
    const STATUSES = [
        0 => 'Ismeretlen', 1 => 'Elemzés alatt', 2 => 'Elfogadva', 3 => 'Elutasítva', 4 => 'Elállt', 5 => 'Nyomtatva',
        6 => 'Áru kiszállítható', 7 => 'Hiteldosszié hiánypótlás', 11 => 'Finanszírozva', 12 => 'Nem finanszírozható',
        14 => 'Sikertelen azonosítás', 15 => 'Sztornózva', 16 => 'Ügyfélazonosítás folyamatban', 17 => 'Ügyfélazonosítás folyamatban',
        18 => 'Áru kiszállítható azonosítás nélkül', 19 => 'Ügyfélazonosítás még folyamatban', 20 => 'Áru kiszállítva',
    ];

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('init', [$this, 'test_link'], 5);
        add_action('template_redirect', [$this, 'start_page'], 1);
        add_action('rest_api_init', [$this, 'rest']);
        add_action('admin_menu', [$this, 'menu'], 80);
        add_action('admin_post_vk_cofidis_csr', [$this, 'action_csr']);
        add_action('admin_post_vk_cofidis_cert', [$this, 'action_cert']);
        add_action('admin_post_vk_cofidis_csr_download', [$this, 'action_csr_download']);
        add_filter('woocommerce_order_actions', [$this, 'order_actions'], 20, 2);
        add_action('woocommerce_order_action_vk_cofidis_status', [$this, 'action_status']);
        add_action('woocommerce_order_action_vk_cofidis_delivered', [$this, 'action_delivered']);
        add_action('woocommerce_order_status_completed', [$this, 'auto_delivered']);
        add_action('woocommerce_order_status_'.OrderStatuses::SHIPPED, [$this, 'auto_delivered']);
        add_action(self::POLL, [__CLASS__, 'poll']);
        add_action('init', [$this, 'schedule']);
        add_filter('wp_robots', [$this, 'noindex_page']);
    }

    /* ---------- Beállítások ---------- */

    static function opt($key, $default = ''){
        $s = get_option('woocommerce_'.Cofidis::ID.'_settings', []);
        $v = is_array($s) && isset($s[$key]) ? $s[$key] : $default;
        return is_string($v) ? trim($v) : $v;
    }

    static function live_mode(){ return self::opt('bank_mode', 'demo') === 'live'; }

    static function base(){
        $url = self::live_mode() ? self::opt('live_base', 'https://services.cofidisbank.hu/') : 'https://dmo-services.cofidisbank.hu/';
        return trailingslashit($url ?: 'https://services.cofidisbank.hu/');
    }

    static function shop_code(){ return (int)self::opt('shop_code', '1000125'); }

    /** Hiteltermékek: soronként „baremID; megnevezés”. */
    static function barems(){
        $out = [];
        foreach(preg_split('/\R/', (string)self::opt('barems', '961; THM 19,9% (II)')) as $line){
            $parts = array_map('trim', explode(';', $line, 2));
            if ((int)$parts[0] > 0) $out[(int)$parts[0]] = $parts[1] ?? '';
        }
        return $out;
    }

    static function barem(){ $b = array_keys(self::barems()); return $b ? (int)$b[0] : 0; }

    static function configured(){ return self::shop_code() > 0 && self::barem() > 0; }

    static function limits(){
        return [ (int)self::opt('min_amount', '25000') ?: 25000, (int)self::opt('max_amount', '2500000') ?: 2500000 ];
    }

    static function amount_ok($amount){
        [$min, $max] = self::limits();
        return $amount >= $min && $amount <= $max;
    }

    /* ---------- Láthatóság (élesítésig csak tesztlinkkel) ---------- */

    static function test_token(){ return substr(hash_hmac('sha256', 'vk-cofidis-test', wp_salt('auth')), 0, 20); }

    static function test_url(){ return add_query_arg(self::TEST_COOKIE, self::test_token(), home_url('/')); }

    function test_link(){
        $t = (string)($_GET[self::TEST_COOKIE] ?? '');
        if ($t === '') return;
        if ($t === 'off'){ setcookie(self::TEST_COOKIE, '', time() - DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true); unset($_COOKIE[self::TEST_COOKIE]); return; }
        if (!hash_equals(self::test_token(), $t)) return;
        setcookie(self::TEST_COOKIE, $t, time() + 60 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true);
        $_COOKIE[self::TEST_COOKIE] = $t;
    }

    static function tester(){ return hash_equals(self::test_token(), (string)($_COOKIE[self::TEST_COOKIE] ?? '')); }

    static function visible(){
        if (!self::configured()) return false;
        return self::opt('live_enabled', 'no') === 'yes' || self::tester();
    }

    function noindex_page($robots){
        if (is_page(Cofidis::INFO_PAGE) && self::opt('live_enabled', 'no') !== 'yes'){ $robots['noindex'] = true; $robots['nofollow'] = true; }
        return $robots;
    }

    /* ---------- Kalkulátor (a bank beágyazott kalkulátora) ---------- */

    static function widget($amount, $id = ''){
        $amount = (int)round($amount);
        if (!self::visible() || !self::amount_ok($amount)) return '';
        $id = $id ?: 'vk-cof-calc-'.wp_rand(1000, 999999);
        $barems = self::barems();
        $tabs = '';
        if (count($barems) > 1){
            foreach($barems as $b => $name) $tabs .= '<button type="button" class="vk-pill" data-barem="'.(int)$b.'">'.esc_html($name ?: 'Konstrukció '.$b).'</button>';
            $tabs = '<div class="vk-cof-barems">'.$tabs.'</div>';
        }
        $cfg = [ 'shopCode' => (string)self::shop_code(), 'barem' => (string)self::barem(), 'purchaseAmount' => (string)$amount, 'duration' => 12, 'url' => self::base() ];
        $info = get_page_by_path(Cofidis::INFO_PAGE);
        // Az SDK az iframe-et a megadott elembe teszi (különben az oldal aljára); az „auto-resize” üzenetre magasságot állít.
        $target = '#'.$id.' .vk-cof-frame-target';
        return '<div class="vk-cofidis vk-cofidis-bank" id="'.esc_attr($id).'">'
            .'<div class="vk-cof-bank-head"><h3 class="vk-cof-title">Cofidis expressz online áruhitel</h3>'.Catalog::cofidis_badge().'</div>'
            .'<p class="vk-cof-lead">Számold ki a havi törlesztőrészletet. A hitelt a Magyar Cofidis Bank Zrt. nyújtja; a kalkuláció tájékoztató jellegű.</p>'
            .$tabs
            .'<div class="vk-cof-frame" data-config="'.esc_attr(wp_json_encode($cfg)).'"><div class="vk-cof-frame-target"></div>'
            .'<script src="'.esc_url(self::base().'eshop-simulator/widget/sdk/v1.js').'"></script>'
            .'<script>(function(){var cfg='.wp_json_encode($cfg, JSON_UNESCAPED_SLASHES).',box=document.getElementById('.wp_json_encode($id).');'
            .'function make(b){try{cfg.barem=String(b||cfg.barem);var c=new Cetelem.Ecommerce.Calculator(cfg);c.iframe("100%",360,'.wp_json_encode($target).');}catch(e){}}'
            .'make();if(box){box.querySelectorAll("[data-barem]").forEach(function(btn){btn.addEventListener("click",function(){make(btn.getAttribute("data-barem"));});});}})();</script>'
            .'</div>'
            .($info ? '<p class="vk-cof-info"><a href="'.esc_url(get_permalink($info)).'" target="_blank">Tájékoztató, hirdetmény, ügynöki tájékoztató</a></p>' : '')
            .'</div>';
    }

    /* ---------- Átirányítás a bank hiteligénylő oldalára ---------- */

    static function start_url(\WC_Order $order){
        return add_query_arg([ 'vk_cofidis_start' => $order->get_id(), 'key' => $order->get_order_key() ], home_url('/'));
    }

    static function sign($order_id, $result){ return substr(hash_hmac('sha256', $order_id.'|'.$result, wp_salt('nonce')), 0, 24); }

    static function callback_url(\WC_Order $order, $result){
        return add_query_arg([ 'o' => $order->get_id(), 'r' => $result, 's' => self::sign($order->get_id(), $result) ], rest_url('vasskisgep/v1/cofidis/callback'));
    }

    function start_page(){
        if (!isset($_GET['vk_cofidis_start'])) return;
        $order = wc_get_order((int)$_GET['vk_cofidis_start']);
        if (!$order || !hash_equals($order->get_order_key(), (string)($_GET['key'] ?? '')) || $order->get_payment_method() !== Cofidis::ID){
            wp_die('Ismeretlen rendelés.', 'Cofidis', [ 'response' => 404 ]);
        }
        $amount = (int)round((float)$order->get_total());
        $fields = [ 'shopCode' => self::shop_code(), 'purchaseAmount' => $amount ];
        $barem = (int)$order->get_meta('_vk_cofidis_barem') ?: self::barem();
        $error = '';
        if (self::soap_ready()){
            $data = [ 'purchaseAmount' => $amount, 'barem' => $barem, 'orderId' => (string)$order->get_order_number(),
                'cbZold' => self::callback_url($order, 'zold'), 'cbNarancs' => self::callback_url($order, 'narancs'),
                'cbPiros' => self::callback_url($order, 'piros'), 'cbTimeout' => self::callback_url($order, 'timeout') ];
            $res = self::call('initProcess', [ self::SOCIETY, self::shop_code(), wp_json_encode($data) ]);
            if (is_wp_error($res) || empty($res['success']) || empty($res['customerKey'])){
                $error = is_wp_error($res) ? $res->get_error_message() : (string)($res['errorMessage'] ?? 'ismeretlen hiba');
                $order->add_order_note('Cofidis initProcess hiba: '.$error.' – átirányítás rendelésazonosítóval.');
            } else {
                $order->update_meta_data('_vk_cofidis_customer_key', (string)$res['customerKey']);
                $order->save();
                $fields['customerKey'] = (string)$res['customerKey'];
            }
        }
        if (!isset($fields['customerKey'])){
            $fields['barem'] = $barem;
            $fields['orderId'] = (string)$order->get_order_number();
        }
        if (!$order->get_meta('_vk_cofidis_started')){
            $order->update_meta_data('_vk_cofidis_started', time());
            $order->add_order_note('Cofidis: a vásárló a bank hiteligénylő oldalára ment ('.(self::live_mode() ? 'éles' : 'demo').', '.number_format($amount, 0, ',', ' ').' Ft).');
            $order->save();
        }
        nocache_headers();
        $inputs = '';
        foreach($fields as $k => $v) $inputs .= '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr((string)$v).'">';
        echo '<!doctype html><html lang="hu"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Cofidis hiteligénylés</title>'
            .'<style>body{font-family:system-ui,sans-serif;display:flex;min-height:90vh;align-items:center;justify-content:center;text-align:center;color:#1f2937}button{background:#d0103a;color:#fff;border:0;border-radius:6px;padding:.8rem 1.4rem;font-size:1rem;cursor:pointer}</style></head>'
            .'<body><form id="f" method="post" action="'.esc_url(self::base().'webeshop-application/credit-assessment').'">'.$inputs
            .'<p>Átirányítás a Cofidis Bank hiteligénylő oldalára…</p><p><button type="submit">Tovább a hiteligényléshez</button></p></form>'
            .'<script>document.getElementById("f").submit();</script></body></html>';
        exit;
    }

    /* ---------- Visszajelzés (callback) ---------- */

    function rest(){
        register_rest_route('vasskisgep/v1', '/cofidis/callback', [
            'methods' => [ 'GET', 'POST' ],
            'permission_callback' => '__return_true',
            'callback' => [ $this, 'callback' ],
        ]);
    }

    function callback(\WP_REST_Request $request){
        $id = (int)$request->get_param('o');
        $result = sanitize_key((string)$request->get_param('r'));
        if (!in_array($result, [ 'zold', 'narancs', 'piros', 'timeout' ], true) || !hash_equals(self::sign($id, $result), (string)$request->get_param('s'))){
            return new \WP_Error('cofidis', 'Érvénytelen visszahívás.', [ 'status' => 403 ]);
        }
        $order = wc_get_order($id);
        if (!$order || $order->get_payment_method() !== Cofidis::ID) return new \WP_Error('cofidis', 'Ismeretlen rendelés.', [ 'status' => 404 ]);
        $dossier = sanitize_text_field((string)$request->get_param('dossierId'));
        self::apply_result($order, $result, $dossier);

        // A vásárló böngészője: vissza a köszönőoldalra; a bank szervere: JSON.
        if (stripos((string)$request->get_header('accept'), 'text/html') !== false){
            $response = new \WP_REST_Response(null, 302);
            $response->header('Location', $order->get_checkout_order_received_url());
            return $response;
        }
        return [ 'success' => true ];
    }

    /** A bírálat eredménye a rendelésen (ismételt hívásra ugyanaz, nem duplikál). */
    static function apply_result(\WC_Order $order, $result, $dossier = ''){
        if ($dossier !== '' && $order->get_meta('_vk_cofidis_dossier') !== $dossier) $order->update_meta_data('_vk_cofidis_dossier', $dossier);
        // Zöldnél (ha van tanúsítvány) a bankkal is ellenőrizzük.
        if ($result === 'zold' && self::soap_ready() && $order->get_meta('_vk_cofidis_customer_key')){
            $check = self::call('getCustomerDossierV2', [ self::SOCIETY, self::shop_code(), (string)$order->get_meta('_vk_cofidis_customer_key'), '' ]);
            if (!is_wp_error($check) && !empty($check['success'])){
                if (!empty($check['dossierId'])) $order->update_meta_data('_vk_cofidis_dossier', (string)$check['dossierId']);
                $map = [ 'Z' => 'zold', 'N' => 'narancs', 'P' => 'piros' ];
                $result = $map[(string)($check['resultCode'] ?? '')] ?? $result;
            }
        }
        if ($order->get_meta('_vk_cofidis_result') === $result){ $order->save(); return; }
        $order->update_meta_data('_vk_cofidis_result', $result);
        $d = $order->get_meta('_vk_cofidis_dossier') ? ' Dosszié: '.$order->get_meta('_vk_cofidis_dossier').'.' : '';
        switch($result){
            case 'zold':
                $order->update_meta_data('_vk_cofidis_status', 'accepted');
                $order->add_order_note('Cofidis: előbírálat elfogadva (zöld).'.$d.' Kiszállítani a bank „Áru kiszállítható” jelzése után lehet.');
                break;
            case 'narancs':
                $order->update_meta_data('_vk_cofidis_status', 'review');
                $order->add_order_note('Cofidis: a hitelkérelem további elbírálás alatt (narancs).'.$d);
                break;
            case 'piros':
                $order->update_meta_data('_vk_cofidis_status', 'rejected');
                $order->save();
                if (!$order->has_status('cancelled')) $order->update_status('cancelled', 'Cofidis: a hitelkérelmet elutasították (piros).'.$d);
                return;
            case 'timeout':
                $order->update_meta_data('_vk_cofidis_status', 'timeout');
                $order->add_order_note('Cofidis: a vásárló nem adta be a hitelkérelmet 60 percen belül (vagy már volt kérelme a banknál). Érdemes felhívni.');
                break;
        }
        $order->save();
    }

    /* ---------- SOAP (kliens-tanúsítvánnyal) ---------- */

    private static function env(){ return self::live_mode() ? 'live' : 'demo'; }

    static function key_pem($env = null){ return (string)get_option('vk_cofidis_key_'.($env ?: self::env()), ''); }
    static function cert_pem($env = null){ return (string)get_option('vk_cofidis_cert_'.($env ?: self::env()), ''); }

    static function soap_ready(){
        return class_exists('\SoapClient') && self::key_pem() !== '' && self::cert_pem() !== '';
    }

    static function credentials_ready(){ return self::opt('tech_user') !== '' && self::opt('tech_password') !== ''; }

    /** SOAP hívás; a válasz (JSON szöveg) tömbként. */
    static function call($method, array $args){
        if (!self::soap_ready()) return new \WP_Error('cofidis', 'Nincs bank által aláírt tanúsítvány (WooCommerce → Cofidis tanúsítvány) vagy hiányzik a PHP SOAP bővítmény.');
        $pem = wp_tempnam('vk-cofidis');
        file_put_contents($pem, self::cert_pem()."\n".self::key_pem());
        @chmod($pem, 0600);
        try {
            $context = stream_context_create([ 'ssl' => [ 'local_cert' => $pem, 'verify_peer' => true ] ]);
            ini_set('soap.wsdl_cache_enabled', '1');
            $client = new \SoapClient(self::base().'e-direct-application/ecommerce/EcommerceService?wsdl', [
                'local_cert' => $pem, 'passphrase' => '', 'trace' => 0, 'exceptions' => true, 'connection_timeout' => 20,
                'stream_context' => $context, 'cache_wsdl' => WSDL_CACHE_DISK,
            ]);
            $raw = $client->__soapCall($method, $args);
            if (is_object($raw) && isset($raw->return)) $raw = $raw->return;
            $res = is_string($raw) ? json_decode($raw, true) : (array)$raw;
            return is_array($res) ? $res : new \WP_Error('cofidis', 'Értelmezhetetlen válasz a banktól.');
        } catch (\Throwable $e){
            return new \WP_Error('cofidis', $e->getMessage());
        } finally {
            @unlink($pem);
        }
    }

    /* ---------- Státusz és kiszállítás ---------- */

    function order_actions($actions, $order = null){
        if (!$order instanceof \WC_Order || $order->get_payment_method() !== Cofidis::ID || !self::configured()) return $actions;
        $actions['vk_cofidis_status'] = 'Cofidis: hitel státuszának lekérdezése';
        $actions['vk_cofidis_delivered'] = 'Cofidis: „áru kiszállítva” jelzése a banknak';
        return $actions;
    }

    private static function ref_args(\WC_Order $order){
        $dossier = (string)$order->get_meta('_vk_cofidis_dossier');
        return [ $dossier, $dossier === '' ? (string)$order->get_order_number() : '' ];
    }

    static function check_status(\WC_Order $order, $quiet = false){
        if (!self::credentials_ready()){ if (!$quiet) $order->add_order_note('Cofidis státusz: hiányzik a technikai felhasználó / jelszó (WooCommerce → Fizetés → Cofidis).'); return null; }
        [$dossier, $ref] = self::ref_args($order);
        $res = self::call('getDossierStatusV2', [ self::SOCIETY, self::shop_code(), self::opt('tech_user'), self::opt('tech_password'), $dossier, $ref ]);
        if (is_wp_error($res) || isset($res['success']) && !$res['success'] && !isset($res['status'])){
            if (!$quiet) $order->add_order_note('Cofidis státusz-lekérdezés sikertelen: '.(is_wp_error($res) ? $res->get_error_message() : (string)($res['errorMessage'] ?? '')));
            return null;
        }
        $status = (int)($res['status'] ?? -1);
        if (!empty($res['dossierId'])) $order->update_meta_data('_vk_cofidis_dossier', (string)$res['dossierId']);
        $old = (int)$order->get_meta('_vk_cofidis_bank_status');
        if ((string)$order->get_meta('_vk_cofidis_bank_status') === '' || $old !== $status || !$quiet){
            $order->update_meta_data('_vk_cofidis_bank_status', $status);
            $text = self::STATUSES[$status] ?? (string)($res['statusDescription'] ?? $status);
            $order->add_order_note('Cofidis státusz: '.$status.' – '.$text.(in_array($status, [ 6, 18 ], true) ? '. Az áru kiszállítható.' : '.'));
            if (in_array($status, [ 3, 4, 12, 14, 15 ], true) && $order->has_status([ 'on-hold', 'pending' ])) $order->update_status('cancelled', 'Cofidis: '.$text.'.');
        }
        $order->save();
        return $status;
    }

    function action_status($order){ self::check_status($order); }

    static function delivered(\WC_Order $order){
        if ($order->get_meta('_vk_cofidis_delivered')) return true;
        if (!self::credentials_ready()){ $order->add_order_note('Cofidis: a kiszállítás jelzéséhez hiányzik a technikai felhasználó / jelszó.'); return false; }
        [$dossier, $ref] = self::ref_args($order);
        $res = self::call('setDeliveryDoneStateV2', [ self::SOCIETY, self::shop_code(), self::opt('tech_user'), self::opt('tech_password'), $dossier, $ref ]);
        if (is_wp_error($res) || empty($res['success'])){
            $order->add_order_note('Cofidis: az „áru kiszállítva” jelzés nem ment át: '.(is_wp_error($res) ? $res->get_error_message() : (string)($res['errorMessage'] ?? '')).' (a Creditor felületen is jelezhető).');
            return false;
        }
        $order->update_meta_data('_vk_cofidis_delivered', time());
        $order->add_order_note('Cofidis: „áru kiszállítva” jelezve a banknak.');
        $order->save();
        return true;
    }

    function action_delivered($order){ self::delivered($order); }

    function auto_delivered($order_id){
        $order = wc_get_order($order_id);
        if ($order && $order->get_payment_method() === Cofidis::ID && self::soap_ready() && self::credentials_ready()) self::delivered($order);
    }

    function schedule(){
        if (self::soap_ready() && self::credentials_ready()){
            if (!wp_next_scheduled(self::POLL)) wp_schedule_event(time() + 300, 'hourly', self::POLL);
        } elseif (wp_next_scheduled(self::POLL)){
            wp_clear_scheduled_hook(self::POLL);
        }
    }

    /** Óránként: a függő Cofidis-rendelések státusza. */
    static function poll(){
        if (!self::soap_ready() || !self::credentials_ready()) return;
        $orders = wc_get_orders([ 'payment_method' => Cofidis::ID, 'status' => [ 'on-hold', 'pending', 'processing' ], 'limit' => 30, 'date_created' => '>'.(time() - 60 * DAY_IN_SECONDS) ]);
        foreach($orders as $order) self::check_status($order, true);
    }

    /* ---------- Tanúsítvány (CSR) ---------- */

    function menu(){
        add_submenu_page('woocommerce', 'Cofidis tanúsítvány', 'Cofidis tanúsítvány', 'manage_woocommerce', self::PAGE, [ $this, 'page' ]);
    }

    static function dn($env){
        return [
            'countryName' => 'HU', 'stateOrProvinceName' => 'Szabolcs-Szatmar-Bereg', 'localityName' => 'Kisvarda',
            'organizationName' => 'Vass Kisgep 2011 Kft.', 'organizationalUnitName' => $env === 'live' ? 'PROD' : 'IT',
            'commonName' => (string)parse_url(home_url(), PHP_URL_HOST), 'emailAddress' => 'info@vasskisgep.hu',
        ];
    }

    /** Új kulcs + CSR (ha még nincs; $force-szal újat). A CSR nyilvános, a kulcs az adatbázisban marad. */
    static function make_csr($env, $force = false){
        $env = $env === 'live' ? 'live' : 'demo';
        if (!$force && get_option('vk_cofidis_csr_'.$env)) return (string)get_option('vk_cofidis_csr_'.$env);
        if (!function_exists('openssl_pkey_new')) return new \WP_Error('cofidis', 'Hiányzik a PHP OpenSSL bővítmény.');
        $key = openssl_pkey_new([ 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256' ]);
        if (!$key) return new \WP_Error('cofidis', 'A kulcs nem készült el.');
        $csr = openssl_csr_new(self::dn($env), $key, [ 'digest_alg' => 'sha256' ]);
        if (!$csr) return new \WP_Error('cofidis', 'A CSR nem készült el.');
        openssl_pkey_export($key, $key_pem);
        openssl_csr_export($csr, $csr_pem);
        update_option('vk_cofidis_key_'.$env, $key_pem, false);
        update_option('vk_cofidis_csr_'.$env, $csr_pem, false);
        delete_option('vk_cofidis_cert_'.$env);
        return $csr_pem;
    }

    function action_csr(){
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('vk_cofidis_csr')) wp_die('', '', [ 'response' => 403 ]);
        $res = self::make_csr(sanitize_key($_POST['env'] ?? 'demo'), !empty($_POST['force']));
        wp_safe_redirect(add_query_arg([ 'page' => self::PAGE, 'msg' => is_wp_error($res) ? rawurlencode($res->get_error_message()) : 'csr' ], admin_url('admin.php')));
        exit;
    }

    function action_csr_download(){
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('vk_cofidis_csr_dl')) wp_die('', '', [ 'response' => 403 ]);
        $env = sanitize_key($_GET['env'] ?? 'demo') === 'live' ? 'live' : 'demo';
        nocache_headers();
        header('Content-Type: application/pkcs10');
        header('Content-Disposition: attachment; filename="vasskisgep-'.$env.'.csr"');
        echo (string)get_option('vk_cofidis_csr_'.$env);
        exit;
    }

    function action_cert(){
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('vk_cofidis_cert')) wp_die('', '', [ 'response' => 403 ]);
        $env = sanitize_key($_POST['env'] ?? 'demo') === 'live' ? 'live' : 'demo';
        $cert = trim((string)wp_unslash($_POST['cert'] ?? ''));
        $msg = 'cert';
        if ($cert !== '' && strpos($cert, 'BEGIN CERTIFICATE') === false) $cert = "-----BEGIN CERTIFICATE-----\n".chunk_split(preg_replace('/\s+/', '', $cert), 64, "\n")."-----END CERTIFICATE-----";
        if ($cert !== '' && function_exists('openssl_x509_check_private_key') && !@openssl_x509_check_private_key($cert, self::key_pem($env))) $msg = rawurlencode('A tanúsítvány nem ehhez a kulcshoz tartozik (vagy hibás). Ellenőrizd, melyik CSR-re készült.');
        else update_option('vk_cofidis_cert_'.$env, $cert, false);
        wp_safe_redirect(add_query_arg([ 'page' => self::PAGE, 'msg' => $msg ], admin_url('admin.php')));
        exit;
    }

    function page(){
        if (!current_user_can('manage_woocommerce')) return;
        $msg = (string)($_GET['msg'] ?? '');
        echo '<div class="wrap"><h1>Cofidis tanúsítvány (E-direkt webservice)</h1>';
        if ($msg === 'csr') echo '<div class="notice notice-success"><p>A kulcs és a CSR elkészült.</p></div>';
        elseif ($msg === 'cert') echo '<div class="notice notice-success"><p>A tanúsítvány mentve.</p></div>';
        elseif ($msg !== '') echo '<div class="notice notice-error"><p>'.esc_html(rawurldecode($msg)).'</p></div>';
        echo '<p>A bank a webservice-hívásokat (hitelkérelem indítása visszahívásokkal, státusz, „áru kiszállítva”) kliens-tanúsítvánnyal engedi. Lépések: 1) CSR készítése, 2) a CSR elküldése a banknak (Ecom_Helpdesk@cofidisbank.hu), 3) a banktól kapott tanúsítvány bemásolása ide. A privát kulcs a webshop adatbázisában marad, sehová nem kell küldeni.</p>';
        echo '<p>Mód most: <strong>'.(self::live_mode() ? 'éles' : 'demo').'</strong> · PHP SOAP: '.(class_exists('\SoapClient') ? 'van' : '<strong>nincs (a tárhelyen be kell kapcsolni)</strong>').' · Technikai felhasználó: '.(self::credentials_ready() ? 'megadva' : 'nincs megadva (WooCommerce → Beállítások → Fizetés → Cofidis)').'</p>';
        echo '<p>Tesztlink (élesítésig csak ezzel látszik a kalkulátor és a fizetési mód): <code>'.esc_html(self::test_url()).'</code></p>';
        foreach([ 'demo' => 'Demo (teszt)', 'live' => 'Éles' ] as $env => $label){
            $csr = (string)get_option('vk_cofidis_csr_'.$env);
            $cert = self::cert_pem($env);
            echo '<h2>'.esc_html($label).'</h2>';
            echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
            wp_nonce_field('vk_cofidis_csr');
            echo '<input type="hidden" name="action" value="vk_cofidis_csr"><input type="hidden" name="env" value="'.esc_attr($env).'">';
            if ($csr === '') echo '<p><button class="button button-primary">Kulcs és CSR készítése</button></p>';
            else echo '<p><label><input type="checkbox" name="force" value="1"> új kulcs (a régi tanúsítvány érvényét veszti)</label> <button class="button">CSR újrakészítése</button></p>';
            echo '</form>';
            if ($csr !== ''){
                echo '<p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=vk_cofidis_csr_download&env='.$env), 'vk_cofidis_csr_dl')).'">CSR letöltése</a></p>';
                echo '<textarea readonly rows="8" style="width:100%;max-width:760px;font-family:monospace">'.esc_textarea($csr).'</textarea>';
                echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
                wp_nonce_field('vk_cofidis_cert');
                echo '<input type="hidden" name="action" value="vk_cofidis_cert"><input type="hidden" name="env" value="'.esc_attr($env).'">';
                echo '<p><strong>A banktól kapott tanúsítvány</strong> '.($cert !== '' ? '(megvan; '.esc_html(self::cert_info($cert)).')' : '(még nincs)').'</p>';
                echo '<textarea name="cert" rows="6" style="width:100%;max-width:760px;font-family:monospace" placeholder="-----BEGIN CERTIFICATE-----">'.esc_textarea($cert).'</textarea>';
                echo '<p><button class="button button-primary">Tanúsítvány mentése</button></p></form>';
            }
        }
        echo '</div>';
    }

    static function cert_info($cert){
        $info = function_exists('openssl_x509_parse') ? @openssl_x509_parse($cert) : false;
        if (!$info) return 'nem olvasható';
        return 'érvényes: '.wp_date('Y. m. d.', (int)$info['validTo_time_t']).'-ig';
    }

    /** Állapot a kulcsos státusz-végponthoz (diag=cofidis). */
    static function diagnose($make_csr = ''){
        $out = [
            'configured' => self::configured(), 'mode' => self::live_mode() ? 'live' : 'demo', 'base' => self::base(),
            'shop_code' => self::shop_code(), 'barems' => self::barems(), 'limits' => self::limits(),
            'live_enabled' => self::opt('live_enabled', 'no') === 'yes', 'gateway_enabled' => self::opt('enabled', 'no') === 'yes',
            'soap_ext' => class_exists('\SoapClient'), 'openssl' => function_exists('openssl_pkey_new'),
            'csr' => [ 'demo' => (bool)get_option('vk_cofidis_csr_demo'), 'live' => (bool)get_option('vk_cofidis_csr_live') ],
            'cert' => [ 'demo' => self::cert_pem('demo') !== '', 'live' => self::cert_pem('live') !== '' ],
            'credentials' => self::credentials_ready(),
            'info_page' => ($p = get_page_by_path(Cofidis::INFO_PAGE)) ? get_permalink($p) : null,
            'test_url' => self::test_url(),
        ];
        if (in_array($make_csr, [ 'demo', 'live' ], true)){
            $csr = self::make_csr($make_csr);
            $out['csr_pem'] = is_wp_error($csr) ? $csr->get_error_message() : $csr;
        }
        return $out;
    }
}
