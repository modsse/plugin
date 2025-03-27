<!-- tabs/tickets-tab.php -->
<?php
defined('ABSPATH') or die('No direct access allowed');

$user_id = get_current_user_id();
$per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

global $wpdb;
$tickets_table = $wpdb->prefix . 'steam_auth_tickets';
$topics_table = $wpdb->prefix . 'steam_auth_ticket_topics';
$days = get_option('steam_auth_ticket_auto_delete_days', 0); // Получаем настройку срока удаления

$total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tickets_table WHERE user_id = %d", $user_id));
$tickets = $wpdb->get_results($wpdb->prepare(
    "SELECT t.*, tt.name as topic_name 
     FROM $tickets_table t 
     LEFT JOIN $topics_table tt ON t.topic_id = tt.id 
     WHERE t.user_id = %d 
     ORDER BY t.updated_at DESC 
     LIMIT %d OFFSET %d",
    $user_id,
    $per_page,
    $offset
), ARRAY_A);

$topics = $wpdb->get_results("SELECT id, name FROM $topics_table WHERE is_active = 1", ARRAY_A);
?>

<div class="widget widget-tickets visible">
    <h3>Мои тикеты (<?php echo $total; ?>)</h3>
    
    <!-- Форма создания тикета (сворачиваемая) -->
    <div class="ticket-form accordion">
        <button type="button" class="accordion-toggle">Создать новый тикет <span class="accordion-icon">▼</span></button>
        <div class="accordion-content" style="display: none;">
            <form id="create-ticket-form" enctype="multipart/form-data">
                <p>
                    <label for="ticket-topic">Тема:</label>
                    <select name="topic_id" id="ticket-topic" required>
                        <option value="">Выберите тему</option>
                        <?php foreach ($topics as $topic): ?>
                            <option value="<?php echo esc_attr($topic['id']); ?>"><?php echo esc_html($topic['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </p>
                <p>
                    <label for="ticket-title">Заголовок:</label>
                    <input type="text" name="title" id="ticket-title" required>
                </p>
                <p>
                    <label for="ticket-content">Сообщение:</label>
                    <?php
                    wp_editor('', 'ticket-content', [
                        'textarea_name' => 'content',
                        'media_buttons' => true,
                        'teeny' => true,
                        'quicktags' => true,
                        'textarea_rows' => 5
                    ]);
                    ?>
                </p>
                <p>
                    <label for="ticket-attachment">Прикрепить файл:</label>
                    <input type="file" name="attachment" id="ticket-attachment">
                </p>
                <p>
                    <input type="submit" value="Отправить тикет" class="steam-save-btn">
                    <span class="loading-spinner" id="ticket-spinner" style="display: none;"></span>
                </p>
            </form>
        </div>
    </div>

    <!-- Список тикетов -->
    <?php if (empty($tickets)): ?>
        <p>Тикетов нет</p>
    <?php else: ?>
        <ul class="ticket-list">
            <?php foreach ($tickets as $ticket): ?>
                <li class="ticket-item <?php echo $ticket['status']; ?>">
                    <div class="ticket-header">
                        <span class="ticket-id">#<?php echo esc_html($ticket['id']); ?></span>
                        <span class="ticket-topic"><?php echo esc_html($ticket['topic_name']); ?></span>
                        <span class="ticket-title"><?php echo esc_html($ticket['title']); ?></span>
                        <span class="ticket-status">
                            <?php echo esc_html($ticket['status'] === 'open' ? 'Открыт' : ($ticket['status'] === 'in_progress' ? 'В обработке' : 'Закрыт')); ?>
                        </span>
                        <span class="ticket-date"><?php echo esc_html($ticket['updated_at']); ?></span>
                    </div>
                    <div class="ticket-actions">
                        <a href="#" class="view-ticket" data-ticket-id="<?php echo esc_attr($ticket['id']); ?>">Просмотреть</a>
                        <?php if ($ticket['status'] === 'closed' && $days > 0): ?>
                            <?php
                            $delete_date = date('Y-m-d H:i:s', strtotime($ticket['updated_at'] . " +$days days"));
                            ?>
                            <span class="ticket-delete-notice">Будет удалён: <?php echo esc_html($delete_date); ?></span>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <!-- Пагинация -->
    <?php if (ceil($total / $per_page) > 1): ?>
        <div class="pagination">
            <?php
            echo paginate_links([
                'base' => add_query_arg('page', '%#%'),
                'format' => '',
                'total' => ceil($total / $per_page),
                'current' => $page,
                'prev_text' => '« Назад',
                'next_text' => 'Вперед »',
            ]);
            ?>
        </div>
    <?php endif; ?>
</div>

<!-- Модальное окно для просмотра тикета -->
<div id="ticket-modal" class="steam-modal" style="display: none;">
    <div class="steam-modal-content">
        <span class="steam-modal-close">×</span>
        <div id="ticket-modal-content"></div>
    </div>
</div>