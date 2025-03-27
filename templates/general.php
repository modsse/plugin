<h1>Настройки плагина</h1>

<!-- Навигация по вкладкам -->
<ul class="tabs-nav">
    <li class="tab-link active" data-tab="main" data-tooltip="Основные настройки">Основные</li>
    <li class="tab-link" data-tab="discord" data-tooltip="Настройки Discord">Настройки Discord</li>
    <li class="tab-link" data-tab="steam" data-tooltip="Настройки Steam">Steam</li>
    <li class="tab-link" data-tab="moderators" data-tooltip="Настройки модераторов">Модераторы</li>
</ul>

<!-- Форма с вкладками -->
<form method="post" id="general-form" data-type="general">
    <?php wp_nonce_field('steam_auth_nonce', 'nonce'); ?>

    <!-- Вкладка "Основные" -->
    <div id="main" class="tab-content active">
        <table class="form-table">
            <tr>
                <th><label for="bot_url_qery">Bot url для запросов</label></th>
                <td><input type="text" name="bot_url_qery" id="bot_url_qery" value="<?php echo esc_attr($bot_url); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="steam_default_role">Роль по умолчанию</label></th>
                <td>
                    <select name="steam_default_role" id="steam_default_role">
                        <?php wp_dropdown_roles($default_role); ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="steam_auth_debug">Режим отладки</label></th>
                <td>
                    <label class="toggle-switch" data-tooltip="Включить режим отладки">
                        <input type="checkbox" name="steam_auth_debug" id="steam_auth_debug" <?php checked($debug_mode, true); ?>>
                        <span class="slider"></span>
                    </label>
                </td>
            </tr>
            <tr>
                <th><label for="steam_auth_admin_key">Ключ админ-входа</label></th>
                <td>
                    <input type="text" name="steam_auth_admin_key" id="steam_auth_admin_key" value="<?php echo esc_attr(get_option('steam_auth_admin_key', 'secret123')); ?>" class="regular-text">
                    <p class="description">Секретный ключ для доступа к /auth</p>
                </td>
            </tr>
            <tr>
                <th><label for="steam_auth_bot_api_key">API-ключ для Telegram бота</label></th>
                <td>
                    <input type="text" name="steam_auth_bot_api_key" id="steam_auth_bot_api_key" value="<?php echo esc_attr(get_option('steam_auth_bot_api_key', '')); ?>" class="regular-text">
                    <p class="description">Ключ для безопасного взаимодействия с Telegram ботом</p>
                </td>
            </tr>
            <tr>
                <th><label for="steam_auth_log_limit">Лимит логов</label></th>
                <td><input type="number" name="steam_auth_log_limit" id="steam_auth_log_limit" value="<?php echo esc_attr(get_option('steam_auth_log_limit', 50)); ?>" min="10" max="1000" class="small-text"></td>
            </tr>
            <tr>
                <th><label for="steam_auth_custom_login_enabled">Кастомная страница входа</label></th>
                <td>
                    <label class="toggle-switch" data-tooltip="Использовать кастомную страницу входа">
                        <input type="checkbox" name="steam_auth_custom_login_enabled" id="steam_auth_custom_login_enabled" <?php checked(get_option('steam_auth_custom_login_enabled', false), true); ?>>
                        <span class="slider"></span>
                    </label>
                    <p class="description">Включить кастомную страницу входа вместо стандартной</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Вкладка "Настройки Discord" -->
    <div id="discord" class="tab-content">
        <table class="form-table">
            <tr>
                <th><label for="steam_auth_discord_guild_id">Discord Guild ID</label></th>
                <td><input type="text" name="steam_auth_discord_guild_id" id="steam_auth_discord_guild_id" value="<?php echo esc_attr($guild_id); ?>" class="regular-text" pattern="[0-9]*" placeholder="Введите ID сервера Discord"></td>
            </tr>
            <tr>
                <th><label for="steam_auth_discord_client_id">Discord Client ID</label></th>
                <td>
                    <input type="text" name="steam_auth_discord_client_id" id="steam_auth_discord_client_id" value="<?php echo esc_attr(get_option('steam_auth_discord_client_id', '')); ?>" class="regular-text">
                    <p class="description">Client ID из Discord Developer Portal</p>
                </td>
            </tr>
            <tr>
                <th><label for="steam_auth_discord_client_secret">Discord Client Secret</label></th>
                <td>
                    <input type="text" name="steam_auth_discord_client_secret" id="steam_auth_discord_client_secret" value="<?php echo esc_attr(get_option('steam_auth_discord_client_secret', '')); ?>" class="regular-text">
                    <p class="description">Client Secret из Discord Developer Portal</p>
                </td>
            </tr>
            <tr>
                <th><label for="steam_auth_discord_bot_token">Discord Bot Token</label></th>
                <td>
                    <input type="text" name="steam_auth_discord_bot_token" id="steam_auth_discord_bot_token" value="<?php echo esc_attr(get_option('steam_auth_discord_bot_token', '')); ?>" class="regular-text">
                    <p class="description">Токен бота из Discord Developer Portal для отправки уведомлений</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Вкладка "Steam" -->
    <div id="steam" class="tab-content">
        <table class="form-table">
            <tr>
                <th><label for="steam_api_key">Steam API Key</label></th>
                <td><input type="text" name="steam_api_key" id="steam_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text"></td>
            </tr>
        </table>
    </div>

    <!-- Вкладка "Модераторы" -->
    <div id="moderators" class="tab-content">
        <table class="form-table">
            <tr>
                <th><label>Роли модераторов</label></th>
                <td>
                    <?php
                    $moders_config = get_option('steam_auth_mods_config', []);
                    $selected_roles = $moders_config['selected_roles'] ?? [];
                    $roles = wp_roles()->get_names();
                    foreach ($roles as $role_key => $role_name):
                        if ($role_key === 'administrator') continue; // Пропускаем администратора
                    ?>
                        <label>
                            <input type="checkbox" name="moderator_roles[]" value="<?php echo esc_attr($role_key); ?>" <?php checked(in_array($role_key, $selected_roles)); ?>>
                            <?php echo esc_html($role_name); ?>
                        </label><br>
                    <?php endforeach; ?>
                    <p class="description">Выберите роли, которые будут считаться модераторами.</p>
                </td>
            </tr>
            <tr>
                <th><label>Права модераторов</label></th>
                <td>
                    <label><input type="checkbox" name="mod_can_manage_tickets" <?php checked($moders_config['can_manage_tickets'] ?? true); ?>> Управление тикетами</label><br>
                    <label><input type="checkbox" name="mod_can_view_users" <?php checked($moders_config['can_view_users'] ?? false); ?>> Просмотр пользователей</label><br>
                    <p class="description">Определите, какие действия доступны модераторам.</p>
                </td>
            </tr>
        </table>
    </div>

    <!-- Кнопка сохранения -->
    <p class="submit">
    <input type="submit" class="button-primary" value="Сохранить изменения" />
    <span class="loading-spinner" id="save-spinner" style="display: none;"></span>
</p>
</form>