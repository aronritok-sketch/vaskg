<?php

namespace VasskisgepTheme;

/**
 * WooCommerce kiegészítések, amelyek a keretrendszerben (iu_woocommerce) nincsenek meg.
 */
class Shop {
    /** Évszakok: termékcímkék (product_tag), a kezdőlap szezonális karusszele használja. */
    const SEASONS = [
        'tavasz' => 'Tavasz',
        'nyar' => 'Nyár',
        'osz' => 'Ősz',
        'tel' => 'Tél',
    ];

    static function current_season(){
        $month = (int)current_time('n');
        if ($month >= 3 && $month <= 5) return 'tavasz';
        if ($month >= 6 && $month <= 8) return 'nyar';
        if ($month >= 9 && $month <= 11) return 'osz';
        return 'tel';
    }

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        // A téma sablonmotorja nem futtatja a loopot, ezért a termékoldalon a
        // globális $product-ot nekünk kell beállítani a blokkok számára.
        add_action('wp', [$this, 'setup_single_product']);

        add_filter('iu_title', [$this, 'archive_titles']);
        // A kártyák (lista, karusszel, kereső, összehasonlítás) képe: a WooCommerce négyzetre vágott
        // bélyegképe helyett a vágatlan változat, így az álló vagy fekvő gép teljes egészében látszik.
        add_filter('image_downsize', [$this, 'uncropped_thumbnail'], 10, 3);
        add_filter('document_title_parts', function($parts){
            if (self::is_sale_listing()) $parts['title'] = 'Akciós termékek';
            return $parts;
        });
        add_filter('render_block_iu/breadcrumbs', [$this, 'escape_search_breadcrumb']);
        add_filter('woocommerce_get_breadcrumb', '__return_empty_array');

        // Termékoldal: kapcsolódó termékek, készlet szövegek.
        add_filter('woocommerce_get_availability_text', [$this, 'availability_text'], 10, 2);
        add_filter('woocommerce_product_single_add_to_cart_text', function(){ return 'Kosárba teszem'; });
        add_filter('woocommerce_product_add_to_cart_text', [$this, 'loop_add_to_cart_text'], 10, 2);

        // A WooCommerce márka-archívuma saját sablonfájlt töltene be (fejléc,
        // lábléc nélkül); a téma sablonmotorja kell (tax_product_brand_content.html).
        add_filter('template_include', [$this, 'brand_template'], 99);

        // Márkaoldal: a terméklista a márkára szűr.
        add_filter('woocommerce_shortcode_products_query', [$this, 'brand_archive_filter'], 20, 2);

        // Az iu_woocommerce az is_archive('product_cat') feltétellel minden
        // archívumon (márka, címke) a lekérdezett elem slugját kategóriaként
        // adja át a [products] listának; ez a márka/címke oldalakon üres listát ad.
        add_filter('shortcode_atts_products', [$this, 'fix_archive_category'], 20, 3);
        // A design szerinti kereső felirat (a keretrendszer blokkjában fix szöveg).
        add_filter('render_block_iu-woocommerce/search', function($content){ return str_replace('placeholder="Termékek keresése..."', 'placeholder="Keresés..."', $content); });
        add_filter('render_block_iu/breadcrumbs', [$this, 'term_breadcrumbs'], 10, 2);

        // Kosár / pénztár apróságok.
        add_filter('woocommerce_cart_item_thumbnail', [$this, 'cart_thumbnail'], 10, 3);
        add_filter('gettext_woocommerce', [$this, 'texts'], 10, 2);

        // Strukturált adatok: a márka megjelenik a termék schemában.
        add_filter('woocommerce_structured_data_product', [$this, 'structured_data_brand'], 10, 2);

        // Adószám (a keretrendszer pénztár-mezője): megjelenik a rendelésben és az e-mailekben.
        add_filter('woocommerce_order_get_formatted_billing_address', [$this, 'billing_tax_number'], 10, 3);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'admin_tax_number']);

        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 100);
    }

    function billing_tax_number($address, $raw_address, $order){
        $tax_number = $order->get_meta('_billing_tax_number');
        if ($tax_number === '' || $address === '') return $address;
        return $address.'<br/>Adószám: '.esc_html($tax_number);
    }

    function admin_tax_number($order){
        $tax_number = $order->get_meta('_billing_tax_number');
        if ($tax_number === '') return;
        echo '<p><strong>Adószám:</strong> '.esc_html($tax_number).'</p>';
    }

    function enqueue(){
        $dir = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();
        wp_enqueue_style('vasskisgep-shop', $uri.'/assets/shop.css', [], filemtime($dir.'/assets/shop.css'));
        wp_enqueue_script('vasskisgep-shop', $uri.'/assets/shop.js', ['jquery'], filemtime($dir.'/assets/shop.js'), true);
        wp_localize_script('vasskisgep-shop', 'vasskisgep', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'wishlist_url' => Lists::page_url('wishlist'),
            'compare_url' => Lists::page_url('compare'),
            'compare_limit' => Lists::COMPARE_LIMIT,
            'cookie_path' => COOKIEPATH ?: '/',
            'lists_nonce' => is_user_logged_in() ? wp_create_nonce('vasskisgep-lists') : '',
        ]);

        // A termékgaléria (zoom, lightbox, slider) szkriptjei csak a termékoldalon kellenek.
        if (is_product()){
            wp_enqueue_script('wc-single-product');
            wp_enqueue_script('flexslider');
            wp_enqueue_script('photoswipe-ui-default');
            wp_enqueue_style('photoswipe-default-skin');
            add_action('wp_footer', 'woocommerce_photoswipe');
        }
    }

    function setup_single_product(){
        if (!is_product()) return;
        global $product;
        $product = wc_get_product(get_queried_object_id());
    }

    function uncropped_thumbnail($out, $id, $size){
        static $busy = false;
        if ($out !== false || $busy || $size !== 'woocommerce_thumbnail' || (is_admin() && !wp_doing_ajax())) return $out;
        $busy = true;
        $single = image_downsize($id, 'woocommerce_single');
        $busy = false;
        return $single ?: $out;
    }

    /** A terméklista akciós szűrővel (?onsale=yes, pl. a főmenü „Akciók” pontja). */
    static function is_sale_listing(){
        return function_exists('is_shop') && is_shop() && isset($_GET['onsale']) && $_GET['onsale'] === 'yes';
    }

    function archive_titles($title){
        if (self::is_sale_listing()) return 'Akciós termékek';
        if (is_tax(['product_cat', 'product_brand', 'product_tag'])){
            return single_term_title('', false);
        }
        if (is_category()){
            return 'Téma: '.single_term_title('', false);
        }
        // A keretrendszer a keresőszót escape nélkül írná ki ($_GET['s']).
        if (is_search()){
            return 'Keresés erre: '.esc_html(get_search_query(false));
        }
        // Fiók végpontok (Elfelejtett jelszó, Rendelések, …): a végpont címe a „Fiókom” helyett.
        if (function_exists('is_account_page') && is_account_page() && is_wc_endpoint_url()){
            $endpoint = WC()->query->get_current_endpoint();
            $endpoint_title = $endpoint ? WC()->query->get_endpoint_title($endpoint) : '';
            if ($endpoint_title !== '') return esc_html($endpoint_title);
        }
        return $title;
    }

    /** A keretrendszer morzsamenüje a keresőszót escape nélkül írja ki ($_GET['s']). */
    function escape_search_breadcrumb($content){
        if (!is_search() || !isset($_GET['s']) || !is_string($_GET['s'])) return $content;
        return str_replace('Keresés erre: '.$_GET['s'].'</span>', 'Keresés erre: '.esc_html(get_search_query(false)).'</span>', $content);
    }

    function availability_text($text, $product){
        if ($product->is_on_backorder()) return '1-5 munkanap visszaigazolástól';
        if (!$product->is_in_stock()) return 'Jelenleg nem rendelhető';
        if ($product->managing_stock() && $product->get_stock_quantity() !== null && $product->get_stock_quantity() <= (int)get_option('woocommerce_notify_low_stock_amount', 2)){
            return 'Utolsó darabok';
        }
        return 'Készleten';
    }

    function loop_add_to_cart_text($text, $product){
        if (!$product->is_purchasable() || !$product->is_in_stock()) return 'Részletek';
        if ($product->is_type('variable')) return 'Kiválasztom';
        return 'Kosárba';
    }

    function fix_archive_category($out, $pairs, $atts){
        if (!isset($atts['class']) || $atts['class'] !== 'mainquery') return $out;
        if (!is_tax(['product_brand', 'product_tag']) || empty($out['category'])) return $out;

        $queried = get_queried_object();
        if ($queried && $out['category'] === $queried->slug && empty($_GET['product-categories'])){
            $out['category'] = '';
        }
        if (is_tax('product_tag') && !wp_doing_ajax()) $out['tag'] = $queried->slug;
        return $out;
    }

    /** A keretrendszer morzsamenüje a márka- és címkeoldalt nem ismeri, a kategóriaoldalon nem zárja le a markupot. */
    function term_breadcrumbs($content, $block){
        if (!is_tax(['product_brand', 'product_tag', 'product_cat'])) return $content;

        $separator = !empty($block['attrs']['separator']) ? $block['attrs']['separator'] : '/';
        $shop_id = wc_get_page_id('shop');
        $home = get_post(get_option('page_on_front'));

        // Kategóriánál a szülő kategóriák (a keretrendszer ennél nem zárja le a markupot).
        $ancestors = '';
        $term = get_queried_object();
        if ($term->taxonomy === 'product_cat'){
            foreach(array_reverse(get_ancestors($term->term_id, 'product_cat', 'taxonomy')) as $ancestor_id){
                $ancestor = get_term($ancestor_id, 'product_cat');
                $ancestors .= '<span itemprop="itemListElement" itemtype="http://schema.org/ListItem"><a href="'.esc_url(get_term_link($ancestor)).'">'.esc_html($ancestor->name).'</a></span>'
                    .'<span class="iu-breadcrumbs-separator">'.esc_html($separator).'</span>';
            }
        }

        return '<div class="iu-breadcrumbs" itemscope itemtype="http://schema.org/BreadcrumbList"><div class="iu-breadcrumbs-wrap">'
            .'<span itemprop="itemListElement" itemtype="http://schema.org/ListItem"><a href="'.esc_url(home_url('/')).'">'.esc_html($home ? $home->post_title : 'Kezdőlap').'</a></span>'
            .'<span class="iu-breadcrumbs-separator">'.esc_html($separator).'</span>'
            .'<span itemprop="itemListElement" itemtype="http://schema.org/ListItem"><a href="'.esc_url(get_permalink($shop_id)).'">'.esc_html(get_the_title($shop_id)).'</a></span>'
            .'<span class="iu-breadcrumbs-separator">'.esc_html($separator).'</span>'
            .$ancestors
            .'<span class="iu-breadcrumbs-current">'.esc_html(single_term_title('', false)).'</span>'
            .'</div></div>';
    }

    function brand_template($template){
        if (!is_tax('product_brand')) return $template;
        $index = locate_template('index.php');
        return $index ?: $template;
    }

    function brand_archive_filter($query_args, $atts){
        if (!isset($atts['class']) || $atts['class'] !== 'mainquery') return $query_args;

        $brand = '';
        if (is_tax('product_brand') && !wp_doing_ajax()) $brand = get_queried_object()->slug;
        if (!empty($_GET['product-brand'])) $brand = sanitize_title(wp_unslash($_GET['product-brand']));
        if ($brand === '') return $query_args;

        if (!isset($query_args['tax_query'])) $query_args['tax_query'] = [];
        $query_args['tax_query'][] = [
            'taxonomy' => 'product_brand',
            'field' => 'slug',
            'terms' => [ $brand ]
        ];
        return $query_args;
    }

    function cart_thumbnail($thumbnail, $cart_item, $cart_item_key){
        $product = $cart_item['data'];
        if (!$product || $product->get_image_id()) return $thumbnail;
        return wc_placeholder_img('woocommerce_thumbnail');
    }

    function texts($translation, $text){
        switch($text){
            case 'Related products': return 'Hasonló termékek';
            case 'Proceed to checkout': return 'Tovább a pénztárhoz';
        }
        return $translation;
    }

    function structured_data_brand($markup, $product){
        $brands = get_the_terms($product->get_id(), 'product_brand');
        if (!empty($brands) && !is_wp_error($brands)){
            $markup['brand'] = [ '@type' => 'Brand', 'name' => $brands[0]->name ];
        }
        return $markup;
    }

    /**
     * Egységes termékkártya-lista (a Loop Product minta használja a kártyát).
     * $products: WC_Product tömb.
     */
    static function render_product_list($products, $class = ''){
        if (empty($products)) return '';

        global $product, $post, $wp_query;
        $in_the_loop = $wp_query->in_the_loop;
        $wp_query->in_the_loop = true;

        ob_start();
        wc_set_loop_prop('columns', 4);
        echo '<div class="woocommerce '.esc_attr($class).'">';
        woocommerce_product_loop_start();
        foreach($products as $product_obj){
            $product = $product_obj;
            $post = get_post($product->get_id());
            setup_postdata($post);
            wc_get_template_part('content', 'product');
        }
        woocommerce_product_loop_end();
        echo '</div>';
        wp_reset_postdata();
        wc_reset_loop();

        $wp_query->in_the_loop = $in_the_loop;
        if (is_product()) $product = wc_get_product(get_queried_object_id());

        return ob_get_clean();
    }

    /**
     * Termékkarusszel a keretrendszer product-carousel blokkjának markupjával,
     * így annak szkriptje és stílusa érvényes rá.
     */
    static function render_product_carousel($products, $params = []){
        if (empty($products)) return '';

        $params = wp_parse_args($params, [ 'slidesToShow' => 4, 'slidesToScroll' => 1, 'infinite' => false, 'dots' => true ]);

        wp_enqueue_script('iu-woocommerce-product-carousel-script');
        wp_enqueue_style('iu-woocommerce-product-carousel-style');

        global $product, $post, $wp_query;
        $in_the_loop = $wp_query->in_the_loop;
        $wp_query->in_the_loop = true;

        ob_start();
        echo '<div class="iu-woocommerce-product-carousel">';
            echo '<div class="iu-woocommerce-product-carousel-slider" data-slick=\''.esc_attr(wp_json_encode($params)).'\'>';
            foreach($products as $product_obj){
                $product = $product_obj;
                $post = get_post($product->get_id());
                setup_postdata($post);
                wc_get_template_part('content', 'product');
            }
            echo '</div>';
            echo '<div class="iu-woocommerce-product-carousel-arrows"></div>';
        echo '</div>';
        wp_reset_postdata();

        $wp_query->in_the_loop = $in_the_loop;
        if (is_product()) $product = wc_get_product(get_queried_object_id());

        return ob_get_clean();
    }
}
