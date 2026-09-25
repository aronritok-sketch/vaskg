<?php

namespace VasskisgepTheme;

/**
 * Kedvencek és összehasonlítás.
 *
 * A listák sütiben tárolódnak (vk_wishlist, vk_compare: vesszővel elválasztott
 * termékazonosítók), így vendég vásárlóknál is működnek, és az oldal-gyorsítótár
 * sem zavarja őket: a gombok állapotát és a fejléc számlálóit JS állítja be.
 * Bejelentkezett vásárlónál a lista a fiókjához is mentődik (user meta), és
 * belépéskor összefésülődik a süti tartalmával.
 */
class Lists {
    const COMPARE_LIMIT = 4;
    const LISTS = [ 'wishlist' => 'vk_wishlist', 'compare' => 'vk_compare' ];

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('wp_ajax_vasskisgep_list_sync', [$this, 'ajax_sync']);
        add_action('wp_login', [$this, 'merge_on_login'], 10, 2);
    }

    /** A lista termékazonosítói (csak létező, közzétett termékek). */
    static function get($list){
        $cookie = self::LISTS[$list] ?? null;
        if (!$cookie) return [];

        $ids = isset($_COOKIE[$cookie]) ? explode(',', sanitize_text_field(wp_unslash($_COOKIE[$cookie]))) : [];
        if (empty($ids) && is_user_logged_in()){
            $ids = (array)get_user_meta(get_current_user_id(), $cookie, true);
        }

        $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
        $ids = array_values(array_filter($ids, function($id){
            return get_post_type($id) === 'product' && get_post_status($id) === 'publish';
        }));

        if ($list === 'compare') $ids = array_slice($ids, 0, self::COMPARE_LIMIT);
        return $ids;
    }

    /** Bejelentkezett vásárlónál a süti tartalma a fiókba is mentődik. */
    function ajax_sync(){
        check_ajax_referer('vasskisgep-lists', 'security');
        foreach(self::LISTS as $list => $cookie){
            $ids = isset($_POST[$list]) ? array_filter(array_map('absint', explode(',', sanitize_text_field(wp_unslash($_POST[$list]))))) : [];
            update_user_meta(get_current_user_id(), $cookie, array_values(array_unique($ids)));
        }
        wp_send_json_success();
    }

    function merge_on_login($user_login, $user){
        foreach(self::LISTS as $list => $cookie){
            $saved = (array)get_user_meta($user->ID, $cookie, true);
            $current = isset($_COOKIE[$cookie]) ? explode(',', sanitize_text_field(wp_unslash($_COOKIE[$cookie]))) : [];
            $merged = array_values(array_unique(array_filter(array_map('absint', array_merge($current, $saved)))));
            if ($list === 'compare') $merged = array_slice($merged, 0, self::COMPARE_LIMIT);

            update_user_meta($user->ID, $cookie, $merged);
            setcookie($cookie, implode(',', $merged), [
                'expires' => time() + YEAR_IN_SECONDS,
                'path' => COOKIEPATH ?: '/',
                'samesite' => 'Lax'
            ]);
        }
    }

    /** Kedvencek / összehasonlítás gombok egy termékhez. */
    static function buttons($product_id, $compact = false){
        $heart = '<svg width="18" height="16" viewBox="0 0 18 15" fill="none" aria-hidden="true"><path d="M16.75 5.33824C16.75 9.16177 8.75 13.75 8.75 13.75C8.75 13.75 0.75 9.16177 0.75 5.33824C0.75 -0.397053 8.75 -0.397059 8.75 4.30394C8.75 -0.397059 16.75 -0.205885 16.75 5.33824Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        $compare = '<svg width="16" height="16" viewBox="0 0 14 16" fill="none" aria-hidden="true"><rect x="0.75" y="3.375" width="9.625" height="11.375" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M3.375 0.75H11.25C12.3546 0.75 13.25 1.64543 13.25 2.75V12.125" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>';

        return '<div class="vk-product-actions'.($compact ? ' vk-product-actions-compact' : '').'">
            <button type="button" class="vk-list-toggle" data-list="wishlist" data-product="'.(int)$product_id.'" aria-pressed="false" title="Kedvencekhez adom">
                '.$heart.'<span class="vk-list-toggle-label">'.($compact ? '' : '<span class="vk-off">Kedvencekhez adom</span><span class="vk-on">Kedvencek között</span>').'</span>
            </button>
            <button type="button" class="vk-list-toggle" data-list="compare" data-product="'.(int)$product_id.'" aria-pressed="false" title="Összehasonlítom">
                '.$compare.'<span class="vk-list-toggle-label">'.($compact ? '' : '<span class="vk-off">Összehasonlítom</span><span class="vk-on">Összehasonlításban</span>').'</span>
            </button>
        </div>';
    }
}
