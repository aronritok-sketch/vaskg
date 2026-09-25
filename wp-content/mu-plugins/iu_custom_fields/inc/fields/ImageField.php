<?php

namespace IUCustomFields;

class ImageField extends Field {
	private $button_label;
	private $remove_label;

	function __construct($name, $args){
		parent::__construct($name, $args);

		if (isset($args['button_label'])) $this->button_label = $args['button_label'];
		else $this->button_label = 'Select image';
		if (isset($args['remove_label'])) $this->button_label = $args['remove_label'];
		else $this->remove_label = 'Remove image';
	}

	function get_html(){
		echo '<img '.(empty($this->value) ? 'style="display:none;"' : 'src="'.wp_get_attachment_image_url($this->value).'"').'>';
		echo '<button class="components-button is-secondary is-compact" '.(!empty($this->value) ? 'style="display:none;"' : '').'>'.$this->button_label.'</button>';
		echo '<br/><a href="#" class="components-button is-secondary is-small is-destructive iu_image_field_remove" '.(empty($this->value) ? 'style="display:none;"' : '').'>'.$this->remove_label.'</a>';
		echo '<input '.$this->render_name().' type="hidden" class="iu_field_element" value="'.$this->value.'" />';
	}

	function add_scripts(){
		wp_register_script('iucf_image_script', '');
		wp_enqueue_script('iucf_image_script');
		wp_add_inline_script('iucf_image_script','
			document.querySelectorAll(".iu_image_field").forEach(function(wrap){
				var image = wrap.querySelectorAll("img")[0];
				var button = wrap.querySelectorAll("button")[0];
				var remove = wrap.querySelectorAll("a")[0];
				var input = wrap.querySelectorAll("input")[0];
			  	
			  	button.addEventListener("click", function(event){
			  		event.preventDefault();
			  		
			  		var uploader = wp.media({
						title: "'.$this->button_label.'",
						library : {
							type : "image"
						},
						button: {
							text: "'.$this->button_label.'"
						},
						multiple: false
					}).on("select", function(){
						var attachment = uploader.state().get("selection").first().toJSON();
						image.src = attachment.sizes.thumbnail.url;
						image.style.display = "";
						button.style.display = "none";
						remove.style.display = "";
						input.value = attachment.id;
					});

					uploader.open();
			  	});
				remove.addEventListener("click", function(event){
					event.preventDefault();
					image.style.display = "none";
					button.style.display = "";
					remove.style.display = "none";
					input.value = "";
			  	});
			});
		');
	}

}