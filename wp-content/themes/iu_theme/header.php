<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <title><?php echo wp_get_document_title() ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head() ?>
    <?php do_action('iu_theme_head_end') ?>
</head>

<body<?php IUTheme\Theme::body_class() ?>>
    <?php do_action('iu_theme_body_begin') ?>