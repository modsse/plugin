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
                console.error('Ошибка:', error);
                showNotification('Ошибка AJAX: ' + error, 'error');
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
        const formData = $(this).serialize();
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
                loadTab('messages');
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
        const data = $(this).serialize() + '&action=steam_auth_save_settings&general=1';
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
            }
        });
    });

    $(document).on('submit', '#profile-form', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Отправка формы #profile-form');
        const data = $(this).serialize() + '&action=steam_auth_save_settings&profile=1';
        if (steamAuthAjax.debug) console.log('Данные для отправки:', data);
        $.ajax({
            url: steamAuthAjax.ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (steamAuthAjax.debug) console.log('Ответ сервера:', response);
                if (response.success) {
                    showNotification(response.data.message, 'success');
                    $.post(steamAuthAjax.ajaxurl, { action: 'steam_auth_load_tab', tab: 'profile' }, function(response) {
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
            }
        });
    });

    $(document).on('submit', '#discord-notifications-form', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Отправка формы #discord-notifications-form');
        const data = $(this).serialize() + '&action=steam_auth_save_settings&discord-notifications=1';
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
            }
        });
    });

    $(document).on('submit', '#steam-auth-mods-form', function(e) {
        e.preventDefault();
        if (steamAuthAjax.debug) console.log('Отправка формы #steam-auth-mods-form');
        const formData = $(this).serializeArray(); // Получаем массив данных
        formData.push({ name: 'action', value: 'steam_auth_save_settings' });
        formData.push({ name: 'tab', value: 'mods' }); // Заменяем mods=1 на tab=mods
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
                    // Перезагрузка вкладки, если нужно
                } else {
                    showNotification('Ошибка: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ошибка AJAX:', status, error);
                showNotification('Ошибка AJAX: ' + error, 'error');
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
        const data = $('#discord-notifications-form').serialize() + '&action=steam_auth_test_discord_embed';
        $.post(steamAuthAjax.ajaxurl, data, function(response) {
            if (steamAuthAjax.debug) console.log('Ответ тестовой отправки:', response);
            showNotification(response.success ? 'Тестовое сообщение отправлено!' : 'Ошибка: ' + response.data, response.success ? 'success' : 'error');
        }).fail(function(xhr, status, error) {
            console.error('Ошибка AJAX:', status, error);
            showNotification('Ошибка AJAX', 'error');
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
        if (settings.data && settings.data.indexOf('action=steam_auth_load_tab') !== -1) {
            const tab = settings.data.match(/tab=([^&]+)/)[1];
            $(document).trigger('steam_auth_tab_loaded', [tab]);
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

    const tabs = document.querySelectorAll('.nav-tab');
    const content = document.getElementById('tab-content');

    function loadTab(tab) {
        if (steamAuthAjax.debug) console.log('Загрузка вкладки:', tab);
        $.post(steamAuthAjax.ajaxurl, {
            action: 'steam_auth_load_tab',
            tab: tab,
            nonce: steamAuthAjax.nonce
        }, function(response) {
            if (steamAuthAjax.debug) console.log('Ответ сервера получен для вкладки', tab);
            content.innerHTML = response;
            $(document).trigger('steam_auth_tab_loaded', [tab]);
        }).fail(function(xhr, status, error) {
            console.error('Ошибка AJAX:', status, error);
        });
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            if (steamAuthAjax.debug) console.log('Клик по вкладке:', this.dataset.tab);
            tabs.forEach(t => t.classList.remove('nav-tab-active'));
            this.classList.add('nav-tab-active');
            loadTab(this.dataset.tab);
        });
    });

    $(document).on('click', '#clear-logs', function() {
        showConfirmModal('Вы уверены, что хотите очистить все логи?', function(confirmed) {
            if (confirmed) {
                if (steamAuthAjax.debug) console.log('Попытка очистки логов');
                $.post(steamAuthAjax.ajaxurl, {
                    action: 'steam_auth_clear_logs',
                    nonce: steamAuthAjax.nonce
                }, function(response) {
                    if (steamAuthAjax.debug) console.log('Логи очищены, обновление вкладки');
                    content.innerHTML = '<div class="notice notice-success is-dismissible"><p>Логи успешно очищены</p></div>' + response;
                }).fail(function(xhr, status, error) {
                    console.error('Ошибка при очистке логов:', status, error);
                    content.innerHTML = '<div class="notice notice-error is-dismissible"><p>Ошибка при очистке логов: ' + error + '</p></div>' + content.innerHTML;
                });
            }
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

    loadTab('general');
});