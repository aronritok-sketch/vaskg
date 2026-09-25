<?php

$atts = wp_parse_args($attributes, [
	'taxonomy' => 'post_tag'
]);

$terms = get_terms([
	'taxonomy' => $atts['taxonomy'],
	'orderby' => 'name',
	'order' => 'ASC'
]);

echo '<div class="iu-term-cloud">';
if (!empty($terms)){
	$max_font_size = 3;

	$min = $terms[0]->count;
	$max = $terms[0]->count;
	foreach($terms as $term){
		if ($term->count > $max) $max = $term->count;
		if ($term->count < $min) $min = $term->count;
	}

	foreach($terms as $term){
		$ratio = ($max_font_size-1) * ($term->count - $min) / ($max-$min);
		echo '<a style="font-size: '.($ratio+1).'em;" href="'.get_term_link($term, $atts['taxonomy']).'">'.$term->name.'</a>';
	}
} else {
	echo 'No terms.';
}
echo '</div>';