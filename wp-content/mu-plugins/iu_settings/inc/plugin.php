<?php

namespace IUSettings;

require_once('fields.php');
require_once('functions.php');
require_once('settings.php');
require_once('validator.php');

class Plugin {

	private static $instance;
	static function get_instance(){
		if (is_null(self::$instance)){
			self::$instance = new self();
		}
		return self::$instance;
	}

	function __construct(){
		add_action('admin_menu', [$this, 'admin_menu']);
		add_action('admin_enqueue_scripts', [$this, 'script']);
		add_action('admin_init', [$this, 'handle_submit']);
	}

	function admin_menu(){
		global $iu_settings;
		if (empty($iu_settings)) return;

		foreach($iu_settings as $id => $args){
			$capability = !empty($args['capability']) ? $args['capability'] : 'manage_options';
			$icon = !empty($args['icon']) ? $args['icon'] : null;
			$position = !empty($args['position']) ? $args['position'] : null;
			$menu_title = !empty($args['menu_title']) ? $args['menu_title'] : $args['title'];

			if (empty($args['location'])){
				add_menu_page($args['title'], $menu_title, $capability, $id, 'ius_settings_page', $icon, $position);
			} else {
				switch($args['location']){
					case 'options': {
						add_options_page($args['title'], $menu_title, $capability, $id, 'ius_settings_page', $icon, $position);
					} break;
					default: {
						add_submenu_page($args['location'], $args['title'], $menu_title, $capability, $id, 'ius_settings_page', $icon, $position);
					}
				}
			}
		}
	}

	function script(){
		global $iu_settings;

		if (!isset($_GET['page']) || !isset($iu_settings[$_GET['page']])) return;
		wp_enqueue_script('iu-settings-script', plugins_url('script.js', __FILE__), [], false, true);
	}

	function handle_submit(){
		global $iu_settings;
		if (!isset($_POST['action']) || !isset($iu_settings[$_POST['action']])) return;
		
		$errors = Settings::submission($_POST['action']);
		if (!empty($errors)) {
			echo json_encode([ 'errors' => $errors ]);
		} else {
			echo json_encode([ 'success' => __('Settings saved.') ]);
		}
		exit;
	}

}