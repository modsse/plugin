// js/profile.js
document.addEventListener('DOMContentLoaded', function() {
    if (steamProfileData && steamProfileData.debug) {
        console.log('Profile.js loaded');
    }

    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const dashboardContent = document.getElementById('dashboard-content');
    const tabLinks = document.querySelectorAll('.tab-link');
    const notification = document.getElementById('steam-profile-notification');

    // Сайдбар
    if (sidebarToggle && sidebar) {
        sidebar.style.transition = 'none';
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }
        setTimeout(() => {
            sidebar.style.transition = 'width 0.3s ease';
        }, 0);

        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }

    // Уведомления
    function showNotification(message, isError = false) {
        if (!notification) return;
        notification.innerHTML = message;
        notification.classList.remove('visible', 'success', 'error');
        notification.classList.add(isError ? 'error' : 'success');
        notification.classList.add('visible');
        setTimeout(() => {
            notification.classList.remove('visible');
        }, 3000);
    }

    // Начальное уведомление
    if (steamProfileData && typeof steamProfileData.notification === 'string' && steamProfileData.notification) {
        showNotification(steamProfileData.notification, steamProfileData.notification.includes('Ошибка'));
    }

    // AJAX для вкладок
    if (tabLinks && dashboardContent) {
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const tab = this.getAttribute('data-tab');
                loadTab(tab);
                tabLinks.forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                window.history.pushState({ tab }, '', `?tab=${tab}`);
            });
        });
    }

    // Функция загрузки вкладки
    function loadTab(tab, edit = false, page = 1, category = '') {
        const body = `action=load_tab&tab=${tab}&edit=${edit}&page=${page}&category=${encodeURIComponent(category)}&nonce=${steamProfileData.nonce}`;
        fetch(steamProfileData.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
        .then(response => response.text())
        .then(html => {
            dashboardContent.innerHTML = html;
            initTabEvents(tab, edit, page, category); // Передаем параметры в initTabEvents
        })
        .catch(error => {
            console.error('Ошибка загрузки вкладки:', error);
            showNotification('<p class="error">Ошибка загрузки вкладки</p>', true);
        });
    }

    // Инициализация событий для вкладок
    function initTabEvents(tab, edit = false, page = 1, category = '') {
        if (tab === 'profile') {
            const profileWidget = document.querySelector('.widget-profile');
            const editWidget = document.querySelector('.widget-edit');

            if (profileWidget && editWidget) {
                function toggleEdit(isEdit) {
                    if (isEdit) {
                        profileWidget.classList.remove('visible');
                        profileWidget.classList.add('hidden');
                        editWidget.classList.remove('hidden');
                        editWidget.classList.add('visible');
                    } else {
                        editWidget.classList.remove('visible');
                        editWidget.classList.add('hidden');
                        profileWidget.classList.remove('hidden');
                        profileWidget.classList.add('visible');
                    }
                }

                toggleEdit(edit);

                document.querySelector('.steam-edit-btn')?.addEventListener('click', function(e) {
                    e.preventDefault();
                    loadTab('profile', true);
                    window.history.pushState({ tab: 'profile', edit: true }, '', '?tab=profile&edit=true');
                });

                document.querySelector('.steam-cancel-btn')?.addEventListener('click', function(e) {
                    e.preventDefault();
                    loadTab('profile', false);
                    window.history.pushState({ tab: 'profile' }, '', '?tab=profile');
                });

                const editForm = document.getElementById('profile-edit-form');
                if (editForm) {
                    editForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        formData.append('action', 'save_profile');
                        formData.append('nonce', steamProfileData.nonce);

                        fetch(steamProfileData.ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showNotification('<p class="success">Профиль обновлён!</p>');
                                loadTab('profile', false);
                                window.history.pushState({ tab: 'profile' }, '', '?tab=profile');
                            } else {
                                showNotification(`<p class="error">Ошибка: ${data.data || 'Неизвестная ошибка'}</p>`, true);
                            }
                        })
                        .catch(error => {
                            showNotification('<p class="error">Ошибка сохранения</p>', true);
                        });
                    });
                }

                const notificationsCheckbox = document.getElementById('discord_notifications');
                if (notificationsCheckbox) {
                    notificationsCheckbox.addEventListener('change', function() {
                        const userId = this.getAttribute('data-user-id');
                        const enabled = this.checked ? 1 : 0;

                        fetch(steamProfileData.ajaxurl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=update_discord_notifications_profile&user_id=${userId}&enabled=${enabled}&nonce=${steamProfileData.nonce}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showNotification(`<p class="success">${data.data.message}</p>`);
                            } else {
                                showNotification(`<p class="error">Ошибка: ${data.data || 'Неизвестная ошибка'}</p>`, true);
                                notificationsCheckbox.checked = !enabled; // Откатываем изменение
                            }
                        })
                        .catch(error => {
                            console.error('Ошибка:', error);
                            showNotification('<p class="error">Произошла ошибка</p>', true);
                            notificationsCheckbox.checked = !enabled; // Откатываем изменение
                        });
                    });
                }
            }
        }

        if (tab === 'messages') {
            // Обработчик для "Прочитать"
            document.querySelectorAll('.mark-read').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const messageId = this.getAttribute('data-message-id');
                    fetch(steamProfileData.ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=mark_read&message_id=${messageId}&nonce=${steamProfileData.nonce}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            loadTab('messages', false, page, category); // Перезагружаем вкладку с текущими параметрами
                        } else {
                            showNotification('<p class="error">Ошибка пометки сообщения</p>', true);
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка:', error);
                        showNotification('<p class="error">Произошла ошибка</p>', true);
                    });
                });
            });

            // Обработчик для "Удалить"
            document.querySelectorAll('.delete-message').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (confirm('Удалить это сообщение?')) {
                        const messageId = this.getAttribute('data-message-id');
                        fetch(steamProfileData.ajaxurl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: `action=delete_message&message_id=${messageId}&nonce=${steamProfileData.nonce}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showNotification('<p class="success">Сообщение удалено</p>');
                                loadTab('messages', false, page, category);
                            } else {
                                showNotification('<p class="error">Ошибка удаления сообщения</p>', true);
                            }
                        })
                        .catch(error => {
                            console.error('Ошибка:', error);
                            showNotification('<p class="error">Произошла ошибка</p>', true);
                        });
                    }
                });
            });

            // Обработчик для "Удалить прочитанные"
            document.querySelector('.delete-all-read')?.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Удалить все прочитанные сообщения?')) {
                    fetch(steamProfileData.ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete_all_read&nonce=${steamProfileData.nonce}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('<p class="success">Все прочитанные сообщения удалены</p>');
                            loadTab('messages', false, page, category);
                        } else {
                            showNotification('<p class="error">Ошибка удаления</p>', true);
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка:', error);
                        showNotification('<p class="error">Произошла ошибка</p>', true);
                    });
                }
            });

            // Обработчик для "Удалить все"
            document.querySelector('.delete-all')?.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Удалить все сообщения?')) {
                    fetch(steamProfileData.ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=delete_all&nonce=${steamProfileData.nonce}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showNotification('<p class="success">Все сообщения удалены</p>');
                            loadTab('messages', false, page, category);
                        } else {
                            showNotification('<p class="error">Ошибка удаления</p>', true);
                        }
                    })
                    .catch(error => {
                        console.error('Ошибка:', error);
                        showNotification('<p class="error">Произошла ошибка</p>', true);
                    });
                }
            });

            // Обработчик для пагинации
            document.querySelectorAll('.pagination a').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = new URL(this.href, window.location.origin);
                    const newPage = url.searchParams.get('page') || 1;
                    const newCategory = url.searchParams.get('category') || '';
                    loadTab('messages', false, newPage, newCategory);
                    window.history.pushState({ tab: 'messages', page: newPage, category: newCategory }, '', this.href);
                });
            });

            // Обработчик для фильтров категорий
            document.querySelectorAll('.category-filter a').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    const url = new URL(href, window.location.origin);
                    const newCategory = url.searchParams.get('category') || '';
                    loadTab('messages', false, 1, newCategory); // Сбрасываем на первую страницу
                    window.history.pushState({ tab: 'messages', page: 1, category: newCategory }, '', href);
                    document.querySelectorAll('.category-filter a').forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        }
    }

    // Инициализация начальной вкладки
    const initialTab = steamProfileData.tab || 'profile';
    const initialEdit = window.location.search.includes('edit=true');
    const initialPage = new URLSearchParams(window.location.search).get('page') || 1;
    const initialCategory = new URLSearchParams(window.location.search).get('category') || '';
    loadTab(initialTab, initialEdit, initialPage, initialCategory);
});