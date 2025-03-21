<?php
// tabs/mods-tab.php
defined('ABSPATH') or die('No direct access allowed');
?>

<div class="widget widget-mods">
    <h3>Ваши моды</h3>
    <?php
    if ($discord_id) {
        $member_data = fetch_discord_membership($discord_id);
        $mods_config = get_option('steam_auth_mods_config', []);
        $selected_mod_roles = isset($mods_config['selected_roles']) ? $mods_config['selected_roles'] : [];
        $roles = fetch_discord_roles();
        if ($member_data && !empty($member_data['roles'])) {
            $has_mods = false;
            ?>
            <table class="mods-table">
                <thead>
                    <tr>
                        <th>Мод</th>
                        <th>Версия</th>
                        <th>Документация</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($member_data['roles'] as $role_id) :
                        if (isset($selected_mod_roles[$role_id]) && isset($roles[$role_id])) : 
                            $has_mods = true;
                            $mod_config = $mods_config[$role_id] ?? [];
                            $doc_url = $mod_config['documentation_url'] ?? '';
                            ?>
                            <tr>
                                <td>
                                    <?php if ($mod_config['image']) : ?>
                                        <img src="<?php echo esc_url($mod_config['image']); ?>" alt="<?php echo esc_attr($roles[$role_id]['name']); ?>" width="30" style="vertical-align: middle; margin-right: 10px;">
                                    <?php endif; ?>
                                    <?php echo esc_html($roles[$role_id]['name']); ?>
                                </td>
                                <td><?php echo esc_html($mod_config['version'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($doc_url) : ?>
                                        <a href="<?php echo esc_url($doc_url); ?>" target="_blank" class="steam-documentation-btn">Документация</a>
                                    <?php else : ?>
                                        Нет
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; 
                    endforeach; 
                    ?>
                </tbody>
            </table>
            <?php
            if (!$has_mods) {
                echo '<p>У вас нет доступных модов.</p>';
            }
        } else {
            echo '<p>Вы не состоите на сервере Discord или у вас нет ролей.</p>';
        }
    } else {
        echo '<p>Привяжите Discord, чтобы увидеть свои моды.</p>';
    }
    ?>
</div>