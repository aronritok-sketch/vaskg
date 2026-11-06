<?php

namespace VasskisgepTheme;

/**
 * Szezon (évszak) kezelése a terméklistában (ügyfélkérés, 2026-11: a „Szerkesztés” csoportművelet panelje nehézkes
 * volt): „Szezon” oszlop négy kapcsolóval (egy kattintás, azonnal ment), csoportműveletek („Szezon + Tél”,
 * „Szezon − Nyár”, „Szezonok törlése”) a lenyíló listában, és szűrés szezon szerint. Az évszakok termékcímkék
 * (Shop::SEASONS), a kezdőlap szezonális sávja ezekből dolgozik.
 */
class SeasonsAdmin {
    const ORDER = [ 'tel', 'tavasz', 'nyar', 'osz' ];

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_filter('manage_edit-product_columns', [$this, 'columns'], 30);
        add_action('manage_product_posts_custom_column', [$this, 'column'], 10, 2);
        add_filter('bulk_actions-edit-product', [$this, 'bulk_actions']);
        add_filter('handle_bulk_actions-edit-product', [$this, 'handle_bulk'], 10, 3);
        add_action('admin_notices', [$this, 'notice']);
        add_action('restrict_manage_posts', [$this, 'filter'], 30);
        add_action('wp_ajax_vk_season_toggle', [$this, 'ajax_toggle']);
        add_action('admin_footer-edit.php', [$this, 'script']);
    }

    private static function is_products_screen(){
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        return $screen && $screen->id === 'edit-product';
    }

    function columns($columns){
        $out = [];
        foreach($columns as $key => $label){
            if ($key === 'product_tag') continue; // a címkék helyett a szezon kapcsolók
            $out[$key] = $label;
            if ($key === 'product_cat') $out['vk_season'] = 'Szezon';
        }
        if (!isset($out['vk_season'])) $out['vk_season'] = 'Szezon';
        return $out;
    }

    function column($column, $post_id){
        if ($column !== 'vk_season') return;
        $current = Shop::product_seasons($post_id);
        echo '<span class="vk-seasons" data-product="'.(int)$post_id.'">';
        foreach(self::ORDER as $slug){
            $on = in_array($slug, $current, true);
            echo '<button type="button" class="vk-season-chip'.($on ? ' is-on' : '').'" data-season="'.esc_attr($slug).'" aria-pressed="'.($on ? 'true' : 'false').'">'.esc_html(Shop::SEASONS[$slug]).'</button>';
        }
        echo '</span>';
    }

    function bulk_actions($actions){
        foreach(self::ORDER as $slug) $actions['vk_season_add_'.$slug] = 'Szezon + '.Shop::SEASONS[$slug];
        foreach(self::ORDER as $slug) $actions['vk_season_remove_'.$slug] = 'Szezon − '.Shop::SEASONS[$slug];
        $actions['vk_season_clear'] = 'Szezonok törlése';
        return $actions;
    }

    function handle_bulk($redirect, $action, $ids){
        if (strpos($action, 'vk_season_') !== 0 || !current_user_can('edit_products')) return $redirect;
        $done = 0;
        foreach(array_map('intval', (array)$ids) as $id){
            if (!current_user_can('edit_post', $id)) continue;
            $seasons = Shop::product_seasons($id);
            if ($action === 'vk_season_clear') $seasons = [];
            elseif (strpos($action, 'vk_season_add_') === 0) $seasons[] = substr($action, strlen('vk_season_add_'));
            elseif (strpos($action, 'vk_season_remove_') === 0) $seasons = array_diff($seasons, [ substr($action, strlen('vk_season_remove_')) ]);
            Shop::set_product_seasons($id, $seasons);
            $done++;
        }
        return add_query_arg('vk_season_done', $done, remove_query_arg('vk_season_done', $redirect));
    }

    function notice(){
        if (!self::is_products_screen() || !isset($_GET['vk_season_done'])) return;
        echo '<div class="notice notice-success is-dismissible"><p>'.esc_html(sprintf('Szezon módosítva %d terméknél.', (int)$_GET['vk_season_done'])).'</p></div>';
    }

    /** Szűrés szezon szerint (termékcímke). */
    function filter($post_type){
        if ($post_type !== 'product') return;
        $current = sanitize_key(wp_unslash($_GET['product_tag'] ?? ''));
        echo '<select name="product_tag"><option value="">Szezon szerint</option>';
        foreach(self::ORDER as $slug) echo '<option value="'.esc_attr($slug).'"'.selected($current, $slug, false).'>'.esc_html(Shop::SEASONS[$slug]).'</option>';
        echo '</select>';
    }

    function ajax_toggle(){
        $id = (int)($_POST['product'] ?? 0);
        $slug = sanitize_key(wp_unslash($_POST['season'] ?? ''));
        if (!check_ajax_referer('vk_season', 'nonce', false) || !$id || !current_user_can('edit_post', $id) || !isset(Shop::SEASONS[$slug])) wp_send_json_error([ 'message' => 'Nincs jogosultság.' ], 403);
        $seasons = Shop::product_seasons($id);
        $seasons = in_array($slug, $seasons, true) ? array_values(array_diff($seasons, [ $slug ])) : array_merge($seasons, [ $slug ]);
        Shop::set_product_seasons($id, $seasons);
        wp_send_json_success([ 'seasons' => Shop::product_seasons($id) ]);
    }

    function script(){
        if (!self::is_products_screen()) return;
        ?>
        <style>
            .column-vk_season { width: 15rem; }
            .vk-seasons { display: flex; flex-wrap: wrap; gap: 4px; }
            .vk-season-chip { padding: 3px 9px; border: 1px solid #c3c4c7; border-radius: 999px; background: #fff; color: #50575e; font-size: 12px; line-height: 1.5; cursor: pointer; }
            .vk-season-chip:hover { border-color: #2271b1; color: #2271b1; }
            .vk-season-chip.is-on { background: #2e7d32; border-color: #2e7d32; color: #fff; }
            .vk-season-chip.is-on[data-season="tel"] { background: #1d4ed8; border-color: #1d4ed8; }
            .vk-season-chip.is-on[data-season="tavasz"] { background: #16a34a; border-color: #16a34a; }
            .vk-season-chip.is-on[data-season="nyar"] { background: #ea8a00; border-color: #ea8a00; }
            .vk-season-chip.is-on[data-season="osz"] { background: #b45309; border-color: #b45309; }
            .vk-season-chip.is-busy { opacity: .5; }
        </style>
        <script>
        jQuery(function($){
            var nonce = <?php echo wp_json_encode(wp_create_nonce('vk_season')); ?>;
            $(document).on('click', '.vk-season-chip', function(e){
                e.preventDefault(); e.stopPropagation();
                var $chip = $(this), $wrap = $chip.closest('.vk-seasons');
                if ($chip.hasClass('is-busy')) return;
                $chip.addClass('is-busy');
                $.post(ajaxurl, { action: 'vk_season_toggle', nonce: nonce, product: $wrap.data('product'), season: $chip.data('season') }).done(function(r){
                    if (!r || !r.success){ window.alert((r && r.data && r.data.message) || 'Nem sikerült menteni.'); return; }
                    $wrap.find('.vk-season-chip').each(function(){
                        var on = r.data.seasons.indexOf($(this).data('season')) !== -1;
                        $(this).toggleClass('is-on', on).attr('aria-pressed', on ? 'true' : 'false');
                    });
                }).fail(function(){ window.alert('Nem sikerült menteni (kapcsolati hiba).'); }).always(function(){ $chip.removeClass('is-busy'); });
            });
        });
        </script>
        <?php
    }
}
