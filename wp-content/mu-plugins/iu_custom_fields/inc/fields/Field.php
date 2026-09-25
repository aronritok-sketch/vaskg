<?php

namespace IUCustomFields;

abstract class Field {
	protected $name;
	protected $type;
	protected $label;
	protected $value;
	protected $placeholder;
	protected $description;
	protected $default;

	function __construct($name, $args){
		$this->name = $name;
		$this->type = $args['type'];
		$this->label = !empty($args['label']) ? $args['label'] : '';
		
		if (isset($args['placeholder'])) $this->placeholder = $args['placeholder'];
		if (isset($args['description'])) $this->description = $args['description'];
		if (isset($args['default'])) $this->default = $args['default'];
	}

	function render(){
		ob_start();
		echo $this->render_wrap_start();
			echo $this->render_label();
			echo $this->render_field_wrap_start();

				$this->get_html();

			echo $this->render_field_wrap_end();
			echo $this->render_description();	
		echo $this->render_wrapper_end();

		$this->add_scripts();
		return ob_get_clean();
	}

	abstract protected function get_html();
	
	protected function add_scripts(){}

	protected function render_wrap_start(){
		return '<div class="iu_field_wrap iu_'.$this->type.'_field_wrap" style="margin-bottom: 12px;">';
	}

	protected function render_wrapper_end(){
		return '</div>';
	}

	protected function render_label(){
		if (empty($this->label)) return '';
		return '<label>'.$this->label.'</label>';
	}

	protected function render_description(){
		if (empty($this->description)) return '';
		return '<i class="iu_field_description">'.$this->description.'</i>';
	}

	protected function render_field_wrap_start(){
		return '<div class="iu_field iu_'.$this->type.'_field">';
	}

	protected function render_field_wrap_end(){
		return '</div>';
	}

	protected function render_placeholder(){
		if (empty($this->placeholder)) return '';
		return 'placeholder="'.$this->placeholder.'"';
	}

	protected function render_name(){
		return 'name="'.$this->name.'"';
	}

	function save($post_id, $value){
		if ($value === '') delete_post_meta($post_id, $this->name);
		else update_post_meta($post_id, $this->name, $value);
	}

	function set_value($value){
		$this->value = $value;
		if (isset($this->default) && is_null($value)){
			$this->value = $this->default;
		}
	}

	function fetch_value($post_id){
		$value = get_metadata_raw('post', $post_id, $this->name, true);
		$this->set_value($value);
	}

	function get_value($post_id){
		if (!isset($this->value)){
			$this->fetch_value($post_id);
		}

		return $this->value;
	}

}