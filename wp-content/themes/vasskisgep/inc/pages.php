<?php

namespace VasskisgepTheme;

/**
 * Oldal-szintű beállítások.
 *
 * „Saját oldalfejléc”: a keretrendszer minden oldalra ugyanazt a „Single Oldal” sablont
 * teszi (kiemelt kép + cím + morzsamenü). Ha az oldalon ez be van kapcsolva, a sablonból
 * csak az oldal tartalma marad, így az oldal a saját fejlécét hozhatja (pl. Rólunk).
 */
class Pages {
    const CUSTOM_HEADER = '_vk_custom_header';

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('init', [$this, 'register_meta']);
        add_action('add_meta_boxes_page', [$this, 'meta_box']);
        add_action('save_post_page', [$this, 'save'], 10, 2);
        add_filter('the_content', [$this, 'strip_template_header'], 0);
        add_filter('body_class', [$this, 'body_class']);
        add_filter('render_block', [$this, 'hide_disabled_payments'], 10, 2);
        add_action('wp_footer', [$this, 'arukereso_widget'], 99);
    }

    /**
     * A tartalomban (pl. Szállítás és fizetés oldal) a Barion-os listaelem / bekezdés csak akkor látszik, ha a Barion
     * fizetési mód be van kapcsolva (2026-11: az élesítés Barion nélkül indult; visszakapcsoláskor magától visszajön).
     */
    function hide_disabled_payments($content, $block){
        if (is_admin()) return $content;
        $content = (string)$content;
        $name = $block['blockName'] ?? '';
        // Lábléc alja, középen: az Árukereső jelvénye (ügyfélkérés, 2026-11: „alul középen”, mint a régi oldalon) –
        // a copyright sorban a bal oldali linkek és a jobb oldali © közé.
        if (strpos($content, 'Minden jog fenntartva') !== false && in_array($name, [ 'core/paragraph', 'iu/text' ], true)) return self::arukereso_badge().$content;
        $card = stripos($content, 'barion') !== false && in_array($name, [ 'core/list-item', 'iu/list-item', 'core/paragraph', 'iu/text' ], true);
        // Lábléc: „Elfogadott fizetési módok” és a kártyalogók csak online kártyás fizetésnél (Barion).
        if (strpos($content, 'Payment-methods-logos-container') !== false && strpos($name, 'image') !== false) $card = true;
        if (strpos($content, 'Elfogadott fizetési módok') !== false && in_array($name, [ 'core/paragraph', 'iu/text' ], true)) $card = true;
        if (!$card) return $content;
        return self::card_payment_enabled() ? $content : '';
    }

    static function card_payment_enabled(){
        static $enabled = null;
        if ($enabled === null){
            $enabled = false;
            if (function_exists('WC') && WC()->payment_gateways()){
                foreach(WC()->payment_gateways()->payment_gateways() as $gateway){
                    if (strpos($gateway->id, 'barion') === 0 && $gateway->enabled === 'yes') $enabled = true;
                }
            }
        }
        return $enabled;
    }

    /** Árukereső „Megbízható Bolt” jelvény (az Árukereső kódja, a bolt azonosítójával). */
    const ARUKERESO_KEY = '64c44dfc124163ee93d84b39212c4c04';
    const ARUKERESO_SHOP = '196224';

    static function arukereso_badge(){
        return '<!-- ÁRUKERESŐ.HU - PLEASE DO NOT MODIFY THE LINES BELOW -->'
            .'<div class="vk-arukereso-badge" style="background: transparent; text-align: center; padding: 0; margin: 0 auto;">'
            .'<a title="Árukereső.hu" href="https://www.arukereso.hu"><img src="https://image.arukereso.hu/trustedbadge/'.self::ARUKERESO_SHOP.'-100x100-'.self::ARUKERESO_KEY.'.png" alt="Árukereső.hu" style="border-style:none;" /></a>'
            .'</div>'
            .'<!-- ÁRUKERESŐ.HU CODE END -->';
    }

    /** Árukereső widget (lebegő „Megbízható Bolt” jelvény, bal alul) – az Árukereső kódja változatlanul. */
    function arukereso_widget(){
        if (is_admin()) return;
        echo "\n<!-- ÁRUKERESŐ.HU - PLEASE DO NOT MODIFY THE LINES BELOW -->\n"
            .'<script type="text/javascript">'."\n"
            .'if(ak_widget_params === undefined || ak_widget_script === undefined) {'."\n"
            .'var ak_widget_params = ["'.self::ARUKERESO_KEY.'","L","HU",1,"LB",0,480];'."\n"
            .'  var ak_widget_script = document.createElement("script");'."\n"
            .'  ak_widget_script.type = "text/javascript";'."\n"
            .'  ak_widget_script.src = "https://static.arukereso.hu/widget/presenter.js";'."\n"
            .'  ak_widget_script.async = true;'."\n"
            .'  document.body.appendChild(ak_widget_script);'."\n"
            .'}'."\n"
            .'</script>'."\n"
            ."<!-- ÁRUKERESŐ.HU CODE END -->\n";
    }

    static function has_custom_header($post_id = null){
        $post_id = $post_id ?: (is_page() ? get_queried_object_id() : 0);
        return $post_id && get_post_meta($post_id, self::CUSTOM_HEADER, true) === '1';
    }

    function register_meta(){
        register_post_meta('page', self::CUSTOM_HEADER, [ 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'auth_callback' => function(){ return current_user_can('edit_pages'); } ]);
    }

    function meta_box(){
        add_meta_box('vk-page-options', 'Oldal megjelenése', function($post){
            wp_nonce_field('vk_page_options', 'vk_page_options_nonce');
            echo '<label><input type="checkbox" name="vk_custom_header" value="1"'.checked(self::has_custom_header($post->ID), true, false).' /> Saját oldalfejléc (a sablon kiemelt képe, címe és morzsamenüje nélkül; a fejlécet az oldal tartalma adja)</label>';
        }, 'page', 'side');
    }

    function save($post_id, $post){
        if (!isset($_POST['vk_page_options_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vk_page_options_nonce'])), 'vk_page_options')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_page', $post_id)) return;
        if (!empty($_POST['vk_custom_header'])) update_post_meta($post_id, self::CUSTOM_HEADER, '1');
        else delete_post_meta($post_id, self::CUSTOM_HEADER);
    }

    /** A „Single Oldal” sablonból csak az oldal tartalma (iu/content) marad. */
    function strip_template_header($content){
        if (!is_page() || !self::has_custom_header()) return $content;
        if (strpos($content, '<!-- wp:iu/content /-->') === false || strpos($content, '<!-- wp:iu/title') === false) return $content;
        return '<!-- wp:iu/content /-->';
    }

    function body_class($classes){
        if (is_page() && self::has_custom_header()) $classes[] = 'vk-custom-header';
        return $classes;
    }
}
