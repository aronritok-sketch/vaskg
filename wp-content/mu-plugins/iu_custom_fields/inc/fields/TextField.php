<?php

namespace IUCustomFields;

class TextField extends Field {

	function get_html(){
		echo '<input '.$this->render_name().' type="text" class="iu_field_element widefat" '.$this->render_placeholder().' value="'.$this->value.'" />';
	}

}