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
            $out[$gateway->id] = [ 'enabled' => ($settings['enabled'] ?? 'no'), 'title' => $gateway->get_title(), 'poskey_set' => !empty($settings['poskey']) || !empty($settings['pos_key']) ];
        }
        return $out ?: [ 'error' => 'nincs Barion fizetési mód (bővítmény?)' ];
    }
}
