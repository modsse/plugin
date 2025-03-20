<?php // templates/tickets.php
if (!defined('ABSPATH')) exit;
?>

<div class="steam-auth-tab-content">
    <h2>Управление тикетами</h2>
    <?php if (empty($tickets)) : ?>
        <p>Тикетов пока нет.</p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Заголовок</th>
                    <th>Автор</th>
                    <th>Статус</th>
                    <th>Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tickets as $ticket) : 
                    $user_id = get_post_meta($ticket->ID, 'user_id', true);
                    $user = get_userdata($user_id);
                    $status = get_post_meta($ticket->ID, 'ticket_status', true) ?: 'open';
                ?>
                <tr>
                    <td><?php echo $ticket->ID; ?></td>
                    <td><?php echo esc_html($ticket->post_title); ?></td>
                    <td><?php echo $user ? esc_html($user->display_name) : 'Неизвестно'; ?></td>
                    <td><?php echo esc_html($status); ?></td>
                    <td>
                        <a href="<?php echo admin_url('post.php?post=' . $ticket->ID . '&action=edit'); ?>">Редактировать</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>