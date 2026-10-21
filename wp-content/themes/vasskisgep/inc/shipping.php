<?php

namespace VasskisgepTheme;

/**
 * Súly szerinti házhozszállítás (a régi webshop díjtáblája szerint; ügyfélkérés, 2026-10).
 * A díjtábla, az ingyenes szállítás értékhatára és súlyhatára a WooCommerce → Beállítások → Szállítás → Magyarország
 * zónában, a „Súly szerinti házhozszállítás” mód beállításaiban szerkeszthető (kampányokhoz is).
 * A termék súlya: a WooCommerce „Tömeg” mezője (kg), ha üres, a „Tömeg” tulajdonság (pa_tomeg).
 */
class Shipping {
    const METHOD = 'vk_weight_rate';

    /** A régi webshop díjtáblája (ÁSZF 5.5.2., bruttó): legfeljebb ennyi kg → ennyi Ft. */
    const DEFAULT_RATES = "10 | 1900\n15 | 2390\n20 | 2790\n30 | 3190\n40 | 4290\n50 | 5790\n60 | 7490\n80 | 8790\n100 | 11490\n300 | 15890\n500 | 17790";

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('woocommerce_shipping_init', function(){ require_once __DIR__.'/shipping-weight-rate.php'; });
        add_filter('woocommerce_shipping_methods', function($methods){
            $methods[self::METHOD] = __NAMESPACE__.'\\WeightRate';
            return $methods;
        });
        // Ingyenes házhozszállításnál a díj helyén „Ingyenes”; a táblán túli súlynál magyarázat a pénztárban és a kosárban.
        add_filter('woocommerce_cart_shipping_method_full_label', function($label, $rate){
            return ($rate->get_method_id() === self::METHOD && (float)$rate->get_cost() == 0) ? $label.': <strong>Ingyenes</strong>' : $label;
        }, 10, 2);
        add_action('woocommerce_review_order_after_shipping', [ $this, 'over_limit_notice' ]);
        add_action('woocommerce_cart_totals_after_shipping', [ $this, 'over_limit_notice' ]);
        iucb_add_block('vasskisgep/shipping-rates', [
            'title' => 'Szállítási díjtábla',
            'description' => 'A súly szerinti házhozszállítás díjai a WooCommerce beállításaiból.',
            'category' => 'iu-woocommerce',
            'attributes' => [],
            'editJS' => 'return "Szállítási díjtábla (súly szerint)";',
            'template' => function(){ return self::rates_table(); }
        ]);
    }

    function over_limit_notice(){
        $method = self::method();
        if (!$method || !WC()->cart) return;
        $weight = 0.0;
        foreach(WC()->cart->get_cart() as $item) $weight += self::product_weight($item['data']) * (float)$item['quantity'];
        $rows = self::parse_rates($method->get_option('rates', self::DEFAULT_RATES));
        if (empty($rows) || $weight <= end($rows)[0]) return;
        echo '<tr class="vk-shipping-over"><td colspan="2"><small>A csomag súlya kb. '.esc_html(wc_format_localized_decimal(round($weight))).' kg: ekkora tételnél a házhozszállítás módját és díját egyedileg egyeztetjük. Válassza a személyes átvételt, és megjegyzésben jelezze a szállítási igényt, vagy hívjon minket: +36 30 289 5977.</small></td></tr>';
    }

    /** A termék súlya kg-ban (Tömeg mező, különben a Tömeg tulajdonság; ismeretlen: 0). */
    static function product_weight($product){
        if (!$product) return 0.0;
        $weight = (float)wc_get_weight((float)$product->get_weight(), 'kg');
        if ($weight > 0) return $weight;
        $id = $product->get_parent_id() ?: $product->get_id();
        $terms = wc_get_product_terms($id, 'pa_tomeg', [ 'fields' => 'names' ]);
        if (!empty($terms)) return (float)str_replace([ ' ', ',' ], [ '', '.' ], preg_replace('/[^0-9,.\s]/u', '', $terms[0]));
        return 0.0;
    }

    /** A díjtábla sorai: [[max kg, bruttó Ft], …] súly szerint növekvő sorrendben. */
    static function parse_rates($text){
        $rows = [];
        foreach(preg_split('/\r\n|\r|\n/', (string)$text) as $line){
            if (!preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*(?:kg)?\s*[|;:=\t]\s*(\d+(?:[ .]\d{3})*(?:,\d+)?)/iu', $line, $m)) continue;
            $rows[] = [ (float)str_replace(',', '.', $m[1]), (float)str_replace([ ' ', '.', ',' ], [ '', '', '.' ], $m[2]) ];
        }
        usort($rows, function($a, $b){ return $a[0] <=> $b[0]; });
        return $rows;
    }

    /** Az aktív (Magyarország zónás) súly szerinti mód, ha van. */
    static function method(){
        if (!function_exists('WC') || !class_exists('\WC_Shipping_Zones')) return null;
        $zone = \WC_Shipping_Zones::get_zone_matching_package([ 'destination' => [ 'country' => 'HU', 'state' => '', 'postcode' => '' ] ]);
        foreach($zone->get_shipping_methods(true) as $method){
            if ($method->id === self::METHOD) return $method;
        }
        return null;
    }

    /**
     * Bruttó díj a súly és a (bruttó) kosárérték alapján; null, ha a súly a táblán kívül esik (egyedi egyeztetés).
     */
    static function gross_cost($method, $weight, $total){
        $free_total = (float)str_replace([ ' ', ',' ], [ '', '.' ], (string)$method->get_option('free_total', '30000'));
        $free_weight = (float)str_replace(',', '.', (string)$method->get_option('free_weight', '10'));
        if ($free_total > 0 && $total >= $free_total && ($free_weight <= 0 || $weight <= $free_weight)) return 0.0;
        foreach(self::parse_rates($method->get_option('rates', self::DEFAULT_RATES)) as [$max, $price]){
            if ($weight <= $max + 1e-9) return $price;
        }
        return null;
    }

    /** Díjtábla HTML a Szállítás és fizetés oldalra (a beállításokból, így mindig a mostani díjak látszanak). */
    static function rates_table(){
        $method = self::method();
        if (!$method) return '';
        $rows = self::parse_rates($method->get_option('rates', self::DEFAULT_RATES));
        if (empty($rows)) return '';
        $html = '<table class="vk-shipping-rates"><thead><tr><th>Csomag súlya</th><th>Szállítási díj (bruttó)</th></tr></thead><tbody>';
        foreach($rows as [$max, $price]){
            $html .= '<tr><td>'.esc_html(wc_format_localized_decimal($max).' kg-ig').'</td><td>'.wc_price($price).'</td></tr>';
        }
        $html .= '</tbody></table>';
        $free_total = (float)$method->get_option('free_total', '30000');
        $free_weight = (float)$method->get_option('free_weight', '10');
        $last = end($rows)[0];
        $notes = [];
        if ($free_total > 0) $notes[] = wc_price($free_total).' feletti rendelésnél'.($free_weight > 0 ? ' '.wc_format_localized_decimal($free_weight).' kg-ig' : '').' a házhozszállítás ingyenes.';
        $notes[] = wc_format_localized_decimal($last).' kg felett a szállítás módját és díját egyedileg egyeztetjük.';
        return $html.'<p class="iu-text vk-shipping-rates-note">'.implode(' ', $notes).'</p>';
    }
}
