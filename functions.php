<?php
// =========================
// News Shortcode - Fetches from /wp-json/wp/v2/news_item
// =========================
function news_shortcode($atts) {
    static $instance = 0;
    $instance++;

    $atts = shortcode_atts(array(
        'per_page' => 6,
    ), $atts, 'news');

    $per_page = max(1, min(12, intval($atts['per_page'])));

    $request_url = add_query_arg(array(
        'per_page' => $per_page,
        'orderby'  => 'date',
        'order'    => 'desc',
        '_embed'   => true, // For featured image if needed
    ), rest_url('wp/v2/news_item'));

    $response = wp_remote_get($request_url, array(
        'timeout' => 15,
        'headers' => array(
            'Accept' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        return '<div style="padding:20px;border:1px solid #e2e8f0;border-radius:10px;background:#fff7f7;color:#9b1c1c;">Unable to load news right now.</div>';
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $news_items = json_decode(wp_remote_retrieve_body($response), true);

    if (200 !== $status_code || !is_array($news_items) || empty($news_items)) {
        return '<div style="padding:20px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;color:#64748b;">No news items found.</div>';
    }

    ob_start();
    ?>
    <div class="news-container-<?php echo esc_attr($instance); ?>" style="padding:20px 0;">
        <h2 style="margin:0 0 24px;color:#163447;font-size:28px;font-weight:700;">Latest News</h2>
        <div style="display:grid;gap:20px;">
            <?php foreach ($news_items as $item) : ?>
                <?php
                $title = isset($item['title']['rendered']) ? wp_strip_all_tags($item['title']['rendered']) : 'Untitled';
                $excerpt = isset($item['excerpt']['rendered']) ? wp_strip_all_tags($item['excerpt']['rendered']) : '';
                $date = !empty($item['date']) ? mysql2date('F j, Y', $item['date']) : '';
                $link = !empty($item['link']) ? $item['link'] : '';
                $featured_image = '';
                if (isset($item['_embedded']['wp:featuredmedia'][0])) {
                    $image = $item['_embedded']['wp:featuredmedia'][0];
                    $featured_image = isset($image['media_details']['sizes']['medium']['source_url']) ? $image['media_details']['sizes']['medium']['source_url'] : (isset($image['source_url']) ? $image['source_url'] : '');
                }
                ?>
                <article style="border:1px solid #dbe4ea;border-radius:12px;padding:24px;background:#f9fbfd;display:flex;gap:20px;">
                    <?php if ($featured_image) : ?>
                        <div style="flex:0 0 120px;">
                            <img src="<?php echo esc_url($featured_image); ?>" alt="<?php echo esc_attr($title); ?>" style="width:100%;height:100px;object-fit:cover;border-radius:8px;">
                        </div>
                    <?php endif; ?>
                    <div style="flex:1;">
                        <h3 style="margin:0 0 12px;font-size:20px;line-height:1.4;color:#163447;">
                            <a href="<?php echo esc_url($link); ?>" style="text-decoration:none;color:#163447;"><?php echo esc_html($title); ?></a>
                        </h3>
                        <div style="color:#5b6b79;font-size:14px;margin-bottom:12px;"><?php echo esc_html($date); ?></div>
                        <div style="color:#555;line-height:1.6;margin-bottom:16px;font-size:15px;"><?php echo esc_html(wp_trim_words($excerpt, 25)); ?></div>
                        <a href="<?php echo esc_url($link); ?>" style="color:#0b3440;text-decoration:none;font-weight:600;font-size:14px;">Read More →</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('news', 'news_shortcode');

// =========================
//
// =========================
function inherit_wp_calendar_shortcode() {
    // We pass 'false' to the second parameter so it returns the string instead of echoing
    $calendar = get_calendar(true, false); 
    
    if (empty($calendar)) {
        // Fallback so the shortcode isn't "invisible" when there are no posts
        return '<div class="my-custom-styled-calendar"><p>No posts found for this month.</p></div>';
    }
    
    return '<div class="my-custom-styled-calendar">' .$calendar.'</div>';
}
// Ensure this tag matches what you type in WordPress: [wp_calendar]
add_shortcode('wp_calendar', 'inherit_wp_calendar_shortcode');

// =========================
// Camaligan Weather Shortcode
// =========================
function camaligan_weather_shortcode() {
    static $instance = 0;
    $instance++;

    // Camaligan / Daraga area coordinates
    $latitude  = 13.1417;
    $longitude = 123.6936;

    $cache_key = 'camaligan_weather_data';
    $weather_data = get_transient($cache_key);

    if ($weather_data === false) {
        $url = add_query_arg(array(
            'latitude'  => $latitude,
            'longitude' => $longitude,
            'current'   => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m',
            'daily'     => 'weather_code,temperature_2m_max,temperature_2m_min',
            'timezone'  => 'Asia/Manila',
            'forecast_days' => 5
        ), 'https://api.open-meteo.com/v1/forecast');

        $response = wp_remote_get($url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json'
            )
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            $weather_data = json_decode($body, true);

            if (!empty($weather_data) && is_array($weather_data)) {
                set_transient($cache_key, $weather_data, 30 * MINUTE_IN_SECONDS);
            } else {
                $weather_data = null;
            }
        } else {
            $weather_data = null;
        }
    }

    // Weather code to icon + label
    $get_weather_icon = function($code) {
        $map = array(
            0  => array('☀️', 'Clear sky'),
            1  => array('🌤️', 'Mainly clear'),
            2  => array('⛅', 'Partly cloudy'),
            3  => array('☁️', 'Overcast'),
            45 => array('🌫️', 'Fog'),
            48 => array('🌫️', 'Depositing rime fog'),
            51 => array('🌦️', 'Light drizzle'),
            53 => array('🌦️', 'Moderate drizzle'),
            55 => array('🌧️', 'Dense drizzle'),
            61 => array('🌦️', 'Slight rain'),
            63 => array('🌧️', 'Moderate rain'),
            65 => array('🌧️', 'Heavy rain'),
            66 => array('🌧️', 'Freezing rain'),
            67 => array('🌧️', 'Heavy freezing rain'),
            71 => array('🌨️', 'Slight snow'),
            73 => array('🌨️', 'Moderate snow'),
            75 => array('❄️', 'Heavy snow'),
            77 => array('❄️', 'Snow grains'),
            80 => array('🌦️', 'Rain showers'),
            81 => array('🌧️', 'Moderate showers'),
            82 => array('⛈️', 'Violent showers'),
            85 => array('🌨️', 'Snow showers'),
            86 => array('❄️', 'Heavy snow showers'),
            95 => array('⛈️', 'Thunderstorm'),
            96 => array('⛈️', 'Thunderstorm with hail'),
            99 => array('⛈️', 'Heavy thunderstorm')
        );

        return isset($map[$code]) ? $map[$code] : array('☁️', 'Unknown');
    };

    ob_start();
    ?>

    <div style="display:flex;justify-content:center;margin:20px 0;">
        <div id="weather-box-<?php echo esc_attr($instance); ?>" style="
            width:100%;
            max-width:560px;
            background:#fff;
            border-radius:12px;
            padding:28px 30px;
            box-shadow:0 4px 12px rgba(0,0,0,0.15);
            font-family:Arial,sans-serif;
            color:#163447;
            box-sizing:border-box;
        ">
            <h2 style="
                margin:0 0 24px 0;
                font-size:28px;
                line-height:1.2;
                font-weight:700;
                color:#163447;
            ">Weather Forecast</h2>

            <?php if (!empty($weather_data['current']) && !empty($weather_data['daily'])) : ?>
                <?php
                $current = $weather_data['current'];
                $daily   = $weather_data['daily'];

                $current_code = isset($current['weather_code']) ? intval($current['weather_code']) : 0;
                $current_icon = $get_weather_icon($current_code);

                $current_temp = isset($current['temperature_2m']) ? round($current['temperature_2m']) : '--';
                $humidity     = isset($current['relative_humidity_2m']) ? intval($current['relative_humidity_2m']) : '--';
                $wind_speed   = isset($current['wind_speed_10m']) ? round($current['wind_speed_10m']) : '--';

                $times = isset($daily['time']) ? $daily['time'] : array();
                $codes = isset($daily['weather_code']) ? $daily['weather_code'] : array();
                ?>

                <div style="
                    border-left:1px solid #e5e5e5;
                    border-right:1px solid #e5e5e5;
                    padding:4px 36px 0 36px;
                ">
                    <div style="
                        font-size:16px;
                        color:#7a7a7a;
                        margin-bottom:18px;
                    ">
                        Camaligan Proper, Daraga, Albay
                    </div>

                    <div style="
                        display:flex;
                        align-items:center;
                        gap:14px;
                        margin-bottom:10px;
                    ">
                        <div style="font-size:42px; line-height:1;">
                            <?php echo esc_html($current_icon[0]); ?>
                        </div>

                        <div style="
                            font-size:52px;
                            font-weight:700;
                            line-height:1;
                            color:#163447;
                        ">
                            <?php echo esc_html($current_temp); ?>°
                        </div>
                    </div>

                    <div style="
                        font-size:14px;
                        color:#7a5d3b;
                        margin-bottom:18px;
                    ">
                        <?php echo esc_html($humidity); ?>% Humidity - <?php echo esc_html($wind_speed); ?> km/h Winds
                    </div>

                    <div style="
                        border-top:1px solid #dcdcdc;
                        margin:8px 0 18px 0;
                    "></div>

                    <div style="
                        display:flex;
                        justify-content:space-between;
                        align-items:flex-start;
                        gap:10px;
                        text-align:center;
                    ">
                        <?php
                        if (!empty($times) && !empty($codes)) {
                            $count = min(5, count($times));

                            for ($i = 0; $i < $count; $i++) {
                                $day_ts = strtotime($times[$i]);
                                $day_label = date_i18n('D', $day_ts);
                                $day_icon = $get_weather_icon(intval($codes[$i]));
                                ?>
                                <div style="flex:1;">
                                    <div style="
                                        font-size:20px;
                                        line-height:1;
                                        margin-bottom:8px;
                                    ">
                                        <?php echo esc_html($day_icon[0]); ?>
                                    </div>
                                    <div style="
                                        font-size:14px;
                                        color:#4b4b4b;
                                    ">
                                        <?php echo esc_html($day_label); ?>
                                    </div>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </div>

            <?php else : ?>
                <div style="
                    text-align:center;
                    font-size:16px;
                    color:#666;
                    padding:20px 0;
                ">
                    Weather unavailable at the moment.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    return ob_get_clean();
}
// =========================
// Live Time Shortcode
// =========================
function custom_live_time_shortcode($atts) {
    static $instance = 0;
    $instance++;

    $atts = shortcode_atts(array(
        'timezone' => 'Asia/Manila',
        'title_color' => '#ffffff',
        'date_color' => '#d4af0d',
        'bg_color' => '#0b3440',
    ), $atts, 'live_time_card');

    ob_start();
    ?>

    <div style="display:flex;justify-content:center;margin:20px 0;">
        <div id="live-time-card-<?php echo esc_attr($instance); ?>" style="
            width:100%;
            max-width:620px;
            background:<?php echo esc_attr($atts['bg_color']); ?>;
            border-radius:10px;
            padding:38px 30px;
            box-shadow:0 4px 12px rgba(0,0,0,0.18);
            text-align:center;
            font-family: Times New Roman, Times, serif;
            box-sizing:border-box;
        ">
            <div id="live-time-<?php echo esc_attr($instance); ?>" style="
                font-size:40px;
                line-height:1.1;
                font-weight:400;
                color:<?php echo esc_attr($atts['title_color']); ?>;
                margin-bottom:26px;
                letter-spacing:1px;
            ">
                --:--:-- --
            </div>

            <div style="
                display:flex;
                justify-content:center;
                align-items:center;
                gap:12px;
                flex-wrap:wrap;
            ">
                <div id="live-date-<?php echo esc_attr($instance); ?>" style="
                    font-size:18px;
                    line-height:1.2;
                    font-weight:700;
                    color:<?php echo esc_attr($atts['date_color']); ?>;
                    font-family:Georgia, 'Times New Roman', serif;
                ">
                    Loading date...
                </div>

                <div aria-hidden="true" style="
                    width:28px;
                    height:28px;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                ">
                    <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var timeEl = document.getElementById("live-time-<?php echo $instance; ?>");
        var dateEl = document.getElementById("live-date-<?php echo $instance; ?>");
        var timeZone = <?php echo wp_json_encode($atts['timezone']); ?>;

        function updateClock() {
            var now = new Date();

            var timeText = now.toLocaleTimeString("en-US", {
                timeZone: timeZone,
                hour: "numeric",
                minute: "2-digit",
                second: "2-digit",
                hour12: true
            });

            var dateText = now.toLocaleDateString("en-US", {
                timeZone: timeZone,
                weekday: "long",
                year: "numeric",
                month: "long",
                day: "numeric"
            });

            timeEl.textContent = timeText;
            dateEl.textContent = dateText;
        }

        updateClock();
        setInterval(updateClock, 1000);
    })();
    </script>

    <?php
    return ob_get_clean();
}


// =========================
// Municipal Ordinances REST API
// =========================
function register_ordinances_rest_api() {
    // Register REST API route for fetching ordinances with category support
    register_rest_route('govph/v1', '/ordinances', array(
        'methods'             => 'GET',
        'callback'            => 'get_ordinances_callback',
        'permission_callback' => '__return_true',
        'args'                => array(
            'page'     => array(
                'validate_callback' => function($param) {
                    return is_numeric($param);
                },
                'default'           => 1,
            ),
            'per_page' => array(
                'validate_callback' => function($param) {
                    return is_numeric($param) && $param <= 100;
                },
                'default'           => 10,
            ),
            'search'   => array(
                'validate_callback' => function($param) {
                    return is_string($param);
                },
                'default'           => '',
            ),
            'category' => array(
                'validate_callback' => function($param) {
                    return is_string($param) || is_numeric($param);
                },
                'default'           => '',
            ),
            'category_id' => array(
                'validate_callback' => function($param) {
                    return is_numeric($param);
                },
                'default'           => 0,
            ),
        ),
    ));

    // Register REST API route to get all ordinance categories
    register_rest_route('govph/v1', '/ordinances/categories', array(
        'methods'             => 'GET',
        'callback'            => 'get_ordinance_categories_callback',
        'permission_callback' => '__return_true',
    ));

    // Register REST API route for single ordinance
    register_rest_route('govph/v1', '/ordinances/(?P<id>\d+)', array(
        'methods'             => 'GET',
        'callback'            => 'get_single_ordinance_callback',
        'permission_callback' => '__return_true',
        'args'                => array(
            'id' => array(
                'validate_callback' => function($param) {
                    return is_numeric($param);
                },
            ),
        ),
    ));
}
add_action('rest_api_init', 'register_ordinances_rest_api');

function get_ordinances_callback(WP_REST_Request $request) {
    $page        = intval($request->get_param('page'));
    $per_page    = intval($request->get_param('per_page'));
    $search      = sanitize_text_field($request->get_param('search'));
    $category    = sanitize_text_field($request->get_param('category'));
    $category_id = intval($request->get_param('category_id'));

    // Build query arguments
    $args = array(
        'post_type'      => 'post', // Change to custom post type if needed
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    // Add search parameter if provided
    if (!empty($search)) {
        $args['s'] = $search;
    }

    // Add category filtering
    if (!empty($category)) {
        $args['category_name'] = $category;
    } elseif (!empty($category_id) && $category_id > 0) {
        $args['cat'] = $category_id;
    }

    // Query posts
    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'No ordinances found',
            'data'    => array(),
        ), 404);
    }

    // Build response data
    $ordinances = array();
    foreach ($query->posts as $post) {
        $categories = get_the_category($post->ID);
        $category_list = array();
        if (!empty($categories)) {
            foreach ($categories as $cat) {
                $category_list[] = array(
                    'id'   => $cat->term_id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                );
            }
        }

        $ordinances[] = array(
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'slug'        => $post->post_name,
            'excerpt'     => wp_trim_words($post->post_content, 20),
            'date'        => $post->post_date,
            'date_gmt'    => $post->post_date_gmt,
            'link'        => get_permalink($post->ID),
            'status'      => $post->post_status,
            'categories'  => $category_list,
        );
    }

    return new WP_REST_Response(array(
        'success'      => true,
        'data'         => $ordinances,
        'total'        => $query->found_posts,
        'pages'        => $query->max_num_pages,
        'current_page' => $page,
        'per_page'     => $per_page,
    ), 200);
}

/**
 * Callback to fetch all ordinance categories
 * 
 * @return WP_REST_Response
 */
function get_ordinance_categories_callback() {
    $categories = get_categories(array(
        'hide_empty' => false,
    ));

    if (empty($categories)) {
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'No categories found',
            'data'    => array(),
        ), 404);
    }

    $category_list = array();
    foreach ($categories as $cat) {
        $category_list[] = array(
            'id'          => $cat->term_id,
            'name'        => $cat->name,
            'slug'        => $cat->slug,
            'description' => $cat->description,
            'count'       => $cat->count,
        );
    }

    return new WP_REST_Response(array(
        'success' => true,
        'data'    => $category_list,
        'total'   => count($category_list),
    ), 200);
}

/**
 * Callback to fetch a single ordinance
 * 
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function get_single_ordinance_callback(WP_REST_Request $request) {
    $post_id = intval($request->get_param('id'));

    $post = get_post($post_id);

    if (!$post || $post->post_status !== 'publish') {
        return new WP_Error(
            'ordinance_not_found',
            'Ordinance not found',
            array('status' => 404)
        );
    }

    $response = array(
        'id'       => $post->ID,
        'title'    => $post->post_title,
        'slug'     => $post->post_name,
        'content'  => wp_kses_post($post->post_content),
        'excerpt'  => $post->post_excerpt,
        'date'     => $post->post_date,
        'date_gmt' => $post->post_date_gmt,
        'link'     => get_permalink($post->ID),
        'status'   => $post->post_status,
        'author'   => array(
            'id'   => $post->post_author,
            'name' => get_the_author_meta('display_name', $post->post_author),
        ),
    );

    return new WP_REST_Response($response, 200);
}

// =========================
// Municipal Ordinances Shortcode
// =========================
function ordinances_shortcode($atts) {
    static $instance = 0;
    $instance++;

    $atts = shortcode_atts(array(
        'per_page'        => 10,
        'search'          => '',
        'category'        => '',
        'show_categories' => 'true',
    ), $atts, 'ordinances');

    $per_page        = intval($atts['per_page']);
    $search          = sanitize_text_field($atts['search']);
    $category        = sanitize_text_field($atts['category']);
    $show_categories = filter_var($atts['show_categories'], FILTER_VALIDATE_BOOLEAN);

    // Get all categories
    $categories = get_categories(array(
        'hide_empty' => false,
    ));

    // Build query arguments
    $args = array(
        'post_type'      => 'post',
        'posts_per_page' => $per_page,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    if (!empty($search)) {
        $args['s'] = $search;
    }

    if (!empty($category)) {
        $args['category_name'] = $category;
    }

    $query = new WP_Query($args);

    ob_start();
    ?>

    <div class="ordinances-container-<?php echo esc_attr($instance); ?>" style="padding: 20px 0;">
        <!-- Category Filters -->
        <?php if ($show_categories && !empty($categories)) : ?>
            <div style="
                margin-bottom: 30px;
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                align-items: flex-start;
            ">
                <button class="ordinance-category-btn-<?php echo esc_attr($instance); ?>" 
                    data-category="" 
                    style="
                        padding: 10px 16px;
                        border: 1px solid #e0e0e0;
                        background: <?php echo empty($category) ? '#163447' : '#fff'; ?>;
                        color: <?php echo empty($category) ? '#fff' : '#163447'; ?>;
                        border-radius: 6px;
                        font-weight: 600;
                        cursor: pointer;
                        transition: all 0.3s ease;
                        font-size: 14px;
                    ">
                    All
                </button>
                <?php foreach ($categories as $cat) : ?>
                    <button class="ordinance-category-btn-<?php echo esc_attr($instance); ?>" 
                        data-category="<?php echo esc_attr($cat->slug); ?>"
                        style="
                            padding: 10px 16px;
                            border: 1px solid #e0e0e0;
                            background: <?php echo $category === $cat->slug ? '#163447' : '#fff'; ?>;
                            color: <?php echo $category === $cat->slug ? '#fff' : '#163447'; ?>;
                            border-radius: 6px;
                            font-weight: 600;
                            cursor: pointer;
                            transition: all 0.3s ease;
                            font-size: 14px;
                        ">
                        <?php echo esc_html($cat->name); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Ordinances List -->
        <div class="ordinances-list-<?php echo esc_attr($instance); ?>">
            <?php if ($query->have_posts()) : ?>
                <div style="
                    display: grid;
                    gap: 15px;
                ">
                    <?php while ($query->have_posts()) : $query->the_post(); ?>
                        <div style="
                            border: 1px solid #ddd;
                            border-radius: 8px;
                            padding: 20px;
                            background: #f9f9f9;
                            transition: box-shadow 0.3s ease;
                        " onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.boxShadow='none'">
                            <h3 style="
                                margin: 0 0 10px 0;
                                font-size: 18px;
                                color: #163447;
                            ">
                                <a href="<?php the_permalink(); ?>" style="text-decoration: none; color: #163447;">
                                    <?php the_title(); ?>
                                </a>
                            </h3>
                            <div style="
                                color: #666;
                                font-size: 14px;
                                margin-bottom: 12px;
                            ">
                                <?php echo get_the_date('F j, Y'); ?>
                            </div>
                            <?php
                            $post_categories = get_the_category();
                            if (!empty($post_categories)) : ?>
                                <div style="
                                    display: flex;
                                    gap: 8px;
                                    margin-bottom: 12px;
                                    flex-wrap: wrap;
                                ">
                                    <?php foreach ($post_categories as $cat) : ?>
                                        <span style="
                                            background: #e8f4f8;
                                            color: #163447;
                                            padding: 4px 10px;
                                            border-radius: 4px;
                                            font-size: 12px;
                                            font-weight: 600;
                                        ">
                                            <?php echo esc_html($cat->name); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <div style="
                                color: #555;
                                line-height: 1.6;
                                margin-bottom: 12px;
                            ">
                                <?php echo wp_trim_words(get_the_content(), 30); ?>
                            </div>
                            <a href="<?php the_permalink(); ?>" style="
                                color: #0b3440;
                                text-decoration: none;
                                font-weight: 600;
                                font-size: 14px;
                            ">
                                Read More →
                            </a>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php wp_reset_postdata(); ?>
            <?php else : ?>
                <div style="
                    text-align: center;
                    padding: 40px 20px;
                    color: #999;
                    font-size: 16px;
                ">
                    No ordinances found.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    (function() {
        const instance = <?php echo intval($instance); ?>;
        const btns = document.querySelectorAll('.ordinance-category-btn-' + instance);
        const container = document.querySelector('.ordinances-container-' + instance);

        btns.forEach(btn => {
            btn.addEventListener('click', function() {
                const category = this.getAttribute('data-category');
                
                // Update button styles
                btns.forEach(b => {
                    b.style.background = '#fff';
                    b.style.color = '#163447';
                });
                this.style.background = '#163447';
                this.style.color = '#fff';

                // Fetch ordinances by category
                const url = new URL('<?php echo rest_url('govph/v1/ordinances'); ?>');
                if (category) {
                    url.searchParams.append('category', category);
                }

                fetch(url)
                    .then(res => res.json())
                    .then(data => {
                        const list = container.querySelector('.ordinances-list-' + instance);
                        if (data.data && data.data.length > 0) {
                            let html = '<div style="display: grid; gap: 15px;">';
                            data.data.forEach(post => {
                                let categoryTags = '';
                                if (post.categories && post.categories.length > 0) {
                                    categoryTags = post.categories.map(cat => 
                                        '<span style="background: #e8f4f8; color: #163447; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600;">' + cat.name + '</span>'
                                    ).join('');
                                    categoryTags = '<div style="display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap;">' + categoryTags + '</div>';
                                }

                                html += `
                                    <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; background: #f9f9f9; transition: box-shadow 0.3s ease;" onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.boxShadow='none'">
                                        <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #163447;">
                                            <a href="${post.link}" style="text-decoration: none; color: #163447;">${post.title}</a>
                                        </h3>
                                        <div style="color: #666; font-size: 14px; margin-bottom: 12px;">${new Date(post.date).toLocaleDateString('en-US', {year: 'numeric', month: 'long', day: 'numeric'})}</div>
                                        ${categoryTags}
                                        <div style="color: #555; line-height: 1.6; margin-bottom: 12px;">${post.excerpt}</div>
                                        <a href="${post.link}" style="color: #0b3440; text-decoration: none; font-weight: 600; font-size: 14px;">Read More →</a>
                                    </div>
                                `;
                            });
                            html += '</div>';
                            list.innerHTML = html;
                        } else {
                            list.innerHTML = '<div style="text-align: center; padding: 40px 20px; color: #999; font-size: 16px;">No ordinances found.</div>';
                        }
                    })
                    .catch(error => console.error('Error fetching ordinances:', error));
            });
        });
    })();
    </script>

    <?php
    return ob_get_clean();
}

// =========================
// Latest Annual Reports Shortcode
// =========================
function latest_annual_reports_shortcode($atts) {
    static $instance = 0;
    $instance++;

    $atts = shortcode_atts(array(
        'per_page' => 6,
        'title'    => 'Annual Reports',
    ), $atts, 'latest_annual_reports');

    $per_page = max(1, min(12, intval($atts['per_page'])));
    $heading  = sanitize_text_field($atts['title']);

    $request_url = add_query_arg(array(
        'per_page' => $per_page,
        'orderby'  => 'date',
        'order'    => 'desc',
        '_fields'  => 'id,date,link,title',
    ), rest_url('wp/v2/annual_report'));

    $response = wp_remote_get($request_url, array(
        'timeout' => 15,
        'headers' => array(
            'Accept' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        return '<div style="padding:20px;border:1px solid #e2e8f0;border-radius:10px;background:#fff7f7;color:#9b1c1c;">Unable to load annual reports right now.</div>';
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $reports     = json_decode(wp_remote_retrieve_body($response), true);

    if (200 !== $status_code || !is_array($reports) || empty($reports)) {
        return '<div style="padding:20px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;color:#64748b;">No annual reports found.</div>';
    }

    ob_start();
    ?>
    <div class="annual-reports-container-<?php echo esc_attr($instance); ?>" style="padding:20px 0;">
        <?php if (!empty($heading)) : ?>
            <h2 style="margin:0 0 18px;color:#163447;font-size:28px;font-weight:700;">
                <?php echo esc_html($heading); ?>
            </h2>
        <?php endif; ?>

        <div style="display:grid;gap:16px;">
            <?php foreach ($reports as $report) : ?>
                <?php
                $post_id   = isset($report['id']) ? intval($report['id']) : 0;
                $title     = isset($report['title']['rendered']) ? wp_strip_all_tags($report['title']['rendered']) : '';
                $date      = !empty($report['date']) ? mysql2date('F j, Y', $report['date']) : '';
                $year      = $post_id ? get_post_meta($post_id, 'annual_report_year', true) : '';
                $pdf_id    = $post_id ? absint(get_post_meta($post_id, 'annual_report_pdf_id', true)) : 0;
                $pdf_url   = $pdf_id ? wp_get_attachment_url($pdf_id) : '';
                $item_link = $pdf_url ? $pdf_url : (!empty($report['link']) ? $report['link'] : '');
                ?>
                <article style="border:1px solid #dbe4ea;border-radius:10px;padding:20px;background:#f8fbfd;">
                    <div style="display:flex;justify-content:space-between;gap:16px;align-items:flex-start;flex-wrap:wrap;">
                        <div style="flex:1 1 260px;">
                            <h3 style="margin:0 0 10px;font-size:20px;line-height:1.35;color:#163447;">
                                <?php echo esc_html($title); ?>
                            </h3>
                            <div style="display:flex;gap:12px;flex-wrap:wrap;color:#5b6b79;font-size:14px;">
                                <?php if (!empty($year)) : ?>
                                    <span><?php echo esc_html('Year: ' . $year); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($date)) : ?>
                                    <span><?php echo esc_html($date); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($item_link)) : ?>
                            <div style="flex:0 0 auto;">
                                <a href="<?php echo esc_url($item_link); ?>" <?php echo $pdf_url ? 'download' : ''; ?> style="display:inline-block;padding:11px 18px;background:#0b3440;color:#ffffff;text-decoration:none;border-radius:999px;font-weight:600;font-size:14px;">
                                    <?php echo esc_html($pdf_url ? 'Download PDF' : 'View Report'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

// =========================
// Budget Overview Shortcode
// =========================
function budget_overview_shortcode($atts) {
    static $instance = 0;
    $instance++;

    $atts = shortcode_atts(array(
        'per_page' => 5,
        'title'    => 'Budget Overview',
        'summary'  => 'Publish the LGU budget, Annual Investment Plan (AIP), and related appropriations in a clear tabular format. Replace the sample data with the actual figures and ordinance numbers.',
    ), $atts, 'budget_overview');

    $per_page = max(1, min(12, intval($atts['per_page'])));
    $heading  = sanitize_text_field($atts['title']);
    $summary  = sanitize_textarea_field($atts['summary']);

    $request_url = add_query_arg(array(
        'per_page' => $per_page,
        'orderby'  => 'date',
        'order'    => 'desc',
        '_fields'  => 'id,date,link,title',
    ), rest_url('wp/v2/budget_overview'));

    $response = wp_remote_get($request_url, array(
        'timeout' => 15,
        'headers' => array(
            'Accept' => 'application/json',
        ),
    ));

    if (is_wp_error($response)) {
        return '<div style="padding:20px;border:1px solid #e2e8f0;border-radius:10px;background:#fff7f7;color:#9b1c1c;">Unable to load budget overview right now.</div>';
    }

    $status_code = wp_remote_retrieve_response_code($response);
    $budgets     = json_decode(wp_remote_retrieve_body($response), true);

    if (200 !== $status_code || !is_array($budgets) || empty($budgets)) {
        return '<div style="padding:20px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;color:#64748b;">No budget overview entries found.</div>';
    }

    ob_start();
    ?>
    <section class="budget-overview-container-<?php echo esc_attr($instance); ?>" style="padding:28px;background:#ffffff;border:1px solid #d8dee4;border-radius:4px;box-shadow:0 2px 8px rgba(15, 23, 42, 0.12);">
        <?php if (!empty($heading)) : ?>
            <h2 style="margin:0 0 14px;color:#163447;font-size:28px;line-height:1.2;font-weight:700;">
                <?php echo esc_html($heading); ?>
            </h2>
        <?php endif; ?>

        <?php if (!empty($summary)) : ?>
            <p style="margin:0 0 18px;color:#2f3a45;font-size:14px;line-height:1.65;max-width:760px;">
                <?php echo esc_html($summary); ?>
            </p>
        <?php endif; ?>

        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;min-width:520px;">
                <thead>
                    <tr>
                        <th style="padding:0 14px 10px 0;text-align:left;color:#111827;font-size:14px;font-weight:700;">Year</th>
                        <th style="padding:0 14px 10px 0;text-align:left;color:#111827;font-size:14px;font-weight:700;">Ordinance No.</th>
                        <th style="padding:0 14px 10px 0;text-align:left;color:#111827;font-size:14px;font-weight:700;">Total Budget</th>
                        <th style="padding:0 0 10px;text-align:left;color:#111827;font-size:14px;font-weight:700;">Download</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($budgets as $budget) : ?>
                        <?php
                        $post_id       = isset($budget['id']) ? intval($budget['id']) : 0;
                        $year          = $post_id ? get_post_meta($post_id, 'budget_overview_year', true) : '';
                        $ordinance_no  = $post_id ? get_post_meta($post_id, 'budget_overview_ordinance_no', true) : '';
                        $total_budget  = $post_id ? get_post_meta($post_id, 'budget_overview_total_budget', true) : '';
                        $pdf_id        = $post_id ? absint(get_post_meta($post_id, 'budget_overview_pdf_id', true)) : 0;
                        $pdf_url       = $pdf_id ? wp_get_attachment_url($pdf_id) : '';
                        $fallback_link = !empty($budget['link']) ? $budget['link'] : '';
                        $download_link = $pdf_url ? $pdf_url : $fallback_link;
                        ?>
                        <tr>
                            <td style="padding:0 14px 12px 0;color:#1f2937;font-size:14px;vertical-align:top;">
                                <?php echo esc_html($year ?: 'N/A'); ?>
                            </td>
                            <td style="padding:0 14px 12px 0;color:#1f2937;font-size:14px;vertical-align:top;">
                                <?php echo esc_html($ordinance_no ?: 'N/A'); ?>
                            </td>
                            <td style="padding:0 14px 12px 0;color:#1f2937;font-size:14px;vertical-align:top;">
                                <?php echo esc_html($total_budget ?: 'N/A'); ?>
                            </td>
                            <td style="padding:0 0 12px;color:#1f2937;font-size:14px;vertical-align:top;">
                                <?php if (!empty($download_link)) : ?>
                                    <a href="<?php echo esc_url($download_link); ?>" <?php echo $pdf_url ? 'download' : ''; ?> style="color:#6b8fe8;text-decoration:underline;">
                                        View PDF
                                    </a>
                                <?php else : ?>
                                    <span style="color:#94a3b8;">No PDF</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php

    return ob_get_clean();
}

// Register shortcode
add_shortcode('ordinances', 'ordinances_shortcode');
add_shortcode('latest_annual_reports', 'latest_annual_reports_shortcode');
add_shortcode('annual_reports', 'latest_annual_reports_shortcode');
add_shortcode('budget_overview', 'budget_overview_shortcode');
add_shortcode('latest_budget_overview', 'budget_overview_shortcode');
add_shortcode('camaligan_weather', 'camaligan_weather_shortcode');
add_shortcode('live_time_card', 'custom_live_time_shortcode');

// =========================
// 