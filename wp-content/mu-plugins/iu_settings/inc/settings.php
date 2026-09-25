<?php

namespace IUSettings;

class Settings {

	static function register($id, $args){
		global $iu_settings;
		$iu_settings[$id] = $args;
	}

	static function page(){
		global $iu_settings;

		$id = $_GET['page'];
		if (!isset($iu_settings[$id])) return;

		?>
			<div class="wrap">
				<h1><?php echo $iu_settings[$id]['title'] ?></h1>
				<div class="notice notice-success is-dismissible" style="display: none;"> 
					<p><strong></strong></p>
					<button type="button" class="notice-dismiss"></button>
				</div>
				<form class="iu-settings-form" action="#">
					<input type="hidden" name="action" value="<?php echo $id ?>" />
					<?php 
							Fields::render($id);
							submit_button();
					?>
				</form>
			</div>
		<?php
	}

	static function get($id = false, $field = false){
		global $iu_settings;

		if (false === $id){
			$id = $_GET['page'];
		}

		$settings = array_map(function($item){ return str_replace("\'", "'", $item); }, get_option($id, []));


		if (false === $field) return $settings;
		if (!isset($settings[$field])) {
			if (isset($iu_settings[$id][$field]) && isset($iu_settings[$id][$field]['default'])) return $iu_settings[$id][$field]['default'];
			return null;
		}

		return $settings[$field];
	}

	static function submission($id){
		$errors = Fields::validate($id, $_POST);
		if (empty($errors)) {
			Settings::save($id);
		}

		return $errors;
	}

	private static function save($id){
		global $iu_settings;

		$settings = Fields::get_values($id, $_POST);

		do_action('iu_settings_save', $id, $settings);
		update_option($id, $settings);
	}

}