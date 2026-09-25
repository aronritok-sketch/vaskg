<?php

namespace IUTheme;

class Forms {

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
    	add_action('template_redirect', [$this, 'handle_form_submissions']);
    }

    function handle_form_submissions(){
    	if (!isset($_POST['iu_form_id'])) return;

        global $post;
        $templates = Templates::get_instance();

        $header = $templates->get_template('header');
        $blocks = parse_blocks($header);
        $form = $this->find_form($_POST['iu_form_id'], $blocks);

        if (!$form){
            $content = $templates->get_template('content');
            $blocks = parse_blocks(str_replace('<!-- wp:iu/content /-->', $post->post_content, $content));
            $form = $this->find_form($_POST['iu_form_id'], $blocks);
        }

        if (!$form){
            $footer = $templates->get_template('footer');
            $blocks = parse_blocks($footer);
            $form = $this->find_form($_POST['iu_form_id'], $blocks);
        }

    	$validate = $this->get_field_validations($form);

    	$errors = [];
    	foreach($validate as $name => $methods){
    		foreach(explode("\n", $methods) as $m){
    			$method = explode('|', $m);
    			if (!$this->validate($name, $method[0])){
    				$errors[$name] = $method[1];
    			}
    		}
    	}

    	if (!empty($errors)){
    		echo json_encode([ 'errors' => $errors ]);
    		exit;
    	}

        if (!empty($form['attrs']['ac_url']) && !empty($form['attrs']['ac_key']) && !empty($form['attrs']['ac_listid'])){
            $values = $this->get_field_values($form);
            $result = $this->active_campaigne($form['attrs']['ac_url'], $form['attrs']['ac_key'], $form['attrs']['ac_listid'], $values);

            if (!$result) {
                echo json_encode([ 'error' => 'Váratlan hiba történt, próbálja meg később.' ]);
                exit;
            }
        }
        
        if (!empty($form['attrs']['email'])){
            $headers = [];
            if (!empty($from['attrs']['from'])) $headers[] = 'From: '.$form['attrs']['from'];
        	
            wp_mail($form['attrs']['email'], $form['attrs']['subject'], $form['attrs']['message'], $headers);
        }

        echo json_encode(apply_filters('iu_form_submit_'.$_POST['iu_form_id'], [ 'success' => 1 ], $form));
    	exit;
    }

    private function find_form($form_id, $blocks){
    	foreach($blocks as $block){
    		if ($block['blockName'] === 'iu/form' && $block['attrs']['formId'] === $form_id){
    			return $block;
    		}
    		$form_block = $this->find_form($form_id, $block['innerBlocks']);
    		if ($form_block) return $form_block;
    	}
    	return false;
    }

    private function get_field_validations($form){
    	$validations = [];

    	foreach($form['innerBlocks'] as $block){
    		if ($block['blockName'] !== 'iu/group'){
    			if (!empty($block['attrs']['validate'])){
    				$validations[$block['attrs']['name']] = $block['attrs']['validate'];
    			}
    		} else {
    			$validations = array_merge($validations, $this->get_field_validations($block));
    		}
    	}

    	return $validations;
    }

    private function validate($name, $method){
    	if (!isset($_POST[$name])) return true;
    	
    	$value = $_POST[$name];
    	switch($method){
    		case 'required': {
    			if ($value === '') return false;
    		} break;
    		case 'email': {
    			if (!filter_var($value, FILTER_VALIDATE_EMAIL)) return false;
    		} break;
    	}

    	return true;
    }

    private function active_campaigne($url, $key, $listid, $values){
        require_once(__DIR__.'/../vendor/activecampaigne/ActiveCampaign.class.php');

        $ac = new \ActiveCampaign($url, $key);
        $values['p['.$listid.']'] = $listid;
        $values['status['.$listid.']'] = 1;
        $result = $ac->api("contact/sync", apply_filters('iu_form_ac_data_'.$_POST['iu_form_id'], $values));

        return $result->success == 1;
    }

    private function get_field_values($block){
        $values = [];

        foreach($block['innerBlocks'] as $childBlock){
            if (isset($childBlock['attrs']['name']) && isset($_POST[$childBlock['attrs']['name']])){
                $values[$childBlock['attrs']['name']] = $_POST[$childBlock['attrs']['name']]; 
            }
            if (!empty($childBlock['innerBlocks'])){
                $values = array_merge($values, $this->get_field_values($childBlock));
            }
        }

        return $values;
    }

}