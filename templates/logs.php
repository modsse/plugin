<?php
function get_steam_auth_logs($limit = 50) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'steam_auth_logs';
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name ORDER BY date DESC LIMIT %d",
        $limit
    ));
}
?>

<div class="wrap">
    <h1>Логи Steam Auth</h1>
    <?php
    $logs = get_steam_auth_logs();
    if ($logs) {
        echo '<ul>';
        foreach ($logs as $log) {
            $log_details = $log->date . ' - ' . esc_html($log->action) . ' (Steam ID: ' . esc_html($log->steam_id);
            if ($log->discord_id) $log_details .= ', Discord ID: ' . esc_html($log->discord_id);
            if ($log->discord_username) $log_details .= ', Discord: ' . esc_html($log->discord_username);
            $log_details .= ')';
            if ($log->error) $log_details .= ' - Ошибка: ' . esc_html($log->error);
            echo '<li>' . $log_details . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Логов нет.</p>';
    }
    ?>
    <button id="clear-logs" class="button button-primary">Очистить логи</button>
</div>