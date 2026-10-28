<?php

namespace VasskisgepTheme;

/**
 * Katalógus: szűrhető tulajdonságok (mértékegységgel), termékenkénti extra adatok
 * (promóciós szöveg, Cofidis, dokumentumok) és ezek szerkesztése a termékoldalon.
 */
class Catalog {
    private static $attributes;

    /**
     * Az első telepítő változat kategóriái (slug => [név, új kategória útvonala a
     * categories.json fájában]). A 19. telepítő lépés ezek termékeit átsorolja és törli őket;
     * a régi címek átirányítódnak az új kategóriára.
     */
    const LEGACY_CATEGORIES = [
        'funyirok' => [ 'Fűnyírók', 'Kerti és erdészeti gépek/Fűnyírás és gyepápolás/Fűnyírók' ],
        'robotfunyirok' => [ 'Robotfűnyírók', 'Kerti és erdészeti gépek/Fűnyírás és gyepápolás/További gépek/Robotfűnyírók' ],
        'funyiro-traktorok' => [ 'Fűnyíró traktorok', 'Kerti és erdészeti gépek/Fűnyírás és gyepápolás/Fűnyíró traktorok' ],
        'fukaszak-szegelynyirok' => [ 'Fűkaszák, szegélynyírók', 'Kerti és erdészeti gépek/Fűnyírás és gyepápolás/További gépek/Fűkaszák és szegélynyírók' ],
        'sovenyvagok' => [ 'Sövényvágók', 'Kerti és erdészeti gépek/Fa- és sövényápolás/Metszés és sövényápolás/Sövénynyírók és sövényvágók' ],
        'lombfuvok-lombszivok' => [ 'Lombfúvók, lombszívók', 'Kerti és erdészeti gépek/Tisztítás, víz és energia/Terület- és kerttisztítás/Lombfúvók' ],
        'kapalogepek-talajmarok' => [ 'Kapálógépek, talajmarók', 'Kerti és erdészeti gépek/Talajművelés és kertgondozás/Talajművelő gépek/Kapálógépek és rotációs kapák' ],
        'permetezok' => [ 'Permetezők', 'Kerti és erdészeti gépek/Talajművelés és kertgondozás/Permetezés' ],
        'metszoollok' => [ 'Metszőollók', 'Kerti és erdészeti gépek/Fa- és sövényápolás/Metszés és sövényápolás/Metszőollók' ],
        'lancfureszek' => [ 'Láncfűrészek', 'Kerti és erdészeti gépek/Fa- és sövényápolás/Láncfűrészek és ágnyesők' ],
        'magassagi-agvagok' => [ 'Magassági ágvágók', 'Kerti és erdészeti gépek/Fa- és sövényápolás/Láncfűrészek és ágnyesők/Magassági ágvágók' ],
        'ronkhasitok' => [ 'Rönkhasítók', 'Kerti és erdészeti gépek/Fa- és sövényápolás/Faanyag-feldolgozás/Rönkhasítók' ],
        'agapritok' => [ 'Ágaprítók', 'Kerti és erdészeti gépek/Fa- és sövényápolás/Faanyag-feldolgozás/Ágaprítók és ágdarálók' ],
        'aramfejlesztok' => [ 'Áramfejlesztők', 'Kerti és erdészeti gépek/Tisztítás, víz és energia/Víz és energia/Áramfejlesztők és aggregátorok' ],
        'darabolo-es-vagogepek' => [ 'Daraboló- és vágógépek', 'Építőipari gépek/Vágás és bontás/Daraboló gépek' ],
        'foldfurok' => [ 'Földfúrók', 'Kerti és erdészeti gépek/Talajművelés és kertgondozás/Fúrás, ásás és szállítás/Talaj- és gödörfúrók' ],
        'magasnyomasu-mosok' => [ 'Magasnyomású mosók', 'Kerti és erdészeti gépek/Tisztítás, víz és energia/Gépi tisztítás/Magasnyomású mosók' ],
        'lancok-vezetolemezek' => [ 'Láncok, vezetőlemezek', 'Alkatrészek és karbantartás/Alkatrészek/Vágóelemek/Láncok és vezetőlemezek' ],
        'damilok-fukaszafejek' => [ 'Damilok, fűkaszafejek', 'Alkatrészek és karbantartás/Alkatrészek/Vágóelemek/Damilok és fűkaszafejek' ],
        'akkumulatorok-toltok' => [ 'Akkumulátorok, töltők', 'Alkatrészek és karbantartás/Akkumulátorok és töltők' ],
        'kenoanyagok-uzemanyagok' => [ 'Kenőanyagok, üzemanyagok', 'Alkatrészek és karbantartás/Kenő- és üzemanyagok' ],
        'kerteszeti-gepek' => [ 'Kertészeti gépek', 'Kerti és erdészeti gépek' ],
        'erdeszeti-gepek' => [ 'Erdészeti gépek', 'Kerti és erdészeti gépek/Fa- és sövényápolás' ],
        'alkatreszek-tartozekok' => [ 'Alkatrészek, tartozékok', 'Alkatrészek és karbantartás' ],
        'munkaruhazat-vedofelszereles' => [ 'Munkaruházat, védőfelszerelés', 'Alkatrészek és karbantartás/Munkaruházat és védőfelszerelés' ],
    ];

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
        add_filter('render_block_iu/group', [$this, 'card_cofidis'], 10, 2);

        // Régi / hibás kategóriacímek átirányítása (pl. /termekkategoria/kerteszeti-gepek/funyirok/).
        add_action('template_redirect', [$this, 'redirect_legacy_category'], 5);

        // Kirakat-sorrend (vk_showcase lekérdezés-paraméter): kiemelt → gép (nem tartozék) → készleten.
        add_filter('posts_clauses', [__CLASS__, 'showcase_clauses'], 20, 2);
    }

    /** A term_taxonomy_id-k: a megadott kategóriák és minden alkategóriájuk (név szerint, főkategóriák). */
    private static function subtree_tt_ids($taxonomy, array $names){
        $ids = [];
        foreach($names as $name){
            $root = get_term_by('name', $name, $taxonomy);
            if (!$root) continue;
            foreach(array_merge([ (int)$root->term_id ], array_map('intval', get_term_children($root->term_id, $taxonomy))) as $term_id){
                $term = get_term($term_id, $taxonomy);
                if ($term && !is_wp_error($term)) $ids[] = (int)$term->term_taxonomy_id;
            }
        }
        return $ids;
    }

    /**
     * Kirakat-sorrend: a Kiemelt termékek elöl, utána a gépek (a Tartozékok és az Alkatrészek hátrébb), azon belül a
     * készleten lévők; a meglévő rendezés (pl. dátum) csak utána dönt. 'popular': előtte az eladások száma (a
     * wc_get_products() „popularity” rendezést nem ismer), így eladás híján is gépek jelennek meg.
     */
    static function showcase_clauses($clauses, $query){
        $mode = $query->get('vk_showcase');
        if (!$mode) return $clauses;
        global $wpdb;
        static $sets = null;
        if ($sets === null){
            $featured = get_term_by('slug', 'featured', 'product_visibility');
            $sets = [
                'featured' => $featured ? [ (int)$featured->term_taxonomy_id ] : [],
                'accessory' => self::subtree_tt_ids('product_cat', [ 'Tartozékok', 'Alkatrészek és karbantartás' ]),
            ];
        }
        $has = function($ids, $alias) use ($wpdb){
            return "EXISTS (SELECT 1 FROM {$wpdb->term_relationships} {$alias} WHERE {$alias}.object_id = {$wpdb->posts}.ID AND {$alias}.term_taxonomy_id IN (".implode(',', array_map('intval', $ids))."))";
        };
        $order = [];
        if ($mode === 'popular') $order[] = "(SELECT vk_sp.total_sales FROM {$wpdb->prefix}wc_product_meta_lookup vk_sp WHERE vk_sp.product_id = {$wpdb->posts}.ID) DESC";
        if ($sets['featured']) $order[] = $has($sets['featured'], 'vk_sf').' DESC';
        if ($sets['accessory']) $order[] = $has($sets['accessory'], 'vk_sa').' ASC';
        $order[] = "EXISTS (SELECT 1 FROM {$wpdb->prefix}wc_product_meta_lookup vk_sl WHERE vk_sl.product_id = {$wpdb->posts}.ID AND vk_sl.stock_status = 'instock') DESC";
        $order = implode(', ', $order);
        $current = trim((string)$clauses['orderby']);
        $clauses['orderby'] = $current === '' ? $order : $order.', '.$current;
        return $clauses;
    }

    /**
     * Egy márka termékeinek kategóriái a kategóriafa 3. szintjén (pl. Fűnyírók, Láncfűrészek és ágnyesők),
     * termékszámmal, csökkenő sorrendben. [term_id => count]; gyorsítótárazva, termék- vagy kategóriaváltozásig.
     */
    /**
     * Márkaoldali kategória-csempék szintje (a régi webshop márkaoldalai szerint: gépfajták). A fa 3. szintjén
     * állnak meg azok a csoportok, amelyek alcsoportjai csak változatok (pl. benzines / akkus fűnyírók);
     * a többi gyűjtőcsoport (pl. „További gépek”, „Gépi tisztítás”) a 4. szintre bomlik.
     */
    const BRAND_TILE_GROUPS = [ 'Fűnyírók', 'Fűnyíró traktorok', 'Láncfűrészek és ágnyesők', 'Permetezés', 'Kombi- és multirendszerek', 'Daraboló gépek', 'Tömörítő gépek' ];

    /**
     * A márka termékeinek kategória-csoportjai: [term_id => ['count' => n, 'image' => attachment_id]].
     * Gépek elöl (darabszám szerint), utána az Alkatrészek és karbantartás ágai, végül a Tartozékok egyben.
     * A csempe képe a márka egy terméke a csoportból (kiemelt, készleten lévő előnyben).
     */
    static function brand_categories($brand){
        $key = 'vk_brand_tiles2_'.$brand->term_id.'_'.md5(wp_cache_get_last_changed('posts').wp_cache_get_last_changed('terms'));
        $cached = get_transient($key);
        if (is_array($cached)) return $cached;
        $ids = get_posts([ 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids',
            'tax_query' => [[ 'taxonomy' => 'product_brand', 'field' => 'term_id', 'terms' => $brand->term_id ]] ]);
        $groups = [];
        if ($ids){
            update_meta_cache('post', $ids);
            $featured = array_flip(get_posts([ 'post_type' => 'product', 'post__in' => $ids, 'numberposts' => -1, 'fields' => 'ids',
                'tax_query' => [[ 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => 'featured' ]] ]));
            $names = [];
            $name = function($id) use (&$names){
                if (!isset($names[$id])){ $t = get_term($id, 'product_cat'); $names[$id] = $t && !is_wp_error($t) ? $t->name : ''; }
                return $names[$id];
            };
            $seen = [];
            foreach(wp_get_object_terms($ids, 'product_cat', [ 'fields' => 'all_with_object_id' ]) as $t){
                $chain = array_reverse(array_map('intval', get_ancestors($t->term_id, 'product_cat')));
                $chain[] = (int)$t->term_id;
                $root = $name($chain[0]);
                if ($root === 'Tartozékok') $group = $chain[0];
                elseif ($root === 'Alkatrészek és karbantartás') $group = $chain[1] ?? $chain[0];
                elseif (isset($chain[2]) && !in_array($name($chain[2]), self::BRAND_TILE_GROUPS, true)) $group = $chain[3] ?? $chain[2];
                else $group = $chain[2] ?? end($chain);
                $product = (int)$t->object_id;
                // Alkategória (a csempe kategóriájának közvetlen gyereke): a márkaoldali típusválasztóhoz.
                $position = array_search($group, $chain, true);
                $child = $position !== false && isset($chain[$position + 1]) ? $chain[$position + 1] : 0;
                if (!isset($groups[$group])) $groups[$group] = [ 'count' => 0, 'image' => 0, 'score' => -1, 'kind' => $root === 'Tartozékok' ? 2 : ($root === 'Alkatrészek és karbantartás' ? 1 : 0), 'children' => [] ];
                if ($child && !isset($seen[$product]['c'.$child])){
                    $seen[$product]['c'.$child] = true;
                    $groups[$group]['children'][$child] = ($groups[$group]['children'][$child] ?? 0) + 1;
                }
                if (isset($seen[$product][$group])) continue;
                $seen[$product][$group] = true;
                $groups[$group]['count']++;
                $image = (int)get_post_meta($product, '_thumbnail_id', true);
                if (!$image) continue;
                $score = (isset($featured[$product]) ? 2 : 0) + (get_post_meta($product, '_stock_status', true) === 'instock' ? 1 : 0);
                if ($score > $groups[$group]['score']){ $groups[$group]['image'] = $image; $groups[$group]['score'] = $score; }
            }
            uasort($groups, function($a, $b){ return [ $a['kind'], -$a['count'] ] <=> [ $b['kind'], -$b['count'] ]; });
            foreach($groups as &$g){
                arsort($g['children']);
                $g = [ 'count' => $g['count'], 'image' => $g['image'], 'children' => $g['children'] ];
            }
            unset($g);
        }
        set_transient($key, $groups, DAY_IN_SECONDS);
        return $groups;
    }

    static function brand_categories_html($brand){
        $groups = self::brand_categories($brand);
        if (count($groups) < 2) return '';
        $base = get_term_link($brand);
        $active = isset($_GET['product-categories']) ? sanitize_text_field(wp_unslash($_GET['product-categories'])) : '';
        $active_term = $active !== '' ? get_term_by('slug', $active, 'product_cat') : null;
        $active_id = $active_term ? (int)$active_term->term_id : 0;
        // A kiválasztott csempe: maga a kategória, vagy az, amelyiknek az alkategóriája ki van választva.
        $active_group = 0;
        foreach($groups as $term_id => $group){
            if ($term_id === $active_id || isset($group['children'][$active_id])){ $active_group = $term_id; break; }
        }
        $url = function($term, $anchor) use ($base){ return add_query_arg('product-categories', $term->slug, $base).'#'.$anchor; };

        $html = '<nav class="vk-brand-cats" aria-label="'.esc_attr($brand->name.' kategóriák').'"><div class="vk-brand-cats-head"><span class="vk-brand-cats-label">Kategóriák</span>';
        if ($active !== '') $html .= '<a class="vk-brand-cats-all" href="'.esc_url($base.'#termekek').'">Összes '.esc_html($brand->name).' termék <small>'.(int)$brand->count.'</small></a>';
        $html .= '</div><ul class="vk-brand-tiles">';
        foreach($groups as $term_id => $group){
            $term = get_term($term_id, 'product_cat');
            if (!$term || is_wp_error($term)) continue;
            $image = $group['image'] ? wp_get_attachment_image($group['image'], 'medium', false, [ 'alt' => '', 'loading' => 'lazy' ]) : '';
            if ($image === '') $image = wc_placeholder_img('medium', [ 'alt' => '' ]);
            // A csempe a terméklistához visz (#termekek); ha vannak típusai (pl. akkus, benzines, elektromos), a típusválasztóhoz.
            $anchor = count($group['children'] ?? []) > 1 ? 'tipusok' : 'termekek';
            $html .= '<li><a class="vk-brand-tile'.($active_group === $term_id ? ' vk-active' : '').'" href="'.esc_url($url($term, $anchor)).'">'
                .'<span class="vk-brand-tile-image">'.$image.'</span>'
                .'<span class="vk-brand-tile-name">'.esc_html($term->name).'</span>'
                .'<small>'.(int)$group['count'].' termék</small></a></li>';
        }
        $html .= '</ul>';

        // Típusválasztó (ügyfélkérés, 2026-10): a kiválasztott kategória alkategóriái ebben a márkában, mint a régi boltban.
        $children = $active_group ? ($groups[$active_group]['children'] ?? []) : [];
        if (count($children) > 1){
            $group_term = get_term($active_group, 'product_cat');
            $html .= '<div class="vk-brand-subcats" id="tipusok"><span class="vk-brand-cats-label">'.esc_html($group_term->name).' – válasszon típust</span><ul>';
            $html .= '<li><a class="vk-brand-subcat'.($active_id === $active_group ? ' vk-active' : '').'" href="'.esc_url($url($group_term, 'tipusok')).'"><span>Összes</span><small>'.(int)$groups[$active_group]['count'].' termék</small></a></li>';
            foreach($children as $child_id => $count){
                $child = get_term($child_id, 'product_cat');
                if (!$child || is_wp_error($child)) continue;
                $html .= '<li><a class="vk-brand-subcat'.($active_id === (int)$child_id ? ' vk-active' : '').'" href="'.esc_url($url($child, 'tipusok')).'"><span>'.esc_html($child->name).'</span><small>'.(int)$count.' termék</small></a></li>';
            }
            $html .= '</ul></div>';
        }
        return $html.'</nav>';
    }

    /** Kategória útvonal (név szerint, „Szülő/Gyerek”) → kategória. */
    static function category_by_path($path){
        $parent = 0;
        $term = null;
        foreach(explode('/', $path) as $name){
            $terms = get_terms([ 'taxonomy' => 'product_cat', 'name' => $name, 'parent' => $parent, 'hide_empty' => false, 'number' => 1 ]);
            if (empty($terms) || is_wp_error($terms)) return null;
            $term = $terms[0];
            $parent = $term->term_id;
        }
        return $term;
    }

    /**
     * Egy /termekkategoria/ utáni útvonalhoz az érvényes kategória címe, ha a régi fa
     * kategóriája vagy más útvonalon megadott, létező kategória; különben ''.
     */
    static function legacy_category_url($path){
        $slug = basename(trim($path, '/'));
        if ($slug === '') return '';
        $term = null;
        if (isset(self::LEGACY_CATEGORIES[$slug])){
            $current = get_term_by('slug', $slug, 'product_cat');
            if (!$current || $current->name !== self::LEGACY_CATEGORIES[$slug][0]){
                $term = self::category_by_path(self::LEGACY_CATEGORIES[$slug][1]);
            }
        }
        if (!$term) $term = get_term_by('slug', $slug, 'product_cat');
        if (!$term) return '';
        $url = get_term_link($term);
        return is_wp_error($url) ? '' : $url;
    }

    function redirect_legacy_category(){
        $is_category = is_tax('product_cat') && !is_paged();
        if (!is_404() && !$is_category) return;
        $path = trim(rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)), '/');
        $home = trim((string)parse_url(home_url('/'), PHP_URL_PATH), '/');
        if ($home !== '' && strpos($path, $home.'/') === 0) $path = substr($path, strlen($home) + 1);
        if (strpos($path, 'termekkategoria/') !== 0) return;

        if ($is_category){
            // Létező kategória más (pl. régi) szülő-útvonalon: a helyes címre.
            $url = get_term_link(get_queried_object());
        } else {
            $url = self::legacy_category_url(substr($path, strlen('termekkategoria/')));
        }
        if (!$url || is_wp_error($url)) return;
        if (trim((string)parse_url($url, PHP_URL_PATH), '/') === trim($home.'/'.$path, '/')) return;
        $query = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
        wp_safe_redirect($url.($query !== '' ? '?'.$query : ''), 301);
        exit;
    }

    static function promo_html($product, $class = 'vk-promo', $tag = 'p'){
        $promo = self::promo($product);
        if ($promo === '') return '';
        $lines = array_filter(array_map('trim', preg_split('/\R/', $promo)));
        return '<'.$tag.' class="'.esc_attr($class).'">'.implode('<br>', array_map('esc_html', $lines)).'</'.$tag.'>';
    }

    /** Cofidis jelvény: a „Cofidis expressz” logó (a design szerint; a lábléc logójából). */
    static function cofidis_badge(){
        $src = get_stylesheet_directory_uri().'/assets/cofidis-expressz.png';
        return '<span class="vk-cofidis-badge" title="Cofidis áruhitellel is megvásárolható"><img src="'.esc_url($src).'" alt="Cofidis áruhitel" width="87" height="39" loading="lazy" decoding="async"></span>';
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

    /** A kártya márka-sávjába (kedvezmény bal oldalt, márka jobb oldalt) kerül a Cofidis logó. */
    function card_cofidis($content, $block){
        if (strpos((string)($block['attrs']['className'] ?? ''), 'brand') === false) return $content;
        $product = wc_get_product();
        if (!$product || !self::has_cofidis($product)) return $content;
        $pos = strrpos($content, '</div>');
        return $pos === false ? $content : substr_replace($content, self::cofidis_badge().'</div>', $pos, 6);
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

    const CARD_ATTRIBUTES = 6;

    function render_attributes($content, $block){
        global $product;
        $current = $product instanceof \WC_Product ? $product : wc_get_product();
        if (!$current) return $content;

        // A kártyán is a termékoldali csempék adatai (ügyfélkérés, 2026-10): legfeljebb 6 sor, elöl a szűrhetők;
        // a hosszú szöveges érték csak a termékoldalon. A kártyák egy magasak, az ár + gomb sor alul egy vonalban.
        $rows = array_values(array_filter(self::product_attributes($current), function($row){
            return mb_strlen(wp_strip_all_tags($row['text'])) <= 40;
        }));
        $rows = array_slice($rows, 0, self::CARD_ATTRIBUTES);
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
