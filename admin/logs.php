<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 操作日志模块

$page_title = '操作日志';

// 获取日志列表
$logs_result = $db->query("SELECT * FROM operation_logs ORDER BY create_time DESC LIMIT 200");
$logs = array();
while ($row = $logs_result->fetch_assoc()) {
    $logs[] = $row;
}

include 'header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>📋 操作日志</h2>
        <span style="font-size: 13px; color: var(--gray-500);">最近 200 条记录</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>时间</th>
                    <th>操作类型</th>
                    <th>描述</th>
                    <th>IP地址</th>
                    <th>User-Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo safeOutput($log['create_time'] ?? ''); ?></td>
                    <td>
                        <?php
                        $action_colors = array(
                            'login' => 'badge-info',
                            'logout' => 'badge-warning',
                            'delete_app' => 'badge-danger',
                            'batch_delete_apps' => 'badge-danger',
                            'mark_violation' => 'badge-danger',
                            'unmark_violation' => 'badge-success',
                            'process_report' => 'badge-warning',
                            'delete_report' => 'badge-danger',
                            'save_site' => 'badge-info',
                            'save_storage' => 'badge-info',
                            'cleanup' => 'badge-success',
                            'clear_cache' => 'badge-success'
                        );
                        $badge_class = isset($action_colors[$log['action']]) ? $action_colors[$log['action']] : 'badge-info';
                        ?>
                        <span class="badge <?php echo $badge_class; ?>"><?php echo safeOutput($log['action'] ?? ''); ?></span>
                    </td>
                    <td><?php echo safeOutput($log['description'] ?? ''); ?></td>
                    <td><?php echo safeOutput($log['ip'] ?? ''); ?></td>
                    <td style="font-size: 11px; color: var(--gray-500); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($log['user_agent'] ?? ''); ?>"><?php echo safeOutput($log['user_agent'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
