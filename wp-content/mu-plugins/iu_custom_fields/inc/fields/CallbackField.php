<?php

namespace IUCustomFields;

class CallbackField extends Field {
	private $render;

	function __construct($name, $args){
		parent::__construct($name, $args);

		if (isset($args['render'])) $this->render = $args['render'];
	}

	function get_html(){
		if (isset($this->render) && is_callable($this->render)) call_user_func_array($this->render, []);
	}

}