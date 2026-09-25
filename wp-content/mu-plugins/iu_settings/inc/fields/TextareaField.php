<?php

namespace IUSettings;

class TextareaField extends Field {

	function get_html(){
		echo '<textarea '.$this->render_name().' '.$this->render_placeholder().' class="large-text" rows="4">'.$this->value.'</textarea>';
	}

}