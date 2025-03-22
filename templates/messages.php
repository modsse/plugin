<?php
global $wpdb;
$users = get_users(['fields' => ['ID', 'user_login']]);
$roles = wp_roles()->get_names();
$table_name = $wpdb->prefix . 'steam_auth_messages';

// Настройки пагинации
$per_page = 20; // Количество сообщений на странице
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($page - 1) * $per_page;

// Получаем общее количество сообщений
$total_messages = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
$total_pages = ceil($total_messages / $per_page);

// Получаем сообщения для текущей страницы
$all_messages = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY date DESC LIMIT %d OFFSET %d",
        $per_page,
        $offset
    ),
    ARRAY_A
);

// Получаем все уникальные категории из базы данных
$categories = $wpdb->get_col("SELECT DISTINCT category FROM $table_name WHERE category != '' ORDER BY category");
?>

<h2>Сообщения (<?php echo $total_messages; ?>)</h2>
<form id="steam-messages-form" method="post">
    <table class="wp-list-table widefat fixed">
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

    <?php if ($total_pages > 1): ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php
                $pagination_args = [
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'total' => $total_pages,
                    'current' => $page,
                    'prev_text' => __('&laquo; Назад'),
                    'next_text' => __('Вперед &raquo;'),
                ];
                echo paginate_links($pagination_args);
                ?>
            </div>
        </div>
    <?php endif; ?>
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
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo esc_attr($category); ?>"><?php echo esc_html(ucfirst($category)); ?></option>
                    <?php endforeach; ?>
                    <option value="new_category">Добавить новую категорию...</option>
                </select>
                <div id="new-category-field" style="display: none; margin-top: 10px;">
                    <input type="text" id="new-category-input" class="regular-text" placeholder="Введите название новой категории">
                    <button type="button" id="add-new-category" class="button">Добавить</button>
                </div>
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

<h3>Управление категориями</h3>
<div id="category-management">
    <table class="wp-list-table widefat fixed">
        <thead>
            <tr>
                <th scope="col">Название</th>
                <th scope="col">Действия</th>
            </tr>
        </thead>
        <tbody id="category-list">
            <?php foreach ($categories as $category): ?>
                <tr data-category="<?php echo esc_attr($category); ?>">
                    <td><?php echo esc_html(ucfirst($category)); ?></td>
                    <td>
                        <a href="#" class="edit-category" data-category="<?php echo esc_attr($category); ?>">Редактировать</a> |
                        <a href="#" class="delete-category" data-category="<?php echo esc_attr($category); ?>">Удалить</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>