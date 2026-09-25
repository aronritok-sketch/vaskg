<?php

add_filter('plugins_list', function($plugins){
	$plugin_dirs = array_filter(glob(__DIR__.'/*'), 'is_dir');

	$muplugins = [];
	foreach($plugin_dirs as $plugin_dir){
		$plugin_data = get_plugin_data($plugin_dir.'/index.php', false, false);
		if (empty($plugin_data['Name'])) continue;

		$muplugins[plugin_basename($plugin_dir.'/index.php')] = $plugin_data;
	}

	uasort($muplugins, '_sort_uname_callback');
	$plugins['mustuse'] = $muplugins;
	return $plugins;
});

add_action('plugins_loaded', function(){
	$plugin_dirs = array_filter(glob(__DIR__.'/*'), 'is_dir');
	foreach($plugin_dirs as $plugin_dir){
		require_once($plugin_dir.'/index.php');
	}
});