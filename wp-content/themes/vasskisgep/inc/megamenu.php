<?php

namespace VasskisgepTheme;

/**
 * Megamenü a főmenü „Termékek” pontjához (a Termékek oldalra mutató menüpont).
 *
 * Fülek: a főkategóriák + „Gyártók”. Egy fülön oszloponként a második szintű kategóriák,
 * alattuk a harmadik szint csoportcímként, a negyedik szint linkként. A tartalom a
 * termékkategóriákból épül (sorrend: `order` term meta), a módosításuk után frissül.
 *
 * Asztali nézetben (992px felett) a keretrendszer legördülője helyett jelenik meg;
 * mobilon a menü saját almenüje marad.
 */
class MegaMenu {
    const HIDDEN_CATEGORIES = [ 'egyeb', 'uncategorized' ];

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_filter('nav_menu_css_class', [$this, 'item_class'], 10, 4);
        add_filter('walker_nav_menu_start_el', [$this, 'append_panel'], 10, 4);
        add_action('wp_enqueue_scripts', [$this, 'enqueue'], 110);

        // A gyorsítótárazott panel frissítése kategória / márka módosításakor.
        foreach(['saved_term', 'delete_term', 'added_term_meta', 'updated_term_meta', 'deleted_term_meta', 'update_option_home'] as $hook){
            add_action($hook, [__CLASS__, 'flush']);
        }
    }

    const CACHE_KEY = 'vk_megamenu';

    static function flush(){
        delete_transient(self::CACHE_KEY);
    }

    function enqueue(){
        $dir = get_stylesheet_directory();
        $uri = get_stylesheet_directory_uri();
        wp_enqueue_script('vasskisgep-megamenu', $uri.'/assets/megamenu.js', [], filemtime($dir.'/assets/megamenu.js'), true);
    }

    private function is_shop_item($item){
        if (!function_exists('wc_get_page_id')) return false;
        $shop = wc_get_page_id('shop');
        return (int)$item->menu_item_parent === 0 && $item->object === 'page' && (int)$item->object_id === $shop && $shop > 0;
    }

    function item_class($classes, $item, $args, $depth){
        if ($depth === 0 && $this->is_shop_item($item)) $classes[] = 'vk-has-mega';
        return $classes;
    }

    function append_panel($output, $item, $depth, $args){
        if ($depth !== 0 || !$this->is_shop_item($item)) return $output;
        return $output.self::panel();
    }

    /** A panel HTML-je (gyorsítótárazva, a kategóriák/márkák módosításakor frissül). */
    static function panel(){
        $key = self::CACHE_KEY;
        // A téma új változata (a fájl módosítása) is frissíti: a tárolt panel a régi kóddal készült.
        $version = (string)filemtime(__FILE__);
        $cached = get_transient($key);
        if (is_array($cached) && ($cached['version'] ?? '') === $version) return $cached['html'];

        $tabs = [];
        foreach(self::children(0) as $top){
            $tabs[] = [ 'id' => 'cat-'.$top->term_id, 'label' => $top->name, 'html' => self::category_tab($top) ];
        }
        $brands = self::brands_tab();
        if ($brands !== '') $tabs[] = [ 'id' => 'brands', 'label' => 'Gyártók', 'html' => $brands ];
        if (empty($tabs)) return '';

        $nav = '';
        $panes = '';
        foreach($tabs as $i => $tab){
            $nav .= '<button type="button" class="vk-mega-tab'.($i === 0 ? ' vk-active' : '').'" role="tab" aria-selected="'.($i === 0 ? 'true' : 'false').'" data-tab="'.esc_attr($tab['id']).'">'.esc_html($tab['label']).'</button>';
            $panes .= '<div class="vk-mega-pane'.($i === 0 ? ' vk-active' : '').'" role="tabpanel" data-tab="'.esc_attr($tab['id']).'">'.$tab['html'].'</div>';
        }

        $html = '<div class="vk-mega" aria-label="Termékkategóriák">
            <div class="vk-mega-head">
                <div class="vk-mega-tabs" role="tablist">'.$nav.'</div>
                <button type="button" class="vk-mega-close" aria-label="Bezárás"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M5 5l14 14M19 5 5 19"/></svg></button>
            </div>
            '.$panes.'
        </div>';

        set_transient($key, [ 'version' => $version, 'html' => $html ], WEEK_IN_SECONDS);
        return $html;
    }

    private static function children($parent){
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'parent' => $parent,
            'hide_empty' => false,
            'orderby' => 'meta_value_num',
            'meta_key' => 'order'
        ]);
        // A sorrend nélküli kategóriák is látszódjanak (a meta_key szűrés kihagyná őket).
        $all = get_terms([ 'taxonomy' => 'product_cat', 'parent' => $parent, 'hide_empty' => false, 'orderby' => 'name' ]);
        if (is_wp_error($terms)) $terms = [];
        if (is_wp_error($all)) $all = [];
        $ids = wp_list_pluck($terms, 'term_id');
        foreach($all as $term){
            if (!in_array($term->term_id, $ids, true)) $terms[] = $term;
        }
        return array_values(array_filter($terms, function($term){
            return !in_array($term->slug, self::HIDDEN_CATEGORIES, true);
        }));
    }

    /** Menübeli név: a kategória „vk_menu_label” term metája (rövid név), különben a neve. */
    private static function link($term, $class){
        $label = trim((string)get_term_meta($term->term_id, 'vk_menu_label', true));
        return '<a class="'.esc_attr($class).'" href="'.esc_url(get_term_link($term)).'">'.esc_html($label !== '' ? $label : $term->name).'</a>';
    }

    private static function category_tab($top){
        $columns = '';
        foreach(self::children($top->term_id) as $column){
            $groups = '';
            $loose = '';
            foreach(self::children($column->term_id) as $group){
                $items = self::children($group->term_id);
                if (empty($items)){
                    $loose .= '<li>'.self::link($group, 'vk-mega-link').'</li>';
                    continue;
                }
                $links = '';
                foreach($items as $item) $links .= '<li>'.self::link($item, 'vk-mega-link').'</li>';
                // „Összes …” link a csoport alatt, ha a kategóriának van ilyen felirata (vk_menu_all).
                $all = trim((string)get_term_meta($group->term_id, 'vk_menu_all', true));
                if ($all !== '') $links .= '<li class="vk-mega-more"><a href="'.esc_url(get_term_link($group)).'">'.esc_html($all).' <span aria-hidden="true">›</span></a></li>';
                $groups .= '<div class="vk-mega-group">'.self::link($group, 'vk-mega-group-title').'<ul>'.$links.'</ul></div>';
            }
            if ($loose !== '') $groups = '<div class="vk-mega-group"><ul>'.$loose.'</ul></div>'.$groups;
            $columns .= '<div class="vk-mega-col">'.self::link($column, 'vk-mega-col-title').$groups.'</div>';
        }

        return '<div class="vk-mega-cols">'.$columns.'</div>';
    }

    private static function brands_tab(){
        if (!taxonomy_exists('product_brand')) return '';
        $brands = get_terms([ 'taxonomy' => 'product_brand', 'hide_empty' => false, 'orderby' => 'name' ]);
        if (is_wp_error($brands) || empty($brands)) return '';

        $html = '';
        foreach($brands as $brand){
            $logo = (int)get_term_meta($brand->term_id, 'thumbnail_id', true);
            $inner = $logo ? wp_get_attachment_image($logo, 'medium', false, [ 'alt' => $brand->name, 'loading' => 'lazy' ]) : '';
            $inner = $inner !== '' ? $inner : '<span>'.esc_html(mb_strtoupper(wp_specialchars_decode($brand->name, ENT_QUOTES))).'</span>';
            $html .= '<a class="vk-mega-brand" href="'.esc_url(get_term_link($brand)).'" title="'.esc_attr($brand->name).'">'.$inner.'</a>';
        }
        return '<div class="vk-mega-brands">'.$html.'</div>';
    }
}
