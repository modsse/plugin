<h1>Логи Steam Auth</h1> <!-- Убрал .wrap, стили в CSS -->
<div id="steam-auth-notification" class="notification" style="display: none;"></div>
<div class="steam-auth-logs">
    <?php
    $logs = get_steam_auth_logs(50);
    if ($logs) {
        ?>
        <table class="messages-table widefat">
            <thead>
                <tr>
                    <th>Дата</th>
                    <th>Действие</th>
                    <th>Steam ID</th>
                    <th>Discord ID</th>
                    <th>Discord Имя</th>
                    <th>Ошибка</th>
                </tr>
            </thead>
            <tbody>
                <?php
                foreach ($logs as $log) {
                    echo '<tr>';
                    echo '<td>' . esc_html($log->date) . '</td>';
                    echo '<td>' . esc_html($log->action) . '</td>';
                    echo '<td>' . esc_html($log->steam_id) . '</td>';
                    echo '<td>' . esc_html($log->discord_id ?: '—') . '</td>';
                    echo '<td>' . esc_html($log->discord_username ?: '—') . '</td>';
                    echo '<td>' . esc_html($log->error ?: '—') . '</td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
        <?php
    } else {
        echo '<p>Логов нет.</p>';
    }
    ?>
</div>
<p><button id="clear-logs" class="button button-primary">Очистить логи</button></p>