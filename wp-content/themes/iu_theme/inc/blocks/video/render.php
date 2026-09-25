<?php

$atts = wp_parse_args($attributes, [
	'url' => '',
	'marginTop' => '',
	'marginBottom' => '',
	'paddingTop' => '',
	'paddingBottom' => '',
	'paddingLeft' => '',
	'paddingRight' => ''
]);

$style = [];
if ($atts['marginTop'] !== '') $style[] = 'margin-top:'.$atts['marginTop'];
if ($atts['marginBottom'] !== '') $style[] = 'margin-bottom:'.$atts['marginBottom'];
if ($atts['paddingTop'] !== '') $style[] = 'padding-top:'.$atts['paddingTop'];
if ($atts['paddingLeft'] !== '') $style[] = 'padding-left:'.$atts['paddingLeft'];
if ($atts['paddingRight'] !== '') $style[] = 'padding-right:'.$atts['paddingRight'];
if ($atts['paddingBottom'] !== '') $style[] = 'padding-bottom:'.$atts['paddingBottom'];

$url = do_shortcode($atts['url']);
parse_str(explode('?',$url)[1], $query);

global $post;

echo '<div class="iu-video" '.(!empty($style) ? 'style="'.implode(';',$style).'"' : '').'>';
if (!is_admin() && $post->post_type !== 'iu_template') echo '<iframe src="https://youtube.com/embed/'.$query['v'].'"></iframe>';
echo '</div>';