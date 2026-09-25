<?php

global $post;

$atts = wp_parse_args($attributes, [
	'params' => '',
	'previous' => '',
	'next' => '',
	'marginTop' => '',
    'marginBottom' => '',
    'paddingTop' => '',
    'paddingBottom' => '',
    'paddingleft' => '',
    'paddingRight' => ''
]);

$params = json_decode('{'.trim($atts['params']).'}', true);
$params['fields'] = 'ids';
$params['posts_per_page'] = -1;
$params['numberposts'] = -1;
$query = new \WP_Query($params);

$posts = $query->get_posts();
$index = array_search($post->ID, $posts);

$style = '';
    if ($atts['marginTop'] !== '') $style .= 'margin-top:'.$atts['marginTop'].';';
    if ($atts['marginBottom'] !== '') $style .= 'margin-bottom:'.$atts['marginBottom'].';';
    if ($atts['paddingTop'] !== '') $style .= 'padding-top:'.$atts['paddingTop'].';';
    if ($atts['paddingLeft'] !== '') $style .= 'padding-left:'.$atts['paddingLeft'].';';
    if ($atts['paddingRight'] !== '') $style .= 'padding-right:'.$atts['paddingRight'].';';
    if ($atts['paddingBottom'] !== '') $style .= 'padding-bottom:'.$atts['paddingBottom'].';';

echo '<div class="iu-post-navigation" '.(!empty($style) ? 'style="'.$style.'"' : '').'>';

if ($index == 0){
	echo '<span class="prev placeholder">&nbsp;</span>';
} else {
	$p = get_post($posts[$index-1]);
	$prev = empty($atts['previous']) ? '&larr; '.wp_html_excerpt($p->post_title, 36, '...') : $atts['previous'];
	echo '<a class="prev" href="'.get_permalink($p->ID).'" title="'.$p->post_title.'">'.$prev.'</a>';
}

if ($index == count($posts)-1){
	echo '<span class="next placeholder">&nbsp;</span>';
} else {
	$p = get_post($posts[$index+1]);
	$next = empty($atts['next']) ? wp_html_excerpt($p->post_title, 36, '...').' &rarr;' : $atts['next'];
	echo '<a class="next" href="'.get_permalink($p->ID).'" title="'.$p->post_title.'">'.$next.'</a>';
}

echo '</div>';