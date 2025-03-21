<?php
$users = get_users(['fields' => ['ID', 'user_login']]);
$roles = wp_roles()->get_names();
$all_messages = get_option('steam_auth_messages', []);
?>

<h2>Сообщения</h2>
<form id="steam-messages-form" method="post">
    <table class="wp-list-table widefat fixed"> <!-- Убрал .striped -->
        <thead>
            <tr>
                <th scope="col" style="width: 30px;"><input type="checkbox" id="select-all-messages"></th>
                <th scope="col">Пользователь</th>
                <th scope="col">Роль</th>
                <th scope="col">Категория</th>
                <th scope="col">Заголовок</th>
                <th scope="col">Содержимое</th>
                <th scope="col">Дата</th>
                <th scope="col">Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($all_messages)): ?>
                <tr>
                    <td colspan="8">Сообщений нет</td>
                </tr>
            <?php else: ?>
                <?php foreach ($all_messages as $message): ?>
                    <tr>
                        <td><input type="checkbox" name="message_ids[]" value="<?php echo esc_attr($message['id']); ?>" class="message-checkbox"></td>
                        <td><?php echo $message['user_id'] == 0 ? 'Все' : esc_html(get_userdata($message['user_id'])->user_login ?? 'Неизвестный'); ?></td>
                        <td><?php echo esc_html($message['role'] ?: 'Все роли'); ?></td>
                        <td><?php echo esc_html($message['category']); ?></td>
                        <td><?php echo esc_html($message['title']); ?></td>
                        <td><?php echo esc_html(substr($message['content'], 0, 50)) . (strlen($message['content']) > 50 ? '...' : ''); ?></td>
                        <td><?php echo esc_html($message['date']); ?></td>
                        <td>
                            <a href="#" class="delete-message" data-message-id="<?php echo esc_attr($message['id']); ?>">Удалить</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <p class="submit">
        <button type="button" id="bulk-delete-messages" class="button button-primary" disabled>Удалить выбранные</button>
    </p>
</form>

<h3>Отправить новое сообщение</h3>
<form id="steam-send-message-form">
    <table class="form-table">
        <tr>
            <th><label for="message-user-id">Пользователь</label></th>
            <td>
                <select name="user_id" id="message-user-id" class="regular-text">
                    <option value="0">Все пользователи</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->user_login); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="message-role">Роль</label></th>
            <td>
                <select name="role" id="message-role" class="regular-text">
                    <option value="">Без роли</option>
                    <?php foreach ($roles as $role_key => $role_name): ?>
                        <option value="<?php echo esc_attr($role_key); ?>"><?php echo esc_html($role_name); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Если выбрана роль, сообщение отправится всем пользователям с этой ролью.</p>
            </td>
        </tr>
        <tr>
            <th><label for="message-category">Категория</label></th>
            <td>
                <select name="category" id="message-category" class="regular-text">
                    <option value="general">Общее</option>
                    <option value="news">Новости</option>
                    <option value="alert">Оповещения</option>
                    <option value="personal">Личные</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label for="message-title">Заголовок</label></th>
            <td><input type="text" name="title" id="message-title" class="regular-text" value=""></td>
        </tr>
        <tr>
            <th><label for="message-content">Содержимое</label></th>
            <td><textarea name="content" id="message-content" rows="5" class="large-text"></textarea></td>
        </tr>
        <tr>
            <th><label for="discord-embed-template">Шаблон Discord Embed</label></th>
            <td>
                <select name="discord_embed_template" id="discord-embed-template" class="regular-text">
                    <option value="">Без шаблона</option>
                    <option value="success">Success (Зелёный)</option>
                    <option value="error">Error (Красный)</option>
                    <option value="warning">Warning (Жёлтый)</option>
                    <option value="info">Info (Синий)</option>
                    <?php 
                    $custom_templates = get_option('steam_auth_discord_custom_templates', []);
                    foreach ($custom_templates as $key => $template): ?>
                        <option value="custom_<?php echo esc_attr($key); ?>"><?php echo esc_html($template['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
    </table>
    <p class="submit">
    <input type="submit" class="button-primary" value="Отправить сообщение" />
    <span class="loading-spinner" id="save-spinner" style="display: none;"></span>
</p>
</form>