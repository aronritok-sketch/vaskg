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
    const DEFAULT_RATES = "10 | 2490\n15 | 2990\n20 | 3490\n30 | 3990\n40 | 4990\n50 | 8890\n60 | 9990\n80 | 9990\n100 | 11490\n300 | 19990\n99999 | 24990";
    /** E fölötti felső határ: „X kg felett” (a díjtábla utolsó sora, ha nincs felső súlyhatár). */
    const NO_LIMIT = 99999;
    /** Ingyenes szállítás a termékre (termék-meta) vagy a kategóriája termékeire (kategória-meta; Dávid, 2026-11-09). */
    const FREE = '_vk_free_shipping';
    const FREE_TERM = 'vk_free_shipping';

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
        // Ingyenes szállítás termékre / kategóriára.
        add_action('woocommerce_product_options_shipping', [ $this, 'product_field' ]);
        add_action('woocommerce_admin_process_product_object', [ $this, 'save_product_field' ]);
        add_action('product_cat_add_form_fields', [ $this, 'category_add_field' ]);
        add_action('product_cat_edit_form_fields', [ $this, 'category_edit_field' ]);
        add_action('created_product_cat', [ $this, 'save_category_field' ]);
        add_action('edited_product_cat', [ $this, 'save_category_field' ]);
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

    /** Ingyenes-e a házhozszállítás erre a termékre (a termék vagy valamelyik kategóriája, ill. annak szülője szerint). */
    static function is_free($product){
        if (!$product) return false;
        $id = $product->get_parent_id() ?: $product->get_id();
        if (get_post_meta($id, self::FREE, true) === 'yes') return true;
        static $terms = [];
        $cats = get_the_terms($id, 'product_cat');
        if (empty($cats) || is_wp_error($cats)) return false;
        foreach($cats as $cat){
            foreach(array_merge([ (int)$cat->term_id ], array_map('intval', get_ancestors($cat->term_id, 'product_cat'))) as $tid){
                if (!isset($terms[$tid])) $terms[$tid] = get_term_meta($tid, self::FREE_TERM, true) === 'yes';
                if ($terms[$tid]) return true;
            }
        }
        return false;
    }

    function product_field(){
        global $product_object;
        woocommerce_wp_checkbox([
            'id' => self::FREE,
            'label' => 'Ingyenes szállítás',
            'description' => 'Erre a termékre ingyenes a házhozszállítás (a súlya nem számít bele a szállítási díjba). Kategóriára: Termékek → Kategóriák.',
            'value' => $product_object && $product_object->get_meta(self::FREE) === 'yes' ? 'yes' : 'no',
        ]);
    }

    function save_product_field($product){
        $product->update_meta_data(self::FREE, !empty($_POST[self::FREE]) ? 'yes' : 'no');
    }

    function category_add_field(){
        echo '<div class="form-field"><label><input type="checkbox" name="'.esc_attr(self::FREE_TERM).'" value="yes"> Ingyenes szállítás</label><p>A kategória (és alkategóriái) minden termékére ingyenes a házhozszállítás.</p></div>';
    }

    function category_edit_field($term){
        $on = get_term_meta($term->term_id, self::FREE_TERM, true) === 'yes';
        echo '<tr class="form-field"><th scope="row">Ingyenes szállítás</th><td><label><input type="checkbox" name="'.esc_attr(self::FREE_TERM).'" value="yes"'.checked($on, true, false).'> A kategória (és alkategóriái) minden termékére ingyenes a házhozszállítás</label></td></tr>';
    }

    function save_category_field($term_id){
        if (!current_user_can('manage_product_terms') || !isset($_POST['_wpnonce'])) return;
        if (!empty($_POST[self::FREE_TERM])) update_term_meta($term_id, self::FREE_TERM, 'yes');
        else delete_term_meta($term_id, self::FREE_TERM);
    }

    /**
     * Diagnosztika (kulcsos státusz-végpont, diag=promo-free): a termékek, amelyek promóciós szövege ingyenes szállítást
     * ígér, a jelölésükkel és a súlyukkal; $set (termék-azonosítók) ezeket ingyenes szállításúnak jelöli.
     */
    static function promo_free_diag($set = []){
        global $wpdb;
        $set = array_flip(array_map('intval', (array)$set));
        $out = [];
        foreach($wpdb->get_results("SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'product' AND p.post_status = 'publish' WHERE pm.meta_key = '_vk_promo' AND pm.meta_value <> ''") as $row){
            $id = (int)$row->post_id;
            if (!preg_match('/ingyenes\s+szállítás/iu', (string)$row->meta_value)) continue;
            if (isset($set[$id]) && get_post_meta($id, self::FREE, true) !== 'yes'){ update_post_meta($id, self::FREE, 'yes'); wc_delete_product_transients($id); }
            $product = wc_get_product($id);
            $out[] = [ 'id' => $id, 'title' => get_the_title($id), 'kg' => $product ? self::product_weight($product) : 0, 'free' => get_post_meta($id, self::FREE, true) === 'yes' ];
        }
        if ($set && class_exists(__NAMESPACE__.'\\Feed')) Feed::flush();
        return $out;
    }

    /** A kosár (csomag) díjköteles súlya: az ingyenes szállítású termékek nélkül. [súly, van-e díjköteles tétel] */
    static function paid_weight($items){
        $weight = 0.0;
        $paid = false;
        foreach($items as $item){
            if (self::is_free($item['data'])) continue;
            $paid = true;
            $weight += self::product_weight($item['data']) * (float)$item['quantity'];
        }
        return [ $weight, $paid ];
    }

    function over_limit_notice(){
        $method = self::method();
        if (!$method || !WC()->cart) return;
        [$weight] = self::paid_weight(WC()->cart->get_cart());
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
        $prev = 0;
        foreach($rows as [$max, $price]){
            $label = $max >= self::NO_LIMIT ? wc_format_localized_decimal($prev).' kg felett' : wc_format_localized_decimal($max).' kg-ig';
            $html .= '<tr><td>'.esc_html($label).'</td><td>'.wc_price($price).'</td></tr>';
            $prev = $max;
        }
        $html .= '</tbody></table>';
        $free_total = (float)$method->get_option('free_total', '30000');
        $free_weight = (float)$method->get_option('free_weight', '10');
        $last = end($rows)[0];
        $notes = [];
        if ($free_total > 0) $notes[] = wc_price($free_total).' feletti rendelésnél'.($free_weight > 0 ? ' '.wc_format_localized_decimal($free_weight).' kg-ig' : '').' a házhozszállítás ingyenes.';
        if ($last < self::NO_LIMIT) $notes[] = wc_format_localized_decimal($last).' kg felett a szállítás módját és díját egyedileg egyeztetjük.';
        return $html.'<p class="iu-text vk-shipping-rates-note">'.implode(' ', $notes).'</p>';
    }
}
