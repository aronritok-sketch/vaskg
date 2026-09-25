<?php

namespace IUTheme;

require_once('blocks.php');
require_once('clean.php');
require_once('forms.php');
require_once('patterns.php');
require_once('settings.php');
require_once('shortcodes.php');
require_once('templates.php');


class Theme {

    private static $instance;
    static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        Blocks::get_instance();
        Clean::get_instance();
        Forms::get_instance();
        Patterns::get_instance();
        Settings::get_instance();
        Shortcodes::get_instance();
        Templates::get_instance();

        $this->hooks();
    }

    private function hooks(){
        add_action('init', [$this, 'unregister_post_format']);
        add_action('admin_init', [$this, 'disable_block_suggestions']);
        add_action('after_setup_theme', [$this, 'theme_support']);
        add_action('admin_footer', [$this, 'editor_fixes']);
        add_action('wp_head', [$this, 'remove_query_params']);
        add_action('admin_init', [$this, 'remove_dashboard_widgets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_main_style']);

        add_filter('register_post_type_args', [$this, 'unpublish_attachment'], 10, 2);
        add_filter('register_post_type_args', [$this, 'post_type_copy'], 10, 2);
        add_filter('map_meta_cap', [$this, 'disallow_file_edit'], 10, 2);
        add_filter('wp_theme_json_data_default', [$this, 'remove_default_settings']);

        add_filter('upload_mimes', [$this, 'allow_svg_upload'], 10);
        
        add_filter('rest_url_prefix', function(){ return '87sdtzugas76fgcw8atedw'; } );
    }

    function unregister_post_format(){
        unregister_taxonomy_for_object_type('post_format', 'post');
    }

    function disable_block_suggestions(){
        remove_action('enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets');
        remove_action('enqueue_block_editor_assets', 'gutenberg_enqueue_block_editor_assets_block_directory');
    }

    function theme_support(){
        add_theme_support('post-thumbnails');
        add_theme_support('menus');
        add_theme_support('align-wide');
        add_theme_support('editor-styles');
        add_theme_support('disable-layout-styles');

        remove_theme_support('core-block-patterns');
        remove_theme_support('block-templates');

        add_editor_style('variables.css');
        add_editor_style('inc/blocks/block-styles.css');
    }

    function editor_fixes(){
        global $current_screen;
        if (!$current_screen->is_block_editor) return;

        ?><style>
            .block-editor-inserter__preview-container { 
                display: none;
            }
            .typography-block-support-panel:has(.components-tools-panel-header + :empty:last-child) {
                display: none;
            }
            .block-editor-tabbed-sidebar__tablist {
                visibility: hidden;
            }
            .block-editor-inserter__tips {
                display: none;
            }
            .editor-visual-editor__post-title-wrapper h1 {
                padding: 0 20px;
            }
            .block-editor-default-block-appender .block-editor-default-block-appender__content {
                font-family: var(--iu-text-font-family);
                font-size: var(--iu-text-font-size);
                line-height: var(--iu-text-line-height);
                margin: revert;
            }
        </style><?php
    }

    function remove_query_params(){
        ?>
            <script>                
                if (window.history.replaceStatey) {
                    let url = new URL(window.location.href);
                    url.searchParams.delete('error');
                    url.searchParams.delete('success');
                    window.history.replaceState(null, null, url.href);
                }    
            </script>
        <?php
    }

    function remove_dashboard_widgets(){
        remove_action('welcome_panel', 'wp_welcome_panel');

        remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
        remove_meta_box('dashboard_activity', 'dashboard', 'normal');
        remove_meta_box('dashboard_secondary', 'dashboard', 'normal');

        remove_meta_box('dashboard_primary', 'dashboard', 'side');
        remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
    }

    function enqueue_main_style(){
        wp_dequeue_style( 'wp-block-library' );
        wp_dequeue_style( 'wp-block-library-theme' );

        if (is_child_theme()){
            wp_enqueue_style('parent-style', get_template_directory_uri().'/style.css');
        }
        wp_enqueue_style('style', get_stylesheet_uri());
    }

    function unpublish_attachment($args, $post_type){
        if ($post_type === 'attachment'){
            $args['public'] = false;
        }
        return $args;
    }

    function post_type_copy($args, $post_type){
        if (in_array($post_type, ['post', 'page'])) $args['copyable'] = true;
        return $args;
    }

    function disallow_file_edit($caps, $cap){
        if (in_array($cap, ['edit_files', 'edit_plugins', 'edit_themes'])){
            $caps[] = 'do_not_allow';
        }
        return $caps;
    }

    function remove_default_settings($theme_json){
        $new_theme_json_array = $theme_json->get_data();
        $new_theme_json_array['settings']['color']['palette'] = [];
        $new_theme_json_array['settings']['color']['duotone'] = [];
        $new_theme_json_array['settings']['color']['gradients'] = [];
        $new_theme_json_array['settings']['typography']['fontSizes'] = [];
        $new_theme_json_array['settings']['shadow']['presets'] = [];
        $new_theme_json_array['settings']['spacing'] = null;
        $new_theme_json_array['settings']['blocks'] = null;
        $new_theme_json_array['styles'] = null;
        $theme_json->update_with($new_theme_json_array);
        return $theme_json;
    }

    function allow_svg_upload($file_types){
        $file_types['svg'] = 'image/svg+xml';
        return $file_types;
    }

    static function body_class(){
        $classes = [];
        if (is_admin_bar_showing()) $classes[] = 'admin-bar';
        if (is_front_page()) $classes[] = 'front-page';
        echo !empty($classes) ? ' class="'.implode(' ', $classes).'"' : '';
    }

}