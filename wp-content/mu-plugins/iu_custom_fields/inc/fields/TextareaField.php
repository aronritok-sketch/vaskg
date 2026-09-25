<?php

namespace IUCustomFields;

class TextareaField extends Field {

	function get_html(){
		echo '<textarea '.$this->render_name().' type="textarea" class="iu_field_element widefat" rows="4" '.$this->render_placeholder().'>'.$this->value.'</textarea>';
	}

}