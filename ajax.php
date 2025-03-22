<?php
/**
 * AJAX-обработчик для загрузки контента вкладок настроек Steam Auth.
 *
 * Эта функция обрабатывает AJAX-запросы для загрузки содержимого
 * конкретной вкладки, указанной в параметре 'tab'. В зависимости от
 * переданного значения 'tab', загружается соответствующий шаблон.
 * Если параметр не указан, по умолчанию загружается вкладка 'general'.
 *
 * @since 1.0.0
 */
add_action('wp_ajax_steam_auth_admin_load_tab', 'steam_auth_admin_load_tab');
function steam_auth_admin_load_tab() {
    $tab = isset($_POST['tab']) ? $_POST['tab'] : 'general';
    $api_key = get_option('steam_api_key', '');
    $bot_url = get_option('bot_url_qery', '');
    $guild_id = get_option('steam_auth_discord_guild_id', '958141724054671420');
    $default_role = get_option('steam_default_role', 'subscriber');
    $debug_mode = get_option('steam_auth_debug', false);
    $admin_key = get_option('steam_auth_admin_key', '');
    
    $profile_settings = get_option('steam_profile_settings', []);
    //$logs = get_option('steam_auth_logs', []);
    $discord_unlink_requests = get_option('steam_auth_discord_unlink_requests', []);
    $roles = wp_roles()->get_names();

    if ($debug_mode) {
        error_log("Загрузка вкладки: $tab");
    }

    ob_start();
    if ($tab === 'general') {
        require __DIR__ . '/templates/general.php';
    } elseif ($tab === 'profile') {
        require __DIR__ . '/templates/profile.php';
    } elseif ($tab === 'logs') {
        require __DIR__ . '/templates/logs.php';
    } elseif ($tab === 'discord-unlink') {
        require __DIR__ . '/templates/discord-unlink.php';
    } elseif ($tab === 'messages') {
        require __DIR__ . '/templates/messages.php';
    } elseif ($tab === 'discord-notifications') {
        require __DIR__ . '/templates/discord-notifications.php';
    } elseif ($tab === 'mods') {
        $discord_roles = fetch_discord_roles();
        $mods_config = get_option('steam_auth_mods_config', []);
        // Добавляем массив выбранных ролей для модов
        $selected_mod_roles = isset($mods_config['selected_roles']) ? $mods_config['selected_roles'] : [];
        require __DIR__ . '/templates/mods.php';
    }
    echo ob_get_clean();
    wp_die();
}

/**
 * Отправляет сообщение Steam-юзеру(ам) с помощью Discord Webhook.
 * 
 * Функция обрабатывает AJAX-запрос, отправляемый из админки, и посылает
 * сообщение, если отправитель - админ.
 * 
 * @since 1.0.0
 * 
 * @global array $default_templates - массив предопределенных шаблонов
 * @global array $custom_templates - массив пользовательских шаблонов
 * 
 * @return void
 */
add_action('wp_ajax_steam_auth_send_message', 'steam_auth_send_message');
function steam_auth_send_message() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();

    $user_id = intval($_POST['user_id']);
    $role = sanitize_text_field($_POST['role']);
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : 'Без заголовка';
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : 'Сообщение отсутствует';
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'general'; // Добавляем категорию
    $template = sanitize_text_field($_POST['discord_embed_template']);

    $message_id = add_user_message($user_id, $role, $title, $content, false, $category);

    $custom_templates = get_option('steam_auth_discord_custom_templates', []);
    $default_templates = [
        'success' => [
            'color' => '3066993',
            'fields' => [
                'title' => true, 'title_emoji' => '✅',
                'description' => true, 'description_emoji' => '🎉',
                'timestamp' => true,
                'footer' => true, 'footer_icon' => 'https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_small.png', 'footer_emoji' => '🌟',
                'author' => true, 'author_icon' => home_url('/favicon.ico'), 'author_emoji' => '',
                'custom' => []
            ]
        ],
        'error' => [
            'color' => '15548997',
            'fields' => [
                'title' => true, 'title_emoji' => '❌',
                'description' => true, 'description_emoji' => '⚠️',
                'timestamp' => true,
                'footer' => true, 'footer_icon' => 'https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_small.png', 'footer_emoji' => '🔥',
                'author' => true, 'author_icon' => home_url('/favicon.ico'), 'author_emoji' => '',
                'custom' => []
            ]
        ],
        'warning' => [
            'color' => '16776960',
            'fields' => [
                'title' => true, 'title_emoji' => '⚠️',
                'description' => true, 'description_emoji' => '📢',
                'timestamp' => true,
                'footer' => true, 'footer_icon' => 'https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_small.png', 'footer_emoji' => '🔔',
                'author' => true, 'author_icon' => home_url('/favicon.ico'), 'author_emoji' => '',
                'custom' => []
            ]
        ],
        'info' => [
            'color' => '3447003',
            'fields' => [
                'title' => true, 'title_emoji' => 'ℹ️',
                'description' => true, 'description_emoji' => '📩',
                'timestamp' => true,
                'footer' => true, 'footer_icon' => 'https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_small.png', 'footer_emoji' => '💡',
                'author' => true, 'author_icon' => home_url('/favicon.ico'), 'author_emoji' => '',
                'custom' => []
            ]
        ]
    ];

    $users = $user_id == 0 ? get_users($role ? ['role' => $role] : []) : [get_userdata($user_id)];
    $count = 0;

    foreach ($users as $user) {
        $discord_id = get_user_meta($user->ID, 'discord_id', true);
        $notifications_enabled = get_user_meta($user->ID, 'discord_notifications_enabled', true);
        if ($discord_id && $notifications_enabled !== '0') {
            $template_settings = null;
            if ($template) {
                $template_settings = strpos($template, 'custom_') === 0 
                    ? $custom_templates[substr($template, 7)] 
                    : ($default_templates[$template] ?? null);
                if (!$template_settings && get_option('steam_auth_debug', false)) {
                    error_log("Steam Auth: Шаблон '$template' не найден");
                }
            }
            if (send_discord_message($discord_id, $title, $content, $template_settings)) {
                $count++;
            }
        }
    }

    wp_send_json_success("Сообщение отправлено $count пользователям");
}

// Новая функция для отправки уведомлений в Discord
function send_discord_notification($discord_id, $title, $description, $template = 'info') {
    $embed_settings = get_option('steam_auth_discord_embed_settings', []);
    $custom_templates = get_option('steam_auth_discord_custom_templates', []);

    // Встроенные шаблоны
    $templates = [
        'success' => ['color' => 3066993, 'title_emoji' => '✅', 'description_emoji' => '🎉', 'footer_emoji' => '🌟'],
        'error' => ['color' => 15548997, 'title_emoji' => '❌', 'description_emoji' => '⚠️', 'footer_emoji' => '🔥'],
        'warning' => ['color' => 16776960, 'title_emoji' => '⚠️', 'description_emoji' => '📢', 'footer_emoji' => '🔔'],
        'info' => ['color' => 3447003, 'title_emoji' => 'ℹ️', 'description_emoji' => '📩', 'footer_emoji' => '💡']
    ];

    // Проверяем, является ли шаблон пользовательским
    if (strpos($template, 'custom_') === 0) {
        $custom_key = str_replace('custom_', '', $template);
        if (isset($custom_templates[$custom_key])) {
            $template_data = [
                'color' => (int)$custom_templates[$custom_key]['color'],
                'title_emoji' => $custom_templates[$custom_key]['fields']['title_emoji'] ?? '',
                'description_emoji' => $custom_templates[$custom_key]['fields']['description_emoji'] ?? '',
                'footer_emoji' => $custom_templates[$custom_key]['fields']['footer_emoji'] ?? ''
            ];
        } else {
            $template_data = $templates['info']; // По умолчанию "info", если пользовательский шаблон не найден
        }
    } else {
        $template_data = isset($templates[$template]) ? $templates[$template] : $templates['info'];
    }

    $embed = [
        'title' => $template_data['title_emoji'] . ' ' . $title,
        'description' => $template_data['description_emoji'] . ' ' . $description,
        'color' => $template_data['color'],
        'timestamp' => current_time('c'),
        'footer' => [
            'text' => $template_data['footer_emoji'] . ' Steam Auth Notification',
            'icon_url' => $embed_settings['fields']['footer_icon'] ?? ''
        ],
        'author' => [
            'name' => ($embed_settings['fields']['author_emoji'] ?? '') . ' ' . get_bloginfo('name'),
            'url' => home_url(),
            'icon_url' => $embed_settings['fields']['author_icon'] ?? ''
        ]
    ];

    $bot_token = get_option('steam_auth_discord_bot_token', '');
    if (empty($bot_token)) {
        error_log('Токен бота Discord не настроен');
        return;
    }

    $url = "https://discord.com/api/v10/users/@me/channels";
    $response = wp_remote_post($url, [
        'headers' => ['Authorization' => "Bot $bot_token", 'Content-Type' => 'application/json'],
        'body' => json_encode(['recipient_id' => $discord_id]),
        'timeout' => 10
    ]);

    if (is_wp_error($response)) {
        error_log('Ошибка создания DM канала: ' . $response->get_error_message());
        return;
    }

    $channel_data = json_decode(wp_remote_retrieve_body($response), true);
    $channel_id = $channel_data['id'] ?? '';
    if (!$channel_id) {
        error_log('Не удалось создать DM канал');
        return;
    }

    $message_url = "https://discord.com/api/v10/channels/$channel_id/messages";
    $response = wp_remote_post($message_url, [
        'headers' => ['Authorization' => "Bot $bot_token", 'Content-Type' => 'application/json'],
        'body' => json_encode(['embeds' => [$embed]]),
        'timeout' => 10
    ]);

    if (is_wp_error($response)) {
        error_log('Ошибка отправки Discord-сообщения: ' . $response->get_error_message());
    }
}

/**
 * AJAX-функция для очистки логов Steam Auth.
 *
 * Функция-обработчик AJAX-запроса, вызываемый при отправке формы очистки логов.
 *
 * @since 1.0.0
 */
function steam_auth_clear_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_auth_logs';

    /*
     * Проверка прав доступа.
     *
     * только администраторы могут очищать логи.
     */
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Недостаточно прав']);
        return;
    }

    /*
     * Проверка nonce.
     *
     * nonce - это случайное значение, которое генерируется WordPress
     * для предотвращения CSRF-атак.
     */
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'steam_auth_nonce')) {
        wp_send_json_error(['message' => 'Ошибка проверки nonce']);
        return;
    }

    /*
     * Очистка логов.
     *
     * если таблица логов существует, то очищаем ее.
     */
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        $result = $wpdb->query("TRUNCATE TABLE $table_name");
        if ($result === false) {
            wp_send_json_error(['message' => 'Ошибка базы данных: ' . $wpdb->last_error]);
        } else {
            wp_send_json_success(['message' => 'Логи успешно очищены']);
        }
    } else {
        wp_send_json_error(['message' => 'Таблица логов не найдена']);
    }
}

add_action('wp_ajax_steam_auth_clear_logs', 'steam_auth_clear_logs');

add_action('wp_ajax_steam_auth_save_settings', 'steam_auth_save_settings');
/**
 * Обработка сохранения настроек Steam Auth.
 *
 * Функция-обработчик AJAX-запроса, вызываемый при отправке форм настроек.
 *
 * @since 2.0.0
 */
function steam_auth_save_settings() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    $data = $_POST;

    if (isset($data['general'])) {
        update_option('steam_api_key', sanitize_text_field($data['steam_api_key']));
        update_option('bot_url_qery', sanitize_text_field($data['bot_url_qery']));
        update_option('steam_auth_discord_guild_id', sanitize_text_field($data['steam_auth_discord_guild_id']));
        update_option('steam_default_role', sanitize_text_field($data['steam_default_role']));
        update_option('steam_auth_debug', isset($data['steam_auth_debug']) ? true : false);
        update_option('steam_auth_admin_key', sanitize_text_field($data['steam_auth_admin_key']));
        update_option('steam_auth_custom_login_enabled', isset($data['steam_auth_custom_login_enabled']) ? true : false);
        update_option('steam_auth_discord_client_id', sanitize_text_field($data['steam_auth_discord_client_id']));
        update_option('steam_auth_discord_client_secret', sanitize_text_field($data['steam_auth_discord_client_secret']));
        update_option('steam_auth_discord_bot_token', sanitize_text_field($data['steam_auth_discord_bot_token']));
        update_option('steam_auth_bot_api_key', sanitize_text_field($data['steam_auth_bot_api_key'])); // Новый ключ
        update_option('steam_auth_log_limit', max(10, min(1000, intval($data['steam_auth_log_limit'])))); // Новый лимит

        wp_send_json_success(['message' => 'Общие настройки сохранены', 'tab' => 'general']);
    } elseif (isset($data['profile'])) {
        $profile_settings = steam_auth_sanitize_profile_settings($data);
        update_option('steam_profile_settings', $profile_settings);
        wp_send_json_success(['message' => 'Настройки профиля сохранены', 'tab' => 'profile']);
    } elseif (isset($data['discord-notifications'])) {
        $embed_settings = steam_auth_sanitize_embed_settings($data);
        update_option('steam_auth_discord_embed_settings', $embed_settings);
        wp_send_json_success(['message' => 'Настройки Discord Embed сохранены', 'tab' => 'discord-notifications']);
    } elseif (isset($data['mods'])) {
        $mods_config = steam_auth_sanitize_mods_config($data['mods']);
        update_option('steam_auth_mods_config', $mods_config);
        wp_send_json_success(['message' => 'Настройки модов сохранены', 'tab' => 'mods']);
    }

    wp_send_json_error('Ошибка сохранения');
}

add_action('wp_ajax_steam_auth_remove_field', 'steam_auth_remove_field');
function steam_auth_remove_field() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    $field_key = isset($_POST['field_key']) ? sanitize_key($_POST['field_key']) : '';

    if (empty($field_key)) {
        wp_send_json_error('Не указан ключ поля');
    }

    $profile_settings = get_option('steam_profile_settings');
    if (isset($profile_settings['custom_fields'][$field_key])) {
        unset($profile_settings['custom_fields'][$field_key]);
        update_option('steam_profile_settings', $profile_settings);
        wp_send_json_success('Поле удалено');
    } else {
        wp_send_json_error('Поле не найдено');
    }
}

add_action('wp_ajax_steam_auth_approve_unlink_discord', 'steam_auth_approve_unlink_discord');
/**
 * AJAX-обработчик одобрения запроса на отвязку Discord.
 *
 * Функция-обработчик AJAX-запроса, вызываемый при отправке формы одобрения
 * запроса на отвязку Discord.
 *
 * @since 2.0.0
 */
function steam_auth_approve_unlink_discord() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
    }

    $user_id = intval($_POST['user_id']);
    $discord_unlink_requests = get_option('steam_auth_discord_unlink_requests', []);
    if (isset($discord_unlink_requests[$user_id])) {
        $steam_id = get_user_meta($user_id, 'steam_id', true);
        $discord_id = $discord_unlink_requests[$user_id]['id'];
        $discord_username = $discord_unlink_requests[$user_id]['username'];

        // Отправляем уведомление перед удалением метаданных
        $template_settings = [
            'color' => '15548997', // Error (красный)
            'fields' => [
                'title' => true, 'title_emoji' => '❌',
                'description' => true, 'description_emoji' => '⚠️',
                'timestamp' => true,
                'footer' => true, 'footer_icon' => 'https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_small.png', 'footer_emoji' => '🔥',
                'author' => true, 'author_icon' => home_url('/favicon.ico'), 'author_emoji' => '',
                'custom' => []
            ]
        ];
        send_discord_message($discord_id, 'Discord отвязан', "Ваш Discord ($discord_username) был отвязан от Steam ($steam_id) администратором.", $template_settings);

        delete_user_meta($user_id, 'discord_id');
        delete_user_meta($user_id, 'discord_username');
        unset($discord_unlink_requests[$user_id]);
        update_option('steam_auth_discord_unlink_requests', $discord_unlink_requests);
        log_steam_action($steam_id, 'discord_unlink_approved', $discord_id, $discord_username);

        wp_send_json_success('Отвязка одобрена');
    }
    wp_send_json_error('Запрос не найден');
}

add_action('wp_ajax_steam_auth_reject_unlink_discord', 'steam_auth_reject_unlink_discord');

/**
 * AJAX-обработчик отклонения запроса на отвязку Discord.
 *
 * Функция-обработчик AJAX-запроса, вызываемый при отправке формы отклонения
 * запроса на отвязку Discord.
 *
 * @since 2.0.0
 */
function steam_auth_reject_unlink_discord() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
    }

    $user_id = intval($_POST['user_id']);
    $discord_unlink_requests = get_option('steam_auth_discord_unlink_requests', []);
    if (isset($discord_unlink_requests[$user_id])) {
        $steam_id = get_user_meta($user_id, 'steam_id', true);
        $discord_id = $discord_unlink_requests[$user_id]['id'];
        $discord_username = $discord_unlink_requests[$user_id]['username'];

        unset($discord_unlink_requests[$user_id]);
        update_option('steam_auth_discord_unlink_requests', $discord_unlink_requests);
        log_steam_action($steam_id, 'discord_unlink_rejected', $discord_id, $discord_username);
        
        wp_send_json_success('Запрос на отвязку отклонён');
        $template_settings = [/* ... */];
        send_discord_message($discord_id, 'Запрос на отвязку отклонён', "Ваш запрос на отвязку Discord ($discord_username) от Steam ($steam_id) был отклонён администратором.", $template_settings);
    }
    wp_send_json_error('Запрос не найден');
}

add_action('wp_ajax_steam_auth_test_discord_embed', function() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    $user_id = get_current_user_id();
    $discord_id = get_user_meta($user_id, 'discord_id', true);
    
    if (!$discord_id) {
        wp_send_json_error('Discord ID не привязан к вашему аккаунту.');
    }

    $data = $_POST;
    $template = isset($data['discord_embed_template']) ? sanitize_text_field($data['discord_embed_template']) : 'info';
    
    $templates = [
        'success' => [
            'color' => 3066993,
            'title_emoji' => '✅',
            'description_emoji' => '🎉',
            'footer_emoji' => '🌟'
        ],
        'error' => [
            'color' => 15548997,
            'title_emoji' => '❌',
            'description_emoji' => '⚠️',
            'footer_emoji' => '🔥'
        ],
        'warning' => [
            'color' => 16776960,
            'title_emoji' => '⚠️',
            'description_emoji' => '📢',
            'footer_emoji' => '🔔'
        ],
        'info' => [
            'color' => 3447003,
            'title_emoji' => 'ℹ️',
            'description_emoji' => '📩',
            'footer_emoji' => '💡'
        ]
    ];

    $template = array_key_exists($template, $templates) ? $template : 'info';
    $color = isset($data['discord_embed_color_hex']) ? hexdec(str_replace('#', '', $data['discord_embed_color_hex'])) : (int)$data['discord_embed_color'];
    $custom_fields = [];
    if (isset($data['discord_embed_fields']['custom']) && is_array($data['discord_embed_fields']['custom'])) {
        foreach ($data['discord_embed_fields']['custom'] as $index => $field) {
            if (!empty($field['name']) && !empty($field['value'])) {
                $custom_fields[] = [
                    'name' => sanitize_text_field($field['name']),
                    'value' => sanitize_text_field($field['value']),
                    'emoji' => sanitize_text_field($field['emoji'] ?? '')
                ];
            }
        }
    }

    $embed_settings = [
        'color' => isset($templates[$template]['color']) ? $templates[$template]['color'] : $color,
        'fields' => [
            'title' => isset($data['discord_embed_fields']['title']),
            'title_emoji' => isset($templates[$template]['title_emoji']) ? $templates[$template]['title_emoji'] : sanitize_text_field($data['discord_embed_fields']['title_emoji'] ?? ''),
            'description' => isset($data['discord_embed_fields']['description']),
            'description_emoji' => isset($templates[$template]['description_emoji']) ? $templates[$template]['description_emoji'] : sanitize_text_field($data['discord_embed_fields']['description_emoji'] ?? ''),
            'timestamp' => isset($data['discord_embed_fields']['timestamp']),
            'footer' => isset($data['discord_embed_fields']['footer']),
            'footer_icon' => esc_url_raw($data['discord_embed_fields']['footer_icon'] ?? ''),
            'footer_emoji' => isset($templates[$template]['footer_emoji']) ? $templates[$template]['footer_emoji'] : sanitize_text_field($data['discord_embed_fields']['footer_emoji'] ?? ''),
            'author' => isset($data['discord_embed_fields']['author']),
            'author_icon' => esc_url_raw($data['discord_embed_fields']['author_icon'] ?? ''),
            'author_emoji' => sanitize_text_field($data['discord_embed_fields']['author_emoji'] ?? ''),
            'custom' => $custom_fields
        ]
    ];

    $embed = [];
    if ($embed_settings['fields']['title']) {
        $embed['title'] = ($embed_settings['fields']['title_emoji'] ? $embed_settings['fields']['title_emoji'] . ' ' : '') . 'Тестовое сообщение';
    }
    if ($embed_settings['fields']['description']) {
        $embed['description'] = ($embed_settings['fields']['description_emoji'] ? $embed_settings['fields']['description_emoji'] . ' ' : '') . 'Это пример содержимого сообщения для предпросмотра.';
    }
    $embed['color'] = (int)$embed_settings['color'];
    if ($embed_settings['fields']['timestamp']) {
        $embed['timestamp'] = current_time('c');
    }
    if ($embed_settings['fields']['footer']) {
        $embed['footer'] = [
            'text' => ($embed_settings['fields']['footer_emoji'] ? $embed_settings['fields']['footer_emoji'] . ' ' : '') . 'Steam Auth Notification',
            'icon_url' => $embed_settings['fields']['footer_icon']
        ];
    }
    if ($embed_settings['fields']['author']) {
        $embed['author'] = [
            'name' => ($embed_settings['fields']['author_emoji'] ? $embed_settings['fields']['author_emoji'] . ' ' : '') . get_bloginfo('name'),
            'url' => home_url(),
            'icon_url' => $embed_settings['fields']['author_icon']
        ];
    }
    if (!empty($embed_settings['fields']['custom'])) {
        $embed['fields'] = array_map(function($field) {
            return [
                'name' => ($field['emoji'] ? $field['emoji'] . ' ' : '') . $field['name'],
                'value' => $field['value'],
                'inline' => false
            ];
        }, $embed_settings['fields']['custom']);
    }

    $bot_token = get_option('steam_auth_discord_bot_token', '');
    if (empty($bot_token)) {
        wp_send_json_error('Токен бота Discord не настроен.');
    }

    $url = "https://discord.com/api/v10/users/@me/channels";
    $response = wp_remote_post($url, [
        'headers' => [
            'Authorization' => "Bot $bot_token",
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode(['recipient_id' => $discord_id]),
        'timeout' => 10
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error('Ошибка создания DM канала: ' . $response->get_error_message());
    }

    $channel_data = json_decode(wp_remote_retrieve_body($response), true);
    $channel_id = $channel_data['id'] ?? '';
    if (!$channel_id) {
        wp_send_json_error('Не удалось создать DM канал.');
    }

    $message_url = "https://discord.com/api/v10/channels/$channel_id/messages";
    $response = wp_remote_post($message_url, [
        'headers' => [
            'Authorization' => "Bot $bot_token",
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'embeds' => [$embed]
        ]),
        'timeout' => 10
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error('Ошибка отправки: ' . $response->get_error_message());
    } elseif (wp_remote_retrieve_response_code($response) !== 200) {
        wp_send_json_error('Discord API вернул ошибку: ' . wp_remote_retrieve_body($response));
    }

    wp_send_json_success('Тестовое сообщение отправлено.');
});

// Добавим обработчик для сохранения пользовательских шаблонов (из предыдущих изменений)
add_action('wp_ajax_steam_auth_save_custom_template', 'steam_auth_save_custom_template');
/**
 * Обработчик AJAX для сохранения пользовательских шаблонов Discord
 *
 * @since 1.3
 */
function steam_auth_save_custom_template() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
    }

    $template_data = json_decode(stripslashes($_POST['template']), true);
    if (!$template_data || empty($template_data['name'])) {
        wp_send_json_error('Неверные данные шаблона');
    }

    $custom_templates = get_option('steam_auth_discord_custom_templates', []);
    $key = sanitize_key($template_data['name']);
    $custom_templates[$key] = $template_data;
    update_option('steam_auth_discord_custom_templates', $custom_templates);

    wp_send_json_success(['key' => $key]);
}

add_action('wp_ajax_steam_auth_remove_custom_template', 'steam_auth_remove_custom_template');
/**
 * AJAX-обработчик удаления пользовательских шаблонов Discord
 *
 * @since 1.3
 */
function steam_auth_remove_custom_template() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();

    $key = sanitize_text_field($_POST['key']);
    $templates = get_option('steam_auth_discord_custom_templates', []);
    if (isset($templates[$key])) {
        unset($templates[$key]);
        update_option('steam_auth_discord_custom_templates', $templates);
        wp_send_json_success('Шаблон удалён');
    } else {
        wp_send_json_error('Шаблон не найден');
    }
}

add_action('wp_ajax_update_discord_notifications', 'steam_auth_update_discord_notifications');
/**
 * AJAX-обработчик обновления настроек уведомлений Discord для профиля
 *
 * @since 1.3
 */
function steam_auth_update_discord_notifications() {
    check_ajax_referer('steam_profile_nonce', 'nonce');

    $user_id = intval($_POST['user_id']);
    $enabled = intval($_POST['enabled']);

    if (!is_user_logged_in() || get_current_user_id() !== $user_id) {
        wp_send_json_error('Нет доступа');
    }

    update_user_meta($user_id, 'discord_notifications_enabled', $enabled);

    if (get_option('steam_auth_debug', false)) {
        error_log("Steam Auth: Уведомления Discord для пользователя $user_id " . ($enabled ? 'включены' : 'отключены'));
    }

    wp_send_json_success([
        'message' => "Уведомления " . ($enabled ? 'включены' : 'отключены') . "!",
    ]);
}

add_action('wp_ajax_steam_auth_bulk_delete_messages', 'steam_auth_bulk_delete_messages');
function steam_auth_bulk_delete_messages() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Недостаточно прав');

    $message_ids = isset($_POST['message_ids']) ? array_map('intval', (array)$_POST['message_ids']) : [];
    if (empty($message_ids)) wp_send_json_error('Не выбраны сообщения для удаления');

    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_auth_messages';

    // Удаляем сообщения из базы данных
    $placeholders = implode(',', array_fill(0, count($message_ids), '%d'));
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE id IN ($placeholders)",
        $message_ids
    ));

    if ($deleted === false) {
        wp_send_json_error('Ошибка при удалении сообщений: ' . $wpdb->last_error);
    } elseif ($deleted > 0) {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Удалено $deleted сообщений из таблицы $table_name");
        }
        wp_send_json_success('Сообщения удалены');
    } else {
        wp_send_json_error('Сообщения не найдены');
    }
}

add_action('wp_ajax_steam_auth_get_unread_count', 'steam_auth_get_unread_count');
function steam_auth_get_unread_count() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    $user_id = get_current_user_id();
    $count = steam_auth_get_unread_messages_count($user_id);
    wp_send_json_success($count);
}

add_action('wp_ajax_steam_auth_remove_general_field', 'steam_auth_remove_general_field');
function steam_auth_remove_general_field() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
    }

    $field_key = isset($_POST['field_key']) ? sanitize_key($_POST['field_key']) : '';
    if (empty($field_key)) {
        wp_send_json_error('Не указан ключ поля');
    }

    $profile_settings = get_option('steam_profile_settings', []);
    if (isset($profile_settings['fields'][$field_key])) {
        unset($profile_settings['fields'][$field_key]);
        update_option('steam_profile_settings', $profile_settings);
        wp_send_json_success('Общее поле удалено');
    } else {
        wp_send_json_error('Поле не найдено');
    }
}

// Добавление новой категории
add_action('wp_ajax_steam_auth_add_category', 'steam_auth_add_category');
function steam_auth_add_category() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Недостаточно прав');

    $category = sanitize_text_field($_POST['category']);
    if (empty($category)) wp_send_json_error('Название категории не может быть пустым');

    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_auth_messages';
    $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE category = %s", $category));
    if ($existing > 0) wp_send_json_error('Категория уже существует');

    // Добавляем категорию как "техническую" запись
    $wpdb->insert(
        $table_name,
        [
            'user_id' => 0,
            'role' => '',
            'title' => 'Создана категория',
            'content' => 'Категория добавлена через админку',
            'category' => $category,
            'date' => current_time('mysql'),
            'is_read' => 1,
            'is_deleted' => 1 // Помечаем как удалённое, чтобы не отображалось в списке сообщений
        ]
    );

    if ($wpdb->insert_id) {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Категория '$category' добавлена в таблицу $table_name");
        }
        wp_send_json_success('Категория добавлена');
    } else {
        wp_send_json_error('Ошибка при добавлении категории');
    }
}

// Редактирование категории
add_action('wp_ajax_steam_auth_edit_category', 'steam_auth_edit_category');
function steam_auth_edit_category() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Недостаточно прав');

    $old_category = sanitize_text_field($_POST['old_category']);
    $new_category = sanitize_text_field($_POST['new_category']);
    if (empty($new_category)) wp_send_json_error('Новое название категории не может быть пустым');

    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_auth_messages';
    $existing = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE category = %s", $new_category));
    if ($existing > 0 && $old_category !== $new_category) wp_send_json_error('Категория с таким названием уже существует');

    $updated = $wpdb->update(
        $table_name,
        ['category' => $new_category],
        ['category' => $old_category],
        ['%s'],
        ['%s']
    );

    if ($updated === false) {
        wp_send_json_error('Ошибка при обновлении категории: ' . $wpdb->last_error);
    } else {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Категория '$old_category' изменена на '$new_category'");
        }
        wp_send_json_success('Категория обновлена');
    }
}

// Удаление категории
add_action('wp_ajax_steam_auth_delete_category', 'steam_auth_delete_category');
function steam_auth_delete_category() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Недостаточно прав');

    $category = sanitize_text_field($_POST['category']);
    if (empty($category)) wp_send_json_error('Категория не указана');

    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_auth_messages';

    // Перемещаем сообщения из удаляемой категории в "general"
    $updated = $wpdb->update(
        $table_name,
        ['category' => 'general'],
        ['category' => $category],
        ['%s'],
        ['%s']
    );

    if ($updated === false) {
        wp_send_json_error('Ошибка при удалении категории: ' . $wpdb->last_error);
    } else {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Категория '$category' удалена, сообщения перемещены в 'general'");
        }
        wp_send_json_success('Категория удалена');
    }
}

?>