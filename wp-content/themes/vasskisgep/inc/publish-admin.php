<?php

namespace VasskisgepTheme;

/**
 * „Közzétéve” pipa a terméklistában (Dávid, 2026-11-09): egy kattintással le- és felrakható a termék a webshopra
 * (közzétéve ↔ vázlat), több termékre a csoportműveletekkel („Közzététel a webshopon”, „Levétel a webshopról”).
 * A levett termék vázlat lesz: a webshopon, a keresőben és a feedekben nem látszik, az adatai megmaradnak.
 * Mellette „Ingyenes szállítás” pipa és csoportművelet (Shipping::FREE; a kategória szerinti ingyenesség szürkén látszik).
 */
class PublishAdmin {
    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_filter('manage_edit-product_columns', [$this, 'columns'], 40);
        add_action('manage_product_posts_custom_column', [$this, 'column'], 10, 2);
        add_filter('bulk_actions-edit-product', [$this, 'bulk_actions']);
        add_filter('handle_bulk_actions-edit-product', [$this, 'handle_bulk'], 10, 3);
        add_action('admin_notices', [$this, 'notice']);
        add_action('wp_ajax_vk_publish_toggle', [$this, 'ajax_toggle']);
        add_action('wp_ajax_vk_free_toggle', [$this, 'ajax_free_toggle']);
        add_action('admin_footer-edit.php', [$this, 'script']);
    }

    private static function is_products_screen(){
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        return $screen && $screen->id === 'edit-product';
    }

    function columns($columns){
        $out = [];
        foreach($columns as $key => $label){
            if ($key === 'date'){ $out['vk_publish'] = 'Közzétéve'; $out['vk_free'] = 'Ingyenes szállítás'; }
            $out[$key] = $label;
        }
        if (!isset($out['vk_publish'])) $out['vk_publish'] = 'Közzétéve';
        if (!isset($out['vk_free'])) $out['vk_free'] = 'Ingyenes szállítás';
        return $out;
    }

    function column($column, $post_id){
        if ($column === 'vk_free'){
            // Ingyenes szállítás (Dávid, 2026-11-09): a termék saját jelölése; ha a kategóriája miatt ingyenes, az is látszik.
            $own = get_post_meta($post_id, Shipping::FREE, true) === 'yes';
            $by_cat = !$own && Shipping::is_free(wc_get_product($post_id));
            $title = $by_cat ? 'A kategóriája szerint ingyenes (Termékek → Kategóriák)' : ($own ? 'Ingyenes szállítás – kattints a kikapcsoláshoz' : 'Kattints: ingyenes szállítás erre a termékre');
            echo '<label class="vk-publish vk-free" title="'.esc_attr($title).'"><input type="checkbox" class="vk-free-toggle" data-product="'.(int)$post_id.'"'.checked($own || $by_cat, true, false).disabled($by_cat, true, false).'><span class="screen-reader-text">Ingyenes szállítás</span></label>';
            if ($by_cat) echo '<small class="vk-free-cat">kategória</small>';
            return;
        }
        if ($column !== 'vk_publish') return;
        $on = get_post_status($post_id) === 'publish';
        echo '<label class="vk-publish" title="'.esc_attr($on ? 'Látszik a webshopon – kattints a levételhez' : 'Nem látszik a webshopon – kattints a közzétételhez').'">'
            .'<input type="checkbox" class="vk-publish-toggle" data-product="'.(int)$post_id.'"'.checked($on, true, false).'>'
            .'<span class="screen-reader-text">Közzétéve</span></label>';
    }

    /** Közzététel vagy levétel (vázlat). Igaz, ha sikerült. */
    static function set_published($id, $publish){
        $id = (int)$id;
        $status = get_post_status($id);
        if (!$status || get_post_type($id) !== 'product') return false;
        if ($publish){
            if ($status === 'publish') return true;
            // A jövőbeli dátumú vázlat ne ütemezett legyen, hanem azonnal kint.
            $post = get_post($id);
            $update = [ 'ID' => $id, 'post_status' => 'publish' ];
            if (strtotime($post->post_date_gmt.' UTC') > time() || $post->post_date_gmt === '0000-00-00 00:00:00'){
                $update['post_date'] = current_time('mysql');
                $update['post_date_gmt'] = current_time('mysql', true);
            }
            $res = wp_update_post($update, true);
        } else {
            if ($status === 'draft') return true;
            $res = wp_update_post([ 'ID' => $id, 'post_status' => 'draft' ], true);
        }
        if (is_wp_error($res) || !$res) return false;
        if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($id);
        return get_post_status($id) === ($publish ? 'publish' : 'draft');
    }

    function bulk_actions($actions){
        $actions['vk_publish_on'] = 'Közzététel a webshopon';
        $actions['vk_publish_off'] = 'Levétel a webshopról (vázlat)';
        $actions['vk_free_on'] = 'Ingyenes szállítás: be';
        $actions['vk_free_off'] = 'Ingyenes szállítás: ki';
        return $actions;
    }

    function handle_bulk($redirect, $action, $ids){
        if (in_array($action, [ 'vk_free_on', 'vk_free_off' ], true) && current_user_can('edit_products')){
            $done = 0;
            foreach(array_map('intval', (array)$ids) as $id){
                if (!current_user_can('edit_post', $id)) continue;
                update_post_meta($id, Shipping::FREE, $action === 'vk_free_on' ? 'yes' : 'no');
                wc_delete_product_transients($id);
                $done++;
            }
            if ($done && class_exists(__NAMESPACE__.'\\Feed')) Feed::flush();
            return add_query_arg([ 'vk_free_done' => $done, 'vk_free_mode' => $action === 'vk_free_on' ? 'on' : 'off' ], remove_query_arg([ 'vk_free_done', 'vk_free_mode' ], $redirect));
        }
        if (!in_array($action, [ 'vk_publish_on', 'vk_publish_off' ], true) || !current_user_can('edit_products')) return $redirect;
        $done = 0;
        foreach(array_map('intval', (array)$ids) as $id){
            if (!current_user_can('edit_post', $id) || ($action === 'vk_publish_on' && !current_user_can('publish_products'))) continue;
            if (self::set_published($id, $action === 'vk_publish_on')) $done++;
        }
        return add_query_arg([ 'vk_publish_done' => $done, 'vk_publish_mode' => $action === 'vk_publish_on' ? 'on' : 'off' ], remove_query_arg([ 'vk_publish_done', 'vk_publish_mode' ], $redirect));
    }

    function notice(){
        if (self::is_products_screen() && isset($_GET['vk_free_done'])){
            $text = ($_GET['vk_free_mode'] ?? '') === 'on' ? 'Ingyenes szállítás bekapcsolva: %d termék.' : 'Ingyenes szállítás kikapcsolva: %d termék.';
            echo '<div class="notice notice-success is-dismissible"><p>'.esc_html(sprintf($text, (int)$_GET['vk_free_done'])).'</p></div>';
        }
        if (!self::is_products_screen() || !isset($_GET['vk_publish_done'])) return;
        $on = ($_GET['vk_publish_mode'] ?? '') === 'on';
        $text = $on ? 'Közzétéve a webshopon: %d termék.' : 'Levéve a webshopról: %d termék (vázlat).';
        echo '<div class="notice notice-success is-dismissible"><p>'.esc_html(sprintf($text, (int)$_GET['vk_publish_done'])).'</p></div>';
    }

    function ajax_toggle(){
        $id = (int)($_POST['product'] ?? 0);
        $publish = !empty($_POST['publish']) && $_POST['publish'] !== '0';
        if (!check_ajax_referer('vk_publish', 'nonce', false) || !$id || !current_user_can('edit_post', $id) || ($publish && !current_user_can('publish_products'))) wp_send_json_error([ 'message' => 'Nincs jogosultság.' ], 403);
        if (!self::set_published($id, $publish)) wp_send_json_error([ 'message' => 'Nem sikerült menteni.' ], 500);
        wp_send_json_success([ 'published' => get_post_status($id) === 'publish' ]);
    }

    function ajax_free_toggle(){
        $id = (int)($_POST['product'] ?? 0);
        $on = !empty($_POST['free']) && $_POST['free'] !== '0';
        if (!check_ajax_referer('vk_publish', 'nonce', false) || !$id || !current_user_can('edit_post', $id)) wp_send_json_error([ 'message' => 'Nincs jogosultság.' ], 403);
        update_post_meta($id, Shipping::FREE, $on ? 'yes' : 'no');
        wc_delete_product_transients($id);
        if (class_exists(__NAMESPACE__.'\\Feed')) Feed::flush();
        wp_send_json_success([ 'free' => get_post_meta($id, Shipping::FREE, true) === 'yes' ]);
    }

    function script(){
        if (!self::is_products_screen()) return;
        ?>
        <style>
            .column-vk_publish { width: 6.5rem; text-align: center; }
            .column-vk_free { width: 7.5rem; text-align: center; }
            .vk-free-cat { display: block; color: #646970; }
            .vk-publish { display: inline-flex; padding: 4px; cursor: pointer; }
            .vk-publish input { margin: 0 !important; width: 18px; height: 18px; }
            .vk-publish.is-busy { opacity: .5; pointer-events: none; }
            tr.vk-unpublished td { background: #fff8e5; }
        </style>
        <script>
        jQuery(function($){
            var nonce = <?php echo wp_json_encode(wp_create_nonce('vk_publish')); ?>;
            function mark($box){ $box.closest('tr').toggleClass('vk-unpublished', !$box.prop('checked')); }
            $('.vk-publish-toggle').each(function(){ mark($(this)); });
            $(document).on('click', '.vk-publish', function(e){ e.stopPropagation(); });
            $(document).on('change', '.vk-free-toggle', function(){
                var $box = $(this), $label = $box.closest('.vk-publish'), want = $box.prop('checked');
                $label.addClass('is-busy');
                $.post(ajaxurl, { action: 'vk_free_toggle', nonce: nonce, product: $box.data('product'), free: want ? 1 : 0 }).done(function(r){
                    if (!r || !r.success){ $box.prop('checked', !want); window.alert((r && r.data && r.data.message) || 'Nem sikerült menteni.'); return; }
                    $box.prop('checked', r.data.free);
                }).fail(function(){ $box.prop('checked', !want); window.alert('Nem sikerült menteni (kapcsolati hiba).'); }).always(function(){ $label.removeClass('is-busy'); });
            });
            $(document).on('change', '.vk-publish-toggle', function(){
                var $box = $(this), $label = $box.closest('.vk-publish'), want = $box.prop('checked');
                $label.addClass('is-busy');
                $.post(ajaxurl, { action: 'vk_publish_toggle', nonce: nonce, product: $box.data('product'), publish: want ? 1 : 0 }).done(function(r){
                    if (!r || !r.success){ $box.prop('checked', !want); window.alert((r && r.data && r.data.message) || 'Nem sikerült menteni.'); return; }
                    $box.prop('checked', r.data.published);
                    $label.attr('title', r.data.published ? 'Látszik a webshopon – kattints a levételhez' : 'Nem látszik a webshopon – kattints a közzétételhez');
                }).fail(function(){ $box.prop('checked', !want); window.alert('Nem sikerült menteni (kapcsolati hiba).'); }).always(function(){ $label.removeClass('is-busy'); mark($box); });
            });
        });
        </script>
        <?php
    }
}
