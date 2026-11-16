<?php

namespace VasskisgepTheme;

/**
 * Nagyker árak a B2BKing bővítménnyel (Dávid / István, 2026-11-16: a régi Joomla-boltban vásárlói csoportonként
 * külön ár volt; Laci a B2BKing-et tette fel hozzá).
 *
 * – Megjelenítés: a belépett B2B vásárló (B2BKing: „b2bking_b2buser” = yes, csoport: „b2bking_customergroup”), ha a
 *   terméknek van csoportára, két sort lát: „Listaár: X Ft” és „Nagyker ár: Y Ft” (a vásárlás a nagyker áron megy –
 *   ezt a B2BKing intézi).
 * – Átvétel a régi boltból (diag=b2b&apply=<csoport-azonosító | new>): a régi „Nagyker” csoport árai (`_vk_wholesale_price`,
 *   bruttó) a B2BKing csoportárába, ahol még üres; a régi „Nagyker” csoport vásárlói (a régi fiók → `_vk_old_user_id`)
 *   B2B vásárlóként ebbe a csoportba.
 * – A kezdőlapi termékkarusszelek gyorsítótára csoportonként külön (Shop::cached_block a self::cache_key()-t nézi).
 */
class Wholesale {
    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_filter('woocommerce_get_price_html', [$this, 'price_html'], 10001, 2);
        add_action('wp_head', [$this, 'css']);
    }

    static function active(){ return defined('B2BKINGCORE_DIR') || class_exists('\B2bkingcore') || post_type_exists('b2bking_group'); }

    /** A bejelentkezett vásárló B2B csoportja (0: nem B2B). */
    static function user_group(){
        if (!is_user_logged_in()) return 0;
        $uid = get_current_user_id();
        if (get_user_meta($uid, 'b2bking_account_type', true) === 'subaccount') $uid = (int)get_user_meta($uid, 'b2bking_account_parent', true);
        if (get_user_meta($uid, 'b2bking_b2buser', true) !== 'yes') return 0;
        return (int)get_user_meta($uid, 'b2bking_customergroup', true);
    }

    /** A gyorsítótár-kulcs része: vendég / B2B csoport. */
    static function cache_key(){ $g = self::user_group(); return $g ? 'b2b'.$g : ''; }

    static function group_price($product, $group){
        if (!$product || !$group) return '';
        $price = get_post_meta($product->get_id(), 'b2bking_regular_product_price_group_'.$group, true);
        $sale = get_post_meta($product->get_id(), 'b2bking_sale_product_price_group_'.$group, true);
        return $sale !== '' && (float)$sale > 0 ? $sale : ($price !== '' && (float)$price > 0 ? $price : '');
    }

    function price_html($html, $product){
        if (is_admin() && !wp_doing_ajax()) return $html;
        $group = self::user_group();
        if (!$group || !$product || $product->is_type('variable')) return $html;
        $b2b = self::group_price($product, $group);
        if ($b2b === '') return $html;
        // Listaár: a termék saját (nem csoport-) ára – akciós, ha épp akciós.
        $id = $product->get_id();
        $regular = (string)get_post_meta($id, '_regular_price', true);
        $sale = (string)get_post_meta($id, '_sale_price', true);
        $list = $sale !== '' && (float)$sale > 0 && $product->is_on_sale('edit') ? $sale : $regular;
        if ($list === '' || (float)$list <= 0) return $html;
        $list_html = wc_price(wc_get_price_to_display($product, [ 'price' => (float)$list ]));
        $b2b_html = wc_price(wc_get_price_to_display($product, [ 'price' => (float)$b2b ]));
        return '<span class="vk-b2b-prices"><span class="vk-b2b-list">Listaár: '.$list_html.'</span><span class="vk-b2b-price"><span class="vk-b2b-label">Nagyker ár:</span> '.$b2b_html.'</span></span>';
    }

    function css(){
        if (!self::user_group()) return;
        echo '<style>.vk-b2b-prices{display:inline-flex;flex-direction:column;gap:.15em;line-height:1.15}.vk-b2b-list{font-size:.55em;font-weight:500;opacity:.75}.vk-b2b-label{font-size:.55em;font-weight:600;color:var(--wp--preset--color--primary,#f34a17);text-transform:none}</style>';
    }

    /* ---------- Állapot és átvétel (diag=b2b) ---------- */

    static function groups(){
        $out = [];
        foreach(get_posts([ 'post_type' => 'b2bking_group', 'post_status' => 'any', 'numberposts' => -1 ]) as $g){
            global $wpdb;
            $out[] = [ 'id' => $g->ID, 'title' => $g->post_title, 'status' => $g->post_status,
                'prices' => (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''", 'b2bking_regular_product_price_group_'.$g->ID)),
                'users' => (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'b2bking_customergroup' AND meta_value = %s", (string)$g->ID)) ];
        }
        return $out;
    }

    static function diagnose($apply = ''){
        global $wpdb;
        $out = [
            'active' => self::active(),
            'groups' => self::groups(),
            'b2b_users' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = 'b2bking_b2buser' AND meta_value = 'yes'"),
            'old_wholesale_prices' => (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '".Catalog::WHOLESALE."' AND meta_value <> ''"),
        ];
        if ($apply !== '') $out['applied'] = self::apply($apply);
        return $out;
    }

    /** A régi nagyker árak és vásárlók átvétele egy B2BKing csoportba (vagy egy új „Nagyker” csoportba). */
    static function apply($target){
        global $wpdb;
        if (!post_type_exists('b2bking_group')) return [ 'error' => 'a B2BKing nincs bekapcsolva' ];
        if ($target === 'new'){
            $group = wp_insert_post([ 'post_type' => 'b2bking_group', 'post_status' => 'publish', 'post_title' => 'Nagyker' ]);
        } else {
            $group = (int)$target;
            if (get_post_type($group) !== 'b2bking_group') return [ 'error' => 'nincs ilyen csoport' ];
        }
        if (!$group || is_wp_error($group)) return [ 'error' => 'a csoport nem jött létre' ];

        $prices = 0;
        foreach($wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '".Catalog::WHOLESALE."' AND meta_value <> ''") as $row){
            $key = 'b2bking_regular_product_price_group_'.$group;
            if (get_post_meta((int)$row->post_id, $key, true) !== '') continue;
            update_post_meta((int)$row->post_id, $key, wc_format_decimal($row->meta_value));
            $prices++;
        }

        $users = 0; $missing = 0;
        if (MigrationData::prefix() !== '' && MigrationData::has('virtuemart_vmuser_shoppergroups') && MigrationData::has('virtuemart_shoppergroups')){
            $old = (int)$wpdb->get_var('SELECT virtuemart_shoppergroup_id FROM '.MigrationData::t('virtuemart_shoppergroups')." WHERE shopper_group_name LIKE '%agyker%' LIMIT 1");
            if ($old){
                $map = [];
                foreach($wpdb->get_results("SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_vk_old_user_id'") as $r) $map[(int)$r->meta_value] = (int)$r->user_id;
                foreach((array)$wpdb->get_col($wpdb->prepare('SELECT virtuemart_user_id FROM '.MigrationData::t('virtuemart_vmuser_shoppergroups').' WHERE virtuemart_shoppergroup_id = %d', $old)) as $old_uid){
                    $uid = $map[(int)$old_uid] ?? 0;
                    if (!$uid){ $missing++; continue; }
                    if (get_user_meta($uid, 'b2bking_b2buser', true) === 'yes' && (int)get_user_meta($uid, 'b2bking_customergroup', true)) continue;
                    update_user_meta($uid, 'b2bking_b2buser', 'yes');
                    update_user_meta($uid, 'b2bking_customergroup', (string)$group);
                    $users++;
                }
            }
        }
        if (class_exists(__NAMESPACE__.'\\Shop')) Shop::flush_block_cache();
        return [ 'group' => (int)$group, 'prices_copied' => $prices, 'users_assigned' => $users, 'old_users_not_found' => $missing ];
    }
}
