<?php

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
        $selected_mod_roles = isset($mods_config['selected_roles']) ? $mods_config['selected_roles'] : [];
        require __DIR__ . '/templates/mods.php';
    } elseif ($tab === 'tickets') { // Добавляем вкладку тикетов
        require __DIR__ . '/templates/tickets.php';
    }
    echo ob_get_clean();
    wp_die();
}


add_action('wp_ajax_steam_auth_send_message', 'steam_auth_send_message');
function steam_auth_send_message() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_die();

    $user_id = intval($_POST['user_id']);
    $role = sanitize_text_field($_POST['role']);
    $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : 'Без заголовка';
    $content = isset($_POST['content']) ? sanitize_textarea_field($_POST['content']) : 'Сообщение отсутствует';
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'general';
    $template = sanitize_text_field($_POST['discord_embed_template']);

    // Добавляем оригинальное сообщение
    $message_id = add_user_message($user_id, $role, $title, $content, false, $category);
    if (!$message_id) {
        wp_send_json_error("Ошибка при добавлении сообщения");
    }

    // Определяем шаблон для Discord
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

    // Определяем получателей
    $users = $user_id == 0 ? get_users($role ? ['role' => $role] : []) : [get_userdata($user_id)];
    $count = 0;

    // Отправляем уведомления в Discord
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

function steam_auth_clear_logs() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_auth_logs';

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Недостаточно прав']);
        return;
    }

    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'steam_auth_nonce')) {
        wp_send_json_error(['message' => 'Ошибка проверки nonce']);
        return;
    }

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
    }   elseif (isset($data['moderators'])) {
        $moders_config = [
            'selected_roles' => isset($data['moderator_roles']) ? array_map('sanitize_text_field', $data['moderator_roles']) : [],
            'can_manage_tickets' => isset($data['mod_can_manage_tickets']) ? true : false,
            'can_view_users' => isset($data['mod_can_view_users']) ? true : false,
        ];
        update_option('steam_auth_mods_config', $moders_config);
        wp_send_json_success(['message' => 'Настройки модераторов сохранены', 'tab' => 'moderators']);
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
        $template_settings = [/* ... */];
        send_discord_message($discord_id, 'Запрос на отвязку отклонён', "Ваш запрос на отвязку Discord ($discord_username) от Steam ($steam_id) был отклонён администратором.", $template_settings);
        log_steam_action($steam_id, 'discord_unlink_rejected', $discord_id, $discord_username);
        
        wp_send_json_success('Запрос на отвязку отклонён');
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

    $placeholders = implode(',', array_fill(0, count($message_ids), '%d'));
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE id IN ($placeholders) AND original_message_id = 0",
        $message_ids
    ));

    if ($deleted === false) {
        wp_send_json_error('Ошибка при удалении сообщений: ' . $wpdb->last_error);
    } elseif ($deleted > 0) {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Удалено $deleted оригинальных сообщений из таблицы $table_name");
        }
        wp_send_json_success('Сообщения удалены');
    } else {
        wp_send_json_error('Сообщения не найдены или не являются оригинальными');
    }
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

add_action('wp_ajax_steam_auth_save_messages_settings', 'steam_auth_save_messages_settings');
function steam_auth_save_messages_settings() {
    check_ajax_referer('steam_auth_messages_settings', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Недостаточно прав');

    update_option('steam_auth_allow_user_delete_messages', isset($_POST['steam_auth_allow_user_delete_messages']) ? true : false);

    wp_send_json_success('Настройки сохранены');
}

add_action('wp_ajax_create_ticket', 'steam_auth_create_ticket');
function steam_auth_create_ticket() {
    check_ajax_referer('steam_profile_nonce', 'nonce');
    $user_id = get_current_user_id();

    if (!$user_id) {
        wp_send_json_error("Пользователь не авторизован");
    }

    $topic_id = intval($_POST['topic_id']);
    $title = sanitize_text_field($_POST['title']);
    $content = wp_kses_post($_POST['content']);

    // Настройки ограничений для файлов
    $max_file_size = get_option('steam_auth_ticket_max_file_size', 2) * 1024 * 1024; // 2MB по умолчанию
    $allowed_file_types = explode(',', get_option('steam_auth_ticket_allowed_file_types', 'jpg,png,pdf'));

    // Проверка файла
    $attachment_url = '';
    if (!empty($_FILES['attachment']['name'])) {
        $file_size = $_FILES['attachment']['size'];
        $file_type = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));

        if ($file_size > $max_file_size) {
            wp_send_json_error("Размер файла превышает допустимый лимит (" . ($max_file_size / (1024 * 1024)) . " МБ)");
        }

        if (!in_array($file_type, $allowed_file_types)) {
            wp_send_json_error("Недопустимый тип файла. Разрешены: " . implode(', ', $allowed_file_types));
        }

        $upload_overrides = ['test_form' => false];
        $upload = wp_handle_upload($_FILES['attachment'], $upload_overrides);
        if (isset($upload['error'])) {
            wp_send_json_error("Ошибка загрузки: " . $upload['error']);
        }
        $attachment_url = $upload['url'];
    }

    global $wpdb;
    $tickets_table = $wpdb->prefix . 'steam_auth_tickets';
    $messages_table = $wpdb->prefix . 'steam_auth_ticket_messages';

    // Создаём тикет
    $wpdb->insert($tickets_table, [
        'user_id' => $user_id,
        'topic_id' => $topic_id,
        'title' => $title,
        'status' => 'open'
    ]);
    $ticket_id = $wpdb->insert_id;

    if (!$ticket_id) {
        wp_send_json_error("Ошибка создания тикета: " . $wpdb->last_error);
    }

    // Извлекаем Steam ID пользователя
    $steam_id = get_user_meta($user_id, 'steam_id', true);
    if (!$steam_id) {
        wp_send_json_error("Steam ID не найден для пользователя");
    }

    // Записываем сообщение пользователя
    $wpdb->insert($messages_table, [
        'ticket_id' => $ticket_id,
        'user_id' => $user_id,
        'steam_id' => $steam_id,
        'source' => 'user',   
        'content' => $content,
        'attachment_url' => $attachment_url,
        'created_at' => current_time('mysql') // Добавляем временную метку
    ]);

    if ($wpdb->last_error) {
        error_log("Steam Auth: Ошибка записи сообщения для тикета #$ticket_id: " . $wpdb->last_error);
        wp_send_json_error("Ошибка записи сообщения: " . $wpdb->last_error);
    }

    // Отправка уведомления в Discord и логирование
    $discord_id = get_user_meta($user_id, 'discord_id', true);
    if ($discord_id) {
        $extra_data = [
            'ticket_id' => $ticket_id,
            'title' => $title,
            'content' => $content,
            'attachment_url' => $attachment_url
        ];
        send_discord_message($discord_id, "Тикет #$ticket_id создан", "Вы создали тикет: $title");
        log_steam_action($steam_id, 'ticket_created', $discord_id, '', "Тикет #$ticket_id создан", $extra_data);
    }

    if (get_option('steam_auth_debug', false)) {
        error_log("Steam Auth: Пользователь $user_id создал тикет #$ticket_id");
    }

    wp_send_json_success("Тикет успешно создан");
}

add_action('wp_ajax_view_ticket', 'steam_auth_view_ticket');
function steam_auth_view_ticket() {
    check_ajax_referer('steam_profile_nonce', 'nonce');
    $user_id = get_current_user_id();
    $ticket_id = intval($_POST['ticket_id']);

    global $wpdb;
    $tickets_table = $wpdb->prefix . 'steam_auth_tickets';
    $messages_table = $wpdb->prefix . 'steam_auth_ticket_messages';
    $topics_table = $wpdb->prefix . 'steam_auth_ticket_topics';

    $ticket = $wpdb->get_row($wpdb->prepare(
        "SELECT t.*, tt.name as topic_name 
         FROM $tickets_table t 
         LEFT JOIN $topics_table tt ON t.topic_id = tt.id 
         WHERE t.id = %d AND t.user_id = %d",
        $ticket_id,
        $user_id
    ), ARRAY_A);

    if (!$ticket) {
        wp_die('Тикет не найден');
    }

    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT m.*, u.user_login 
         FROM $messages_table m 
         LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID 
         WHERE m.ticket_id = %d 
         ORDER BY m.created_at ASC",
        $ticket_id
    ), ARRAY_A);

    ob_start();
    ?>
    <h3>Тикет #<?php echo $ticket['id']; ?> - <?php echo esc_html($ticket['title']); ?></h3>
    <p><strong>Тема:</strong> <?php echo esc_html($ticket['topic_name']); ?></p>
    <p><strong>Статус:</strong> <?php echo esc_html($ticket['status'] === 'open' ? 'Открыт' : ($ticket['status'] === 'in_progress' ? 'В обработке' : 'Закрыт')); ?></p>
    <div class="ticket-messages">
        <?php foreach ($messages as $message): ?>
            <div class="ticket-message <?php echo $message['user_id'] == $user_id ? 'user' : 'admin'; ?>">
                <p><strong><?php echo esc_html($message['user_login']); ?>:</strong> <?php echo wp_kses_post($message['content']); ?></p>
                <?php if ($message['attachment_url']): ?>
                    <p><a href="<?php echo esc_url($message['attachment_url']); ?>" target="_blank">Вложение</a></p>
                <?php endif; ?>
                <p><small><?php echo esc_html($message['created_at']); ?></small></p>
            </div>
        <?php endforeach; ?>
    </div>
    <?php if ($ticket['status'] !== 'closed'): ?>
        <form id="ticket-reply-form" enctype="multipart/form-data">
            <p>
                <label for="reply-content">Ответ:</label>
                <?php
                wp_editor('', 'reply-content', [
                    'textarea_name' => 'content',
                    'media_buttons' => true,
                    'teeny' => true,
                    'quicktags' => true,
                    'textarea_rows' => 5
                ]);
                ?>
            </p>
            <p>
                <label for="reply-attachment">Прикрепить файл:</label>
                <input type="file" name="attachment" id="reply-attachment">
            </p>
            <p>
                <input type="submit" value="Отправить" class="button button-primary">
            </p>
        </form>
    <?php endif; ?>
    <?php
    echo ob_get_clean();
    wp_die();
}

add_action('wp_ajax_reply_ticket', 'steam_auth_reply_ticket');
function steam_auth_reply_ticket() {
    check_ajax_referer('steam_profile_nonce', 'nonce');
    $user_id = get_current_user_id();
    $ticket_id = intval($_POST['ticket_id']);
    $content = wp_kses_post($_POST['content']);

    global $wpdb;
    $tickets_table = $wpdb->prefix . 'steam_auth_tickets';
    $messages_table = $wpdb->prefix . 'steam_auth_ticket_messages';

    $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tickets_table WHERE id = %d AND user_id = %d", $ticket_id, $user_id));
    if (!$ticket || $ticket->status === 'closed') {
        wp_send_json_error('Тикет не найден или закрыт');
    }

    // Настройки ограничений для файлов
    $max_file_size = get_option('steam_auth_ticket_max_file_size', 2) * 1024 * 1024; // Например, 2MB по умолчанию
    $allowed_file_types = explode(',', get_option('steam_auth_ticket_allowed_file_types', 'jpg,png,pdf'));

    // Проверка файла
    if (!empty($_FILES['attachment']['name'])) {
        $file_size = $_FILES['attachment']['size'];
        $file_type = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));

        // Проверка размера
        if ($file_size > $max_file_size) {
            wp_send_json_error("Размер файла превышает допустимый лимит (" . ($max_file_size / (1024 * 1024)) . " МБ)");
        }

        // Проверка типа файла
        if (!in_array($file_type, $allowed_file_types)) {
            wp_send_json_error("Недопустимый тип файла. Разрешены: " . implode(', ', $allowed_file_types));
        }

        $upload_overrides = ['test_form' => false];
        $upload = wp_handle_upload($_FILES['attachment'], $upload_overrides);
        if (isset($upload['error'])) {
            wp_send_json_error("Ошибка загрузки: " . $upload['error']);
        }
        $attachment_url = $upload['url'];
    } else {
        $attachment_url = '';
    }

    $steam_id = get_user_meta($user_id, 'steam_id', true);
    if (!$steam_id) {
        wp_send_json_error("Steam ID не найден для пользователя");
    }

    $wpdb->insert($messages_table, [
        'ticket_id' => $ticket_id,
        'user_id' => $user_id,
        'steam_id' => $steam_id, // Теперь Steam ID определён
        'source' => 'user',      // Явно указываем источник
        'content' => $content,
        'attachment_url' => $attachment_url
    ]);

    $wpdb->update($tickets_table, ['status' => 'open'], ['id' => $ticket_id]);

    $discord_id = get_user_meta($user_id, 'discord_id', true);
    if ($discord_id) {
        send_discord_message($discord_id, "Ответ в тикете #$ticket_id", "Вы ответили: $content");
        //log_steam_action($discord_id, 'ticket_replied', '', '', "Тикет #$ticket_id: $content");
    }

    if (get_option('steam_auth_debug', false)) {
        error_log("Steam Auth: Пользователь $user_id ответил в тикете #$ticket_id");
    }

    wp_send_json_success("Ответ успешно отправлен");
}

add_action('wp_ajax_save_ticket_topics', 'steam_auth_save_ticket_topics');
function steam_auth_save_ticket_topics() {
    check_ajax_referer('steam_auth_ticket_settings', 'steam_auth_ticket_nonce'); // Исправляем action и имя
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
    }

    global $wpdb;
    $topics_table = $wpdb->prefix . 'steam_auth_ticket_topics';
    $topics = $_POST['topics'] ?? [];

    foreach ($topics as $id => $data) {
        if (strpos($id, 'new_') === 0) {
            $wpdb->insert($topics_table, [
                'name' => sanitize_text_field($data['name']),
                'description' => sanitize_text_field($data['description']),
                'is_active' => isset($data['is_active']) ? 1 : 0
            ]);
        } else {
            $wpdb->update($topics_table, [
                'name' => sanitize_text_field($data['name']),
                'description' => sanitize_text_field($data['description']),
                'is_active' => isset($data['is_active']) ? 1 : 0
            ], ['id' => intval($id)]);
        }
    }

    if (get_option('steam_auth_debug', false)) {
        error_log("Steam Auth: Темы тикетов сохранены: " . json_encode($topics));
    }

    wp_send_json_success('Темы тикетов сохранены');
}

add_action('wp_ajax_save_ticket_settings', 'steam_auth_save_ticket_settings');
function steam_auth_save_ticket_settings() {
    check_ajax_referer('steam_auth_ticket_settings', 'steam_auth_ticket_nonce'); // Исправляем action и имя
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
    }

    update_option('steam_auth_tickets_enabled', isset($_POST['steam_auth_tickets_enabled']) ? true : false);
    update_option('steam_auth_ticket_max_file_size', intval($_POST['steam_auth_ticket_max_file_size']));
    update_option('steam_auth_ticket_allowed_file_types', sanitize_text_field($_POST['steam_auth_ticket_allowed_file_types']));
    update_option('steam_auth_ticket_auto_delete_days', intval($_POST['steam_auth_ticket_auto_delete_days']));

    if (get_option('steam_auth_debug', false)) {
        error_log("Steam Auth: Настройки тикетов сохранены: " . json_encode($_POST));
    }

    wp_send_json_success('Настройки тикетов сохранены');
}

add_action('wp_ajax_update_ticket_status', 'steam_auth_update_ticket_status');
function steam_auth_update_ticket_status() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
    }

    global $wpdb;
    $ticket_id = intval($_POST['ticket_id']);
    $status = in_array($_POST['status'], ['open', 'in_progress', 'closed']) ? $_POST['status'] : 'open';

    $updated = $wpdb->update(
        $wpdb->prefix . 'steam_auth_tickets',
        ['status' => $status, 'updated_at' => current_time('mysql')], // Обновляем дату
        ['id' => $ticket_id]
    );

    if ($updated === false) {
        wp_send_json_error('Ошибка обновления статуса');
    }

    $ticket = $wpdb->get_row($wpdb->prepare("SELECT user_id FROM {$wpdb->prefix}steam_auth_tickets WHERE id = %d", $ticket_id));
    $discord_id = get_user_meta($ticket->user_id, 'discord_id', true);
    $steam_id = get_user_meta($ticket->user_id, 'steam_id', true);
    $days = get_option('steam_auth_ticket_auto_delete_days', 0);

    if ($discord_id) {
        $status_text = $status === 'open' ? 'Открыт' : ($status === 'in_progress' ? 'В обработке' : 'Закрыт');
        $message = "Новый статус: $status_text";
        if ($status === 'closed' && $days > 0) {
            $message .= "\nТикет будет автоматически удалён через $days дней.";
        }
        send_discord_message($discord_id, "Статус тикета #$ticket_id обновлён", $message);
    }

    if (get_option('steam_auth_debug', false)) {
        error_log("Steam Auth: Статус тикета #$ticket_id обновлён на '$status'");
    }

    wp_send_json_success();
}

add_action('wp_ajax_admin_view_ticket', 'steam_auth_admin_view_ticket');
function steam_auth_admin_view_ticket() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_die('Недостаточно прав');
    }

    $ticket_id = intval($_POST['ticket_id']);
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'steam_auth_tickets';
    $messages_table = $wpdb->prefix . 'steam_auth_ticket_messages';
    $topics_table = $wpdb->prefix . 'steam_auth_ticket_topics';

    $ticket = $wpdb->get_row($wpdb->prepare(
        "SELECT t.*, tt.name as topic_name, u.user_login 
         FROM $tickets_table t 
         LEFT JOIN $topics_table tt ON t.topic_id = tt.id 
         LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID 
         WHERE t.id = %d",
        $ticket_id
    ), ARRAY_A);

    if (!$ticket) {
        wp_die('Тикет не найден');
    }

    $messages = $wpdb->get_results($wpdb->prepare(
        "SELECT m.*, u.user_login 
         FROM $messages_table m 
         LEFT JOIN {$wpdb->users} u ON m.user_id = u.ID 
         WHERE m.ticket_id = %d 
         ORDER BY m.created_at ASC",
        $ticket_id
    ), ARRAY_A);

    wp_enqueue_editor();
    wp_enqueue_media();

    if (get_option('steam_auth_debug', false)) {
        error_log("Steam Auth: wp_enqueue_editor и wp_enqueue_media вызваны для тикета #$ticket_id");
    }

    $note = get_metadata('steam_auth_ticket', $ticket_id, 'admin_ticket_note', true);

    ob_start();
    ?>
    <div id="ticket-modal-content" class="theme-<?php echo esc_attr($ticket['status']); ?>">
        <div class="ticket-modal-header">
            <h3 class="admin-ticket-h3">Тикет #<?php echo $ticket['id']; ?> - <?php echo esc_html($ticket['title']); ?></h3>
            <div class="ticket-meta">
                <span><i class="fas fa-user"></i><strong>Пользователь:</strong> <?php echo esc_html($ticket['user_login']); ?></span>
                <span><i class="fas fa-comment"></i><strong>Тема:</strong> <?php echo esc_html($ticket['topic_name']); ?></span>
                <span class="ticket-status <?php echo esc_attr($ticket['status']); ?>">
                    <strong>Статус:</strong> 
                    <?php echo esc_html($ticket['status'] === 'open' ? 'Открыт' : ($ticket['status'] === 'in_progress' ? 'В обработке' : 'Закрыт')); ?>
                </span>
            </div>
            <div class="ticket-progress <?php echo esc_attr($ticket['status']); ?>">
                <div class="ticket-progress-bar"></div>
            </div>
            <?php if ($ticket['status'] === 'closed' && $auto_delete_days = get_option('ticket_auto_delete_days', 0)): ?>
                <?php
                $updated_at = strtotime($ticket['updated_at'] ?: date('Y-m-d H:i:s'));
                $delete_timestamp = $updated_at + ($auto_delete_days * 86400);
                ?>
                <div class="ticket-timer" data-delete-timestamp="<?php echo $delete_timestamp; ?>"></div>
            <?php endif; ?>
            <div class="ticket-actions">
                <?php if ($ticket['status'] !== 'closed'): ?>
                    <button class="action-btn" data-action="in_progress" data-tooltip="Взять в работу">В обработку</button>
                    <button class="action-btn" data-action="closed" data-tooltip="Завершить тикет">Закрыть</button>
                <?php endif; ?>
                <!-- <button class="action-btn copy-ticket-link" data-ticket-id="<?php echo $ticket['id']; ?>" data-tooltip="Скопировать ссылку">Копировать</button> -->
            </div>
            <span class="ticket-modal-close">×</span>
        </div>

        <div class="ticket-messages-container">
            <?php foreach ($messages as $message): ?>
                <div class="ticket-message <?php echo $message['user_id'] == $ticket['user_id'] ? 'user-message' : 'admin-message'; ?>">
                    <div class="message-header">
                        <div>
                            <?php
                            $steam_avatar = get_user_meta($message['user_id'], 'steam_avatar', true);
                            $avatar_url = $steam_avatar ?: get_avatar_url($message['user_id'], ['size' => 24]);
                            ?>
                            <img src="<?php echo esc_url($avatar_url); ?>" alt="Avatar" class="message-avatar">
                            <span class="message-author"><?php echo esc_html($message['user_login']); ?></span>
                        </div>
                        <span class="message-time"><?php echo esc_html($message['created_at']); ?></span>
                    </div>
                    <div class="message-content">
                        <?php echo wp_kses_post($message['content']); ?>
                        <?php if ($message['attachment_url']): ?>
                            <p class="message-attachment">
                                <a href="<?php echo esc_url($message['attachment_url']); ?>" target="_blank"><i class="fas fa-paperclip"></i> Вложение</a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($note): ?>
            <div class="ticket-note">
                <h4>Заметка админа:</h4>
                <p><?php echo esc_html($note); ?></p>
            </div>
        <?php endif; ?>

        <?php if ($ticket['status'] !== 'closed'): ?>
            <div class="ticket-reply-form">
                <h4>Написать ответ</h4>
                <form id="admin-ticket-reply-form" enctype="multipart/form-data">
                    <div class="form-field">
                        <label for="quick-reply">Быстрый ответ:</label>
                        <select id="quick-reply" onchange="if(this.value) tinymce.get('reply-content').setContent(this.value);">
                            <option value="">Выберите шаблон</option>
                            <option value="Спасибо за обращение! Мы рассмотрим ваш запрос в ближайшее время.">Быстрый ответ 1</option>
                            <option value="Пожалуйста, уточните детали проблемы.">Уточнение</option>
                            <option value="Ваш запрос передан разработчикам.">Передано</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <label for="reply-content">Сообщение:</label>
                        <?php
                        wp_editor('', 'reply-content', [
                            'textarea_name' => 'content',
                            'media_buttons' => true,
                            'teeny' => false,
                            'quicktags' => true,
                            'textarea_rows' => 5,
                            'editor_css' => '<style>#wp-reply-content-wrap { width: 100%; border-radius: 4px; background: #343a40; }</style>'
                        ]);
                        ?>
                    </div>
                    <div class="form-field">
                        <label for="ticket-note">Заметка (только для админов):</label>
                        <textarea id="ticket-note" name="note" rows="3" class="wp-editor-area"></textarea>
                    </div>
                    <div class="form-field">
                        <label for="reply-attachment">Прикрепить файл:</label>
                        <input type="file" name="attachment" id="reply-attachment" class="file-input">
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="button button-primary">Отправить</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <?php if (get_option('steam_auth_debug', false)): ?>
            <script>console.log('TinyMCE доступен:', typeof tinymce !== 'undefined');</script>
        <?php endif; ?>
    </div>
    <?php
    echo ob_get_clean();
    wp_die();
}

add_action('wp_ajax_admin_reply_ticket', 'steam_auth_admin_reply_ticket');
function steam_auth_admin_reply_ticket() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
    }

    $ticket_id = intval($_POST['ticket_id']);
    $content = wp_kses_post($_POST['content']);
    $note = sanitize_textarea_field($_POST['note'] ?? '');
    $admin_id = get_current_user_id();

    global $wpdb;
    $tickets_table = $wpdb->prefix . 'steam_auth_tickets';
    $messages_table = $wpdb->prefix . 'steam_auth_ticket_messages';

    $ticket = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tickets_table WHERE id = %d", $ticket_id));
    if (!$ticket || $ticket->status === 'closed') {
        wp_send_json_error('Тикет не найден или закрыт');
    }

    $attachment_url = '';
    if (!empty($_FILES['attachment']['name'])) {
        $upload = wp_handle_upload($_FILES['attachment'], ['test_form' => false]);
        if (isset($upload['url'])) {
            $attachment_url = $upload['url'];
        } elseif (isset($upload['error'])) {
            wp_send_json_error($upload['error']);
        }
    }

    $wpdb->insert($messages_table, [
        'ticket_id' => $ticket_id,
        'user_id' => $admin_id,
        'steam_id' => '',
        'source' => 'admin_site',
        'content' => $content,
        'attachment_url' => $attachment_url
    ]);

    if ($note) {
        update_metadata('steam_auth_ticket', $ticket_id, 'admin_ticket_note', $note); // Используем кастомную таблицу
    }

    $wpdb->update($tickets_table, ['status' => 'in_progress'], ['id' => $ticket_id]);

    $discord_id = get_user_meta($ticket->user_id, 'discord_id', true);
    if ($discord_id) {
        send_discord_message($discord_id, "Ответ в тикете #$ticket_id", "Администратор ответил: $content");
    }

    if (get_option('steam_auth_debug', false)) {
        error_log("Steam Auth: Администратор $admin_id ответил в тикете #$ticket_id");
    }

    wp_send_json_success();
}

add_action('wp_ajax_admin_delete_ticket', 'steam_auth_admin_delete_ticket');
function steam_auth_admin_delete_ticket() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'steam_auth_nonce')) {
        wp_send_json_error('Неверный nonce');
    }

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Недостаточно прав');
    }

    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    if (!$ticket_id) {
        wp_send_json_error('Неверный ID тикета');
    }

    global $wpdb;
    $tickets_table = $wpdb->prefix . 'steam_auth_tickets';
    $messages_table = $wpdb->prefix . 'steam_auth_ticket_messages';

    // Проверяем существование тикета
    $ticket_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $tickets_table WHERE id = %d",
        $ticket_id
    ));

    if (!$ticket_exists) {
        wp_send_json_error('Тикет не найден');
    }

    // Удаляем сообщения тикета
    $wpdb->delete($messages_table, ['ticket_id' => $ticket_id], ['%d']);

    // Удаляем сам тикет
    $deleted = $wpdb->delete($tickets_table, ['id' => $ticket_id], ['%d']);
    if ($deleted === false) {
        wp_send_json_error('Ошибка при удалении тикета: ' . $wpdb->last_error);
    }

    // Проверяем количество оставшихся тикетов и сбрасываем AUTO_INCREMENT, если таблица пуста
    $remaining_tickets = $wpdb->get_var("SELECT COUNT(*) FROM $tickets_table");
    if ($remaining_tickets == 0) {
        $reset_result = $wpdb->query("ALTER TABLE $tickets_table AUTO_INCREMENT = 1");
        if ($reset_result === false) {
            if (get_option('steam_auth_debug', false)) {
                error_log("Steam Auth: Ошибка сброса AUTO_INCREMENT для таблицы $tickets_table: " . $wpdb->last_error);
            }
        } else {
            if (get_option('steam_auth_debug', false)) {
                error_log("Steam Auth: AUTO_INCREMENT успешно сброшен до 1 для таблицы $tickets_table");
            }
        }
    } else {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Таблица $tickets_table не пуста ($remaining_tickets тикетов), AUTO_INCREMENT не сброшен");
        }
    }

    wp_send_json_success('Тикет успешно удалён');
}


// AJAX-обработчики для вкладок
add_action('wp_ajax_load_tab', 'steam_auth_load_tab');
function steam_auth_load_tab() {
    check_ajax_referer('steam_profile_nonce', 'nonce');

    // Получаем данные пользователя
    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_die('Ошибка: пользователь не авторизован');
    }

    $user = get_userdata($user_id);
    $steam_id = get_user_meta($user_id, 'steam_id', true);
    $steam_profile = get_user_meta($user_id, 'steam_profile', true);
    $steam_avatar = get_user_meta($user_id, 'steam_avatar', true);
    $user_email = $user->user_email;
    $display_name = $user->display_name;
    $discord_id = get_user_meta($user_id, 'discord_id', true);
    $discord_username = get_user_meta($user_id, 'discord_username', true);
    $discord_unlink_requests = get_option('steam_auth_discord_unlink_requests', []);
    
    // Настройки профиля
    $profile_settings = get_option('steam_profile_settings');
    if (empty($profile_settings)) {
        $profile_settings = [
            'fields' => [
                'display_name' => ['visible' => true, 'editable' => false, 'label' => 'Имя', 'icon' => 'fa-user'],
                'steam_id' => ['visible' => true, 'editable' => false, 'label' => 'SteamID', 'icon' => 'fa-steam'],
                'user_email' => ['visible' => true, 'editable' => true, 'label' => 'Email', 'icon' => 'fa-envelope'],
                'steam_profile' => ['visible' => true, 'editable' => false, 'label' => 'Steam Profile', 'icon' => 'fa-link'],
            ],
            'custom_fields' => []
        ];
    }

    $unread_count = steam_auth_get_unread_messages_count($user_id);

    // Функция для префикса иконок (копируем из шорткода)
    function get_icon_prefix($icon_name) {
        static $icons = null;
        if ($icons === null) {
            $icons_json = @file_get_contents(plugin_dir_path(__FILE__) . 'icons.json');
            $icons = $icons_json ? json_decode($icons_json, true) : [];
        }
        $icon_key = str_replace('fa-', '', $icon_name);
        if (isset($icons[$icon_key])) {
            $style = $icons[$icon_key]['styles'][0];
            return $style === 'brands' ? 'fab' : 'fas';
        }
        return 'fas';
    }

    // Определяем вкладку и режим редактирования
    $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'profile';
    $edit = isset($_POST['edit']) && $_POST['edit'] === 'true';
    $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
    $_GET['edit'] = $edit ? 'true' : null;
    $_GET['page'] = $page;
    $_GET['category'] = $category;

    // Проверяем существование файла вкладки
    $tab_file = plugin_dir_path(__FILE__) . "tabs/{$tab}-tab.php";
    if (!file_exists($tab_file)) {
        wp_die("Ошибка: файл вкладки {$tab} не найден");
    }

    // Загружаем содержимое вкладки
    ob_start();
    include $tab_file;
    $content = ob_get_clean();

    echo $content;
    wp_die();
}

add_action('wp_ajax_save_profile', 'steam_auth_save_profile');
function steam_auth_save_profile() {
    check_ajax_referer('steam_profile_nonce', 'nonce');

    $user_id = get_current_user_id();
    $profile_settings = get_option('steam_profile_settings');
    $updated_data = [];
    $errors = [];

    if (!isset($_POST['steam_edit_profile_action']) || !wp_verify_nonce($_POST['steam_edit_profile_action'], 'steam_edit_profile_nonce')) {
        wp_send_json_error('Ошибка безопасности');
    }

    foreach ($profile_settings['fields'] as $field => $settings) {
        if ($settings['editable'] && isset($_POST[$field])) {
            $value = sanitize_text_field($_POST[$field]);
            if ($field === 'user_email' && !is_email($value)) {
                $errors[] = 'Некорректный email';
            } else {
                $updated_data[$field] = $value;
            }
        }
    }

    foreach ($profile_settings['custom_fields'] as $field => $settings) {
        if ($settings['editable'] && isset($_POST[$field])) {
            $value = $settings['type'] === 'textarea' ? sanitize_textarea_field($_POST[$field]) : sanitize_text_field($_POST[$field]);
            update_user_meta($user_id, $field, $value);
        }
    }

    if (empty($errors)) {
        $updated = wp_update_user(array_merge(['ID' => $user_id], $updated_data));
        if (is_wp_error($updated)) {
            wp_send_json_error($updated->get_error_message());
        } else {
            wp_send_json_success();
        }
    } else {
        wp_send_json_error(implode('<br>', $errors));
    }
}

add_action('wp_ajax_mark_read', 'steam_auth_mark_read');
function steam_auth_mark_read() {
    check_ajax_referer('steam_profile_nonce', 'nonce');
    $user_id = get_current_user_id();
    $message_id = sanitize_text_field($_POST['message_id']);
    mark_message_read($user_id, $message_id);
    wp_send_json_success();
}

add_action('wp_ajax_delete_message', 'steam_auth_delete_message');
function steam_auth_delete_message() {
    check_ajax_referer('steam_profile_nonce', 'nonce');
    $user_id = get_current_user_id();
    $message_id = sanitize_text_field($_POST['message_id']);
    
    if (!get_option('steam_auth_allow_user_delete_messages', 0)) {
        wp_send_json_error(['message' => 'Удаление сообщений запрещено']);
    }

    delete_user_message($user_id, $message_id);
}

add_action('wp_ajax_delete_all_read', 'steam_auth_delete_all_read');
function steam_auth_delete_all_read() {
    check_ajax_referer('steam_profile_nonce', 'nonce');
    $user_id = get_current_user_id();
    
    if (!get_option('steam_auth_allow_user_delete_messages', 0)) {
        wp_send_json_error(['message' => 'Удаление сообщений запрещено']);
    }

    delete_all_read_messages($user_id);
}

add_action('wp_ajax_delete_all', 'steam_auth_delete_all');
function steam_auth_delete_all() {
    check_ajax_referer('steam_profile_nonce', 'nonce');
    $user_id = get_current_user_id();
    
    if (!get_option('steam_auth_allow_user_delete_messages', 0)) {
        wp_send_json_error(['message' => 'Удаление сообщений запрещено']);
    }

    delete_all_messages($user_id);
}

add_action('wp_ajax_update_discord_notifications_profile', 'steam_auth_update_discord_notifications_profile');
function steam_auth_update_discord_notifications_profile() {
    check_ajax_referer('steam_profile_nonce', 'nonce');
    $user_id = get_current_user_id();
    $enabled = isset($_POST['enabled']) && $_POST['enabled'] == '1' ? '1' : '0';

    update_user_meta($user_id, 'discord_notifications_enabled', $enabled);

    $message = $enabled == '1' ? 'Уведомления Discord включены' : 'Уведомления Discord отключены';
    wp_send_json_success(['message' => $message]);
}

add_action('wp_ajax_steam_auth_get_unread_tickets_count', 'steam_auth_get_unread_tickets_count');
function steam_auth_get_unread_tickets_count($user_id) {
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'steam_auth_tickets';
    $messages_table = $wpdb->prefix . 'steam_auth_ticket_messages';

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT t.id) 
         FROM $tickets_table t
         LEFT JOIN $messages_table m ON t.id = m.ticket_id AND m.user_id != %d
         WHERE t.user_id = %d 
         AND t.status != 'closed'
         AND (m.id IS NOT NULL AND m.created_at > COALESCE(
             (SELECT MAX(m2.created_at) 
              FROM $messages_table m2 
              WHERE m2.ticket_id = t.id AND m2.user_id = %d), 
             '0000-00-00 00:00:00'
         ))",
        $user_id,
        $user_id,
        $user_id
    ));

    return (int)$count;
}

// Для профиля пользователя
function steam_auth_get_unread_tickets_count_user($user_id) {
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'steam_auth_tickets';
    $messages_table = $wpdb->prefix . 'steam_auth_ticket_messages';

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT t.id)
         FROM $tickets_table t
         INNER JOIN $messages_table m ON t.id = m.ticket_id
         WHERE t.user_id = %d
         AND m.user_id != %d
         AND m.is_read = 0
         AND t.status != 'closed'",
        $user_id,
        $user_id
    ));

    return (int)$count;
}


add_action('wp_ajax_get_unread_tickets_count_user', 'steam_auth_get_unread_tickets_count_user_ajax');

function steam_auth_get_unread_tickets_count_user_ajax() {
    check_ajax_referer('steam_profile_nonce', 'nonce');
    $user_id = get_current_user_id();
    $count = steam_auth_get_unread_tickets_count_user($user_id);
    wp_send_json_success($count);
}

add_action('wp_ajax_steam_auth_get_unread_count', 'steam_auth_get_unread_count');
function steam_auth_get_unread_count() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    $user_id = get_current_user_id();
    $count = steam_auth_get_unread_messages_count($user_id);
    wp_send_json_success($count);
}

add_action('wp_ajax_steam_auth_load_dashboard_tab', 'steam_auth_admin_load_dashboard_tab');
function steam_auth_admin_load_dashboard_tab() {
    check_ajax_referer('steam_auth_nonce', 'nonce');
    $user_id = get_current_user_id();
    $user = wp_get_current_user();
    $mods_config = get_option('steam_auth_mods_config', []);

    $is_admin = user_can($user_id, 'manage_options');
    $is_moderator = steam_auth_has_moderator_role($user);

    if (!$is_admin && !$is_moderator) {
        wp_die('Недостаточно прав');
    }

    $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : 'tickets';
    ob_start();

    if ($tab === 'tickets' && ($is_admin || ($is_moderator && $mods_config['can_manage_tickets']))) {
        steam_auth_admin_tickets_tab();
    } elseif ($tab === 'users' && ($is_admin || ($is_moderator && $mods_config['can_view_users']))) {
        steam_auth_admin_users_tab();
    } elseif ($tab === 'settings' && $is_admin) {
        steam_auth_admin_settings_tab();
    } else {
        echo '<p>Вкладка не найдена или у вас нет доступа.</p>';
    }

    echo ob_get_clean();
    wp_die();
}

function steam_auth_admin_tickets_tab() {
    global $wpdb;
    $tickets_table = $wpdb->prefix . 'steam_auth_tickets';
    $tickets = $wpdb->get_results("SELECT t.*, u.user_login FROM $tickets_table t LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID ORDER BY t.updated_at DESC");
    ?>
    <h2>Управление тикетами</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Заголовок</th>
                <th>Пользователь</th>
                <th>Статус</th>
                <th>Дата обновления</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?php echo $ticket->id; ?></td>
                    <td><?php echo esc_html($ticket->title); ?></td>
                    <td><?php echo esc_html($ticket->user_login); ?></td>
                    <td>
                        <select class="ticket-status" data-ticket-id="<?php echo $ticket->id; ?>">
                            <option value="open" <?php selected($ticket->status, 'open'); ?>>Открыт</option>
                            <option value="in_progress" <?php selected($ticket->status, 'in_progress'); ?>>В обработке</option>
                            <option value="closed" <?php selected($ticket->status, 'closed'); ?>>Закрыт</option>
                        </select>
                    </td>
                    <td><?php echo esc_html($ticket->updated_at); ?></td>
                    <td>
                        <button class="button view-ticket" data-ticket-id="<?php echo $ticket->id; ?>">Просмотреть</button>
                        <button class="button delete-ticket" data-ticket-id="<?php echo $ticket->id; ?>">Удалить</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function steam_auth_admin_users_tab() {
    $users = get_users(['number' => 20]);
    ?>
    <h2>Пользователи</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Имя</th>
                <th>Email</th>
                <th>Steam ID</th>
                <th>Discord ID</th>
                <th>Роли</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <?php
                $steam_id = get_user_meta($user->ID, 'steam_id', true);
                $discord_id = get_user_meta($user->ID, 'discord_id', true);
                ?>
                <tr>
                    <td><?php echo $user->ID; ?></td>
                    <td><?php echo esc_html($user->display_name); ?></td>
                    <td><?php echo esc_html($user->user_email); ?></td>
                    <td><?php echo esc_html($steam_id ?: 'Не указан'); ?></td>
                    <td><?php echo esc_html($discord_id ?: 'Не привязан'); ?></td>
                    <td><?php echo implode(', ', $user->roles); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function steam_auth_admin_settings_tab() {
    ?>
    <h2>Настройки модераторов</h2>
    <p>Эти настройки доступны только администраторам. И туту скоро что то будет</p>
    <!-- Здесь можно добавить форму для быстрого редактирования -->
    <?php
}

?>