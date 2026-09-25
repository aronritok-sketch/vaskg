<?php

(function() use ($attributes){

    if (get_post_type() == 'iu_template'){
        echo 'Main query';
        return;
    }

    $atts = wp_parse_args($attributes, [
        'main_query' => true,
    	'params' => '',
    	'columns' => '',
    	'template' => '',
        'paging' => false,
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

    $params = json_decode('{'.trim($atts['params']).'}', true);

    if ($atts['paging']){
        $params['paged'] = max(1,get_query_var('paged'));
    }
    if (is_singular($params['post_type'])){
        //$params['post__not_in'] = [get_the_ID()];
    }

    global $wp_query;
    $query = $atts['main_query'] ? $wp_query : new \WP_Query($params);

    

    echo '<div class="iu-query iu-query-col-'.$atts['columns'].'" '.(!empty($style) ? 'style="'.$style.'"' : '').'>';

    if ( $query->have_posts() ){
        while ( $query->have_posts() ){
            $query->the_post();
            echo do_shortcode($atts['template']);
        }
    } elseif (is_search()){
        echo '<div class="no-result">Nincs találat</div>';
    }

    echo '</div>';

    wp_reset_query();
    wp_reset_postdata();

    if ($atts['paging'] && $query->max_num_pages > 1){
        $big = 999999999;

        $prev = '&larr; '.__('Previous');
        $next = __('Next').' &rarr;';

        $links = '<div class="iu-query-paging">';
        $links .= paginate_links( array(
            'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
            'format' => '/page/%#%',
            'current' => max( 1, get_query_var('paged') ),
            'total' => $query->max_num_pages,
            'mid_size' => 1,
            'prev_text' => $prev,
            'next_text' => $next
        ) );
        $links .= '</div>';

        if (strpos($links, 'prev page-numbers') === false){
            $links = str_replace('<div class="iu-query-paging">', '<div class="iu-query-paging"><span class="prev page-numbers placeholder">' . $prev . '</span>', $links);
        }
        if (strpos($links, 'next page-numbers') === false){
            $links = str_replace('</div>', '<span class="next page-numbers placeholder">' . $next . '</span></div>', $links);
        }

        echo $links;
    }

})();