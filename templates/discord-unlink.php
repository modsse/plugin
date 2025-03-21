<h2>Очередь на отвязку Discord</h2>
<table class="wp-list-table widefat fixed"> <!-- Убрал .striped -->
    <thead>
        <tr>
            <th>Пользователь</th>
            <th>SteamID</th>
            <th>Discord ID</th>
            <th>Discord Имя</th>
            <th>Действия</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $discord_unlink_requests = get_option('steam_auth_discord_unlink_requests', []);
        if (empty($discord_unlink_requests)) {
            echo '<tr><td colspan="5">Нет запросов на отвязку</td></tr>';
        } else {
            foreach ($discord_unlink_requests as $user_id => $request) {
                $user = get_userdata($user_id);
                $steam_id = get_user_meta($user_id, 'steam_id', true);
                ?>
                <tr>
                    <td><?php echo esc_html($user->display_name); ?></td>
                    <td><?php echo esc_html($steam_id); ?></td>
                    <td><?php echo esc_html($request['id']); ?></td>
                    <td><?php echo esc_html($request['username']); ?></td>
                    <td>
                        <button class="button button-primary steam-approve-unlink-discord" data-user-id="<?php echo $user_id; ?>">Одобрить</button>
                        <button class="button steam-reject-unlink-discord" data-user-id="<?php echo $user_id; ?>">Отклонить</button>
                    </td>
                </tr>
                <?php
            }
        }
        ?>
    </tbody>
</table>