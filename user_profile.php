<?php
// user_profile.php
defined('ABSPATH') or die('No direct access allowed');
?>

<div class="steam-dashboard" style="max-width: none; width: 100%; margin: 0; padding: 0;">
    <div class="sidebar">
        <div class="sidebar-header">
            <button class="sidebar-toggle"><i class="fas fa-bars"></i></button>
            <?php if ($steam_avatar): ?>
                <img src="<?php echo esc_url($steam_avatar); ?>" alt="Steam Avatar" class="avatar">
            <?php endif; ?>
            <h2><?php echo esc_html($display_name); ?></h2>
            <p>Ваш личный дашборд</p>
        </div>
        <nav class="sidebar-nav">
            <a href="#profile" data-tab="profile" class="tab-link <?php echo $tab === 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user"></i> <span>Профиль</span>
            </a>
            <a href="#messages" data-tab="messages" class="tab-link <?php echo $tab === 'messages' ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> <span>Сообщения</span>
                <?php if ($unread_count > 0): ?>
                    <span class="unread-count"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="#mods" data-tab="mods" class="tab-link <?php echo $tab === 'mods' ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i> <span>Моды</span>
            </a>
            <a href="<?php echo wp_logout_url(home_url()); ?>" class="steam-logout-btn">
                <i class="fas fa-sign-out-alt"></i> <span>Выйти</span>
            </a>
        </nav>
    </div>

    <div class="dashboard-content" id="dashboard-content">
        <!-- Контент будет загружаться через AJAX -->
        <?php
        // Загружаем начальный контент на сервере
        if ($tab === 'profile') {
            include plugin_dir_path(__FILE__) . 'tabs/profile-tab.php';
        } elseif ($tab === 'messages') {
            include plugin_dir_path(__FILE__) . 'tabs/messages-tab.php';
        } elseif ($tab === 'mods') {
            include plugin_dir_path(__FILE__) . 'tabs/mods-tab.php';
        }
        ?>
    </div>

    <div id="steam-profile-notification" class="notification"></div>
</div>