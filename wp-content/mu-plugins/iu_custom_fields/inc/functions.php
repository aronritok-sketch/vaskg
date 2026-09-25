<?php

function iucf_add_post_type_fields($post_types, $name, $args){
	IUCustomFields\Fields::add_to_post_types($post_types, $name, $args);
}

function iucf_get_value($name, $field_name){
	global $post, $iucf_post_type_custom_fields;

	$p = $post;
	if ($post->post_type == 'revision'){
		$p = get_post($post->post_parent);
	}

	if ( !empty($iucf_post_type_custom_fields[$p->post_type]) &&
		 !empty($iucf_post_type_custom_fields[$p->post_type][$name]) &&
		 !empty($iucf_post_type_custom_fields[$p->post_type][$name]['fields'][$field_name])
	){
		$field = IUCustomFields\Fields::field_instance($field_name, $iucf_post_type_custom_fields[$p->post_type][$name]['fields'][$field_name]);
		return $field->get_value($p->ID);
	}
	
	return false;
}