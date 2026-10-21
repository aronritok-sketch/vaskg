<?php

namespace VasskisgepTheme;

/**
 * „Súly szerinti házhozszállítás” szállítási mód (lásd inc/shipping.php). A díjtábla bruttó összegekkel szerkeszthető;
 * adóköteles szállításnál a nettó díjat a WooCommerce a szállítás ÁFA-kulcsával számolja vissza.
 */
class WeightRate extends \WC_Shipping_Method {

    function __construct($instance_id = 0){
        $this->id = Shipping::METHOD;
        $this->instance_id = absint($instance_id);
        $this->method_title = 'Súly szerinti házhozszállítás';
        $this->method_description = 'Díj a csomag súlya szerint (díjtábla), ingyenes szállítás értékhatárral. A termék súlya a „Tömeg” mezőből, ha üres, a Tömeg tulajdonságból.';
        $this->supports = [ 'shipping-zones', 'instance-settings', 'instance-settings-modal' ];
        $this->instance_form_fields = [
            'title' => [ 'title' => 'Megnevezés', 'type' => 'text', 'default' => 'Házhozszállítás futárszolgálattal' ],
            'rates' => [
                'title' => 'Díjtábla (bruttó)',
                'type' => 'textarea',
                'default' => Shipping::DEFAULT_RATES,
                'css' => 'min-height:14rem;font-family:monospace',
                'description' => 'Soronként: legfeljebb ennyi kg | bruttó díj Ft-ban. Pl. „10 | 1900” = 10 kg-ig 1 900 Ft. Az utolsó sor felett nem rendelhető házhozszállítással (egyedi egyeztetés).',
            ],
            'free_total' => [ 'title' => 'Ingyenes szállítás ennyi Ft (bruttó) felett', 'type' => 'text', 'default' => '30000', 'description' => '0 vagy üres: nincs ingyenes szállítás. Kampányhoz átírható.' ],
            'free_weight' => [ 'title' => 'Ingyenes szállítás legfeljebb ennyi kg-ig', 'type' => 'text', 'default' => '10', 'description' => '0 vagy üres: súlytól függetlenül ingyenes az értékhatár felett.' ],
            'tax_status' => [ 'title' => 'Adózás', 'type' => 'select', 'default' => 'taxable', 'options' => [ 'taxable' => 'Adóköteles', 'none' => 'Nincs' ] ],
        ];
        $this->init_settings();
        $this->title = $this->get_option('title');
        $this->tax_status = $this->get_option('tax_status');
        add_action('woocommerce_update_options_shipping_'.$this->id, [ $this, 'process_admin_options' ]);
    }

    function calculate_shipping($package = []){
        $weight = 0.0;
        $total = 0.0;
        foreach($package['contents'] as $item){
            $weight += Shipping::product_weight($item['data']) * (float)$item['quantity'];
            $total += (float)$item['line_total'] + (float)$item['line_tax'];
        }
        $gross = Shipping::gross_cost($this, $weight, $total);
        if ($gross === null) return; // a táblán kívül: nem kínáljuk fel (egyedi egyeztetés)

        $cost = $gross;
        if ($gross > 0 && $this->is_taxable()){
            $rates = \WC_Tax::get_shipping_tax_rates();
            $percent = array_sum(wp_list_pluck($rates, 'rate'));
            if ($percent > 0) $cost = $gross / (1 + $percent / 100);
        }
        $this->add_rate([
            'id' => $this->get_rate_id(),
            'label' => $this->title,
            'cost' => $cost,
            'package' => $package,
            'meta_data' => [ 'Csomag súlya' => wc_format_localized_decimal(round($weight, 1)).' kg' ],
        ]);
    }
}
