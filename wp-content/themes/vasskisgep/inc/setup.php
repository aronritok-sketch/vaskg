<?php

namespace VasskisgepTheme;

/**
 * Egyszeri, verziózott telepítő lépések (beállítások, oldalak, sablonok, menü).
 *
 * A lépések az admin felület első betöltésekor futnak le (vagy WP-CLI-ből:
 * `wp vasskisgep setup`). Minden lépés csak egyszer fut; a lefutott verziót a
 * `vasskisgep_setup_version` opció tárolja. Új lépést a STEPS végére kell felvenni.
 *
 * A lépések idempotensek: meglévő tartalmat csak akkor írnak felül, ha az adott
 * lépés kifejezetten erre készült (pl. hibás link javítása).
 */
class Setup {
    const OPTION = 'vasskisgep_setup_version';
    const LOG_OPTION = 'vasskisgep_setup_log';

    const STEPS = [
        1 => 'store_settings',
        2 => 'tax_rates',
        3 => 'shipping_methods',
        4 => 'payment_methods',
        5 => 'product_taxonomies',
        6 => 'pages',
        7 => 'managed_content',
        8 => 'menu',
        9 => 'permalinks',
        10 => 'blog_posts',
        11 => 'email_branding',
        12 => 'demo_products',
        13 => 'product_page',
        14 => 'about_page',
        15 => 'about_design',
        16 => 'wc_pages_layout',
        17 => 'footer_design',
    ];

    private $log = [];

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('admin_init', [$this, 'maybe_run'], 20);
        add_action('admin_notices', [$this, 'notice']);

        if (defined('WP_CLI') && WP_CLI){
            \WP_CLI::add_command('vasskisgep setup', function($args, $assoc){
                if (isset($assoc['step'])){
                    $this->run_step((int)$assoc['step']);
                } else {
                    $this->run();
                }
                foreach($this->log as $line) \WP_CLI::log($line);
                \WP_CLI::success('Telepítő verzió: '.get_option(self::OPTION, 0));
            });
        }
    }

    static function latest_version(){
        return max(array_keys(self::STEPS));
    }

    function maybe_run(){
        if (!current_user_can('manage_options') || wp_doing_ajax()) return;
        if (defined('VASSKISGEP_SETUP_MANUAL') && VASSKISGEP_SETUP_MANUAL) return;
        if (!class_exists('WooCommerce')) return;
        if ((int)get_option(self::OPTION, 0) >= self::latest_version()) return;

        $this->run();
    }

    function run(){
        $current = (int)get_option(self::OPTION, 0);
        foreach(self::STEPS as $version => $method){
            if ($version <= $current) continue;
            $this->run_step($version);
            update_option(self::OPTION, $version);
        }
        if (!empty($this->log)){
            update_option(self::LOG_OPTION, $this->log, false);
        }
    }

    function run_step($version){
        // A tartalmak blokk-markupot tartalmaznak, amit a kses és a blokk-CSS
        // szűrő elrontana, ha nincs bejelentkezett adminisztrátor (WP-CLI).
        if (!current_user_can('unfiltered_html')){
            $admins = get_users([ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ]);
            if (!empty($admins)) wp_set_current_user((int)$admins[0]);
        }
        kses_remove_filters();
        remove_filter('content_save_pre', 'wp_strip_custom_css_from_blocks', 8);

        $method = self::STEPS[$version];
        $this->log[] = '— '.$version.'. lépés: '.$method;
        $this->$method();
    }

    function notice(){
        $log = get_option(self::LOG_OPTION);
        if (empty($log) || !current_user_can('manage_options')) return;
        delete_option(self::LOG_OPTION);

        echo '<div class="notice notice-success is-dismissible"><p><strong>Vasskisgép téma: a telepítő lépések lefutottak.</strong></p><ul style="list-style:disc;margin-left:1.5em">';
        foreach($log as $line) echo '<li>'.esc_html($line).'</li>';
        echo '</ul></div>';
    }

    /* ---------------------------------------------------------------------
     * Segédfüggvények
     * ------------------------------------------------------------------- */

    static function content_file($name){
        $file = get_stylesheet_directory().'/setup/'.$name;
        if (!file_exists($file)) return '';
        return self::expand(file_get_contents($file));
    }

    /**
     * Helyőrzők a setup/ tartalmakban:
     *   {{home}}                  – a webhely címe
     *   {{media:fajl.jpg}}        – a setup/media/fajl.jpg médiatárba töltött példányának URL-je
     *   {{media-thumb:fajl.jpg}}  – ugyanennek 150×150-es mérete
     *   {{media-id:fajl.jpg}}     – a csatolmány azonosítója
     * A képek az első hivatkozáskor kerülnek a médiatárba (egyszer).
     */
    static function expand($content){
        $content = str_replace('{{home}}', untrailingslashit(home_url()), $content);
        return preg_replace_callback('/\{\{media(-thumb|-id)?:([a-z0-9._-]+)\}\}/', function($m){
            $id = self::media($m[2]);
            if (!$id) return $m[1] === '-id' ? '0' : '';
            if ($m[1] === '-id') return (string)$id;
            if ($m[1] === '-thumb'){
                $thumb = wp_get_attachment_image_src($id, 'thumbnail');
                return $thumb ? $thumb[0] : wp_get_attachment_url($id);
            }
            return wp_get_attachment_url($id);
        }, $content);
    }

    /** A expand() fordítottja (a dev/canon.php használja a fájlba visszaíráskor). */
    static function collapse($content){
        foreach(get_posts([ 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => -1, 'meta_key' => '_vk_media_source' ]) as $post){
            $name = get_post_meta($post->ID, '_vk_media_source', true);
            $thumb = wp_get_attachment_image_src($post->ID, 'thumbnail');
            if ($thumb && $thumb[0] !== wp_get_attachment_url($post->ID)) $content = str_replace($thumb[0], '{{media-thumb:'.$name.'}}', $content);
            $content = str_replace(wp_get_attachment_url($post->ID), '{{media:'.$name.'}}', $content);
            $content = preg_replace('/("imageID":)'.$post->ID.'([,}])/', '$1{{media-id:'.$name.'}}$2', $content);
        }
        return str_replace(untrailingslashit(home_url()), '{{home}}', $content);
    }

    /** A setup/media/<fájl> médiatárbeli azonosítója; ha még nincs feltöltve, feltölti. */
    static function media($name){
        static $cache = [];
        if (isset($cache[$name])) return $cache[$name];

        $existing = get_posts([ 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_vk_media_source', 'meta_value' => $name ]);
        if (!empty($existing)) return $cache[$name] = (int)$existing[0];

        $source = get_stylesheet_directory().'/setup/media/'.$name;
        if (!file_exists($source)) return $cache[$name] = 0;

        require_once(ABSPATH.'wp-admin/includes/image.php');
        $upload = wp_upload_bits($name, null, file_get_contents($source));
        if (!empty($upload['error'])) return $cache[$name] = 0;

        $type = wp_check_filetype($upload['file']);
        $id = wp_insert_attachment([
            'post_mime_type' => $type['type'],
            'post_title' => preg_replace('/\.[^.]+$/', '', $name),
            'post_status' => 'inherit'
        ], $upload['file']);
        if (is_wp_error($id) || !$id) return $cache[$name] = 0;

        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
        update_post_meta($id, '_vk_media_source', $name);
        return $cache[$name] = (int)$id;
    }

    private function set_option($name, $value){
        update_option($name, $value);
        $this->log[] = 'Beállítás: '.$name;
    }

    /**
     * Oldal létrehozása, ha még nincs ilyen slugú oldal. Meglévő oldalt csak
     * akkor ír felül, ha $overwrite igaz, vagy ha az oldal tartalma üres.
     */
    function upsert_page($slug, $title, $content_file, $args = []){
        $args = wp_parse_args($args, [ 'overwrite' => false, 'parent' => 0, 'status' => 'publish' ]);
        $content = self::content_file($content_file);

        $page = get_page_by_path($slug, OBJECT, 'page');
        if (!$page){
            $trashed = get_posts([ 'post_type' => 'page', 'name' => $slug, 'post_status' => ['draft', 'pending', 'private', 'trash'], 'numberposts' => 1 ]);
            $page = !empty($trashed) ? $trashed[0] : null;
        }

        if ($page){
            $update = [ 'ID' => $page->ID ];
            if ($args['overwrite'] || trim($page->post_content) === '') $update['post_content'] = wp_slash($content);
            if ($page->post_status !== $args['status']) $update['post_status'] = $args['status'];
            if ($args['overwrite']) $update['post_title'] = $title;
            if (count($update) > 1){
                wp_update_post($update);
                $this->log[] = 'Oldal frissítve: '.$title;
            }
            return $page->ID;
        }

        $id = wp_insert_post([
            'post_type' => 'page',
            'post_status' => $args['status'],
            'post_name' => $slug,
            'post_title' => $title,
            'post_parent' => $args['parent'],
            'post_content' => wp_slash($content)
        ]);
        $this->log[] = 'Oldal létrehozva: '.$title;
        return $id;
    }

    private function ensure_term($name, $taxonomy, $slug, $parent = 0, $description = ''){
        $term = get_term_by('slug', $slug, $taxonomy);
        if ($term) return (int)$term->term_id;

        $result = wp_insert_term($name, $taxonomy, [ 'slug' => $slug, 'parent' => $parent, 'description' => $description ]);
        if (is_wp_error($result)) return 0;
        return (int)$result['term_id'];
    }

    private function attachment_by_name($name){
        $posts = get_posts([ 'post_type' => 'attachment', 'name' => $name, 'numberposts' => 1, 'post_status' => 'inherit' ]);
        return !empty($posts) ? $posts[0]->ID : 0;
    }

    /* ---------------------------------------------------------------------
     * Lépések
     * ------------------------------------------------------------------- */

    /** Bolt alapadatok, árak megjelenítése, e-mailek, pénztár. */
    function store_settings(){
        $settings = [
            'woocommerce_store_address' => 'Városmajor út 20.',
            'woocommerce_store_address_2' => '',
            'woocommerce_store_city' => 'Kisvárda',
            'woocommerce_store_postcode' => '4600',
            'woocommerce_default_country' => 'HU:SZ',
            'woocommerce_allowed_countries' => 'specific',
            'woocommerce_specific_allowed_countries' => ['HU'],
            'woocommerce_ship_to_countries' => '',
            'woocommerce_default_customer_address' => 'base',
            'woocommerce_currency' => 'HUF',
            'woocommerce_currency_pos' => 'right_space',
            'woocommerce_price_thousand_sep' => ' ',
            'woocommerce_price_decimal_sep' => ',',
            'woocommerce_price_num_decimals' => '0',
            'woocommerce_weight_unit' => 'kg',
            'woocommerce_dimension_unit' => 'cm',
            'woocommerce_email_from_name' => 'Vass Kisgép',
            'woocommerce_email_from_address' => 'info@vasskisgep.hu',
            'woocommerce_email_footer_text' => 'Vass Kisgép 2011 Kft. · 4600 Kisvárda, Városmajor út 20. · +36 30 289 5977 · info@vasskisgep.hu',
            'woocommerce_enable_guest_checkout' => 'yes',
            'woocommerce_enable_checkout_login_reminder' => 'yes',
            'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
            'woocommerce_enable_myaccount_registration' => 'yes',
            'woocommerce_registration_generate_password' => 'no',
            'woocommerce_hide_out_of_stock_items' => 'no',
            'woocommerce_notify_low_stock_amount' => '2',
            'woocommerce_cart_redirect_after_add' => 'no',
            'woocommerce_enable_ajax_add_to_cart' => 'yes',
            'woocommerce_checkout_highlight_required_fields' => 'yes',
            'woocommerce_checkout_company_field' => 'optional',
            'woocommerce_checkout_address_2_field' => 'optional',
            'woocommerce_checkout_phone_field' => 'required',
        ];
        foreach($settings as $name => $value) $this->set_option($name, $value);

        // Új rendelés értesítő az üzletnek.
        $new_order = get_option('woocommerce_new_order_settings', []);
        $new_order = is_array($new_order) ? $new_order : [];
        $new_order['enabled'] = 'yes';
        $new_order['recipient'] = 'info@vasskisgep.hu';
        $this->set_option('woocommerce_new_order_settings', $new_order);

        $this->set_option('admin_email_lifespan', time() + YEAR_IN_SECONDS);

        // Google cégprofil (a kezdőlap értékelés blokkja erre linkel, amíg nincs API kulcs).
        $theme = get_option('iu_theme', []);
        $theme = is_array($theme) ? $theme : [];
        if (empty($theme['google_profile_url'])){
            $theme['google_profile_url'] = 'https://www.google.com/maps/place/Vass+Kisg%C3%A9p+2011+Kft.+STIHL/@48.2265414,22.0686757,17z/data=!3m1!4b1!4m6!3m5!1s0x4738ecf10ed575d3:0x940dcbc45f60fc4e!8m2!3d48.2265379!4d22.0712506!16s%2Fg%2F1hhxr_n7_';
            $this->set_option('iu_theme', $theme);
        }
    }

    /** Bruttó árak, 27% ÁFA. */
    function tax_rates(){
        $this->set_option('woocommerce_calc_taxes', 'yes');
        $this->set_option('woocommerce_prices_include_tax', 'yes');
        $this->set_option('woocommerce_tax_based_on', 'base');
        $this->set_option('woocommerce_shipping_tax_class', '');
        $this->set_option('woocommerce_tax_round_at_subtotal', 'no');
        $this->set_option('woocommerce_tax_display_shop', 'incl');
        $this->set_option('woocommerce_tax_display_cart', 'incl');
        $this->set_option('woocommerce_price_display_suffix', '');
        $this->set_option('woocommerce_tax_total_display', 'single');

        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_country = %s AND tax_rate_class = ''",
            'HU'
        ));
        if (!$exists){
            \WC_Tax::_insert_tax_rate([
                'tax_rate_country' => 'HU',
                'tax_rate_state' => '',
                'tax_rate' => '27.0000',
                'tax_rate_name' => 'ÁFA',
                'tax_rate_priority' => 1,
                'tax_rate_compound' => 0,
                'tax_rate_shipping' => 1,
                'tax_rate_order' => 0,
                'tax_rate_class' => ''
            ]);
            $this->log[] = 'ÁFA-kulcs létrehozva: HU 27%';
        }
    }

    /**
     * Szállítás: házhozszállítás + személyes átvétel mindkét üzletben.
     * A blokkos pénztárhoz kötött "pickup_location" módot kikapcsoljuk, mert a
     * téma klasszikus (shortcode-os) pénztárat használ, ott nem jelenik meg.
     */
    function shipping_methods(){
        // A meglévő "Magyarország" zónához nem volt hely rendelve (így minden címre
        // illeszkedett); ha van ilyen, azt használjuk, és megkapja Magyarországot.
        $zone_id = 0;
        $empty_zone_id = 0;
        foreach(\WC_Shipping_Zones::get_zones() as $zone){
            if (empty($zone['zone_locations']) && !$empty_zone_id) $empty_zone_id = $zone['id'];
            foreach($zone['zone_locations'] as $location){
                if ($location->type === 'country' && $location->code === 'HU') $zone_id = $zone['id'];
            }
        }

        if ($zone_id){
            $zone = new \WC_Shipping_Zone($zone_id);
        } elseif ($empty_zone_id){
            $zone = new \WC_Shipping_Zone($empty_zone_id);
            $zone->set_zone_name('Magyarország');
            $zone->add_location('HU', 'country');
            $zone->save();
        } else {
            $zone = new \WC_Shipping_Zone();
            $zone->set_zone_name('Magyarország');
            $zone->add_location('HU', 'country');
            $zone->save();
        }

        $methods = $zone->get_shipping_methods();
        $has = function($id, $title = null) use ($methods){
            foreach($methods as $method){
                if ($method->id === $id && (is_null($title) || $method->get_title() === $title)) return $method;
            }
            return false;
        };

        $flat = $has('flat_rate');
        if (!$flat){
            $instance_id = $zone->add_shipping_method('flat_rate');
        } else {
            $instance_id = $flat->get_instance_id();
        }
        $flat_settings = get_option('woocommerce_flat_rate_'.$instance_id.'_settings', []);
        $flat_settings = wp_parse_args([ 'title' => 'Házhozszállítás futárszolgálattal', 'tax_status' => 'taxable' ], (array)$flat_settings);
        if (!isset($flat_settings['cost']) || $flat_settings['cost'] === '') $flat_settings['cost'] = '1000';
        update_option('woocommerce_flat_rate_'.$instance_id.'_settings', $flat_settings);
        $this->log[] = 'Szállítási mód: Házhozszállítás futárszolgálattal ('.$flat_settings['cost'].' Ft)';

        $stores = [
            'Személyes átvétel – Kisvárda, Városmajor út 20.',
            'Személyes átvétel – Vásárosnamény, Jókai M. út 51.',
        ];
        foreach($stores as $title){
            $pickup = $has('local_pickup', $title);
            $instance_id = $pickup ? $pickup->get_instance_id() : $zone->add_shipping_method('local_pickup');
            update_option('woocommerce_local_pickup_'.$instance_id.'_settings', [
                'title' => $title,
                'tax_status' => 'none',
                'cost' => ''
            ]);
            $this->log[] = 'Szállítási mód: '.$title;
        }

        $pickup_location = get_option('woocommerce_pickup_location_settings', []);
        if (is_array($pickup_location) && !empty($pickup_location)){
            $pickup_location['enabled'] = 'no';
            update_option('woocommerce_pickup_location_settings', $pickup_location);
        }

        \WC_Cache_Helper::invalidate_cache_group('shipping_zones');
        delete_transient('wc_shipping_method_count');
    }

    /**
     * Fizetés: utánvét / fizetés átvételkor mindig elérhető. A banki átutalás
     * csak akkor marad bekapcsolva, ha van megadott bankszámlaszám. A Barion
     * (kártyás fizetés) külön bővítmény, a POSKey megadása után kapcsolható be.
     */
    function payment_methods(){
        $cod = get_option('woocommerce_cod_settings', []);
        $cod = wp_parse_args([
            'enabled' => 'yes',
            'title' => 'Fizetés átvételkor',
            'description' => 'Házhozszállításnál a futárnak, személyes átvételnél az üzletben fizethet készpénzzel vagy bankkártyával.',
            'instructions' => 'A rendelés összegét átvételkor kell kifizetni (futárnál vagy az üzletben).',
            'enable_for_methods' => [],
            'enable_for_virtual' => 'yes'
        ], (array)$cod);
        update_option('woocommerce_cod_settings', $cod);
        $this->log[] = 'Fizetési mód: Fizetés átvételkor';

        $accounts = get_option('woocommerce_bacs_accounts', []);
        $has_account = false;
        foreach((array)$accounts as $account){
            if (!empty($account['account_number']) || !empty($account['iban'])) $has_account = true;
        }
        $bacs = get_option('woocommerce_bacs_settings', []);
        $bacs = wp_parse_args([
            'title' => 'Előre utalás',
            'description' => 'Fizetés banki átutalással. A közleményben tüntesse fel a rendelésszámot. A terméket a jóváírás után adjuk át vagy küldjük el.',
            'instructions' => 'Kérjük, a rendelés végösszegét az alábbi bankszámlára utalja, a közleményben a rendelésszámmal.'
        ], (array)$bacs);
        $bacs['enabled'] = $has_account ? 'yes' : 'no';
        update_option('woocommerce_bacs_settings', $bacs);
        $this->log[] = $has_account ? 'Fizetési mód: Előre utalás' : 'Előre utalás kikapcsolva (nincs megadva bankszámlaszám – WooCommerce → Beállítások → Fizetés → Előre utalás)';

        update_option('woocommerce_gateway_order', [ 'barion' => 0, 'cod' => 1, 'bacs' => 2, 'cheque' => 3 ]);
    }

    /**
     * Termékkategória-fa (a Figma megamenüje alapján: fül → oszlop → csoport → kategória),
     * szűrhető tulajdonságok (globális attribútumok), márkák, évszak címkék.
     */
    function product_taxonomies(){
        $catalog = json_decode(self::content_file('catalog/categories.json'), true);

        $create = function($nodes, $parent) use (&$create){
            $order = 0;
            foreach($nodes as $node){
                [$name, $children] = is_array($node) ? $node : [$node, []];
                $existing = get_terms([ 'taxonomy' => 'product_cat', 'name' => $name, 'parent' => $parent, 'hide_empty' => false, 'number' => 1 ]);
                if (!empty($existing) && !is_wp_error($existing)){
                    $term_id = $existing[0]->term_id;
                } else {
                    $result = wp_insert_term($name, 'product_cat', [ 'parent' => $parent ]);
                    if (is_wp_error($result)) continue;
                    $term_id = $result['term_id'];
                }
                update_term_meta($term_id, 'order', $order++);
                if (!empty($children)) $create($children, $term_id);
            }
        };
        $create($catalog['tree'], 0);
        delete_option('product_cat_children');
        $this->log[] = 'Termékkategóriák létrehozva (megamenü szerinti fa)';

        foreach($catalog['attributes'] as $attribute){
            if (!wc_attribute_taxonomy_id_by_name($attribute['slug'])){
                wc_create_attribute([
                    'name' => $attribute['label'],
                    'slug' => $attribute['slug'],
                    'type' => 'select',
                    'order_by' => 'name_num',
                    'has_archives' => false
                ]);
                $this->log[] = 'Tulajdonság: '.$attribute['label'].' ('.$attribute['unit'].')';
            }
        }
        delete_transient('wc_attribute_taxonomies');

        $other = get_term_by('slug', 'egyeb', 'product_cat');

        if (taxonomy_exists('product_brand')){
            $brands = [
                'Stihl' => [ 'stihl', 'stihl-logo', 'A STIHL gépei jó szervizháttérrel, széles alkatrészellátással és hosszú távra tervezett, kiforrott konstrukciókkal készülnek.' ],
                'Stiga' => [ 'stiga', 'stiga-logo', 'A STIGA ePower rendszere ugyanazt az akkumulátort több géphez használja, így a gyepápolás egyetlen platformon megoldható.' ],
                'Cedrus' => [ 'cedrus', 'cedrus-logo', 'A CEDRUS a széles gépkínálat szinonimája: fűnyírótól és talajmarótól az áramfejlesztőig sokféle feladat is lefedhető egy márkával.' ],
                'Bluebird' => [ 'bluebird', 'bluebird-logo', 'A BLUEBIRD fő erőssége a metszéstechnika: könnyű akkumulátoros ollói, ágvágói szőlőben, gyümölcsösben is pontos munkát eredményeznek.' ],
                'Rato' => [ 'rato', 'rato-logo', 'A RATO saját motorokra épülő kapálógépeket és áramfejlesztőket kínál, ezért erős, tartós energiaforrást igénylő munkákhoz ideális választás.' ],
                'Kasei' => [ 'kasei', 'kasei-logo', 'A KASEI fűkaszákban, permetezőkben és földfúrókban kínál gépeket, bozótirtáshoz és növényvédelemhez is célszerű.' ],
            ];
            foreach($brands as $name => [$slug, $logo, $description]){
                $term_id = $this->ensure_term($name, 'product_brand', $slug, 0, $description);
                if (!$term_id) continue;
                if (!get_term_meta($term_id, 'thumbnail_id', true)){
                    $logo_id = $this->attachment_by_name($logo);
                    if ($logo_id) update_term_meta($term_id, 'thumbnail_id', $logo_id);
                }
                if (get_term($term_id)->description === ''){
                    wp_update_term($term_id, 'product_brand', [ 'description' => $description ]);
                }
            }
            $this->log[] = 'Márkák létrehozva';
        }

        // Évszak címkék a kezdőlap szezonális karusszeléhez.
        foreach(Shop::SEASONS as $slug => $name){
            $this->ensure_term($name, 'product_tag', $slug);
        }
        $this->log[] = 'Évszak címkék: Tavasz, Nyár, Ősz, Tél (a termékeknél címkeként adhatók meg)';

        delete_transient('wc_term_counts');
    }

    /** Hiányzó oldalak és a jogi oldalak. */
    function pages(){
        $pages = [
            [ 'rolunk', 'Rólunk', 'pages/rolunk.html' ],
            [ 'szolgaltatasok', 'Szolgáltatások', 'pages/szolgaltatasok.html' ],
            [ 'ajanlatkeres', 'Ajánlatkérés', 'pages/ajanlatkeres.html' ],
            [ 'szerviz', 'Szerviz', 'pages/szerviz.html' ],
            [ 'kapcsolat', 'Kapcsolat', 'pages/kapcsolat.html' ],
            [ 'kedvencek', 'Kedvencek', 'pages/kedvencek.html' ],
            [ 'osszehasonlitas', 'Összehasonlítás', 'pages/osszehasonlitas.html' ],
            [ 'szallitas-es-fizetes', 'Szállítás és fizetés', 'pages/szallitas-es-fizetes.html' ],
            [ 'aszf', 'Általános Szerződési Feltételek', 'pages/aszf.html' ],
        ];
        foreach($pages as [$slug, $title, $file]){
            $this->upsert_page($slug, $title, $file);
        }

        // A WordPress/WooCommerce angol mintaszövegei helyére magyar jogi szövegek.
        $this->upsert_page('adatkezelesi-tajekoztato', 'Adatkezelési tájékoztató', 'pages/adatkezelesi-tajekoztato.html', [ 'overwrite' => true ]);
        $returns = get_posts([ 'post_type' => 'page', 'name' => 'elallas_visszakuldes', 'post_status' => 'any', 'numberposts' => 1 ]);
        if (!empty($returns)){
            wp_update_post([
                'ID' => $returns[0]->ID,
                'post_name' => 'elallas-es-visszakuldes',
                'post_title' => 'Elállás és visszaküldés',
                'post_status' => 'publish',
                'post_content' => wp_slash(self::content_file('pages/elallas-es-visszakuldes.html'))
            ]);
            $this->log[] = 'Oldal frissítve: Elállás és visszaküldés';
        } else {
            $this->upsert_page('elallas-es-visszakuldes', 'Elállás és visszaküldés', 'pages/elallas-es-visszakuldes.html');
        }

        $terms = get_page_by_path('aszf');
        if ($terms) $this->set_option('woocommerce_terms_page_id', $terms->ID);
        $privacy = get_page_by_path('adatkezelesi-tajekoztato');
        if ($privacy) $this->set_option('wp_page_for_privacy_policy', $privacy->ID);

        $this->set_option('woocommerce_checkout_terms_and_conditions_checkbox_text', 'Elolvastam és elfogadom az [terms] és az [privacy_policy]');
        $this->set_option('woocommerce_checkout_privacy_policy_text', 'Személyes adatait a rendelés teljesítéséhez és a vásárlási élmény biztosításához használjuk, az [privacy_policy] szerint.');
        $this->set_option('woocommerce_registration_privacy_policy_text', 'Személyes adatait a fiókja kezeléséhez használjuk, az [privacy_policy] szerint.');

        // A Tudástár (bejegyzések oldala) bevezető szöveget kap, ha üres.
        $blog = (int)get_option('page_for_posts');
        if ($blog && trim(get_post_field('post_content', $blog)) === ''){
            wp_update_post([ 'ID' => $blog, 'post_excerpt' => 'Kertészeti, erdészeti és építőipari gépekről: választási tanácsok, karbantartás, szezonális teendők.' ]);
        }
    }

    /**
     * Meglévő, DB-ben tárolt tartalmak (fejléc, lábléc, kezdőlap, terméklista
     * kártya, oldalsablon) cseréje a setup/managed/ mappában lévő változatra.
     *
     * Csak akkor ír felül, ha a jelenlegi tartalom megegyezik azzal, amiből a
     * módosítás készült (a 2026-09-25-i mentés), így a közben kézzel szerkesztett
     * tartalom nem vész el – ilyenkor csak figyelmeztet.
     */
    function managed_content(){
        $home = untrailingslashit(home_url());
        $manifest = json_decode(self::content_file('managed/manifest.json'), true);
        if (empty($manifest)) return;

        foreach($manifest as $entry){
            $post = get_post($entry['id']);
            if (!$post || $post->post_type !== $entry['post_type']){
                $this->log[] = 'Kihagyva (nem található): '.$entry['file'];
                continue;
            }

            $new = self::content_file('managed/'.$entry['file']);
            $current = str_replace($home, '{{home}}', $post->post_content);
            $current_hash = md5($current);

            if (trim($post->post_content) === trim($new)) continue;
            if ($current_hash !== $entry['original_md5']){
                $this->log[] = 'FIGYELEM: "'.$post->post_title.'" a mentés óta módosult, nem írtam felül. Az új változat: themes/vasskisgep/setup/managed/'.$entry['file'];
                continue;
            }

            wp_update_post([ 'ID' => $post->ID, 'post_content' => wp_slash($new) ]);
            $this->log[] = 'Tartalom frissítve: '.$post->post_title;
        }
    }

    /** Főmenü: valós célok, a Termékek alatt a főkategóriák. */
    function menu(){
        $menu = wp_get_nav_menu_object('fomenu');
        if (!$menu) return;

        $home = untrailingslashit(home_url());
        $targets = [
            'Rólunk' => 'rolunk',
            'Szolgáltatások' => 'szolgaltatasok',
            'Ajánlatkérés' => 'ajanlatkeres',
            'Szervíz' => 'szerviz',
            'Szerviz' => 'szerviz',
            'Kapcsolat' => 'kapcsolat',
        ];

        $items = wp_get_nav_menu_items($menu->term_id, [ 'post_status' => 'any' ]);
        $products_item = 0;
        foreach($items as $item){
            if ($item->object === 'page' && (int)$item->object_id === (int)wc_get_page_id('shop')){
                $products_item = $item->ID;
                continue;
            }
            if ($item->type !== 'custom' || $item->url !== '#') continue;

            if ($item->title === 'Tudástár'){
                $this->update_menu_item_to_page($menu->term_id, $item, (int)get_option('page_for_posts'));
                continue;
            }
            if ($item->title === 'Kategória'){
                wp_delete_post($item->ID, true);
                continue;
            }
            if (isset($targets[$item->title])){
                $page = get_page_by_path($targets[$item->title]);
                if ($page) $this->update_menu_item_to_page($menu->term_id, $item, $page->ID, $item->title === 'Szervíz' ? 'Szerviz' : $item->title);
            }
        }

        if ($products_item){
            $position = 0;
            $parents = get_terms([ 'taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => false, 'orderby' => 'meta_value_num', 'meta_key' => 'order' ]);
            foreach($parents as $term){
                if ($term->slug === 'egyeb') continue;
                $exists = false;
                foreach($items as $item){
                    if ($item->object === 'product_cat' && (int)$item->object_id === $term->term_id) $exists = true;
                }
                if ($exists) continue;

                wp_update_nav_menu_item($menu->term_id, 0, [
                    'menu-item-object-id' => $term->term_id,
                    'menu-item-object' => 'product_cat',
                    'menu-item-type' => 'taxonomy',
                    'menu-item-parent-id' => $products_item,
                    'menu-item-position' => ++$position,
                    'menu-item-status' => 'publish'
                ]);
            }
            $this->log[] = 'Főmenü: Termékek almenü a főkategóriákkal';
        }
        $this->log[] = 'Főmenü: linkek beállítva';
    }

    private function update_menu_item_to_page($menu_id, $item, $page_id, $title = null){
        if (!$page_id) return;
        wp_update_nav_menu_item($menu_id, $item->ID, [
            'menu-item-object-id' => $page_id,
            'menu-item-object' => 'page',
            'menu-item-type' => 'post_type',
            'menu-item-title' => $title ?? $item->title,
            'menu-item-parent-id' => $item->menu_item_parent,
            'menu-item-position' => $item->menu_order,
            'menu-item-status' => 'publish'
        ]);
    }

    /**
     * Tudástár: a mentésben lévő "Lorem ipsum dolor" helyőrző bejegyzések helyére
     * valódi cikkek (csak ha a bejegyzés még a helyőrző címet viseli).
     */
    function blog_posts(){
        $data = json_decode(self::content_file('posts/posts.json'), true);
        if (empty($data)) return;

        $topics = [];
        foreach($data['topics'] as $slug => $name){
            $topics[$slug] = $this->ensure_term($name, 'category', $slug);
        }

        foreach($data['posts'] as $item){
            $post = get_post($item['id']);
            if (!$post || $post->post_type !== 'post' || $post->post_title !== 'Lorem ipsum dolor') continue;

            wp_update_post([
                'ID' => $post->ID,
                'post_title' => $item['title'],
                'post_name' => $item['slug'],
                'post_excerpt' => $item['excerpt'],
                'post_content' => wp_slash(self::content_file('posts/'.$item['slug'].'.html')),
                'post_status' => 'publish'
            ]);
            if (!empty($topics[$item['topic']])) wp_set_post_categories($post->ID, [ $topics[$item['topic']] ]);

            $image = $this->attachment_by_name($item['image']);
            if ($image) set_post_thumbnail($post->ID, $image);

            $this->log[] = 'Tudástár cikk: '.$item['title'];
        }

        // A helyőrző "Kategória" téma üresen maradt.
        $placeholder = get_term_by('slug', 'kategoria', 'category');
        if ($placeholder && $placeholder->name === 'Kategória'){
            $placeholder = get_term($placeholder->term_id, 'category');
            if ((int)$placeholder->count === 0) wp_delete_term($placeholder->term_id, 'category');
        }
    }

    /**
     * Bemutató termékek (SKU: DEMO-xxx) a webshop teszteléséhez. A korábbi,
     * egyforma "TESZT001" termékek piszkozatba kerülnek. Élesítés előtt a DEMO
     * termékek törlendők (Termékek → keresés: DEMO-).
     */
    function demo_products(){
        $products = json_decode(self::content_file('catalog/demo-products.json'), true);
        if (empty($products)) return;

        // Az újonnan létrehozott attribútum-taxonómiák ebben a kérésben még nincsenek regisztrálva.
        foreach(wc_get_attribute_taxonomies() as $tax){
            $taxonomy = wc_attribute_taxonomy_name($tax->attribute_name);
            if (!taxonomy_exists($taxonomy)) register_taxonomy($taxonomy, 'product', [ 'hierarchical' => false, 'public' => false ]);
        }

        foreach(get_posts([ 'post_type' => 'product', 'numberposts' => -1, 'post_status' => 'publish' ]) as $post){
            $sku = get_post_meta($post->ID, '_sku', true);
            if (strpos($sku, 'TESZT001') === 0){
                wp_update_post([ 'ID' => $post->ID, 'post_status' => 'draft' ]);
            }
        }

        $ids_by_sku = [];
        foreach($products as $item){
            $id = wc_get_product_id_by_sku($item['sku']);
            $product = $id ? wc_get_product($id) : new \WC_Product_Simple();

            $product->set_name($item['name']);
            $product->set_sku($item['sku']);
            $product->set_status('publish');
            $product->set_regular_price((string)$item['regular_price']);
            $product->set_sale_price($item['sale_price'] ? (string)$item['sale_price'] : '');
            $product->set_short_description($item['short_description']);
            $product->set_description($item['description']);
            $product->set_featured(!empty($item['featured']));
            $product->set_weight((string)$item['weight']);

            [$status, $qty] = array_pad(explode(':', $item['stock']), 2, null);
            if ($status === 'instock'){
                $product->set_manage_stock(true);
                $product->set_stock_quantity((int)$qty);
                $product->set_backorders('no');
            } elseif ($status === 'backorder'){
                $product->set_manage_stock(true);
                $product->set_stock_quantity(0);
                $product->set_backorders('notify');
            } else {
                $product->set_manage_stock(true);
                $product->set_stock_quantity(0);
                $product->set_backorders('no');
            }

            $category = get_terms([ 'taxonomy' => 'product_cat', 'name' => $item['category'], 'hide_empty' => false, 'number' => 1 ]);
            if (!empty($category) && !is_wp_error($category)) $product->set_category_ids([ $category[0]->term_id ]);

            $tags = [];
            foreach($item['seasons'] as $season){
                $tag = get_term_by('slug', $season, 'product_tag');
                if ($tag) $tags[] = $tag->term_id;
            }
            $product->set_tag_ids($tags);

            if (!empty($item['image'])){
                $image = $this->attachment_by_name($item['image']);
                if ($image) $product->set_image_id($image);
            }

            $attributes = [];
            $attribute_terms = [];
            $position = 0;
            foreach($item['attributes'] as $slug => $value){
                $taxonomy = wc_attribute_taxonomy_name($slug);
                $term = get_term_by('name', $value, $taxonomy);
                if (!$term){
                    // Tizedes értéknél a vessző helyett kötőjel a slugban („0,9” → 0-9, nem 09).
                    $result = wp_insert_term($value, $taxonomy, [ 'slug' => sanitize_title(str_replace([',', '.'], '-', $value)) ]);
                    if (is_wp_error($result)) continue;
                    $term = get_term($result['term_id'], $taxonomy);
                }
                $attribute = new \WC_Product_Attribute();
                $attribute->set_id(wc_attribute_taxonomy_id_by_name($slug));
                $attribute->set_name($taxonomy);
                $attribute->set_options([ $term->term_id ]);
                $attribute_terms[$taxonomy] = (int)$term->term_id;
                $attribute->set_position($position++);
                $attribute->set_visible(true);
                $attribute->set_variation(false);
                $attributes[] = $attribute;
            }
            $product->set_attributes($attributes);

            $product->update_meta_data('_vk_promo', $item['promo']);
            $product->update_meta_data('_vk_cofidis', $item['cofidis'] ? 'yes' : 'no');
            $id = $product->save();

            // Az attribútum-kifejezések kapcsolata (a set_attributes csak a termék-metát írja).
            foreach($attribute_terms as $taxonomy => $term_id){
                wp_set_object_terms($id, [ $term_id ], $taxonomy);
            }
            if (!empty($item['brand'])) wp_set_object_terms($id, $item['brand'], 'product_brand');

            $ids_by_sku[$item['sku']] = $id;
        }

        foreach($products as $item){
            if (empty($item['cross_sells'])) continue;
            $product = wc_get_product($ids_by_sku[$item['sku']]);
            $product->set_cross_sell_ids(array_values(array_filter(array_map(function($sku) use ($ids_by_sku){ return $ids_by_sku[$sku] ?? 0; }, $item['cross_sells']))));
            $product->save();
        }

        wc_delete_product_transients();
        delete_transient('wc_term_counts');
        $this->log[] = 'Bemutató termékek: '.count($products).' db (DEMO-001…), a TESZT001 termékek piszkozatba kerültek';
    }

    /** Rendelési e-mailek a webshop színeivel. */
    function email_branding(){
        $this->set_option('woocommerce_email_base_color', '#F36517');
        $this->set_option('woocommerce_email_background_color', '#F3F4F6');
        $this->set_option('woocommerce_email_body_background_color', '#FFFFFF');
        $this->set_option('woocommerce_email_text_color', '#1A1A1A');
        $this->set_option('woocommerce_email_footer_text_color', '#6B7280');
    }

    /** Tudástár cikkek URL-je: /tudastar/cikk-neve/ (dátum nélkül). */
    function permalinks(){
        global $wp_rewrite;
        $wp_rewrite->set_permalink_structure('/tudastar/%postname%/');
        update_option('category_base', 'tudastar/tema');
        update_option('tag_base', 'tudastar/cimke');
        flush_rewrite_rules(false);
        $this->log[] = 'Permalinkek: /tudastar/%postname%/';
    }

    /**
     * Termékoldal: értékelések bekapcsolása minden terméken, a bemutató gépeknél
     * tájékoztató doboz és néhány minta értékelés (a DEMO termékekkel együtt törlendők).
     */
    function product_page(){
        global $wpdb;
        update_option('woocommerce_enable_reviews', 'yes');
        update_option('woocommerce_enable_review_rating', 'yes');
        update_option('woocommerce_review_rating_required', 'yes');
        $opened = $wpdb->query("UPDATE {$wpdb->posts} SET comment_status = 'open' WHERE post_type = 'product' AND comment_status <> 'open'");
        $this->log[] = 'Értékelések bekapcsolva ('.(int)$opened.' termék)';

        $notice = "Személyes átvétel esetén az ár tartalmazza a gép összeszerelését és beüzemelését.\nMotorolaj nélkül szállítva.";
        $reviews = [
            [ 5, 'Gyorsan megérkezett, a gép elsőre indult. Az üzletben mindent elmagyaráztak.' ],
            [ 4, 'Jó ár-érték arány, a kezelése egyszerű. A fűgyűjtő lehetne nagyobb.' ],
            [ 5, 'Harmadik szezonja használom gond nélkül, a szerviz is gyors volt.' ]
        ];

        foreach(get_posts([ 'post_type' => 'product', 'numberposts' => -1, 'post_status' => 'any', 'meta_key' => '_sku', 'meta_value' => 'DEMO-', 'meta_compare' => 'LIKE' ]) as $post){
            $product = wc_get_product($post->ID);
            if (!$product) continue;

            $engine = wc_get_product_terms($post->ID, 'pa_loketterfogat', [ 'fields' => 'ids' ]);
            if (!empty($engine) && $product->get_meta('_vk_notice') === ''){
                $product->update_meta_data('_vk_notice', $notice);
                $product->save();
            }

            // Minta értékelések a kiemelt bemutató termékekre.
            if (!$product->is_featured() || get_comments([ 'post_id' => $post->ID, 'type' => 'review', 'count' => true ])) continue;
            foreach($reviews as $index => [$rating, $text]){
                $comment_id = wp_insert_comment([
                    'comment_post_ID' => $post->ID,
                    'comment_author' => 'Teszt vásárló '.($index + 1),
                    'comment_author_email' => 'teszt'.($index + 1).'@example.com',
                    'comment_content' => $text,
                    'comment_type' => 'review',
                    'comment_approved' => 1,
                    'comment_date' => gmdate('Y-m-d H:i:s', time() - ($index + 1) * 20 * DAY_IN_SECONDS)
                ]);
                if ($comment_id) add_comment_meta($comment_id, 'rating', $rating);
            }
            \WC_Comments::clear_transients($post->ID);
            $this->log[] = 'Minta értékelések: '.$product->get_name();
        }
    }

    /**
     * Rólunk oldal a design szerint. Csak akkor íródik felül, ha a tartalom még a
     * korábbi telepítői változat (szerkesztés után nem nyúlunk hozzá).
     */
    function about_page(){
        $previous = [ '00fa5715e0cfd908e77557d47087b1a3' ];
        $page = get_page_by_path('rolunk');
        if (!$page){
            $this->upsert_page('rolunk', 'Rólunk', 'pages/rolunk.html');
            $page = get_page_by_path('rolunk');
        } else {
            $current = md5(str_replace(untrailingslashit(home_url()), '{{home}}', $page->post_content));
            if (trim($page->post_content) === '' || in_array($current, $previous, true)){
                wp_update_post([ 'ID' => $page->ID, 'post_content' => wp_slash(self::content_file('pages/rolunk.html')) ]);
                $this->log[] = 'Oldal frissítve: Rólunk (design szerint)';
            } else {
                $this->log[] = 'Rólunk: a tartalom szerkesztve lett, nem íródik felül (setup/pages/rolunk.html)';
            }
        }
        if ($page && !get_post_thumbnail_id($page->ID)){
            $image = $this->attachment_by_name('stihl-termekek');
            if ($image) set_post_thumbnail($page->ID, $image);
        }
    }

    /**
     * Rólunk oldal pontosan a Figma szerint: saját fejléc (hero fotó + nagy cím), a terv
     * fotói és márkalogói (setup/media → médiatár). Csak a korábbi telepítői változatot írja felül.
     */
    function about_design(){
        $previous = [ '00fa5715e0cfd908e77557d47087b1a3', 'f575d706e38ace6d064bea78dfeaa308' ];
        $page = get_page_by_path('rolunk');
        if (!$page){
            $this->upsert_page('rolunk', 'Rólunk', 'pages/rolunk.html');
            $page = get_page_by_path('rolunk');
        } else {
            $current = md5(self::collapse($page->post_content));
            if (trim($page->post_content) === '' || in_array($current, $previous, true)){
                wp_update_post([ 'ID' => $page->ID, 'post_content' => wp_slash(self::content_file('pages/rolunk.html')) ]);
                $this->log[] = 'Oldal frissítve: Rólunk (Figma szerinti fotókkal, logókkal)';
            } else {
                $this->log[] = 'FIGYELEM: a Rólunk oldalt szerkesztették, nem íródik felül – az új változat: setup/pages/rolunk.html';
                return;
            }
        }
        if (!$page) return;
        update_post_meta($page->ID, Pages::CUSTOM_HEADER, '1');
        $hero = self::media('rolunk-hero.jpg');
        if ($hero) set_post_thumbnail($page->ID, $hero);
    }

    /**
     * WooCommerce oldalak: a Fiókom oldal (belépés, jelszó-visszaállítás, profil) shortcode-ja
     * iu/section → iu/row → iu/column keretbe kerül (mint a kosár és a pénztár); a Termékek
     * oldal régi szűrője (iu-woocommerce/filter + [products]) helyére az új szűrős lista.
     */
    function wc_pages_layout(){
        $wrap = function($shortcode){
            return "<!-- wp:iu/section -->\n<section class=\"iu-section\"><!-- wp:iu/row -->\n<div class=\"iu-row\"><!-- wp:iu/column -->\n<div class=\"iu-column iu-column-1-1\"><!-- wp:shortcode -->\n".$shortcode."\n<!-- /wp:shortcode --></div>\n<!-- /wp:iu/column --></div>\n<!-- /wp:iu/row --></section>\n<!-- /wp:iu/section -->";
        };
        foreach([ 'myaccount' => '[woocommerce_my_account]', 'cart' => '[woocommerce_cart]', 'checkout' => '[woocommerce_checkout]' ] as $key => $shortcode){
            $id = wc_get_page_id($key);
            if ($id <= 0) continue;
            $content = get_post_field('post_content', $id);
            if (strpos($content, 'wp:iu/section') !== false || strpos($content, $shortcode) === false) continue;
            wp_update_post([ 'ID' => $id, 'post_content' => wp_slash($wrap($shortcode)) ]);
            $this->log[] = 'Oldal keretezve (iu/section): '.get_the_title($id);
        }

        $shop = wc_get_page_id('shop');
        if ($shop > 0){
            $content = get_post_field('post_content', $shop);
            if (strpos($content, 'wp:iu-woocommerce/filter') !== false && strpos($content, 'vasskisgep/product-listing') === false){
                wp_update_post([ 'ID' => $shop, 'post_content' => wp_slash(self::content_file('managed/termekek.html')) ]);
                $this->log[] = 'Termékek oldal: a régi szűrő helyett az új szűrős terméklista';
            }
        }
    }

    /** Lábléc szövegei a design szerint („© 2026 Provision…”, „Adatvédelmi tájékoztató”), ha nem szerkesztették. */
    function footer_design(){
        $known = [ '73e37ba18a47b2f1b950cd84a228fbf3', '70e3d9415290791a68f4d11e133703f0' ];
        $footer = get_post(30);
        if (!$footer || $footer->post_type !== 'iu_template'){
            $found = get_posts([ 'post_type' => 'iu_template', 'title' => 'Footer', 'post_status' => 'any', 'numberposts' => 1 ]);
            $footer = $found ? $found[0] : null;
        }
        if (!$footer) return;
        if (!in_array(md5(self::collapse($footer->post_content)), $known, true)){
            $this->log[] = 'FIGYELEM: a láblécet szerkesztették, nem íródik felül – az új változat: setup/managed/footer.html';
            return;
        }
        wp_update_post([ 'ID' => $footer->ID, 'post_content' => wp_slash(self::content_file('managed/footer.html')) ]);
        $this->log[] = 'Lábléc frissítve a design szerint';
    }
}
