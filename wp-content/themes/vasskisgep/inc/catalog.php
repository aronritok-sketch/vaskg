<?php

namespace VasskisgepTheme;

/**
 * Katalógus: szűrhető tulajdonságok (mértékegységgel), termékenkénti extra adatok
 * (promóciós szöveg, Cofidis, dokumentumok) és ezek szerkesztése a termékoldalon.
 */
class Catalog {
    private static $attributes;

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('woocommerce_product_options_general_product_data', [$this, 'product_fields']);
        add_action('woocommerce_admin_process_product_object', [$this, 'save_product_fields']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);

        // A keretrendszer tulajdonság-blokkja taxonómia-attribútumnál az azonosítót írná ki.
        add_filter('render_block_iu-woocommerce/product-attributes', [$this, 'render_attributes'], 10, 2);

        // Termékkártya a design szerint: promóciós szöveg a cím alatt, Cofidis jelvény a képen.
        add_filter('render_block_iu-woocommerce/loop-product-title', [$this, 'card_promo'], 10, 2);
        add_filter('render_block_iu-woocommerce/loop-product-image', [$this, 'card_cofidis'], 10, 2);
    }

    static function promo_html($product, $class = 'vk-promo', $tag = 'p'){
        $promo = self::promo($product);
        if ($promo === '') return '';
        $lines = array_filter(array_map('trim', preg_split('/\R/', $promo)));
        return '<'.$tag.' class="'.esc_attr($class).'">'.implode('<br>', array_map('esc_html', $lines)).'</'.$tag.'>';
    }

    static function cofidis_badge(){
        return '<span class="vk-cofidis-badge" title="Cofidis áruhitellel is megvásárolható"><svg width="14" height="14" viewBox="0 0 24 24" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3.2"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4M4.9 4.9l2.8 2.8M16.3 16.3l2.8 2.8M4.9 19.1l2.8-2.8M16.3 7.7l2.8-2.8"/></g></svg><span>Cofidis</span></span>';
    }

    function card_promo($content, $block){
        $product = wc_get_product();
        if (!$product) return $content;
        // A cím abszolút pozícionált a kártya tetején, ezért a promó a címen belülre kerül.
        $promo = self::promo_html($product, 'vk-promo vk-card-promo', 'span');
        if ($promo === '') return $content;
        $pos = strrpos($content, '</h4>');
        return $pos === false ? $content.$promo : substr_replace($content, $promo.'</h4>', $pos, 5);
    }

    function card_cofidis($content, $block){
        $product = wc_get_product();
        if (!$product || !self::has_cofidis($product)) return $content;
        $pos = strrpos($content, '</div>');
        return $pos === false ? $content.self::cofidis_badge() : substr_replace($content, self::cofidis_badge().'</div>', $pos, 6);
    }

    /** A szűrhető tulajdonságok: [slug => ['label' => …, 'unit' => …]] a megjelenítési sorrendben. */
    static function attributes(){
        if (is_null(self::$attributes)){
            $data = json_decode(Setup::content_file('catalog/categories.json'), true);
            self::$attributes = [];
            foreach(($data['attributes'] ?? []) as $attribute){
                self::$attributes[$attribute['slug']] = $attribute;
            }
        }
        return self::$attributes;
    }

    /**
     * A termék tulajdonságai megjelenítésre: [['slug', 'label', 'value', 'unit', 'text']].
     * Először a szűrhető tulajdonságok (a megadott sorrendben), utána a többi látható tulajdonság.
     */
    static function product_attributes($product){
        $known = self::attributes();
        $rows = [];
        $other = [];

        foreach($product->get_attributes() as $attribute){
            if (!$attribute->get_visible()) continue;
            if ($attribute->is_taxonomy()){
                $slug = preg_replace('/^pa_/', '', $attribute->get_name());
                $values = wc_get_product_terms($product->get_id(), $attribute->get_name(), [ 'fields' => 'names' ]);
                $label = isset($known[$slug]) ? $known[$slug]['label'] : wc_attribute_label($attribute->get_name());
            } else {
                $slug = sanitize_title($attribute->get_name());
                $values = $attribute->get_options();
                $label = $attribute->get_name();
            }
            if (empty($values)) continue;

            $unit = isset($known[$slug]) ? $known[$slug]['unit'] : '';
            $value = implode(', ', $values);
            $row = [
                'slug' => $slug,
                'label' => $label,
                'value' => $value,
                'unit' => $unit,
                'text' => $value.($unit !== '' && strpos($value, $unit) === false ? ' '.$unit : '')
            ];
            if (isset($known[$slug])) $rows[$slug] = $row;
            else $other[] = $row;
        }

        $ordered = [];
        foreach(array_keys($known) as $slug){
            if (isset($rows[$slug])) $ordered[] = $rows[$slug];
        }
        return array_merge($ordered, $other);
    }

    static function promo($product){
        return trim((string)$product->get_meta('_vk_promo'));
    }

    static function has_cofidis($product){
        return $product->get_meta('_vk_cofidis') === 'yes';
    }

    static function documents($product){
        $ids = array_filter(array_map('absint', explode(',', (string)$product->get_meta('_vk_documents'))));
        return array_values(array_filter($ids, function($id){ return get_post_type($id) === 'attachment'; }));
    }

    function render_attributes($content, $block){
        global $product;
        $current = $product instanceof \WC_Product ? $product : wc_get_product();
        if (!$current) return $content;

        $rows = self::product_attributes($current);
        $limit = is_product() && !in_the_loop() ? 6 : 3;
        $rows = array_slice($rows, 0, $limit);
        if (empty($rows)) return '';

        $html = '<div class="iu-woocommerce-product-attributes">';
        foreach($rows as $row){
            $html .= '<div class="iu-woocommerce-product-attribute '.esc_attr($row['slug']).'">'
                .'<span class="iu-woocommerce-product-attribute-name">'.esc_html($row['label']).'</span>'
                .'<span class="iu-woocommerce-product-attribute-value">'.wp_kses_post(self::format_unit($row['text'])).'</span>'
            .'</div>';
        }
        return $html.'</div>';
    }

    /** "cm³" → cm<sup>3</sup> (a keretrendszer stílusa ezt várja). */
    static function format_unit($text){
        return str_replace('³', '<sup>3</sup>', esc_html($text));
    }

    /* ---------- Szerkesztés az adminban ---------- */

    function product_fields(){
        global $product_object;
        echo '<div class="options_group">';
        woocommerce_wp_textarea_input([
            'id' => '_vk_promo',
            'label' => 'Promóciós szöveg',
            'description' => 'A termékkártyán és a termékképen jelenik meg (pl. „Ingyenes szállítás!”). Soronként egy.',
            'desc_tip' => true,
            'value' => $product_object ? $product_object->get_meta('_vk_promo') : ''
        ]);
        woocommerce_wp_checkbox([
            'id' => '_vk_cofidis',
            'label' => 'Cofidis áruhitel',
            'description' => 'Cofidis áruhitellel is megvásárolható (jelvény + kalkulátor a termékoldalon)',
            'value' => $product_object && $product_object->get_meta('_vk_cofidis') === 'yes' ? 'yes' : 'no'
        ]);
        woocommerce_wp_text_input([
            'id' => '_vk_documents',
            'label' => 'Dokumentumok',
            'description' => 'Letölthető dokumentumok (pl. használati utasítás PDF) a termékoldal Dokumentumok fülén.',
            'desc_tip' => true,
            'value' => $product_object ? $product_object->get_meta('_vk_documents') : '',
            'custom_attributes' => [ 'readonly' => 'readonly' ]
        ]);
        echo '<p class="form-field"><label></label><button type="button" class="button vk-documents-select">Dokumentumok kiválasztása</button> <button type="button" class="button-link vk-documents-clear">Törlés</button></p>';
        echo '</div>';
    }

    function save_product_fields($product){
        $product->update_meta_data('_vk_promo', isset($_POST['_vk_promo']) ? sanitize_textarea_field(wp_unslash($_POST['_vk_promo'])) : '');
        $product->update_meta_data('_vk_cofidis', !empty($_POST['_vk_cofidis']) ? 'yes' : 'no');
        $documents = isset($_POST['_vk_documents']) ? implode(',', array_filter(array_map('absint', explode(',', wp_unslash($_POST['_vk_documents']))))) : '';
        $product->update_meta_data('_vk_documents', $documents);
    }

    function admin_scripts($hook){
        if (!in_array($hook, ['post.php', 'post-new.php'], true) || get_post_type() !== 'product') return;
        wp_enqueue_media();
        wp_add_inline_script('jquery-core', "jQuery(function($){
            $(document).on('click', '.vk-documents-select', function(e){
                e.preventDefault();
                const frame = wp.media({ title: 'Dokumentumok', multiple: true, library: { type: ['application', 'text'] } });
                frame.on('select', function(){
                    $('#_vk_documents').val(frame.state().get('selection').map(function(a){ return a.id; }).join(','));
                });
                frame.open();
            });
            $(document).on('click', '.vk-documents-clear', function(e){ e.preventDefault(); $('#_vk_documents').val(''); });
        });");
    }
}
