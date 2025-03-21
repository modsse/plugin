<?php
$discord_embed_settings = get_option('steam_auth_discord_embed_settings', [
    'color' => '3447003',
    'fields' => [
        'title' => true,
        'title_emoji' => '',
        'description' => true,
        'description_emoji' => '',
        'timestamp' => true,
        'footer' => true,
        'footer_icon' => 'https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_small.png',
        'footer_emoji' => '',
        'author' => true,
        'author_icon' => home_url('/favicon.ico'),
        'author_emoji' => '',
        'custom' => []
    ]
]);
$custom_templates = get_option('steam_auth_discord_custom_templates', []);
?>

<form method="post" id="discord-notifications-form" data-type="discord-notifications">
    <?php wp_nonce_field('steam_auth_nonce', 'nonce'); ?>
    <h2>Настройки уведомлений Discord</h2>
    <table class="form-table">
        <tr>
            <th><label for="discord_embed_template">Шаблон Embed</label></th>
            <td>
                <select name="discord_embed_template" id="discord_embed_template">
                    <option value="">Выберите шаблон</option>
                    <option value="success">Успех</option>
                    <option value="error">Ошибка</option>
                    <option value="warning">Предупреждение</option>
                    <option value="info">Информация</option>
                    <?php foreach ($custom_templates as $key => $template) : ?>
                        <option value="custom_<?php echo esc_attr($key); ?>"><?php echo esc_html($template['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Выберите шаблон для автоматической настройки полей.</p>
            </td>
        </tr>
        <tr>
            <th><label for="custom_template_name">Сохранить как шаблон</label></th>
            <td>
                <input type="text" name="custom_template_name" id="custom_template_name" placeholder="Название нового шаблона">
                <button type="button" id="save-custom-template" class="button">Сохранить шаблон</button>
                <p class="description">Введите название для сохранения текущих настроек.</p>
            </td>
        </tr>
        <tr>
            <th><label for="discord_embed_color">Цвет полоски</label></th>
            <td>
                <input type="color" name="discord_embed_color_hex" id="discord_embed_color_hex" value="#<?php echo dechex((int)$discord_embed_settings['color']); ?>">
                <input type="hidden" name="discord_embed_color" id="discord_embed_color" value="<?php echo esc_attr($discord_embed_settings['color']); ?>">
                <p class="description">Выберите цвет для Discord Embed.</p>
            </td>
        </tr>
        <tr>
            <th>Поля Embed</th>
            <td>
                <table class="form-table embed-fields-table" style="box-shadow: none; margin: 0;">
                    <tbody>
                        <tr>
                            <td style="width: 50px;">
                                <label class="toggle-switch" data-tooltip="Включить заголовок">
                                    <input type="checkbox" name="discord_embed_fields[title]" <?php checked($discord_embed_settings['fields']['title']); ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <td style="width: 150px;">Заголовок</td>
                            <td>
                                <input type="text" name="discord_embed_fields[title_emoji]" value="<?php echo esc_attr($discord_embed_settings['fields']['title_emoji'] ?? ''); ?>" placeholder="Эмодзи (🚀)" class="emoji-input">
                                <button type="button" class="emoji-picker button" data-target="title_emoji">🙂</button>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label class="toggle-switch" data-tooltip="Включить описание">
                                    <input type="checkbox" name="discord_embed_fields[description]" <?php checked($discord_embed_settings['fields']['description']); ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <td>Описание</td>
                            <td>
                                <input type="text" name="discord_embed_fields[description_emoji]" value="<?php echo esc_attr($discord_embed_settings['fields']['description_emoji'] ?? ''); ?>" placeholder="Эмодзи (⭐)" class="emoji-input">
                                <button type="button" class="emoji-picker button" data-target="description_emoji">🙂</button>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label class="toggle-switch" data-tooltip="Включить временную метку">
                                    <input type="checkbox" name="discord_embed_fields[timestamp]" <?php checked($discord_embed_settings['fields']['timestamp']); ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <td>Временная метка</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td>
                                <label class="toggle-switch" data-tooltip="Включить подпись">
                                    <input type="checkbox" name="discord_embed_fields[footer]" <?php checked($discord_embed_settings['fields']['footer']); ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <td>Подпись</td>
                            <td>
                                <input type="text" name="discord_embed_fields[footer_icon]" value="<?php echo esc_attr($discord_embed_settings['fields']['footer_icon']); ?>" placeholder="URL иконки" style="width: 70%;">
                                <input type="text" name="discord_embed_fields[footer_emoji]" value="<?php echo esc_attr($discord_embed_settings['fields']['footer_emoji'] ?? ''); ?>" placeholder="Эмодзи (🎉)" class="emoji-input">
                                <button type="button" class="emoji-picker button" data-target="footer_emoji">🙂</button>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <label class="toggle-switch" data-tooltip="Включить автора">
                                    <input type="checkbox" name="discord_embed_fields[author]" <?php checked($discord_embed_settings['fields']['author']); ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                            <td>Автор</td>
                            <td>
                                <input type="text" name="discord_embed_fields[author_icon]" value="<?php echo esc_attr($discord_embed_settings['fields']['author_icon']); ?>" placeholder="URL иконки" style="width: 70%;">
                                <input type="text" name="discord_embed_fields[author_emoji]" value="<?php echo esc_attr($discord_embed_settings['fields']['author_emoji'] ?? ''); ?>" placeholder="Эмодзи (👤)" class="emoji-input">
                                <button type="button" class="emoji-picker button" data-target="author_emoji">🙂</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </td>
        </tr>
        <tr>
            <th>Кастомные поля</th>
            <td>
                <div id="custom-embed-fields">
                    <?php foreach ($discord_embed_settings['fields']['custom'] as $index => $field): ?>
                        <div class="custom-field" data-index="<?php echo $index; ?>">
                            <input type="text" name="discord_embed_fields[custom][<?php echo $index; ?>][name]" value="<?php echo esc_attr($field['name']); ?>" placeholder="Название">
                            <input type="text" name="discord_embed_fields[custom][<?php echo $index; ?>][value]" value="<?php echo esc_attr($field['value']); ?>" placeholder="Значение">
                            <input type="text" name="discord_embed_fields[custom][<?php echo $index; ?>][emoji]" value="<?php echo esc_attr($field['emoji'] ?? ''); ?>" placeholder="Эмодзи (🌟)" class="emoji-input">
                            <button type="button" class="emoji-picker button" data-target="custom[<?php echo $index; ?>][emoji]">🙂</button>
                            <button type="button" class="remove-custom-field button">Удалить</button>
                        </div>
                    <?php endforeach; ?>
                    <p><button type="button" id="add-custom-field" class="button">Добавить поле</button></p>
                </div>
            </td>
        </tr>
    </table>
    <p class="submit">
    <input type="submit" class="button-primary" value="Сохранить изменения" data-tooltip="Сохранить все изменения" />
    <span class="loading-spinner" id="save-spinner" style="display: none;"></span>
    <button type="button" id="discord-embed-preview" class="button" data-tooltip="Показать предпросмотр Embed">Предпросмотр</button>
    <button type="button" id="discord-embed-test" class="button" data-tooltip="Отправить тестовое сообщение">Отправить тестовое</button>
    <span class="loading-spinner" id="test-spinner" style="display: none;"></span>
</p>
<div id="discord-embed-preview-container" style="margin-top: 15px; display: none;">
    <h3>Предпросмотр Embed <button id="discord-embed-preview-close" class="button" style="float: right;">Скрыть</button></h3>
    <div id="discord-embed-preview-content"></div>
</div>
    <div id="emoji-picker-modal" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 15px; border: 1px solid #ccc; z-index: 1000;">
        <h3>Выберите эмодзи</h3>
        <div id="emoji-list" style="max-height: 180px; overflow-y: auto;"></div>
        <button type="button" id="emoji-picker-close" class="button" style="margin-top: 8px;">Закрыть</button>
    </div>
    <div id="emoji-picker-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 999;"></div>
    <div id="custom-template-list">
        <h3>Кастомные шаблоны</h3>
        <?php
        $custom_templates = get_option('steam_auth_discord_custom_templates', []);
        foreach ($custom_templates as $key => $template) {
            echo '<div class="template-option" data-key="custom_' . esc_attr($key) . '">';
            echo '<span>' . esc_html($template['name']) . '</span>';
            echo '<button type="button" class="remove-custom-template button" data-key="' . esc_attr($key) . '">Удалить</button>';
            echo '</div>';
        }
        ?>
    </div>
</form>