<?php 

ob_start();
do_action('iu_header_template');
do_action('iu_main_template');
do_action('iu_footer_template');
$content = ob_get_clean();

get_header();
echo $content;
get_footer();