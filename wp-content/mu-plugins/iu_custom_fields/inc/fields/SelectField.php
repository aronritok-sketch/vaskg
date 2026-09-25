<?php

namespace IUCustomFields;

class SelectField extends Field {
	private $options;

	function __construct($name, $args){
		parent::__construct($name, $args);

		if (isset($args['options'])) $this->options = $args['options'];
	}

	function get_html(){
		$options = $this->options;
		if (is_callable($options)){
			$options = $options();
		}

		?> 
			<select <?php echo $this->render_name(); ?> class="iu_field_element widefat" style="box-sizing: border-box;">
				<?php foreach($options as $item){ ?>
					<?php if (is_string($item)){ ?>
						<option <?php echo $this->value == $item ? 'selected' : '' ?>><?php echo $item ?></option>
					<?php } else { ?>
						<option <?php echo $this->value == $item['value'] ? 'selected' : '' ?> value="<?php echo $item['value'] ?>"><?php echo $item['label'] ?></option>
					<?php } ?>
				<?php } ?>
			</select>
		<?php
	}

}