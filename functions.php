<?php
// functions.php in mytheme

add_action('after_setup_theme', function () {
  add_theme_support('title-tag');
  add_theme_support('post-thumbnails');
  add_theme_support('editor-styles');
  add_editor_style('assets/build/main.css');
});

add_action('wp_enqueue_scripts', function () {
  wp_enqueue_style(
    'mytheme-style',
    get_theme_file_uri('/assets/build/main.css'),
    [],
    filemtime(get_theme_file_path('/assets/build/main.css'))
  );
});

add_action('wp_head', function () {
  $favicon_svg = get_theme_file_uri('/assets/favicon.svg');

  if ($favicon_svg) {
    echo '<link rel="icon" type="image/svg+xml" sizes="any" href="' . esc_url($favicon_svg) . '">' . PHP_EOL;
  }
});
