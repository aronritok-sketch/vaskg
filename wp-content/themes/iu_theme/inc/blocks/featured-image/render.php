<?php

global $post;

$atts = wp_parse_args($attributes, [
    'imageID' => null,
    'imageURL' => '',
    'imageThumbURL' => '',
    'crop' => false,
    'width' => '',
    'height' => '',
    'align' => '',
    'marginTop' => '',
    'marginBottom' => '',
    'paddingTop' => '',
    'paddingBottom' => '',
    'paddingLeft' => '',
    'paddingRight' => '',
]);

$className = '';
$style = '';
$wrapStyle = '';
$imageStyle = '';

if ($atts['marginTop'] !== '') $style .= 'margin-top:'.$atts['marginTop'].';';
if ($atts['marginBottom'] !== '') $style .= 'margin-bottom:'.$atts['marginBottom'].';';
if ($atts['paddingTop'] !== '') $style .= 'padding-top:'.$atts['paddingTop'].';';
if ($atts['paddingLeft'] !== '') $style .= 'padding-left:'.$atts['paddingLeft'].';';
if ($atts['paddingRight'] !== '') $style .= 'padding-right:'.$atts['paddingRight'].';';
if ($atts['paddingBottom'] !== '') $style .= 'padding-bottom:'.$atts['paddingBottom'].';';

if ($atts['crop']){
    $className .= 'iu-image-crop';
    if ($atts['width'] !== '') $wrapStyle .= 'width:'.$atts['width'].';';
    if ($atts['height'] !== '') $wrapStyle .= 'height:0;padding-top:'.$atts['height'].';';
} else {
    if ($atts['width'] !== '') $imageStyle .= 'width:'.$atts['width'].';';
    if ($atts['height'] !== '') $wrapStyle .= 'height:'.$atts['height'].';';
}

if ($atts['align'] == 'center') $className .= ' iu-image-justify-center';
else if ($atts['align'] == 'right') $className .= ' iu-image-justify-flex-end';

if (!empty($attributes['className'])) $className .= ' '.$attributes['className'];

$image_id = get_post_thumbnail_id($post->ID);
if (empty($image_id)) $image_id = $attributes['imageID'];

echo '<div class="iu-featured-image iu-image '.(!empty($className) ? $className : '').'" '.(!empty($style) ? 'style="'.$style.'"':'').'>
    <div class="iu-image-wrap" '.(!empty($wrapStyle) ? 'style="'.$wrapStyle.'"' : '').'>
        '.(!empty($image_id && is_singular()) ? '<img src="'.wp_get_attachment_image_url($image_id, 'full').'" '.(!empty($imageStyle) ? 'style="'.$imageStyle.'"' : '').'/>' : '').'
    </div>
</div>';