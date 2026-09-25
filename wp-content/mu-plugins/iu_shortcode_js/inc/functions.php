<?php

function iusjs_add_shortcode($shortcode, $js){
	IUShortcodeJs\Plugin::add($shortcode, $js);
}