<?php
// tabs/messages-tab.php
defined('ABSPATH') or die('No direct access allowed');

$category_filter = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
$messages = get_user_messages($user_id, $category_filter);
?>

<div class="widget widget-messages">
    <h3>Сообщения (<?php echo count($messages); ?>)</h3>
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
                        <?php else: ?>
                            <a href="#" class="delete-message" data-message-id="<?php echo esc_attr($message['id']); ?>" onclick="return confirm('Удалить это сообщение?');">Удалить</a>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="message-actions">
            <a href="#" class="delete-all-read" onclick="return confirm('Удалить все прочитанные сообщения?');">Удалить прочитанные</a>
            <a href="#" class="delete-all" onclick="return confirm('Удалить все сообщения?');">Удалить все</a>
        </div>
    <?php endif; ?>
</div>