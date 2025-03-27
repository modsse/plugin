<?php
defined('ABSPATH') or die('No direct access allowed');

$category_filter = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10; // Количество сообщений на странице
$messages_data = get_user_messages($user_id, $category_filter, $per_page, $page);
$messages = $messages_data['messages'];
$total_pages = $messages_data['pages'];
$categories = get_message_categories();
$allow_user_delete = get_option('steam_auth_allow_user_delete_messages', 0); // Проверяем настройку
?>

<div class="widget widget-messages">
    <h3>Сообщения (<?php echo $messages_data['total']; ?>)</h3>
    <div class="category-filter">
        <a href="?tab=messages" class="<?php echo empty($category_filter) ? 'active' : ''; ?>">Все</a>
        <?php foreach ($categories as $category): ?>
            <a href="?tab=messages&category=<?php echo urlencode($category); ?>" 
               class="<?php echo $category_filter === $category ? 'active' : ''; ?>">
                <?php echo esc_html(ucfirst($category)); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php if (empty($messages)): ?>
        <p>Нет сообщений.</p>
    <?php else: ?>
        <ul class="message-list">
            <?php foreach ($messages as $message): ?>
                <li class="message-item <?php echo $message['is_read'] ? 'read' : 'unread'; ?>">
                    <div class="message-header">
                        <span class="message-title"><?php echo esc_html($message['title']); ?></span>
                        <span class="message-date"><?php echo esc_html($message['date']); ?></span>
                    </div>
                    <div class="message-content"><?php echo nl2br(esc_html($message['content'])); ?></div>
                    <div class="message-actions">
                        <?php if (!$message['is_read']): ?>
                            <a href="#" class="mark-read" data-message-id="<?php echo esc_attr($message['id']); ?>">Прочитать</a>
                        <?php elseif ($allow_user_delete): ?>
                            <a href="#" class="delete-message" data-message-id="<?php echo esc_attr($message['id']); ?>">Удалить</a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?tab=messages<?php echo $category_filter ? '&category=' . urlencode($category_filter) : ''; ?>&page=<?php echo $i; ?>" 
                       class="<?php echo $page === $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
        <?php if ($allow_user_delete): ?>
            <div class="message-actions">
                <a href="#" class="delete-all-read">Удалить прочитанные</a>
                <a href="#" class="delete-all">Удалить все</a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>