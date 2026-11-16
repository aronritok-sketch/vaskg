<?php

namespace VasskisgepTheme;

/**
 * Barion elfogadóhely újranyitása (ügyfélkérés, 2026-11, a Barion levele alapján): a Barion a jóváhagyáshoz kéri
 *  – a „Barion Smart Payment” logósort a főoldalon (lábléc: Pages::hide_disabled_payments cseréli a régi
 *    kártyalogókat erre, és akkor is látszik, ha a bankkártyás fizetés még nincs bekapcsolva),
 *  – a Barion Pixel alapkódot minden oldalon (azonosító: Megjelenés → Beállítások → Barion, BP-0000000000-00 alakú;
 *    ha üres, a régi webshop adatbázisából előkerült azonosító),
 *  – az ÁSZF Barion-bekezdéseit (az ÁSZF a VirtualJog-ban szerkeszthető, nem itt).
 */
class Barion {
    const FOUND = 'vk_barion_pixel_found';

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_filter('iu_theme_settings', [$this, 'settings']);
        add_action('wp_head', [$this, 'pixel'], 1);
        // Fizetés eredménye egyértelműen (Dávid, 2026-11-16): jegyzet, jelölés a rendeléslistában és a rendelésnél,
        // tájékoztatás a vásárlónak a köszönőoldalon és a levélben.
        add_action('woocommerce_payment_complete', [$this, 'paid'], 20);
        add_action('woocommerce_order_status_changed', [$this, 'status_changed'], 20, 4);
        add_action('woocommerce_thankyou_barion', [$this, 'thankyou'], 5);
        add_action('woocommerce_email_before_order_table', [$this, 'email_line'], 5, 4);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'admin_badge']);
        add_action('manage_woocommerce_page_wc-orders_custom_column', [$this, 'list_badge'], 20, 2);
        add_action('manage_shop_order_posts_custom_column', [$this, 'list_badge'], 20, 2);
        add_action('admin_head', [$this, 'admin_css']);
        // Ha a Barion nem fogadja a fizetést (pl. hibás fiókadat), a bővítmény csak naplóz: a vásárló és a bolt is lássa az okát.
        add_action('woocommerce_barion_prepare_payment_called', [$this, 'prepare_failed'], 10, 2);
    }

    function prepare_failed($payment, $order){
        if (!$payment || !empty($payment->RequestSuccessful) || !$order instanceof \WC_Order) return;
        $msgs = [];
        foreach((array)($payment->Errors ?? []) as $e){
            $code = is_object($e) ? (string)($e->ErrorCode ?? '') : '';
            $text = is_object($e) ? trim((string)($e->Description ?? $e->Title ?? '')) : (string)$e;
            if ($text !== '' || $code !== '') $msgs[] = trim($code.($code !== '' && $text !== '' ? ': ' : '').$text);
        }
        $detail = $msgs ? implode(' | ', $msgs) : 'ismeretlen hiba';
        $order->add_order_note('✖ A Barion nem indította el a fizetést: '.$detail);
        update_option('vk_barion_last_error', [ 'time' => time(), 'order' => $order->get_id(), 'detail' => $detail ], false);
        if (function_exists('wc_add_notice')) wc_add_notice('A bankkártyás fizetés most nem indítható el. Kérjük, válassz másik fizetési módot, vagy próbáld újra később. (Barion: '.$detail.')', 'error');
    }

    const RESULT = '_vk_barion_result';

    static function is_barion($order){
        return $order instanceof \WC_Order && strpos((string)$order->get_payment_method(), 'barion') === 0;
    }

    function paid($order_id){
        $order = wc_get_order($order_id);
        if (!self::is_barion($order) || $order->get_meta(self::RESULT) === 'paid') return;
        $order->update_meta_data(self::RESULT, 'paid');
        $order->add_order_note('✔ Sikeres bankkártyás fizetés (Barion)'.($order->get_transaction_id() ? ', tranzakció: '.$order->get_transaction_id() : '').'. A rendelés kifizetve.');
        $order->save();
    }

    function status_changed($order_id, $from, $to, $order){
        if (!self::is_barion($order) || $order->get_meta(self::RESULT) === 'paid') return;
        if (!in_array($from, [ 'pending', 'on-hold' ], true)) return;
        if ($to === 'failed'){
            $order->update_meta_data(self::RESULT, 'failed');
            $order->add_order_note('✖ Sikertelen bankkártyás fizetés (Barion): a bank elutasította. A rendelés nincs kifizetve.');
            $order->save();
        } elseif ($to === 'cancelled'){
            $order->update_meta_data(self::RESULT, 'cancelled');
            $order->add_order_note('✖ A bankkártyás fizetés nem történt meg (Barion): a vásárló megszakította, vagy lejárt. A rendelés nincs kifizetve.');
            $order->save();
        }
    }

    private static function badge($order){
        if (!self::is_barion($order)) return '';
        $result = (string)$order->get_meta(self::RESULT);
        if ($result === '' && $order->is_paid()) $result = 'paid';
        $map = [
            'paid' => [ 'vk-barion-ok', 'Kifizetve – Barion' ],
            'failed' => [ 'vk-barion-bad', 'Sikertelen fizetés – Barion' ],
            'cancelled' => [ 'vk-barion-bad', 'Nem fizetett – Barion' ],
        ];
        if (!isset($map[$result])) return $order->has_status([ 'pending', 'on-hold' ]) ? '<span class="vk-barion-badge vk-barion-wait">Fizetésre vár – Barion</span>' : '';
        return '<span class="vk-barion-badge '.$map[$result][0].'">'.esc_html($map[$result][1]).'</span>';
    }

    function list_badge($column, $order){
        if ($column !== 'order_status') return;
        $order = $order instanceof \WC_Order ? $order : wc_get_order($order);
        $badge = self::badge($order);
        if ($badge !== '') echo '<br>'.$badge;
    }

    function admin_badge($order){
        $badge = self::badge($order);
        if ($badge !== '') echo '<p><strong>Fizetés:</strong><br>'.$badge.($order->get_transaction_id() ? '<br><small>Barion tranzakció: '.esc_html($order->get_transaction_id()).'</small>' : '').'</p>';
    }

    function admin_css(){
        echo '<style>.vk-barion-badge{display:inline-block;margin-top:4px;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;line-height:1.6;white-space:nowrap}.vk-barion-ok{background:#d1fae5;color:#065f46}.vk-barion-bad{background:#fee2e2;color:#991b1b}.vk-barion-wait{background:#fef3c7;color:#92400e}</style>';
    }

    function thankyou($order_id){
        $order = wc_get_order($order_id);
        if (!self::is_barion($order)) return;
        if ($order->is_paid()){
            echo '<div class="vk-notice vk-barion-notice vk-barion-notice-ok"><p><strong>Sikeres fizetés.</strong> A bankkártyás fizetést a Barion visszaigazolta, a rendelésed kifizetve. Köszönjük!</p></div>';
        } elseif ($order->has_status([ 'failed', 'cancelled' ])){
            echo '<div class="vk-notice vk-barion-notice vk-barion-notice-bad"><p><strong>A fizetés nem sikerült.</strong> A bankkártyás fizetés elutasításra került vagy megszakadt, ezért a rendelés nincs kifizetve. Próbáld újra, vagy válassz másik fizetési módot; kérdés esetén hívj minket.</p>'
                .($order->needs_payment() ? '<p><a class="iu-button iu-button-default" href="'.esc_url($order->get_checkout_payment_url()).'"><span class="iu-button-text"><span>Fizetés újra</span></span></a></p>' : '').'</div>';
        } else {
            echo '<div class="vk-notice vk-barion-notice"><p>A fizetés visszaigazolására várunk a Bariontól. Ha a fizetés sikeres volt, néhány percen belül e-mailben is visszaigazoljuk.</p></div>';
        }
    }

    function email_line($order, $sent_to_admin, $plain_text = false, $email = null){
        if (!self::is_barion($order) || !$order->is_paid()) return;
        $text = 'Fizetés: bankkártyával (Barion) – sikeres, a rendelés kifizetve.';
        echo $plain_text ? $text."\n\n" : '<p><strong>'.esc_html($text).'</strong></p>';
    }

    function settings($fields){
        $fields['barion'] = [
            'type' => 'tab',
            'title' => 'Barion',
            'fields' => [
                'barion_pixel_id' => [
                    'type' => 'text',
                    'label' => 'Barion Pixel azonosító (BP-xxxxxxxxxx-xx; a Barion fiókban az üzlet adatainál)'
                ]
            ]
        ];
        return $fields;
    }

    static function pixel_id(){
        // A Barion Pixel azonosító betűket is tartalmaz, kis- és nagybetű számít (pl. BP-8LFwQ1BBAd-EC).
        $id = trim(Reviews::setting('barion_pixel_id'));
        if ($id === '') $id = trim((string)get_option(self::FOUND, ''));
        return preg_match('/^BP-[A-Za-z0-9]{10}-[A-Za-z0-9]{2}$/', $id) ? $id : '';
    }

    /** Barion Pixel alapkód (a Barion leírása szerint). */
    function pixel(){
        if (is_admin()) return;
        // Ha a Barion bővítmény maga is kiteszi a Pixelt (azonosító megadva, követés bekapcsolva), nem duplázzuk.
        $plugin = (array)get_option('woocommerce_barion_settings', []);
        if (!empty($plugin['barion_pixel_id']) && ($plugin['tracking_enabled'] ?? 'yes') === 'yes' && class_exists('\WC_Gateway_Barion')) return;
        $id = self::pixel_id();
        if ($id === '') return;
        $js = wp_json_encode($id);
        echo "<script>window['bp']=window['bp']||function(){(window['bp'].q=window['bp'].q||[]).push(arguments);};window['bp'].l=1*new Date();(function(){var s=document.createElement('script'),f=document.getElementsByTagName('script')[0];s.async=true;s.src='https://pixel.barion.com/bp.js';f.parentNode.insertBefore(s,f);})();window['barion_pixel_id']=".$js.";bp('init','addBarionPixelId',window['barion_pixel_id']);</script>\n";
        echo '<noscript><img height="1" width="1" style="display:none" alt="Barion Pixel" src="https://pixel.barion.com/a.gif?ba_pixel_id='.esc_attr($id).'&amp;ev=contact&amp;noscript=1"></noscript>'."\n";
    }

    /** A logósor (Barion Smart Payment banner) a láblécbe. */
    static function banner(){
        $src = get_stylesheet_directory_uri().'/assets/barion-banner-light.svg';
        return '<figure class="iu-image vk-barion-banner"><img src="'.esc_url($src).'" width="567" height="108" alt="Biztonságos online fizetés a Barionnal – Visa, Mastercard, American Express, Apple Pay, Google Pay" loading="lazy" decoding="async"></figure>';
    }

    /** A régi (Joomla / VirtueMart) webshop adatbázisában keres Barion Pixel azonosítót (diag=barion). */
    static function find_old_pixel(){
        global $wpdb;
        $prefix = MigrationData::prefix();
        if ($prefix === '') return [ 'error' => 'nincs régi adatbázis' ];
        $found = [];
        $tables = [ 'extensions' => 'params', 'modules' => 'content', 'template_styles' => 'params', 'virtuemart_paymentmethods' => 'payment_params' ];
        foreach($tables as $table => $column){
            $name = $prefix.$table;
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $name)) !== $name) continue;
            $rows = $wpdb->get_col("SELECT `$column` FROM `$name` WHERE `$column` LIKE '%BP-%'");
            foreach((array)$rows as $value){
                if (preg_match_all('/BP-[A-Za-z0-9]{10}-[A-Za-z0-9]{2}/', (string)$value, $m)) $found = array_merge($found, $m[0]);
            }
        }
        $found = array_values(array_unique($found));
        if (count($found) === 1 && !get_option(self::FOUND)) update_option(self::FOUND, $found[0], false);
        return [ 'found' => $found, 'active' => self::pixel_id() ];
    }
    /**
     * A Barion fizetési mód állapota, és (enable=1 / 0) be- vagy kikapcsolása – a kulcsos státusz-végpontról
     * (diag=barion-gateway), hogy a Barion jóváhagyása után admin-belépés nélkül bekapcsolható legyen (Áron, 2026-11-06:
     * „ha jóváhagyták, bekapcsoljuk”). A POSKey és a többi beállítás a WooCommerce → Fizetés → Barion alatt marad.
     */
    static function gateway($enable = null){
        $out = [];
        if (!function_exists('WC') || !WC()->payment_gateways()) return [ 'error' => 'nincs WooCommerce' ];
        foreach(WC()->payment_gateways()->payment_gateways() as $gateway){
            if (strpos($gateway->id, 'barion') !== 0) continue;
            if ($enable !== null){
                $key = 'woocommerce_'.$gateway->id.'_settings';
                $settings = (array)get_option($key, []);
                $settings['enabled'] = $enable ? 'yes' : 'no';
                update_option($key, $settings);
            }
            $settings = (array)get_option('woocommerce_'.$gateway->id.'_settings', []);
            $out['last_error'] = get_option('vk_barion_last_error') ?: null;
            $out[$gateway->id] = [ 'enabled' => ($settings['enabled'] ?? 'no'), 'title' => $gateway->get_title(), 'poskey_set' => !empty($settings['poskey']) || !empty($settings['pos_key']),
                'environment' => $settings['environment'] ?? '', 'payee_set' => !empty($settings['payee']), 'pixel_set' => !empty($settings['barion_pixel_id']),
                'order_status' => $settings['order_status'] ?? '', 'rejected_status' => $settings['rejected_status'] ?? '', 'expired_status' => $settings['expired_status'] ?? '' ];
        }
        return $out ?: [ 'error' => 'nincs Barion fizetési mód (bővítmény?)' ];
    }
}
