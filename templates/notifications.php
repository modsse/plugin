<?php
// templates/notifications.php
defined('ABSPATH') or die('No direct access allowed');

if (!current_user_can('manage_options')) {
    wp_die('Недостаточно прав');
}
?>

<div class="steam-auth-wrap">
    <h2>Уведомления для пользователей</h2>
    <form id="notification-form" method="post" action="">
        <table class="form-table">
            <tr>
                <th><label for="notification_title">Заголовок</label></th>
                <td><input type="text" name="title" id="notification_title" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="notification_content">Содержимое</label></th>
                <td><textarea name="content" id="notification_content" rows="5" class="large-text" required></textarea></td>
            </tr>
            <tr>
                <th><label for="notification_bg_color">Цвет фона</label></th>
                <td><input type="color" name="bg_color" id="notification_bg_color" value="#3447003"></td>
            </tr>
            <tr>
                <th><label for="notification_text_color">Цвет текста</label></th>
                <td><input type="color" name="text_color" id="notification_text_color" value="#ffffff"></td>
            </tr>
            <tr>
                <th><label for="notification_role">Роль (опционально)</label></th>
                <td>
                    <select name="role" id="notification_role">
                        <option value="">Всем</option>
                        <?php
                        global $wp_roles;
                        foreach ($wp_roles->roles as $role => $details) {
                            echo '<option value="' . esc_attr($role) . '">' . esc_html($details['name']) . '</option>';
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="notification_user_id">Пользователь (опционально)</label></th>
                <td><input type="number" name="user_id" id="notification_user_id" value="0" min="0" placeholder="ID пользователя (0 = всем)"></td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" class="button button-primary" value="Отправить уведомление">
            <span id="save-spinner" class="spinner" style="display: none;"></span>
        </p>
    </form>

    <h3>Список уведомлений</h3>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Заголовок</th>
                <th>Содержимое</th>
                <th>Цвет фона</th>
                <th>Цвет текста</th>
                <th>Роль</th>
                <th>Пользователь</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($notifications as $notification): ?>
                <tr>
                    <td><?php echo esc_html($notification->id); ?></td>
                    <td><?php echo esc_html($notification->title); ?></td>
                    <td><?php echo wp_kses_post($notification->content); ?></td>
                    <td><span style="display:inline-block;width:20px;height:20px;background:<?php echo esc_attr($notification->bg_color); ?>;"></span></td>
                    <td><span style="display:inline-block;width:20px;height:20px;background:<?php echo esc_attr($notification->text_color); ?>;"></span></td>
                    <td><?php echo esc_html($notification->role ?: 'Всем'); ?></td>
                    <td><?php echo $notification->user_id ? esc_html($notification->user_id) : 'Всем'; ?></td>
                    <td><?php echo $notification->is_active ? 'Активно' : 'Отключено'; ?></td>
                    <td>
                        <a href="#" class="toggle-notification" data-id="<?php echo $notification->id; ?>" data-active="<?php echo $notification->is_active; ?>">
                            <?php echo $notification->is_active ? 'Отключить' : 'Включить'; ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>