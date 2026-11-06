<?php

namespace VasskisgepTheme;

/**
 * ÁSZF → „Szállítás és fizetés” oldal (ügyfélkérés, 2026-11, Dávid 17. pontja): az ÁSZF a VirtualJog-ból töltődik
 * (az ÁSZF oldalán a VirtualJog szinkron-kódja: documentID, accessToken). Ugyanebből a dokumentumból a
 * „Szállítás és fizetés” oldal a fizetési módokat és a szállítási díjakat (ÁSZF 5.5.1–5.5.2 és az egyedi szállítási
 * díj bekezdés) mutatja, így az ÁSZF frissítésével ez az oldal is frissül (óránként; ha a VirtualJog nem érhető el,
 * az utolsó sikeresen letöltött változat marad).
 *
 * Blokk: vasskisgep/aszf-shipping. Ha az ÁSZF-ből nem jön ki a rész, a WooCommerce díjtáblája látszik (Shipping).
 */
class Aszf {
    const CACHE = 'vk_aszf_doc';
    const FAIL = 'vk_aszf_fail';
    const LAST = 'vk_aszf_doc_last';

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('init', [$this, 'register'], 20);
    }

    function register(){
        if (!function_exists('iucb_add_block')) return;
        iucb_add_block('vasskisgep/aszf-shipping', [
            'title' => 'Fizetés és szállítás az ÁSZF-ből',
            'description' => 'A fizetési módok és a szállítási díjak az ÁSZF-ből (VirtualJog), az ÁSZF-fel együtt frissül.',
            'category' => 'iu-woocommerce',
            'attributes' => [],
            'editJS' => 'return "Fizetési módok és szállítási díjak (az ÁSZF-ből)";',
            'template' => function(){ return self::shipping_html(); }
        ]);
    }

    /** A VirtualJog dokumentum azonosítója és hozzáférési kulcsa az ÁSZF oldal kódjából. */
    static function source(){
        $page = get_page_by_path('aszf');
        $content = $page ? (string)$page->post_content : '';
        if (!preg_match('/"documentID"\s*:\s*(\d+)/', $content, $id) || !preg_match('/"accessToken"\s*:\s*"([A-Za-z0-9]+)"/', $content, $token)) return null;
        return [ (int)$id[1], $token[1] ];
    }

    /** Az ÁSZF HTML-je (VirtualJog), óránként frissítve; hiba esetén az utolsó jó változat. */
    static function document($fresh = false){
        if (!$fresh){
            $cached = get_transient(self::CACHE);
            if (is_string($cached) && $cached !== '') return $cached;
            if (get_transient(self::FAIL)) return (string)get_option(self::LAST, '');
        }
        $source = self::source();
        if (!$source) return (string)get_option(self::LAST, '');
        $res = wp_remote_get('https://api.virtualjog.hu/api/v1/document/'.$source[0].'?access-token='.rawurlencode($source[1]), [ 'timeout' => 12 ]);
        $html = is_wp_error($res) || (int)wp_remote_retrieve_response_code($res) !== 200 ? '' : (string)wp_remote_retrieve_body($res);
        if (strpos($html, '5.5.') === false){
            set_transient(self::FAIL, 1, 10 * MINUTE_IN_SECONDS);
            return (string)get_option(self::LAST, '');
        }
        set_transient(self::CACHE, $html, HOUR_IN_SECONDS);
        update_option(self::LAST, $html, false);
        return $html;
    }

    private static function text($node){
        return trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', (string)$node->textContent)));
    }

    /** ÁSZF 5.5.1 (fizetési módok) – 5.6 előtt (szállítási díjak, egyedi díj). */
    static function shipping_html(){
        $doc = self::document();
        $start = $doc !== '' ? preg_match('/<p[^>]*>\s*5\.5\.1\./u', $doc, $m, PREG_OFFSET_CAPTURE) : 0;
        if (!$start) return Shipping::rates_table();
        $from = $m[0][1];
        $to = preg_match('/<p[^>]*>\s*5\.6\./u', $doc, $e, PREG_OFFSET_CAPTURE, $from) ? $e[0][1] : strlen($doc);
        $segment = substr($doc, $from, $to - $from);
        // A VirtualJog a táblázatot le nem zárt <p><strong> elembe teszi: e nélkül a következő bekezdések a táblázat
        // „belsejébe” kerülnének.
        $segment = preg_replace('#<p[^>]*>\s*(?:<(?:strong|b)>\s*)+(?=<table)#iu', '', $segment);

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div id="vk-aszf">'.$segment.'</div>');
        libxml_clear_errors();
        $root = $dom->getElementById('vk-aszf');
        if (!$root) return Shipping::rates_table();

        $out = '';
        $list = [];
        $flush = function() use (&$out, &$list){
            if ($list) $out .= '<ul class="wp-block-list iu-list">'.implode('', $list).'</ul>';
            $list = [];
        };
        $tables = 0;
        foreach($root->childNodes as $node){
            if (!$node instanceof \DOMElement) continue;
            $table = $node->tagName === 'table' ? $node : $node->getElementsByTagName('table')->item(0);
            if ($table){
                $flush();
                $out .= self::table($table);
                $tables++;
                continue;
            }
            $text = self::text($node);
            if ($text === '') continue;
            if (preg_match('/^5\.5\.\d+\.\s*(.+?):?$/u', $text, $h)){
                $flush();
                $out .= '<h4 class="wp-block-heading iu-heading">'.esc_html($h[1]).'</h4>';
                continue;
            }
            $strong = $node->getElementsByTagName('strong')->item(0);
            if ($strong && self::text($strong) === $text){
                $flush();
                $out .= '<h4 class="wp-block-heading iu-heading">'.esc_html($text).'</h4>';
                continue;
            }
            // „Fizetési mód: leírás” → lista; a nem elérhető online kártyás fizetés nem látszik.
            if (preg_match('/^([^:]{3,60}):\s*(.+)$/u', $text, $item)){
                if (stripos($item[1], 'kártyá') !== false && stripos($item[1], 'online') !== false && !Pages::card_payment_enabled()) continue;
                $list[] = '<li class="iu-list-item"><strong>'.esc_html($item[1]).'</strong> – '.esc_html($item[2]).'</li>';
                continue;
            }
            $flush();
            $out .= '<p class="iu-text">'.esc_html($text).'</p>';
        }
        $flush();
        if (!$tables) return Shipping::rates_table();
        return '<div class="vk-aszf-shipping">'.$out.'<p class="iu-text vk-aszf-source">Forrás: <a href="'.esc_url(home_url('/aszf/')).'">Általános Szerződési Feltételek</a> (5.5. pont).</p></div>';
    }

    /** Az ÁSZF díjtáblája tisztán: az üres oszlopok nélkül, „10 kg-ig” / „2.490 Ft” alakban. */
    private static function table(\DOMElement $table){
        $rows = [];
        foreach($table->getElementsByTagName('tr') as $tr){
            $cells = [];
            foreach($tr->childNodes as $td) if ($td instanceof \DOMElement && in_array($td->tagName, [ 'td', 'th' ], true)) $cells[] = self::text($td);
            if (array_filter($cells, 'strlen')) $rows[] = $cells;
        }
        if (count($rows) < 2) return '';
        $head = array_shift($rows);
        $width = max(array_map('count', array_merge([ $head ], $rows)));
        $keep = [];
        for ($c = 0; $c < $width; $c++){
            foreach($rows as $r) if (($r[$c] ?? '') !== ''){ $keep[] = $c; break; }
        }
        $label = function($h, $c){
            $h = trim($h);
            if ($c === 0 && preg_match('/^kg$/iu', $h)) return 'Csomag súlya';
            if (preg_match('/^brutt[óo] [áa]r$/iu', $h)) return 'Szállítási díj (bruttó)';
            return $h;
        };
        $html = '<table class="vk-shipping-rates"><thead><tr>';
        foreach($keep as $c) $html .= '<th>'.esc_html($label($head[$c] ?? '', $c)).'</th>';
        $html .= '</tr></thead><tbody>';
        foreach($rows as $r){
            $html .= '<tr>';
            foreach($keep as $i => $c){
                $v = $r[$c] ?? '';
                if ($i === 0 && preg_match('/^\d+(?:[.,]\d+)?$/', $v)) $v .= ' kg-ig';
                elseif ($i > 0 && preg_match('/^\d{1,3}(?:[. ]\d{3})*$/', $v)) $v .= ' Ft';
                $html .= '<td>'.esc_html($v).'</td>';
            }
            $html .= '</tr>';
        }
        return $html.'</tbody></table>';
    }
}
