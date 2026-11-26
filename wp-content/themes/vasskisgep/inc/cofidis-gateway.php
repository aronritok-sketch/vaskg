<?php

namespace VasskisgepTheme;

/** Cofidis áruhitel fizetési mód (lásd inc/cofidis.php). */
class CofidisGateway extends \WC_Payment_Gateway {

    function __construct(){
        $this->id = Cofidis::ID;
        $this->method_title = 'Cofidis áruhitel';
        $this->method_description = (CofidisBank::configured() ? '<strong>Cofidis expressz online áruhitel (E-direkt).</strong> A vásárló a bank hiteligénylő oldalára megy; a kalkulátor a bank beágyazott kalkulátora. Élesítésig csak a tesztlinkkel látszik: <code>'.esc_html(CofidisBank::test_url()).'</code>. Tanúsítvány: WooCommerce → Cofidis tanúsítvány.<br>' : '').'Vásárlás Cofidis áruhitellel. Csak akkor választható, ha a kosár minden terméke „Cofidis áruhitel” jelölésű, és az összeg a Megjelenés → Beállítások → Cofidis áruhitel fülön megadott határokon belül van. Kézi ügyintézés: a rendelés „Függőben” állapotba kerül; a hitelbírálat után a rendelés műveleteinél „Cofidis: hitel jóváhagyva / elutasítva”.'
            .'<br>Visszahívási cím (online hitelkérelem bekötésekor ezt kell a Cofidisnak megadni): <code>'.esc_html(rest_url('vasskisgep/v1/cofidis/callback')).'</code>';
        $this->has_fields = false;
        $this->supports = [ 'products' ];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_thankyou_'.$this->id, [$this, 'thankyou']);
        add_action('woocommerce_email_before_order_table', [$this, 'email_instructions'], 10, 3);
    }

    function init_form_fields(){
        $this->form_fields = [
            'enabled' => [
                'title' => 'Engedélyezés',
                'type' => 'checkbox',
                'label' => 'Cofidis áruhitel fizetési mód engedélyezése',
                'default' => 'no'
            ],
            'title' => [
                'title' => 'Megnevezés',
                'type' => 'text',
                'default' => 'Cofidis áruhitel'
            ],
            'description' => [
                'title' => 'Leírás a pénztárban',
                'type' => 'textarea',
                'default' => 'Vásároljon kényelmes havi részletekben Cofidis áruhitellel. A rendelés után munkatársunk segít a hitelkérelem benyújtásában; a hitelbírálatot a Magyar Cofidis Bank végzi.'
            ],
            'bank_mode' => [
                'title' => 'Bank-kapcsolat',
                'type' => 'select',
                'options' => [ 'demo' => 'Demo (teszt) – dmo-services.cofidisbank.hu', 'live' => 'Éles' ],
                'default' => 'demo',
                'description' => 'Élesre csak a bank jóváhagyása után (a demo tesztek után) állítsd; pénteken és ünnep előtt nem lehet élesíteni.'
            ],
            'live_enabled' => [
                'title' => 'Élesítve',
                'type' => 'checkbox',
                'label' => 'A kalkulátor és a fizetési mód mindenkinek látszik (különben csak a tesztlinkkel)',
                'default' => 'no'
            ],
            'shop_code' => [
                'title' => 'Boltkód (shopCode)',
                'type' => 'text',
                'default' => '1000125'
            ],
            'barems' => [
                'title' => 'Hiteltermékek (baremID; megnevezés)',
                'type' => 'textarea',
                'default' => '961; THM 19,9% (II)',
                'description' => 'Soronként egy; az első az alapértelmezett. A baremID-t a bank adja hiteltermékenként.'
            ],
            'min_amount' => [
                'title' => 'Legkisebb vételár (Ft)',
                'type' => 'text',
                'default' => '25000'
            ],
            'max_amount' => [
                'title' => 'Legnagyobb vételár (Ft)',
                'type' => 'text',
                'default' => '2500000',
                'description' => 'A hirdetmény szerint a hitelösszeg 25 000 – 2 000 000 Ft; 500 000 Ft fölött legalább 20% önrész.'
            ],
            'live_base' => [
                'title' => 'Éles bank-cím',
                'type' => 'text',
                'default' => 'https://services.cofidisbank.hu/',
                'description' => 'A bank éles szolgáltatásának alapcíme (élesítéskor a bank megerősíti).'
            ],
            'tech_user' => [
                'title' => 'Technikai felhasználó',
                'type' => 'text',
                'default' => '',
                'description' => 'A banktól kapott technikai felhasználónév (státusz-lekérdezéshez és „áru kiszállítva” jelzéshez).'
            ],
            'tech_password' => [
                'title' => 'Technikai jelszó',
                'type' => 'password',
                'default' => ''
            ],
            'application_url' => [
                'title' => 'Hitelkérelem link (nem kötelező)',
                'type' => 'text',
                'description' => 'Ha a Cofidis ad a partnereinek online hitelkérelmi linket, itt adható meg; a köszönőoldalon és az e-mailben gombként jelenik meg. Helyőrzők: {order} (rendelésszám), {amount} (végösszeg Ft-ban).',
                'default' => ''
            ],
            'instructions' => [
                'title' => 'Tájékoztató a köszönőoldalon és az e-mailben',
                'type' => 'textarea',
                'default' => 'Köszönjük a rendelését! A Cofidis áruhitel igényléséhez munkatársunk 1 munkanapon belül felveszi Önnel a kapcsolatot. A rendelést a hitel jóváhagyása után teljesítjük.'
            ]
        ];
    }

    function is_available(){
        if (!parent::is_available()) return false;
        if (get_woocommerce_currency() !== 'HUF') return false;
        if (is_admin() && !wp_doing_ajax()) return true;
        // Bank-kapcsolattal élesítésig csak a tesztelők látják (a bank kérése).
        if (CofidisBank::configured() && !CofidisBank::visible()) return false;
        // Fizetési oldal (order-pay): a rendelés alapján.
        if (is_wc_endpoint_url('order-pay')){
            $order = wc_get_order(absint(get_query_var('order-pay')));
            if (!$order) return false;
            foreach($order->get_items() as $item){
                $product = $item->get_product();
                if (!$product || !Catalog::has_cofidis(wc_get_product($product->get_parent_id() ?: $product->get_id()))) return false;
            }
            return Cofidis::amount_eligible((float)$order->get_total());
        }
        return Cofidis::cart_eligible();
    }

    /** A pénztárban: bank-kapcsolattal a bank kalkulátora a kosár összegével. */
    function payment_fields(){
        parent::payment_fields();
        if (CofidisBank::configured() && function_exists('WC') && WC()->cart) echo CofidisBank::widget((float)WC()->cart->get_total('edit'), 'vk-cof-checkout', CofidisBank::cart_state()['barem']);
    }

    /** A pénztárban a leírás mellé a várható havi részlet (12 hónap), ha a THM meg van adva. */
    function get_description(){
        $description = parent::get_description();
        if (CofidisBank::configured()) return $description;
        $config = ProductPage::cofidis_config();
        if ($config && function_exists('WC') && WC()->cart){
            $option = $config['options'][ProductPage::cofidis_default_option($config)];
            $months = (int)$option['months'];
            $monthly = ProductPage::cofidis_installment((float)WC()->cart->get_total('edit'), $months, $option);
            if ($monthly > 0){
                $description .= "\n\n".sprintf('Várható havi törlesztőrészlet: %s / hó (%d hónap, THM: %s %%%s). A kalkuláció tájékoztató jellegű.',
                    number_format($monthly, 0, ',', '.').' Ft', $months, number_format($option['thm'], 2, ',', ' '), $option['name'] !== '' ? ', '.$option['name'] : '');
            }
        }
        return $description;
    }

    function process_payment($order_id){
        $order = wc_get_order($order_id);

        // Online hitelkérelem (API) bekötése esetén: a szűrő URL-t ad vissza, ahová a vásárlót irányítjuk.
        $redirect = apply_filters('vasskisgep_cofidis_start_application', '', $order, $this);
        if (is_wp_error($redirect)){
            wc_add_notice('A Cofidis hitelkérelem indítása nem sikerült: '.$redirect->get_error_message(), 'error');
            return [ 'result' => 'failure' ];
        }

        $order->update_meta_data('_vk_cofidis_status', 'pending');
        if (CofidisBank::configured()) $order->update_meta_data('_vk_cofidis_barem', CofidisBank::cart_state()['barem'] ?: CofidisBank::barem());
        $order->update_status('on-hold', 'Cofidis hitelbírálatra vár.');
        wc_maybe_reduce_stock_levels($order_id);
        WC()->cart->empty_cart();
        // Online hiteligénylés: a bank hiteligénylő oldalára (a köztes oldal POST-tal továbbít).
        if ($redirect === '' && CofidisBank::configured()) $redirect = CofidisBank::start_url($order);

        return [
            'result' => 'success',
            'redirect' => $redirect !== '' ? $redirect : $this->get_return_url($order)
        ];
    }

    private function application_link($order){
        $url = trim((string)$this->get_option('application_url'));
        if ($url === '') return '';
        return str_replace([ '{order}', '{amount}' ], [ rawurlencode($order->get_order_number()), rawurlencode((string)round((float)$order->get_total())) ], $url);
    }

    function thankyou($order_id){
        $order = wc_get_order($order_id);
        if (!$order) return;
        $link = $this->application_link($order);
        if (CofidisBank::configured()){
            $result = (string)$order->get_meta('_vk_cofidis_result');
            $texts = [
                'zold' => 'A Cofidis Bank előzetesen elfogadta a hitelkérelmét. A bank e-mailben küldi a további teendőket; a terméket a bank jóváhagyása után küldjük.',
                'narancs' => 'A hitelkérelme további elbírálás alatt van. A Cofidis Bank e-mailben értesíti az eredményről; mi is jelentkezünk.',
                'piros' => 'A Cofidis Bank sajnos nem tudta elfogadni a hitelkérelmet, ezért a rendelést visszamondtuk. Ha más fizetési móddal rendelne, szívesen segítünk.',
                'timeout' => 'A hitelkérelem nem készült el. Ha szeretné, indítsa újra az alábbi gombbal, vagy hívjon minket.',
            ];
            $text = $texts[$result] ?? 'Köszönjük a rendelését! Ha a hiteligénylést még nem fejezte be, az alábbi gombbal folytathatja.';
            $again = !in_array($result, [ 'zold', 'narancs', 'piros' ], true) && $order->has_status([ 'on-hold', 'pending' ]);
            echo '<div class="vk-notice vk-cofidis-notice"><p>'.esc_html($text).'</p>'
                .($again ? '<p><a class="iu-button iu-button-default" href="'.esc_url(CofidisBank::start_url($order)).'"><span class="iu-button-text"><span>Hiteligénylés a Cofidis Banknál</span></span></a></p>' : '')
                .'</div>';
            return;
        }
        echo '<div class="vk-notice vk-cofidis-notice"><p>'.nl2br(esc_html($this->get_option('instructions'))).'</p>'
            .($link !== '' ? '<p><a class="iu-button iu-button-default" href="'.esc_url($link).'" target="_blank" rel="noopener"><span class="iu-button-text"><span>Hitelkérelem indítása</span></span></a></p>' : '')
            .'</div>';
    }

    function email_instructions($order, $sent_to_admin, $plain_text = false){
        if ($sent_to_admin || !$order instanceof \WC_Order || $order->get_payment_method() !== $this->id || !$order->has_status('on-hold')) return;
        $text = $this->get_option('instructions');
        $link = CofidisBank::configured() ? CofidisBank::start_url($order) : $this->application_link($order);
        if (CofidisBank::configured()) $text = 'Köszönjük a rendelését! A Cofidis áruhitel igénylését a bank oldalán lehet elvégezni; ha még nem fejezte be, az alábbi linken folytathatja. A rendelést a bank jóváhagyása után teljesítjük.';
        if ($plain_text){
            echo "\n".$text.($link !== '' ? "\nHitelkérelem: ".$link : '')."\n\n";
        } else {
            echo '<p>'.nl2br(esc_html($text)).'</p>'.($link !== '' ? '<p><a href="'.esc_url($link).'">Hitelkérelem indítása</a></p>' : '');
        }
    }
}
