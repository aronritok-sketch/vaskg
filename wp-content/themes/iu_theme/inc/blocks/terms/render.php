<?php

$atts = wp_parse_args($attributes, [
	'taxonomy' => 'category',
	'all_text' => 'Összes',
	'post_counts' => false,
	'buttons' => false
]);

$blog_id = get_option('page_for_posts', false);

$terms = get_terms([
	'taxonomy' => $atts['taxonomy'],
	'orderby' => 'name',
	'order' => 'ASC'
]);

if ($atts['buttons']){

	echo '<div class="iu-terms iu-button-group'.(!empty($attributes['className']) ? ' '.$attributes['className'] : '').'">';
	if (!empty($blog_id) && !empty($atts['all_text'])) echo '<a class="iu-button '.(is_home() ? 'iu-button-disabled' : 'iu-button-default').'" href="'.get_permalink($blog_id).'">'.$atts['all_text'].'</a>';
	foreach($terms as $term){
		echo '<a class="iu-button '.(is_category($term->term_id) || is_tax($atts['taxonomy'], $term->term_id) ? 'iu-button-disabled' : 'iu-button-default').'" href="'.get_term_link($term, $atts['taxonomy']).'">'.$term->name.''.($atts['post_counts'] ? '<span class="count">('.$term->count.')</span>' : '').'</a>';
	}
	echo '</div>';

} else {

	echo '<div class="iu-terms'.(!empty($attributes['className']) ? ' '.$attributes['className'] : '').'">';
	if (!empty($blog_id) && !empty($atts['all_text'])) echo '<a class="iu-terms-all '.(is_home() ? 'iu-terms-active' : '').'" href="'.get_permalink($blog_id).'">'.$atts['all_text'].'</a>';
	foreach($terms as $term){
		echo '<a class="iu-terms-term '.(is_category($term->term_id) || is_tax($atts['taxonomy'], $term->term_id) ? 'iu-terms-active' : '').'" href="'.get_term_link($term, $atts['taxonomy']).'">'.$term->name.''.($atts['post_counts'] ? '<span class="count">('.$term->count.')</span>' : '').'</a>';
	}
	echo '</div>';

}

