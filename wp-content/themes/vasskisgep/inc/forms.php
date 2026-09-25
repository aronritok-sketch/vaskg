<?php

namespace VasskisgepTheme;

/**
 * Űrlap e-mailek.
 *
 * A keretrendszer iu/form blokkja az e-mailben csak a "message" attribútum fix
 * szövegét küldi el, a kitöltött mezőket nem. Ezért a saját űrlapjainknál az
 * iu/form "email" attribútuma üres, és itt, az iu_form_submit_{formId} szűrőben
 * küldjük el a teljes, mezőnkénti e-mailt.
 */
class Forms {
    const RECIPIENT = 'info@vasskisgep.hu';

    const FORMS = [
        'quick-contact' => 'Visszahívás kérés',
        'ajanlatkeres' => 'Ajánlatkérés',
        'szerviz' => 'Szerviz bejelentés',
        'kapcsolat' => 'Üzenet a kapcsolat oldalról',
        'termek-kerdes' => 'Kérdés egy termékről',
    ];

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        foreach(array_keys(self::FORMS) as $form_id){
            add_filter('iu_form_submit_'.$form_id, [$this, 'send'], 10, 2);
        }
    }

    function send($response, $form){
        if (empty($form) || empty($form['attrs']['formId'])) return $response;
        $form_id = $form['attrs']['formId'];

        $fields = $this->fields($form['innerBlocks']);

        // A kötelező elfogadó checkboxot a keretrendszer szerveroldalon nem ellenőrzi.
        foreach($fields as $name => $field){
            if ($field['type'] === 'accept' && empty($_POST[$name])){
                return [ 'errors' => [ $name => $field['error'] ?: 'Kötelező mező' ] ];
            }
        }

        $rows = [];
        $reply_to = '';
        foreach($fields as $name => $field){
            if ($field['type'] === 'accept') continue;
            $value = isset($_POST[$name]) ? trim(sanitize_textarea_field(wp_unslash($_POST[$name]))) : '';
            if ($value === '') continue;
            if ($reply_to === '' && is_email($value)) $reply_to = $value;
            $rows[] = '<tr><th align="left" valign="top" style="padding:6px 12px 6px 0;white-space:nowrap">'.esc_html($field['label'] ?: $name).'</th><td style="padding:6px 0">'.nl2br(esc_html($value)).'</td></tr>';
        }

        // Az űrlap a saját oldalára küld, ezért a wp_get_referer() itt üres lenne.
        $page = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';
        $subject = self::FORMS[$form_id].' – '.get_bloginfo('name');

        $body = '<p><strong>'.esc_html(self::FORMS[$form_id]).'</strong> érkezett a weboldalról.</p>';
        $body .= '<table cellspacing="0" cellpadding="0" style="font-family:Arial,sans-serif;font-size:14px">'.implode('', $rows).'</table>';
        if ($page) $body .= '<p style="color:#666;font-size:12px">Oldal: '.esc_html($page).'</p>';

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        if ($reply_to !== '') $headers[] = 'Reply-To: '.$reply_to;

        $recipient = apply_filters('vasskisgep_form_recipient', self::RECIPIENT, $form_id);
        if (!wp_mail($recipient, $subject, $body, $headers)){
            return [ 'errors' => [], 'error' => 'Az üzenet küldése nem sikerült. Kérjük, hívjon minket: +36 30 289 5977.' ];
        }

        return $response;
    }

    private function fields($blocks){
        $fields = [];
        foreach($blocks as $block){
            if (!empty($block['innerBlocks'])){
                $fields = array_merge($fields, $this->fields($block['innerBlocks']));
            }
            if (empty($block['blockName']) || strpos($block['blockName'], 'iu/form-') !== 0) continue;
            if (empty($block['attrs']['name'])) continue;

            $fields[$block['attrs']['name']] = [
                'type' => str_replace('iu/form-', '', $block['blockName']),
                'label' => isset($block['attrs']['label']) ? wp_strip_all_tags($block['attrs']['label']) : '',
                'error' => $block['attrs']['error'] ?? ''
            ];
        }
        return $fields;
    }
}
