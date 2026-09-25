<?php

namespace IUSettings;

class PasswordField extends Field {

	function get_html(){
		echo '<input '.$this->render_name().' type="password" class="regular-text" '.$this->render_placeholder().' value="'.$this->value.'" />';
	}

}