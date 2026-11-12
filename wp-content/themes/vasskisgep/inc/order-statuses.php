<?php

namespace VasskisgepTheme;

/**
 * Rendelésstátuszok és csomagkövetés (ügyfélkérés, 2026-11, Dávid 14–15. pontja):
 *  – „Postázva” státusz: a csomag átadva a futárnak; a vásárló levelet kap a csomagszámmal (GLS: követési link).
 *  – Csomagkövetés a rendelésnél: futár (GLS / Kumi / egyéb) + csomagszám; látszik a levelekben és a vásárló fiókjában.
 *  – „Részben teljesítve” státusz: a rendelés tételeinél bejelölhető, mi nincs még teljesítve („később érkezik”);
 *    a vásárló levelet kap a még hiányzó tételekkel.
 * A státuszlevelek WooCommerce levelek (WooCommerce → Beállítások → E-mailek alatt ki- / bekapcsolhatók), így a
 * rendeléslista „Értesítés” pipája ezekre is vonatkozik.
 */
class OrderStatuses {
    const SHIPPED = 'shipped';
    const PARTIAL = 'partial';
    /** „Új rendelés”: a webshopból jött, még senki nem nyúlt hozzá (Dávid, 2026-11-09). */
    const NEW_ORDER = 'new-order';
    /** „Beszállítótól rendelve” (Dávid, 2026-11-12): a termék a beszállítótól rendelve, arra várunk. Fizetettnek számít. */
    const SUPPLIER = 'supplier-ordered';
    const CARRIERS = [
        'gls' => [ 'GLS', 'https://gls-group.com/HU/hu/csomagkovetes?match=%s' ],
        'kumi' => [ 'Kumi', '' ],
        'other' => [ 'Egyéb', '' ],
    ];

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('init', [$this, 'register']);
        add_filter('wc_order_statuses', [$this, 'statuses']);
        add_filter('woocommerce_order_is_paid_statuses', [$this, 'paid']);
        add_filter('woocommerce_reports_order_statuses', [$this, 'paid']);
        add_filter('bulk_actions-woocommerce_page_wc-orders', [$this, 'bulk_actions'], 30);
        add_filter('bulk_actions-edit-shop_order', [$this, 'bulk_actions'], 30);
        add_filter('woocommerce_email_classes', [$this, 'emails']);
        add_filter('woocommerce_email_actions', [$this, 'email_actions']);
        // A webshopból érkező rendelés „Feldolgozás alatt” helyett „Új rendelés” (a levelek és a készletlevonás előtte
        // a szokásos módon lefutnak; a váltás a kérés végén történik).
        add_action('woocommerce_order_status_changed', [$this, 'queue_new'], 99, 4);
        add_action('shutdown', [$this, 'apply_new'], 5);

        // Csomagkövetés és teljesítetlen tételek az admin rendelésoldalon.
        add_action('add_meta_boxes', [$this, 'meta_box'], 30);
        add_action('woocommerce_process_shop_order_meta', [$this, 'save'], 30);
        add_action('woocommerce_after_order_itemmeta', [$this, 'item_pending_field'], 10, 3);
        add_filter('woocommerce_hidden_order_itemmeta', function($keys){ $keys[] = '_vk_pending'; return $keys; });

        // Megjelenítés: levelek, vásárlói fiók.
        add_action('woocommerce_email_before_order_table', [$this, 'email_tracking'], 20, 4);
        add_action('woocommerce_order_item_meta_end', [$this, 'item_pending_note'], 10, 3);
        add_action('woocommerce_order_details_before_order_table', [$this, 'account_tracking']);
    }

    private static $new_queue = [];

    function queue_new($order_id, $from, $to, $order = null){
        if ($from !== 'pending' || $to !== 'processing') return;
        $order = $order instanceof \WC_Order ? $order : wc_get_order($order_id);
        if (!$order || !in_array($order->get_created_via(), [ 'checkout', 'store-api' ], true)) return;
        self::$new_queue[(int)$order_id] = true;
    }

    function apply_new(){
        foreach(array_keys(self::$new_queue) as $order_id){
            $order = wc_get_order($order_id);
            if ($order && $order->get_status() === 'processing') $order->update_status(self::NEW_ORDER, 'Új rendelés – még senki nem kezelte.');
        }
        self::$new_queue = [];
    }

    function register(){
        register_post_status('wc-'.self::NEW_ORDER, [
            'label' => 'Új rendelés', 'public' => false, 'exclude_from_search' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Új rendelés <span class="count">(%s)</span>', 'Új rendelés <span class="count">(%s)</span>'),
        ]);
        register_post_status('wc-'.self::SUPPLIER, [
            'label' => 'Beszállítótól rendelve', 'public' => false, 'exclude_from_search' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Beszállítótól rendelve <span class="count">(%s)</span>', 'Beszállítótól rendelve <span class="count">(%s)</span>'),
        ]);
        register_post_status('wc-'.self::SHIPPED, [
            'label' => 'Postázva', 'public' => false, 'exclude_from_search' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Postázva <span class="count">(%s)</span>', 'Postázva <span class="count">(%s)</span>'),
        ]);
        register_post_status('wc-'.self::PARTIAL, [
            'label' => 'Részben teljesítve', 'public' => false, 'exclude_from_search' => false, 'show_in_admin_all_list' => true, 'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Részben teljesítve <span class="count">(%s)</span>', 'Részben teljesítve <span class="count">(%s)</span>'),
        ]);
    }

    /** A „Feldolgozás alatt” után, a „Teljesítve” elé. */
    function statuses($statuses){
        $out = [];
        foreach($statuses as $key => $label){
            if ($key === 'wc-processing') $out['wc-'.self::NEW_ORDER] = 'Új rendelés';
            if ($key === 'wc-completed'){
                $out['wc-'.self::PARTIAL] = 'Részben teljesítve';
                $out['wc-'.self::SHIPPED] = 'Postázva';
            }
            $out[$key] = $label;
            if ($key === 'wc-processing') $out['wc-'.self::SUPPLIER] = 'Beszállítótól rendelve';
        }
        if (!isset($out['wc-'.self::SUPPLIER])) $out['wc-'.self::SUPPLIER] = 'Beszállítótól rendelve';
        if (!isset($out['wc-'.self::NEW_ORDER])) $out = [ 'wc-'.self::NEW_ORDER => 'Új rendelés' ] + $out;
        if (!isset($out['wc-'.self::SHIPPED])){
            $out['wc-'.self::PARTIAL] = 'Részben teljesítve';
            $out['wc-'.self::SHIPPED] = 'Postázva';
        }
        return $out;
    }

    function paid($statuses){
        return array_values(array_unique(array_merge((array)$statuses, [ self::NEW_ORDER, self::SUPPLIER, self::SHIPPED, self::PARTIAL ])));
    }

    function bulk_actions($actions){
        $actions['mark_'.self::SUPPLIER] = 'Állapot módosítása: beszállítótól rendelve';
        $actions['mark_'.self::SHIPPED] = 'Állapot módosítása: postázva';
        $actions['mark_'.self::PARTIAL] = 'Állapot módosítása: részben teljesítve';
        return $actions;
    }

    function emails($emails){
        require_once __DIR__.'/order-status-emails.php';
        $emails['VK_Email_Shipped'] = new VK_Email_Shipped();
        $emails['VK_Email_Partial'] = new VK_Email_Partial();
        $emails['VK_Email_Survey'] = new VK_Email_Survey();
        return $emails;
    }

    function email_actions($actions){
        foreach([ 'woocommerce_order_status_'.self::SHIPPED, 'woocommerce_order_status_'.self::PARTIAL ] as $hook){
            if (!in_array($hook, $actions, true)) $actions[] = $hook;
        }
        return $actions;
    }

    /* ---------- Csomagkövetés ---------- */

    /** [ 'carrier' => kulcs, 'name' => „GLS”, 'number' => …, 'url' => követési link vagy '' ], vagy null. */
    /** A csomagszám: a beírt, különben a GLS-címke csomagszáma (Dávid, 2026-11-12: címke után nem látszott). */
    static function tracking_number($order){
        $number = trim((string)$order->get_meta('_vk_tracking_number'));
        if ($number === ''){
            $gls = $order->get_meta('_vk_gls');
            if (is_array($gls) && !empty($gls['parcel'])) $number = (string)$gls['parcel'];
        }
        return $number;
    }

    static function tracking($order){
        if (!$order instanceof \WC_Order) return null;
        $number = self::tracking_number($order);
        if ($number === '') return null;
        $carrier = (string)$order->get_meta('_vk_tracking_carrier') ?: 'gls';
        $def = self::CARRIERS[$carrier] ?? self::CARRIERS['other'];
        return [ 'carrier' => $carrier, 'name' => $def[0], 'number' => $number, 'url' => $def[1] !== '' ? sprintf($def[1], rawurlencode($number)) : '' ];
    }

    /** Alapértelmezett futár a szállítási módból: személyes átvételnél nincs, „Kumi” névnél Kumi, különben GLS. */
    static function default_carrier($order){
        foreach($order->get_shipping_methods() as $method){
            $name = mb_strtolower($method->get_name().' '.$method->get_method_id());
            if (strpos($name, 'kumi') !== false) return 'kumi';
        }
        return 'gls';
    }

    function meta_box(){
        $screen = function_exists('wc_get_page_screen_id') ? wc_get_page_screen_id('shop-order') : 'shop_order';
        add_meta_box('vk-tracking', 'Csomagkövetés és teljesítés', [$this, 'meta_box_html'], $screen, 'side', 'high');
    }

    function meta_box_html($post_or_order){
        $order = $post_or_order instanceof \WC_Order ? $post_or_order : wc_get_order($post_or_order instanceof \WP_Post ? $post_or_order->ID : $post_or_order);
        if (!$order) return;
        $carrier = (string)$order->get_meta('_vk_tracking_carrier') ?: self::default_carrier($order);
        wp_nonce_field('vk_tracking', 'vk_tracking_nonce');
        echo '<p><label for="vk_tracking_carrier"><strong>Futár</strong></label><br><select id="vk_tracking_carrier" name="vk_tracking_carrier" style="width:100%">';
        foreach(self::CARRIERS as $key => $def) echo '<option value="'.esc_attr($key).'"'.selected($key, $carrier, false).'>'.esc_html($def[0]).'</option>';
        echo '</select></p>';
        echo '<p><label for="vk_tracking_number"><strong>Csomagszám (tracking)</strong></label><br><input type="text" id="vk_tracking_number" name="vk_tracking_number" value="'.esc_attr(self::tracking_number($order)).'" style="width:100%" placeholder="pl. 12345678901"></p>';
        echo '<p class="description">A „Postázva” státusz levelében és a vásárló fiókjában megjelenik (GLS-nél követési linkkel).</p>';
        $survey = $order->get_meta('_vk_survey');
        echo '<p><label><input type="checkbox" name="vk_survey" value="1"'.checked($survey === 'yes' || $survey === 'sent', true, false).'> <strong>Elégedettségi kérdőív</strong> küldése teljesítéskor</label>'.($survey === 'sent' ? '<br><span class="description">Elküldve.</span>' : '').'</p>';
        echo '<p class="description">„Részben teljesítve”: a tételeknél jelöld be, melyik nincs még teljesítve.</p>';
    }

    function save($order_id){
        if (empty($_POST['vk_tracking_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vk_tracking_nonce'])), 'vk_tracking')) return;
        $order = wc_get_order($order_id);
        if (!$order) return;
        $carrier = sanitize_key(wp_unslash($_POST['vk_tracking_carrier'] ?? 'gls'));
        $order->update_meta_data('_vk_tracking_carrier', isset(self::CARRIERS[$carrier]) ? $carrier : 'gls');
        // Üres mezővel ne vesszen el a közben (pl. GLS-címkével) beírt csomagszám: az oldal még a címke előtt töltődött be.
        $number = sanitize_text_field(wp_unslash($_POST['vk_tracking_number'] ?? ''));
        if ($number === '') $number = self::tracking_number($order);
        $order->update_meta_data('_vk_tracking_number', $number);
        if ($order->get_meta('_vk_survey') !== 'sent') $order->update_meta_data('_vk_survey', !empty($_POST['vk_survey']) ? 'yes' : '');
        // Teljesítetlen tételek.
        $pending = array_map('intval', array_keys((array)($_POST['vk_item_pending'] ?? [])));
        foreach($order->get_items() as $item_id => $item){
            $item->update_meta_data('_vk_pending', in_array((int)$item_id, $pending, true) ? 'yes' : '');
            $item->save();
        }
        $order->save();
    }

    function item_pending_field($item_id, $item, $product){
        if (!is_admin() || !$item instanceof \WC_Order_Item_Product) return;
        echo '<label class="vk-item-pending" style="display:inline-flex;gap:.35rem;align-items:center;margin-top:.35rem;color:#b45309"><input type="checkbox" name="vk_item_pending['.(int)$item_id.']" value="1"'.checked($item->get_meta('_vk_pending'), 'yes', false).'> Még nincs teljesítve</label>';
    }

    static function pending_items($order){
        return array_filter($order->get_items(), function($item){ return $item->get_meta('_vk_pending') === 'yes'; });
    }

    function item_pending_note($item_id, $item, $order){
        if ($item instanceof \WC_Order_Item_Product && $item->get_meta('_vk_pending') === 'yes'){
            echo '<br><small style="color:#b45309">Később érkezik</small>';
        }
    }

    static function tracking_html($order, $plain = false){
        $t = self::tracking($order);
        if (!$t) return '';
        if ($plain) return 'Csomagkövetés ('.$t['name'].'): '.$t['number'].($t['url'] ? ' – '.$t['url'] : '')."\n\n";
        return '<p style="margin:0 0 16px;padding:12px 14px;border:1px solid #e5e7eb;border-radius:6px;background:#f6f7f9">'
            .'<strong>Csomagkövetés ('.esc_html($t['name']).'):</strong> '
            .($t['url'] ? '<a href="'.esc_url($t['url']).'" target="_blank">'.esc_html($t['number']).'</a>' : esc_html($t['number']))
            .'</p>';
    }

    /** A csomagszám a vásárlói levelekben (postázva, teljesítve, részben teljesítve). */
    function email_tracking($order, $sent_to_admin = false, $plain_text = false, $email = null){
        if ($sent_to_admin || !$order instanceof \WC_Order) return;
        if (!in_array($order->get_status(), [ self::SHIPPED, self::PARTIAL, 'completed' ], true)) return;
        echo self::tracking_html($order, $plain_text);
    }

    function account_tracking($order){
        echo self::tracking_html($order);
    }
}
