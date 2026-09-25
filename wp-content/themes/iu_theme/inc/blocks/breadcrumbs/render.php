<?php

(function() use ($attributes){

    $atts = wp_parse_args($attributes, [
        'separator' => '>',
        'marginTop' => '',
        'marginBottom' => '',
        'paddingTop' => '',
        'paddingBottom' => '',
        'paddingleft' => '',
        'paddingRight' => '',
    ]);

    $style = '';
    if ($atts['marginTop'] !== '') $style .= 'margin-top:'.$atts['marginTop'].';';
    if ($atts['marginBottom'] !== '') $style .= 'margin-bottom:'.$atts['marginBottom'].';';
    if ($atts['paddingTop'] !== '') $style .= 'padding-top:'.$atts['paddingTop'].';';
    if ($atts['paddingLeft'] !== '') $style .= 'padding-left:'.$atts['paddingLeft'].';';
    if ($atts['paddingRight'] !== '') $style .= 'padding-right:'.$atts['paddingRight'].';';
    if ($atts['paddingBottom'] !== '') $style .= 'padding-bottom:'.$atts['paddingBottom'].';';

    $classnames = ['iu-breadcrumbs'];
    if ($atts['align'] === 'center') $classnames[] = 'iu-breadcrumbs-align-center';
    if ($atts['align'] === 'right') $classnames[] = 'iu-breadcrumbs-align-right';

    global $post;
    $home_post = get_post(get_option('page_on_front'));

    echo '<div class="'.implode(' ', $classnames).'" itemscope itemtype="http://schema.org/BreadcrumbList" '.(!empty($style) ? 'style="'.$style.'"' : '').'>';
        echo '<div class="iu-breadcrumbs-wrap">';
        
        echo '<span itemprop="itemListElement" itemtype="http://schema.org/ListItem">
            <a href="'.home_url().'">'.$home_post->post_title.'</a>
        </span>';
        echo '<span class="iu-breadcrumbs-separator">'.$attributes['separator'].'</span>';

    if (is_singular('post')){
        $blog_id = get_option('page_for_posts');
        echo '<span itemprop="itemListElement" itemtype="http://schema.org/ListItem">
            <a href="'.get_permalink($blog_id).'">'.get_post($blog_id)->post_title.'</a>
        </span>';
        echo '<span class="iu-breadcrumbs-separator">'.$attributes['separator'].'</span>';

        /*if (has_category('', $post)){
            $category = get_the_category($post->ID)[0];
            echo '<span itemprop="itemListElement" itemtype="http://schema.org/ListItem">
                <a href="'.get_term_link($category->term_id, 'category').'">'.$category->name.'</a>
            </span>';
            echo '<span class="iu-breadcrumbs-separator">'.$attributes['separator'].'</span>';
        }*/

        echo '<span class="iu-breadcrumbs-current">'.$post->post_title.'</span>';
    echo '</div>';
        return;
    }

    if (is_singular('page')){
        if (!empty($post->post_parent)){
            echo '<span itemprop="itemListElement" itemtype="http://schema.org/ListItem">
                <a href="'.get_permalink($post->post_parent).'">'.get_post($post->post_parent)->post_title.'</a>
            </span>';
            echo '<span class="iu-breadcrumbs-separator">'.$attributes['separator'].'</span>';

        }

        echo '<span class="iu-breadcrumbs-current">'.$post->post_title.'</span>';
    echo '</div>';
        return;
    }

    if (is_singular('product')){
        $shop_id = woocommerce_get_page_id('shop');
        echo '<span itemprop="itemListElement" itemtype="http://schema.org/ListItem">
            <a href="'.get_permalink($shop_id).'">'.get_post($shop_id)->post_title.'</a>
        </span>';
        echo '<span class="iu-breadcrumbs-separator">'.$attributes['separator'].'</span>';

        if (has_term('', 'product_cat', $post->ID)){
            $ids = wc_get_product(get_queried_object()->ID)->get_category_ids();
            if (count($ids) === 1) {
                $category = get_term($ids[0]);
                $cats = get_ancestors($ids[0], 'product_cat');
            } else {
                $ancestors = get_ancestors($ids[0], 'product_cat');
                $category = get_term($ids[0]);

                for($i = 1; $i < count($ids); $i++){
                    $a = get_ancestors($ids[$i], 'product_cat');
                    if (count($a) > count($ancestors)) {
                        $ancestors = $a;
                        $category = get_term($ids[$i]);
                    }
                }
            }

            foreach(array_reverse($cats) as $cat_id){
                $cat = get_term($cat_id);
                echo '<span itemprop="itemListElement" itemtype="http://schema.org/ListItem">
                    <a href="'.get_term_link($cat->term_id).'">'.$cat->name.'</a>
                </span>';
                echo '<span class="iu-breadcrumbs-separator">'.$attributes['separator'].'</span>';
            }

            echo '<span itemprop="itemListElement" itemtype="http://schema.org/ListItem">
                <a href="'.get_term_link($category->term_id).'">'.$category->name.'</a>
            </span>';
            echo '<span class="iu-breadcrumbs-separator">'.$attributes['separator'].'</span>';
        }

        echo '<span class="iu-breadcrumbs-current">'.$post->post_title.'</span>';
    echo '</div>';
        return;
    }

    if (is_tax('product_cat')){
        $shop_id = woocommerce_get_page_id('shop');
        echo '<span itemprop="itemListElement" itemtype="http://schema.org/ListItem">
            <a href="'.get_permalink($shop_id).'">'.get_post($shop_id)->post_title.'</a>
        </span>';
        echo '<span class="iu-breadcrumbs-separator">'.$attributes['separator'].'</span>';

        $cats = [];
        $category = get_queried_object();
        while($category->parent != 0){
            $category = get_term($category->parent);
            $cats[] = $category;
        }

        foreach(array_reverse($cats) as $cat){
            echo '<span itemprop="itemListElement" itemtype="http://schema.org/ListItem">
                <a href="'.get_term_link($cat->term_id).'">'.$cat->name.'</a>
            </span>';
            echo '<span class="iu-breadcrumbs-separator">'.$attributes['separator'].'</span>';
        }
        echo '<span class="iu-breadcrumbs-current">'.get_queried_object()->name.'</span>';
        return;
    }

    if (is_home()){
        echo '<span class="iu-breadcrumbs-current">'.get_post(get_option('page_for_posts'))->post_title.'</span>';
    echo '</div>';
        return;
    }

    if (is_search()){
        $blog_id = get_option('page_for_posts');
        echo '<span itemprop="itemListElement" itemtype="http://schema.org/ListItem">
            <a href="'.get_permalink($blog_id).'">'.get_post($blog_id)->post_title.'</a>
        </span>';
        echo '<span class="iu-breadcrumbs-separator">'.$attributes['separator'].'</span>';
        
        echo '<span class="iu-breadcrumbs-current">Keresés erre: '.$_GET['s'].'</span>';
    echo '</div>';
        return;
    }

    if (is_category()){
        $blog_id = get_option('page_for_posts');
        echo '<span itemprop="itemListElement" itemtype="http://schema.org/ListItem">
            <a href="'.get_permalink($blog_id).'">'.get_post($blog_id)->post_title.'</a>
        </span>';
        echo '<span class="iu-breadcrumbs-separator">'.$attributes['separator'].'</span>';

        echo '<span class="iu-breadcrumbs-current">Kategória: '.get_queried_object()->name.'</span>';
    echo '</div>';
        return;
    }

    if (is_tag()){
        $blog_id = get_option('page_for_posts');
        echo '<span itemprop="itemListElement" itemtype="http://schema.org/ListItem">
            <a href="'.get_permalink($blog_id).'">'.get_post($blog_id)->post_title.'</a>
        </span>';
        echo '<span class="iu-breadcrumbs-separator">'.$attributes['separator'].'</span>';
        
        echo '<span class="iu-breadcrumbs-current">Címke: '.get_queried_object()->name.'</span>';
    echo '</div>';
        return;
    }

    $post_type = get_post_type();
    $post_type_object = get_post_type_object($post_type);

    if (is_post_type_archive()){
        echo '<span class="iu-breadcrumbs-current">'.$post_type_object->labels->name.'</span>';
    echo '</div>';
        return;
    }

    if ($post_type_object->has_archive){
        echo '<span itemprop="itemListElement" itemtype="http://schema.org/ListItem">
            <a href="'.get_post_type_archive_link($post_type).'">'.$post_type_object->labels->name.'</a>
        </span>';
        echo '<span class="iu-breadcrumbs-separator">'.$attributes['separator'].'</span>';
    }

        echo '<span class="iu-breadcrumbs-current">'.$post->post_title.'</span>';

        echo '</div>';
    echo '</div>';

})();