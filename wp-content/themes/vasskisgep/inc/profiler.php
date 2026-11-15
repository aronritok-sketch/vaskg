<?php

namespace VasskisgepTheme;

/**
 * Sebességmérés (Levi, 2026-11-13: „a webshop egyre lassabb”): ha a kérésben a `vk_prof` paraméter a státusz-kulcs,
 * az oldal végére egy HTML-megjegyzés kerül a mérésekkel: a WordPress betöltésének szakaszai, blokkonként a
 * renderelési idő és az adatbázis-lekérdezések száma, a külső HTTP-kérések (cím, idő). Más kérésre nem fut semmi.
 */
class Profiler {
    private static $on = false;
    private static $stack = [];
    private static $blocks = [];
    private static $marks = [];
    private static $http = [];
    private static $http_start = [];
    private static $slow_queries = [];

    static function start(){
        $key = (string)($_GET['vk_prof'] ?? '');
        if ($key === '' || !hash_equals(MigrationData::STATUS_KEY_HASH, hash('sha256', $key))) return;
        self::$on = true;
        if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
        if (!defined('SAVEQUERIES')) define('SAVEQUERIES', true);
        self::mark('theme');
        foreach([ 'after_setup_theme', 'init', 'wp_loaded', 'parse_request', 'wp', 'template_redirect', 'wp_head', 'wp_body_open', 'wp_footer' ] as $hook){
            add_action($hook, function() use ($hook){ self::mark($hook.':start'); }, -99999);
            add_action($hook, function() use ($hook){ self::mark($hook.':end'); }, 99999);
        }
        add_filter('pre_render_block', [__CLASS__, 'block_start'], -99999, 2);
        add_filter('render_block', [__CLASS__, 'block_end'], 99999, 2);
        add_filter('pre_http_request', function($pre, $args, $url){ self::$http_start[$url] = microtime(true); return $pre; }, -99999, 3);
        add_action('http_api_debug', function($response, $context, $class, $args, $url){
            $t = isset(self::$http_start[$url]) ? microtime(true) - self::$http_start[$url] : null;
            self::$http[] = [ 'url' => preg_replace('/([?&](key|token|secret|password|api_key)=)[^&]*/i', '$1…', (string)$url), 'ms' => $t === null ? null : round($t * 1000), 'error' => is_wp_error($response) ? $response->get_error_message() : null ];
        }, 10, 5);
        add_filter('log_query_custom_data', function($data, $query, $elapsed){
            if ($elapsed > 0.05) self::$slow_queries[] = [ 'ms' => round($elapsed * 1000), 'q' => mb_substr(preg_replace('/\s+/', ' ', $query), 0, 400) ];
            return $data;
        }, 10, 3);
        add_action('shutdown', [__CLASS__, 'output'], 0);
    }

    private static function mark($label){
        global $wpdb;
        self::$marks[] = [ $label, round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000), isset($wpdb) ? (int)$wpdb->num_queries : 0 ];
    }

    static function block_start($pre, $block){
        global $wpdb;
        self::$stack[] = [ microtime(true), (int)$wpdb->num_queries ];
        return $pre;
    }

    static function block_end($content, $block){
        global $wpdb;
        $s = array_pop(self::$stack);
        if (!$s) return $content;
        $name = (string)($block['blockName'] ?? '');
        if ($name === '') return $content;
        $label = $name;
        if (!empty($block['attrs']['className'])) $label .= ' .'.mb_substr((string)$block['attrs']['className'], 0, 40);
        $b = &self::$blocks[$label];
        $b = $b ?? [ 'n' => 0, 'ms' => 0, 'q' => 0, 'depth' => count(self::$stack) ];
        $b['n']++;
        $b['ms'] += (microtime(true) - $s[0]) * 1000;
        $b['q'] += (int)$wpdb->num_queries - $s[1];
        $b['depth'] = min($b['depth'], count(self::$stack));
        return $content;
    }

    static function output(){
        self::mark('shutdown');
        uasort(self::$blocks, function($a, $b){ return $b['ms'] <=> $a['ms']; });
        $blocks = [];
        foreach(array_slice(self::$blocks, 0, 40, true) as $k => $v) $blocks[$k] = [ 'n' => $v['n'], 'ms' => round($v['ms']), 'q' => $v['q'], 'depth' => $v['depth'] ];
        usort(self::$slow_queries, function($a, $b){ return $b['ms'] <=> $a['ms']; });
        $out = [ 'marks' => self::$marks, 'blocks' => $blocks, 'http' => self::$http, 'slow_queries' => array_slice(self::$slow_queries, 0, 15), 'memory_mb' => round(memory_get_peak_usage() / 1048576, 1), 'object_cache' => wp_using_ext_object_cache() ];
        echo "\n<!-- vk_prof ".str_replace('--', '- -', wp_json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))." -->\n";
    }
}
