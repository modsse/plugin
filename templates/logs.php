<style>
.messages-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
.messages-table th, .messages-table td { padding: 10px; text-align: left; border: 1px solid #ddd; }
.messages-table th { background: #f1f1f1; font-weight: bold; }
.messages-table tr:nth-child(even) { background: #f9f9f9; }
</style>
<div class="wrap">
    <h1>Логи Steam Auth</h1>
    <div class="steam-auth-logs">
        <?php
        $logs = get_steam_auth_logs(50);
        if ($logs) {
            ?>
            <table class="steam-table">
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
    <button id="clear-logs" class="button button-primary">Очистить логи</button>
</div>