<?php

namespace IUSettings;

class SelectField extends Field {
	private $options;

	function __construct($name, $args){
		parent::__construct($name, $args);

		$this->options = $args['options'] ?? [];
	}

	function get_html(){
		echo '<select '.$this->render_name().'>';
		foreach($this->options as $value => $label){
			echo '<option value="'.$value.'" '.($value == $this->value ? 'selected' : '').'>'.$label.'</option>';
		}
		echo '</select>';
	}

}