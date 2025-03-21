<?php
// tabs/profile-tab.php
defined('ABSPATH') or die('No direct access allowed');

$is_editing = isset($_GET['edit']) && $_GET['edit'] === 'true';
?>

<div class="widget widget-profile <?php echo $is_editing ? 'hidden' : 'visible'; ?>">
    <h3>Профиль</h3>
    <?php
    foreach ($profile_settings['fields'] as $field => $settings) {
        if ($settings['visible']) {
            $value = $field === 'display_name' ? $display_name :
                     ($field === 'steam_id' ? $steam_id :
                     ($field === 'user_email' ? $user_email :
                     ($field === 'steam_profile' ? '<a href="' . esc_url($steam_profile) . '" target="_blank">Перейти</a>' : '')));
            $prefix = get_icon_prefix($settings['icon']);
            ?>
            <div class="profile-field">
                <span class="field-icon"><i class="<?php echo esc_attr($prefix . ' ' . $settings['icon']); ?>"></i></span>
                <span class="field-label"><?php echo esc_html($settings['label']); ?>:</span>
                <span class="field-value"><?php echo $value ?: 'Не указано'; ?></span>
            </div>
            <?php
        }
    }
    ?>
    <a href="#profile&edit=true" class="steam-edit-btn" data-edit="true">Редактировать</a>
</div>

<div class="widget widget-discord <?php echo $is_editing ? 'hidden' : 'visible'; ?>">
    <h3>Discord</h3>
    <?php if ($discord_id && !isset($discord_unlink_requests[$user_id])): ?>
        <div class="profile-field">
            <span class="field-icon"><i class="fab fa-discord"></i></span>
            <span class="field-label">Имя:</span>
            <span class="field-value"><?php echo esc_html($discord_username); ?></span>
        </div>
        <div class="profile-field">
            <span class="field-icon"><i class="fas fa-bell"></i></span>
            <span class="field-label">Уведомления:</span>
            <span class="field-value">
                <input type="checkbox" id="discord_notifications" name="discord_notifications" <?php checked(get_user_meta($user_id, 'discord_notifications_enabled', true) !== '0'); ?> data-user-id="<?php echo esc_attr($user_id); ?>">
                <label for="discord_notifications">Включить</label>
            </span>
        </div>
        <a href="<?php echo wp_nonce_url(home_url('/discord-unlink'), 'discord_unlink_nonce'); ?>" class="steam-discord-unlink-btn">Отвязать</a>
    <?php elseif (isset($discord_unlink_requests[$user_id])): ?>
        <p>Ожидает отвязки (<?php echo esc_html($discord_username); ?>)</p>
    <?php else: ?>
        <p>Discord не привязан</p>
        <a href="<?php echo wp_nonce_url(home_url('/discord-link'), 'discord_link_nonce'); ?>" class="steam-discord-btn">Привязать</a>
    <?php endif; ?>
</div>

<div class="widget widget-edit <?php echo $is_editing ? 'visible' : 'hidden'; ?>">
    <h3>Редактировать профиль</h3>
    <form method="post" id="profile-edit-form">
        <?php wp_nonce_field('steam_edit_profile_nonce', 'steam_edit_profile_action'); ?>
        <?php
        foreach ($profile_settings['fields'] as $field => $settings) {
            if ($settings['editable']) {
                $value = $field === 'user_email' ? $user_email : $display_name;
                $type = $field === 'user_email' ? 'email' : 'text';
                ?>
                <p>
                    <label for="<?php echo $field; ?>"><?php echo esc_html($settings['label']); ?>:</label>
                    <input type="<?php echo $type; ?>" name="<?php echo $field; ?>" id="<?php echo $field; ?>" value="<?php echo esc_attr($value); ?>" required>
                </p>
                <?php
            }
        }
        foreach ($profile_settings['custom_fields'] as $field => $settings) {
            if ($settings['editable']) {
                $value = get_user_meta($user_id, $field, true);
                ?>
                <p>
                    <label for="<?php echo $field; ?>"><?php echo esc_html($settings['label']); ?>:</label>
                    <?php if ($settings['type'] === 'textarea'): ?>
                        <textarea name="<?php echo $field; ?>" id="<?php echo $field; ?>"><?php echo esc_textarea($value); ?></textarea>
                    <?php else: ?>
                        <input type="<?php echo $settings['type']; ?>" name="<?php echo $field; ?>" id="<?php echo $field; ?>" value="<?php echo esc_attr($value); ?>">
                    <?php endif; ?>
                </p>
                <?php
            }
        }
        ?>
        <div class="profile-actions">
            <input type="submit" value="Сохранить" class="steam-save-btn">
            <a href="#profile" class="steam-cancel-btn" data-edit="false">Отмена</a>
        </div>
    </form>
</div>