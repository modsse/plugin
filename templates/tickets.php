<!-- tabs/tickets-tab.php (для админки) -->
<?php
global $wpdb;
$tickets_table = $wpdb->prefix . 'steam_auth_tickets';
$topics_table = $wpdb->prefix . 'steam_auth_ticket_topics';
$per_page = 20;
$page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($page - 1) * $per_page;

$total = $wpdb->get_var("SELECT COUNT(*) FROM $tickets_table");
$tickets = $wpdb->get_results($wpdb->prepare(
    "SELECT t.*, tt.name as topic_name, u.user_login 
     FROM $tickets_table t 
     LEFT JOIN $topics_table tt ON t.topic_id = tt.id 
     LEFT JOIN {$wpdb->users} u ON t.user_id = u.ID 
     ORDER BY t.updated_at DESC 
     LIMIT %d OFFSET %d",
    $per_page,
    $offset
), ARRAY_A);

$topics = $wpdb->get_results("SELECT * FROM $topics_table ORDER BY name", ARRAY_A);
?>

<h2>Тикеты (<?php echo $total; ?>)</h2>
<table class="wp-list-table widefat fixed">
    <thead>
        <tr>
            <th>ID</th>
            <th>Пользователь</th>
            <th>Тема</th>
            <th>Заголовок</th>
            <th>Статус</th>
            <th>Дата обновления</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($tickets)): ?>
            <tr><td colspan="7">Тикетов нет</td></tr>
        <?php else: ?>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?php echo esc_html($ticket['id']); ?></td>
                    <td><?php echo esc_html($ticket['user_login']); ?></td>
                    <td><?php echo esc_html($ticket['topic_name']); ?></td>
                    <td><?php echo esc_html($ticket['title']); ?></td>
                    <td>
                        <select class="ticket-status" data-ticket-id="<?php echo esc_attr($ticket['id']); ?>">
                            <option value="open" <?php selected($ticket['status'], 'open'); ?>>Открыт</option>
                            <option value="in_progress" <?php selected($ticket['status'], 'in_progress'); ?>>В обработке</option>
                            <option value="closed" <?php selected($ticket['status'], 'closed'); ?>>Закрыт</option>
                        </select>
                    </td>
                    <td><?php echo esc_html($ticket['updated_at']); ?></td>
                    <td>
                        <a href="#" class="view-ticket" data-ticket-id="<?php echo esc_attr($ticket['id']); ?>">Просмотреть</a>
                        <a href="#" class="delete-ticket" data-ticket-id="<?php echo esc_attr($ticket['id']); ?>">Удалить</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if (ceil($total / $per_page) > 1): ?>
    <div class="tablenav">
        <div class="tablenav-pages">
            <?php
            echo paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'total' => ceil($total / $per_page),
                'current' => $page,
                'prev_text' => '« Назад',
                'next_text' => 'Вперед »',
            ]);
            ?>
        </div>
    </div>
<?php endif; ?>

<h3>Управление темами тикетов</h3>
<form id="ticket-topics-form">
<?php wp_nonce_field('steam_auth_ticket_settings', 'steam_auth_ticket_nonce'); ?>
    <table class="wp-list-table widefat fixed">
        <thead>
            <tr>
                <th>Название</th>
                <th>Описание</th>
                <th>Активна</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody id="ticket-topics-list">
            <?php foreach ($topics as $topic): ?>
                <tr data-topic-id="<?php echo esc_attr($topic['id']); ?>">
                    <td><input type="text" value="<?php echo esc_attr($topic['name']); ?>" name="topics[<?php echo esc_attr($topic['id']); ?>][name]"></td>
                    <td><input type="text" value="<?php echo esc_attr($topic['description']); ?>" name="topics[<?php echo esc_attr($topic['id']); ?>][description]"></td>
                    <td><input type="checkbox" <?php checked($topic['is_active'], 1); ?> name="topics[<?php echo esc_attr($topic['id']); ?>][is_active]"></td>
                    <td><a href="#" class="delete-topic">Удалить</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p>
        <button type="button" id="add-ticket-topic" class="button">Добавить тему</button>
        <input type="submit" class="button button-primary" value="Сохранить темы">
    </p>
</form>

<h3>Настройки тикетов</h3>
<form id="ticket-settings-form">
    <?php wp_nonce_field('steam_auth_ticket_settings', 'steam_auth_ticket_nonce'); ?>
    <table class="form-table">
        <tr>
            <th><label for="steam_auth_tickets_enabled">Включить систему тикетов</label></th>
            <td>
                <input type="checkbox" name="steam_auth_tickets_enabled" id="steam_auth_tickets_enabled" <?php checked(get_option('steam_auth_tickets_enabled', true), true); ?>>
            </td>
        </tr>
        <tr>
            <th><label for="steam_auth_ticket_max_file_size">Максимальный размер файла (МБ)</label></th>
            <td>
                <input type="number" name="steam_auth_ticket_max_file_size" id="steam_auth_ticket_max_file_size" value="<?php echo esc_attr(get_option('steam_auth_ticket_max_file_size', 5)); ?>" min="1" max="50">
            </td>
        </tr>
        <tr>
            <th><label for="steam_auth_ticket_allowed_file_types">Разрешённые типы файлов</label></th>
            <td>
                <input type="text" name="steam_auth_ticket_allowed_file_types" id="steam_auth_ticket_allowed_file_types" value="<?php echo esc_attr(get_option('steam_auth_ticket_allowed_file_types', 'jpg,png,pdf')); ?>" class="regular-text">
                <p class="description">Укажите расширения через запятую (например, jpg,png,pdf)</p>
            </td>
        </tr>
        <tr>
            <th><label for="steam_auth_ticket_auto_delete_days">Удалять закрытые тикеты через (дней)</label></th>
            <td>
                <input type="number" name="steam_auth_ticket_auto_delete_days" id="steam_auth_ticket_auto_delete_days" value="<?php echo esc_attr(get_option('steam_auth_ticket_auto_delete_days', 7)); ?>" min="1" max="365">
                <p class="description">Укажите, через сколько дней после закрытия тикеты будут автоматически удаляться. Оставьте пустым или 0, чтобы отключить.</p>
            </td>
        </tr>
    </table>
    <p class="submit">
        <input type="submit" class="button-primary" value="Сохранить настройки" />
        <span class="loading-spinner" id="ticket-settings-spinner" style="display: none;"></span>
    </p>
</form>

<div id="ticket-modal" class="ticket-modal" style="display: none;">
    <div class="ticket-modal-content">
        <span class="ticket-modal-close">X</span>
        <div id="ticket-modal-content"></div>
    </div>
</div>