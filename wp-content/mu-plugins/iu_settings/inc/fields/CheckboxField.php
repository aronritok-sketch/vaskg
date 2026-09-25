<?php

namespace IUSettings;

class CheckboxField extends Field {
	function get_html(){
		echo '<input '.$this->render_name().' type="hidden" value="0" />';
		echo '<input '.$this->render_name().' type="checkbox" value="1" '.(empty($this->value) ? '' : 'checked').' />';
	}

}