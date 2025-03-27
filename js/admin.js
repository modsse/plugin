jQuery(document).ready(function($) {
    if (steamAuthAjax.debug) {
        console.log('Steam Auth JS: Начало выполнения');
        console.log('jQuery версия:', $.fn.jquery);
        console.log('Select2 доступен:', typeof $.fn.select2 !== 'undefined');
        console.log('steamAuthAjax:', steamAuthAjax);
    }

    let iconsData = [];
    const defaultIcons = ['fa-user', 'fa-steam', 'fa-envelope', 'fa-link', 'fa-phone', 'fa-home', 'fa-lock', 'fa-key', 'fa-cog', 'fa-circle'];
    let iconsLoaded = false;

    // Список популярных эмодзи
    const emojiList = [
        '🚀', '⭐', '🎉', '👤', '🌟', '✅', '❌', '⚠️', '🔥', '💡',
        '📢', '🔔', '🎮', '🏆', '🎯', '💾', '🔒', '🔓', '📩', '📅',
        '😀', '😂', '😍', '😢', '😡', '👍', '👎', '🙌', '👀', '✨',
        '⚡', '🌈', '☀️', '🌙', '⭐', '🌍', '💻', '📱', '🎧', '📸',
        '🍕', '🍔', '🍟', '🍎', '🍉', '☕', '🍺', '🍷', '🎁', '🎈',
        '🏃', '🚴', '🏋️', '⚽', '🏀', '🎸', '🎹', '🎤', '🎬', '🎨'
    ];

    const embedTemplates = {
        success: {
            color: '3066993',
            fields: {
                title: true,
                title_emoji: '✅',
                description: true,
                description_emoji: '🎉',
                timestamp: true,
                footer: true,
                footer_icon: 'https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_small.png',
                footer_emoji: '🌟',
                author: true,
                author_icon: steamAuthAjax.home_url + '/favicon.ico',
                author_emoji: '👤',
                custom: []
            }
        },
        error: {
            color: '15548997',
            fields: {
                title: true,
                title_emoji: '❌',
                description: true,
                description_emoji: '⚠️',
                timestamp: true,
                footer: true,
                footer_icon: 'https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_small.png',
                footer_emoji: '🔥',
                author: true,
                author_icon: steamAuthAjax.home_url + '/favicon.ico',
                author_emoji: '👤',
                custom: []
            }
        },
        warning: {
            color: '16776960',
            fields: {
                title: true,
                title_emoji: '⚠️',
                description: true,
                description_emoji: '📢',
                timestamp: true,
                footer: true,
                footer_icon: 'https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_small.png',
                footer_emoji: '🔔',
                author: true,
                author_icon: steamAuthAjax.home_url + '/favicon.ico',
                author_emoji: '👤',
                custom: []
            }
        },
        info: {
            color: '3447003',
            fields: {
                title: true,
                title_emoji: 'ℹ️',
                description: true,
                description_emoji: '📩',
                timestamp: true,
                footer: true,
                footer_icon: 'https://steamcdn-a.akamaihd.net/steamcommunity/public/images/steamworks_docs/english/sits_small.png',
                footer_emoji: '💡',
                author: true,
                author_icon: steamAuthAjax.home_url + '/favicon.ico',
                author_emoji: '👤',
                custom: []
            }
        }
    };

    let customTemplates = steamAuthAjax.customTemplates || {};

    $(document).on('change', '#discord_embed_template', function() {
        const template = $(this).val();
        if (steamAuthAjax.debug) console.log('Выбран шаблон:', template);
        if (!template) return;

        let settings;
        if (template.startsWith('custom_')) {
            const customKey = template.replace('custom_', '');
            settings = customTemplates[customKey];
        } else {
            settings = embedTemplates[template];
        }
        if (!settings) return;

        const form = $('#discord-notifications-form');
        $('#discord_embed_color').val(settings.color);
        $('#discord_embed_color_hex').val('#' + parseInt(settings.color).toString(16).padStart(6, '0'));
        form.find('input[name="discord_embed_fields[title]"]').prop('checked', settings.fields.title);
        form.find('input[name="discord_embed_fields[title_emoji]"]').val(settings.fields.title_emoji);
        form.find('input[name="discord_embed_fields[description]"]').prop('checked', settings.fields.description);
        form.find('input[name="discord_embed_fields[description_emoji]"]').val(settings.fields.description_emoji);
        form.find('input[name="discord_embed_fields[timestamp]"]').prop('checked', settings.fields.timestamp);
        form.find('input[name="discord_embed_fields[footer]"]').prop('checked', settings.fields.footer);
        form.find('input[name="discord_embed_fields[footer_icon]"]').val(settings.fields.footer_icon);
        form.find('input[name="discord_embed_fields[footer_emoji]"]').val(settings.fields.footer_emoji);
        form.find('input[name="discord_embed_fields[author]"]').prop('checked', settings.fields.author);
        form.find('input[name="discord_embed_fields[author_icon]"]').val(settings.fields.author_icon);
        form.find('input[name="discord_embed_fields[author_emoji]"]').val(settings.fields.author_emoji);

        $('#custom-embed-fields').empty();
        settings.fields.custom.forEach((field, index) => {
            const fieldHtml = `
                <div class="custom-field" data-index="${index}">
                    <input type="text" name="discord_embed_fields[custom][${index}][name]" value="${field.name || ''}" placeholder="Название поля">
                    <input type="text" name="discord_embed_fields[custom][${index}][value]" value="${field.value || ''}" placeholder="Значение">
                    <input type="text" name="discord_embed_fields[custom][${index}][emoji]" value="${field.emoji || ''}" placeholder="Эмодзи (напр., 🌟)" class="emoji-input">
                    <button type="button" class="emoji-picker button" data-target="custom[${index}][emoji]">🙂</button>
                    <button type="button" class="remove-custom-field button">Удалить</button>
                </div>`;
            $('#custom-embed-fields').prepend(fieldHtml);
        });

        $('#discord-embed-preview').trigger('click');
    });

    $(document).on('click', '#save-custom-template', function(e) {
        e.preventDefault();
        const templateName = $('#custom_template_name').val().trim();
        if (!templateName) {
            showNotification('Введите название шаблона', 'error');
            return;
        }
    
        const form = $('#discord-notifications-form');
        const color = form.find('#discord_embed_color').val() || '3447003';
        const fields = {
            title: form.find('input[name="discord_embed_fields[title]"]').is(':checked'),
            title_emoji: form.find('input[name="discord_embed_fields[title_emoji]"]').val(),
            description: form.find('input[name="discord_embed_fields[description]"]').is(':checked'),
            description_emoji: form.find('input[name="discord_embed_fields[description_emoji]"]').val(),
            timestamp: form.find('input[name="discord_embed_fields[timestamp]"]').is(':checked'),
            footer: form.find('input[name="discord_embed_fields[footer]"]').is(':checked'),
            footer_icon: form.find('input[name="discord_embed_fields[footer_icon]"]').val(),
            footer_emoji: form.find('input[name="discord_embed_fields[footer_emoji]"]').val(),
            author: form.find('input[name="discord_embed_fields[author]"]').is(':checked'),
            author_icon: form.find('input[name="discord_embed_fields[author_icon]"]').val(),
            author_emoji: form.find('input[name="discord_embed_fields[author_emoji]"]').val(),
            custom: []
        };
    
        form.find('.custom-field').each(function() {
            const name = $(this).find('input[name*="[name]"]').val();
            const value = $(this).find('input[name*="[value]"]').val();
            const emoji = $(this).find('input[name*="[emoji]"]').val();
            if (name && value) {
                fields.custom.push({ name: name, value: value, emoji: emoji });
            }
        });
    
        const templateData = {
            name: templateName,
            color: color,
            fields: fields
        };
    
        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'steam_auth_save_custom_template',
                nonce: steamAuthAjax.nonce,
                template: JSON.stringify(templateData)
            },
            success: function(response) {
                if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                if (response.success) {
                    customTemplates[response.data.key] = templateData;
                    const optionHtml = `
                        <div class="template-option" data-key="custom_${response.data.key}">
                            <select-option value="custom_${response.data.key}">${templateName}</select-option>
                            <button type="button" class="remove-custom-template button" data-key="${response.data.key}">Удалить</button>
                        </div>`;
                    $('#discord_embed_template').append(`<option value="custom_${response.data.key}">${templateName}</option>`);
                    $('#custom-template-list').append(optionHtml);
                    $('#custom_template_name').val('');
                    showNotification('Шаблон сохранён', 'success');
                } else {
                    showNotification('Ошибка: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка AJAX:', status, error);
                showNotification('Ошибка AJAX', 'error');
            }
        });
    });

    function loadIcons(callback) {
        if (iconsLoaded) {
            if (steamAuthAjax.debug) console.log('Иконки уже загружены:', iconsData);
            callback();
            return;
        }

        if (steamAuthAjax.debug) console.log('Попытка загрузки icons.json');
        $.getJSON('https://semods.art/wp-content/plugins/steam-auth/icons.json', function(data) {
            if (steamAuthAjax.debug) console.log('Ответ от icons.json:', data);
            if (data && typeof data === 'object' && !Array.isArray(data)) {
                iconsData = Object.keys(data).map(key => {
                    const style = data[key].styles[0];
                    const prefix = style === 'brands' ? 'fab' : 'fas';
                    return { id: `fa-${key}`, prefix: prefix, text: `fa-${key}` };
                });
                if (steamAuthAjax.debug) console.log('Иконки извлечены из объекта:', iconsData.length, 'элементов', iconsData.slice(0, 10));
            } else if (Array.isArray(data) && data.length > 0) {
                iconsData = data.map(icon => ({ id: icon, prefix: 'fas', text: icon }));
                if (steamAuthAjax.debug) console.log('Полный список иконок загружен:', iconsData.length, 'элементов', iconsData.slice(0, 10));
            } else {
                iconsData = defaultIcons.map(icon => ({ id: icon, prefix: 'fas', text: icon }));
                if (steamAuthAjax.debug) console.log('Данные из icons.json некорректны, используется запасной список:', iconsData);
            }
            iconsLoaded = true;
            callback();
        }).fail(function(xhr, status, error) {
            iconsData = defaultIcons.map(icon => ({ id: icon, prefix: 'fas', text: icon }));
            if (steamAuthAjax.debug) console.log('Ошибка загрузки icons.json:', status, error, 'используется запасной список:', iconsData);
            iconsLoaded = true;
            callback();
        });
    }

    function initIconSelect() {
        if (steamAuthAjax.debug) console.log('Инициализация Select2 для', $('.icon-select').length, 'элементов');
        if (!Array.isArray(iconsData) || iconsData.length === 0) {
            if (steamAuthAjax.debug) console.error('iconsData не массив или пустой перед инициализацией:', iconsData);
            iconsData = defaultIcons.map(icon => ({ id: icon, prefix: 'fas', text: icon }));
        }
        $('.icon-select').each(function() {
            const $select = $(this);
            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }
            $select.select2({
                width: '100%',
                placeholder: 'Выберите иконку',
                allowClear: true,
                templateResult: formatIcon,
                templateSelection: formatIcon,
                data: iconsData,
                matcher: function(params, data) {
                    if (!params.term || params.term.trim() === '') return data;
                    if (data.text.toLowerCase().indexOf(params.term.toLowerCase()) > -1) return data;
                    return null;
                }
            });
            const selected = $select.data('selected');
            if (selected) {
                if (steamAuthAjax.debug) console.log('Установка значения', selected, 'для', $select.attr('name'));
                $select.val(selected).trigger('change');
            }
        });
    }

    function formatIcon(state) {
        if (!state.id) return state.text;
        const prefix = state.prefix || 'fas';
        return $(`<span><i class="${prefix} ${state.id}"></i> ${state.text}</span>`);
    }

    function showNotification(message, type) {
        const $notice = $(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`);
        $('.wrap').prepend($notice);
        setTimeout(() => $notice.fadeOut(500, () => $notice.remove()), 5000);
    }

    $(document).on('submit', '#steam-send-message-form', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Отправка формы #steam-send-message-form');
        const $form = $(this);
        const $submitButton = $form.find('input[type="submit"]');
        const $saveSpinner = $('#save-spinner'); // Предполагаем, что спиннер называется save-spinner

        // Показываем спиннер и блокируем кнопку
        $saveSpinner.show();
        $submitButton.prop('disabled', true);

        const formData = $form.serialize();
        const data = formData + '&action=steam_auth_send_message&nonce=' + steamAuthAjax.nonce;
        if (steamAuthAjax.debug) console.log('Данные для отправки:', data);

        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                if (response.success) {
                    showNotification(response.data, 'success');
                    $('#message_title').val('');
                    $('#message_content').val('');
                    $('#user_id').val('0');
                    $('#role').val('');
                    $('#discord_embed_template').val('');
                    loadTab('messages'); // Перезагружаем вкладку после отправки
                } else {
                    showNotification('Ошибка: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка AJAX:', status, error);
                showNotification('Ошибка AJAX: ' + error, 'error');
            },
            complete: function() {
                // Скрываем спиннер и разблокируем кнопку
                $saveSpinner.hide();
                $submitButton.prop('disabled', false);
            }
        });
    });

    // Пагинация (перехватываем клики по ссылкам пагинации)
    $(document).on('click', '.tablenav-pages a', function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        loadTab('messages', href); // Передаём URL с параметром paged
    });

    // Показываем поле для новой категории
    $(document).on('change', '#message-category', function() {
        if ($(this).val() === 'new_category') {
            $('#new-category-field').show();
        } else {
            $('#new-category-field').hide();
        }
    });

    // Добавление новой категории
    $(document).on('click', '#add-new-category', function() {
        const newCategory = $('#new-category-input').val().trim();
        if (!newCategory) {
            showNotification('Введите название категории', 'error');
            return;
        }

        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'steam_auth_add_category',
                category: newCategory,
                nonce: steamAuthAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#message-category').append(`<option value="${newCategory}">${newCategory.charAt(0).toUpperCase() + newCategory.slice(1)}</option>`);
                    $('#message-category').val(newCategory);
                    $('#new-category-field').hide();
                    $('#new-category-input').val('');
                    $('#category-list').append(`
                        <tr data-category="${newCategory}">
                            <td>${newCategory.charAt(0).toUpperCase() + newCategory.slice(1)}</td>
                            <td>
                                <a href="#" class="edit-category" data-category="${newCategory}">Редактировать</a> |
                                <a href="#" class="delete-category" data-category="${newCategory}">Удалить</a>
                            </td>
                        </tr>
                    `);
                    showNotification('Категория добавлена', 'success');
                } else {
                    showNotification('Ошибка: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка AJAX:', status, error);
                showNotification('Ошибка AJAX', 'error');
            }
        });
    });

    // Редактирование категории
    $(document).on('click', '.edit-category', function(e) {
        e.preventDefault();
        const oldCategory = $(this).data('category');
        const newCategory = prompt('Введите новое название категории:', oldCategory);
        if (!newCategory || newCategory === oldCategory) return;

        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'steam_auth_edit_category',
                old_category: oldCategory,
                new_category: newCategory,
                nonce: steamAuthAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    $(`tr[data-category="${oldCategory}"]`).replaceWith(`
                        <tr data-category="${newCategory}">
                            <td>${newCategory.charAt(0).toUpperCase() + newCategory.slice(1)}</td>
                            <td>
                                <a href="#" class="edit-category" data-category="${newCategory}">Редактировать</a> |
                                <a href="#" class="delete-category" data-category="${newCategory}">Удалить</a>
                            </td>
                        </tr>
                    `);
                    $(`#message-category option[value="${oldCategory}"]`).replaceWith(`<option value="${newCategory}">${newCategory.charAt(0).toUpperCase() + newCategory.slice(1)}</option>`);
                    showNotification('Категория обновлена', 'success');
                } else {
                    showNotification('Ошибка: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка AJAX:', status, error);
                showNotification('Ошибка AJAX', 'error');
            }
        });
    });

    // Удаление категории
    $(document).on('click', '.delete-category', function(e) {
        e.preventDefault();
        const category = $(this).data('category');
        showConfirmModal('Вы уверены, что хотите удалить категорию "' + category + '"? Сообщения с этой категорией будут перемещены в "general".', function(confirmed) {
            if (confirmed) {
                $.ajax({
                    url: steamAuthAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'steam_auth_delete_category',
                        category: category,
                        nonce: steamAuthAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $(`tr[data-category="${category}"]`).remove();
                            $(`#message-category option[value="${category}"]`).remove();
                            showNotification('Категория удалена', 'success');
                        } else {
                            showNotification('Ошибка: ' + response.data, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Ошибка AJAX:', status, error);
                        showNotification('Ошибка AJAX', 'error');
                    }
                });
            }
        });
    });

    // Добавляем обработчики для массового удаления сообщений
    $(document).on('change', '#select-all-messages', function() {
        if (steamAuthAjax.debug) console.log('Чекбокс "Выбрать все" изменён:', this.checked);
        $('.message-checkbox').prop('checked', this.checked);
        toggleBulkDeleteButton();
    });

    $(document).on('change', '.message-checkbox', function() {
        if (steamAuthAjax.debug) console.log('Индивидуальный чекбокс изменён:', this.value, this.checked);
        toggleBulkDeleteButton();
        if (!this.checked) $('#select-all-messages').prop('checked', false);
    });

    $(document).on('click', '.delete-message', function(e) {
        e.preventDefault();
        const messageId = $(this).data('message-id');
        if (steamAuthAjax.debug) console.log('Удаление одного сообщения:', messageId);
        showConfirmModal('Вы уверены, что хотите удалить это сообщение?', function(confirmed) {
            if (confirmed) deleteMessages([messageId]);
        });
    });

    $(document).on('click', '#bulk-delete-messages', function(e) {
        e.preventDefault();
        const selectedIds = $('.message-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        if (steamAuthAjax.debug) console.log('Массовое удаление, выбрано сообщений:', selectedIds);
        if (selectedIds.length > 0) {
            showConfirmModal('Вы уверены, что хотите удалить выбранные сообщения?', function(confirmed) {
                if (confirmed) deleteMessages(selectedIds);
            });
        }
    });

    function toggleBulkDeleteButton() {
        const checkedCount = $('.message-checkbox:checked').length;
        if (steamAuthAjax.debug) console.log('Обновление состояния кнопки удаления, выбрано:', checkedCount);
        $('#bulk-delete-messages').prop('disabled', checkedCount === 0);
    }

    function deleteMessages(messageIds) {
        if (steamAuthAjax.debug) console.log('Отправка запроса на удаление сообщений:', messageIds);
        $.post(steamAuthAjax.ajaxurl, {
            action: 'steam_auth_bulk_delete_messages',
            message_ids: messageIds,
            nonce: steamAuthAjax.nonce
        }, function(response) {
            if (steamAuthAjax.debug) console.log('Ответ сервера на удаление:', response);
            if (response.success) {
                showNotification('Сообщения удалены', 'success');
                loadTab('messages'); // Перезагружаем вкладку
            } else {
                showNotification('Ошибка: ' + response.data, 'error');
            }
        }).fail(function(xhr, status, error) {
            console.error('Ошибка AJAX при удалении:', status, error);
            showNotification('Ошибка AJAX', 'error');
        });
    }

    $(document).on('submit', '#general-form', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Отправка формы #general-form');
        const $form = $(this);
        const $submitButton = $form.find('input[type="submit"]');
        const $saveSpinner = $('#save-spinner'); // Предполагаем единый ID спиннера

        // Показываем спиннер и блокируем кнопку
        $saveSpinner.show();
        $submitButton.prop('disabled', true);

        const data = $form.serialize() + '&action=steam_auth_save_settings&general=1';
        if (steamAuthAjax.debug) console.log('Данные для отправки:', data);
        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                if (response.success) {
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification('Ошибка: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка AJAX:', status, error);
                showNotification('Ошибка AJAX', 'error');
            },
            complete: function() {
                // Скрываем спиннер и разблокируем кнопку
                $saveSpinner.hide();
                $submitButton.prop('disabled', false);
            }
        });
    });

    $(document).on('submit', '#profile-form', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Отправка формы #profile-form');
        const $form = $(this);
        const $submitButton = $form.find('input[type="submit"]');
        const $saveSpinner = $('#save-spinner'); // Предполагаем единый ID спиннера

        // Показываем спиннер и блокируем кнопку
        $saveSpinner.show();
        $submitButton.prop('disabled', true);

        const data = $form.serialize() + '&action=steam_auth_save_settings&profile=1';
        if (steamAuthAjax.debug) console.log('Данные для отправки:', data);
        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    $.post(steamAuthAjax.ajaxurl, { action: 'steam_auth_admin_load_tab', tab: 'profile' }, function(response) {
                        $('#tab-content').html(response);
                        loadIcons(function() {
                            initIconSelect();
                        });
                    });
                } else {
                    showNotification('Ошибка: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка AJAX:', status, error);
                showNotification('Ошибка AJAX', 'error');
            },
            complete: function() {
                // Скрываем спиннер и разблокируем кнопку
                $saveSpinner.hide();
                $submitButton.prop('disabled', false);
            }
        });
    });

    $(document).on('submit', '#discord-notifications-form', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Отправка формы #discord-notifications-form');
        const $form = $(this);
        const $submitButton = $form.find('input[type="submit"]');
        const $saveSpinner = $('#save-spinner');

        // Показываем спиннер и блокируем кнопку
        $saveSpinner.show();
        $submitButton.prop('disabled', true);

        const data = $form.serialize() + '&action=steam_auth_save_settings&discord-notifications=1';
        if (steamAuthAjax.debug) console.log('Данные для отправки:', data);
        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                if (response.success) {
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification('Ошибка: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка AJAX:', status, error);
                showNotification('Ошибка AJAX', 'error');
            },
            complete: function() {
                // Скрываем спиннер и разблокируем кнопку
                $saveSpinner.hide();
                $submitButton.prop('disabled', false);
            }
        });
    });

    $(document).on('submit', '#steam-auth-mods-form', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Отправка формы #steam-auth-mods-form');
        const $form = $(this);
        const $submitButton = $form.find('input[type="submit"]');
        const $saveSpinner = $('#save-spinner'); // Предполагаем единый ID спиннера

        // Показываем спиннер и блокируем кнопку
        $saveSpinner.show();
        $submitButton.prop('disabled', true);

        const formData = $form.serializeArray(); // Получаем массив данных
        formData.push({ name: 'action', value: 'steam_auth_save_settings' });
        formData.push({ name: 'tab', value: 'mods' });
        formData.push({ name: 'nonce', value: steamAuthAjax.nonce });
        if (steamAuthAjax.debug) console.log('Данные для отправки:', formData);
    
        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                if (response.success) {
                    showNotification(response.data.message, 'success');
                } else {
                    showNotification('Ошибка: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка AJAX:', status, error);
                showNotification('Ошибка AJAX: ' + error, 'error');
            },
            complete: function() {
                // Скрываем спиннер и разблокируем кнопку
                $saveSpinner.hide();
                $submitButton.prop('disabled', false);
            }
        });
    });

    $(document).on('submit', '#steam-messages-settings-form', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Отправка формы #steam-messages-settings-form');
    
        const $form = $(this);
        const $submitButton = $form.find('input[type="submit"]');
        const $saveSpinner = $('#settings-spinner'); // Убедитесь, что этот ID существует в HTML
    
        // Показываем спиннер и блокируем кнопку
        $saveSpinner.show();
        $submitButton.prop('disabled', true);
    
        const formData = $form.serialize();
        const data = formData + '&action=steam_auth_save_messages_settings';
        if (steamAuthAjax.debug) console.log('Данные для отправки:', data);
    
        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                if (response.success) {
                    showNotification('Настройки сохранены', 'success');
                } else {
                    showNotification('Ошибка: ' + (response.data || 'Неизвестная ошибка'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка AJAX:', status, error, xhr.responseText);
                showNotification('Ошибка AJAX: ' + xhr.responseText, 'error');
            },
            complete: function() {
                // Скрываем спиннер и разблокируем кнопку
                $saveSpinner.hide();
                $submitButton.prop('disabled', false);
            }
        });
    });




    $(document).on('change', '#discord_embed_color_hex', function() {
        const hex = $(this).val().replace('#', '');
        const decimal = parseInt(hex, 16);
        $('#discord_embed_color').val(decimal);
        if (steamAuthAjax.debug) console.log(`Цвет изменён: HEX ${hex} -> Decimal ${decimal}`);
        $('#discord-embed-preview').trigger('click');
    });

    $(document).on('click', '#discord-embed-preview', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Клик по кнопке "Предпросмотр"');

        const form = $('#discord-notifications-form');
        const color = form.find('#discord_embed_color').val() || '3447003';
        const fields = {
            title: form.find('input[name="discord_embed_fields[title]"]').is(':checked'),
            title_emoji: form.find('input[name="discord_embed_fields[title_emoji]"]').val(),
            description: form.find('input[name="discord_embed_fields[description]"]').is(':checked'),
            description_emoji: form.find('input[name="discord_embed_fields[description_emoji]"]').val(),
            timestamp: form.find('input[name="discord_embed_fields[timestamp]"]').is(':checked'),
            footer: form.find('input[name="discord_embed_fields[footer]"]').is(':checked'),
            footer_icon: form.find('input[name="discord_embed_fields[footer_icon]"]').val(),
            footer_emoji: form.find('input[name="discord_embed_fields[footer_emoji]"]').val(),
            author: form.find('input[name="discord_embed_fields[author]"]').is(':checked'),
            author_icon: form.find('input[name="discord_embed_fields[author_icon]"]').val(),
            author_emoji: form.find('input[name="discord_embed_fields[author_emoji]"]').val(),
            custom: []
        };

        form.find('.custom-field').each(function() {
            const name = $(this).find('input[name*="[name]"]').val();
            const value = $(this).find('input[name*="[value]"]').val();
            const emoji = $(this).find('input[name*="[emoji]"]').val();
            if (name && value) {
                fields.custom.push({ name: name, value: value, emoji: emoji });
            }
        });

        const previewContent = $('#discord-embed-preview-content');
        let html = '';

        const hexColor = '#' + parseInt(color).toString(16).padStart(6, '0');
        html += `<div class="embed-color-bar" style="background-color: ${hexColor};"></div>`;
        html += '<div class="embed-inner">';

        if (fields.author) {
            html += '<div class="embed-author">';
            if (fields.author_icon) {
                html += `<img src="${fields.author_icon}" alt="Author Icon"> `;
            }
            html += `<span>${fields.author_emoji || ''} ${$('body').hasClass('wp-admin') ? 'Admin' : 'Steam Auth'}</span>`;
            html += '</div>';
        }

        if (fields.title) {
            html += `<div class="embed-title">${fields.title_emoji || ''} Тестовое сообщение</div>`;
        }

        if (fields.description) {
            html += `<div class="embed-description">${fields.description_emoji || ''} Это пример содержимого сообщения для предпросмотра.</div>`;
        }

        if (fields.custom.length > 0) {
            html += '<div class="embed-custom-fields">';
            fields.custom.forEach(field => {
                html += `<div class="embed-field">${field.emoji || ''} <strong>${field.name}</strong><br>${field.value}</div>`;
            });
            html += '</div>';
        }

        if (fields.timestamp) {
            const now = new Date().toISOString().replace('T', ' ').substring(0, 19);
            html += `<div class="embed-timestamp">${now}</div>`;
        }

        if (fields.footer) {
            html += '<div class="embed-footer">';
            if (fields.footer_icon) {
                html += `<img src="${fields.footer_icon}" alt="Footer Icon"> `;
            }
            html += `<span>${fields.footer_emoji || ''} Steam Auth Notification</span>`;
            html += '</div>';
        }

        html += '</div>';
        previewContent.html(html);
        $('#discord-embed-preview-container').show();

        if (steamAuthAjax.debug) console.log('Предпросмотр сформирован:', html);
    });

    $(document).on('click', '#discord-embed-preview-close', function() {
        $('#discord-embed-preview-container').hide();
    });

    $(document).on('click', '#add-custom-field', function() {
        const index = Date.now();
        const field = `
            <div class="custom-field" data-index="${index}">
                <input type="text" name="discord_embed_fields[custom][${index}][name]" placeholder="Название поля">
                <input type="text" name="discord_embed_fields[custom][${index}][value]" placeholder="Значение">
                <input type="text" name="discord_embed_fields[custom][${index}][emoji]" placeholder="Эмодзи (напр., 🌟)" class="emoji-input">
                <button type="button" class="emoji-picker button" data-target="custom[${index}][emoji]">🙂</button>
                <button type="button" class="remove-custom-field button">Удалить</button>
            </div>`;
        $('#custom-embed-fields').prepend(field);
        $('#discord-embed-preview').trigger('click');
    });

    $(document).on('click', '.remove-custom-field', function() {
        $(this).parent('.custom-field').remove();
        $('#discord-embed-preview').trigger('click');
    });

    $(document).on('input', '.emoji-input', function() {
        $('#discord-embed-preview').trigger('click');
    });

    $(document).on('click', '.emoji-picker', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Клик по кнопке выбора эмодзи');
        const target = $(this).data('target');
        const $modal = $('#emoji-picker-modal');
        const $list = $('#emoji-list');
        $list.empty();
        emojiList.forEach(emoji => {
            $list.append(`<span class="emoji-option" style="cursor: pointer; font-size: 24px; margin: 5px;" data-emoji="${emoji}">${emoji}</span>`);
        });
        $('#emoji-picker-overlay').css('display', 'block');
        $modal.css('display', 'block').data('target', target);
    });

    $(document).on('click', '.emoji-option', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Выбор эмодзи');
        const emoji = $(this).data('emoji');
        const target = $('#emoji-picker-modal').data('target');
        let $input;

        if (target.includes('custom')) {
            const match = target.match(/custom\[(\d+)\]\[emoji\]/);
            if (match && match[1]) {
                const index = match[1];
                $input = $(`input[name="discord_embed_fields[custom][${index}][emoji]"]`);
            } else {
                console.error('Не удалось извлечь индекс из target:', target);
            }
        } else {
            $input = $(`input[name="discord_embed_fields[${target}]"]`);
        }

        if ($input.length) {
            $input.val(emoji);
            $('#emoji-picker-modal').css('display', 'none');
            $('#emoji-picker-overlay').css('display', 'none');
            $('#discord-embed-preview').trigger('click');
        } else {
            console.error('Целевой input не найден для:', target);
        }
    });

    $(document).on('click', '#emoji-picker-close, #emoji-picker-overlay', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Клик по закрытию модального окна');
        $('#emoji-picker-modal').css('display', 'none');
        $('#emoji-picker-overlay').css('display', 'none');
    });

    $(document).on('click', '#discord-embed-test', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Клик по "Отправить тестовое"');
        const $button = $(this);
        const $testSpinner = $('#test-spinner');

        // Показываем спиннер и блокируем кнопку
        $testSpinner.show();
        $button.prop('disabled', true);

        const data = $('#discord-notifications-form').serialize() + '&action=steam_auth_test_discord_embed';
        $.post(steamAuthAjax.ajaxurl, data, function(response) {
            if (steamAuthAjax.debug) console.log('Ответ тестовой отправки:', response);
            showNotification(response.success ? 'Тестовое сообщение отправлено!' : 'Ошибка: ' + response.data, response.success ? 'success' : 'error');
        }).fail(function(xhr, status, error) {
            console.error('Ошибка AJAX:', status, error);
            showNotification('Ошибка AJAX', 'error');
        }).always(function() {
            // Скрываем спиннер и разблокируем кнопку
            $testSpinner.hide();
            $button.prop('disabled', false);
        });
    });

    $(document).on('steam_auth_tab_loaded', function(e, tab) {
        if (tab === 'profile') {
            loadIcons(function() {
                initIconSelect();
            });
        }
    });

    $(document).on('click', '#add-custom-field', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Клик по кнопке "Добавить поле"');
        const tbody = $('#custom-fields tbody');
        const fieldCount = tbody.find('tr').length; // Используем счётчик вместо timestamp для простоты
        const tempKey = `new_field_${fieldCount}`; // Временный ключ
        const row = `
            <tr data-field-key="${tempKey}">
                <td><input type="text" name="custom_fields[${tempKey}][name]" value="" placeholder="Имя поля" required></td>
                <td><input type="text" name="custom_fields[${tempKey}][label]" value="" placeholder="Название"></td>
                <td>
                    <select name="custom_fields[${tempKey}][type]">
                        <option value="text">Текст</option>
                        <option value="email">Email</option>
                        <option value="number">Число</option>
                        <option value="textarea">Текстовая область</option>
                    </select>
                </td>
                <td><input type="checkbox" name="custom_fields[${tempKey}][visible]"></td>
                <td><input type="checkbox" name="custom_fields[${tempKey}][editable]"></td>
                <td>
                    <select name="custom_fields[${tempKey}][icon]" class="icon-select" data-selected="">
                        <option value="">Выберите иконку</option>
                    </select>
                </td>
                <td><button type="button" class="remove-field">Удалить</button></td>
            </tr>`;
        tbody.append(row);
        loadIcons(function() {
            initIconSelect();
        });
    });

    $(document).on('click', '.remove-field', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Клик по кнопке "Удалить"');
        const $row = $(this).closest('tr');
        const fieldKey = $row.data('field-key');
    
        if (fieldKey && !fieldKey.startsWith('new_')) {
            $.ajax({
                url: steamAuthAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'steam_auth_remove_field',
                    nonce: steamAuthAjax.nonce,
                    field_key: fieldKey
                },
                success: function(response) {
                    if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                    if (response.success) {
                        $row.remove();
                        showNotification('Поле удалено', 'success');
                    } else {
                        showNotification('Ошибка: ' + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ошибка AJAX:', status, error);
                    showNotification('Ошибка AJAX', 'error');
                }
            });
        } else {
            $row.remove();
            showNotification('Новое поле удалено из формы', 'info');
        }
    });

    $(document).ajaxSuccess(function(event, xhr, settings) {
        if (typeof settings.data === 'string' && settings.data.indexOf('action=steam_auth_admin_load_tab') !== -1) {
            const tabMatch = settings.data.match(/tab=([^&]+)/);
            if (tabMatch && tabMatch[1]) {
                const tab = tabMatch[1];
                $(document).trigger('steam_auth_tab_loaded', [tab]);
            }
        }
    });

    if ($('#general-form').length) {
        loadIcons(function() {
            if (steamAuthAjax.debug) console.log('Иконки загружены для general, но Select2 не требуется');
        });
    }

    function showConfirmModal(message, callback) {
        const modal = document.getElementById('steam-confirm-modal');
        const messageEl = document.getElementById('steam-confirm-message');
        const yesBtn = document.getElementById('steam-confirm-yes');
        const noBtn = document.getElementById('steam-confirm-no');

        if (!modal) {
            console.error('Модальное окно не найдено в DOM');
            return;
        }

        messageEl.textContent = message;
        modal.style.display = 'flex';

        yesBtn.onclick = function() {
            modal.style.display = 'none';
            callback(true);
        };
        noBtn.onclick = function() {
            modal.style.display = 'none';
            callback(false);
        };
    }

    // Переключение вкладок для .tab-link
    $(document).on('click', '.tab-link', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Клик по вкладке .tab-link:', $(this).data('tab'));
    
        var tabId = $(this).data('tab');
    
        $('.tab-link').removeClass('active');
        $('.tab-content').removeClass('active');
    
        $(this).addClass('active');
        $('#' + tabId).addClass('active');
    
        if (steamAuthAjax.debug) console.log('Активная вкладка:', tabId);
    });

    const tabs = document.querySelectorAll('.nav-tab');
    const content = document.getElementById('tab-content');

    // Обновляем функцию loadTab для поддержки дополнительных параметров
    function loadTab(tab, url = null) {
        if (steamAuthAjax.debug) console.log('Загрузка вкладки админки:', tab, 'URL:', url);
        const $tabContent = $('#tab-content');
        $tabContent.html('<span class="loading-spinner"></span> Загрузка...');
    
        const data = {
            action: 'steam_auth_admin_load_tab',
            tab: tab,
            nonce: steamAuthAjax.nonce
        };
    
        $.post(url || steamAuthAjax.ajaxurl, data, function(response) {
            $tabContent.html(response);
            $(document).trigger('steam_auth_tab_loaded', [tab]);
            if (tab === 'tickets') {
                initTicketActions();
            }
        }).fail(function(xhr, status, error) {
            console.error('Ошибка AJAX:', status, error);
            $tabContent.html('Ошибка загрузки вкладки');
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            if (steamAuthAjax.debug) console.log('Клик по вкладке .nav-tab:', this.dataset.tab);
            tabs.forEach(t => t.classList.remove('nav-tab-active'));
            this.classList.add('nav-tab-active');
            loadTab(this.dataset.tab);
        });
    });

    $(document).on('click', '.steam-approve-unlink-discord', function() {
        const userId = $(this).data('user-id');
        showConfirmModal('Вы уверены, что хотите одобрить отвязку Discord для этого пользователя?', function(confirmed) {
            if (confirmed) {
                if (steamAuthAjax.debug) console.log('Попытка одобрения отвязки Discord для пользователя:', userId);
                $.post(steamAuthAjax.ajaxurl, {
                    action: 'steam_auth_approve_unlink_discord',
                    user_id: userId,
                    nonce: steamAuthAjax.nonce
                }, function(response) {
                    if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                    if (response.success) {
                        showNotification(response.data, 'success');
                        loadTab('discord-unlink');
                    } else {
                        showNotification('Ошибка: ' + response.data, 'error');
                    }
                }).fail(function(xhr, status, error) {
                    console.error('Ошибка AJAX:', status, error);
                    showNotification('Ошибка AJAX', 'error');
                });
            }
        });
    });

    $(document).on('click', '.steam-reject-unlink-discord', function() {
        const userId = $(this).data('user-id');
        showConfirmModal('Вы уверены, что хотите отклонить запрос на отвязку Discord?', function(confirmed) {
            if (confirmed) {
                if (steamAuthAjax.debug) console.log('Попытка отклонения отвязки Discord для пользователя:', userId);
                $.post(steamAuthAjax.ajaxurl, {
                    action: 'steam_auth_reject_unlink_discord',
                    user_id: userId,
                    nonce: steamAuthAjax.nonce
                }, function(response) {
                    if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                    if (response.success) {
                        showNotification(response.data, 'success');
                        loadTab('discord-unlink');
                    } else {
                        showNotification('Ошибка: ' + response.data, 'error');
                    }
                }).fail(function(xhr, status, error) {
                    console.error('Ошибка AJAX:', status, error);
                    showNotification('Ошибка AJAX', 'error');
                });
            }
        });
    });

    if (typeof steamAuthAjax === 'undefined') {
        console.error('steamAuthAjax не определён');
    } else {
        console.log('steamAuthAjax определён:', steamAuthAjax);
    }

    $(document).on('click', '.remove-custom-template', function(e) {
        e.preventDefault();
        const templateKey = $(this).data('key');
        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'steam_auth_remove_custom_template',
                nonce: steamAuthAjax.nonce,
                key: templateKey
            },
            success: function(response) {
                if (response.success) {
                    delete customTemplates[templateKey];
                    $(`#discord_embed_template option[value="custom_${templateKey}"]`).remove();
                    $(`.template-option[data-key="custom_${templateKey}"]`).remove();
                    showNotification('Шаблон удалён', 'success');
                } else {
                    showNotification('Ошибка: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка AJAX:', status, error);
                showNotification('Ошибка AJAX', 'error');
            }
        });
    });

    $(document).on('click', '.upload-image-button', function(e) {
        e.preventDefault();
        const button = $(this);
        const imageField = button.siblings('.image-url');
    
        // Проверяем доступность медиатеки WordPress
        if (typeof wp === 'undefined' || !wp.media) {
            alert('Медиатека WordPress не доступна. Убедитесь, что вы находитесь в админке WordPress.');
            return;
        }
    
        // Открываем медиатеку
        const mediaFrame = wp.media({
            title: 'Выберите изображение для мода',
            button: { text: 'Выбрать' },
            multiple: false,
            library: { type: 'image' } // Ограничиваем выбор только изображениями
        });
    
        // При выборе изображения
        mediaFrame.on('select', function() {
            const attachment = mediaFrame.state().get('selection').first().toJSON();
            imageField.val(attachment.url);
        });
    
        mediaFrame.open();
    });

    $(document).on('click', '.remove-general-field', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Клик по кнопке "Удалить общее поле"');
        const $row = $(this).closest('tr');
        const fieldKey = $row.data('field-key');
    
        showConfirmModal('Вы уверены, что хотите удалить это общее поле?', function(confirmed) {
            if (confirmed) {
                $.ajax({
                    url: steamAuthAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'steam_auth_remove_general_field',
                        nonce: steamAuthAjax.nonce,
                        field_key: fieldKey
                    },
                    success: function(response) {
                        if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                        if (response.success) {
                            $row.remove();
                            showNotification(response.data, 'success');
                        } else {
                            showNotification('Ошибка: ' + response.data, 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Ошибка AJAX:', status, error);
                        showNotification('Ошибка AJAX', 'error');
                    }
                });
            }
        });
    });

    // Обработка очистки логов
    $(document).on('click', '#clear-logs', function(e) {
        e.preventDefault();
        var $button = $(this);
        if (steamAuthAjax.debug) console.log('Клик по "Очистить логи"');

        showConfirmModal('Вы уверены, что хотите очистить все логи?', function(confirmed) {
            if (confirmed) {
                $button.prop('disabled', true);
                if (steamAuthAjax.debug) console.log('Подтверждение получено, отправка AJAX');

                $.ajax({
                    url: steamAuthAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'steam_auth_clear_logs',
                        nonce: steamAuthAjax.nonce
                    },
                    success: function(response) {
                        if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                        if (response.success) {
                            $('.steam-auth-logs').html('<p>Логов нет.</p>');
                            $('#steam-auth-notification')
                                .removeClass('error')
                                .addClass('success')
                                .html(response.data.message || 'Логи успешно очищены')
                                .slideDown(300)
                                .delay(3000)
                                .slideUp(300);
                        } else {
                            $('#steam-auth-notification')
                                .removeClass('success')
                                .addClass('error')
                                .html(response.data.message || 'Неизвестная ошибка')
                                .slideDown(300)
                                .delay(3000)
                                .slideUp(300);
                        }
                        $button.prop('disabled', false);
                    },
                    error: function(xhr, status, error) {
                        if (steamAuthAjax.debug) console.error('Ошибка AJAX:', status, error, xhr.responseText);
                        $('#steam-auth-notification')
                            .removeClass('success')
                            .addClass('error')
                            .html('Ошибка при очистке логов: ' + (xhr.responseText || error))
                            .slideDown(300)
                            .delay(3000)
                            .slideUp(300);
                        $button.prop('disabled', false);
                    }
                });
            } else {
                if (steamAuthAjax.debug) console.log('Очистка отменена пользователем');
            }
        });
    });

    // Тёмная тема
    function applyTheme(theme) {
        const $body = $('body');
        if (theme === 'dark') {
            $body.addClass('steam-auth-dark-theme');
            $('#theme-toggle').text('Светлая тема');
        } else {
            $body.removeClass('steam-auth-dark-theme');
            $('#theme-toggle').text('Тёмная тема');
        }
        localStorage.setItem('steamAuthTheme', theme);
        if (steamAuthAjax.debug) console.log('Тема применена:', theme);
    }

    // Инициализация темы при загрузке
    const savedTheme = localStorage.getItem('steamAuthTheme') || 'light';
    applyTheme(savedTheme);

    // Переключатель темы
    $(document).on('click', '#theme-toggle', function(e) {
        e.preventDefault();
        const currentTheme = localStorage.getItem('steamAuthTheme') || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        applyTheme(newTheme);
    });

    // js/admin.js (добавить в document.ready)
    $(document).on('submit', '#ticket-topics-form', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Отправка формы #ticket-topics-form');
        const $spinner = $('#ticket-settings-spinner');
        $spinner.show();
    
        const formData = $(this).serialize();
        const nonce = $(this).find('[name="nonce"]').val(); // Используем имя "nonce"
        if (steamAuthAjax.debug) console.log('Данные формы:', formData, 'Nonce:', nonce);
    
        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: formData + '&action=save_ticket_topics&nonce=' + nonce,
            success: function(response) {
                $spinner.hide();
                if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                if (response.success) {
                    showNotification('Темы сохранены', 'success');
                    loadTab('tickets');
                } else {
                    showNotification('Ошибка: ' + (response.data || 'Неизвестная ошибка'), 'error');
                }
            },
            error: function(xhr, status, error) {
                $spinner.hide();
                console.error('Ошибка AJAX:', status, error, xhr.responseText);
                showNotification('Ошибка AJAX: ' + xhr.responseText, 'error');
            }
        });
    });

    $(document).on('click', '#add-ticket-topic', function(e) {
        e.preventDefault(); // Добавляем, чтобы избежать нежелательного поведения
        if (steamAuthAjax.debug) console.log('Клик по #add-ticket-topic');
        const $tbody = $('#ticket-topics-list');
        const newRow = `
            <tr>
                <td><input type="text" name="topics[new_${Date.now()}][name]"></td>
                <td><input type="text" name="topics[new_${Date.now()}][description]"></td>
                <td><input type="checkbox" name="topics[new_${Date.now()}][is_active]" checked></td>
                <td><a href="#" class="delete-topic">Удалить</a></td>
            </tr>`;
        $tbody.append(newRow);
        if (steamAuthAjax.debug) console.log('Новая строка добавлена:', newRow);
    });

    $(document).on('click', '.delete-topic', function(e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    $(document).on('submit', '#ticket-settings-form', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Отправка формы #ticket-settings-form');
        const $spinner = $('#ticket-settings-spinner');
        $spinner.show();
    
        const formData = $(this).serialize();
        const nonce = $(this).find('[name="nonce"]').val(); // Используем имя "nonce"
        if (steamAuthAjax.debug) console.log('Данные формы:', formData, 'Nonce:', nonce);
    
        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: formData + '&action=save_ticket_settings&nonce=' + nonce,
            success: function(response) {
                $spinner.hide();
                if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                if (response.success) {
                    showNotification('Настройки сохранены', 'success');
                } else {
                    showNotification('Ошибка: ' + (response.data || 'Неизвестная ошибка'), 'error');
                }
            },
            error: function(xhr, status, error) {
                $spinner.hide();
                console.error('Ошибка AJAX:', status, error, xhr.responseText);
                showNotification('Ошибка AJAX: ' + xhr.responseText, 'error');
            }
        });
    });

    $(document).on('change', '.ticket-status', function() {
        const ticketId = $(this).data('ticket-id');
        const status = $(this).val();
    
        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'update_ticket_status',
                ticket_id: ticketId,
                status: status,
                nonce: steamAuthAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Статус обновлён', 'success');
                    // Если статус "closed" и есть настройка автоудаления, показываем дату удаления в строке тикета
                    if (status === 'closed' && steamAuthAjax.ticket_auto_delete_days > 0) {
                        const $row = $(`tr:has(select[data-ticket-id="${ticketId}"])`);
                        const updatedAt = new Date().toISOString(); // Предполагаем, что сервер обновляет updated_at
                        const deleteDate = new Date(new Date(updatedAt).getTime() + steamAuthAjax.ticket_auto_delete_days * 24 * 60 * 60 * 1000).toLocaleString();
                        $row.find('.ticket-actions').append(`<span class="ticket-delete-notice">Будет удалён: ${deleteDate}</span>`);
                    }
                } else {
                    showNotification('Ошибка: ' + (response.data || 'Неизвестная ошибка'), 'error');
                }
            },
            error: function() {
                showNotification('Ошибка AJAX', 'error');
            }
        });
    });

    $(document).on('click', '.view-ticket', function(e) {
        e.preventDefault();
        const ticketId = $(this).data('ticket-id');
        if (steamAuthAjax.debug) console.log('Просмотр тикета:', ticketId);
    
        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'admin_view_ticket',
                ticket_id: ticketId,
                nonce: steamAuthAjax.nonce
            },
            success: function(response) {
                $('#ticket-modal-content').html(response);
                $('#ticket-modal').show();
    
                if (typeof tinymce !== 'undefined' && $('#reply-content').length) {
                    tinymce.remove('#reply-content');
                    tinymce.init({
                        selector: '#reply-content',
                        height: 200,
                        menubar: false,
                        plugins: 'lists link image paste quickbars',
                        toolbar: 'undo redo | bold italic | bullist numlist | link image',
                        quickbars_selection_toolbar: 'bold italic | quicklink',
                        quickbars_insert_toolbar: false,
                        quicktags: true,
                        setup: function(editor) {
                            editor.on('init', function() {
                                if (steamAuthAjax.debug) console.log('TinyMCE инициализирован');
                            });
                        }
                    });
                }
                initAdminTicketModal(ticketId);
    
                // Добавляем отображение даты удаления, если тикет закрыт
                const $status = $('#ticket-modal-content p:contains("Статус:")');
                const statusText = $status.text();
                if (statusText.includes('Закрыт')) {
                    const days = steamAuthAjax.ticket_auto_delete_days || 0; // Предполагаем, что значение передаётся через steamAuthAjax
                    if (days > 0) {
                        const updatedAt = $('#ticket-modal-content p:contains("Обновлено:")').text().replace('Обновлено: ', '') || new Date().toISOString();
                        const deleteDate = new Date(new Date(updatedAt).getTime() + days * 24 * 60 * 60 * 1000).toLocaleString();
                        $status.after(`<p><strong>Будет удалён:</strong> ${deleteDate}</p>`);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка загрузки тикета:', status, error);
                showNotification('Ошибка загрузки тикета', 'error');
            }
        });
    });
    
    $(document).on('click', '.ticket-modal-close', function() {
        $('#ticket-modal').hide();
        if (typeof tinymce !== 'undefined') {
            tinymce.remove('#reply-content');
        }
    });

    $(document).on('click', function(event) {
        const $modal = $('#ticket-modal');
        if (event.target === $modal[0]) {
            $modal.hide();
        }
    });

    // Управление действиями с тикетами
    function initTicketActions() {
        // Поддержка как <a>, так и <button> для просмотра и удаления
        $('.view-ticket').off('click').on('click', function(e) {
            e.preventDefault();
            const ticketId = $(this).data('ticket-id');
            if (steamAuthAjax.debug) console.log('Просмотр тикета:', ticketId);
            $.post(steamAuthAjax.ajaxurl, {
                action: 'admin_view_ticket',
                ticket_id: ticketId,
                nonce: steamAuthAjax.nonce
            }, function(response) {
                $('#ticket-modal-content').html(response);
                $('#ticket-modal').show();
                initAdminTicketModal(ticketId);
            }).fail(function(xhr, status, error) {
                console.error('Ошибка загрузки тикета:', status, error);
                showNotification('Ошибка загрузки тикета', 'error');
            });
        });

        $('.delete-ticket').off('click').on('click', function(e) {
            e.preventDefault();
            const ticketId = $(this).data('ticket-id');
            showConfirmModal('Вы уверены, что хотите удалить тикет #' + ticketId + '?', function(confirmed) {
                if (confirmed) {
                    $.post(steamAuthAjax.ajaxurl, {
                        action: 'admin_delete_ticket',
                        ticket_id: ticketId,
                        nonce: steamAuthAjax.nonce
                    }, function(response) {
                        if (response.success) {
                            showNotification('Тикет удалён', 'success');
                            $(`tr:has([data-ticket-id="${ticketId}"])`).fadeOut(300, function() {
                                $(this).remove();
                                const totalMatch = $('h2').text().match(/\d+/);
                                if (totalMatch) {
                                    const total = parseInt(totalMatch[0]) - 1;
                                    $('h2').text(`Тикеты (${total})`);
                                }
                            });
                        } else {
                            showNotification('Ошибка: ' + response.data, 'error');
                        }
                    }).fail(function(xhr, status, error) {
                        console.error('Ошибка AJAX:', status, error);
                        showNotification('Ошибка AJAX', 'error');
                    });
                }
            });
        });

        $('.ticket-status').off('change').on('change', function() {
            const ticketId = $(this).data('ticket-id');
            const status = $(this).val();
            $.post(steamAuthAjax.ajaxurl, {
                action: 'update_ticket_status',
                ticket_id: ticketId,
                status: status,
                nonce: steamAuthAjax.nonce
            }, function(response) {
                if (response.success) {
                    showNotification('Статус обновлён', 'success');
                    if (status === 'closed' && steamAuthAjax.ticket_auto_delete_days > 0) {
                        const $row = $(`tr:has(select[data-ticket-id="${ticketId}"])`);
                        const updatedAt = new Date().toISOString();
                        const deleteDate = new Date(new Date(updatedAt).getTime() + steamAuthAjax.ticket_auto_delete_days * 24 * 60 * 60 * 1000).toLocaleString();
                        $row.find('.ticket-actions, td:last-child').append(`<span class="ticket-delete-notice">Будет удалён: ${deleteDate}</span>`);
                    }
                } else {
                    showNotification('Ошибка: ' + response.data, 'error');
                }
            }).fail(function(xhr, status, error) {
                console.error('Ошибка AJAX:', status, error);
                showNotification('Ошибка AJAX', 'error');
            });
        });
    }

    // Автоматическая загрузка активной вкладки при загрузке страницы
    // Проверить на совместимость с вкладками админки.
    const activeTab = $('.tab-link.active').data('tab');
    if (activeTab) {
        loadDashboardTab(activeTab);
    }

    // Загрузка вкладок для дашборда
    function loadDashboardTab(tab, paged = 1) {
        if (steamAuthAjax.debug) console.log('Загрузка вкладки дашборда:', tab, 'Страница:', paged);
        const $content = $(`#${tab}-content`);
        $content.html('<span class="loading-spinner"></span> Загрузка...');

        $.post(steamAuthAjax.ajaxurl, {
            action: 'steam_auth_load_dashboard_tab',
            tab: tab,
            paged: paged,
            nonce: steamAuthAjax.nonce
        }, function(response) {
            $content.html(response);
            if (tab === 'tickets') {
                initTicketActions();
            }
        }).fail(function(xhr, status, error) {
            console.error('Ошибка AJAX:', status, error, xhr.responseText);
            $content.html('Ошибка загрузки вкладки: ' + xhr.responseText);
        });
    }

    // Переключение вкладок в дашборде
    $(document).on('click', '.tab-link', function(e) {
        e.preventDefault();
        const tabId = $(this).data('tab');
        if (steamAuthAjax.debug) console.log('Переключение вкладки в дашборде:', tabId);

        $('.tab-link').removeClass('active');
        $('.tab-content').removeClass('active');
        $(this).addClass('active');
        $(`#${tabId}`).addClass('active');

        if ($('#steam-admin-dashboard').length) {
            loadDashboardTab(tabId); // Загружаем контент для дашборда
        }
    });

    // Пагинация в дашборде
    $(document).on('click', '#steam-admin-dashboard .tablenav-pages a', function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        const paged = href.match(/paged=(\d+)/) ? parseInt(RegExp.$1) : 1;
        loadDashboardTab('tickets', paged);
    });

    function initAdminTicketModal(ticketId) {
        const $replyForm = $('#admin-ticket-reply-form');
        if ($replyForm.length) {
            $replyForm.off('submit').on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'admin_reply_ticket');
                formData.append('ticket_id', ticketId);
                formData.append('nonce', steamAuthAjax.nonce);

                $.ajax({
                    url: steamAuthAjax.ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            showNotification('Ответ отправлен', 'success');
                            $('#ticket-modal').hide();
                            if ($('#steam-admin-dashboard').length) {
                                loadDashboardTab('tickets'); // Обновляем вкладку в дашборде
                            } else {
                                loadTab('tickets'); // Обновляем вкладку в админке
                            }
                        } else {
                            showNotification('Ошибка: ' + (response.data || 'Неизвестная ошибка'), 'error');
                        }
                    },
                    error: function() {
                        showNotification('Ошибка AJAX', 'error');
                    }
                });
            });
        }
    }

    $(document).on('click', '.action-btn', function(e) {
        e.preventDefault();
        const ticketId = $('#ticket-modal-content .admin-ticket-h3').text().match(/#(\d+)/)[1];
        const status = $(this).data('action');
        if (steamAuthAjax.debug) console.log('Обновление статуса тикета:', ticketId, status);
    
        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'update_ticket_status',
                ticket_id: ticketId,
                status: status,
                nonce: steamAuthAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('Статус обновлён', 'success');
                    $('#ticket-modal').hide();
                    if ($('#steam-admin-dashboard').length) {
                        loadDashboardTab('tickets');
                    } else {
                        loadTab('tickets');
                    }
                } else {
                    showNotification('Ошибка: ' + (response.data || 'Неизвестная ошибка'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка AJAX:', status, error);
                showNotification('Ошибка AJAX', 'error');
            }
        });
    });

    // Пагинация в дашборде
    $(document).on('click', '#steam-admin-dashboard .tablenav-pages a', function(e) {
        e.preventDefault();
        const href = $(this).attr('href');
        const paged = href.match(/paged=(\d+)/) ? parseInt(RegExp.$1) : 1;
        loadDashboardTab('tickets', paged);
    });

    $(document).on('click', '.delete-ticket', function(e) {
        e.preventDefault();
        const ticketId = $(this).data('ticket-id');
        if (steamAuthAjax.debug) console.log('Удаление тикета:', ticketId);
    
        showConfirmModal('Вы уверены, что хотите удалить тикет #' + ticketId + '?', function(confirmed) {
            if (confirmed) {
                $.ajax({
                    url: steamAuthAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'admin_delete_ticket',
                        ticket_id: ticketId,
                        nonce: steamAuthAjax.nonce
                    },
                    success: function(response) {
                        if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                        if (response.success) {
                            showNotification('Тикет удалён', 'success');
                            $(`tr:has(a[data-ticket-id="${ticketId}"])`).fadeOut(300, function() {
                                $(this).remove();
                                // Обновляем счётчик тикетов в заголовке
                                const total = parseInt($('h2').text().match(/\d+/)[0]) - 1;
                                $('h2').text(`Тикеты (${total})`);
                            });
                        } else {
                            showNotification('Ошибка: ' + (response.data || 'Неизвестная ошибка'), 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Ошибка AJAX:', status, error);
                        showNotification('Ошибка AJAX', 'error');
                    }
                });
            }
        });
    });

    // Обработчики форм в админке (пример для ticket-topics-form и ticket-settings-form)
    $('#ticket-topics-form').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        $.post(steamAuthAjax.ajaxurl, formData + '&action=save_ticket_topics', function(response) {
            if (response.success) {
                showNotification('Темы тикетов сохранены', 'success');
            } else {
                showNotification('Ошибка: ' + response.data, 'error');
            }
        }).fail(function() {
            showNotification('Ошибка AJAX', 'error');
        });
    });

    $('#ticket-settings-form').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        $.post(steamAuthAjax.ajaxurl, formData + '&action=save_ticket_settings', function(response) {
            if (response.success) {
                showNotification('Настройки тикетов сохранены', 'success');
            } else {
                showNotification('Ошибка: ' + response.data, 'error');
            }
        }).fail(function() {
            showNotification('Ошибка AJAX', 'error');
        });
    });
    
    function initAdminTicketModal(ticketId) {
        const $replyForm = $('#admin-ticket-reply-form');
        if ($replyForm.length) {
            $replyForm.off('submit').on('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'admin_reply_ticket');
                formData.append('ticket_id', ticketId);
                formData.append('nonce', steamAuthAjax.nonce);
                formData.append('note', $('#ticket-note').val());
    
                $.ajax({
                    url: steamAuthAjax.ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            showNotification('Ответ отправлен', 'success');
                            $('#ticket-modal').hide();
                            if ($('#steam-admin-dashboard').length) {
                                loadDashboardTab('tickets');
                            } else {
                                loadTab('tickets');
                            }
                        } else {
                            showNotification('Ошибка: ' + (response.data || 'Неизвестная ошибка'), 'error');
                        }
                    },
                    error: function() {
                        showNotification('Ошибка AJAX', 'error');
                    }
                });
            });
        }
    
        // Быстрые ответы
        $('#quick-reply').off('change').on('change', function() {
            const value = $(this).val();
            if (value && typeof tinymce !== 'undefined' && tinymce.get('reply-content')) {
                tinymce.get('reply-content').setContent(value);
                if (steamAuthAjax.debug) console.log('Шаблон ответа применён:', value);
            } else if (value) {
                $('#reply-content').val(value);
                if (steamAuthAjax.debug) console.log('TinyMCE не готов, использован запасной вариант:', value);
            }
        });

        // // Переключатель тем
        // const $themeSelect = $('<select id="ticket-theme" style="position: absolute; top: 10px; right: 50px;"><option value="default">Стандарт</option><option value="theme-open">Открыт</option><option value="theme-in_progress">В обработке</option><option value="theme-closed">Закрыт</option></select>');
        // $('.ticket-modal-header').append($themeSelect);
        // $themeSelect.val('theme-' + $('.ticket-status').text().toLowerCase().replace(' ', '_'));
        // $themeSelect.on('change', function() {
        //     $('#ticket-modal-content').removeClass('theme-open theme-in_progress theme-closed').addClass($(this).val());
        // });

        // Копирование ссылки на тикет
        // $('.copy-ticket-link').on('click', function() {
        //     const ticketId = $(this).data('ticket-id');
        //     const url = `${steamAuthAjax.home_url}/auth/admin.php?page=steam-auth-tickets&ticket_id=${ticketId}`;
        //     navigator.clipboard.writeText(url).then(() => {
        //         showNotification('Ссылка скопирована в буфер обмена', 'success');
        //     }).catch(() => {
        //         showNotification('Ошибка копирования', 'error');
        //     });
        // });

        // Таймер удаления
        const $timer = $('.ticket-timer');
        if ($timer.length) {
            const deleteTime = $timer.data('delete-timestamp') * 1000;
            setInterval(() => {
                const now = Date.now();
                const timeLeft = deleteTime - now;
                if (timeLeft <= 0) {
                    $timer.text('Тикет будет удалён в любой момент');
                } else {
                    const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                    $timer.text(`Удалится через: ${days}д ${hours}ч ${minutes}м`);
                }
            }, 1000);
        }
    }

    // Инициализация
    if ($('#steam-admin-dashboard').length) {
        initTicketActions(); // Для дашборда, если тикеты уже загружены
    } else {
        loadTab('general'); // Для админки
    }
});