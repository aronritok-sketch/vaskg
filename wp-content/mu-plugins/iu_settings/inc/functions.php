<?php

function ius_register_settings($id, $args){
	IUSettings\Settings::register($id, $args);
}

function ius_get($id = false, $fields = false) {
	return IUSettings\Settings::get($id, $fields);
}

function ius_settings_page(){
	IUSettings\Settings::page();
}