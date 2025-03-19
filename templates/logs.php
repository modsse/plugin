<h2>Логи действий</h2>
<table class="widefat fixed" cellspacing="0">
    <thead>
        <tr>
            <th>Дата</th>
            <th>SteamID</th>
            <th>Действие</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($logs)): ?>
            <tr><td colspan="3">Логов пока нет</td></tr>
        <?php else: ?>
            <?php foreach (array_reverse($logs) as $log): ?>
                <tr>
                    <td><?php echo esc_html($log['date']); ?></td>
                    <td><?php echo esc_html($log['steam_id']); ?></td>
                    <td><?php echo esc_html($log['action']); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
<p><button type="button" id="clear-logs" class="button">Очистить логи</button></p>