<?php

namespace IUSettings;

class Validator {

	private static $types = [
		'empty'
	];

	static function validate($value, $types){
		foreach($types as $type => $message){
			if (is_callable($message)) {
				$error = call_user_func_array($message, [ $value ]);
			} else if (!in_array($type, self::$types)){
				continue;
			} else {
				$error = self::$type($value) ? $message : false;
			}

			if (false !== $error){
				return $error;
			}
		}

		return false;
	}

	private static function empty($value){
		return empty($value);
	}

}