<?php
// =========================
// Custom Calendar Shortcode
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



// Register shortcode
// add_shortcode('custom_calendar', 'custom_calendar_shortcode'); 
add_shortcode('camaligan_weather', 'camaligan_weather_shortcode');
add_shortcode('live_time_card', 'custom_live_time_shortcode');