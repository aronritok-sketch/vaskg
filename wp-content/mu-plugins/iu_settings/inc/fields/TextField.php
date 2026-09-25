<?php

namespace IUSettings;

class TextField extends Field {

	function get_html(){
		echo '<input '.$this->render_name().' type="text" class="regular-text" '.$this->render_placeholder().' value="'.$this->value.'" />';
	}

}