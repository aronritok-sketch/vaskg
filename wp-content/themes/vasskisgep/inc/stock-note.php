<?php

namespace VasskisgepTheme;

/**
 * Készlet megjegyzés és várható beérkezés (Dávid, 2026-11-09; a régi webshopban a „Készlet” alá szabadon írható volt):
 *  – „Készlet megjegyzés”: szabad szöveg a termék Készlet fülén (pl. „3000 W, 230 V (1 fázis)”), a termékoldalon a
 *    készletinfó alatt látszik. A régi webshop „Elérhetőség” szövegei innen jönnek (beállító lépés 50).
 *  – „Várható beérkezés”: dátum; a termékoldalon „Várható beérkezés: 2026. november 23.”, és a dátum után magától eltűnik.
 *  – „Beérkező mennyiség”: darabszám a dátum mellé („… (5 db)”); csak tájékoztató, a készletet nem módosítja.
 */
class StockNote {
    const NOTE = '_vk_stock_note';
    const ARRIVAL = '_vk_arrival';
    const QTY = '_vk_arrival_qty';

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('woocommerce_product_options_stock_status', [$this, 'fields']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save']);
        add_filter('woocommerce_get_stock_html', [$this, 'stock_html'], 20, 2);
    }

    function fields(){
        echo '<div class="options_group">';
        woocommerce_wp_text_input([ 'id' => self::NOTE, 'label' => 'Készlet megjegyzés', 'placeholder' => 'pl. 3000 W, 230 V (1 fázis)', 'desc_tip' => true, 'description' => 'A termékoldalon a készletinfó alatt látszik.' ]);
        woocommerce_wp_text_input([ 'id' => self::ARRIVAL, 'label' => 'Várható beérkezés', 'type' => 'date', 'desc_tip' => true, 'description' => 'A termékoldalon „Várható beérkezés: …” – a dátum után magától eltűnik.' ]);
        woocommerce_wp_text_input([ 'id' => self::QTY, 'label' => 'Beérkező mennyiség (db)', 'type' => 'number', 'custom_attributes' => [ 'min' => 0, 'step' => 1 ], 'desc_tip' => true, 'description' => 'A beérkezés dátuma mellett látszik (pl. „5 db”). Csak tájékoztató: a készletet beérkezéskor a Készlet mezőben állítsd.' ]);
        echo '</div>';
    }

    function save($product){
        if (isset($_POST[self::NOTE])) $product->update_meta_data(self::NOTE, sanitize_text_field(wp_unslash($_POST[self::NOTE])));
        if (isset($_POST[self::ARRIVAL])){
            $date = sanitize_text_field(wp_unslash($_POST[self::ARRIVAL]));
            $product->update_meta_data(self::ARRIVAL, preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '');
        }
        if (isset($_POST[self::QTY])){
            $qty = (int)wp_unslash($_POST[self::QTY]);
            $product->update_meta_data(self::QTY, $qty > 0 ? (string)$qty : '');
        }
    }

    /** A még érvényes beérkezési dátum (Y-m-d), vagy ''. */
    static function arrival($product){
        $id = $product->get_parent_id() ?: $product->get_id();
        $date = (string)get_post_meta($id, self::ARRIVAL, true);
        if ($product->get_parent_id() && $own = (string)get_post_meta($product->get_id(), self::ARRIVAL, true)) $date = $own;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return '';
        return $date >= wp_date('Y-m-d') ? $date : '';
    }

    function stock_html($html, $product){
        if (!$product instanceof \WC_Product) return $html;
        $id = $product->get_parent_id() ?: $product->get_id();
        $note = trim((string)get_post_meta($id, self::NOTE, true));
        $arrival = self::arrival($product);
        if ($note === '' && $arrival === '') return $html;
        // Üres készletszövegnél az iu_woocommerce „Készleten” feliratot ír ki; a megjegyzés mellett is maradjon meg.
        if (trim(wp_strip_all_tags($html)) === '' && $product->is_in_stock()) $html = '<p class="stock in-stock">'.esc_html__('In stock', 'woocommerce').'</p>';
        if ($note !== '') $html .= '<p class="vk-stock-note">'.esc_html($note).'</p>';
        if ($arrival !== ''){
            $qty = (int)get_post_meta($id, self::QTY, true);
            if ($product->get_parent_id() && $own = (int)get_post_meta($product->get_id(), self::QTY, true)) $qty = $own;
            $html .= '<p class="vk-arrival">Várható beérkezés: <strong>'.esc_html(wp_date('Y. F j.', strtotime($arrival.' 12:00:00'))).'</strong>'.($qty > 0 ? ' <span class="vk-arrival-qty">('.esc_html($qty).' db)</span>' : '').'</p>';
        }
        return $html;
    }
}
