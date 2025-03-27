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
        }, 5000);
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

    // Функция обновления счётчика тикетов
    function updateTicketsCount() {
        fetch(steamProfileData.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=get_unread_tickets_count&nonce=${steamProfileData.nonce}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const ticketsLink = document.querySelector('.tab-link[data-tab="tickets"]');
                let countSpan = ticketsLink.querySelector('.unread-count');
                if (data.data > 0) {
                    if (!countSpan) {
                        countSpan = document.createElement('span');
                        countSpan.className = 'unread-count';
                        ticketsLink.appendChild(countSpan);
                    }
                    countSpan.textContent = data.data;
                } else if (countSpan) {
                    countSpan.remove();
                }
            }
        })
        .catch(error => console.error('Ошибка обновления счётчика тикетов:', error));
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
                                showNotification(`<p class="error">Ошибка: ${data.data.message || 'Неизвестная ошибка'}</p>`, true);
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

        if (tab === 'tickets') {
            const createTicketForm = document.getElementById('create-ticket-form');
            const ticketSpinner = document.getElementById('ticket-spinner');
            const modal = document.getElementById('ticket-modal');
            const modalClose = document.querySelector('.steam-modal-close');
            const modalContent = document.getElementById('ticket-modal-content');
            const accordionToggle = document.querySelector('.accordion-toggle');
            const accordionContent = document.querySelector('.accordion-content');
            const accordion = document.querySelector('.accordion');
        
            if (accordionToggle && accordionContent) {
                accordionToggle.addEventListener('click', function() {
                    const isOpen = accordion.classList.contains('open');
                    accordion.classList.toggle('open');
                    accordionContent.style.display = isOpen ? 'none' : 'block';
                });
            }
        
            if (createTicketForm) {
                createTicketForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    formData.append('action', 'create_ticket');
                    formData.append('nonce', steamProfileData.nonce);
                    ticketSpinner.style.display = 'inline-block';
        
                    fetch(steamProfileData.ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        ticketSpinner.style.display = 'none';
                        if (data.success) {
                            showNotification('<p class="success">Тикет создан!</p>');
                            loadTab('tickets');
                            updateTicketsCount();
                            accordion.classList.remove('open');
                            accordionContent.style.display = 'none'; // Сворачиваем после создания
                            createTicketForm.reset(); // Очистка формы
                        } else {
                            showNotification(`<p class="error">Ошибка: ${data.data || 'Неизвестная ошибка'}</p>`, true);
                        }
                    })
                    .catch(error => {
                        ticketSpinner.style.display = 'none';
                        console.error('Ошибка создания тикета:', error);
                        showNotification('<p class="error">Ошибка соединения</p>', true);
                    });
                });
            }
        
            document.querySelectorAll('.view-ticket').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const ticketId = this.getAttribute('data-ticket-id');
                    fetch(steamProfileData.ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=view_ticket&ticket_id=${ticketId}&nonce=${steamProfileData.nonce}`
                    })
                    .then(response => response.text())
                    .then(html => {
                        modalContent.innerHTML = html;
                        modal.style.display = 'block';
                        initTicketModalEvents(ticketId);
                    })
                    .catch(error => {
                        console.error('Ошибка загрузки тикета:', error);
                        showNotification('<p class="error">Ошибка загрузки тикета</p>', true);
                    });
                });
            });
        
            if (modalClose) {
                modalClose.addEventListener('click', function() {
                    modal.style.display = 'none';
                    updateTicketsCount();
                });
            }
        
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    updateTicketsCount();
                }
            });
        
            function initTicketModalEvents(ticketId) {
                const replyForm = document.getElementById('ticket-reply-form');
                if (replyForm) {
                    replyForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        formData.append('action', 'reply_ticket');
                        formData.append('ticket_id', ticketId);
                        formData.append('nonce', steamProfileData.nonce);
        
                        fetch(steamProfileData.ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showNotification('<p class="success">Ответ отправлен!</p>');
                                loadTab('tickets'); // Перезагружаем вкладку тикетов
                                modal.style.display = 'none';
                                updateTicketsCount();
                                replyForm.reset(); // Очистка формы
                            } else {
                                showNotification(`<p class="error">Ошибка: ${data.data || 'Неизвестная ошибка'}</p>`, true);
                            }
                        })
                        .catch(error => {
                            console.error('Ошибка отправки ответа:', error);
                            showNotification('<p class="error">Ошибка соединения</p>', true);
                        });
                    });
                }
            }
        }
    }

    // Периодическое обновление счётчика (каждые 60 секунд)
    setInterval(updateTicketsCount, 60000);

    // Инициализация начального состояния
    const initialTab = steamProfileData.tab || 'profile';
    const initialEdit = window.location.search.includes('edit=true');
    const initialPage = new URLSearchParams(window.location.search).get('page') || 1;
    const initialCategory = new URLSearchParams(window.location.search).get('category') || '';
    loadTab(initialTab, initialEdit, initialPage, initialCategory);
    updateTicketsCount(); // Обновляем счётчик при загрузке страницы
});