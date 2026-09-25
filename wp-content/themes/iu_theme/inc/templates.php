<?php

namespace IUTheme;

class Templates {

    private $template_positions;
    private $template_types;

    private static $instance;
    public static function get_instance(){
        if (is_null(self::$instance)){
            self::$instance = new self();
        }
        return self::$instance;
    }

    function __construct(){
        add_action('init', [$this, 'init']);

        add_action('iu_header_template', [$this, 'header_template']);
        add_action('iu_main_template', [$this, 'main_template']);
        add_action('iu_footer_template', [$this, 'footer_template']);

        add_action('the_post', [$this, 'alter_post_content']);
        add_action('save_post', [$this, 'handle_save'], 10, 2);
    }

    private function get_template_positions(){
        if (empty($this->template_positions)){
            $this->template_positions = [ 
                'header' => 'Header',
                'content' => 'Content',
                'footer' => 'Footer'
            ];
        }
        return $this->template_positions;
    }

    private function get_template_types(){
        if (empty($this->template_types)){
            $this->template_types = [
                'global' => 'Global',
                'front_page' => 'Front Page',
                'blog_page' => 'Blog Page',
                'search' => 'Search Page',
                '404' => '404 Page'
            ];

            $post_types = get_post_types(['public' => true], 'objects');
            foreach($post_types as $post_type){
                if ($post_type->has_archive || $post_type->name == 'post'){
                    $this->template_types['archive_'.$post_type->name] = $post_type->labels->name.' Archive';
                }
                $this->template_types['single_'.$post_type->name] = 'Single '.$post_type->labels->singular_name;

                $taxonomies = get_object_taxonomies($post_type->name, 'objects');
                foreach($taxonomies as $taxonomy){
                    $this->template_types['tax_'.$taxonomy->name] = $taxonomy->labels->singular_name. ' Pages';
                }
            }
        }
        return $this->template_types;
    }

    function init(){
        register_post_type('iu_template',[
            'labels' => [
                'name' => 'Template-ek',
                'singular_name' => 'Template',
                'add_new' => 'Template hozzáadása',
                'add_new_item' => 'Template hozzáadása',
                'edit_item' => 'Template szerkesztése',
                'new_item' => 'Új template'
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'themes.php',
            'show_in_rest' => true,
            'supports' => ['title', 'editor'],
            'copyable' => true,
            'list_columns' => [
                'position' => [
                    'label' => 'Position',
                    'position' => 2,
                    'render_callback' => function($post_id){
                        $positions = $this->get_template_positions();
                        echo $positions[get_post_meta($post_id, 'iu_template_position', true)];
                    },
                    'width' => '10%'
                ],
                'type' => [
                    'label' => 'Type',
                    'position' => 3,
                    'render_callback' => function($post_id){
                        $types = $this->get_template_types();
                        echo $types[get_post_meta($post_id, 'iu_template_type', true)];
                    },
                    'width' => '15%'
                ]
            ]
        ]);

        iucf_add_post_type_fields('iu_template', 'template', [
            'label' => 'Template',
            'fields' => [
                'iu_template_position' => [
                    'label' => 'Position',
                    'type' => 'select',
                    'options' => function(){
                        $positions = $this->get_template_positions();
                        return array_map(function($label, $value){
                            return [ 'label' => $label, 'value' => $value ];
                        }, $positions, array_keys($positions));
                    }
                ],
                'iu_template_type' => [
                    'label' => 'Type',
                    'type' => 'select',
                    'options' => function(){
                        $types = $this->get_template_types();
                        return array_map(function($label, $value){
                            return ['label' => $label, 'value' => $value];
                        }, $types, array_keys($types));
                    }
                ]
            ]
        ]);
    }

    private function get_query_type(){
        if (is_front_page()) return ['front_page', 'single_page'];
        if (is_home()) return ['blog_page'];
        if (is_404()) return ['404'];
        if (is_search()) return ['search'];
        if (is_category()) return ['tax_category'];
        if (is_tag()) return ['tax_post_tag'];
        
        $object = get_queried_object();
        if (is_singular()) return ['single_'.$object->post_type];
        if (is_tax()) return ['tax_'.$object->taxonomy];
        if (is_archive() && $object->name !== 'page') return ['archive_'.$object->name];

        return [];
    }

    function get_template($position){
        $query_types = array_merge($this->get_query_type(), ['global']);

        foreach($query_types as $type){
            $query = new \WP_Query([
                'post_type' => 'iu_template',
                'numberposts' => -1,
                'meta_query' => [
                    [
                        'key' => 'iu_template_position',
                        'value' => $position
                    ],
                    [
                        'key' => 'iu_template_type',
                        'value' => $type
                    ]
                ],
                'meta_key' => 'iu_template_type',
                'orderby' => 'meta_value'
            ]);

            if (!empty($query->posts)){
                return $query->posts[0]->post_content;
            }

            $file = get_stylesheet_directory().'/templates/'.$type.'_'.$position.'.html';
            if (file_exists($file)){
                return file_get_contents($file);
            }

            $file = get_template_directory().'/templates/'.$type.'_'.$position.'.html';
            if (file_exists($file)){
                return file_get_contents($file);
            }
        }

        return false;
    }

    function header_template(){
        $template = $this->get_template('header');

        ?>
            <header>
                <?php echo apply_filters('the_content', $template); ?>
            </header>
        <?php
    }

    function main_template(){
        $template = $this->get_template('content');

        ?>
            <main>
                <?php 
                    if (false === $template){
                        while(have_posts()) { the_post();
                            ?> 
                                <article>
                                    <?php the_content(); ?>
                                </article>
                            <?php
                        }
                    } else {
                        echo apply_filters('the_content', $template);
                    }
                ?>
            </main>
        <?php
    }

    function footer_template(){
        $template = $this->get_template('footer');

        ?>
            <footer>
                <?php echo apply_filters('the_content', $template); ?>
            </footer>
        <?php
    }

    function alter_post_content(){
        global $post, $pagenow;

        if (($pagenow !== 'post.php' && $pagenow !== 'post-new.php') || $post->post_type === 'product' || !use_block_editor_for_post_type($post->post_type)) return;
        
        $templates = get_posts([
            'post_type' => 'iu_template',
            'meta_key' => 'iu_template_type',
            'meta_value' => 'single_'.$post->post_type
        ]);

        if (empty($templates)) return;

        $post->post_content = '<!-- wp:iu/template -->'.$post->post_content.'<!-- /wp:iu/template -->';
    }

    function handle_save($post_id){
        $post_type = get_post_type($post_id);
        if ($post_type === 'iu_template'|| $post_type === 'product' || !use_block_editor_for_post_type($post_type)) return;

        $input = file_get_contents('php://input');
        if (empty($input)) return;

        $json = json_decode($input, true);

        if (!isset($json['content']) || empty($json['content'])) return;
        $content_blocks = $this->parse_blocks_ignore_empty_blocks($json['content']);

        $template_block = $this->find_content_inner_blocks('iu/template', $content_blocks);
        if (false === $template_block) return;

        $content_inner_blocks = $this->find_content_inner_blocks('iu/content', $content_blocks);
        if (false === $content_inner_blocks) return;

        remove_action('save_post', [$this, 'handle_save']);
        wp_update_post([
            'ID' => $post_id,
            'post_content' => wp_slash(serialize_blocks($content_inner_blocks))
        ]);

        return $post_id;
    }

    private function is_non_empty_block( $block ) {
        return ! ( $block['blockName'] === null && empty( trim( $block['innerHTML'] ) ) );
    }

    private function parse_blocks_ignore_empty_blocks( $input ) {
        return array_filter( parse_blocks( $input ), [$this, 'is_non_empty_block'] );
    }

    private function find_content_inner_blocks($block_name, $blocks){
        $found = false;
        foreach($blocks as $block){
            if ($block['blockName'] === $block_name) return $block['innerBlocks'];
            if (!empty($block['innerBlocks'])) {
                $found = $this->find_content_inner_blocks($block_name, $block['innerBlocks']);
                if (false !== $found){
                    return $found;
                }
            }
        }
        return false;
    }

}