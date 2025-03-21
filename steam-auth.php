<?php
/*
Plugin Name: Steam Auth
Description: Регистрация и авторизация через Steam с анимацией, управляемым профилем и логами
Version: 2.10.2
*/

if (!file_exists(__DIR__ . '/lightopenid.php')) {
    error_log("Steam Auth: Файл lightopenid.php отсутствует");
    wp_die("Ошибка конфигурации плагина: отсутствует LightOpenID");
}

require_once __DIR__ . '/lightopenid.php';
require_once __DIR__ . '/ajax.php';

// Подключение стилей
add_action('wp_enqueue_scripts', 'steam_auth_enqueue_styles');
function steam_auth_enqueue_styles() {
    wp_enqueue_style('steam-auth-style', plugin_dir_url(__FILE__) . 'css/user_main.css', [], filemtime(plugin_dir_path(__FILE__) . 'css/user_main.css'));
}

// Подключаем стили и скрипты админки
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'toplevel_page_steam-auth') {
        wp_enqueue_style('steam-auth-admin', plugin_dir_url(__FILE__) . 'css/admin.css', [], filemtime(plugin_dir_path(__FILE__) . 'css/admin.css'));
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
        wp_enqueue_style('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css');
        wp_enqueue_script('jquery');
        wp_enqueue_script('select2', 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js', ['jquery'], null, true);
        wp_enqueue_script('steam-auth-admin', plugin_dir_url(__FILE__) . 'js/admin.js', ['jquery', 'select2'], filemtime(plugin_dir_path(__FILE__) . 'js/admin.js'), true);
        wp_localize_script('steam-auth-admin', 'steamAuthAjax', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('steam_auth_nonce'),
            'debug' => get_option('steam_auth_debug', false) ? true : false,
            'home_url' => home_url(),
            'customTemplates' => get_option('steam_auth_discord_custom_templates', [])
        ]);
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Скрипты и стили подключены для $hook");
        }
    }
});

// Подключаем только стили для фронтенда
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css');
});

add_action('wp_enqueue_scripts', 'steam_auth_enqueue_scripts');
function steam_auth_enqueue_scripts() {
    wp_enqueue_script('steam-auth-frontend', plugin_dir_url(__FILE__) . 'js/frontend.js', [], '2.10.2', true);
    wp_localize_script('steam-auth-frontend', 'steamAuth', [
        'loginUrl' => wp_nonce_url(home_url('/steam-login'), 'steam_login_nonce'),
        'debug' => get_option('steam_auth_debug', false) ? true : false
    ]);
    // Локализация steamAuthAjax для AJAX-запросов
    wp_localize_script('steam-auth-frontend', 'steamAuthAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('steam_auth_nonce')
    ]);
}

// Отключаем стандартную регистрацию
add_action('login_form_register', 'disable_default_registration');
function disable_default_registration() {
    wp_redirect(home_url());
    exit;
}

// Убираем ссылку на регистрацию
add_filter('register', 'remove_register_link');
function remove_register_link($url) {
    return '#';
}

add_shortcode('steam_login_button', 'steam_login_shortcode');
function steam_login_shortcode($atts) {
    $atts = shortcode_atts(['class' => 'steam-login-btn'], $atts);
    $steam_login_url = wp_nonce_url(home_url('/steam-login'), 'steam_login_nonce');
    return '<a href="' . esc_url($steam_login_url) . '" class="' . esc_attr($atts['class']) . '" style="background: #171a21; color: white; padding: 10px; display: inline-block; margin: 10px 0;">
                <img src="https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_large_noborder.png" width="30" style="vertical-align: middle;">
                Войти через Steam
            </a>';
}

// Обработка Steam-аутентификации
add_action('init', 'handle_steam_auth');
function handle_steam_auth() {
    $request_uri = $_SERVER['REQUEST_URI'];
    if (strpos($request_uri, '/steam-login') === 0 && !is_admin()) {
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'steam_login_nonce')) {
            wp_redirect(home_url() . '?steam_error=' . urlencode('Ошибка безопасности'));
            exit;
        }

        try {
            $openid = new LightOpenID(home_url());
            $openid->identity = 'https://steamcommunity.com/openid';

            if (!$openid->mode) {
                wp_redirect($openid->authUrl());
                exit;
            } elseif ($openid->validate()) {
                $steam_id = basename($openid->identity);
                $steam_user = get_steam_user_data($steam_id);
                process_steam_user($steam_user);
            }
        } catch (Exception $e) {
            wp_redirect(home_url() . '?steam_error=' . urlencode($e->getMessage()));
            exit;
        }
    }
}

// Получение данных Steam
function get_steam_user_data($steam_id) {
    $api_key = get_option('steam_api_key', '');
    if (empty($api_key)) {
        log_steam_action($steam_id, 'steam_api_error', '', '', 'API-ключ Steam не настроен');
        wp_redirect(home_url() . '?steam_error=' . urlencode('API-ключ Steam не настроен'));
        exit;
    }

    $url = "https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key={$api_key}&steamids={$steam_id}";
    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        throw new Exception('Ошибка API Steam');
    }

    $data = json_decode($response['body'], true);
    if (empty($data['response']['players'])) {
        throw new Exception('Пользователь Steam не найден');
    }

    return $data['response']['players'][0];
}

// Логирование действий
function log_steam_action($steam_id, $action, $discord_id = '', $discord_username = '', $error = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_auth_logs';
    $log_limit = (int) get_option('steam_auth_log_limit', 50);

    // Запись лога в таблицу
    $wpdb->insert(
        $table_name,
        array(
            'date'            => current_time('mysql'),
            'steam_id'        => $steam_id,
            'action'          => $action,
            'discord_id'      => $discord_id,
            'discord_username'=> $discord_username,
            'error'           => $error
        )
    );

    // Ограничение количества записей (удаляем старые, если превышен лимит)
    $total_logs = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    if ($total_logs > $log_limit) {
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE id <= %d",
            $wpdb->get_var("SELECT id FROM $table_name ORDER BY id ASC LIMIT 1 OFFSET " . ($log_limit - 1))
        ));
    }

    // Отправка данных в бот
    $bot_url = get_option('bot_url_qery', '');
    $data = [
        'action' => $action,
        'steam_id' => $steam_id,
    ];
    if ($discord_id) $data['discord_id'] = $discord_id;
    if ($discord_username) $data['discord_username'] = $discord_username;
    if ($error) $data['error'] = $error;

    $args = [
        'body' => json_encode($data),
        'headers' => [
            'Content-Type' => 'application/json',
            'X-API-Key' => get_option('steam_auth_bot_api_key', ''),
        ],
        'method' => 'POST',
        'timeout' => 15,
    ];

    if (get_option('steam_auth_debug', false)) {
        error_log("Steam Auth: Отправка в бот: " . json_encode($data));
    }

    $response = wp_remote_post($bot_url, $args);

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log("Steam Auth: Ошибка отправки в Telegram бот: $error_message");
    } else {
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        if ($response_code !== 200) {
            error_log("Steam Auth: Бот вернул код $response_code: $response_body");
        } elseif (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Уведомление успешно отправлено: " . json_encode($data));
        }
    }
}

// Создание или авторизация пользователя
function process_steam_user($steam_user) {
    $steam_id = $steam_user['steamid'];
    $base_username = sanitize_user($steam_user['personaname'], true);
    $email = $steam_id . '@' . parse_url(home_url(), PHP_URL_HOST);

    $user = get_users([
        'meta_key' => 'steam_id',
        'meta_value' => $steam_id,
        'fields' => 'ID'
    ]);

    if (empty($user)) {
        $username = $base_username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $base_username . '_' . $steam_id;
            if (!username_exists($username)) break;
            $username = $base_username . '_' . $counter++;
        }

        $default_role = get_option('steam_default_role', 'subscriber');
        $userdata = [
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => wp_generate_password(),
            'role' => $default_role,
            'display_name' => $base_username
        ];

        $user_id = wp_insert_user($userdata);
        if (is_wp_error($user_id)) {
            throw new Exception("Не удалось создать пользователя: " . $user_id->get_error_message());
        }

        update_user_meta($user_id, 'steam_id', $steam_id);
        update_user_meta($user_id, 'steam_avatar', $steam_user['avatarfull']);
        update_user_meta($user_id, 'steam_profile', $steam_user['profileurl']);
        log_steam_action($steam_id, 'registration');
    } else {
        $user_id = $user[0];
        log_steam_action($steam_id, 'authorization');
    }

    wp_set_auth_cookie($user_id, true);
    wp_redirect(home_url('/user'));
    exit;
}

// Страница профиля с редактированием и привязкой/отвязкой Discord
add_shortcode('steam_profile', 'steam_profile_shortcode');
function steam_profile_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Пожалуйста, <a href="' . wp_login_url() . '">войдите через Steam</a>.</p>';
    }

    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    $steam_id = get_user_meta($user_id, 'steam_id', true);
    $steam_profile = get_user_meta($user_id, 'steam_profile', true);
    $steam_avatar = get_user_meta($user_id, 'steam_avatar', true);
    $user_email = $user->user_email;
    $display_name = $user->display_name;
    $discord_id = get_user_meta($user_id, 'discord_id', true);
    $discord_username = get_user_meta($user_id, 'discord_username', true);
    $discord_unlink_requests = get_option('steam_auth_discord_unlink_requests', []);
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
    $is_editing = ($tab === 'profile' && isset($_GET['edit']) && $_GET['edit'] === 'true');

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
        update_option('steam_profile_settings', $profile_settings);
    }

    $messages = get_user_messages($user_id);
    $unread_count = count(array_filter($messages, function($message) {
        return !$message['is_read'];
    }));

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
    // Начало сессии
if (!session_id()) {
    session_start();
}

// Уведомления
$transient_key = 'steam_profile_notification_' . $user_id;
$notification = isset($_SESSION['steam_profile_notification']) ? $_SESSION['steam_profile_notification'] : get_transient($transient_key);

// Обработка GET-параметров для уведомлений
if (isset($_GET['discord_success'])) {
    $notification = '<p class="success">' . esc_html(urldecode($_GET['discord_success'])) . '</p>';
    $_SESSION['steam_profile_notification'] = $notification;
} elseif (isset($_GET['discord_unlink_success'])) {
    $notification = '<p class="success">' . esc_html(urldecode($_GET['discord_unlink_success'])) . '</p>';
    $_SESSION['steam_profile_notification'] = $notification;
} elseif (isset($_GET['discord_error'])) {
    $notification = '<p class="error">' . esc_html(urldecode($_GET['discord_error'])) . '</p>';
    $_SESSION['steam_profile_notification'] = $notification;
    add_user_message($user_id, '', 'Ошибка Discord', urldecode($_GET['discord_error']));
}

// После обработки формы редактирования
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['steam_edit_profile_action']) && wp_verify_nonce($_POST['steam_edit_profile_action'], 'steam_edit_profile_nonce')) {
    $updated_data = [];
    $errors = [];

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

    if (empty($errors)) {
        $updated = wp_update_user(array_merge(['ID' => $user_id], $updated_data));
        if (is_wp_error($updated)) {
            $notification = '<p class="error">Ошибка: ' . $updated->get_error_message() . '</p>';
        } else {
            $notification = '<p class="success">Профиль обновлён!</p>';
            $user_email = isset($updated_data['user_email']) ? $updated_data['user_email'] : $user_email;
            $display_name = isset($updated_data['display_name']) ? $updated_data['display_name'] : $display_name;
        }
        $_SESSION['steam_profile_notification'] = $notification;
        wp_redirect(remove_query_arg('edit'));
        exit;
    } else {
        $notification = '<p class="error">' . implode('<br>', $errors) . '</p>';
        $_SESSION['steam_profile_notification'] = $notification;
    }
}

// Очистка уведомления после отображения
if ($notification && !isset($_GET['discord_success']) && !isset($_GET['discord_unlink_success']) && !isset($_GET['discord_error'])) {
    unset($_SESSION['steam_profile_notification']);
    delete_transient($transient_key);
}

    if (isset($_GET['action']) && $_GET['action'] === 'mark_read' && isset($_GET['message_id'])) {
        $message_id = sanitize_text_field($_GET['message_id']);
        mark_message_read($user_id, $message_id);
        wp_redirect(remove_query_arg(['action', 'message_id'], add_query_arg('tab', 'messages')));
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['message_id'])) {
        $message_id = sanitize_text_field($_GET['message_id']);
        delete_user_message($user_id, $message_id);
        wp_redirect(remove_query_arg(['action', 'message_id'], add_query_arg('tab', 'messages')));
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'delete_all_read') {
        delete_all_read_messages($user_id);
        wp_redirect(remove_query_arg(['action'], add_query_arg('tab', 'messages')));
        exit;
    }

    if (isset($_GET['action']) && $_GET['action'] === 'delete_all') {
        delete_all_messages($user_id);
        wp_redirect(remove_query_arg(['action'], add_query_arg('tab', 'messages')));
        exit;
    }

    ob_start();
    require_once plugin_dir_path(__FILE__) . 'user_profile.php';
    $output = ob_get_clean();

    if (get_option('steam_auth_debug', false)) {
        error_log("Steam Profile: Вкладка - $tab, Is Editing - " . ($is_editing ? 'true' : 'false') . ", Steam ID - $steam_id, Вывод - " . substr($output, 0, 200));
    }

    wp_enqueue_script('steam-auth-profile', plugins_url('js/profile.js', __FILE__), [], '2.10.2', true);
    wp_localize_script('steam-auth-profile', 'steamProfileData', [
        'notification' => $notification,
        'debug' => get_option('steam_auth_debug', false) ? true : false,
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('steam_profile_nonce'),
        'tab' => $tab
    ]);

    return $output;
}

// Обработка привязки Discord
add_action('init', 'handle_discord_link');
function handle_discord_link() {
    if (strpos($_SERVER['REQUEST_URI'], '/discord-link') === 0 && is_user_logged_in()) {
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'discord_link_nonce')) {
            wp_redirect(home_url('/user?discord_error=' . urlencode('Ошибка безопасности')));
            exit;
        }

        $client_id = get_option('steam_auth_discord_client_id', '');
        $client_secret = get_option('steam_auth_discord_client_secret', '');
        if (empty($client_id) || empty($client_secret)) {
            wp_redirect(home_url('/user?discord_error=' . urlencode('Discord не настроен')));
            exit;
        }

        $redirect_uri = home_url('/discord-callback');
        $auth_url = "https://discord.com/api/oauth2/authorize?client_id={$client_id}&redirect_uri=" . urlencode($redirect_uri) . "&response_type=code&scope=identify";
        wp_redirect($auth_url);
        exit;
    }
}

add_action('init', 'handle_discord_callback');
function handle_discord_callback() {
    if (strpos($_SERVER['REQUEST_URI'], '/discord-callback') === 0 && is_user_logged_in()) {
        $code = $_GET['code'] ?? '';
        if (empty($code)) {
            wp_redirect(home_url('/user?discord_error=' . urlencode('Ошибка авторизации')));
            exit;
        }

        $client_id = get_option('steam_auth_discord_client_id', '');
        $client_secret = get_option('steam_auth_discord_client_secret', '');
        $redirect_uri = home_url('/discord-callback');

        $response = wp_remote_post('https://discord.com/api/oauth2/token', [
            'body' => [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect_uri,
            ],
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        ]);

        if (is_wp_error($response)) {
            $steam_id = get_user_meta(get_current_user_id(), 'steam_id', true);
            log_steam_action($steam_id, 'discord_link', '', '', 'Ошибка получения токена');
            wp_redirect(home_url('/user?discord_error=' . urlencode('Ошибка получения токена')));
            exit;
        }

        $token_data = json_decode(wp_remote_retrieve_body($response), true);
        $access_token = $token_data['access_token'] ?? '';

        if (empty($access_token)) {
            $steam_id = get_user_meta(get_current_user_id(), 'steam_id', true);
            log_steam_action($steam_id, 'discord_link', '', '', 'Токен не получен');
            wp_redirect(home_url('/user?discord_error=' . urlencode('Токен не получен')));
            exit;
        }

        $user_response = wp_remote_get('https://discord.com/api/users/@me', [
            'headers' => ['Authorization' => "Bearer {$access_token}"],
        ]);

        if (is_wp_error($user_response)) {
            $steam_id = get_user_meta(get_current_user_id(), 'steam_id', true);
            log_steam_action($steam_id, 'discord_link', '', '', 'Ошибка получения данных пользователя');
            wp_redirect(home_url('/user?discord_error=' . urlencode('Ошибка получения данных пользователя')));
            exit;
        }

        $user_data = json_decode(wp_remote_retrieve_body($user_response), true);
        $discord_id = $user_data['id'];
        $discord_username = $user_data['username'];
        $user_id = get_current_user_id();
        $steam_id = get_user_meta($user_id, 'steam_id', true);

        $existing_user = get_users([
            'meta_key' => 'discord_id',
            'meta_value' => $discord_id,
            'exclude' => [$user_id],
            'fields' => 'ID',
        ]);

        if (!empty($existing_user)) {
            wp_redirect(home_url('/user?discord_error=' . urlencode('Этот Discord ID уже привязан к другому пользователю')));
            log_steam_action($steam_id, 'discord_link', $discord_id, $discord_username, 'Этот Discord ID уже привязан к другому пользователю');
            exit;
        }

        update_user_meta($user_id, 'discord_id', $discord_id);
        update_user_meta($user_id, 'discord_username', $discord_username);
        log_steam_action($steam_id, 'discord_link', $discord_id, $discord_username);

        // Добавляем уведомление в Discord
        $template_settings = [
            'color' => '3066993', // Success (зелёный)
            'fields' => [
                'title' => true, 'title_emoji' => '✅',
                'description' => true, 'description_emoji' => '🎉',
                'timestamp' => true,
                'footer' => true, 'footer_icon' => 'https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_small.png', 'footer_emoji' => '🌟',
                'author' => true, 'author_icon' => home_url('/favicon.ico'), 'author_emoji' => '',
                'custom' => []
            ]
        ];
        send_discord_message($discord_id, 'Discord привязан', "Ваш Discord ($discord_username) успешно привязан к Steam ($steam_id).", $template_settings);

        wp_redirect(home_url('/user?discord_success=' . urlencode('Discord успешно привязан')));
        exit;
    }
}
// Обработка отвязки Discord
// Обработка отвязки Discord
add_action('wp', 'handle_discord_unlink');
function handle_discord_unlink() {
    if (strpos($_SERVER['REQUEST_URI'], '/discord-unlink') === false || !is_user_logged_in()) {
        return;
    }

    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'discord_unlink_nonce')) {
        wp_redirect(home_url('/user?discord_error=' . urlencode('Неверный nonce')));
        exit;
    }

    $user_id = get_current_user_id();
    $discord_id = get_user_meta($user_id, 'discord_id', true);
    $discord_username = get_user_meta($user_id, 'discord_username', true);

    if (!$discord_id) {
        wp_redirect(home_url('/user?discord_error=' . urlencode('Discord не привязан')));
        exit;
    }

    $discord_unlink_requests = get_option('steam_auth_discord_unlink_requests', []);
    $discord_unlink_requests[$user_id] = [
        'id' => $discord_id,
        'username' => $discord_username,
        'date' => current_time('mysql')
    ];
    update_option('steam_auth_discord_unlink_requests', $discord_unlink_requests);

    $steam_id = get_user_meta($user_id, 'steam_id', true);
    log_steam_action($steam_id, 'discord_unlink_request', $discord_id, $discord_username);

    $template_settings = [
        'color' => '16776960', // Warning (жёлтый)
        'fields' => [
            'title' => true, 'title_emoji' => '⚠️',
            'description' => true, 'description_emoji' => '📢',
            'timestamp' => true,
            'footer' => true, 'footer_icon' => 'https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_small.png', 'footer_emoji' => '🔔',
            'author' => true, 'author_icon' => home_url('/favicon.ico'), 'author_emoji' => '',
            'custom' => []
        ]
    ];
    send_discord_message($discord_id, 'Запрос на отвязку Discord', 'Ваш запрос на отвязку Discord отправлен администратору. Ожидайте подтверждения.', $template_settings);

    if (!session_id()) {
        session_start();
    }
    $_SESSION['steam_profile_notification'] = '<p class="success">Запрос на отвязку Discord отправлен</p>';
    wp_redirect(home_url('/user'));
    exit;
}



add_action('admin_menu', 'steam_auth_settings_menu');
function steam_auth_settings_menu() {
    add_menu_page('Steam Auth', 'Steam Auth', 'manage_options', 'steam-auth', 'steam_auth_settings_page', 'dashicons-admin-users', 6);

    // Регистрируем группу настроек
    add_action('admin_init', 'steam_auth_register_settings');
}

function steam_auth_register_settings() {
    // Основные опции (General)
    register_setting('steam_auth_options', 'steam_api_key', [
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ]);
    register_setting('steam_auth_options', 'steam_auth_discord_guild_id', [
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ]);
    register_setting('steam_auth_options', 'bot_url_qery', [
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ]);
    register_setting('steam_auth_options', 'steam_default_role', [
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'subscriber'
    ]);
    register_setting('steam_auth_options', 'steam_auth_debug', [
        'sanitize_callback' => 'boolval',
        'default' => false
    ]);
    register_setting('steam_auth_options', 'steam_auth_admin_key', [
        'sanitize_callback' => 'sanitize_text_field',
        'default' => 'secret123'
    ]);
    register_setting('steam_auth_options', 'steam_auth_custom_login_enabled', [
        'sanitize_callback' => 'boolval',
        'default' => false
    ]);
    register_setting('steam_auth_options', 'steam_auth_discord_client_id', [
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ]);
    register_setting('steam_auth_options', 'steam_auth_discord_client_secret', [
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ]);
    register_setting('steam_auth_options', 'steam_auth_discord_bot_token', [
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ]);
    register_setting('steam_auth_options', 'steam_auth_bot_api_key', [  // Новый API-ключ для бота
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ]);
    register_setting('steam_auth_options', 'steam_auth_log_limit', [  // Новый лимит логов
        'sanitize_callback' => 'intval',
        'default' => 50
    ]);

    // Остальные опции
    register_setting('steam_auth_options', 'steam_profile_settings', [
        'sanitize_callback' => 'steam_auth_sanitize_profile_settings',
        'default' => [
            'fields' => [
                'display_name' => ['visible' => true, 'editable' => false, 'label' => 'Имя', 'icon' => 'fa-user'],
                'steam_id' => ['visible' => true, 'editable' => false, 'label' => 'SteamID', 'icon' => 'fa-steam'],
                'user_email' => ['visible' => true, 'editable' => true, 'label' => 'Email', 'icon' => 'fa-envelope'],
                'steam_profile' => ['visible' => true, 'editable' => false, 'label' => 'Steam Profile', 'icon' => 'fa-link'],
            ],
            'custom_fields' => []
        ]
    ]);
    register_setting('steam_auth_options', 'steam_auth_discord_embed_settings', [
        'sanitize_callback' => 'steam_auth_sanitize_embed_settings',
        'default' => [
            'color' => '3447003',
            'fields' => [
                'title' => true, 'title_emoji' => '',
                'description' => true, 'description_emoji' => '',
                'timestamp' => true,
                'footer' => true, 'footer_icon' => 'https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_small.png', 'footer_emoji' => '',
                'author' => true, 'author_icon' => home_url('/favicon.ico'), 'author_emoji' => '',
                'custom' => []
            ]
        ]
    ]);
    register_setting('steam_auth_options', 'steam_auth_mods_config', [
        'sanitize_callback' => 'steam_auth_sanitize_mods_config',
        'default' => []
    ]);
    register_setting('steam_auth_options', 'steam_auth_discord_custom_templates', [
        'sanitize_callback' => 'steam_auth_sanitize_custom_templates',
        'default' => []
    ]);

    // Добавляем секции и поля
    add_settings_section('steam_auth_general_section', 'Общие настройки', null, 'steam-auth');
    add_settings_field('steam_api_key', 'Steam API Key', 'steam_auth_field_callback', 'steam-auth', 'steam_auth_general_section', ['id' => 'steam_api_key', 'type' => 'text']);
    add_settings_field('steam_auth_discord_guild_id', 'ID Discord сервера', 'steam_auth_field_callback', 'steam-auth', 'steam_auth_general_section', ['id' => 'steam_auth_discord_guild_id', 'type' => 'text']);
    add_settings_field('bot_url_qery', 'Bot url Для запросов', 'steam_auth_field_callback', 'steam-auth', 'steam_auth_general_section', ['id' => 'bot_url_qery', 'type' => 'text']);
    add_settings_field('steam_default_role', 'Роль по умолчанию', 'steam_auth_field_callback', 'steam-auth', 'steam_auth_general_section', ['id' => 'steam_default_role', 'type' => 'select', 'options' => wp_roles()->get_names()]);
    add_settings_field('steam_auth_debug', 'Режим отладки', 'steam_auth_field_callback', 'steam-auth', 'steam_auth_general_section', ['id' => 'steam_auth_debug', 'type' => 'checkbox']);
    add_settings_field('steam_auth_admin_key', 'Ключ администратора', 'steam_auth_field_callback', 'steam-auth', 'steam_auth_general_section', ['id' => 'steam_auth_admin_key', 'type' => 'text']);
    add_settings_field('steam_auth_custom_login_enabled', 'Кастомная страница входа', 'steam_auth_field_callback', 'steam-auth', 'steam_auth_general_section', ['id' => 'steam_auth_custom_login_enabled', 'type' => 'checkbox']);
    add_settings_field('steam_auth_discord_client_id', 'Discord Client ID', 'steam_auth_field_callback', 'steam-auth', 'steam_auth_general_section', ['id' => 'steam_auth_discord_client_id', 'type' => 'text']);
    add_settings_field('steam_auth_discord_client_secret', 'Discord Client Secret', 'steam_auth_field_callback', 'steam-auth', 'steam_auth_general_section', ['id' => 'steam_auth_discord_client_secret', 'type' => 'text']);
    add_settings_field('steam_auth_discord_bot_token', 'Discord Bot Token', 'steam_auth_field_callback', 'steam-auth', 'steam_auth_general_section', ['id' => 'steam_auth_discord_bot_token', 'type' => 'text']);
    add_settings_field('steam_auth_bot_api_key', 'API-ключ для Telegram бота', 'steam_auth_field_callback', 'steam-auth', 'steam_auth_general_section', ['id' => 'steam_auth_bot_api_key', 'type' => 'text']);
    add_settings_field('steam_auth_log_limit', 'Лимит логов', 'steam_auth_field_callback', 'steam-auth', 'steam_auth_general_section', ['id' => 'steam_auth_log_limit', 'type' => 'number', 'min' => 10, 'max' => 1000]);
}

// Универсальная функция для вывода полей
function steam_auth_field_callback($args) {
    $value = get_option($args['id'], '');
    switch ($args['type']) {
        case 'text':
            echo "<input type='text' name='{$args['id']}' id='{$args['id']}' value='" . esc_attr($value) . "' class='regular-text'>";
            break;
        case 'checkbox':
            echo "<input type='checkbox' name='{$args['id']}' id='{$args['id']}' " . checked($value, true, false) . ">";
            break;
        case 'select':
            echo "<select name='{$args['id']}' id='{$args['id']}'>";
            foreach ($args['options'] as $key => $label) {
                echo "<option value='$key' " . selected($value, $key, false) . ">$label</option>";
            }
            echo "</select>";
            break;
        case 'number':
            echo "<input type='number' name='{$args['id']}' id='{$args['id']}' value='" . esc_attr($value) . "' min='{$args['min']}' max='{$args['max']}' class='small-text'>";
            break;
    }
}

// Функции санитизации сложных опций
function steam_auth_sanitize_profile_settings($input) {
    $output = [];
    $output['fields'] = [];
    foreach ($input['fields'] as $key => $field) {
        $output['fields'][$key] = [
            'visible' => isset($field['visible']),
            'editable' => isset($field['editable']),
            'label' => sanitize_text_field($field['label']),
            'icon' => sanitize_text_field($field['icon'])
        ];
    }
    $output['custom_fields'] = [];
    if (isset($input['custom_fields']) && is_array($input['custom_fields'])) {
        foreach ($input['custom_fields'] as $temp_key => $field) {
            // Используем значение поля 'name' как ключ, если оно задано, иначе генерируем уникальный ключ
            $field_name = !empty($field['name']) ? sanitize_key($field['name']) : 'custom_' . uniqid();
            $output['custom_fields'][$field_name] = [
                'visible' => isset($field['visible']),
                'editable' => isset($field['editable']),
                'label' => sanitize_text_field($field['label'] ?? 'Без названия'),
                'type' => in_array($field['type'], ['text', 'email', 'number', 'textarea']) ? $field['type'] : 'text',
                'icon' => sanitize_text_field($field['icon'] ?? '')
            ];
        }
    }
    return $output;
}

function steam_auth_sanitize_embed_settings($input) {
    $output = [];
    $output['color'] = (int)$input['color'];
    $output['fields'] = [
        'title' => isset($input['fields']['title']),
        'title_emoji' => sanitize_text_field($input['fields']['title_emoji'] ?? ''),
        'description' => isset($input['fields']['description']),
        'description_emoji' => sanitize_text_field($input['fields']['description_emoji'] ?? ''),
        'timestamp' => isset($input['fields']['timestamp']),
        'footer' => isset($input['fields']['footer']),
        'footer_icon' => esc_url_raw($input['fields']['footer_icon']),
        'footer_emoji' => sanitize_text_field($input['fields']['footer_emoji'] ?? ''),
        'author' => isset($input['fields']['author']),
        'author_icon' => esc_url_raw($input['fields']['author_icon']),
        'author_emoji' => sanitize_text_field($input['fields']['author_emoji'] ?? ''),
        'custom' => array_map(function($field) {
            return [
                'name' => sanitize_text_field($field['name']),
                'value' => sanitize_text_field($field['value']),
                'emoji' => sanitize_text_field($field['emoji'] ?? '')
            ];
        }, $input['fields']['custom'] ?? [])
    ];
    return $output;
}

function steam_auth_sanitize_mods_config($input) {
    $output = [];
    $output['selected_roles'] = [];
    foreach ($input as $role_id => $mod) {
        if (is_array($mod)) {
            $output[$role_id] = [
                'version' => sanitize_text_field($mod['version']),
                'image' => esc_url_raw($mod['image']),
                'documentation_url' => esc_url_raw($mod['documentation_url'])
            ];
            if (isset($mod['is_mod']) && $mod['is_mod'] === 'on') {
                $output['selected_roles'][$role_id] = true;
            }
        }
    }
    return $output;
}

function steam_auth_sanitize_custom_templates($input) {
    $output = [];
    foreach ($input as $key => $template) {
        $output[sanitize_key($key)] = [
            'color' => (int)$template['color'],
            'fields' => [
                'title' => isset($template['fields']['title']),
                'title_emoji' => sanitize_text_field($template['fields']['title_emoji'] ?? ''),
                'description' => isset($template['fields']['description']),
                'description_emoji' => sanitize_text_field($template['fields']['description_emoji'] ?? ''),
                'timestamp' => isset($template['fields']['timestamp']),
                'footer' => isset($template['fields']['footer']),
                'footer_icon' => esc_url_raw($template['fields']['footer_icon']),
                'footer_emoji' => sanitize_text_field($template['fields']['footer_emoji'] ?? ''),
                'author' => isset($template['fields']['author']),
                'author_icon' => esc_url_raw($template['fields']['author_icon']),
                'author_emoji' => sanitize_text_field($template['fields']['author_emoji'] ?? ''),
                'custom' => array_map(function($field) {
                    return [
                        'name' => sanitize_text_field($field['name']),
                        'value' => sanitize_text_field($field['value']),
                        'emoji' => sanitize_text_field($field['emoji'] ?? '')
                    ];
                }, $template['fields']['custom'] ?? [])
            ]
        ];
    }
    return $output;
}


/**
 * Генерирует страницу настроек для администратора.
 *
 * Функция отображает меню с закладками и контент под ними.
 * Она также отображает уведомления и модальное окно для подтверждения.
 *
 * @since 1.0.0
 */

 function steam_auth_settings_page() {
    $notification = '';
    if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
        $notification = '<div class="notice notice-success is-dismissible"><p>Настройки сохранены.</p></div>';
    } elseif (isset($_GET['error'])) {
        $notification = '<div class="notice notice-error is-dismissible"><p>Ошибка: ' . esc_html($_GET['error']) . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Steam Auth <button id="theme-toggle" class="button">Тёмная тема</button></h1>
        <?php echo $notification; ?>
        <div class="nav-tab-wrapper">
            <a href="#general" class="nav-tab nav-tab-active" data-tab="general">Общие</a>
            <a href="#profile" class="nav-tab" data-tab="profile">Поля профиля</a>
            <a href="#logs" class="nav-tab" data-tab="logs">Логи</a>
            <a href="#discord-unlink" class="nav-tab" data-tab="discord-unlink">Discord Unlink Requests</a>
            <a href="#messages" class="nav-tab" data-tab="messages">Сообщения</a>
            <a href="#discord-notifications" class="nav-tab" data-tab="discord-notifications">Discord Notifications</a>
            <a href="#mods" class="nav-tab" data-tab="mods">Моды</a>
        </div>
        <div id="tab-content"></div>

        <div id="steam-confirm-modal" class="steam-modal" style="display: none;">
            <div class="steam-modal-content">
                <p id="steam-confirm-message"></p>
                <div class="steam-modal-buttons">
                    <button id="steam-confirm-yes" class="button button-primary">Да</button>
                    <button id="steam-confirm-no" class="button">Нет</button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

add_action('rest_api_init', function () {
    register_rest_route('steam-auth/v1', '/logs', [
        'methods' => 'GET',
        'callback' => 'get_steam_auth_logs_rest', // Изменено имя функции
        'permission_callback' => function () {
            $api_key = $_GET['api_key'] ?? '';
            return $api_key === get_option('steam_auth_bot_api_key', '');
        }
    ]);

    register_rest_route('steam-auth/v1', '/clearlogs', [
        'methods' => 'POST',
        'callback' => 'clear_steam_auth_logs',
        'permission_callback' => function ($request) {
            $api_key = $request->get_param('api_key');
            $stored_key = get_option('steam_auth_bot_api_key');
            return $api_key && $stored_key && $api_key === $stored_key;
        }
    ]);

    register_rest_route('steam-auth/v1', '/stats', [
        'methods' => 'GET',
        'callback' => 'get_steam_auth_stats',
        'permission_callback' => function () {
            $api_key = $_GET['api_key'] ?? '';
            return $api_key === get_option('steam_auth_bot_api_key', '');
        }
    ]);

    register_rest_route('steam-auth/v1', '/approve_unlink', [
        'methods' => 'POST',
        'callback' => 'approve_discord_unlink',
        'permission_callback' => function ($request) {
            $api_key = $request->get_param('api_key');
            return $api_key === get_option('steam_auth_bot_api_key', '');
        }
    ]);

    register_rest_route('steam-auth/v1', '/reject_unlink', [
        'methods' => 'POST',
        'callback' => 'reject_discord_unlink',
        'permission_callback' => function ($request) {
            $api_key = $request->get_param('api_key');
            return $api_key === get_option('steam_auth_bot_api_key', '');
        }
    ]);

    register_rest_route('steam-auth/v1', '/health', [
        'methods' => 'GET',
        'callback' => 'check_plugin_health',
        'permission_callback' => '__return_true' 
    ]);

});

function check_plugin_health($request) {
    return [
        'status' => 'ok',
        'version' => '2.10.2',
        'timestamp' => current_time('mysql')
    ];
}

function approve_discord_unlink($request) {
    $steam_id = sanitize_text_field($request['steam_id']);
    $discord_id = sanitize_text_field($request['discord_id']);

    $user = get_users([
        'meta_key' => 'steam_id',
        'meta_value' => $steam_id,
        'fields' => 'ID'
    ]);

    if (empty($user)) {
        error_log("Steam Auth: Ошибка approve_unlink - Пользователь с Steam ID $steam_id не найден");
        return new WP_Error('no_user', 'Пользователь не найден', ['status' => 404]);
    }

    $user_id = $user[0];
    $discord_unlink_requests = get_option('steam_auth_discord_unlink_requests', []);

    if (!isset($discord_unlink_requests[$user_id]) || $discord_unlink_requests[$user_id]['id'] !== $discord_id) {
        error_log("Steam Auth: Ошибка approve_unlink - Запрос на отвязку для user_id $user_id и discord_id $discord_id не найден");
        return new WP_Error('no_request', 'Запрос на отвязку не найден', ['status' => 404]);
    }

    $discord_username = $discord_unlink_requests[$user_id]['username'];
    delete_user_meta($user_id, 'discord_id');
    delete_user_meta($user_id, 'discord_username');
    unset($discord_unlink_requests[$user_id]);
    update_option('steam_auth_discord_unlink_requests', $discord_unlink_requests);

    log_steam_action($steam_id, 'discord_unlink_approved', $discord_id, $discord_username);

    $template_settings = [/* ... */];
    send_discord_message($discord_id, 'Discord отвязан', "Ваш Discord ($discord_username) был отвязан от Steam ($steam_id) администратором.", $template_settings);

    return ['success' => true, 'message' => 'Отвязка одобрена'];
}

function reject_discord_unlink($request) {
    $steam_id = sanitize_text_field($request['steam_id']);
    $discord_id = sanitize_text_field($request['discord_id']);

    $user = get_users([
        'meta_key' => 'steam_id',
        'meta_value' => $steam_id,
        'fields' => 'ID'
    ]);

    if (empty($user)) {
        error_log("Steam Auth: Ошибка reject_unlink - Пользователь с Steam ID $steam_id не найден");
        return new WP_Error('no_user', 'Пользователь не найден', ['status' => 404]);
    }

    $user_id = $user[0];
    $discord_unlink_requests = get_option('steam_auth_discord_unlink_requests', []);

    if (!isset($discord_unlink_requests[$user_id]) || $discord_unlink_requests[$user_id]['id'] !== $discord_id) {
        error_log("Steam Auth: Ошибка reject_unlink - Запрос на отвязку для user_id $user_id и discord_id $discord_id не найден");
        return new WP_Error('no_request', 'Запрос на отвязку не найден', ['status' => 404]);
    }

    $discord_username = $discord_unlink_requests[$user_id]['username'];
    unset($discord_unlink_requests[$user_id]);
    update_option('steam_auth_discord_unlink_requests', $discord_unlink_requests);

    log_steam_action($steam_id, 'discord_unlink_rejected', $discord_id, $discord_username);

    $template_settings = [/* ... */];
    send_discord_message($discord_id, 'Запрос на отвязку отклонён', "Ваш запрос на отвязку Discord ($discord_username) от Steam ($steam_id) был отклонён администратором.", $template_settings);

    return ['success' => true, 'message' => 'Запрос отклонён'];
}

function get_steam_auth_logs_rest($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_auth_logs';
    $type = $request->get_param('type') ?? 'all';
    $limit = 50; // Можно сделать настраиваемым через $request->get_param('limit') в будущем

    // Проверяем, существует ли таблица
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth REST: Таблица $table_name не найдена");
        }
        return [];
    }

    $query = "SELECT * FROM $table_name";
    if ($type !== 'all') {
        $query .= $wpdb->prepare(" WHERE action = %s", $type);
    }
    $query .= " ORDER BY date DESC LIMIT %d";
    $logs = $wpdb->get_results($wpdb->prepare($query, $limit));

    if ($logs === null) {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth REST: Ошибка запроса к таблице $table_name: " . $wpdb->last_error);
        }
        return [];
    }

    if (get_option('steam_auth_debug', false)) {
        error_log("Steam Auth REST: Возвращено логов для type=$type: " . count($logs));
    }

    return array_values($logs);
}

/**
 * Возвращает статистику по событиям Steam Auth за указанный период.
 *
 * @param WP_REST_Request $request
 * @return array{
 *     'registration' => int,
 *     'authorization' => int,
 *     'discord_link' => int,
 *     'discord_unlink' => int
 * }
 */
function get_steam_auth_stats($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_auth_logs';
    $period = $request->get_param('period') ?? 'day';
    $timezone = wp_timezone();
    $date = new DateTime('now', $timezone);

    switch ($period) {
        case 'day':
            $date->modify('-1 day');
            break;
        case 'week':
            $date->modify('-1 week');
            break;
        case 'month':
            $date->modify('-1 month');
            break;
    }

    $date_str = $date->format('Y-m-d H:i:s');
    $query = $wpdb->prepare(
        "SELECT action, date FROM $table_name WHERE date >= %s",
        $date_str
    );
    $logs = $wpdb->get_results($query);

    if ($logs === null) {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Ошибка запроса статистики из $table_name: " . $wpdb->last_error);
        }
        return [
            'registration' => 0,
            'authorization' => 0,
            'discord_link' => 0,
            'discord_unlink' => 0
        ];
    }

    $stats = [
        'registration' => 0,
        'authorization' => 0,
        'discord_link' => 0,
        'discord_unlink' => 0
    ];

    foreach ($logs as $log) {
        switch (strtolower($log->action)) {
            case 'registration':
                $stats['registration']++;
                break;
            case 'authorization':
                $stats['authorization']++;
                break;
            case 'discord_link':
                $stats['discord_link']++;
                break;
            case 'discord_unlink':
            case 'discord_unlink_approved': // Добавляем для полноты
                $stats['discord_unlink']++;
                break;
        }
    }

    if (get_option('steam_auth_debug', false)) {
        error_log("Steam Auth: Статистика за $period: " . json_encode($stats));
    }

    return $stats;
}

/**
 * Очищает таблицу логов Steam Auth.
 *
 * Эта функция проверяет существование таблицы логов и очищает её,
 * используя команду TRUNCATE. Если таблица не найдена или происходит
 * ошибка базы данных, возвращает WP_Error. В случае успешной очистки
 * возвращает массив с сообщением об успешной операции.
 *
 * @param WP_REST_Request $request Запрос REST API.
 * @return array|WP_Error Результат операции в виде массива или ошибки.
 */

function clear_steam_auth_logs($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_auth_logs';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return new WP_Error('no_table', 'Таблица логов не найдена', ['status' => 500]);
    }

    $result = $wpdb->query("TRUNCATE TABLE $table_name");

    if ($result === false) {
        error_log("Steam Auth: Ошибка очистки таблицы $table_name: " . $wpdb->last_error);
        return new WP_Error('db_error', 'Ошибка базы данных при очистке логов', ['status' => 500]);
    }

    if (get_option('steam_auth_debug', false)) {
        error_log("Steam Auth: Логи успешно очищены из таблицы $table_name");
    }

    return ['success' => true, 'message' => 'Логи очищены'];
}

add_action('init', 'restrict_wp_login_with_key', 5);
/**
 * Ограничивает доступ к странице wp-login.php.
 *
 * Эта функция проверяет, является ли текущий запрос к странице
 * wp-login.php, и если да, то проверяет наличие параметра GET 'key'
 * и сравнивает его со значением, сохранённым в настройках
 * 'steam_auth_admin_key'. Если ключи совпадают, то сессия
 * подтверждается, если нет, то редирект на главную страницу.
 *
 * @since 2.10.0
 */
function restrict_wp_login_with_key() {
    $login_page = 'auth';
    $secret_key = get_option('steam_auth_admin_key', 'secret123');
    $request_uri = trim($_SERVER['REQUEST_URI'], '/');
    $debug_mode = get_option('steam_auth_debug', false);

    if (!session_id()) {
        session_start();
    }

    if ($request_uri === $login_page || strpos($request_uri, $login_page . '?') === 0) {
        $provided_key = $_GET['key'] ?? '';

        if ($debug_mode) {
            error_log("Steam Auth: Проверка ключа. URI: $request_uri, Provided key: $provided_key, Secret key: $secret_key");
        }

        if ($provided_key === $secret_key) {
            $_SESSION['steam_auth_key_verified'] = true;
            if ($debug_mode) {
                error_log("Steam Auth: Ключ верный, доступ разрешён, сессия установлена");
            }
            if ($request_uri === $login_page . '?key=' . $secret_key) {
                wp_redirect(home_url('/' . $login_page));
                exit;
            }
        } elseif (!isset($_SESSION['steam_auth_key_verified']) || !$_SESSION['steam_auth_key_verified']) {
            if ($debug_mode) {
                error_log("Steam Auth: Ключ неверный и сессия не подтверждена, редирект на главную");
            }
            wp_redirect(home_url());
            exit;
        } elseif ($debug_mode) {
            error_log("Steam Auth: Ключ отсутствует, но сессия подтверждена, доступ разрешён");
        }
    } elseif ($debug_mode) {
        //error_log("Steam Auth: Запрос не к /auth, пропускаем. URI: $request_uri");
    }
}

// Скрываем верхнюю панель для не-администраторов
add_action('after_setup_theme', 'hide_admin_bar_for_non_admins');
function hide_admin_bar_for_non_admins() {
    if (!current_user_can('administrator') && !is_admin()) {
        show_admin_bar(false);
    }
}

// Подключение стилей для страницы входа
add_action('login_enqueue_scripts', 'customize_login_page_styles');
function customize_login_page_styles() {
    if (get_option('steam_auth_custom_login_enabled', false)) {
        wp_enqueue_style('custom-login', plugin_dir_url(__FILE__) . 'css/login.css', [], '2.10.2');
    }
}

// Перехват страницы входа
add_action('login_init', 'custom_login_page', 1);
function custom_login_page() {
    if (!get_option('steam_auth_custom_login_enabled', false)) {
        return;
    }

    $request_uri = trim($_SERVER['REQUEST_URI'], '/');
    $login_page = 'auth';

    if (strpos($request_uri, $login_page) !== 0) {
        return;
    }

    if (is_user_logged_in()) {
        wp_redirect(admin_url());
        exit;
    }

    $error_message = '';
    $action = $_GET['action'] ?? '';
    $is_lostpassword = ($action === 'lostpassword');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wp-submit'])) {
        if ($is_lostpassword) {
            $user_login = sanitize_text_field($_POST['user_login']);
            $user = get_user_by('login', $user_login) ?: get_user_by('email', $user_login);
            if ($user) {
                $reset_key = get_password_reset_key($user);
                $reset_url = home_url("/auth?action=rp&key=$reset_key&login=" . rawurlencode($user->user_login));
                $message = "Для сброса пароля перейдите по ссылке: <a href='$reset_url'>$reset_url</a>";
                wp_mail($user->user_email, 'Сброс пароля', $message);
                $error_message = '<p class="success-message">Ссылка для сброса пароля отправлена на email.</p>';
            } else {
                $error_message = '<p class="error-message">Пользователь не найден.</p>';
            }
        } else {
            $creds = [
                'user_login' => sanitize_text_field($_POST['log']),
                'user_password' => $_POST['pwd'],
                'remember' => false
            ];
            $user = wp_signon($creds, is_ssl());
            
            if (is_wp_error($user)) {
                $error_message = '<p class="error-message">' . $user->get_error_message() . '</p>';
                if (get_option('steam_auth_debug', false)) {
                    error_log("Steam Auth: Ошибка входа: " . $user->get_error_message());
                }
            } else {
                wp_set_auth_cookie($user->ID);
                wp_redirect(admin_url());
                exit;
            }
        }
    }

    ob_start();
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php echo $is_lostpassword ? 'Восстановление пароля' : 'Удаление Мозга'; ?></title>
        <link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__) . 'css/login.css'; ?>" type="text/css" media="all">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap">
        <?php wp_head(); ?>
    </head>
    <body class="login">
        <div id="login">
            <p class="login-message"><?php echo $is_lostpassword ? 'Восстановление пароля' : 'Удаление Мозга'; ?></p>
            <?php echo $error_message; ?>
            <?php if ($is_lostpassword) : ?>
                <form name="lostpasswordform" id="lostpasswordform" action="<?php echo esc_url(home_url('/auth?action=lostpassword')); ?>" method="post">
                    <p>
                        <label for="user_login">Логин или Email<br>
                        <input type="text" name="user_login" id="user_login" class="input" value="" size="20" autocapitalize="off" required></label>
                    </p>
                    <p class="submit">
                        <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="Отправить">
                    </p>
                </form>
                <p class="login-link"><a href="<?php echo esc_url(home_url('/auth')); ?>">Вернуться ко входу</a></p>
            <?php else : ?>
                <form name="loginform" id="loginform" action="<?php echo esc_url(home_url('/auth')); ?>" method="post">
                    <p>
                        <label for="user_login">Логин<br>
                        <input type="text" name="log" id="user_login" class="input" value="" size="20" autocapitalize="off" autocomplete="username" required></label>
                    </p>
                    <p>
                        <label for="user_pass">Пароль<br>
                        <input type="password" name="pwd" id="user_pass" class="input" value="" size="20" autocomplete="current-password" required></label>
                    </p>
                    <p class="submit">
                        <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary" value="Войти">
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url(admin_url()); ?>">
                    </p>
                    <?php wp_nonce_field('login', '_wpnonce'); ?>
                </form>
                <p class="lost-password-link"><a href="<?php echo esc_url(home_url('/auth?action=lostpassword')); ?>">Забыли пароль?</a></p>
            <?php endif; ?>
        </div>
        <?php wp_footer(); ?>
    </body>
    </html>
    <?php
    echo ob_get_clean();
    exit;
}

// Убираем стандартные хуки WordPress для кастомной страницы
add_filter('login_message', '__return_empty_string');
add_action('login_form', '__return_false');
add_filter('login_display_language_dropdown', '__return_false');


/**
 * Добавляет сообщение для пользователя
 *
 * @param int $user_id    ID пользователя, для которого добавляется сообщение. 0 - для всех пользователей.
 * @param string $role    Роль, для которой добавляется сообщение. Если пусто, то для всех ролей.
 * @param string $title   Заголовок сообщения.
 * @param string $content Текст сообщения.
 * @param bool   $send_discord    Отправлять сообщение в Discord?
 * @param string $category  Категория сообщения (например, 'general', 'donate', 'welcome').
 *
 * @return string ID добавленного сообщения.
 */
function add_user_message($user_id, $role, $title, $content, $send_discord = true, $category = 'general') {
    $messages = get_option('steam_auth_messages', []);
    $message_id = uniqid();
    $title = !empty($title) ? sanitize_text_field($title) : 'Без заголовка';
    $content = !empty($content) ? sanitize_textarea_field($content) : 'Сообщение отсутствует';
    $category = sanitize_text_field($category); // Добавляем категорию
    $new_message = [
        'id' => $message_id,
        'user_id' => $user_id,
        'role' => $role,
        'title' => $title,
        'content' => $content,
        'category' => $category, // Новое поле
        'date' => current_time('mysql'),
    ];
    $messages[] = $new_message;
    update_option('steam_auth_messages', $messages);

    if ($send_discord) {
        if ($user_id == 0) {
            if (get_option('steam_auth_debug', false)) {
                log_steam_action('system', 'message_broadcast', '', '', "Сообщение для " . ($role ? "роли $role" : "всех") . ": $title");
            }
            $users = get_users($role ? ['role' => $role] : []);
            foreach ($users as $user) {
                $discord_id = get_user_meta($user->ID, 'discord_id', true);
                $notifications_enabled = get_user_meta($user->ID, 'discord_notifications_enabled', true);
                if ($discord_id && $notifications_enabled !== '0') {
                    send_discord_message($discord_id, $title, $content);
                }
            }
        } else {
            if (get_option('steam_auth_debug', false)) {
                log_steam_action("user_$user_id", 'message_sent', '', '', $title);
            }
            $discord_id = get_user_meta($user_id, 'discord_id', true);
            $notifications_enabled = get_user_meta($user_id, 'discord_notifications_enabled', true);
            if ($discord_id && $notifications_enabled !== '0') {
                send_discord_message($discord_id, $title, $content);
            }
        }
    }

    return $message_id;
}


/**
 * Отправляет сообщение пользователю в Discord.
 *
 * @param int    $discord_id   ID пользователя Discord, которому отправляется сообщение.
 * @param string $message_title Заголовок сообщения.
 * @param string $message_content      Текст сообщения.
 * @param array  $template_settings    Массив с настройками шаблона embed. Если пустой, то используются значения по умолчанию.
 *
 * @return bool Результат отправки сообщения.
 */
function send_discord_message($discord_id, $message_title, $message_content, $template_settings = null) {
    $bot_token = get_option('steam_auth_discord_bot_token', '');
    if (empty($bot_token)) {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Discord Bot Token не настроен");
        }
        return false;
    }

    // Используем переданные настройки шаблона или дефолтные
    $default_settings = get_option('steam_auth_discord_embed_settings', [
        'color' => '3447003',
        'fields' => [
            'title' => true,
            'title_emoji' => '',
            'description' => true,
            'description_emoji' => '',
            'timestamp' => true,
            'footer' => true,
            'footer_icon' => 'https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_small.png',
            'footer_emoji' => '',
            'author' => true,
            'author_icon' => home_url('/favicon.ico'),
            'author_emoji' => '',
            'custom' => []
        ]
    ]);
    $embed_settings = $template_settings ?? $default_settings;

    // Создаём DM-канал
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
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Ошибка создания DM канала для Discord ID $discord_id: " . $response->get_error_message());
        }
        return false;
    }

    $channel_data = json_decode(wp_remote_retrieve_body($response), true);
    $channel_id = $channel_data['id'] ?? '';
    if (!$channel_id) {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Не удалось создать DM канал для Discord ID: $discord_id");
        }
        return false;
    }

    $embed = [
        'title' => ($embed_settings['fields']['title_emoji'] ? $embed_settings['fields']['title_emoji'] . ' ' : '') . $message_title,
        'description' => ($embed_settings['fields']['description_emoji'] ? $embed_settings['fields']['description_emoji'] . ' ' : '') . $message_content,
        'color' => (int)$embed_settings['color']
    ];

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
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Ошибка отправки embed в Discord для ID $discord_id: " . $response->get_error_message());
        }
        return false;
    } elseif (wp_remote_retrieve_response_code($response) !== 200) {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Discord API вернул ошибку для ID $discord_id: " . wp_remote_retrieve_body($response));
        }
        return false;
    }

    if (get_option('steam_auth_debug', false)) {
        error_log("Steam Auth: Сообщение успешно отправлено в Discord для ID $discord_id: " . json_encode($embed));
    }
    return true;
}

/**
 * Возвращает массив сообщений Steam Auth, отсортированный в порядке убывания даты создания,
 * для указанного пользователя, отфильтрованных по категории (если указана).
 *
 * @param int    $user_id       ID пользователя, для которого нужно получить сообщения.
 * @param string $category_filter  Фильтр по категории сообщений. Если пуст, то возвращаются все сообщения.
 *
 * @return array Массив сообщений, отсортированный в порядке убывания даты создания.
 */
function get_user_messages($user_id, $category_filter = '') {
    $all_messages = get_option('steam_auth_messages', []);
    $user = wp_get_current_user();
    $user_role = !empty($user->roles) ? $user->roles[0] : 'subscriber';
    $messages = [];

    foreach ($all_messages as $message) {
        $is_for_user = ($message['user_id'] == $user_id) || 
                       ($message['user_id'] == 0 && (empty($message['role']) || $message['role'] == $user_role));
        if ($is_for_user && (empty($category_filter) || $message['category'] === $category_filter)) {
            $message['is_read'] = get_user_meta($user_id, 'steam_message_read_' . $message['id'], true) == '1';
            $messages[] = $message;
        }
    }

    return array_reverse($messages);
}

function steam_auth_get_unread_messages_count($user_id) {
    if (!is_user_logged_in() || !$user_id) {
        return 0;
    }
    $messages = get_user_messages($user_id);
    return count(array_filter($messages, function($message) {
        return !$message['is_read'];
    }));
}

// Пометка сообщения как прочитанного
function mark_message_read($user_id, $message_id) {
    update_user_meta($user_id, 'steam_message_read_' . $message_id, '1');
}

function delete_user_message($user_id, $message_id) {
    $all_messages = get_option('steam_auth_messages', []);
    $updated_messages = [];
    $deleted = false;

    foreach ($all_messages as $message) {
        if ((string)$message['id'] === (string)$message_id && (int)$message['user_id'] === (int)$user_id) {
            $deleted = true;
            continue;
        }
        $updated_messages[] = $message;
    }

    if ($deleted) {
        update_option('steam_auth_messages', $updated_messages);
        delete_user_meta($user_id, 'steam_message_read_' . $message_id);
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Сообщение $message_id удалено для пользователя $user_id");
        }
        wp_send_json_success(['message' => 'Сообщение удалено']);
    } else {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Сообщение $message_id не найдено для пользователя $user_id");
        }
        wp_send_json_error(['message' => 'Сообщение не найдено']);
    }
}

function delete_all_read_messages($user_id) {
    $all_messages = get_option('steam_auth_messages', []);
    $updated_messages = [];

    foreach ($all_messages as $message) {
        $is_for_user = $message['user_id'] == $user_id;
        if ($is_for_user && get_user_meta($user_id, 'steam_message_read_' . $message['id'], true) == '1') {
            delete_user_meta($user_id, 'steam_message_read_' . $message['id']);
            continue; // Пропускаем только прочитанные сообщения этого пользователя
        }
        $updated_messages[] = $message;
    }

    update_option('steam_auth_messages', $updated_messages);
}

function delete_all_messages($user_id) {
    $all_messages = get_option('steam_auth_messages', []);
    $initial_count = count($all_messages);
    $updated_messages = array_filter($all_messages, function($message) use ($user_id) {
        return $message['user_id'] != $user_id;
    });
    $updated_count = count($updated_messages);

    if ($initial_count > $updated_count) {
        update_option('steam_auth_messages', array_values($updated_messages));
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Все сообщения пользователя $user_id удалены");
        }
        wp_send_json_success(['message' => 'Все сообщения удалены']);
    } else {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Нет сообщений для удаления у пользователя $user_id");
        }
        wp_send_json_error(['message' => 'Нет сообщений для удаления']);
    }
}


/**
 * Получает информацию о членстве пользователя в Discord-сервере.
 *
 * Эта функция делает запрос к Discord API для получения данных о членстве 
 * пользователя с указанным Discord ID на конкретном сервере.
 *
 * @param string $discord_id Discord ID пользователя, для которого необходимо 
 * получить информацию о членстве.
 * 
 * @return array|false Возвращает массив с информацией о членстве пользователя в 
 * Discord-сервере, либо false в случае ошибки или отсутствия токена.
 */

function fetch_discord_membership($discord_id) {
    $bot_token = get_option('steam_auth_discord_bot_token', '');
    $guild_id = '958141724054671420'; // ID вашего сервера
    if (!$bot_token) return false;

    $url = "https://discord.com/api/v10/guilds/{$guild_id}/members/{$discord_id}";
    $response = wp_remote_get($url, [
        'headers' => ['Authorization' => "Bot {$bot_token}"]
    ]);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Ошибка получения данных Discord: " . wp_remote_retrieve_body($response));
        }
        return false;
    }
    return json_decode(wp_remote_retrieve_body($response), true);
}


/**
 * Получает информацию о ролях на Discord-сервере.
 *
 * Эта функция делает запрос к Discord API для получения данных о ролях на 
 * конкретном сервере. Она кэширует ответ на 1 час, поэтому если вам 
 * необходимо обновить список ролей, то вам нужно очистить кэш.
 *
 * @return array Массив, содержащий информацию о ролях на сервере, 
 * где ключ - ID роли, а значение - массив с информацией о роли.
 */
function fetch_discord_roles() {
    $bot_token = get_option('steam_auth_discord_bot_token', '');
    //$guild_id = '958141724054671420';
    $guild_id = get_option('steam_auth_discord_guild_id', '');
    if (!$bot_token) return [];

    $roles = get_transient('steam_auth_discord_roles');
    if ($roles === false) {
        $url = "https://discord.com/api/v10/guilds/{$guild_id}/roles";
        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => "Bot {$bot_token}"],
            'timeout' => 15
        ]);
        if (is_wp_error($response)) {
            error_log("Steam Auth: Ошибка получения ролей Discord: " . $response->get_error_message());
            return [];
        }
        $roles = json_decode(wp_remote_retrieve_body($response), true);
        $role_map = [];
        foreach ($roles as $role) {
            $role_map[$role['id']] = [
                'name' => $role['name'],
                'color' => $role['color']
            ];
        }
        set_transient('steam_auth_discord_roles', $role_map, HOUR_IN_SECONDS);
        return $role_map;
    }
    return $roles;
}

// Функция для создания таблицы логов
function steam_auth_create_logs_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_auth_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        steam_id varchar(255) NOT NULL,
        action varchar(255) NOT NULL,
        discord_id varchar(255) DEFAULT '' NOT NULL,
        discord_username varchar(255) DEFAULT '' NOT NULL,
        error text,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
}

// Проверка и создание таблицы при загрузке плагина
function steam_auth_check_tables() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_auth_logs';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        steam_auth_create_logs_table();
    }
}

// Регистрируем хук на активацию и проверку при загрузке
register_activation_hook( __FILE__, 'steam_auth_create_logs_table' );
add_action('plugins_loaded', 'steam_auth_check_tables');

// Функция для получения логов из таблицы
function get_steam_auth_logs($limit = 50) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_auth_logs';

    // Проверяем, существует ли таблица
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Таблица $table_name не найдена, возвращаем пустой массив");
        }
        return [];
    }

    // Приводим $limit к целому числу, используем значение по умолчанию 50, если не указано
    $limit = (int) $limit > 0 ? (int) $limit : 50;

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY date DESC LIMIT %d",
        $limit
    ));

    if ($results === null) {
        if (get_option('steam_auth_debug', false)) {
            error_log("Steam Auth: Ошибка запроса к таблице $table_name: " . $wpdb->last_error);
        }
        return [];
    }

    return $results;
}

// AJAX-обработчики для вкладок
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

    // Сообщения
    $messages = get_user_messages($user_id);
    $unread_count = count(array_filter($messages, function($message) {
        return !$message['is_read'];
    }));

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
    $_GET['edit'] = $edit ? 'true' : null;

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
    delete_user_message($user_id, $message_id);
    wp_send_json_success();
}

add_action('wp_ajax_delete_all_read', 'steam_auth_delete_all_read');
function steam_auth_delete_all_read() {
    check_ajax_referer('steam_profile_nonce', 'nonce');
    $user_id = get_current_user_id();
    delete_all_read_messages($user_id);
    wp_send_json_success();
}

add_action('wp_ajax_delete_all', 'steam_auth_delete_all');
function steam_auth_delete_all() {
    check_ajax_referer('steam_profile_nonce', 'nonce');
    $user_id = get_current_user_id();
    delete_all_messages($user_id);
    wp_send_json_success();
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
?>