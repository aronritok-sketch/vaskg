<?php

namespace VasskisgepTheme;

/**
 * Sütikezelő + követés (Áron döntése, 2026-11-16: „Csináld meg mindet”).
 *
 * – Sütisáv: „Elfogadom mind” / „Csak a szükségesek” / „Beállítások” (statisztika, marketing). A választás a
 *   `vk_consent` sütiben (180 nap); bármely `.vk-consent-open` elem vagy `#sutibeallitasok` link újranyitja (a lábléc
 *   „Minden jog fenntartva” sora mellé magától kerül egy „Sütibeállítások” link).
 * – Google Consent Mode v2: alapból minden tiltva (a Google for WooCommerce / Site Kit címkéi így cookie nélkül
 *   futnak), a választás után frissül.
 * – Meta (Facebook) képpont: csak marketing-hozzájárulással töltődik be; PageView, ViewContent, AddToCart,
 *   InitiateCheckout, Purchase. Azonosító: Megjelenés → Beállítások → Követés.
 * – Barion „teljes” Pixel: az alapkódot a Barion bővítmény (vagy a téma) adja; marketing-hozzájárulással
 *   contentView, addToCart, initiateCheckout, purchase, setEncryptedEmail; elfogadáskor / elutasításkor
 *   bp('consent', 'grantConsent' / 'rejectConsent') – a Barion ezt nézi a jóváhagyáskor.
 */
class Tracking {
    const COOKIE = 'vk_consent';
    const META_PIXEL = '318737927688198';
    const SESSION_ATC = 'vk_track_atc';

    private $events = [];

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_filter('iu_theme_settings', [$this, 'settings']);
        add_action('wp_head', [$this, 'consent_default'], -1000);
        add_action('wp_footer', [$this, 'footer'], 50);
        add_action('woocommerce_add_to_cart', [$this, 'remember_add'], 20, 6);
        add_filter('woocommerce_add_to_cart_fragments', [$this, 'fragments']);
        // Ha a Facebook for WooCommerce bővítmény fut (Dávid, 2026-11-16), a Meta képpontot az adja: a saját kódunk
        // kimarad (nincs dupla), a bővítmény képpontja pedig csak marketing-hozzájárulással fut.
        add_filter('facebook_for_woocommerce_integration_pixel_enabled', [__CLASS__, 'marketing_allowed'], 20);
    }

    static function fb_plugin(){ return function_exists('facebook_for_woocommerce') || class_exists('\WC_Facebookcommerce'); }

    static function consent(){
        $raw = isset($_COOKIE[self::COOKIE]) ? json_decode(wp_unslash((string)$_COOKIE[self::COOKIE]), true) : null;
        return is_array($raw) ? $raw : null;
    }

    static function marketing_allowed($enabled = true){
        $c = self::consent();
        return $enabled && $c && !empty($c['m']);
    }

    function settings($fields){
        $fields['tracking'] = [
            'type' => 'tab',
            'title' => 'Követés (Meta, sütik)',
            'fields' => [
                'meta_pixel_id' => [ 'type' => 'text', 'label' => 'Meta (Facebook) képpont azonosító – csak marketing-sütik elfogadása után fut. Üresen a beépített (318737927688198), „0” = kikapcsolva.' ],
                'consent_text' => [ 'type' => 'textarea', 'label' => 'Sütisáv szövege (üresen az alapértelmezett)' ],
            ]
        ];
        return $fields;
    }

    /** A Meta képpont azonosítója: a beállításból, ha üres, a Dávid által megadott (2026-11-16); „0” kikapcsolja. */
    static function meta_pixel(){
        $set = trim((string)Reviews::setting('meta_pixel_id'));
        if ($set === '0') return '';
        $id = preg_replace('/\D/', '', $set);
        return $id !== '' ? $id : self::META_PIXEL;
    }

    /** Google Consent Mode v2 alapértékek – minden más címke előtt. */
    function consent_default(){
        if (is_admin()) return;
        echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}"
            ."(function(){var c=null;try{var m=document.cookie.match(/(?:^|; )vk_consent=([^;]*)/);if(m)c=JSON.parse(decodeURIComponent(m[1]));}catch(e){}"
            ."var a=c&&c.a?'granted':'denied',k=c&&c.m?'granted':'denied';"
            ."gtag('consent','default',{ad_storage:k,ad_user_data:k,ad_personalization:k,analytics_storage:a,functionality_storage:'granted',security_storage:'granted',wait_for_update:500});"
            ."gtag('set','ads_data_redaction',k!=='granted');})();</script>\n";
    }

    /* ---------- Események ---------- */

    private function add($meta = null, $barion = null){ $this->events[] = [ 'meta' => $meta, 'barion' => $barion ]; }

    private static function item_data($product, $qty = 1, $price = null){
        $price = $price ?? (float)wc_get_price_to_display($product);
        return [ 'id' => (string)$product->get_id(), 'name' => $product->get_name(), 'qty' => (int)$qty, 'price' => round((float)$price, 2) ];
    }

    private function product_events(){
        if (!function_exists('is_product') || !is_product()) return;
        $product = wc_get_product(get_queried_object_id());
        if (!$product) return;
        $price = (float)wc_get_price_to_display($product);
        $cur = get_woocommerce_currency();
        $this->add([ 'ViewContent', [ 'content_ids' => [ (string)$product->get_id() ], 'content_type' => 'product', 'content_name' => $product->get_name(), 'value' => $price, 'currency' => $cur ] ],
            [ 'contentView', [ 'contentType' => 'Product', 'currency' => $cur, 'id' => (string)$product->get_id(), 'name' => $product->get_name(), 'quantity' => 1, 'unit' => 'pcs', 'unitPrice' => $price ] ]);
    }

    private static function atc_event($item){
        $cur = get_woocommerce_currency();
        return [
            'meta' => [ 'AddToCart', [ 'content_ids' => [ $item['id'] ], 'content_type' => 'product', 'content_name' => $item['name'], 'value' => $item['price'] * $item['qty'], 'currency' => $cur ] ],
            'barion' => [ 'addToCart', [ 'contentType' => 'Product', 'currency' => $cur, 'id' => $item['id'], 'name' => $item['name'], 'quantity' => $item['qty'], 'unit' => 'pcs', 'unitPrice' => $item['price'], 'totalItemPrice' => $item['price'] * $item['qty'], 'step' => 1 ] ],
        ];
    }

    /** Kosárba tétel: a következő oldalbetöltéskor vagy az AJAX-válasz darabjaként megy ki. */
    function remember_add($key, $product_id, $qty, $variation_id = 0, $variation = [], $data = []){
        if (!function_exists('WC') || !WC()->session) return;
        $product = wc_get_product($variation_id ?: $product_id);
        if (!$product) return;
        $list = (array)WC()->session->get(self::SESSION_ATC, []);
        $list[] = self::item_data($product, $qty);
        WC()->session->set(self::SESSION_ATC, array_slice($list, -5));
    }

    private static function take_adds(){
        if (!function_exists('WC') || !WC()->session) return [];
        $list = (array)WC()->session->get(self::SESSION_ATC, []);
        if ($list) WC()->session->set(self::SESSION_ATC, []);
        return array_map([ __CLASS__, 'atc_event' ], $list);
    }

    function fragments($fragments){
        $events = self::take_adds();
        $fragments['div.vk-track-fragment'] = '<div class="vk-track-fragment" hidden data-events="'.esc_attr(wp_json_encode($events)).'"></div>';
        return $fragments;
    }

    private function cart_contents($items_source){
        $items = []; $ids = []; $num = 0;
        foreach($items_source as $item){
            $product = $item['product'] ?? null;
            if (!$product) continue;
            $qty = (int)$item['qty']; $unit = (float)$item['unit'];
            $items[] = [ 'contentType' => 'Product', 'currency' => get_woocommerce_currency(), 'id' => (string)$product->get_id(), 'name' => $product->get_name(), 'quantity' => $qty, 'unit' => 'pcs', 'unitPrice' => round($unit, 2), 'totalItemPrice' => round($unit * $qty, 2) ];
            $ids[] = (string)$product->get_id(); $num += $qty;
        }
        return [ $items, $ids, $num ];
    }

    private function checkout_events(){
        if (!function_exists('is_checkout') || !is_checkout() || is_wc_endpoint_url('order-received') || !WC()->cart || WC()->cart->is_empty()) return;
        $src = [];
        foreach(WC()->cart->get_cart() as $ci){
            $q = max(1, (int)$ci['quantity']);
            $src[] = [ 'product' => $ci['data'], 'qty' => $q, 'unit' => ((float)$ci['line_total'] + (float)$ci['line_tax']) / $q ];
        }
        [$items, $ids, $num] = $this->cart_contents($src);
        $value = (float)WC()->cart->get_cart_contents_total() + (float)WC()->cart->get_cart_contents_tax();
        $cur = get_woocommerce_currency();
        $this->add([ 'InitiateCheckout', [ 'content_ids' => $ids, 'content_type' => 'product', 'num_items' => $num, 'value' => round($value, 2), 'currency' => $cur ] ],
            [ 'initiateCheckout', [ 'contents' => $items, 'currency' => $cur, 'revenue' => round($value, 2), 'step' => 1 ] ]);
    }

    private function purchase_events(){
        if (!function_exists('is_order_received_page') || !is_order_received_page()) return;
        $order = wc_get_order(absint(get_query_var('order-received')));
        if (!$order || !hash_equals($order->get_order_key(), (string)($_GET['key'] ?? '')) || $order->has_status([ 'failed', 'cancelled' ])) return;
        if ($order->get_meta('_vk_tracked_purchase')) return;
        $src = [];
        foreach($order->get_items() as $item){
            $q = max(1, (int)$item->get_quantity());
            $src[] = [ 'product' => $item->get_product(), 'qty' => $q, 'unit' => ((float)$item->get_total() + (float)$item->get_total_tax()) / $q ];
        }
        [$items, $ids, $num] = $this->cart_contents($src);
        $cur = $order->get_currency();
        $this->add([ 'Purchase', [ 'content_ids' => $ids, 'content_type' => 'product', 'num_items' => $num, 'value' => round((float)$order->get_total(), 2), 'currency' => $cur ] ],
            [ 'purchase', [ 'contents' => $items, 'currency' => $cur, 'revenue' => round((float)$order->get_total(), 2), 'step' => 1 ] ]);
        $this->email = strtolower(trim((string)$order->get_billing_email()));
        $order->update_meta_data('_vk_tracked_purchase', time());
        $order->save_meta_data();
    }

    private $email = '';

    /* ---------- Kimenet ---------- */

    function footer(){
        if (is_admin()) return;
        $this->product_events();
        $this->checkout_events();
        $this->purchase_events();
        foreach(self::take_adds() as $e) $this->events[] = $e;
        $text = trim((string)Reviews::setting('consent_text')) ?: 'Sütiket használunk a webshop működéséhez, a látogatottság méréséhez és a hirdetéseink személyre szabásához (Google, Meta, Barion). A szükséges sütik nélkül a webshop nem működik; a többit csak a hozzájárulásoddal használjuk.';
        $privacy = get_privacy_policy_url();
        $cfg = [ 'meta' => self::fb_plugin() ? '' : self::meta_pixel(), 'events' => $this->events, 'email' => $this->email, 'checkout' => function_exists('is_checkout') && is_checkout() && !is_wc_endpoint_url('order-received') ];
        ?>
<div class="vk-track-fragment" hidden data-events="[]"></div>
<div id="vk-consent" class="vk-consent" role="dialog" aria-live="polite" aria-label="Sütibeállítások" hidden>
  <div class="vk-consent-box">
    <p class="vk-consent-title">Sütik a webshopban</p>
    <p class="vk-consent-text"><?php echo esc_html($text); ?><?php if ($privacy) echo ' <a href="'.esc_url($privacy).'" target="_blank">Adatkezelési tájékoztató</a>'; ?></p>
    <div class="vk-consent-options" hidden>
      <label><input type="checkbox" checked disabled> <strong>Szükséges</strong> – kosár, belépés, fizetés (mindig be)</label>
      <label><input type="checkbox" id="vk-consent-a"> <strong>Statisztika</strong> – látogatottság mérése (Google Analytics)</label>
      <label><input type="checkbox" id="vk-consent-m"> <strong>Marketing</strong> – hirdetések személyre szabása (Google Ads, Meta, Barion)</label>
    </div>
    <div class="vk-consent-actions">
      <button type="button" class="vk-consent-btn vk-consent-settings">Beállítások</button>
      <button type="button" class="vk-consent-btn vk-consent-save" hidden>Mentés</button>
      <button type="button" class="vk-consent-btn vk-consent-deny">Csak a szükségesek</button>
      <button type="button" class="vk-consent-btn vk-consent-all">Elfogadom mind</button>
    </div>
  </div>
</div>
<style>
.vk-consent{position:fixed;left:0;right:0;bottom:0;z-index:99999;padding:12px;pointer-events:none}
.vk-consent-box{pointer-events:auto;max-width:960px;margin:0 auto;background:#fff;color:#1f2937;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,.25);padding:18px 20px;font-size:14px;line-height:1.5}
.vk-consent-title{margin:0 0 6px;font-weight:700;font-size:16px}
.vk-consent-text{margin:0 0 12px}
.vk-consent-text a{color:inherit;text-decoration:underline}
.vk-consent-options{display:grid;gap:6px;margin:0 0 12px}
.vk-consent[hidden],.vk-consent-options[hidden],.vk-consent-btn[hidden]{display:none}
.vk-consent-options label{display:flex;gap:8px;align-items:flex-start;cursor:pointer}
.vk-consent-actions{display:flex;flex-wrap:wrap;gap:8px;justify-content:flex-end}
.vk-consent-btn{border:1px solid #1f2937;background:#fff;color:#1f2937;border-radius:6px;padding:9px 16px;font:inherit;font-weight:600;cursor:pointer}
.vk-consent-all{background:#1f2937;color:#fff}
.vk-consent-btn:hover{opacity:.85}
@media (max-width:600px){.vk-consent-actions{justify-content:stretch}.vk-consent-btn{flex:1 1 45%}}
</style>
<script>
(function(){
  var CFG = <?php echo wp_json_encode($cfg); ?>;
  var NAME = '<?php echo esc_js(self::COOKIE); ?>';
  var box = document.getElementById('vk-consent');
  function read(){ try { var m = document.cookie.match(new RegExp('(?:^|; )' + NAME + '=([^;]*)')); return m ? JSON.parse(decodeURIComponent(m[1])) : null; } catch (e) { return null; } }
  function write(c){ document.cookie = NAME + '=' + encodeURIComponent(JSON.stringify(c)) + ';path=/;max-age=' + (180 * 86400) + ';SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : ''); }
  var consent = read();
  var metaLoaded = false, queue = (CFG.events || []).slice();

  function loadMeta(){
    if (metaLoaded || !CFG.meta) return;
    metaLoaded = true;
    !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', CFG.meta);
    fbq('track', 'PageView');
  }
  function sha1(s){ if (!window.crypto || !crypto.subtle) return Promise.reject(); return crypto.subtle.digest('SHA-1', new TextEncoder().encode(s)).then(function(b){ return Array.prototype.map.call(new Uint8Array(b), function(x){ return ('0' + x.toString(16)).slice(-2); }).join(''); }); }
  function sendEmail(e){ if (!e || typeof bp === 'undefined') return; sha1(e.toLowerCase().trim()).then(function(h){ bp('identity', 'setEncryptedEmail', h); }).catch(function(){}); }
  function flush(){
    if (!consent || !consent.m) return;
    loadMeta();
    while (queue.length){
      var ev = queue.shift();
      try { if (ev.meta && CFG.meta && window.fbq) fbq('track', ev.meta[0], ev.meta[1]); } catch (e) {}
      try { if (ev.barion && typeof bp !== 'undefined') bp('track', ev.barion[0], ev.barion[1]); } catch (e) {}
    }
    if (CFG.email) { sendEmail(CFG.email); CFG.email = ''; }
  }
  function apply(c, changed){
    var a = c.a ? 'granted' : 'denied', m = c.m ? 'granted' : 'denied';
    if (typeof gtag === 'function') gtag('consent', 'update', { ad_storage: m, ad_user_data: m, ad_personalization: m, analytics_storage: a });
    // A Barion a döntés pillanatában kéri a hozzájárulást (visszatérő látogatónál a bp.js magától emlékszik).
    if (changed && typeof bp !== 'undefined') bp('consent', c.m ? 'grantConsent' : 'rejectConsent');
    flush();
  }
  function save(a, m){ consent = { a: a ? 1 : 0, m: m ? 1 : 0, t: Date.now() }; write(consent); box.hidden = true; apply(consent, true); }
  function open(){ var o = box.querySelector('.vk-consent-options'); document.getElementById('vk-consent-a').checked = !!(consent && consent.a); document.getElementById('vk-consent-m').checked = !!(consent && consent.m); box.hidden = false; if (consent) { o.hidden = false; box.querySelector('.vk-consent-save').hidden = false; box.querySelector('.vk-consent-settings').hidden = true; } }
  box.querySelector('.vk-consent-all').addEventListener('click', function(){ save(1, 1); });
  box.querySelector('.vk-consent-deny').addEventListener('click', function(){ save(0, 0); });
  box.querySelector('.vk-consent-settings').addEventListener('click', function(){ box.querySelector('.vk-consent-options').hidden = false; this.hidden = true; box.querySelector('.vk-consent-save').hidden = false; });
  box.querySelector('.vk-consent-save').addEventListener('click', function(){ save(document.getElementById('vk-consent-a').checked, document.getElementById('vk-consent-m').checked); });
  document.addEventListener('click', function(e){ var t = e.target.closest && e.target.closest('.vk-consent-open, a[href="#sutibeallitasok"]'); if (t) { e.preventDefault(); open(); } });

  // AJAX kosárba tétel: az események a WooCommerce válaszának darabjában jönnek.
  function takeHtml(html){ try { var d = document.createElement('div'); d.innerHTML = html; var el = d.querySelector('.vk-track-fragment'); var ev = el ? JSON.parse(el.getAttribute('data-events') || '[]') : []; if (ev.length) queue = queue.concat(ev); } catch (e) {} }
  if (window.jQuery) {
    jQuery(document.body).on('added_to_cart', function(e, fragments){
      if (fragments && fragments['div.vk-track-fragment']) { takeHtml(fragments['div.vk-track-fragment']); fragments['div.vk-track-fragment'] = '<div class="vk-track-fragment" hidden data-events="[]"></div>'; }
      flush();
    });
  }
  // Pénztár: a megadott e-mail (Barion setEncryptedEmail).
  if (CFG.checkout) document.addEventListener('change', function(e){ if (e.target && e.target.id === 'billing_email' && consent && consent.m) sendEmail(e.target.value); });

  // „Sütibeállítások” link a lábléc jogi sora mellé.
  function footerLink(){
    var ps = document.querySelectorAll('footer p, .iu-footer p, p');
    for (var i = 0; i < ps.length; i++) { if (/Minden jog fenntartva/i.test(ps[i].textContent) && !ps[i].querySelector('.vk-consent-open')) { var a = document.createElement('a'); a.href = '#sutibeallitasok'; a.className = 'vk-consent-open'; a.textContent = 'Sütibeállítások'; ps[i].appendChild(document.createTextNode(' · ')); ps[i].appendChild(a); break; } }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', footerLink); else footerLink();

  if (consent) apply(consent, false); else box.hidden = false;
})();
</script>
        <?php
    }
}
