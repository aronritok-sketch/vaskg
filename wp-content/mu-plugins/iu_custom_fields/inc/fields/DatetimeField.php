<?php

namespace IUCustomFields;

class DatetimeField extends Field {

	function get_html(){
		if (is_null($this->value)) $value = [''];
		else $value = explode(' ', $this->value);
		
		if (!isset($value[1])) $value[1] = '';

		echo '
			<input type="date" class="iu_field_element" value="'.$value[0].'" />
			<input type="time" class="iu_field_element" value="'.$value[1].'" />
			<input '.$this->render_name().' type="hidden" value="'.$this->value.'" />
		';
	}

	function add_scripts(){
		wp_register_script('iucf_datetime_script', '');
		wp_enqueue_script('iucf_datetime_script');
		wp_add_inline_script('iucf_datetime_script','
		  	document.addEventListener("change", function(event){
		  		if (!event.target.matches(`.iu_datetime_field .iu_field_element`)) return;
		  		let wrap = event.target.parentElement;		  		
		  		let date = wrap.querySelector(`[type="date"]`);
		  		let time = wrap.querySelector(`[type="time"]`);

		  		if (date.value == "" || time.value == ""){
		  			wrap.querySelector(`[type="hidden"]`).value = "";
		  			return;
		  		}
		  			
		  		wrap.querySelector(`[type="hidden"]`).value = date.value + " " + time.value;
		  	});
		');
	}

}