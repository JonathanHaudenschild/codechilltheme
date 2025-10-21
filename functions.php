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

// --- Shortcodes ---

// Luma checkout button shortcode: [luma_button id="evt-..." text="Register for Event" class="optional-classes"]
add_action('init', function () {
  add_shortcode('luma_button', function ($atts) {
    $atts = shortcode_atts([
      'id' => '',
      'text' => 'Register for Event',
      'class' => 'luma-checkout--button',
      'href' => '', // optional explicit href override
    ], $atts, 'luma_button');

    // Enqueue Luma script once, in footer
    wp_enqueue_script('luma-checkout', 'https://embed.lu.ma/checkout-button.js', [], null, true);

    $event_id = sanitize_text_field($atts['id']);
    $text = sanitize_text_field($atts['text']);
    $class = sanitize_html_class($atts['class']);
    $href = esc_url_raw($atts['href']);

    if (!$href && $event_id) {
      $href = 'https://luma.com/event/' . rawurlencode($event_id);
    } elseif (!$href) {
      return '';
    }

    $button = sprintf(
      '<a href="%1$s" class="%2$s" data-luma-action="checkout" data-luma-event-id="%3$s">%4$s</a>',
      esc_url($href),
      esc_attr($class),
      esc_attr($event_id),
      esc_html($text)
    );

    return $button;
  });
});

// Meetup upcoming events shortcode: [meetup_upcoming group="codeandchillclub" count="3"]
add_action('init', function () {
  add_shortcode('meetup_upcoming', function ($atts) {
    $atts = shortcode_atts([
      'group' => 'codeandchillclub',
      'count' => 3,
    ], $atts, 'meetup_upcoming');

    $group = sanitize_title($atts['group']);
    $count = max(1, (int)$atts['count']);
    $count = min($count, 10);

    if (empty($group)) {
      return '';
    }

    $transient_key = 'codechill_meetup_' . md5($group);
    $items = get_transient($transient_key);

    if (false === $items) {
      $feed_url = 'https://www.meetup.com/' . rawurlencode($group) . '/events/rss/';
      $response = wp_remote_get($feed_url, [
        'timeout' => 8,
        'headers' => [
          'Accept' => 'application/rss+xml, application/xml;q=0.9, */*;q=0.8',
        ],
      ]);

      if (is_wp_error($response)) {
        return '';
      }

      $body = wp_remote_retrieve_body($response);
      if (empty($body)) {
        return '';
      }

      // Parse RSS
      $xml = @simplexml_load_string($body);
      if (!$xml || empty($xml->channel->item)) {
        return '';
      }

      $items = [];
      foreach ($xml->channel->item as $item) {
        $items[] = [
          'title' => (string)$item->title,
          'link' => (string)$item->link,
          'pubDate' => (string)$item->pubDate,
          'description' => (string)$item->description,
        ];
      }

      // Cache for 10 minutes
      set_transient($transient_key, $items, MINUTE_IN_SECONDS * 10);
    }

    if (empty($items)) {
      return '';
    }

    $items = array_slice($items, 0, $count);

    // Build markup (Tailwind-friendly)
    $out = '<div class="meetup-events grid gap-6 md:grid-cols-2">';
    foreach ($items as $it) {
      $title = esc_html($it['title']);
      $link = esc_url($it['link']);
      $date = strtotime($it['pubDate']);
      $date_str = $date ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $date)) : '';

      $out .= '<div class="rounded-xl border border-slate-800 bg-slate-900/40 p-5 shadow">
        <div class="text-sm text-slate-400 mb-1">Upcoming Event</div>
        <h3 class="text-lg font-semibold text-white mb-2">' . $title . '</h3>
        <div class="text-slate-300 mb-4">' . $date_str . '</div>
        <div class="flex items-center gap-3">
          <a href="' . $link . '" target="_blank" rel="noopener" class="inline-flex items-center px-4 py-2 rounded-md bg-cyan-500 hover:bg-cyan-400 text-slate-900 font-medium transition">View on Meetup</a>
        </div>
      </div>';
    }
    $out .= '</div>';

    return $out;
  });
});

// --- Custom Post Types ---

// Register Events Custom Post Type
add_action('init', function () {
  register_post_type('event', [
    'labels' => [
      'name' => 'Events',
      'singular_name' => 'Event',
      'add_new' => 'Add New Event',
      'add_new_item' => 'Add New Event',
      'edit_item' => 'Edit Event',
      'new_item' => 'New Event',
      'view_item' => 'View Event',
      'search_items' => 'Search Events',
      'not_found' => 'No events found',
      'not_found_in_trash' => 'No events found in trash',
    ],
    'public' => true,
    'has_archive' => true,
    'show_in_rest' => true,
    'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
    'menu_icon' => 'dashicons-calendar-alt',
    'rewrite' => ['slug' => 'events'],
  ]);
});

// Add custom meta boxes for Event details
add_action('add_meta_boxes', function () {
  add_meta_box(
    'event_details',
    'Event Details',
    function ($post) {
      wp_nonce_field('event_details_nonce', 'event_details_nonce');

      $event_date = get_post_meta($post->ID, '_event_date', true);
      $event_time = get_post_meta($post->ID, '_event_time', true);
      $event_location = get_post_meta($post->ID, '_event_location', true);
      $meetup_url = get_post_meta($post->ID, '_meetup_url', true);
      $luma_event_id = get_post_meta($post->ID, '_luma_event_id', true);

      echo '<style>
        .event-meta-field { margin-bottom: 15px; }
        .event-meta-field label { display: block; font-weight: 600; margin-bottom: 5px; }
        .event-meta-field input[type="text"],
        .event-meta-field input[type="date"],
        .event-meta-field input[type="time"] { width: 100%; padding: 5px; }
      </style>';

      echo '<div class="event-meta-field">
        <label for="event_date">Event Date</label>
        <input type="date" id="event_date" name="event_date" value="' . esc_attr($event_date) . '" />
      </div>';

      echo '<div class="event-meta-field">
        <label for="event_time">Event Time</label>
        <input type="time" id="event_time" name="event_time" value="' . esc_attr($event_time) . '" />
      </div>';

      echo '<div class="event-meta-field">
        <label for="event_location">Location</label>
        <input type="text" id="event_location" name="event_location" value="' . esc_attr($event_location) . '" placeholder="e.g., Berlin, Germany" />
      </div>';

      echo '<div class="event-meta-field">
        <label for="meetup_url">Meetup URL</label>
        <input type="text" id="meetup_url" name="meetup_url" value="' . esc_attr($meetup_url) . '" placeholder="https://www.meetup.com/..." />
      </div>';

      echo '<div class="event-meta-field">
        <label for="luma_event_id">Luma Event ID</label>
        <input type="text" id="luma_event_id" name="luma_event_id" value="' . esc_attr($luma_event_id) . '" placeholder="evt-wFhCiFoQzzMusiw" />
        <p style="font-size: 12px; color: #666; margin-top: 5px;">Enter just the event ID from your Luma event URL</p>
      </div>';
    },
    'event',
    'normal',
    'high'
  );
});

// Save event meta data
add_action('save_post_event', function ($post_id) {
  if (!isset($_POST['event_details_nonce']) || !wp_verify_nonce($_POST['event_details_nonce'], 'event_details_nonce')) {
    return;
  }

  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return;
  }

  if (!current_user_can('edit_post', $post_id)) {
    return;
  }

  $fields = ['event_date', 'event_time', 'event_location', 'meetup_url', 'luma_event_id'];

  foreach ($fields as $field) {
    if (isset($_POST[$field])) {
      update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
    }
  }
});

// Shortcode to display WordPress events: [wp_events count="3"]
add_action('init', function () {
  add_shortcode('wp_events', function ($atts) {
    $atts = shortcode_atts([
      'count' => 3,
      'show_past' => false,
    ], $atts, 'wp_events');

    $args = [
      'post_type' => 'event',
      'posts_per_page' => (int)$atts['count'],
      'orderby' => 'meta_value',
      'meta_key' => '_event_date',
      'order' => 'ASC',
    ];

    if (!$atts['show_past']) {
      $args['meta_query'] = [
        [
          'key' => '_event_date',
          'value' => date('Y-m-d'),
          'compare' => '>=',
          'type' => 'DATE',
        ],
      ];
    }

    $events = new WP_Query($args);

    if (!$events->have_posts()) {
      return '<p class="text-slate-400">No upcoming events at the moment. Check back soon!</p>';
    }

    $out = '<div class="wp-events space-y-6">';

    while ($events->have_posts()) {
      $events->the_post();
      $event_date = get_post_meta(get_the_ID(), '_event_date', true);
      $event_time = get_post_meta(get_the_ID(), '_event_time', true);
      $event_location = get_post_meta(get_the_ID(), '_event_location', true);
      $meetup_url = get_post_meta(get_the_ID(), '_meetup_url', true);
      $luma_event_id = get_post_meta(get_the_ID(), '_luma_event_id', true);

      $date_formatted = $event_date ? date_i18n('F j, Y', strtotime($event_date)) : '';
      $time_formatted = $event_time ? date_i18n('g:i A', strtotime($event_time)) : '';

      $out .= '<div class="event-item rounded-xl border border-slate-800 bg-slate-900/40 p-5 shadow">';
      $out .= '<div class="command-line flex items-center space-x-2 mb-3">';
      $out .= '<span class="prompt text-cyan-400">event@codechillclub:~$</span>';
      $out .= '<span class="command text-yellow-400">cat ' . sanitize_title(get_the_title()) . '.txt</span>';
      $out .= '</div>';

      $out .= '<div class="output pl-6">';
      $out .= '<h3 class="text-xl font-bold text-white mb-2">' . get_the_title() . '</h3>';

      if ($date_formatted) {
        $out .= '<div class="text-green-400 mb-1">📅 ' . esc_html($date_formatted);
        if ($time_formatted) {
          $out .= ' at ' . esc_html($time_formatted);
        }
        $out .= '</div>';
      }

      if ($event_location) {
        $out .= '<div class="text-cyan-400 mb-2">📍 ' . esc_html($event_location) . '</div>';
      }

      if (get_the_excerpt()) {
        $out .= '<p class="text-slate-300 mb-4">' . get_the_excerpt() . '</p>';
      }

      $out .= '<div class="flex items-center gap-3 flex-wrap">';

      if ($meetup_url) {
        $out .= '<a href="' . esc_url($meetup_url) . '" target="_blank" rel="noopener" class="inline-flex items-center px-4 py-2 rounded-md bg-cyan-500 hover:bg-cyan-400 text-slate-900 font-medium transition">View on Meetup</a>';
      }

      if ($luma_event_id) {
        $luma_href = 'https://luma.com/event/' . rawurlencode($luma_event_id);
        $out .= '<a href="' . esc_url($luma_href) . '" target="_blank" rel="noopener" class="inline-flex items-center px-4 py-2 rounded-md bg-emerald-500 hover:bg-emerald-400 text-slate-900 font-medium transition">Register on Luma</a>';
      }

      $out .= '</div>';
      $out .= '</div>'; // output
      $out .= '</div>'; // event-item
    }

    $out .= '</div>';

    wp_reset_postdata();

    return $out;
  });
});

// Register a reusable block pattern for a terminal-styled Events section
add_action('init', function () {
  if (!function_exists('register_block_pattern')) {
    return;
  }

  $content = <<<'HTML'
<!-- wp:group {"className":"terminal-window macos-style rounded-xl shadow-2xl backdrop-blur-xl border transition-all duration-500"} -->
<div class="wp-block-group terminal-window macos-style rounded-xl shadow-2xl backdrop-blur-xl border transition-all duration-500"><!-- wp:group {"className":"terminal-header flex items-center justify-between p-4 border-b"} -->
  <div class="wp-block-group terminal-header flex items-center justify-between p-4 border-b"><!-- wp:html -->
    <div class="terminal-controls flex space-x-2"><div class="control-dot close-dot"></div><div class="control-dot minimize-dot"></div><div class="control-dot maximize-dot"></div></div>
  <!-- /wp:html --><!-- wp:paragraph {"className":"terminal-title font-mono text-sm opacity-80"} -->
  <p class="terminal-title font-mono text-sm opacity-80">Events Console</p>
  <!-- /wp:paragraph --><!-- wp:html -->
  <div class="w-16"></div>
  <!-- /wp:html --></div>
  <!-- /wp:group --><!-- wp:group {"className":"terminal-content p-6 font-mono text-sm h-full w-full overflow-auto"} -->
  <div class="wp-block-group terminal-content p-6 font-mono text-sm h-full w-full overflow-auto"><!-- wp:heading {"level":2,"className":"text-2xl md:text-3xl font-bold mb-4"} -->
  <h2 class="text-2xl md:text-3xl font-bold mb-4">Upcoming Events</h2>
  <!-- /wp:heading --><!-- wp:shortcode -->
  [meetup_upcoming group="codeandchillclub" count="3"]
  <!-- /wp:shortcode --><!-- wp:spacer {"height":"24px"} -->
  <div style="height:24px" aria-hidden="true" class="wp-block-spacer"></div>
  <!-- /wp:spacer --><!-- wp:heading {"level":2,"className":"text-2xl md:text-3xl font-bold mb-2"} -->
  <h2 class="text-2xl md:text-3xl font-bold mb-2">Featured Event</h2>
  <!-- /wp:heading --><!-- wp:paragraph {"className":"text-slate-300 mb-4"} -->
  <p class="text-slate-300 mb-4">Secure your spot at our next Code & Chill session:</p>
  <!-- /wp:paragraph --><!-- wp:shortcode -->
  [luma_button id="evt-wFhCiFoQzzMusiw" text="Register for Event" class="luma-checkout--button inline-flex items-center px-5 py-3 rounded-md bg-emerald-400 hover:bg-emerald-300 text-slate-900 font-semibold transition"]
  <!-- /wp:shortcode --></div>
  <!-- /wp:group --></div>
  <!-- /wp:group -->
HTML;

  register_block_pattern(
    'codechill/terminal-events',
    [
      'title'       => __('Terminal: Events', 'codechilltheme'),
      'description' => __('Terminal-styled Upcoming + Featured Event section with Meetup and Luma button.', 'codechilltheme'),
      'categories'  => ['theme', 'text', 'featured'],
      'content'     => $content,
    ]
  );
});
