<?php

namespace VasskisgepTheme;

/**
 * Vásárlói levelek az új státuszokhoz és az elégedettségi kérdőívhez (WooCommerce levelek: a WooCommerce →
 * Beállítások → E-mailek alatt szerkeszthető a tárgy, a fejléc, a bevezető, és ki- / bekapcsolható).
 * A tartalom a WooCommerce levélsablonjának részeiből épül (fejléc, rendelés-összesítő, vásárlói adatok, lábléc).
 */
abstract class VK_Status_Email extends \WC_Email {
    public $intro = '';

    function __construct(){
        $this->customer_email = true;
        $this->placeholders = [ '{order_date}' => '', '{order_number}' => '' ];
        parent::__construct();
    }

    function trigger($order_id, $order = false){
        $this->setup_locale();
        if ($order_id && !$order instanceof \WC_Order) $order = wc_get_order($order_id);
        if ($order instanceof \WC_Order){
            $this->object = $order;
            $this->recipient = $order->get_billing_email();
            $this->placeholders['{order_date}'] = wc_format_datetime($order->get_date_created());
            $this->placeholders['{order_number}'] = $order->get_order_number();
        }
        if ($this->is_enabled() && $this->get_recipient()){
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
        $this->restore_locale();
    }

    /** A bevezető szöveg (HTML) a rendelés-összesítő fölött. */
    abstract protected function body_html();

    function get_content_html(){
        ob_start();
        do_action('woocommerce_email_header', $this->get_heading(), $this);
        $order = $this->object;
        echo '<p>'.esc_html(sprintf('Kedves %s!', $order ? $order->get_billing_first_name() : '')).'</p>';
        echo $this->body_html();
        if ($order){
            do_action('woocommerce_email_order_details', $order, false, false, $this);
            do_action('woocommerce_email_customer_details', $order, false, false, $this);
        }
        $additional = $this->get_additional_content();
        if ($additional) echo wp_kses_post(wpautop(wptexturize($additional)));
        do_action('woocommerce_email_footer', $this);
        return ob_get_clean();
    }

    function get_content_plain(){
        return wp_strip_all_tags(str_replace([ '</p>', '<br>' ], "\n", $this->get_content_html()));
    }
}

/** „Postázva”: a csomag úton van, csomagszámmal. */
class VK_Email_Shipped extends VK_Status_Email {
    function __construct(){
        $this->id = 'vk_customer_shipped';
        $this->title = 'Postázva (vásárlónak)';
        $this->description = 'A vásárló kapja, amikor a rendelés „Postázva” státuszba kerül – a csomagszámmal (Csomagkövetés doboz a rendelésnél).';
        add_action('woocommerce_order_status_'.OrderStatuses::SHIPPED.'_notification', [ $this, 'trigger' ], 10, 2);
        parent::__construct();
    }
    function get_default_subject(){ return 'Úton van a rendelésed ({order_number})'; }
    function get_default_heading(){ return 'Úton van a csomagod!'; }
    protected function body_html(){
        // A csomagszám a rendelés-összesítő fölött jelenik meg (OrderStatuses::email_tracking).
        return '<p>Jó hírünk van: a rendelésedet átadtuk a futárnak, hamarosan nálad lesz.</p>';
    }
}

/** „Részben teljesítve”: mi ment el / vehető át, és mi érkezik később. */
class VK_Email_Partial extends VK_Status_Email {
    function __construct(){
        $this->id = 'vk_customer_partial';
        $this->title = 'Részben teljesítve (vásárlónak)';
        $this->description = 'A vásárló kapja, amikor a rendelés „Részben teljesítve” státuszba kerül – a még hiányzó tételek listájával (a tételeknél bejelölve).';
        add_action('woocommerce_order_status_'.OrderStatuses::PARTIAL.'_notification', [ $this, 'trigger' ], 10, 2);
        parent::__construct();
    }
    function get_default_subject(){ return 'A rendelésed egy része úton van ({order_number})'; }
    function get_default_heading(){ return 'A rendelésed egy része elkészült'; }
    protected function body_html(){
        $html = '<p>A rendelésed egy részét már teljesítettük. A többi tételt, amint megérkezik hozzánk, azonnal küldjük, erről is értesítünk.</p>';
        $pending = $this->object ? OrderStatuses::pending_items($this->object) : [];
        if ($pending){
            $html .= '<p><strong>Később érkezik:</strong></p><ul>';
            foreach($pending as $item) $html .= '<li>'.esc_html($item->get_quantity().' × '.$item->get_name()).'</li>';
            $html .= '</ul>';
        }
        return $html;
    }
}

/**
 * Elégedettségi kérdőív (ügyfélkérés, 2026-11): csak ha a rendelésnél bepipálták („Csomagkövetés és teljesítés”
 * doboz), a „Teljesítve” státuszkor egyszer. A kérdőív címe a levél beállításaiban (alapból a Google értékelés).
 */
class VK_Email_Survey extends VK_Status_Email {
    function __construct(){
        $this->id = 'vk_customer_survey';
        $this->title = 'Elégedettségi kérdőív (vásárlónak)';
        $this->description = 'Csak azoknak a rendeléseknek, ahol a „Elégedettségi kérdőív küldése teljesítéskor” be van pipálva; a Teljesítve státuszkor, egyszer.';
        add_action('woocommerce_order_status_completed_notification', [ $this, 'maybe_trigger' ], 20, 2);
        parent::__construct();
    }
    function init_form_fields(){
        parent::init_form_fields();
        $this->form_fields['survey_url'] = [
            'title' => 'Kérdőív / értékelés címe',
            'type' => 'text',
            'description' => 'Ide visz a levél gombja (pl. Google Form vagy a Google értékelés oldala). Üresen a Google cégprofil.',
            'default' => '',
            'desc_tip' => true,
        ];
    }
    function maybe_trigger($order_id, $order = false){
        $order = $order instanceof \WC_Order ? $order : wc_get_order($order_id);
        if (!$order || $order->get_meta('_vk_survey') !== 'yes') return;
        $this->trigger($order_id, $order);
        $order->update_meta_data('_vk_survey', 'sent');
        $order->save();
    }
    function url(){
        $url = trim((string)$this->get_option('survey_url'));
        if ($url === '' && class_exists(__NAMESPACE__.'\\Reviews')) $url = Reviews::setting('google_profile_url');
        return $url !== '' ? $url : 'https://www.google.com/maps/search/?api=1&query='.rawurlencode('Vass Kisgép 2011 Kft. Kisvárda');
    }
    function get_default_subject(){ return 'Elégedett volt vásárlásával?'; }
    function get_default_heading(){ return 'Köszönjük, hogy nálunk vásároltál!'; }
    protected function body_html(){
        return '<p>Reméljük, minden rendben megérkezett, és örömmel használod. Sokat segítene nekünk, ha egy percben elmondanád, hogy voltál megelégedve a vásárlással és a kiszolgálással.</p>'
            .'<p style="margin:22px 0;text-align:center"><a href="'.esc_url($this->url()).'" target="_blank" style="display:inline-block;padding:13px 26px;border-radius:6px;background:#f47b20;color:#ffffff;font-weight:700;text-decoration:none">Véleményem elmondom</a></p>'
            .'<p>Ha bármi gond volt, egyszerűen válaszolj erre a levélre, és segítünk.</p>';
    }
    function get_content_html(){
        // A kérdőívnél nem kell a teljes rendelés-összesítő.
        ob_start();
        do_action('woocommerce_email_header', $this->get_heading(), $this);
        $order = $this->object;
        echo '<p>'.esc_html(sprintf('Kedves %s!', $order ? $order->get_billing_first_name() : '')).'</p>';
        echo $this->body_html();
        do_action('woocommerce_email_footer', $this);
        return ob_get_clean();
    }
}
