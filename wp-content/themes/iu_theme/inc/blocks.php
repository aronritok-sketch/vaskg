<?php

namespace IUTheme;

class Blocks {

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('init', [$this, 'register_blocks']);
        add_action('wp_head', [$this, 'render_block_styles'], 999);

        add_filter('allowed_block_types_all', [$this, 'remove_core_blocks']);
        add_filter('register_block_type_args', [$this, 'add_supports']);
        add_filter('register_block_type_args', [$this, 'font_size_attributes']);
        add_filter('render_block', [$this, 'block_styles'], 10, 2);
        add_filter('should_load_remote_block_patterns', '__return_false');
        add_filter('should_load_separate_core_block_assets', '__return_true');
        add_filter('should_load_block_editor_scripts_and_styles', [$this, 'enqueue_utils']);
        add_filter('should_load_block_editor_scripts_and_styles', [$this, 'form_blocks_js']);
        
        add_filter('render_block_core/heading', function($block_content, $block){
            $processor = new \WP_HTML_Tag_Processor( $block_content );
            if ($processor->next_tag(['class_name' => 'wp-block-heading'])){
                $processor->remove_class('wp-block-heading');
            }

            return $processor->get_updated_html();
        }, 10, 2);
        add_filter('render_block_core/list', function($block_content){
            $processor = new \WP_HTML_Tag_Processor( $block_content );
            if ($processor->next_tag(['class_name' => 'wp-block-list'])){
                $processor->remove_class('wp-block-list');
            }

            return $processor->get_updated_html();
        });

        add_filter('render_block_iu/content', function($block_content, $block){
            if (is_admin() || wp_is_json_request()) return $block_content;

            global $post;
            $block_content = apply_filters('the_content', $post->post_content);
            return $block_content;
        }, 10, 2);

        add_filter('render_block_iu/image-carousel', function($block_content, $block){
            foreach($block['attrs']['images'] as $image){
                $caption = get_post($image['id'])->post_content;
                if (!empty($caption)){
                    $block_content = str_replace($image['url'].'"/>',$image['url'].'"/><span>'.$caption.'</span>', $block_content);
                }
            }

            return $block_content;
        }, 10, 2);

        wp_register_script('iu-slick', get_template_directory_uri().'/inc/js/slick.min.js', ['jquery']);
    }

    function register_blocks(){
        $stylesheet_inc_dir = get_stylesheet_directory().'/inc';

        $block_dirs = array_filter(glob(__DIR__.'/blocks/*'), 'is_dir');
        if (__DIR__ !== $stylesheet_inc_dir){
            $block_dirs = array_merge($block_dirs, array_filter(glob($stylesheet_inc_dir.'/blocks/*'), 'is_dir'));
        }

        foreach($block_dirs as $block_dir){
            if (basename($block_dir)[0] !== '_'){
                register_block_type($block_dir);
            }
        }

        $form_block_dirs = array_filter(glob(__DIR__.'/form-blocks/*'), 'is_dir');
        if (__DIR__ !== $stylesheet_inc_dir){
            $form_block_dirs = array_merge($form_block_dirs, array_filter(glob($stylesheet_inc_dir.'/form-blocks/*'), 'is_dir'));
        }

        foreach($form_block_dirs as $block_dir){
            if (basename($block_dir)[0] !== '_'){
                register_block_type($block_dir);
            }
        }
    }

    function remove_core_blocks($allowed_block_types){
        $allowed_core_blocks = [
            'core/paragraph',
            'core/heading',
            'core/list',
            'core/list-item',
            'core/html',
            'core/shortcode'
        ];

        $blocks = \WP_Block_Type_Registry::get_instance()->get_all_registered();
        foreach($blocks as $block_name => $block){
            if (strpos($block_name, 'core/') === 0 && !in_array($block_name, $allowed_core_blocks)){
                unset($blocks[$block_name]);
            }
        }
        return array_keys($blocks);
    }

    function enqueue_utils($is_block_editor_screen){
        global $current_screen;
        if (!$current_screen->is_block_editor) return $is_block_editor_screen;
        
        wp_enqueue_script('iu-theme-block-utils', get_template_directory_uri().'/inc/blocks/block-utils.js');
        
        remove_filter('should_load_block_editor_scripts_and_styles', [$this, 'enqueue_utils']);

        return $is_block_editor_screen;
    }

    function add_supports($args){
        if ($args['name'] === 'core/paragraph' || $args['name'] === 'core/heading' || $args['name'] === 'core/list' || $args['name'] === 'core/list-item'){
            if (!isset($args['supports'])) $args['supports'] = [];
            $args['supports']['iu'] = [ 'spacing' => true ];
            $args['supports']['color'] = [
                'enableContrastChecker' => false,
                'text' => false
            ];
            $args['supports']['typography'] = [ 'textAlign' => true ];
        }

        if (!isset($args['supports']) || !isset($args['supports']['iu'])) return $args;

        foreach($args['supports']['iu'] as $support => $value){
            if ($value){
                switch($support){
                    case 'background': {
                        $args['attributes']['bgColor'] = [ 'type' => 'string', 'default' => '' ];
                        $args['attributes']['bgGradient'] = [ 'type' => 'string', 'default' => null ];
                        $args['attributes']['bgImage'] = [ 'type' => 'string', 'default' => '' ];
                        $args['attributes']['bgImageThumb'] = [ 'type' => 'string', 'default' => '' ];
                        $args['attributes']['bgImageSize'] = [ 'type' => 'string', 'default' => 'cover' ];
                        $args['attributes']['bgImagePosition'] = [ 'type' => 'string', 'default' => 'center' ];
                        $args['attributes']['iu_style'] = ['type' => 'string', 'default' => ''];
                    } break;
                    case 'link': {
                        $args['attributes']['linkUrl'] = [ 'type' => 'string', 'default' => '' ];
                        $args['attributes']['linkNewTab'] = [ 'type' => 'boolean', 'default' => false ];
                    } break;
                    case 'spacing': {
                        $args['attributes']['marginTop'] = [ 'type' => 'string', 'default' => '' ];
                        $args['attributes']['marginBottom'] = [ 'type' => 'string', 'default' => '' ];
                        $args['attributes']['paddingTop'] = [ 'type' => 'string', 'default' => '' ];
                        $args['attributes']['paddingBottom'] = [ 'type' => 'string', 'default' => '' ];
                        $args['attributes']['paddingLeft'] = [ 'type' => 'string', 'default' => '' ];
                        $args['attributes']['paddingRight'] = [ 'type' => 'string', 'default' => '' ];
                        $args['attributes']['iu_style'] = ['type' => 'string', 'default' => ''];
                    } break;
                    case 'gap': {
                        $args['attributes']['gap'] = [ 'type' => 'string', 'default' => '' ];
                        $args['attributes']['iu_style'] = [ 'type' => 'string', 'default' => '' ];
                    } break;
                }
            }
        }

        $args['supports']['reusable'] = false;
        return $args;
    }

    static function style_attribute($atts){
        $style = '';
        if ($atts['marginTop'] !== '') $style .= 'margin-top:'.$atts['marginTop'].';';
        if ($atts['marginBottom'] !== '') $style .= 'margin-bottom:'.$atts['marginBottom'].';';
        if ($atts['paddingTop'] !== '') $style .= 'padding-top:'.$atts['paddingTop'].';';
        if ($atts['paddingLeft'] !== '') $style .= 'padding-left:'.$atts['paddingLeft'].';';
        if ($atts['paddingRight'] !== '') $style .= 'padding-right:'.$atts['paddingRight'].';';
        if ($atts['paddingBottom'] !== '') $style .= 'padding-bottom:'.$atts['paddingBottom'].';';
        return !empty($style) ? 'style="'.$style.'"' : '';
    }

    function font_size_attributes($args){
        if ($args['name'] === 'core/paragraph'){
            $args['attributes']['small'] = ['type' => 'boolean', 'default' => false];
            $args['attributes']['muted'] = ['type' => 'boolean', 'default' => false];
        }
        
        return $args;
    }

    function block_styles($block_content, $block){
        if (in_array($block['blockName'], ['core/shortcode', 'iu/content', 'iu/template'])) return $block_content;

        global $iu_block_styles;
        if (!isset($iu_block_styles)){
            $iu_block_styles = [];
        }

        if (!isset($iu_block_styles[$block['blockName']])){
            $iu_block_styles[$block['blockName']] = [];
        }

        $iu_block_styles[$block['blockName']][] = $block['attrs'];

        $processor = new \WP_HTML_Tag_Processor($block_content);
        if ($processor->next_tag()){
            $processor->add_class(str_replace(['/', 'core'],['-','iu'],$block['blockName']).'_'.count($iu_block_styles[$block['blockName']]));
        }

        return $processor->get_updated_html();
    }

    function render_block_styles(){
        global $iu_block_styles;
        if (empty($iu_block_styles)) return;

        $style = '';
        foreach($iu_block_styles as $block_name => $block){
            $type = explode('/', $block_name)[0];
            if (!in_array($type, ['core', 'iu'])) continue;

            foreach($block as $index => $atts){
                if (!empty($atts['iu_style'])){
                    $style .= '.'.str_replace(['/', 'core'],['-','iu'],$block_name).'_'.($index+1).'{'.$atts['iu_style'].'}';
                }
            }
        }

        echo '<style id="iu-block-styles-css">'.$style.'</style>';
    }

    function form_blocks_js($is_block_editor_screen){
        global $current_screen;
        if (!$current_screen->is_block_editor()) return;

        $form_blocks = [ 'iu/group' ];

        $form_block_dirs = array_filter(glob(__DIR__.'/form-blocks/*'), 'is_dir');
        foreach($form_block_dirs as $dir){
            $json = json_decode(file_get_contents($dir.'/block.json'), true);
            $form_blocks[] = $json['name'];
        }

        $stylesheet_inc_dir = get_stylesheet_directory().'/inc';

        if (__DIR__ !== $stylesheet_inc_dir){
            $stylesheet_form_block_dirs = array_filter(glob($stylesheet_inc_dir.'/form-blocks/*'), 'is_dir');
            foreach($stylesheet_form_block_dirs as $dir){
                $json = json_decode(file_get_contents($dir.'/block.json'), true);
                $form_blocks[] = $json['name'];
            }
        }

        wp_register_script('iu-theme-form-blocks', false);
        wp_localize_script('iu-theme-form-blocks', 'iu_theme_form_blocks', $form_blocks);
        wp_enqueue_script('iu-theme-form-blocks');

        remove_filter('should_load_block_editor_scripts_and_styles', [$this, 'form_blocks_js']);

        return $is_block_editor_screen;
    }
}