<?php

namespace VasskisgepTheme;

/** Cofidis áruhitel fizetési mód (lásd inc/cofidis.php). */
class CofidisGateway extends \WC_Payment_Gateway {

    function __construct(){
        $this->id = Cofidis::ID;
        $this->method_title = 'Cofidis áruhitel';
        $this->method_description = 'Vásárlás Cofidis áruhitellel. Csak akkor választható, ha a kosár minden terméke „Cofidis áruhitel” jelölésű, és az összeg a Megjelenés → Beállítások → Cofidis áruhitel fülön megadott határokon belül van. Kézi ügyintézés: a rendelés „Függőben” állapotba kerül; a hitelbírálat után a rendelés műveleteinél „Cofidis: hitel jóváhagyva / elutasítva”.'
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

    /** A pénztárban a leírás mellé a várható havi részlet (12 hónap), ha a THM meg van adva. */
    function get_description(){
        $description = parent::get_description();
        $config = ProductPage::cofidis_config();
        if ($config && function_exists('WC') && WC()->cart){
            $months = in_array(12, $config['terms'], true) ? 12 : $config['terms'][0];
            $monthly = ProductPage::cofidis_installment((float)WC()->cart->get_total('edit'), $months, $config);
            if ($monthly > 0){
                $description .= "\n\n".sprintf('Várható havi törlesztőrészlet: %s / hó (%d hónap, THM: %s %%). A kalkuláció tájékoztató jellegű.',
                    number_format($monthly, 0, ',', '.').' Ft', $months, number_format($config['thm'], 2, ',', ' '));
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
        $order->update_status('on-hold', 'Cofidis hitelbírálatra vár.');
        wc_maybe_reduce_stock_levels($order_id);
        WC()->cart->empty_cart();

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
        echo '<div class="vk-notice vk-cofidis-notice"><p>'.nl2br(esc_html($this->get_option('instructions'))).'</p>'
            .($link !== '' ? '<p><a class="iu-button iu-button-default" href="'.esc_url($link).'" target="_blank" rel="noopener"><span class="iu-button-text"><span>Hitelkérelem indítása</span></span></a></p>' : '')
            .'</div>';
    }

    function email_instructions($order, $sent_to_admin, $plain_text = false){
        if ($sent_to_admin || !$order instanceof \WC_Order || $order->get_payment_method() !== $this->id || !$order->has_status('on-hold')) return;
        $text = $this->get_option('instructions');
        $link = $this->application_link($order);
        if ($plain_text){
            echo "\n".$text.($link !== '' ? "\nHitelkérelem: ".$link : '')."\n\n";
        } else {
            echo '<p>'.nl2br(esc_html($text)).'</p>'.($link !== '' ? '<p><a href="'.esc_url($link).'">Hitelkérelem indítása</a></p>' : '');
        }
    }
}
