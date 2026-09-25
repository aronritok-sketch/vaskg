<?php

namespace IUSettings;

class Fields {

	private static $types = [
		'text' => 'TextField',
		'textarea' => 'TextareaField',
		'image' => 'ImageField',
		'callback' => 'CallbackField',
		'checkbox' => 'CheckboxField',
		'password' => 'PasswordField',
		'select' => 'SelectField'
	];

	static function render($id){
		global $iu_settings;
		$fields = $iu_settings[$id]['fields'];

		if (empty($fields)) return;

		$first_field = array_values($fields)[0];
		if ($first_field['type'] == 'tab'){
			$tabs = array_map(function($item){
				return $item['title'];
			}, $fields);

			self::render_tabs($tabs);
			self::render_tab_contents($id, $fields);
		} elseif ($first_field['type'] == 'category') {
			self::render_categories($id, $fields);
		} else {
			self::render_fields($id, $fields);
		}
	}

	private static function render_tabs($tabs){
		echo '<h2 class="nav-tab-wrapper">';
		foreach(array_values($tabs) as $index => $tab){
			echo '<a class="nav-tab '.($index === 0 ? 'nav-tab-active' : '').'" style="cursor: pointer;">'.$tab.'</a>';
		}
		echo '</h2>';
	}

	private static function render_tab_contents($id, $fields){
		foreach(array_values($fields) as $index => $tab){
			echo '<div class="tab-contents '.($index !== 0 ? 'hidden' : '').'">';
				self::render_fields($id, $tab['fields']);
			echo '</div>';
		}
	}

	private static function render_categories($id, $fields){
		foreach($fields as $category){
			echo '<h2 class="title">'.$category['title'].'</h2>';
			echo isset($category['help']) ? '<p>'.$category['help'].'</p>' : '';
			self::render_fields($id, $category['fields']);
		}
	}

	private static function render_fields($id, $fields){
		echo '<table class="form-table" role="presentation">';
		foreach($fields as $name => $args){
			$field = self::field_instance($name, $args);
			$field->fetch_value($id);
			
			echo '<tr>';
				echo '<th scope="row">';
					echo $field->render_label();
				echo '</th>';
				echo '<td class="iu-settings-field-'.$args['type'].'">';
					echo $field->render();
				echo '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}

	private static function field_instance($name, $args){
		require_once(__DIR__.'/fields/Field.php');

		$class = self::$types[$args['type']];
		require_once(__DIR__.'/fields/'.$class.'.php');

		$class_with_namespace = __NAMESPACE__.'\\'.$class;
		$field = new $class_with_namespace($name, $args);

		return $field;
	}

	static function validate($id, $data){
		global $iu_settings;
		return self::validate_recursive($iu_settings[$id]['fields'], $data);
	}

	private static function validate_recursive($fields, $data){
		$errors = [];
		foreach($fields as $name => $field){
			if ($field['type'] === 'category' || $field['type'] === 'tab'){
				$errors += self::validate_recursive($field['fields'], $data);
			} else {
				if (!isset($field['validate'])) continue; 
				$error = Validator::validate($data[$name], $field['validate']);
				if (false != $error) $errors[$name] = $error;
			}
		}
		return $errors;
	}

	static function get_values($id, $data){
		global $iu_settings;
		return self::get_values_recursive($iu_settings[$id]['fields'], $data);
	}

	private static function get_values_recursive($fields, $data){
		$values = [];
		foreach($fields as $name => $field){
			if ($field['type'] === 'category' || $field['type'] === 'tab'){
				$values += self::get_values_recursive($field['fields'], $data);
			} else {
				if ((!isset($data[$name]) || is_null($data[$name])) && isset($field['default'])) {
					$values[$name] = $field['default'];
				} elseif (isset($data[$name])) {
					$values[$name] = $data[$name];
				}
			}
		}

		return $values;
	}

}