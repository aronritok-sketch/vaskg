<?php

namespace VasskisgepTheme;

/**
 * Admin listák kényelmi funkciói (Dávid, 2026-11-09):
 *  – Termékkategóriák: szűrés gyártóra (a gyártó termékeit tartalmazó kategóriák, a szülőikkel együtt, hogy a fa
 *    megmaradjon), főágra, ingyenes szállításra és üres / nem üres kategóriára.
 *  – Terméklista: a legutóbbi szűrés (kategória, gyártó, készlet, keresés, oldal …) megmarad; a termék szerkesztése
 *    után a „Vissza a szűrt listához” gomb és a Termékek menüpont is oda visz vissza. Az „Összes” link alaphelyzetbe áll.
 */
class AdminLists {
    const LIST_META = 'vk_products_list_query';
    const CAT_FILTERS = [ 'vk_brand', 'vk_root', 'vk_free', 'vk_empty' ];

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        if (!is_admin()) return;
        add_filter('get_terms_args', [$this, 'category_filter_args'], 10, 2);
        add_action('admin_footer-edit-tags.php', [$this, 'category_filter_form']);
        add_action('load-edit.php', [$this, 'remember_or_restore_list']);
        add_action('edit_form_top', [$this, 'back_button']);
    }

    private static function is_category_screen(){
        global $pagenow;
        return $pagenow === 'edit-tags.php' && ($_GET['taxonomy'] ?? '') === 'product_cat';
    }

    /* ---------- Termékkategóriák szűrése ---------- */

    /** A szűrőnek megfelelő kategóriák azonosítói (a szülőikkel), vagy null, ha nincs szűrés. */
    static function filtered_category_ids(){
        $brand = (int)($_GET['vk_brand'] ?? 0);
        $root = (int)($_GET['vk_root'] ?? 0);
        $free = !empty($_GET['vk_free']);
        $empty = sanitize_key($_GET['vk_empty'] ?? '');
        if (!$brand && !$root && !$free && !in_array($empty, [ 'yes', 'no' ], true)) return null;

        $all = get_terms([ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'id=>parent', 'vk_unfiltered' => 1 ]);
        if (is_wp_error($all)) return null;
        $match = array_keys($all);
        if ($brand){
            global $wpdb;
            $ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT tt2.term_id FROM {$wpdb->term_relationships} r1
                 JOIN {$wpdb->term_taxonomy} tt1 ON tt1.term_taxonomy_id = r1.term_taxonomy_id AND tt1.taxonomy = 'product_brand' AND tt1.term_id = %d
                 JOIN {$wpdb->term_relationships} r2 ON r2.object_id = r1.object_id
                 JOIN {$wpdb->term_taxonomy} tt2 ON tt2.term_taxonomy_id = r2.term_taxonomy_id AND tt2.taxonomy = 'product_cat'", $brand));
            $match = array_values(array_intersect($match, array_map('intval', $ids)));
        }
        if ($root){
            $match = array_values(array_filter($match, function($id) use ($root){ return $id === $root || in_array($root, array_map('intval', get_ancestors($id, 'product_cat')), true); }));
        }
        if ($free){
            $match = array_values(array_filter($match, function($id){ return get_term_meta($id, Shipping::FREE_TERM, true) === 'yes'; }));
        }
        if ($empty === 'yes' || $empty === 'no'){
            $counts = get_terms([ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'id=>count', 'vk_unfiltered' => 1 ]);
            $match = array_values(array_filter($match, function($id) use ($counts, $empty){
                $n = (int)get_term_meta($id, 'product_count_product_cat', true) ?: (int)($counts[$id] ?? 0);
                return $empty === 'yes' ? $n === 0 : $n > 0;
            }));
        }
        // A fa megmaradjon: a találatok szülői is kellenek (a lista csak a szülő alatt mutatja a gyereket).
        $out = [];
        foreach($match as $id){
            $out[$id] = true;
            foreach(get_ancestors($id, 'product_cat') as $a) $out[(int)$a] = true;
        }
        return array_keys($out);
    }

    /** A kategórialista lekérdezése (csak a lista és a darabszáma: a „page” kulcsot csak a lista adja, a szülő-választó nem). */
    function category_filter_args($args, $taxonomies){
        if (!self::is_category_screen() || !empty($args['vk_unfiltered']) || (!isset($args['page']) && ($args['fields'] ?? '') !== 'count')) return $args;
        if (!in_array('product_cat', (array)$taxonomies, true)) return $args;
        $ids = self::filtered_category_ids();
        if ($ids === null) return $args;
        $args['include'] = $ids ?: [ 0 ];
        return $args;
    }

    function category_filter_form(){
        if (!self::is_category_screen()) return;
        $brands = taxonomy_exists('product_brand') ? get_terms([ 'taxonomy' => 'product_brand', 'hide_empty' => true, 'orderby' => 'name' ]) : [];
        $roots = get_terms([ 'taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => false, 'orderby' => 'name', 'vk_unfiltered' => 1 ]);
        $brand = (int)($_GET['vk_brand'] ?? 0);
        $root = (int)($_GET['vk_root'] ?? 0);
        $empty = sanitize_key($_GET['vk_empty'] ?? '');
        $active = $brand || $root || !empty($_GET['vk_free']) || $empty !== '';
        ob_start();
        echo '<form method="get" class="vk-cat-filter"><input type="hidden" name="taxonomy" value="product_cat"><input type="hidden" name="post_type" value="product">';
        echo '<select name="vk_brand"><option value="">Gyártó szerint</option>';
        foreach(is_array($brands) ? $brands : [] as $b) echo '<option value="'.(int)$b->term_id.'"'.selected($brand, (int)$b->term_id, false).'>'.esc_html($b->name).'</option>';
        echo '</select> <select name="vk_root"><option value="">Főág szerint</option>';
        foreach(is_array($roots) ? $roots : [] as $r) echo '<option value="'.(int)$r->term_id.'"'.selected($root, (int)$r->term_id, false).'>'.esc_html($r->name).'</option>';
        echo '</select> <select name="vk_empty"><option value="">Termékszám</option><option value="no"'.selected($empty, 'no', false).'>Van terméke</option><option value="yes"'.selected($empty, 'yes', false).'>Üres kategória</option></select>';
        echo ' <label class="vk-cat-filter-free"><input type="checkbox" name="vk_free" value="1"'.checked(!empty($_GET['vk_free']), true, false).'> Ingyenes szállítás</label>';
        echo ' <button type="submit" class="button">Szűrés</button>';
        if ($active) echo ' <a class="button-link" href="'.esc_url(admin_url('edit-tags.php?taxonomy=product_cat&post_type=product')).'">Szűrés törlése</a>';
        echo '</form>';
        $form = ob_get_clean();
        ?>
        <style>
            .vk-cat-filter { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 0 0 8px; }
            .vk-cat-filter-free { display: inline-flex; align-items: center; gap: 4px; }
        </style>
        <script>
        (function(){
            var html = <?php echo wp_json_encode($form); ?>;
            var target = document.querySelector('#col-right .col-wrap form#posts-filter') || document.querySelector('#posts-filter');
            if (!target) return;
            var box = document.createElement('div');
            box.innerHTML = html;
            target.parentNode.insertBefore(box.firstChild, target);
        })();
        </script>
        <?php
    }

    /* ---------- Terméklista: a szűrés megmarad ---------- */

    /** A terméklista lekérdezése, a műveleti paraméterek nélkül. */
    private static function list_query(){
        $skip = [ 'action', 'action2', '_wpnonce', '_wp_http_referer', 'bulk_edit', 'post', 'ids', 'trashed', 'untrashed', 'deleted', 'locked', 'skipped', 'updated', 'message', 'vk_publish_done', 'vk_publish_mode', 'vk_free_done', 'vk_free_mode', 'vk_season_done', 'vk_list' ];
        $query = [];
        foreach($_GET as $key => $value){
            if (in_array($key, $skip, true) || is_array($value)) continue;
            $query[sanitize_key($key)] = sanitize_text_field(wp_unslash($value));
        }
        return $query;
    }

    function remember_or_restore_list(){
        if (($_GET['post_type'] ?? '') !== 'product' || !current_user_can('edit_products')) return;
        $user = get_current_user_id();
        $query = self::list_query();
        // A Termékek menüpont (paraméter nélkül) egy termék szerkesztése után a legutóbbi szűrt listára visz.
        $referer = (string)wp_get_referer();
        if (count($query) === 1 && empty($_GET['action']) && strpos($referer, 'post.php') !== false && $_SERVER['REQUEST_METHOD'] === 'GET'){
            $saved = get_user_meta($user, self::LIST_META, true);
            if (is_array($saved) && count($saved) > 1){
                wp_safe_redirect(add_query_arg(array_map('rawurlencode', $saved), admin_url('edit.php')));
                exit;
            }
        }
        update_user_meta($user, self::LIST_META, $query);
    }

    /** A termék szerkesztésekor: „Vissza a szűrt listához” (ha volt szűrés). */
    function back_button($post){
        if (!$post || $post->post_type !== 'product') return;
        $saved = get_user_meta(get_current_user_id(), self::LIST_META, true);
        if (!is_array($saved) || count($saved) <= 1) return;
        $url = add_query_arg(array_map('rawurlencode', $saved), admin_url('edit.php'));
        echo '<p class="vk-back-to-list" style="margin:8px 0 0"><a class="button" href="'.esc_url($url).'">← Vissza a szűrt terméklistához</a></p>';
    }
}
