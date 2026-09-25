<?php
// wp eval-file dev/canon.php <mode> <file> [post_id] [home]
// mode=push: create/update a draft iu_template (no type meta) with file contents, print ID
// mode=pull: write post_content of post_id back to file (localhost URL -> {{home}} if .html under setup/)
$mode = $args[0]; $file = $args[1];
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']); wp_set_current_user($admins[0]);
kses_remove_filters();
remove_filter('content_save_pre', 'wp_strip_custom_css_from_blocks', 8);
if ($mode === 'push') {
    $content = file_get_contents($file);
    $content = str_replace('{{home}}', untrailingslashit(home_url()), $content);
    $title = 'CANON '.basename(dirname($file)).'/'.basename($file);
    $existing = get_posts(['post_type' => 'iu_template', 'title' => $title, 'post_status' => 'any', 'numberposts' => 1]);
    $data = ['post_type' => 'iu_template', 'post_status' => 'draft', 'post_title' => $title, 'post_content' => wp_slash($content)];
    if ($existing) { $data['ID'] = $existing[0]->ID; $id = wp_update_post($data); } else { $id = wp_insert_post($data); }
    echo $id;
} else {
    $id = (int)$args[2];
    $content = get_post_field('post_content', $id);
    if (!empty($args[3])) $content = str_replace(untrailingslashit(home_url()), '{{home}}', $content);
    file_put_contents($file, $content);
    echo "pulled $id -> $file\n";
}
