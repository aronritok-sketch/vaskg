<?php

namespace IUCustomFields;

class RepeaterField extends Field {
	private $fields;
	private $add_before;
	private $add_after;

	function __construct($name, $args){
		parent::__construct($name, $args);

		$this->fields = !empty($args['fields']) ? $args['fields'] : [];
		$this->add_before = !isset($args['add_before']) ? true : $args['add_before'];
		$this->add_after = !isset($args['add_after']) ? true : $args['add_after'];
	}

	private function render_template(){
		echo '<script type="template">';
			echo '<div class="iu_repeater_field_row">';
				foreach($this->fields as $field_name => $field){
					$field = Fields::field_instance($this->name.'_??_'.$field_name, $field);
					
					$field->set_value(null);
					echo $field->render();
				}
				echo '<button class="components-button is-secondary is-small is-destructive iu_repeater_field_button_remove">Törlés</button>';
			echo '</div>';
		echo '</script>';
	}

	function get_html(){
		$this->render_template();

		if (empty($this->value)){
			$this->value = [];
		}

		echo '<input '.$this->render_name().' type="hidden" value="'.count($this->value).'">';
		if ($this->add_before) echo '<button class="components-button is-secondary is-small iu_repeater_field_button_add before">Hozzáadás</button>';
		
		echo '<div class="iu_repeater_field_rows">';
		foreach($this->value as $row => $values){
			echo '<div class="iu_repeater_field_row">';
				foreach($this->fields as $field_name => $field){
					$field = Fields::field_instance($this->name.'_'.($row+1).'_'.$field_name, $field);
					
					$field->set_value($values[$field_name]);
					echo $field->render();
				}
				echo '<button class="components-button is-secondary is-small is-destructive iu_repeater_field_button_remove">Törlés</button>';
			echo '</div>';
		}
		echo '</div>';

		if ($this->add_after) echo '<button class="components-button is-secondary is-small iu_repeater_field_button_add after">Hozzáadás</button>';
	}

	function fetch_value($post_id){
		global $wpdb;

		$sql = [];
		foreach($this->fields as $field_name => $field){
			$sql[] = "`meta_key` LIKE '".$this->name."_%_".$field_name."'";
		}

		$query = $wpdb->prepare(
			"SELECT `meta_key` AS 'key', `meta_value` AS 'value' FROM $wpdb->postmeta WHERE `post_id` = %d AND (".implode(" OR ", $sql).") ORDER BY `meta_key` ASC;",
			$post_id
		);

		$results = $wpdb->get_results($query);
		if (!empty($results)) {
			$this->value = [ 0 => [] ];
			
			$row = 1;
			foreach($results as $meta){
				$name = explode('_', $meta->key);
				array_shift($name);
				$index = array_shift($name);
				$field_name = implode('_', $name);
				
				if ($index > $row) {
					array_push($this->value, []);
					$row++;
				}

				$this->value[$row-1][$field_name] = $meta->value;
			}
		}
	}

	function save($post_id, $value){
		global $wpdb;
		$query = $wpdb->prepare( 
			"DELETE FROM $wpdb->postmeta WHERE `post_id` = %d AND meta_key LIKE '".$this->name."_%%'",
		    $post_id
		);
		
		$wpdb->query($query);

		for($i = 1; $i <= $value; $i++){
			foreach($this->fields as $field_name => $field){
				$name = $this->name.'_'.$i.'_'.$field_name;
				update_post_meta($post_id, $name, $_POST[$name]);
			}
		}
	}

	function add_scripts(){
		wp_register_script('iucf_repeater_script', '');
		wp_enqueue_script('iucf_repeater_script');
		wp_add_inline_script('iucf_repeater_script','
			document.querySelectorAll(".iu_repeater_field").forEach(function(wrap){
			  	wrap.addEventListener("click",function(event){
			  		if (event.target.matches(".iu_repeater_field_button_add")){
			  			let item = event.target;
			  			let template = wrap.querySelector(`script[type="template"]`).innerHTML;
				  		
				  		wrap.querySelector(".iu_repeater_field_rows").insertAdjacentHTML(item.classList.contains("before") ? "afterbegin" : "beforeend", template);

				  		wrap.querySelectorAll(".iu_repeater_field_row").forEach(function(row, index){
				  			row.querySelectorAll("[name]").forEach(function(item){
				  				let name = item.name.split("_");
				  				name[1] = index+1;
				  				item.name = name.join("_");
				  			});
				  		});

				  		let count = parseInt(wrap.querySelector(`[type="hidden"]`).value);
				  		wrap.querySelector(`[type="hidden"]`).value = count+1;
			  		}
			  		if (event.target.matches(".iu_repeater_field_button_remove")){
			  			//if (!confirm("Biztosan törlöd?")) return false;

			  			let item = event.target;

			  			item.parentElement.remove();

				  		wrap.querySelectorAll(".iu_repeater_field_row").forEach(function(row, index){
				  			row.querySelectorAll("[name]").forEach(function(item){
				  				let name = item.name.split("_");
				  				name[1] = index+1;
				  				item.name = name.join("_");
				  			});
				  		});

				  		let count = parseInt(wrap.querySelector(`[type="hidden"]`).value);
				  		wrap.querySelector(`[type="hidden"]`).value = count-1;
			  		}
			  	});

			  	let container = jQuery(wrap).find(".iu_repeater_field_rows");
				container.mousedown(function(){
					container.css({ height: container.height() });
				}).mouseup(function(){
					container.css({ height: "auto" });
				}).sortable({
					axis: "y",
					tolerance: "pointer",
					update: function(){
						wrap.querySelectorAll(".iu_repeater_field_row").forEach(function(row, index){
				  			row.querySelectorAll("[name]").forEach(function(item){
				  				let name = item.name.split("_");
				  				name[1] = index+1;
				  				item.name = name.join("_");
				  			});
				  		});
					}
				});
			});
		');

		wp_register_style('iucf_repeater_style', '');
		wp_enqueue_style('iucf_repeater_style');
		wp_add_inline_style('iucf_repeater_style','
			.iu_repeater_field { padding-top: 12px; }
			.iu_repeater_field_rows { margin: 9px 0;border-top: 1px solid #e0e0e0;border-bottom: 1px solid #e0e0e0; }
			.iu_repeater_field_rows:empty { border: none; }
			.iu_repeater_field_row { padding: 12px 0;border-top: 1px solid #e0e0e0; }
			.iu_repeater_field_row:first-child { border: none; }
			.iu_repeater_field_row.ui-sortable-helper { background: rgba(255,255,255,.9);border-top: 1px solid #e0e0e0;border-bottom: 1px solid #e0e0e0; }
			.iu_repeater_field_row.ui-sortable-placeholder { visibility: visible !important; }
		');
	}
}