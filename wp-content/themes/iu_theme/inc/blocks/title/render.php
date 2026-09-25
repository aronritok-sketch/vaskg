<?php

$atts = wp_parse_args($attributes, [
	'marginTop' => '',
    'marginBottom' => '',
    'paddingTop' => '',
    'paddingBottom' => '',
    'paddingLeft' => '',
    'paddingRight' => '',
]);

$style = [];
if ($atts['marginTop'] !== '') $style[] = 'margin-top:'.$atts['marginTop'];
if ($atts['marginBottom'] !== '') $style[] = 'margin-bottom:'.$atts['marginBottom'];
if ($atts['paddingTop'] !== '') $style[] = 'padding-top:'.$atts['paddingTop'];
if ($atts['paddingLeft'] !== '') $style[] = 'padding-left:'.$atts['paddingLeft'];
if ($atts['paddingRight'] !== '') $style[] = 'padding-right:'.$atts['paddingRight'];
if ($atts['paddingBottom'] !== '') $style[] = 'padding-bottom:'.$atts['paddingBottom'];

global $post;
$title = !empty($post) ? get_the_title() : '';

if (is_home()){
    $title = get_post(get_option('page_for_posts'))->post_title;
} elseif(is_search()){
    $title = 'Keresés erre: '.$_GET['s'];
} elseif(is_tag()){
    $title = 'Címke: '.get_queried_object()->name;
}elseif (is_category() || is_tax()){
    $title = 'Kategória: '.get_queried_object()->name;
}

$title = apply_filters('iu_title', $title);

echo '<'.$atts['heading'].' class="iu-title iu-heading iu-text-align-'.$atts['align'].'" '.(!empty($style) ? 'style="'.implode(';', $style).'"' : '').'>'.$title.'</'.$atts['heading'].'>';