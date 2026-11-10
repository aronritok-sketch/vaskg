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
        // Kategóriák sorrendje számmal (Dávid, 2026-11-10: mint a régi adminban; a főág az alkategóriáival együtt mozog).
        add_filter('manage_edit-product_cat_columns', [$this, 'category_columns'], 20);
        add_filter('manage_product_cat_custom_column', [$this, 'category_column'], 10, 3);
        add_action('wp_ajax_vk_cat_order', [$this, 'ajax_category_order']);
        add_filter('edit_product_cat_per_page', function($n){ return (int)$n === 20 ? 400 : $n; });
        // Gyártói oldal csempéinek sorrendje gyártónként (a márka szerkesztésénél).
        add_action('product_brand_edit_form_fields', [$this, 'brand_order_fields'], 20);
        add_action('edited_product_brand', [$this, 'save_brand_order']);
        // Rendelés: telefonszám másolása a futárszolgálatokhoz (+36 nélkül, egybeírva).
        add_action('admin_footer', [$this, 'phone_copy_script']);
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
            .column-vk_order { width: 7rem; }
            .vk-cat-order { width: 4.2rem; }
            .vk-cat-order-of { margin-left: 4px; color: #646970; }
            .vk-cat-order.is-busy { opacity: .5; }
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
        (function(){
            var nonce = <?php echo wp_json_encode(wp_create_nonce('vk_cat_order')); ?>;
            function save(input){
                var pos = parseInt(input.value, 10), old = parseInt(input.getAttribute('data-old'), 10);
                if (!pos || pos === old || input.classList.contains('is-busy')) return;
                input.classList.add('is-busy'); input.disabled = true;
                var body = new URLSearchParams({ action: 'vk_cat_order', nonce: nonce, term: input.getAttribute('data-term'), pos: pos });
                fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: body }).then(function(r){ return r.json(); }).then(function(r){
                    if (!r || !r.success){ alert((r && r.data && r.data.message) || 'Nem sikerült menteni.'); input.value = old; input.disabled = false; input.classList.remove('is-busy'); return; }
                    location.reload();
                }).catch(function(){ alert('Nem sikerült menteni (kapcsolati hiba).'); input.value = old; input.disabled = false; input.classList.remove('is-busy'); });
            }
            document.addEventListener('keydown', function(e){
                if (e.key === 'Enter' && e.target.classList && e.target.classList.contains('vk-cat-order')){ e.preventDefault(); save(e.target); }
            });
            document.addEventListener('change', function(e){ if (e.target.classList && e.target.classList.contains('vk-cat-order')) save(e.target); });
            // A sorban kattintás (Gyors szerkesztés stb.) ne nyíljon meg a mezőre kattintva.
            document.addEventListener('click', function(e){ if (e.target.classList && e.target.classList.contains('vk-cat-order')) e.stopPropagation(); }, true);
        })();
        </script>
        <?php
    }

    /* ---------- Kategóriák sorrendje ---------- */

    /** A szülő alatti kategóriák a megjelenítési sorrendben (a WooCommerce „order” mezője, azonosnál név szerint). */
    static function siblings($parent){
        $terms = get_terms([ 'taxonomy' => 'product_cat', 'parent' => (int)$parent, 'hide_empty' => false, 'vk_unfiltered' => 1 ]);
        if (is_wp_error($terms)) return [];
        usort($terms, function($a, $b){
            return [ (int)get_term_meta($a->term_id, 'order', true), $a->name ] <=> [ (int)get_term_meta($b->term_id, 'order', true), $b->name ];
        });
        return $terms;
    }

    function category_columns($columns){
        $out = [];
        foreach($columns as $key => $label){
            if ($key === 'name') $out['vk_order'] = 'Sorrend';
            $out[$key] = $label;
        }
        return $out;
    }

    function category_column($content, $column, $term_id){
        if ($column !== 'vk_order') return $content;
        static $positions = [];
        $term = get_term($term_id, 'product_cat');
        if (!$term || is_wp_error($term)) return $content;
        if (!isset($positions[$term->parent])){
            $positions[$term->parent] = [];
            foreach(self::siblings($term->parent) as $i => $t) $positions[$term->parent][(int)$t->term_id] = $i + 1;
        }
        $pos = $positions[$term->parent][(int)$term_id] ?? 0;
        $max = count($positions[$term->parent]);
        return '<input type="number" class="vk-cat-order" min="1" max="'.(int)$max.'" value="'.(int)$pos.'" data-term="'.(int)$term_id.'" data-old="'.(int)$pos.'" title="Sorszám a '.($term->parent ? 'szülő kategórián belül' : 'főágak között').' (1–'.(int)$max.'). Átírás után Enter: az alkategóriák vele együtt mozognak.">'
            .'<span class="vk-cat-order-of">/ '.(int)$max.'</span>';
    }

    function ajax_category_order(){
        $term_id = (int)($_POST['term'] ?? 0);
        $pos = (int)($_POST['pos'] ?? 0);
        if (!check_ajax_referer('vk_cat_order', 'nonce', false) || !current_user_can('manage_product_terms')) wp_send_json_error([ 'message' => 'Nincs jogosultság.' ], 403);
        $term = get_term($term_id, 'product_cat');
        if (!$term || is_wp_error($term) || $pos < 1) wp_send_json_error([ 'message' => 'Hibás adat.' ], 400);
        $ids = array_map(function($t){ return (int)$t->term_id; }, self::siblings($term->parent));
        $ids = array_values(array_diff($ids, [ $term_id ]));
        array_splice($ids, min($pos, count($ids) + 1) - 1, 0, [ $term_id ]);
        // A testvérek újraszámozása 0-tól (a WooCommerce húzásos rendezése is így tárolja).
        foreach($ids as $i => $id) update_term_meta($id, 'order', $i);
        if (function_exists('wc_delete_product_transients')) wc_delete_product_transients();
        wp_send_json_success([ 'position' => array_search($term_id, $ids, true) + 1 ]);
    }

    /* ---------- Gyártói oldal: csempék sorrendje gyártónként ---------- */

    function brand_order_fields($term){
        $groups = Catalog::brand_categories($term);
        if (count($groups) < 2) return;
        $mode = (string)get_term_meta($term->term_id, Catalog::TILE_MODE, true);
        echo '<tr class="form-field"><th scope="row">Kategória-csempék sorrendje<br><small style="font-weight:400">a gyártó oldalán</small></th><td>';
        wp_nonce_field('vk_tile_order', 'vk_tile_nonce');
        echo '<p><select name="vk_tile_mode" id="vk-tile-mode">'
            .'<option value=""'.selected($mode, '', false).'>A kategóriák sorrendje szerint (Termékek → Kategóriák)</option>'
            .'<option value="count"'.selected($mode, 'count', false).'>Termékszám szerint (a legtöbb terméket tartalmazó elöl)</option>'
            .'<option value="custom"'.selected($mode, 'custom', false).'>Egyedi sorrend (lent, számokkal)</option>'
            .'</select></p>';
        echo '<table class="widefat striped" style="max-width:560px"><thead><tr><th style="width:90px">Sorrend</th><th>Csempe</th><th style="width:90px">Termék</th></tr></thead><tbody>';
        $i = 0;
        foreach($groups as $gid => $g){
            $cat = get_term($gid, 'product_cat');
            if (!$cat || is_wp_error($cat)) continue;
            $i++;
            echo '<tr><td><input type="number" min="1" name="vk_tile_order['.(int)$gid.']" value="'.$i.'" class="vk-tile-pos" style="width:70px"><input type="hidden" name="vk_tile_old['.(int)$gid.']" value="'.$i.'"></td><td>'.esc_html($cat->name).'</td><td>'.(int)$g['count'].'</td></tr>';
        }
        echo '</tbody></table><p class="description">A mostani sorrend látszik. Ha átírod a számokat, a sorrend „Egyedi” lesz; mentés: „Frissítés” lent. Ez csak ennél a gyártónál számít, a többi gyártó és a kategóriák sorrendje nem változik.</p>';
        echo '<script>document.addEventListener("input",function(e){if(e.target.classList&&e.target.classList.contains("vk-tile-pos")){var s=document.getElementById("vk-tile-mode");if(s)s.value="custom";}});</script>';
        echo '</td></tr>';
    }

    function save_brand_order($term_id){
        if (!isset($_POST['vk_tile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vk_tile_nonce'])), 'vk_tile_order') || !current_user_can('manage_product_terms')) return;
        $mode = sanitize_key(wp_unslash($_POST['vk_tile_mode'] ?? ''));
        if (!in_array($mode, [ '', 'count', 'custom' ], true)) $mode = '';
        if ($mode === '') delete_term_meta($term_id, Catalog::TILE_MODE);
        else update_term_meta($term_id, Catalog::TILE_MODE, $mode);
        if ($mode === 'custom'){
            $positions = [];
            $i = 0;
            $old = (array)($_POST['vk_tile_old'] ?? []);
            foreach((array)($_POST['vk_tile_order'] ?? []) as $gid => $pos){
                $pos = max(1, (int)$pos);
                $was = (int)($old[$gid] ?? $pos);
                // Azonos számnál: a hátrébb tett elem a helyén lévő mögé, az előrébb tett elé kerül.
                $tie = $pos === $was ? 1 : ($pos > $was ? 2 : 0);
                $positions[(int)$gid] = [ $pos, $tie, $i++ ];
            }
            uasort($positions, function($a, $b){ return $a <=> $b; });
            update_term_meta($term_id, Catalog::TILE_ORDER, array_keys($positions));
        }
    }

    /* ---------- Rendelés: telefonszám másolása ---------- */

    function phone_copy_script(){
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->id, [ 'shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders' ], true)) return;
        ?>
        <style>
            .vk-phone-copy { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; margin-left: 6px; padding: 0; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; color: #2271b1; cursor: pointer; vertical-align: middle; }
            .vk-phone-copy:hover { border-color: #2271b1; }
            .vk-phone-copy.is-done { border-color: #00a32a; color: #00a32a; }
            .vk-phone-copy svg { width: 13px; height: 13px; }
        </style>
        <script>
        (function(){
            // +36 után egybeírva (pl. +36 30 304 1524 → 303041524); 06-tal kezdődőnél a 06 nélkül; külföldi számnál + és a számjegyek.
            function courierNumber(raw){
                var d = String(raw || '').replace(/[^\d+]/g, '');
                if (/^\+36/.test(d)) return d.slice(3);
                if (/^0036/.test(d)) return d.slice(4);
                if (/^36\d{8,9}$/.test(d)) return d.slice(2);
                if (/^06/.test(d)) return d.slice(2);
                return d;
            }
            var icon = '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="5" y="5" width="9" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M3 11V3a1 1 0 0 1 1-1h7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';
            function copy(text, btn){
                var done = function(){ btn.classList.add('is-done'); btn.title = 'Másolva: ' + text; setTimeout(function(){ btn.classList.remove('is-done'); btn.title = 'Telefonszám másolása (+36 nélkül, egybeírva): ' + text; }, 1500); };
                if (navigator.clipboard && window.isSecureContext){ navigator.clipboard.writeText(text).then(done); return; }
                var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); done(); } catch(e){} t.remove();
            }
            function add(root){
                (root || document).querySelectorAll('a[href^="tel:"]').forEach(function(a){
                    if (a.dataset.vkCopy) return;
                    a.dataset.vkCopy = '1';
                    var num = courierNumber(a.getAttribute('href').slice(4) || a.textContent);
                    if (!num) return;
                    var b = document.createElement('button');
                    b.type = 'button'; b.className = 'vk-phone-copy'; b.innerHTML = icon;
                    b.title = 'Telefonszám másolása (+36 nélkül, egybeírva): ' + num;
                    b.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation(); copy(num, b); });
                    a.insertAdjacentElement('afterend', b);
                });
            }
            add();
            new MutationObserver(function(){ add(); }).observe(document.body, { childList: true, subtree: true });
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
