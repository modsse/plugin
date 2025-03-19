// js/frontend.js
document.addEventListener('DOMContentLoaded', function() {
    if (steamAuth && steamAuth.debug) {
        console.log('Steam Auth Frontend: Script loaded');
    }

    if (typeof steamAuth === 'undefined' || !steamAuth.loginUrl) {
        if (steamAuth) {
            console.error('Steam Auth Frontend: steamAuth.loginUrl not defined');
        }
        return;
    }

    // Обработка кнопок Steam Login
    const buttons = document.querySelectorAll('.steam-login-btn');
    buttons.forEach(button => {
        button.href = steamAuth.loginUrl;
        button.addEventListener('click', function(event) {
            event.preventDefault();
            window.location.href = steamAuth.loginUrl;
        });
    });

    // Добавление счётчика к кнопке "Профиль"
    const profileButton = document.querySelector('.profile-button') || document.querySelector('a[href="/user"]');
    if (!profileButton) {
        if (steamAuth && steamAuth.debug) {
            console.log('Steam Auth Frontend: Кнопка "Профиль" не найдена');
        }
        return;
    }

    // Убедимся, что кнопка имеет класс profile-button
    profileButton.classList.add('profile-button');

    function updateUnreadCount() {
        fetch(steamAuthAjax.ajaxurl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=steam_auth_get_unread_count&nonce=${steamAuthAjax.nonce}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const count = data.data;
                let badge = profileButton.querySelector('.unread-count');
                if (count > 0) {
                    if (!badge) {
                        badge = document.createElement('span');
                        badge.className = 'unread-count';
                        profileButton.appendChild(badge);
                    }
                    badge.textContent = count;
                } else if (badge) {
                    badge.remove();
                }
            }
        })
        .catch(error => {
            if (steamAuth && steamAuth.debug) {
                console.error('Steam Auth Frontend: Ошибка AJAX:', error);
            }
        });
    }

    // Инициализация и обновление каждые 30 секунд
    updateUnreadCount();
    setInterval(updateUnreadCount, 30000);

    buttons.forEach(button => {
        button.href = steamAuth.loginUrl;
        if (steamAuth && steamAuth.debug) {
            console.log('Steam Auth Frontend: Updated href for button:', button.href);
        }

        button.addEventListener('click', function(event) {
            event.preventDefault();
            if (steamAuth && steamAuth.debug) {
                console.log('Steam Auth Frontend: Button clicked, redirecting to:', steamAuth.loginUrl);
            }
            window.location.href = steamAuth.loginUrl;
        });
    });
});