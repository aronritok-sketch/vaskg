<?php

namespace IUSettings;

abstract class Field {
	protected $name;
	protected $type;
	protected $label;
	protected $value;
	protected $placeholder;
	protected $help;
	protected $default;

	function __construct($name, $args){
		$this->name = $name;
		$this->type = $args['type'];
		$this->label = !empty($args['label']) ? $args['label'] : '';
		
		if (isset($args['placeholder'])) $this->placeholder = $args['placeholder'];
		if (isset($args['help'])) $this->help = $args['help'];
		if (isset($args['default'])) $this->default = $args['default'];
	}

	function render_label(){
		return '<label for="'.$this->name.'">'.$this->label.'</label>';
	}

	function render(){
		ob_start();

		$this->get_html();
		echo $this->render_error();
		echo $this->render_help();

		$this->add_scripts();
		return ob_get_clean();
	}

	abstract protected function get_html();
	
	protected function add_scripts(){}

	protected function render_help(){
		if (empty($this->help)) return '';
		return '<p class="description">'.$this->help.'</p>';
	}

	protected function render_error(){
		return '<p class="error" style="display: none;color: #b32d2e;"></p>';
	}

	protected function render_placeholder(){
		if (empty($this->placeholder)) return '';
		return 'placeholder="'.$this->placeholder.'"';
	}

	protected function render_name(){
		return 'name="'.$this->name.'"';
	}

	function set_value($value){
		$this->value = $value;
		if (isset($this->default) && is_null($value)){
			$this->value = $this->default;
		}
	}

	function fetch_value($id){
		$value = Settings::get($id, $this->name);
		$this->set_value($value);
	}

	function get_value(){
		if (!isset($this->value)){
			$this->fetch_value($post_id);
		}

		return $this->value;
	}

}