<?php

namespace VasskisgepTheme;

/**
 * GLS összekötés (MyGLS API, ügyfélkérés 2026-11, Dávid 1. pontja):
 *  – WooCommerce → GLS (MyGLS): felhasználó, jelszó (csak SHA-512 kivonata tárolódik), ügyfélszám, feladó címe,
 *    címkeformátum, értesítés, automatikus státusz; „Kapcsolat tesztelése”.
 *  – Rendelés: „GLS-címke készítése” (utánvétnél az összeggel), „Címke letöltése (PDF)”; a csomagszám magától a
 *    Csomagkövetés dobozba kerül (OrderStatuses). Több rendelés címkéje egyszerre: csoportművelet, egy PDF-ben.
 *  – Automatikus státusz (óránként): a GLS-nek átadott csomag → „Postázva” (a vásárló levelet kap a csomagszámmal),
 *    a kézbesített → „Teljesítve”; visszaforduló csomagról belső jegyzet.
 * A MyGLS-hívások a HelloProVision Dropship bővítmény bevált kódjából (plugins/helloprovision-dropship).
 */
class Gls {
    const OPTION = 'vk_gls';
    const PAGE = 'vk-gls';
    const CRON = 'vasskisgep_gls_track';
    const PRINTERS = [ 'A4_2x2' => 'A4, 4 címke egy lapon (2×2)', 'A4_4x1' => 'A4, 4 címke egymás alatt', 'Thermo' => 'Címkenyomtató (thermo, 10×15)' ];

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('admin_menu', [$this, 'menu'], 70);
        add_action('admin_post_vk_gls_save', [$this, 'save_settings']);
        add_action('admin_post_vk_gls_label', [$this, 'action_label']);
        add_action('admin_post_vk_gls_download', [$this, 'action_download']);
        add_action('add_meta_boxes', [$this, 'meta_box'], 31);
        add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'bulk_actions'], 40);
        add_filter('bulk_actions-edit-shop_order', [$this, 'bulk_actions'], 40);
        add_filter('handle_bulk_actions-woocommerce_page_wc-orders', [$this, 'handle_bulk'], 10, 3);
        add_filter('handle_bulk_actions-edit-shop_order', [$this, 'handle_bulk'], 10, 3);
        add_action('admin_notices', [$this, 'notices']);
        add_action('init', [$this, 'schedule']);
        add_action(self::CRON, [__CLASS__, 'track']);
    }

    /* ---------- Beállítások ---------- */

    static function settings(){
        return wp_parse_args((array)get_option(self::OPTION, []), [
            'username' => '', 'password_hash' => '', 'client_number' => '', 'test' => 0,
            'sender_name' => 'Vass Kisgép 2011 Kft.', 'sender_line' => 'Városmajor út 20.', 'sender_city' => 'Kisvárda', 'sender_postcode' => '4600',
            'sender_phone' => '+36302895977', 'sender_email' => '', 'printer' => 'A4_2x2', 'notify' => 1, 'auto_status' => 1,
        ]);
    }

    static function ready(){
        $s = self::settings();
        return $s['username'] !== '' && strlen($s['password_hash']) === 128 && ctype_digit((string)$s['client_number']);
    }

    function menu(){
        add_submenu_page('woocommerce', 'GLS (MyGLS)', 'GLS (MyGLS)', 'manage_woocommerce', self::PAGE, [$this, 'page']);
    }

    function page(){
        $s = self::settings();
        echo '<div class="wrap"><h1>GLS (MyGLS) összekötés</h1>';
        if (isset($_GET['vk_gls_msg'])) echo '<div class="notice notice-'.(($_GET['vk_gls_ok'] ?? '') === '1' ? 'success' : 'error').'"><p>'.esc_html(wp_unslash($_GET['vk_gls_msg'])).'</p></div>';
        echo '<p>A rendeléseknél ezzel készül a GLS-címke (a csomagszám magától a rendelésbe kerül), és a csomag állapota alapján magától változik a rendelés státusza: a GLS-nek átadva „Postázva”, kézbesítve „Teljesítve”.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'">';
        wp_nonce_field('vk_gls_save');
        echo '<input type="hidden" name="action" value="vk_gls_save"><table class="form-table" role="presentation">';
        $row = function($label, $html, $desc = ''){ echo '<tr><th scope="row">'.$label.'</th><td>'.$html.($desc ? '<p class="description">'.$desc.'</p>' : '').'</td></tr>'; };
        $row('MyGLS felhasználó', '<input class="regular-text" name="username" value="'.esc_attr($s['username']).'" autocomplete="off">', 'A MyGLS-be belépő e-mail cím.');
        $row('MyGLS jelszó', '<input class="regular-text" type="password" name="password" value="" autocomplete="new-password" placeholder="'.($s['password_hash'] ? '•••••••• (elmentve – csak csere esetén írd be)' : '').'">', 'A jelszót nem tároljuk, csak a kivonatát (így a GLS-nek elég).');
        $row('Ügyfélszám', '<input class="regular-text" name="client_number" value="'.esc_attr($s['client_number']).'" inputmode="numeric">', 'A GLS ügyfélszám (a MyGLS-ben a fiók adatainál, vagy a GLS szerződésen).');
        $row('Feladó neve', '<input class="regular-text" name="sender_name" value="'.esc_attr($s['sender_name']).'">');
        $row('Feladó címe', '<input class="regular-text" name="sender_line" value="'.esc_attr($s['sender_line']).'" placeholder="utca, házszám"><br><input name="sender_postcode" value="'.esc_attr($s['sender_postcode']).'" size="6" placeholder="irsz."> <input name="sender_city" value="'.esc_attr($s['sender_city']).'" placeholder="település">', 'Innen viszi el a futár a csomagot.');
        $row('Feladó telefon / e-mail', '<input name="sender_phone" value="'.esc_attr($s['sender_phone']).'" placeholder="+36…"> <input class="regular-text" name="sender_email" value="'.esc_attr($s['sender_email']).'" placeholder="e-mail">');
        $printer = '<select name="printer">';
        foreach(self::PRINTERS as $key => $label) $printer .= '<option value="'.esc_attr($key).'"'.selected($s['printer'], $key, false).'>'.esc_html($label).'</option>';
        $row('Címke formátuma', $printer.'</select>');
        $row('Értesítés', '<label><input type="checkbox" name="notify" value="1"'.checked($s['notify'], 1, false).'> A GLS e-mailben / SMS-ben értesítse a vásárlót a kézbesítésről</label>');
        $row('Automatikus státusz', '<label><input type="checkbox" name="auto_status" value="1"'.checked($s['auto_status'], 1, false).'> A csomag állapota alapján: átadva → Postázva, kézbesítve → Teljesítve</label>');
        $row('Teszt környezet', '<label><input type="checkbox" name="test" value="1"'.checked($s['test'], 1, false).'> MyGLS teszt rendszer (csak próbához)</label>', 'Éles MyGLS-fiókkal hagyd kikapcsolva: a teszt rendszerben az éles fiók nem létezik.');
        echo '</table><p><button class="button button-primary" name="do" value="save">Mentés</button> <button class="button" name="do" value="test">Mentés és kapcsolat tesztelése</button></p></form></div>';
    }

    function save_settings(){
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('vk_gls_save')) wp_die('', '', [ 'response' => 403 ]);
        $s = self::settings();
        foreach([ 'username', 'sender_name', 'sender_line', 'sender_city', 'sender_postcode', 'sender_phone', 'sender_email' ] as $key) $s[$key] = sanitize_text_field(wp_unslash($_POST[$key] ?? ''));
        $s['client_number'] = preg_replace('/\D/', '', (string)wp_unslash($_POST['client_number'] ?? ''));
        $s['printer'] = isset(self::PRINTERS[$_POST['printer'] ?? '']) ? $_POST['printer'] : 'A4_2x2';
        foreach([ 'notify', 'auto_status', 'test' ] as $key) $s[$key] = empty($_POST[$key]) ? 0 : 1;
        $password = (string)wp_unslash($_POST['password'] ?? '');
        if ($password !== '') $s['password_hash'] = hash('sha512', $password);
        update_option(self::OPTION, $s, false);
        $msg = 'Mentve.';
        $ok = 1;
        if (($_POST['do'] ?? '') === 'test'){
            $res = self::request('GetParcelList', [ 'PrintDateFrom' => '/Date('.((time() - 7 * DAY_IN_SECONDS) * 1000).')/', 'PrintDateTo' => '/Date('.(time() * 1000).')/' ]);
            if (is_wp_error($res)){ $msg = 'Mentve, de a kapcsolat nem jó: '.$res->get_error_message(); $ok = 0; }
            else $msg = 'Mentve, a MyGLS kapcsolat rendben ('.count((array)($res['PrintDataInfoList'] ?? [])).' csomag az elmúlt héten).';
        }
        wp_safe_redirect(add_query_arg([ 'page' => self::PAGE, 'vk_gls_msg' => rawurlencode($msg), 'vk_gls_ok' => $ok ], admin_url('admin.php')));
        exit;
    }

    /* ---------- MyGLS API ---------- */

    private static function password_bytes($hex){
        $bin = preg_match('/^[a-f0-9]{128}$/i', $hex) ? hex2bin($hex) : '';
        return $bin === '' ? [] : array_values(unpack('C*', $bin));
    }

    private static function error_key($method){
        $map = [ 'PrintLabels' => 'PrintLabelsErrorList', 'GetPrintedLabels' => 'GetPrintedLabelsErrorList', 'DeleteLabels' => 'DeleteLabelsErrorList', 'GetParcelStatuses' => 'GetParcelStatusErrors', 'GetParcelListStatuses' => 'GetParcelListStatusesErrors', 'GetParcelList' => 'GetParcelListErrors' ];
        return $map[$method] ?? 'Errors';
    }

    /** @return array|\WP_Error */
    static function request($method, array $body){
        $s = self::settings();
        if (!self::ready()) return new \WP_Error('vk_gls', 'A GLS nincs beállítva (WooCommerce → GLS (MyGLS): felhasználó, jelszó, ügyfélszám).');
        $req = array_merge([ 'Username' => $s['username'], 'Password' => self::password_bytes($s['password_hash']), 'WebshopEngine' => 'WooCommerce' ], $body);
        $url = 'https://api'.($s['test'] ? '.test' : '').'.mygls.hu/ParcelService.svc/json/'.$method;
        // Alap json_encode: a WCF a "\/Date(ms)\/" alakot várja.
        $res = wp_remote_post($url, [ 'timeout' => 45, 'headers' => [ 'Content-Type' => 'application/json' ], 'body' => (string)json_encode($req) ]);
        if (is_wp_error($res)) return new \WP_Error('vk_gls_transport', 'A MyGLS nem érhető el: '.$res->get_error_message());
        $code = (int)wp_remote_retrieve_response_code($res);
        $json = json_decode((string)wp_remote_retrieve_body($res), true);
        if ($code === 401) return new \WP_Error('vk_gls_auth', 'MyGLS: hibás felhasználó vagy jelszó.'.($s['test'] ? ' A „Teszt környezet” be van kapcsolva: éles MyGLS-fiókkal kapcsold ki (WooCommerce → GLS (MyGLS)).' : ''));
        if ($code !== 200 || !is_array($json)) return new \WP_Error('vk_gls', 'MyGLS: HTTP '.$code);
        if (isset($json['ErrorCode']) && !isset($json[self::error_key($method)])) return new \WP_Error('vk_gls_'.(int)$json['ErrorCode'], 'MyGLS ('.(int)$json['ErrorCode'].'): '.(string)($json['ErrorDescription'] ?? 'hibás kérés'));
        $errs = (array)($json[self::error_key($method)] ?? []);
        if ($errs){
            $e = (array)$errs[0];
            if ((int)($e['ErrorCode'] ?? 0) === -1) return new \WP_Error('vk_gls_auth', 'MyGLS: hibás felhasználó vagy jelszó.'.($s['test'] ? ' A „Teszt környezet” be van kapcsolva: éles MyGLS-fiókkal kapcsold ki (WooCommerce → GLS (MyGLS)).' : ''));
            return new \WP_Error('vk_gls_'.(int)($e['ErrorCode'] ?? 0), 'MyGLS ('.(int)($e['ErrorCode'] ?? 0).'): '.(string)($e['ErrorDescription'] ?? 'hiba'), [ 'json' => $json ]);
        }
        return $json;
    }

    private static function bytes($v){
        if (is_array($v)) return implode('', array_map(function($b){ return chr((int)$b & 0xFF); }, $v));
        $b = is_string($v) ? base64_decode($v, true) : false;
        return $b === false ? '' : $b;
    }

    static function phone($raw){
        $d = preg_replace('/[^\d+]/', '', (string)$raw);
        if ($d === '' || strpos($d, '+') === 0) return $d;
        if (strpos($d, '0036') === 0) return '+'.substr($d, 2);
        if (strpos($d, '06') === 0 && strlen($d) >= 10) return '+36'.substr($d, 2);
        if (strpos($d, '36') === 0 && strlen($d) >= 11) return '+'.$d;
        if (strlen($d) === 9 && preg_match('/^(20|30|31|50|70|1)/', $d)) return '+36'.$d;
        return $d;
    }

    private static function split_address($line){
        $line = trim((string)preg_replace('/\s+/u', ' ', (string)$line));
        $tok = $line === '' ? [] : explode(' ', $line);
        $type = '/^(utca|u\.?|út|útja|tér|tere|köz|körút|krt\.?|sor|sétány|dűlő|lakótelep|ltp\.?|fasor|park|híd|rakpart|liget|udvar|lejtő|lépcső|árok|telep|major|tanya|puszta)[,.]?$/iu';
        for ($i = 1, $n = count($tok); $i < $n; $i++){
            if (!preg_match('/^\d/u', $tok[$i])) continue;
            $later = false;
            for ($k = $i + 1; $k < $n; $k++) if (preg_match($type, $tok[$k])){ $later = true; break; }
            if (!$later) return [ rtrim(implode(' ', array_slice($tok, 0, $i)), ' ,'), implode(' ', array_slice($tok, $i)) ];
        }
        return [ $line, '' ];
    }

    private static function address(array $a){
        [ $street, $house ] = self::split_address(trim((string)($a['address_1'] ?? '')));
        $num = '';
        $info = trim((string)($a['address_2'] ?? ''));
        if (preg_match('/^(\d+)(.*)$/u', $house, $m) && (int)$m[1] > 0){
            $num = (string)(int)$m[1];
            $rest = trim(ltrim(trim($m[2]), './'));
            $info = trim($rest.($rest !== '' && $info !== '' ? ' ' : '').$info);
        } elseif ($house !== ''){
            $street = trim($street.' '.$house);
        }
        $out = [
            'Name' => mb_substr(trim((string)($a['name'] ?? '')), 0, 60),
            'Street' => $street !== '' ? $street : trim((string)($a['address_1'] ?? '')),
            'HouseNumber' => $num,
            'HouseNumberInfo' => mb_substr($info, 0, 40),
            'City' => trim((string)($a['city'] ?? '')),
            'ZipCode' => trim((string)($a['postcode'] ?? '')),
            'CountryIsoCode' => strtoupper((string)($a['country'] ?? 'HU') ?: 'HU'),
            'ContactName' => mb_substr(trim((string)($a['contact'] ?? ($a['name'] ?? ''))), 0, 60),
            'ContactPhone' => self::phone((string)($a['phone'] ?? '')),
            'ContactEmail' => trim((string)($a['email'] ?? '')),
        ];
        if ($out['HouseNumber'] === '') unset($out['HouseNumber']);
        if ($out['HouseNumberInfo'] === '') unset($out['HouseNumberInfo']);
        return $out;
    }

    /** A rendelés csomagja a PrintLabels-hez. */
    private static function parcel(\WC_Order $order, $count = 1){
        $s = self::settings();
        $pre = $order->has_shipping_address() ? 'shipping' : 'billing';
        $first = (string)$order->{'get_'.$pre.'_first_name'}();
        $last = (string)$order->{'get_'.$pre.'_last_name'}();
        $country = (string)$order->{'get_'.$pre.'_country'}() ?: 'HU';
        $name = trim($country === 'HU' ? $last.' '.$first : $first.' '.$last);
        $company = trim((string)$order->{'get_'.$pre.'_company'}());
        $phone = (string)((method_exists($order, 'get_shipping_phone') ? $order->get_shipping_phone() : '') ?: $order->get_billing_phone());
        $ship = [
            'name' => $company !== '' ? trim($name.' ('.$company.')') : $name, 'contact' => $name,
            'address_1' => (string)$order->{'get_'.$pre.'_address_1'}(), 'address_2' => (string)$order->{'get_'.$pre.'_address_2'}(),
            'city' => (string)$order->{'get_'.$pre.'_city'}(), 'postcode' => (string)$order->{'get_'.$pre.'_postcode'}(), 'country' => $country,
            'phone' => $phone, 'email' => (string)$order->get_billing_email(),
        ];
        $p = [
            'ClientNumber' => (int)$s['client_number'],
            'ClientReference' => mb_substr((string)$order->get_order_number(), 0, 40),
            'Count' => max(1, min(20, (int)$count)),
            'Content' => mb_substr('Rendelés '.$order->get_order_number(), 0, 60),
            'PickupAddress' => self::address([ 'name' => $s['sender_name'], 'address_1' => $s['sender_line'], 'city' => $s['sender_city'], 'postcode' => $s['sender_postcode'], 'country' => 'HU', 'phone' => $s['sender_phone'], 'email' => $s['sender_email'] ]),
            'DeliveryAddress' => self::address($ship),
            'ServiceList' => [],
        ];
        if ($order->get_payment_method() === 'cod'){
            $p['CODAmount'] = round((float)$order->get_total());
            $p['CODReference'] = mb_substr((string)$order->get_order_number(), 0, 40);
        }
        if (!empty($s['notify'])){
            $email = $ship['email'];
            $tel = self::phone($phone);
            if ($email !== ''){
                $p['ServiceList'][] = [ 'Code' => 'FDS', 'FDSParameter' => [ 'Value' => $email ] ];
                if (strpos($tel, '+') === 0) $p['ServiceList'][] = [ 'Code' => 'FSS', 'FSSParameter' => [ 'Value' => $tel ] ];
            } elseif (strpos($tel, '+') === 0){
                $p['ServiceList'][] = [ 'Code' => 'SM2', 'SM2Parameter' => [ 'Value' => $tel ] ];
            }
        }
        if (!$p['ServiceList']) unset($p['ServiceList']);
        return $p;
    }

    /** Személyes átvételnél nem kell GLS-címke. */
    static function needs_label(\WC_Order $order){
        foreach($order->get_shipping_methods() as $method){
            $id = (string)$method->get_method_id();
            if (in_array($id, [ 'local_pickup', 'pickup_location' ], true) || stripos($method->get_name(), 'átvétel') !== false) return false;
        }
        return (bool)$order->get_shipping_methods();
    }

    private static function label_dir(){
        $dir = trailingslashit(wp_upload_dir()['basedir']).'vk-gls-'.substr(wp_hash('vk-gls'), 0, 12);
        if (!is_dir($dir)){
            wp_mkdir_p($dir);
            file_put_contents($dir.'/.htaccess', "Require all denied\nDeny from all\n");
            file_put_contents($dir.'/index.php', "<?php // Silence is golden.\n");
        }
        return $dir;
    }

    /** A rendelés GLS-adatai: [ parcel_id, parcel, file ]. */
    static function info(\WC_Order $order){
        $info = $order->get_meta('_vk_gls');
        return is_array($info) ? $info : [];
    }

    /** A rendelés GLS-csomagjainak azonosítói (több dobozos küldeménynél több). */
    static function parcel_ids(array $info){
        if (!empty($info['parcel_ids'])) return array_map('intval', (array)$info['parcel_ids']);
        return !empty($info['parcel_id']) ? [ (int)$info['parcel_id'] ] : [];
    }

    /** @param int[] $parcel_ids @param string[] $parcels csomagszámok (az első a küldemény követési száma) */
    private static function store(\WC_Order $order, array $parcel_ids, array $parcels, $pdf = ''){
        $info = self::info($order);
        $info['parcel_ids'] = array_values(array_map('intval', $parcel_ids));
        $info['parcel_id'] = (int)($info['parcel_ids'][0] ?? 0);
        $info['parcels'] = array_values(array_map('strval', $parcels));
        $info['parcel'] = (string)($info['parcels'][0] ?? '');
        $parcel = $info['parcel'];
        if ($pdf !== ''){
            $file = 'gls-'.$order->get_id().'-'.wp_generate_password(8, false).'.pdf';
            file_put_contents(self::label_dir().'/'.$file, $pdf);
            if (!empty($info['file'])) @unlink(self::label_dir().'/'.$info['file']);
            $info['file'] = $file;
        }
        $order->update_meta_data('_vk_gls', $info);
        if ($parcel !== ''){
            $order->update_meta_data('_vk_tracking_carrier', 'gls');
            $order->update_meta_data('_vk_tracking_number', (string)$parcel);
        }
        $order->save();
    }

    /**
     * Címke egy vagy több rendeléshez. Ami már kapott csomagszámot, újranyomtat (nem hoz létre új csomagot).
     * @return array|\WP_Error [ 'pdf' => bináris, 'done' => [order_id => csomagszám], 'errors' => [order_id => hiba] ]
     */
    static function labels(array $orders, array $counts = []){
        $s = self::settings();
        $type = isset(self::PRINTERS[$s['printer']]) ? $s['printer'] : 'A4_2x2';
        $new = $ids = $done = $errors = [];
        foreach($orders as $order){
            $existing = self::parcel_ids(self::info($order));
            if ($existing){ $ids[$order->get_id()] = $existing; continue; }
            if (!self::needs_label($order)){ $errors[$order->get_id()] = 'személyes átvétel – nem kell GLS-címke'; continue; }
            $new[$order->get_id()] = $order;
        }
        if ($new){
            $list = [];
            foreach($new as $id => $order) $list[] = self::parcel($order, $counts[$id] ?? 1);
            $res = self::request('PrintLabels', [ 'ParcelList' => $list, 'TypeOfPrinter' => $type, 'PrintPosition' => 1, 'ShowPrintDialog' => false ]);
            if (is_wp_error($res)){
                foreach($new as $id => $order){ $errors[$id] = $res->get_error_message(); $order->add_order_note('GLS-címke nem készült el: '.$res->get_error_message()); }
            } else {
                $by_ref = $got = [];
                foreach($new as $id => $order) $by_ref[(string)$order->get_order_number()] = $order;
                foreach((array)($res['PrintLabelsInfoList'] ?? []) as $row){
                    $order = $by_ref[(string)($row['ClientReference'] ?? '')] ?? null;
                    if (!$order || empty($row['ParcelId'])) continue;
                    $got[$order->get_id()]['ids'][] = (int)$row['ParcelId'];
                    $got[$order->get_id()]['numbers'][] = (string)($row['ParcelNumber'] ?? '');
                }
                foreach($got as $order_id => $g){
                    $order = $new[$order_id];
                    self::store($order, $g['ids'], $g['numbers']);
                    $order->add_order_note('GLS-címke elkészült ('.count($g['ids']).' csomag; csomagszám: '.implode(', ', $g['numbers']).').');
                    $ids[$order_id] = $g['ids'];
                }
            }
        }
        if (!$ids) return $errors ? new \WP_Error('vk_gls', implode('; ', array_unique($errors))) : new \WP_Error('vk_gls', 'Nincs címkézhető rendelés.');
        $res = self::request('GetPrintedLabels', [ 'ParcelIdList' => array_values(array_merge(...array_values($ids))), 'PrintPosition' => 1, 'ShowPrintDialog' => false, 'TypeOfPrinter' => $type ]);
        if (is_wp_error($res)) return $res;
        $pdf = self::bytes($res['Labels'] ?? null);
        if (strpos($pdf, '%PDF') !== 0) return new \WP_Error('vk_gls', 'A MyGLS nem adott PDF-címkét.');
        foreach($ids as $order_id => $parcel_ids){
            $order = wc_get_order($order_id);
            if (!$order) continue;
            $info = self::info($order);
            if (count($ids) === 1) self::store($order, $parcel_ids, (array)($info['parcels'] ?? [ $info['parcel'] ?? '' ]), $pdf);
            $done[$order_id] = (string)($info['parcel'] ?? '');
        }
        return [ 'pdf' => $pdf, 'done' => $done, 'errors' => $errors ];
    }

    /** Kapcsolat-diagnosztika titok nélkül (a státusz-végpont diag=gls kérésére). */
    static function diagnose(){
        $s = self::settings();
        $out = [
            'ready' => self::ready(),
            'username_set' => $s['username'] !== '',
            'username_is_email' => (bool)is_email($s['username']),
            'password_hash_len' => strlen($s['password_hash']),
            'client_number_len' => strlen((string)$s['client_number']),
            'client_number_digits' => ctype_digit((string)$s['client_number']),
            'test' => (int)$s['test'],
            'sender' => $s['sender_postcode'].' '.$s['sender_city'],
        ];
        if (!self::ready()) return $out;
        $res = self::request('GetParcelList', [ 'PrintDateFrom' => '/Date('.((time() - 7 * DAY_IN_SECONDS) * 1000).')/', 'PrintDateTo' => '/Date('.(time() * 1000).')/' ]);
        $out['GetParcelList'] = is_wp_error($res) ? 'HIBA: '.$res->get_error_message() : 'OK ('.count((array)($res['PrintDataInfoList'] ?? [])).')';
        $orders = wc_get_orders([ 'limit' => 5, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' ]);
        $notes = [];
        foreach($orders as $order){
            foreach(wc_get_order_notes([ 'order_id' => $order->get_id(), 'limit' => 5 ]) as $note){
                if (stripos($note->content, 'GLS') !== false) $notes[] = '#'.$order->get_order_number().': '.$note->content;
            }
        }
        $out['notes'] = $notes;
        return $out;
    }

    private static function send_pdf($pdf, $name){
        nocache_headers();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="'.$name.'"');
        header('Content-Length: '.strlen($pdf));
        echo $pdf;
        exit;
    }

    /* ---------- Admin: rendelés ---------- */

    function meta_box(){
        $screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';
        add_meta_box('vk-gls', 'GLS-címke', [$this, 'meta_box_html'], $screen, 'side', 'high');
    }

    function meta_box_html($post_or_order){
        $order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order($post_or_order instanceof \WP_Post ? $post_or_order->ID : $post_or_order);
        if (!$order) return;
        if (!self::ready()){
            echo '<p>A GLS még nincs beállítva: <a href="'.esc_url(admin_url('admin.php?page='.self::PAGE)).'">WooCommerce → GLS (MyGLS)</a>.</p>';
            return;
        }
        $info = self::info($order);
        $label_url = wp_nonce_url(admin_url('admin-post.php?action=vk_gls_label&order='.$order->get_id()), 'vk_gls_label_'.$order->get_id());
        if (!empty($info['parcel_id'])){
            $all = (array)($info['parcels'] ?? [ $info['parcel'] ?? '' ]);
            echo '<p>'.(count($all) > 1 ? count($all).' csomag, csomagszámok' : 'Csomagszám').': <strong>'.esc_html(implode(', ', $all)).'</strong></p>';
            echo '<p><a class="button" target="_blank" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=vk_gls_download&order='.$order->get_id()), 'vk_gls_dl_'.$order->get_id())).'">Címke letöltése (PDF)</a></p>';
            $status = $order->get_meta('_vk_gls_status');
            if (is_array($status) && !empty($status['text'])) echo '<p class="description">GLS-állapot: '.esc_html($status['text']).'</p>';
        } elseif (!self::needs_label($order)){
            echo '<p class="description">Személyes átvétel – nem kell GLS-címke.</p>';
        } else {
            echo '<p><label for="vk-gls-count">Dobozok száma:</label> <input type="number" id="vk-gls-count" min="1" max="20" value="1" style="width:4.5em"></p>';
            echo '<p><a class="button button-primary" target="_blank" href="'.esc_url($label_url).'" onclick="var n=parseInt(document.getElementById(\'vk-gls-count\').value,10)||1;this.href=this.href.replace(/&count=\\d+/,\'\')+\'&count=\'+Math.max(1,Math.min(20,n));">GLS-címke készítése</a></p>';
            echo '<p class="description">'.($order->get_payment_method() === 'cod' ? 'Utánvét: '.wp_strip_all_tags(wc_price($order->get_total())).'. ' : '').'A csomagszám magától a rendelésbe kerül.</p>';
        }
    }

    function action_label(){
        $id = (int)($_GET['order'] ?? 0);
        if (!current_user_can('edit_shop_orders') || !check_admin_referer('vk_gls_label_'.$id)) wp_die('', '', [ 'response' => 403 ]);
        $order = wc_get_order($id);
        if (!$order) wp_die('Nincs ilyen rendelés.');
        $res = self::labels([ $order ], [ $order->get_id() => max(1, min(20, (int)($_GET['count'] ?? 1))) ]);
        if (is_wp_error($res)) wp_die('<p>'.esc_html($res->get_error_message()).'</p><p><a href="javascript:history.back()">Vissza</a></p>', 'GLS-címke', [ 'response' => 200 ]);
        self::send_pdf($res['pdf'], 'gls-'.$order->get_order_number().'.pdf');
    }

    function action_download(){
        $id = (int)($_GET['order'] ?? 0);
        if (!current_user_can('edit_shop_orders') || !check_admin_referer('vk_gls_dl_'.$id)) wp_die('', '', [ 'response' => 403 ]);
        $order = wc_get_order($id);
        $info = $order ? self::info($order) : [];
        $file = !empty($info['file']) ? self::label_dir().'/'.basename($info['file']) : '';
        if ($file && is_file($file)) self::send_pdf((string)file_get_contents($file), 'gls-'.$order->get_order_number().'.pdf');
        $res = $order ? self::labels([ $order ]) : new \WP_Error('vk_gls', 'Nincs ilyen rendelés.');
        if (is_wp_error($res)) wp_die(esc_html($res->get_error_message()));
        self::send_pdf($res['pdf'], 'gls-'.$order->get_order_number().'.pdf');
    }

    function bulk_actions($actions){
        $actions['vk_gls_labels'] = 'GLS-címke készítése / nyomtatása';
        return $actions;
    }

    function handle_bulk($redirect, $action, $ids){
        if ($action !== 'vk_gls_labels' || !current_user_can('edit_shop_orders')) return $redirect;
        $orders = array_filter(array_map('wc_get_order', array_map('intval', (array)$ids)));
        $res = self::labels($orders);
        if (is_wp_error($res)){
            set_transient('vk_gls_notice_'.get_current_user_id(), 'GLS: '.$res->get_error_message(), 120);
            return $redirect;
        }
        if ($res['errors']) set_transient('vk_gls_notice_'.get_current_user_id(), 'GLS: '.count($res['done']).' címke kész; nem készült: '.implode('; ', array_map(function($id, $err){ return '#'.$id.' ('.$err.')'; }, array_keys($res['errors']), $res['errors'])), 300);
        self::send_pdf($res['pdf'], 'gls-cimkek-'.gmdate('Ymd-His').'.pdf');
    }

    function notices(){
        $msg = get_transient('vk_gls_notice_'.get_current_user_id());
        if (!$msg) return;
        delete_transient('vk_gls_notice_'.get_current_user_id());
        echo '<div class="notice notice-warning is-dismissible"><p>'.esc_html($msg).'</p></div>';
    }

    /* ---------- Automatikus státusz ---------- */

    function schedule(){
        if (!wp_next_scheduled(self::CRON)) wp_schedule_event(time() + 300, 'hourly', self::CRON);
    }

    static function status_kind($code){
        $code = (int)$code;
        if (in_array($code, [ 5, 58, 59, 92, 93 ], true)) return 'delivered';
        if (in_array($code, [ 23, 40 ], true)) return 'returned';
        if (in_array($code, [ 8, 54, 55, 56, 97 ], true)) return 'waiting';
        if ($code === 57) return 'expired';
        if (in_array($code, [ 4, 32 ], true)) return 'out';
        if (in_array($code, [ 51, 52, 83, 84 ], true)) return 'label';
        if (in_array($code, [ 1, 2, 3, 6, 7, 9, 10, 22, 24, 26, 27, 37, 41, 46, 47, 53, 80, 86, 99 ], true)) return 'transit';
        return 'other';
    }

    /** Óránként: a GLS-csomaggal rendelkező, még nem teljesített rendelések állapota. */
    static function track(){
        if (!self::ready()) return;
        $s = self::settings();
        $orders = wc_get_orders([ 'limit' => 100, 'status' => [ 'processing', 'on-hold', OrderStatuses::SHIPPED, OrderStatuses::PARTIAL ], 'meta_key' => '_vk_gls', 'meta_compare' => 'EXISTS', 'date_created' => '>'.(time() - 45 * DAY_IN_SECONDS) ]);
        $by = [];
        foreach($orders as $order){
            $parcel = (string)(self::info($order)['parcel'] ?? '');
            if (ctype_digit($parcel)) $by[$parcel] = $order;
        }
        if (!$by) return;
        $res = self::request('GetParcelListStatuses', [ 'ParcelNumberList' => array_map('intval', array_keys($by)), 'LanguageIsoCode' => 'HU' ]);
        if (is_wp_error($res)) return;
        foreach((array)($res['ParcelList'] ?? []) as $p){
            $order = $by[(string)($p['ParcelNumber'] ?? '')] ?? null;
            if (!$order) continue;
            $latest = null;
            foreach((array)($p['ParcelStatusList'] ?? []) as $st){
                $t = preg_match('/Date\((\d+)/', (string)($st['StatusDate'] ?? ''), $m) ? (int)$m[1] : 0;
                if (!$latest || $t >= $latest['t']) $latest = [ 't' => $t, 'code' => (int)($st['StatusCode'] ?? 0), 'text' => (string)($st['StatusDescription'] ?? '') ];
            }
            if (!$latest) continue;
            $kind = self::status_kind($latest['code']);
            $prev = $order->get_meta('_vk_gls_status');
            $order->update_meta_data('_vk_gls_status', [ 'code' => $latest['code'], 'text' => $latest['text'], 'kind' => $kind ]);
            $order->save();
            if (!$s['auto_status']) continue;
            if ($kind === 'delivered'){
                $order->update_status('completed', 'GLS: kézbesítve ('.$latest['text'].').');
            } elseif (in_array($kind, [ 'transit', 'out', 'waiting' ], true) && in_array($order->get_status(), [ 'processing', 'on-hold' ], true)){
                $order->update_status(OrderStatuses::SHIPPED, 'GLS: a csomag úton van ('.$latest['text'].').');
            } elseif (in_array($kind, [ 'returned', 'expired' ], true) && (!is_array($prev) || ($prev['kind'] ?? '') !== $kind)){
                $order->add_order_note('GLS: '.($kind === 'returned' ? 'a csomag visszafordult a feladóhoz' : 'lejárt a csomagponti tárolás').' ('.$latest['text'].'). Vedd fel a kapcsolatot a vásárlóval.');
            }
        }
    }
}
