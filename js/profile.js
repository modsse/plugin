// js/profile.js
document.addEventListener('DOMContentLoaded', function() {
    if (steamProfileData && steamProfileData.debug) {
        console.log('Profile.js loaded');
    }

    const profileContent = document.querySelector('.profile-content');
    const editForm = document.querySelector('.steam-edit-profile');
    const notification = document.getElementById('steam-profile-notification');

    if (!profileContent || !editForm) {
        if (steamProfileData) {
            console.error('Не найдены элементы .profile-content или .steam-edit-profile');
        }
        return;
    }

    if (!notification) {
        if (steamProfileData) {
            console.error('Не найдено уведомление #steam-profile-notification');
        }
        return;
    }

    function toggleEdit(isEdit) {
        if (isEdit) {
            profileContent.classList.remove('visible');
            profileContent.classList.add('hidden');
            editForm.classList.remove('hidden');
            editForm.classList.add('visible');
        } else {
            editForm.classList.remove('visible');
            editForm.classList.add('hidden');
            profileContent.classList.remove('hidden');
            profileContent.classList.add('visible');
        }
    }

    document.querySelector('.steam-edit-btn')?.addEventListener('click', function(e) {
        e.preventDefault();
        toggleEdit(true);
        window.history.pushState({}, '', '?edit=true');
    });

    document.querySelector('.steam-cancel-btn')?.addEventListener('click', function(e) {
        e.preventDefault();
        toggleEdit(false);
        window.history.pushState({}, '', window.location.pathname);
    });

    if (steamProfileData && steamProfileData.notification) {
        if (steamProfileData.debug) {
            console.log('Уведомление:', steamProfileData.notification);
        }
        notification.innerHTML = steamProfileData.notification;
        notification.classList.add(steamProfileData.notification.includes('Ошибка') ? 'error' : 'success');
        notification.style.display = 'block';
        notification.classList.add('visible');

        setTimeout(() => {
            notification.classList.remove('visible');
            notification.classList.add('fade-out');
            setTimeout(() => {
                notification.style.display = 'none';
                notification.classList.remove('fade-out');
            }, 500);
        }, 3000);
    } else if (steamProfileData && steamProfileData.debug) {
        console.log('Нет уведомления в steamProfileData:', steamProfileData);
    }

    // Обработка чекбокса уведомлений Discord
    const notificationsCheckbox = document.getElementById('discord_notifications');
    if (notificationsCheckbox) {
        notificationsCheckbox.addEventListener('change', function() {
            const userId = this.getAttribute('data-user-id');
            const enabled = this.checked ? 1 : 0;

            if (steamProfileData.debug) {
                console.log('Updating Discord notifications', { userId, enabled });
            }

            fetch(steamProfileData.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_discord_notifications&user_id=${userId}&enabled=${enabled}&nonce=${steamProfileData.nonce}`
            })
            .then(response => response.json())
            .then(data => {
                if (steamProfileData.debug) {
                    console.log('AJAX response', data);
                }
                if (data.success) {
                    notification.innerHTML = `<p class="success">Уведомления ${enabled ? 'включены' : 'отключены'}!</p>`;
                    notification.classList.add('success');
                    notification.style.display = 'block';
                    notification.classList.add('visible');
                    setTimeout(() => {
                        notification.classList.remove('visible');
                        notification.classList.add('fade-out');
                        setTimeout(() => {
                            notification.style.display = 'none';
                            notification.classList.remove('fade-out');
                        }, 500);
                    }, 3000);
                } else {
                    notification.innerHTML = `<p class="error">Ошибка: ${data.data || 'Неизвестная ошибка'}</p>`;
                    notification.classList.add('error');
                    notification.style.display = 'block';
                    notification.classList.add('visible');
                    notificationsCheckbox.checked = !enabled; // Откатываем изменение
                    setTimeout(() => {
                        notification.classList.remove('visible');
                        notification.classList.add('fade-out');
                        setTimeout(() => {
                            notification.style.display = 'none';
                            notification.classList.remove('fade-out');
                        }, 500);
                    }, 3000);
                }
            })
            .catch(error => {
                if (steamProfileData.debug) {
                    console.error('AJAX error', error);
                }
                notification.innerHTML = '<p class="error">Произошла ошибка при сохранении настроек</p>';
                notification.classList.add('error');
                notification.style.display = 'block';
                notification.classList.add('visible');
                notificationsCheckbox.checked = !enabled; // Откатываем изменение
                setTimeout(() => {
                    notification.classList.remove('visible');
                    notification.classList.add('fade-out');
                    setTimeout(() => {
                        notification.style.display = 'none';
                        notification.classList.remove('fade-out');
                    }, 500);
                }, 3000);
            });
        });
    }

    if (steamProfileData && steamProfileData.debug) {
        console.log('Debug mode enabled. Notification value:', steamProfileData.notification);
    }
});