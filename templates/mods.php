<h2>Настройка модов</h2>
<form id="steam-auth-mods-form">
    <table class="wp-list-table widefat fixed">
    <thead>
    <tr>
        <th>Название роли</th>
        <th>Считать модом</th>
        <th>Версия</th>
        <th>Картинка</th>
        <th>URL документации</th>
    </tr>
</thead>
<tbody>
    <?php foreach ($discord_roles as $role_id => $role) : ?>
        <tr>
            <td><?php echo esc_html($role['name']); ?></td>
            <td>
                <label class="toggle-switch" data-tooltip="Считать эту роль модератором">
                    <input type="checkbox" name="mods[<?php echo esc_attr($role_id); ?>][is_mod]" 
                           <?php echo isset($selected_mod_roles[$role_id]) ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>
            </td>
            <td>
                <input type="text" name="mods[<?php echo esc_attr($role_id); ?>][version]" 
                       value="<?php echo esc_attr($mods_config[$role_id]['version'] ?? ''); ?>">
            </td>
            <td>
                <input type="text" name="mods[<?php echo esc_attr($role_id); ?>][image]" 
                       value="<?php echo esc_attr($mods_config[$role_id]['image'] ?? ''); ?>" 
                       class="image-url" readonly>
                <button type="button" class="upload-image-button button">Выбрать изображение</button>
            </td>
            <td>
                <input type="url" name="mods[<?php echo esc_attr($role_id); ?>][documentation_url]" 
                       value="<?php echo esc_attr($mods_config[$role_id]['documentation_url'] ?? ''); ?>" 
                       placeholder="https://example.com/docs">
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
    </table>
    <p><input type="submit" class="button button-primary" value="Сохранить" data-tooltip="Сохранить настройки модов"></p>
</form>