<?php

namespace IUSettings;

class CallbackField extends Field {
	private $render;

	function __construct($name, $args){
		parent::__construct($name, $args);
		$this->render = $args['render'];
	}

	function get_html(){
		if (is_callable($this->render)) call_user_func_array($this->render, [$this]);
	}

}