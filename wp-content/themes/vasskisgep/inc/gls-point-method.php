<?php

namespace VasskisgepTheme;

/** „GLS CsomagPont / Csomagautomata” szállítási mód (lásd inc/gls-point.php). */
class GlsPointMethod extends \WC_Shipping_Method {

    function __construct($instance_id = 0){
        $this->id = GlsPoint::METHOD;
        $this->instance_id = absint($instance_id);
        $this->method_title = 'GLS CsomagPont / Csomagautomata';
        $this->method_description = 'Átvétel GLS CsomagPontban vagy csomagautomatában; a vásárló a pénztárban a GLS térképén választ. Díj súly szerint; a GLS a pontokra legfeljebb 20 kg-os csomagot visz.';
        $this->supports = [ 'shipping-zones', 'instance-settings', 'instance-settings-modal' ];
        $this->instance_form_fields = [
            'title' => [ 'title' => 'Megnevezés', 'type' => 'text', 'default' => 'GLS CsomagPont / Csomagautomata' ],
            'rates' => [
                'title' => 'Díjtábla (bruttó)',
                'type' => 'textarea',
                'default' => GlsPoint::DEFAULT_RATES,
                'css' => 'min-height:6rem;font-family:monospace',
                'description' => 'Soronként: legfeljebb ennyi kg | bruttó díj Ft-ban. Az utolsó sor felett (pl. 20 kg) nem választható.',
            ],
            'free_total' => [ 'title' => 'Ingyenes szállítás ennyi Ft (bruttó) felett', 'type' => 'text', 'default' => '30000', 'description' => '0 vagy üres: nincs ingyenes szállítás.' ],
            'free_weight' => [ 'title' => 'Ingyenes szállítás legfeljebb ennyi kg-ig', 'type' => 'text', 'default' => '10' ],
            'tax_status' => [ 'title' => 'Adózás', 'type' => 'select', 'default' => 'taxable', 'options' => [ 'taxable' => 'Adóköteles', 'none' => 'Nincs' ] ],
        ];
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->tax_status = $this->get_option('tax_status');
        add_action('woocommerce_update_options_shipping_'.$this->id, [ $this, 'process_admin_options' ]);
    }

    function calculate_shipping($package = []){
        // A pontra a teljes csomag súlya számít (20 kg-os korlát), a díjhoz – mint a házhozszállításnál – a nem ingyenes termékeké.
        $total_weight = 0.0;
        foreach($package['contents'] as $item){
            $w = Shipping::product_weight($item['data']);
            $total_weight += ($w > 0 ? $w : 0) * (int)$item['quantity'];
        }
        $max = 0.0;
        foreach(Shipping::parse_rates($this->get_option('rates', GlsPoint::DEFAULT_RATES)) as [$limit]) $max = max($max, (float)$limit);
        if ($max > 0 && $total_weight > $max + 1e-9) return;

        [$weight, $paid] = Shipping::paid_weight($package['contents']);
        $total = 0.0;
        foreach($package['contents'] as $item) $total += (float)$item['line_total'] + (float)$item['line_tax'];
        $gross = $paid ? Shipping::gross_cost($this, $weight, $total) : 0.0;
        if ($gross === null) return;
        $cost = $gross;
        if ($gross > 0 && $this->is_taxable()){
            $percent = array_sum(wp_list_pluck(\WC_Tax::get_shipping_tax_rates(), 'rate'));
            if ($percent > 0) $cost = $gross / (1 + $percent / 100);
        }
        $this->add_rate([ 'id' => $this->get_rate_id(), 'label' => $this->title, 'cost' => $cost, 'package' => $package ]);
    }
}
