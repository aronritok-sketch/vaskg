<?php

(function() use ($attributes){

    $atts = wp_parse_args($attributes, [
        'placeholder' => 'Keresés...',
        'marginTop' => '',
        'marginBottom' => '',
        'paddingTop' => '',
        'paddingBottom' => '',
        'paddingleft' => '',
        'paddingRight' => '',
    ]);

    $style = '';
    if ($atts['marginTop'] !== '') $style .= 'margin-top:'.$atts['marginTop'].';';
    if ($atts['marginBottom'] !== '') $style .= 'margin-bottom:'.$atts['marginBottom'].';';
    if ($atts['paddingTop'] !== '') $style .= 'padding-top:'.$atts['paddingTop'].';';
    if ($atts['paddingLeft'] !== '') $style .= 'padding-left:'.$atts['paddingLeft'].';';
    if ($atts['paddingRight'] !== '') $style .= 'padding-right:'.$atts['paddingRight'].';';
    if ($atts['paddingBottom'] !== '') $style .= 'padding-bottom:'.$atts['paddingBottom'].';';

    echo '<div class="iu-search" '.(!empty($style) ? 'style="'.$style.'"' : '').'>
    	<form action="'.home_url().'" class="iu-form">
    		<div class="iu-form-field iu-form-text">
    			<input type="text" required placeholder="'.$atts['placeholder'].'" name="s" '.(isset($_GET['s']) ? 'value="'.$_GET['s'].'"' : '').'/>
    		</div>
    		<button type="submit" class="iu-button iu-button-default">
    			<span class="dashicons dashicons-search"></span>
    		</button>
    	</form>
    </div>';

})();