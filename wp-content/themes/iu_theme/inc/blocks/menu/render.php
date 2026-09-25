<?php

if (!function_exists('iu_add_html_to_submenu')){
    function iu_add_html_to_submenu($nav){
        return str_replace( '<ul class="sub-menu">', '<ul class="sub-menu"><span class="iu-menu-back dashicons dashicons-arrow-left-alt2"></span>', $nav);
    }
}
if (!function_exists('iu_add_html_to_parent_menu_items')){
    function iu_add_html_to_parent_menu_items($items){
        foreach ($items as $item) {
            if (in_array( 'menu-item-has-children', $item->classes )) {
                $item->title .= '<span class="iu-menu-trigger dashicons dashicons-arrow-right-alt2"></span>';
            }
        }

        return $items;
    }
}

$atts = wp_parse_args( $attributes, [
    'className' => '',
    'menu' => 0,
    'vertical' => false,
    'collapse' => false
]);
    
if ($atts['collapse']){
    echo '<div class="iu-menu-block-wrapper">';
        echo '<span class="mobile-toggle"></span>';
}

echo '<div class="iu-menu-container">';
    echo '<span class="mobile-toggle iu-menu-close"></span>';

add_filter('wp_nav_menu', 'iu_add_html_to_submenu');
add_filter('wp_nav_menu_objects', 'iu_add_html_to_parent_menu_items');

wp_nav_menu([
    'container' => false,
    'menu_class' => 'iu-menu-block '.($atts['vertical'] ? 'vertical' : '').' '.$atts['className'],
    'menu' => $atts['menu']
]);

remove_filter('wp_nav_menu', 'iu_add_html_to_submenu');
remove_filter('wp_nav_menu_objects', 'iu_add_html_to_parent_menu_items');

echo '</div>';

if ($atts['collapse']){
    echo '</div>';
}