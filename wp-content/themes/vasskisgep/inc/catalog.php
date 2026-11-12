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
        // Nagyker ár (a régi webshop „Nagyker” vásárlói csoportjának ára; Dávid, 2026-11-10): az ár mellett, csak az adminban.
        add_action('woocommerce_product_options_pricing', [$this, 'wholesale_field']);
        add_action('admin_enqueue_scripts', [$this, 'admin_scripts']);
        // Másolással („Másolás új piszkozatba”) készült termék új termék: a régi webshop azonosítója nem jön vele
        // (különben nem kapna „Új termék” jelvényt és régi termékként kezelnénk; Dávid, 2026-11-11).
        add_action('woocommerce_product_duplicate', function($duplicate){
            $duplicate->delete_meta_data('_vk_old_id');
            $duplicate->save();
        });

        // A keretrendszer tulajdonság-blokkja taxonómia-attribútumnál az azonosítót írná ki.
        add_filter('render_block_iu-woocommerce/product-attributes', [$this, 'render_attributes'], 10, 2);

        // Termékkártya a design szerint: promóciós szöveg a cím alatt, Cofidis jelvény a képen.
        add_filter('render_block_iu-woocommerce/loop-product-title', [$this, 'card_promo'], 10, 2);
        add_filter('render_block_iu/group', [$this, 'card_cofidis'], 10, 2);
        add_filter('render_block_iu-woocommerce/product-badges', [$this, 'new_badge'], 10, 2);

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
    /** Gép-kategória → a tartozékai (Tartozékok ág levele): a gép típusválasztójában is megjelenik (Dávid, 2026-11-09). */
    const ACCESSORY_FOR = [
        'Láncfűrészek és ágnyesők' => 'Motorfűrész-tartozékok',
        'Fűnyírók' => 'Fűnyíró-tartozékok',
        'Fűnyíró traktorok' => 'Fűnyíró traktor tartozékok',
        'Robotfűnyírók' => 'Robotfűnyíró-tartozékok',
        'Fűkaszák és szegélynyírók' => 'Fűkasza- és szegélynyíró-tartozékok',
        'Kerekes és távirányítású bozótvágók' => 'Bozót- és magasfűvágó tartozékok',
        'Gyepszellőztetők' => 'Gyepszellőztető-tartozékok',
        'Sövénynyírók és sövényvágók' => 'Sövényvágó-tartozékok',
        'Daraboló gépek' => 'Vágótárcsás gép tartozékok',
        'Permetezés' => 'Permetező-tartozékok',
        'Magasnyomású mosók' => 'Magasnyomású mosó tartozékok',
        'Lombfúvók' => 'Fúvógép-tartozékok',
        'Száraz-nedves porszívók' => 'Porszívó-tartozékok',
        'Seprőgépek' => 'Seprőgép- és térkőtisztító tartozékok',
        'Térkőtisztítók' => 'Seprőgép- és térkőtisztító tartozékok',
        'Komposztaprítók' => 'Aprító-tartozékok',
        'Ágaprítók és ágdarálók' => 'Aprító-tartozékok',
        'Rönkhasítók' => 'Rönkhasító-tartozékok',
        'Kapálógépek és rotációs kapák' => 'Kapálógép- és talajmaró-tartozékok',
        'Talajmarók' => 'Kapálógép- és talajmaró-tartozékok',
        'Multifunkciós művelőgépek' => 'Multifunkciós művelőgép tartozékok',
        'Egytengelyes traktorok' => 'Egytengelyes traktor tartozékok',
        'Talaj- és gödörfúrók' => 'Talajfúró-tartozékok',
        'Áramfejlesztők és aggregátorok' => 'Áramfejlesztő-tartozékok',
    ];

    /** A tartozék-levél kategória (név szerint, a Tartozékok ágon belül), vagy null. */
    static function accessory_term($machine_name){
        static $cache = [];
        if (!isset(self::ACCESSORY_FOR[$machine_name])) return null;
        $name = self::ACCESSORY_FOR[$machine_name];
        if (!array_key_exists($name, $cache)){
            $cache[$name] = null;
            foreach(get_terms([ 'taxonomy' => 'product_cat', 'name' => $name, 'hide_empty' => false ]) ?: [] as $t){
                $root = get_ancestors($t->term_id, 'product_cat');
                $top = $root ? get_term(end($root), 'product_cat') : $t;
                if ($top && !is_wp_error($top) && $top->name === 'Tartozékok'){ $cache[$name] = $t; break; }
            }
        }
        return $cache[$name];
    }

    /** A gép-kategória tartozék-kategóriái (azonosítóval, a kategória szerkesztésénél állítható). */
    const ACCESSORIES = 'vk_accessories';

    /**
     * A gép-kategória tartozék-kategóriái: a kategória „Tartozékok ehhez a géphez” beállítása (azonosítóval, így
     * átnevezés után is megmarad; több is lehet – Dávid, 2026-11-10), ha nincs beállítva, a név szerinti alapértelmezés.
     */
    static function accessory_terms($term){
        $term = is_object($term) ? $term : get_term((int)$term, 'product_cat');
        if (!$term || is_wp_error($term)) return [];
        if (metadata_exists('term', $term->term_id, self::ACCESSORIES)){
            $out = [];
            foreach(array_filter(array_map('intval', (array)get_term_meta($term->term_id, self::ACCESSORIES, true))) as $id){
                $t = get_term($id, 'product_cat');
                if ($t && !is_wp_error($t)) $out[] = $t;
            }
            return $out;
        }
        $acc = self::accessory_term($term->name);
        return $acc ? [ $acc ] : [];
    }

    /** Gyártói oldal csempéinek sorrendje gyártónként: '' (kategóriák sorrendje), 'count' (termékszám), 'custom' (egyedi). */
    const TILE_MODE = 'vk_tile_mode';
    const TILE_ORDER = 'vk_tile_order';

    const BRAND_TILE_GROUPS = [ 'Fűnyírók', 'Fűnyíró traktorok', 'Láncfűrészek és ágnyesők', 'Permetezés', 'Kombi- és multirendszerek', 'Daraboló gépek', 'Tömörítő gépek' ];

    /**
     * A márka termékeinek kategória-csoportjai: [term_id => ['count' => n, 'image' => attachment_id]].
     * Gépek elöl (darabszám szerint), utána az Alkatrészek és karbantartás ágai, végül a Tartozékok egyben.
     * A csempe képe a márka egy terméke a csoportból (kiemelt, készleten lévő előnyben).
     */
    static function brand_categories($brand){
        $key = 'vk_brand_tiles12_'.$brand->term_id.'_'.md5(wp_cache_get_last_changed('posts').wp_cache_get_last_changed('terms'));
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
            $assigned = wp_get_object_terms($ids, 'product_cat', [ 'fields' => 'all_with_object_id' ]);
            // Termékenként azok a kategóriák, amelyeknek a termék egy alkategóriájában is benne van (ezek nem típus-csempék).
            $has_deeper = [];
            foreach($assigned as $t) foreach(get_ancestors($t->term_id, 'product_cat') as $a) $has_deeper[(int)$t->object_id][(int)$a] = true;
            foreach($assigned as $t){
                // A termék szülő-kategóriáját kihagyjuk, ha egy alkategóriájában is benne van (azzal számít).
                if (isset($has_deeper[(int)$t->object_id][(int)$t->term_id])) continue;
                $chain = array_reverse(array_map('intval', get_ancestors($t->term_id, 'product_cat')));
                $chain[] = (int)$t->term_id;
                $root = $name($chain[0]);
                if ($root === 'Tartozékok') $group = $chain[0];
                elseif ($root === 'Alkatrészek és karbantartás'){
                    $group = $chain[1] ?? $chain[0];
                    // A fűrészláncok saját csoport (Dávid, 2026-11-09), az osztás szerinti típusokkal.
                    foreach($chain as $cid) if ($name($cid) === 'Fűrészláncok'){ $group = $cid; break; }
                }
                elseif (isset($chain[2]) && !in_array($name($chain[2]), self::BRAND_TILE_GROUPS, true)) $group = $chain[3] ?? $chain[2];
                else $group = $chain[2] ?? end($chain);
                $product = (int)$t->object_id;
                // Alkategória (a csempe kategóriájának közvetlen gyereke): a márkaoldali típusválasztóhoz.
                $position = array_search($group, $chain, true);
                $child = $position !== false && isset($chain[$position + 1]) ? $chain[$position + 1] : 0;
                // Tartozékoknál a legfinomabb kategória (pl. „Fűnyíró traktor tartozékok”, „Robotfűnyíró-tartozékok”),
                // nem a „Fűnyírás és gyepápolás” szint, ahol minden összekeveredik (ügyfélkérés, 2026-11-09).
                if ($root === 'Tartozékok' && count($chain) > 1) $child = (int)end($chain);
                // Gépeknél, ha a típus alatt még van finomabb csoport (pl. Akkumulátoros fűnyírók → STIHL AK rendszerű akkus
                // fűnyírók), az is megjelenik a választóban (Dávid, 2026-11-09).
                elseif ($root !== 'Alkatrészek és karbantartás' && $position !== false && count($chain) > $position + 2) $child = (int)end($chain);
                // Alkatrészeknél is a legfinomabb csoport (pl. Fűrészláncok, Vezetőlemezek, Szerviz csomagok, STIHL AK / AP
                // akkurendszer), ahogy a régi webshopban (Dávid, 2026-11-09).
                elseif ($root === 'Alkatrészek és karbantartás' && $position !== false && count($chain) > $position + 1) $child = isset($has_deeper[(int)$t->object_id][(int)$t->term_id]) ? 0 : (int)end($chain);
                if (!isset($groups[$group])) $groups[$group] = [ 'count' => 0, 'image' => 0, 'score' => -1, 'kind' => $root === 'Tartozékok' ? 2 : ($root === 'Alkatrészek és karbantartás' ? 1 : 0), 'children' => [], 'child_images' => [] ];
                $image = (int)get_post_meta($product, '_thumbnail_id', true);
                $score = (isset($featured[$product]) ? 2 : 0) + (get_post_meta($product, '_stock_status', true) === 'instock' ? 1 : 0);
                if ($child && !isset($seen[$product]['c'.$child])){
                    $seen[$product]['c'.$child] = true;
                    $groups[$group]['children'][$child] = ($groups[$group]['children'][$child] ?? 0) + 1;
                    if ($image && $score > ($groups[$group]['child_images'][$child][1] ?? -1)) $groups[$group]['child_images'][$child] = [ $image, $score ];
                }
                if (isset($seen[$product][$group])) continue;
                $seen[$product][$group] = true;
                $groups[$group]['count']++;
                if (!$image) continue;
                if ($score > $groups[$group]['score']){ $groups[$group]['image'] = $image; $groups[$group]['score'] = $score; }
            }
            // A gép-kategória típusválasztójában a hozzá tartozó tartozékok is (pl. Láncfűrészek → Motorfűrész-tartozékok).
            $acc_group = 0;
            foreach($groups as $gid => $g) if ($g['kind'] === 2){ $acc_group = $gid; break; }
            if ($acc_group){
                foreach($groups as $gid => &$g){
                    if ($g['kind'] !== 0) continue;
                    foreach(self::accessory_terms($gid) as $acc){
                        if (empty($groups[$acc_group]['children'][$acc->term_id])) continue;
                        $g['children'][$acc->term_id] = $groups[$acc_group]['children'][$acc->term_id];
                        $g['acc'][] = (int)$acc->term_id;
                        if (isset($groups[$acc_group]['child_images'][$acc->term_id])) $g['child_images'][$acc->term_id] = $groups[$acc_group]['child_images'][$acc->term_id];
                    }
                }
                unset($g);
            }
            // Sorrend: a kategóriafa sorrendje (Termékek → Kategóriák „Sorrend” oszlop; Dávid, 2026-11-10), nem a termékszám.
            $tree_key = function($term_id) use ($name){
                $chain = array_reverse(array_map('intval', get_ancestors($term_id, 'product_cat')));
                $chain[] = (int)$term_id;
                return implode("\x01", array_map(function($id) use ($name){ return sprintf('%06d:%s', (int)get_term_meta($id, 'order', true), $name($id)); }, $chain));
            };
            $keys = [];
            foreach(array_keys($groups) as $gid) $keys[$gid] = $tree_key($gid);
            uksort($groups, function($a, $b) use ($groups, $keys){ return [ $groups[$a]['kind'], $keys[$a] ] <=> [ $groups[$b]['kind'], $keys[$b] ]; });
            // Gyártónként külön sorrend (Dávid, 2026-11-10; a márka szerkesztésénél): termékszám szerint vagy egyedi.
            $mode = (string)get_term_meta($brand->term_id, self::TILE_MODE, true);
            if ($mode === 'count'){
                uksort($groups, function($a, $b) use ($groups, $keys){ return [ $groups[$a]['kind'], -$groups[$a]['count'], $keys[$a] ] <=> [ $groups[$b]['kind'], -$groups[$b]['count'], $keys[$b] ]; });
            } elseif ($mode === 'custom'){
                $custom = array_flip(array_map('intval', (array)get_term_meta($brand->term_id, self::TILE_ORDER, true)));
                $base = array_flip(array_keys($groups));
                uksort($groups, function($a, $b) use ($custom, $base){
                    return [ $custom[$a] ?? PHP_INT_MAX, $base[$a] ] <=> [ $custom[$b] ?? PHP_INT_MAX, $base[$b] ];
                });
            }
            foreach($groups as &$g){
                $ck = [];
                foreach(array_keys($g['children']) as $cid) $ck[$cid] = $tree_key($cid);
                uksort($g['children'], function($a, $b) use ($ck){ return strcmp($ck[$a], $ck[$b]); });
                // A tartozék-csempe a típusok után, a sor végén.
                $acc_ids = array_values(array_unique(array_map('intval', (array)($g['acc'] ?? []))));
                foreach($acc_ids as $acc_id) if (isset($g['children'][$acc_id])){ $n = $g['children'][$acc_id]; unset($g['children'][$acc_id]); $g['children'][$acc_id] = $n; }
                $g = [ 'count' => $g['count'], 'image' => $g['image'], 'children' => $g['children'], 'acc' => $acc_ids, 'child_images' => array_map(function($i){ return $i[0]; }, $g['child_images']) ];
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
            $anchor = count($group['children'] ?? []) > 1 || !empty($group['acc']) ? 'tipusok' : 'termekek';
            $html .= '<li><a class="vk-brand-tile'.($active_group === $term_id ? ' vk-active' : '').'" href="'.esc_url($url($term, $anchor)).'">'
                .'<span class="vk-brand-tile-image">'.$image.'</span>'
                .'<span class="vk-brand-tile-name">'.esc_html($term->name).'</span>'
                .'<small>'.(int)$group['count'].' termék</small></a></li>';
        }
        $html .= '</ul>';

        // Típusválasztó (ügyfélkérés, 2026-10): a kiválasztott kategória alkategóriái ebben a márkában, képes csempékben.
        $children = $active_group ? ($groups[$active_group]['children'] ?? []) : [];
        if (count($children) > 1 || ($active_group && !empty($groups[$active_group]['acc']) && $children)){
            $group_term = get_term($active_group, 'product_cat');
            $tiles = [];
            // Ha a gép-kategóriának nincs típusa, csak tartozéka, maga a gép is csempe legyen (pl. Rönkhasítók +
            // Rönkhasító-tartozékok; Dávid, 2026-11-09).
            $acc_ids = array_map('intval', (array)($groups[$active_group]['acc'] ?? []));
            if ($acc_ids && !array_diff_key($children, array_flip($acc_ids))){
                $tiles[] = [ 'url' => $url($group_term, 'termekek'), 'image' => (int)$groups[$active_group]['image'], 'name' => $group_term->name, 'count' => (int)$groups[$active_group]['count'], 'active' => false ];
            }
            foreach($children as $child_id => $count){
                $child = get_term($child_id, 'product_cat');
                if (!$child || is_wp_error($child)) continue;
                // Az alkategória csempéje egyből a termékeihez visz (ügyfélkérés, 2026-10).
                $tiles[] = [ 'url' => $url($child, 'termekek'), 'image' => (int)($groups[$active_group]['child_images'][$child_id] ?? 0), 'name' => $child->name, 'count' => $count, 'active' => $active_id === (int)$child_id ];
            }
            $all = '<a class="vk-brand-cats-all'.($active_id === $active_group ? ' vk-active' : '').'" href="'.esc_url($url($group_term, 'tipusok')).'">Mind <small>'.(int)$groups[$active_group]['count'].'</small></a>';
            $html .= '<div class="vk-brand-subcats" id="tipusok"><div class="vk-brand-cats-head"><span class="vk-brand-cats-label">'.esc_html($group_term->name).' – válasszon típust</span>'.$all.'</div>'.self::tiles_html($tiles).'</div>';
        }
        return $html.'</nav>';
    }

    /** Képes csempék (márkaoldal, típusválasztó, kategóriaoldal): [ url, image, name, count, active ] elemekből. */
    static function tiles_html(array $tiles){
        $html = '<ul class="vk-brand-tiles">';
        foreach($tiles as $tile){
            $image = !empty($tile['image']) ? wp_get_attachment_image($tile['image'], 'medium', false, [ 'alt' => '', 'loading' => 'lazy' ]) : '';
            if ($image === '') $image = wc_placeholder_img('medium', [ 'alt' => '' ]);
            $html .= '<li><a class="vk-brand-tile'.(!empty($tile['active']) ? ' vk-active' : '').'" href="'.esc_url($tile['url']).'">'
                .'<span class="vk-brand-tile-image">'.$image.'</span>'
                .'<span class="vk-brand-tile-name">'.esc_html($tile['name']).'</span>'
                .'<small>'.(int)$tile['count'].' termék</small></a></li>';
        }
        return $html.'</ul>';
    }

    /**
     * Kategóriaoldal: az alkategóriák képes csempékben a termékek fölött (ügyfélkérés, 2026-10), mint a márkaoldalon;
     * mobilon két oszlopban. Kép: a kategória saját képe, különben egy kiemelt / készleten lévő termékéé.
     */
    static function category_children_html($term){
        $children = get_terms([ 'taxonomy' => 'product_cat', 'parent' => $term->term_id, 'hide_empty' => true, 'orderby' => 'meta_value_num', 'meta_key' => 'order' ]);
        if (is_wp_error($children)) $children = [];
        if (count($children) < 2 && !self::accessory_terms($term)) return '';
        $key = 'vk_cat_tiles5_'.$term->term_id.'_'.md5(wp_cache_get_last_changed('posts').wp_cache_get_last_changed('terms'));
        $tiles = get_transient($key);
        if (!is_array($tiles)){
            $tiles = [];
            foreach($children as $child){
                $count = (int)get_term_meta($child->term_id, 'product_count_product_cat', true) ?: (int)$child->count;
                if (!$count) continue;
                $image = (int)get_term_meta($child->term_id, 'thumbnail_id', true);
                if (!$image){
                    foreach([ [ 'featured' => true, 'stock_status' => 'instock' ], [ 'stock_status' => 'instock' ], [] ] as $extra){
                        $ids = wc_get_products(array_merge([ 'status' => 'publish', 'limit' => 1, 'return' => 'ids', 'category' => [ $child->slug ], 'visibility' => 'catalog' ], $extra));
                        $image = $ids ? (int)get_post_thumbnail_id($ids[0]) : 0;
                        if ($image) break;
                    }
                }
                // A csempe a termékekhez visz; ha az alkategóriának is vannak alkategóriái, az oldal tetejére (ott a csempéi).
                $grandchildren = get_terms([ 'taxonomy' => 'product_cat', 'parent' => $child->term_id, 'hide_empty' => true, 'fields' => 'ids', 'number' => 2 ]);
                $link = get_term_link($child);
                $tiles[] = [ 'url' => is_wp_error($link) ? '' : $link.(!is_wp_error($grandchildren) && count($grandchildren) > 1 ? '' : '#termekek'), 'image' => $image, 'name' => $child->name, 'count' => $count ];
            }
            // A gép-kategóriánál a tartozékai is egy csempén (Dávid, 2026-11-09).
            foreach(self::accessory_terms($term) as $acc){
                $acc_count = (int)get_term_meta($acc->term_id, 'product_count_product_cat', true) ?: (int)$acc->count;
                if (!$acc_count) continue;
                $image = (int)get_term_meta($acc->term_id, 'thumbnail_id', true);
                if (!$image){
                    $ids = wc_get_products([ 'status' => 'publish', 'limit' => 1, 'return' => 'ids', 'category' => [ $acc->slug ], 'visibility' => 'catalog', 'stock_status' => 'instock' ]);
                    $image = $ids ? (int)get_post_thumbnail_id($ids[0]) : 0;
                }
                $link = get_term_link($acc);
                $tiles[] = [ 'url' => is_wp_error($link) ? '' : $link.'#termekek', 'image' => $image, 'name' => $acc->name, 'count' => $acc_count, 'acc' => 1 ];
            }
            set_transient($key, $tiles, DAY_IN_SECONDS);
        }
        $machine_tiles = count(array_filter($tiles, function($t){ return empty($t['acc']); }));
        if ($machine_tiles < 2 && count($tiles) === $machine_tiles) return '';
        if (!$tiles) return '';
        $label = $machine_tiles ? 'Válasszon kategóriát' : 'Tartozékok ehhez';
        return '<nav class="vk-brand-cats vk-cat-children" aria-label="'.esc_attr($term->name.' alkategóriák').'"><div class="vk-brand-cats-head"><span class="vk-brand-cats-label">'.$label.'</span>'
            .'<a class="vk-brand-cats-all" href="#termekek">Összes termék <small>'.(int)(get_term_meta($term->term_id, 'product_count_product_cat', true) ?: $term->count).'</small></a></div>'
            .self::tiles_html($tiles).'</nav>';
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
        // Soronként egy feltűnő címke (Dávid, 2026-11-09: „ki kellene emelni, hogy látható legyen”).
        return '<'.$tag.' class="'.esc_attr($class).'">'.implode('', array_map(function($line){ return '<span class="vk-promo-tag">'.esc_html($line).'</span>'; }, $lines)).'</'.$tag.'>';
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

    /**
     * „Új termék” jelvény (ügyfélkérés, 2026-11): a felvétel után 30 napig, magától lekerül. A régi webshopból
     * átköltöztetett termékek nem újak (azok felvétele az átköltöztetés napja); kivétel a régi boltba utoljára,
     * az átköltöztetés után felvett néhány termék (MigrationNewProducts). A keretrendszer 10 napos „Új” jelvénye helyett.
     */
    const NEW_DAYS = 30;

    static function is_new($product){
        $created = $product->get_date_created();
        if (!$created || $created->getTimestamp() < time() - self::NEW_DAYS * DAY_IN_SECONDS) return false;
        if ($product->get_meta('_vk_old_id') === '') return true;
        static $late = null;
        if ($late === null){
            $late = [];
            foreach((array)(get_option('vk_mig_newprod2', [])['imported'] ?? []) as $row) $late[(int)$row['product']] = true;
        }
        return isset($late[$product->get_id()]);
    }

    function new_badge($content, $block){
        global $product;
        $content = str_replace('<span class="iu-woocommerce-badge new">Új</span>', '', (string)$content);
        if (!$product instanceof \WC_Product || !self::is_new($product)) return $content;
        $pos = strpos($content, '>');
        return $pos === false ? $content : substr_replace($content, '><span class="iu-woocommerce-badge vk-new">Új termék</span>', $pos, 1);
    }

    /**
     * Garancia az ár alapján (ügyfélkérés, 2026-11): 249 990 Ft-ig 2 év, felette 3 év – a gépeknél (a Tartozékok és
     * az Alkatrészek és karbantartás ág termékeinél nem). A régi bolt garancia-mezője helyett ez látszik.
     */
    const WARRANTY_LIMIT = 249990;

    static function warranty_months($product){
        $price = (float)wc_get_price_including_tax($product);
        if ($price <= 0) return 0;
        static $accessory = null;
        if ($accessory === null){
            $accessory = [];
            foreach([ 'Tartozékok', 'Alkatrészek és karbantartás' ] as $name){
                $root = get_term_by('name', $name, 'product_cat');
                if (!$root) continue;
                $accessory[(int)$root->term_id] = true;
                foreach(get_term_children($root->term_id, 'product_cat') as $id) $accessory[(int)$id] = true;
            }
        }
        $id = $product->get_parent_id() ?: $product->get_id();
        $cats = wc_get_product_cat_ids($id);
        if (!$cats) return 0;
        foreach($cats as $cat) if (isset($accessory[(int)$cat])) return 0;
        return $price <= self::WARRANTY_LIMIT ? 24 : 36;
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
            // A választható tulajdonság (pl. méret) a kosárba sornál van, nem csempe.
            if (!$attribute->get_visible() || $attribute->get_variation()) continue;
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

        $months = self::warranty_months($product);
        if ($months){
            $rows['garancia'] = [ 'slug' => 'garancia', 'label' => $known['garancia']['label'] ?? 'Garancia', 'value' => (string)$months, 'unit' => 'hó', 'text' => ($months / 12).' év' ];
        }
        $ordered = [];
        foreach(array_keys($known) as $slug){
            if (isset($rows[$slug])) $ordered[] = $rows[$slug];
        }
        return array_merge($ordered, $other);
    }

    static function promo($product){
        $promo = trim((string)$product->get_meta('_vk_promo'));
        // Ingyenes szállítású terméknél (termék / kategória) magától is kiírjuk, ha a promóciós szöveg nem említi.
        if (Shipping::is_free($product) && !preg_match('/ingyenes\s+szállítás/iu', $promo)) $promo = trim('Ingyenes szállítás!'."\n".$promo);
        return $promo;
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
        // Dokumentumok: a fájlok nevével, egyenként törölhetők, több is hozzáadható (Dávid, 2026-11-10).
        $value = $product_object ? (string)$product_object->get_meta('_vk_documents') : '';
        $ids = array_filter(array_map('absint', explode(',', $value)));
        echo '<p class="form-field _vk_documents_field"><label>Dokumentumok</label>'
            .'<input type="hidden" id="_vk_documents" name="_vk_documents" value="'.esc_attr(implode(',', $ids)).'">'
            .'<span class="vk-documents-list" style="display:block;margin-bottom:6px">';
        foreach($ids as $id){
            if (get_post_type($id) !== 'attachment') continue;
            $file = basename((string)get_attached_file($id));
            echo self::document_row($id, get_the_title($id), $file);
        }
        echo '</span><button type="button" class="button vk-documents-select">Dokumentum hozzáadása</button>'
            .' <span class="description">Letölthető fájlok (pl. használati utasítás PDF) a termékoldal Dokumentumok fülén. A feltöltőben egyszerre több fájl is kijelölhető.</span></p>';
        echo '</div>';
    }

    function save_product_fields($product){
        if (isset($_POST[self::WHOLESALE])) $product->update_meta_data(self::WHOLESALE, wc_format_decimal(wp_unslash($_POST[self::WHOLESALE])));
        $product->update_meta_data('_vk_promo', isset($_POST['_vk_promo']) ? sanitize_textarea_field(wp_unslash($_POST['_vk_promo'])) : '');
        $product->update_meta_data('_vk_cofidis', !empty($_POST['_vk_cofidis']) ? 'yes' : 'no');
        $documents = isset($_POST['_vk_documents']) ? implode(',', array_filter(array_map('absint', explode(',', wp_unslash($_POST['_vk_documents']))))) : '';
        $product->update_meta_data('_vk_documents', $documents);
    }

    const WHOLESALE = '_vk_wholesale_price';

    function wholesale_field(){
        global $product_object;
        woocommerce_wp_text_input([
            'id' => self::WHOLESALE,
            'label' => 'Nagyker ár (bruttó, Ft)',
            'data_type' => 'price',
            'desc_tip' => true,
            'description' => 'A nagyker (viszonteladói) ár, a régi webshop „Nagyker” csoportjából átvéve. Csak itt, az adminban látszik.',
            'value' => $product_object ? $product_object->get_meta(self::WHOLESALE) : '',
        ]);
    }

    /** Egy dokumentum sora a termékszerkesztőben (cím, fájlnév, eltávolítás). */
    static function document_row($id, $title, $file){
        return '<span class="vk-document" data-id="'.(int)$id.'" style="display:flex;align-items:center;gap:8px;padding:4px 0;border-bottom:1px solid #f0f0f1">'
            .'<span class="dashicons dashicons-media-document" aria-hidden="true"></span>'
            .'<span><strong>'.esc_html($title).'</strong>'.($file !== '' && $file !== $title ? ' <small style="color:#646970">'.esc_html($file).'</small>' : '').'</span>'
            .'<button type="button" class="button-link vk-document-remove" style="margin-left:auto;color:#b32d2e" title="Eltávolítás a termékről (a fájl a Médiatárban marad)">Eltávolítás</button></span>';
    }

    function admin_scripts($hook){
        if (!in_array($hook, ['post.php', 'post-new.php'], true) || get_post_type() !== 'product') return;
        wp_enqueue_media();
        wp_add_inline_script('jquery-core', "jQuery(function($){
            function vkDocIds(){ return $('.vk-documents-list .vk-document').map(function(){ return $(this).data('id'); }).get(); }
            function vkDocSync(){ $('#_vk_documents').val(vkDocIds().join(',')); }
            function vkEsc(t){ return $('<div>').text(t || '').html(); }
            $(document).on('click', '.vk-documents-select', function(e){
                e.preventDefault();
                const frame = wp.media({ title: 'Dokumentumok hozzáadása', button: { text: 'Hozzáadás a termékhez' }, multiple: 'add', library: { type: ['application', 'text'] } });
                frame.on('select', function(){
                    const have = vkDocIds();
                    frame.state().get('selection').each(function(a){
                        if (have.indexOf(a.id) !== -1) return;
                        const t = a.get('title') || a.get('filename'), f = a.get('filename') || '';
                        $('.vk-documents-list').append('<span class=\"vk-document\" data-id=\"' + a.id + '\" style=\"display:flex;align-items:center;gap:8px;padding:4px 0;border-bottom:1px solid #f0f0f1\"><span class=\"dashicons dashicons-media-document\" aria-hidden=\"true\"></span><span><strong>' + vkEsc(t) + '</strong>' + (f && f !== t ? ' <small style=\"color:#646970\">' + vkEsc(f) + '</small>' : '') + '</span><button type=\"button\" class=\"button-link vk-document-remove\" style=\"margin-left:auto;color:#b32d2e\">Eltávolítás</button></span>');
                    });
                    vkDocSync();
                });
                frame.open();
            });
            $(document).on('click', '.vk-document-remove', function(e){ e.preventDefault(); $(this).closest('.vk-document').remove(); vkDocSync(); });
        });");
    }
}
