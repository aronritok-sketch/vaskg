<?php

namespace VasskisgepTheme;

/**
 * Fejléc kereső: a keretrendszer (iu-woocommerce/search) mezője megmarad, a találatok
 * viszont egy megamenü-szerű, teljes szélességű panelben jelennek meg a fejléc alatt:
 * termékek (kép, ár, készlet), kategóriák, Tudástár cikkek, „Összes találat” link.
 * Enter: a keresés oldalra visz (?s=…).
 */
class Search {
    const PRODUCTS = 8;
    const MIN_LENGTH = 2;

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('wp_ajax_vasskisgep_search', [$this, 'ajax']);
        add_action('wp_ajax_nopriv_vasskisgep_search', [$this, 'ajax']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 110);
    }

    function enqueue(){
        $dir = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();
        wp_enqueue_script('vasskisgep-search', $uri.'/assets/search.js', ['jquery'], filemtime($dir.'/assets/search.js'), true);
        wp_localize_script('vasskisgep-search', 'vasskisgepSearch', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'search_url' => home_url('/'),
            'min' => self::MIN_LENGTH
        ]);
    }

    function ajax(){
        $s = isset($_GET['s']) ? trim(wc_clean(wp_unslash($_GET['s']))) : '';
        if (mb_strlen($s) < self::MIN_LENGTH) wp_send_json_success([ 'html' => '', 'total' => 0 ]);

        nocache_headers();
        wp_send_json_success(self::results($s));
    }

    /** Kisbetűs, ékezet nélküli szöveg az összevetéshez. */
    private static function fold($text){
        return mb_strtolower(remove_accents(html_entity_decode(wp_strip_all_tags((string)$text), ENT_QUOTES, 'UTF-8')));
    }

    /** Hosszabb, nem szám szó: a leírásban is kereshető (a rövid szavak és számok csak a névben és a cikkszámban). */
    private static function is_word($token){
        return mb_strlen($token) >= 4 && !preg_match('/\d/', $token);
    }

    /** Rokon alakok (ékezet nélkül): a vásárló „akkus”-t ír, a termék neve „akkumulátoros”. */
    const SYNONYMS = [
        'akkus' => [ 'akkumulator' ], 'akku' => [ 'akkumulator' ], 'aksi' => [ 'akkumulator' ],
        'benzines' => [ 'benzin' ], 'benzinmotoros' => [ 'benzin' ],
        'lancfuresz' => [ 'motorfuresz' ], 'motorfuresz' => [ 'lancfuresz' ],
        'fukasza' => [ 'szegelynyiro' ], 'szegelynyiro' => [ 'fukasza' ],
        'aggregator' => [ 'aramfejleszto' ], 'aramfejleszto' => [ 'aggregator' ],
    ];

    /** A szó keresett alakjai: maga, a rokon alakok és a gyakori toldalékok nélküli tő („fűnyírók” → „fűnyíró”). */
    private static function forms($token){
        $forms = array_merge([ $token ], self::SYNONYMS[$token] ?? []);
        if (mb_strlen($token) >= 6 && !preg_match('/\d/', $token)){
            $stem = preg_replace('/(?:hoz|hez|hoz|nak|nek|bol|bol|val|vel|ok|ek|ak|ot|et|at|k|t)$/u', '', $token);
            if ($stem !== $token && mb_strlen($stem) >= 5) $forms[] = $stem;
        }
        return array_values(array_unique($forms));
    }

    /**
     * A szó a névben: szám egészként (a „60” nem illik a „160”-ra); rövid szó egész szóként, kivéve az utoljára
     * gépeltet (gépelés közben még csonka lehet), az csak szó elején; hosszabb szó bárhol (összetett szavakban is).
     */
    private static function in_text($token, $text, $last = false){
        if (preg_match('/^\d+$/', $token)) $pattern = '/(?<!\d)'.preg_quote($token, '/').'(?!\d)/u';
        elseif (mb_strlen($token) <= 3) $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($token, '/').($last ? '' : '(?![\p{L}])').'/u';
        else return mb_strpos($text, $token) !== false;
        return (bool)preg_match($pattern, $text);
    }

    /**
     * Termékkeresés relevancia szerint (fejléc panel és keresés oldal). Minden szónak egyeznie kell; a számok és
     * a rövid szavak (pl. „msa 60”) csak a névben vagy a cikkszámban, egész szóként. Sorrend: pontos cikkszám →
     * a kifejezés egyben a névben (minél előrébb) → minden szó a névben → leírás; a gépek a tartozékok előtt.
     * @return int[] termékazonosítók
     */
    static function product_ids($s, $limit = 200){
        global $wpdb;
        $query = self::fold($s);
        // „msa60” → „msa 60”, „ms170c” → „ms 170 c”
        $query = preg_replace('/(?<=\p{L})(?=\p{N})|(?<=\p{N})(?=\p{L})/u', ' ', $query);
        $tokens = array_slice(array_values(array_unique(array_filter(preg_split('/[^\p{L}\p{N}]+/u', $query), 'strlen'))), 0, 8);
        if (empty($tokens)) return [];
        $compact = implode('', $tokens);

        $where = [];
        foreach($tokens as $token){
            $parts = [];
            foreach(self::forms($token) as $form){
                $like = '%'.$wpdb->esc_like($form).'%';
                $parts[] = $wpdb->prepare('p.post_title LIKE %s', $like);
                $parts[] = $wpdb->prepare('l.sku LIKE %s', $like);
                if (self::is_word($form)){
                    $parts[] = $wpdb->prepare('p.post_excerpt LIKE %s', $like);
                    $parts[] = $wpdb->prepare('p.post_content LIKE %s', $like);
                }
            }
            $where[] = '('.implode(' OR ', $parts).')';
        }
        $match = '('.implode(' AND ', $where).')';
        // Cikkszám szóközök, kötőjelek nélkül is (pl. „4180 200 0588”).
        if (strlen($compact) >= 4) $match .= $wpdb->prepare(" OR REPLACE(REPLACE(REPLACE(l.sku, ' ', ''), '-', ''), '/', '') LIKE %s", $wpdb->esc_like($compact).'%');
        $rows = $wpdb->get_results("SELECT p.ID, p.post_title, l.sku, l.stock_status FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->wc_product_meta_lookup} l ON l.product_id = p.ID
            WHERE p.post_type = 'product' AND p.post_status = 'publish' AND ($match) LIMIT 1500");
        if (empty($rows)) return [];

        $accessory = self::accessory_ids(wp_list_pluck($rows, 'ID'));
        $phrase = '/(?<![\p{L}\p{N}])'.implode('[^\p{L}\p{N}]*', array_map(function($t){
            return '(?:'.implode('|', array_map(function($f){ return preg_quote($f, '/'); }, self::forms($t))).')';
        }, $tokens)).(preg_match('/\d$/', end($tokens)) ? '(?!\d)' : '').'/u';
        $scored = [];
        foreach($rows as $row){
            $title = self::fold($row->post_title);
            $sku = preg_replace('/[^\p{L}\p{N}]+/u', '', self::fold($row->sku));
            $score = 0;
            if ($sku !== '' && $sku === $compact) $score += 1000;
            elseif ($sku !== '' && strlen($compact) >= 4 && strpos($sku, $compact) === 0) $score += 300;
            $ok = true;
            $count = count($tokens);
            foreach($tokens as $i => $token){
                $forms = self::forms($token);
                $in_title = false;
                foreach($forms as $form) if (self::in_text($form, $title, $i === $count - 1)){ $in_title = true; break; }
                if ($in_title) $score += 40;
                elseif ($sku !== '' && mb_strlen($token) >= 3 && strpos($sku, $token) !== false) $score += 20;
                elseif (self::is_word($token)) $score += 2;
                else { $ok = false; break; }
            }
            if (!$ok && $score < 300) continue;
            if (preg_match($phrase, $title, $m, PREG_OFFSET_CAPTURE)) $score += 200 + max(0, 40 - (int)$m[0][1]);
            if (!isset($accessory[$row->ID])) $score += 25;
            if ($row->stock_status === 'instock') $score += 5;
            $scored[] = [ (int)$row->ID, $score, mb_strlen($row->post_title) ];
        }
        usort($scored, function($a, $b){ return [ $b[1], $a[2] ] <=> [ $a[1], $b[2] ]; });
        return array_slice(array_column($scored, 0), 0, $limit);
    }

    /** A Tartozékok vagy az Alkatrészek és karbantartás ágba tartozó termékek (a gépek ezek elé kerülnek). */
    private static function accessory_ids($ids){
        static $roots = [];
        $out = [];
        foreach(wp_get_object_terms($ids, 'product_cat', [ 'fields' => 'all_with_object_id' ]) as $term){
            if (!isset($roots[$term->term_id])){
                $chain = get_ancestors($term->term_id, 'product_cat');
                $root = get_term($chain ? end($chain) : $term->term_id, 'product_cat');
                $roots[$term->term_id] = $root && !is_wp_error($root) && in_array($root->name, [ 'Tartozékok', 'Alkatrészek és karbantartás' ], true);
            }
            if ($roots[$term->term_id]) $out[$term->object_id] = true;
        }
        return $out;
    }

    static function results($s){
        /* Termékek (név, cikkszám, leírás) relevancia szerint. */
        $ids = self::product_ids($s, 200);
        $products = array_values(array_filter(array_map('wc_get_product', $ids), function($p){
            return $p && $p->is_visible() && $p->get_status() === 'publish' && !$p->get_parent_id();
        }));
        $total = count($products);

        /* Kategóriák, amelyek neve illeszkedik. */
        $categories = get_terms([ 'taxonomy' => 'product_cat', 'name__like' => $s, 'hide_empty' => false, 'number' => 8 ]);
        $categories = is_wp_error($categories) ? [] : array_values(array_filter($categories, function($t){ return $t->slug !== 'egyeb' && $t->slug !== 'uncategorized'; }));

        /* Tudástár cikkek. */
        $posts = get_posts([ 'post_type' => 'post', 'post_status' => 'publish', 's' => $s, 'numberposts' => 3, 'suppress_filters' => false ]);

        if (!$total && empty($categories) && empty($posts)){
            return [ 'total' => 0, 'html' => '<div class="vk-search-empty"><strong>Nincs találat erre: „'.esc_html($s).'”</strong><span>Próbáljon rövidebb kifejezést vagy cikkszámot, vagy <a href="'.esc_url(wc_get_page_permalink('shop')).'">böngésszen a termékek között</a>.</span></div>' ];
        }

        $cards = '';
        foreach(array_slice($products, 0, self::PRODUCTS) as $product){
            $url = $product->get_permalink();
            $stock = $product->is_in_stock() ? ($product->is_on_backorder() ? 'Rendelésre' : 'Készleten') : 'Nem rendelhető';
            $stock_class = $product->is_in_stock() ? ($product->is_on_backorder() ? 'on-backorder' : 'in-stock') : 'out-of-stock';
            $cards .= '<a class="vk-search-card" href="'.esc_url($url).'">
                <span class="vk-search-card-image">'.$product->get_image('woocommerce_thumbnail').'</span>
                <span class="vk-search-card-body">
                    <span class="vk-search-card-title">'.esc_html($product->get_name()).'</span>
                    <span class="vk-search-card-stock '.$stock_class.'">'.$stock.'</span>
                    <span class="vk-search-card-price">'.$product->get_price_html().'</span>
                </span>
            </a>';
        }

        $side = '';
        if (!empty($categories)){
            $side .= '<h4 class="vk-search-heading">Kategóriák</h4><ul class="vk-search-links">';
            foreach($categories as $term){
                $parent = $term->parent ? get_term($term->parent, 'product_cat') : null;
                $side .= '<li><a href="'.esc_url(get_term_link($term)).'">'.esc_html($term->name).($parent && !is_wp_error($parent) ? '<small>'.esc_html($parent->name).'</small>' : '').'</a></li>';
            }
            $side .= '</ul>';
        }
        if (!empty($posts)){
            $side .= '<h4 class="vk-search-heading">Tudástár</h4><ul class="vk-search-links">';
            foreach($posts as $post) $side .= '<li><a href="'.esc_url(get_permalink($post)).'">'.esc_html(get_the_title($post)).'</a></li>';
            $side .= '</ul>';
        }

        $all = add_query_arg('s', rawurlencode($s), home_url('/'));
        $html = '<div class="vk-search-grid'.($side === '' ? ' vk-search-noside' : '').'">'
            .($side !== '' ? '<div class="vk-search-side">'.$side.'</div>' : '')
            .'<div class="vk-search-main">'
                .'<h4 class="vk-search-heading">Termékek'.($total ? ' <small>('.$total.')</small>' : '').'</h4>'
                .($cards !== '' ? '<div class="vk-search-cards">'.$cards.'</div>' : '<p class="vk-search-none">Nincs a keresésnek megfelelő termék.</p>')
            .'</div>'
        .'</div>'
        .'<div class="vk-search-foot"><a class="vk-search-all" href="'.esc_url($all).'">Összes találat megtekintése'.($total ? ' ('.$total.' termék)' : '').' <span aria-hidden="true">›</span></a></div>';

        return [ 'total' => $total, 'html' => $html ];
    }
}
