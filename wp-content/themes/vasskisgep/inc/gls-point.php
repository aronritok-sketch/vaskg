<?php

namespace VasskisgepTheme;

/**
 * GLS CsomagPont / Csomagautomata szállítás (Dávid, 2026-11-16): a pénztárban a GLS hivatalos térképével
 * (map.gls-hungary.com, <gls-dpm-dialog>) választható átvételi pont; a díj súly szerint (saját díjtábla, 20 kg-ig),
 * az ingyenes szállítás szabályai ugyanazok, mint a házhozszállításnál. A választott pont a rendelésben
 * (`_vk_gls_point`), a levelekben és a köszönőoldalon látszik, a MyGLS-címke PSD szolgáltatással a pontra készül.
 */
class GlsPoint {
    const METHOD = 'vk_gls_point';
    const META = '_vk_gls_point';
    const SESSION = 'vk_gls_point';
    const DEFAULT_RATES = "10 | 2490\n20 | 3490";

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('woocommerce_shipping_init', function(){ require_once __DIR__.'/gls-point-method.php'; });
        add_filter('woocommerce_shipping_methods', function($methods){
            $methods[self::METHOD] = __NAMESPACE__.'\\GlsPointMethod';
            return $methods;
        });
        add_filter('woocommerce_cart_shipping_method_full_label', function($label, $rate){
            return ($rate->get_method_id() === self::METHOD && (float)$rate->get_cost() == 0) ? $label.': <strong>Ingyenes</strong>' : $label;
        }, 10, 2);
        add_action('woocommerce_review_order_after_shipping', [$this, 'checkout_row'], 20);
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 130);
        add_action('wp_footer', [$this, 'dialog']);
        add_action('wp_ajax_vk_gls_point', [$this, 'ajax_set']);
        add_action('wp_ajax_nopriv_vk_gls_point', [$this, 'ajax_set']);
        add_action('woocommerce_after_checkout_validation', [$this, 'validate'], 20, 2);
        add_action('woocommerce_checkout_create_order', [$this, 'save'], 20, 2);
        add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'admin_box']);
        add_action('woocommerce_email_after_order_table', [$this, 'email_box'], 5, 4);
        add_action('woocommerce_order_details_after_order_table', [$this, 'customer_box']);
        add_action('woocommerce_thankyou', [$this, 'customer_box'], 6);
        add_action('woocommerce_checkout_order_processed', [$this, 'note'], 20, 3);
    }

    /** A kosárban a GLS-csomagpont van-e kiválasztva szállítási módnak. */
    static function chosen(){
        if (!function_exists('WC') || !WC()->session) return false;
        foreach((array)WC()->session->get('chosen_shipping_methods', []) as $rate){
            if (strpos((string)$rate, self::METHOD) === 0) return true;
        }
        return false;
    }

    static function session_point(){
        $p = function_exists('WC') && WC()->session ? WC()->session->get(self::SESSION) : null;
        return is_array($p) && !empty($p['id']) ? $p : null;
    }

    static function clean(array $raw){
        $c = (array)($raw['contact'] ?? []);
        return [
            'id' => sanitize_text_field((string)($raw['id'] ?? '')),
            'name' => sanitize_text_field((string)($raw['name'] ?? '')),
            'postcode' => sanitize_text_field((string)($c['postalCode'] ?? $raw['postcode'] ?? '')),
            'city' => sanitize_text_field((string)($c['city'] ?? $raw['city'] ?? '')),
            'address' => sanitize_text_field((string)($c['address'] ?? $raw['address'] ?? '')),
            'type' => sanitize_key((string)($raw['type'] ?? '')),
        ];
    }

    static function text($p){
        if (!$p) return '';
        $kind = $p['type'] === 'parcel-locker' ? 'GLS Csomagautomata' : 'GLS CsomagPont';
        return $kind.': '.$p['name'].', '.trim($p['postcode'].' '.mb_convert_case(mb_strtolower($p['city']), MB_CASE_TITLE).', '.$p['address'], ', ');
    }

    function enqueue(){
        if (!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) return;
        wp_enqueue_script('vk-gls-dpm', 'https://map.gls-hungary.com/widget/gls-dpm.js', [], null, true);
        $js = <<<'JS'
(function($){
  function dlg(){ return document.getElementById('vk-gls-dpm'); }
  $(document).on('click', '.vk-gls-point-pick', function(e){
    e.preventDefault();
    var d = dlg();
    if (d && typeof d.showModal === 'function') d.showModal();
  });
  document.addEventListener('change', function(e){
    if (!e.target || e.target.id !== 'vk-gls-dpm' || !e.detail || !e.detail.id) return;
    var p = e.detail;
    try { if (typeof e.target.close === 'function') e.target.close(); } catch (x) {}
    $('.vk-gls-point-text').text((p.name || '') + ' – ' + ((p.contact && (p.contact.postalCode + ' ' + p.contact.city + ', ' + p.contact.address)) || ''));
    $.post(VK_GLS_POINT.ajax, { action: 'vk_gls_point', nonce: VK_GLS_POINT.nonce, point: JSON.stringify(p) }, function(){ $(document.body).trigger('update_checkout'); });
  }, true);
})(jQuery);
JS;
        wp_add_inline_script('vk-gls-dpm', 'var VK_GLS_POINT='.wp_json_encode([ 'ajax' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('vk_gls_point') ]).';'.$js);
    }

    function dialog(){
        if (!function_exists('is_checkout') || !is_checkout() || is_order_received_page()) return;
        echo '<gls-dpm-dialog country="hu" language="hu" id="vk-gls-dpm"></gls-dpm-dialog>';
    }

    function ajax_set(){
        check_ajax_referer('vk_gls_point', 'nonce');
        $raw = json_decode(wp_unslash((string)($_POST['point'] ?? '')), true);
        if (!is_array($raw) || empty($raw['id']) || !WC()->session) wp_send_json_error();
        WC()->session->set(self::SESSION, self::clean($raw));
        wp_send_json_success();
    }

    function checkout_row(){
        if (!self::chosen()) return;
        $p = self::session_point();
        echo '<tr class="vk-gls-point-row"><th>Átvételi pont</th><td>'
            .'<span class="vk-gls-point-text">'.esc_html($p ? self::text($p) : 'Még nincs kiválasztva').'</span><br>'
            .'<a href="#" class="button vk-gls-point-pick">'.($p ? 'Másik csomagpont' : 'Csomagpont kiválasztása').'</a>'
            .'</td></tr>';
    }

    function validate($data, $errors){
        if (self::chosen() && !self::session_point()) $errors->add('vk_gls_point', 'Válassz GLS csomagpontot vagy csomagautomatát a térképen („Csomagpont kiválasztása”).');
    }

    function save($order, $data){
        if (!self::chosen()) return;
        $p = self::session_point();
        if (!$p) return;
        $order->update_meta_data(self::META, $p);
    }

    /** A rendelés mentése után: jegyzet, és a választás törlése a munkamenetből. */
    function note($order_id, $posted = [], $order = null){
        $order = $order instanceof \WC_Order ? $order : wc_get_order($order_id);
        $p = self::order_point($order);
        if (!$p) return;
        $order->add_order_note('Átvételi pont: '.self::text($p).' (azonosító: '.$p['id'].').');
        if (WC()->session) WC()->session->set(self::SESSION, null);
    }

    static function order_point($order){
        $p = $order instanceof \WC_Order ? $order->get_meta(self::META) : null;
        return is_array($p) && !empty($p['id']) ? $p : null;
    }

    function admin_box($order){
        $p = self::order_point($order);
        if ($p) echo '<p><strong>Átvételi pont:</strong><br>'.esc_html(self::text($p)).'<br><small>Azonosító: '.esc_html($p['id']).'</small></p>';
    }

    function email_box($order, $sent_to_admin, $plain_text = false, $email = null){
        $p = self::order_point($order);
        if (!$p) return;
        echo $plain_text ? "\nÁtvételi pont: ".self::text($p)."\n" : '<p><strong>Átvételi pont:</strong> '.esc_html(self::text($p)).'</p>';
    }

    function customer_box($order){
        static $shown = false;
        $order = $order instanceof \WC_Order ? $order : wc_get_order($order);
        $p = self::order_point($order);
        if (!$p || $shown) return;
        $shown = true;
        echo '<p class="vk-gls-point-order"><strong>Átvételi pont:</strong> '.esc_html(self::text($p)).'</p>';
    }
}
