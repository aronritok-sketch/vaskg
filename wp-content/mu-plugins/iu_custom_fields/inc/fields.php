<?php

namespace IUCustomFields;

class Fields {

	private static $types = [
			'text' => 'TextField',
			'textarea' => 'TextareaField',
			'date' => 'DateField',
			'time' => 'TimeField',
			'datetime' => 'DatetimeField',
			'select' => 'SelectField',
			'image' => 'ImageField',
			'file' => 'FileField',
			'repeater' => 'RepeaterField',
			'callback' => 'CallbackField'
	];

	static function add_to_post_types($post_types, $name, $args){
		global $iucf_post_type_custom_fields;

		if (!is_array($iucf_post_type_custom_fields)){
			$iucf_post_type_custom_fields = [];
		}

		if (!is_array($post_types)){
			$post_types = [ $post_types ];
		}

		foreach($post_types as $post_type){
			if (!array_key_exists($post_type, $iucf_post_type_custom_fields)){
				$iucf_post_type_custom_fields[$post_type] = [];
			}
			$iucf_post_type_custom_fields[$post_type][$name] = $args;
		}
	}

	static function field_instance($field_name, $args){
		require_once(__DIR__.'/fields/Field.php');

		$class_name = self::$types[$args['type']];
		require_once(__DIR__.'/fields/'.$class_name.'.php');

		$class_name_with_namespace = __NAMESPACE__.'\\'.$class_name;
		$field = new $class_name_with_namespace($field_name, $args);

		return $field;
	}

	static function get_html($field_name, $args, $post_id){
		$field = self::field_instance($field_name, $args);
		$field->fetch_value($post_id);

		return $field->render();
	}

	static function save($post_id, $field_name, $args, $value){
		$field = self::field_instance($field_name, $args);
		$field->save($post_id, $value);
	}

}