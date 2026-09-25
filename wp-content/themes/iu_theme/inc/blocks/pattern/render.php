<?php

(function() use ($attributes) {

	if (empty($attributes['ref'])) return;

	$pattern = get_post($attributes['ref']);
	echo apply_filters('the_content', $pattern->post_content);

})();