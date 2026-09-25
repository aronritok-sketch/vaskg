<?php

namespace IUCustomFields;

class DateField extends Field {

	function get_html(){
		echo '<input '.$this->render_name().' type="date" class="iu_field_element" value="'.$this->value.'" '.$this->render_placeholder().' />';
	}

}