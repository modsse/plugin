<?php
// Проверяем, что файл вызывается в контексте WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Регистрация шорткода для административного дашборда
add_shortcode('steam_admin_dashboard', 'steam_auth_admin_dashboard_shortcode');
function steam_auth_admin_dashboard_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Пожалуйста, войдите в систему для доступа к дашборду.</p>';
    }

    $user_id = get_current_user_id();
    $user = wp_get_current_user();

    // Проверяем права доступа
    if (!user_can($user_id, 'manage_options') && !steam_auth_has_moderator_role($user)) {
        wp_redirect(home_url());
    }

    // Подключаем стили и скрипты для фронтенда
    wp_enqueue_style('steam-auth-admin-style', plugin_dir_url(__DIR__) . 'css/admin_board.css', [], filemtime(plugin_dir_path(__DIR__) . 'css/admin_board.css'));
    wp_enqueue_script('steam-auth-admin-script', plugin_dir_url(__DIR__) . 'js/admin.js', ['jquery'], filemtime(plugin_dir_path(__DIR__) . 'js/admin.js'), true);
    wp_localize_script('steam-auth-admin-script', 'steamAuthAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('steam_auth_nonce'),
        'debug' => get_option('steam_auth_debug', false) ? true : false,
        'ticket_auto_delete_days' => get_option('ticket_auto_delete_days', 0),
        'home_url' => home_url(), // Для копирования ссылки
    ]);

    ob_start();
    ?>
    <div id="steam-admin-dashboard" style="max-width: none; width: 100%; margin: 0; padding: 0;">
        <ul class="tabs-nav">
            <li class="tab-link active" data-tab="tickets">Тикеты</li>
            <li class="tab-link" data-tab="users">Пользователи</li>
            <?php if (user_can($user_id, 'manage_options')): ?>
                <li class="tab-link" data-tab="settings">Настройки</li>
            <?php endif; ?>
        </ul>
        <div id="tickets" class="tab-content active">
            <div id="tickets-content">Загрузка...</div>
        </div>
        <div id="users" class="tab-content">
            <div id="users-content">Загрузка...</div>
        </div>
        <?php if (user_can($user_id, 'manage_options')): ?>
            <div id="settings" class="tab-content">
                <div id="settings-content">Загрузка...</div>
            </div>
        <?php endif; ?>
    </div>
    <div id="ticket-modal" style="display:none;">
        <div id="ticket-modal-content"></div>
    </div>
    <?php
    return ob_get_clean();
}

// Вспомогательная функция для проверки роли модератора
function steam_auth_has_moderator_role($user) {
    $mods_config = get_option('steam_auth_mods_config', []);
    $moderator_roles = $mods_config['selected_roles'] ?? [];
    return array_intersect($user->roles, $moderator_roles) ? true : false;
}