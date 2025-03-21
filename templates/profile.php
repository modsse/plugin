<form method="post" id="profile-form" data-type="profile">
    <?php wp_nonce_field('steam_auth_nonce', 'nonce'); ?>
    <h2>Настройки профиля</h2>
    <table class="steam-auth-table">
        <thead>
            <tr>
                <th>Поле</th>
                <th>Название</th>
                <th>Отображать</th>
                <th>Редактировать</th>
                <th>Иконка</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (empty($profile_settings['fields'])) {
                echo '<tr><td colspan="6">Ошибка: основные поля не загружены</td></tr>';
            } else {
                foreach ($profile_settings['fields'] as $field => $settings): ?>
                    <tr data-field-key="<?php echo esc_attr($field); ?>">
                        <td><?php echo esc_html($field); ?></td>
                        <td><input type="text" name="fields[<?php echo esc_attr($field); ?>][label]" value="<?php echo esc_attr($settings['label'] ?? ''); ?>"></td>
                        <td><input type="checkbox" name="fields[<?php echo esc_attr($field); ?>][visible]" <?php checked($settings['visible']); ?>></td>
                        <td><input type="checkbox" name="fields[<?php echo esc_attr($field); ?>][editable]" <?php checked($settings['editable']); ?>></td>
                        <td>
                            <select name="fields[<?php echo esc_attr($field); ?>][icon]" class="icon-select" data-selected="<?php echo esc_attr($settings['icon']); ?>">
                                <option value="">Выберите иконку</option>
                            </select>
                        </td>
                        <td><button type="button" class="remove-general-field button">Удалить</button></td>
                    </tr>
                <?php endforeach;
            } ?>
        </tbody>
    </table>

    <h2>Кастомные поля</h2>
    <table class="steam-auth-table" id="custom-fields">
        <thead>
            <tr>
                <th>Имя поля</th>
                <th>Название</th>
                <th>Тип</th>
                <th>Отображать</th>
                <th>Редактировать</th>
                <th>Иконка</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (empty($profile_settings['custom_fields'])) {
                echo '<tr><td colspan="7">Кастомных полей пока нет</td></tr>';
            } else {
                foreach ($profile_settings['custom_fields'] as $field => $settings): ?>
                    <tr data-field-key="<?php echo esc_attr($field); ?>">
                        <td><input type="text" name="custom_fields[<?php echo esc_attr($field); ?>][name]" value="<?php echo esc_attr($field); ?>" required></td>
                        <td><input type="text" name="custom_fields[<?php echo esc_attr($field); ?>][label]" value="<?php echo esc_attr($settings['label'] ?? ''); ?>"></td>
                        <td>
                            <select name="custom_fields[<?php echo esc_attr($field); ?>][type]">
                                <option value="text" <?php selected($settings['type'], 'text'); ?>>Текст</option>
                                <option value="email" <?php selected($settings['type'], 'email'); ?>>Email</option>
                                <option value="number" <?php selected($settings['type'], 'number'); ?>>Число</option>
                                <option value="textarea" <?php selected($settings['type'], 'textarea'); ?>>Текстовая область</option>
                            </select>
                        </td>
                        <td><input type="checkbox" name="custom_fields[<?php echo esc_attr($field); ?>][visible]" <?php checked($settings['visible'] ?? false); ?>></td>
                        <td><input type="checkbox" name="custom_fields[<?php echo esc_attr($field); ?>][editable]" <?php checked($settings['visible'] ?? false); ?>></td>
                        <td>
                            <select name="custom_fields[<?php echo esc_attr($field); ?>][icon]" class="icon-select" data-selected="<?php echo esc_attr($settings['icon'] ?? ''); ?>">
                                <option value="">Выберите иконку</option>
                            </select>
                        </td>
                        <td><button type="button" class="remove-field button">Удалить</button></td>
                    </tr>
                <?php endforeach;
            } ?>
        </tbody>
    </table>
    <button type="button" id="add-custom-field" class="button">Добавить поле</button>
    <p class="submit">
    <input type="submit" class="button-primary" value="Сохранить изменения" />
    <span class="loading-spinner" id="save-spinner" style="display: none;"></span>
</p>
</form>