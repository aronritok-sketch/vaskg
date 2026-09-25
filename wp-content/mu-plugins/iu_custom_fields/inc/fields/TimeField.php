<?php

namespace IUCustomFields;

class TimeField extends Field {

	function get_html(){
		echo '<input '.$this->render_name().' type="time" class="iu_field_element" value="'.$this->value.'" />';
	}

}