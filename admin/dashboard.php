<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 仪表盘页面

$page_title = '仪表盘';

// 获取统计数据
$apps_result = $db->query("SELECT * FROM urls ORDER BY create_time DESC");
$apps = array();
while ($row = $apps_result->fetch_assoc()) {
    $apps[] = $row;
}

$reports_result = $db->query("SELECT * FROM reports ORDER BY create_time DESC");
$reports = array();
while ($row = $reports_result->fetch_assoc()) {
    $reports[] = $row;
}

$logs_result = $db->query("SELECT * FROM operation_logs ORDER BY create_time DESC LIMIT 50");
$logs = array();
while ($row = $logs_result->fetch_assoc()) {
    $logs[] = $row;
}

// 获取全局统计
$global_stats = $stats->getGlobalStats();

include 'header.php';
?>

<!-- 统计卡片 -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="icon">📱</div>
        <div class="number"><?php echo count($apps); ?></div>
        <div class="label">应用总数</div>
    </div>
    <div class="stat-card">
        <div class="icon">📥</div>
        <div class="number"><?php echo $global_stats['total_downloads']; ?></div>
        <div class="label">总下载量</div>
    </div>
    <div class="stat-card">
        <div class="icon">✅</div>
        <div class="number"><?php echo $global_stats['total_installs']; ?></div>
        <div class="label">总安装量</div>
    </div>
    <div class="stat-card">
        <div class="icon">📊</div>
        <div class="number"><?php echo is_numeric($global_stats['today_downloads']) ? $global_stats['today_downloads'] : $global_stats['today_downloads']; ?></div>
        <div class="label">今日下载</div>
    </div>
    <div class="stat-card">
        <div class="icon">⚠️</div>
        <div class="number"><?php echo count(array_filter($reports, function($r) { return isset($r['status']) && $r['status'] === 'pending'; })); ?></div>
        <div class="label">待处理举报</div>
    </div>
    <div class="stat-card">
        <div class="icon">🚫</div>
        <div class="number"><?php echo count(array_filter($apps, function($a) { return isset($a['is_violation']) && $a['is_violation'] == 1; })); ?></div>
        <div class="label">违规应用</div>
    </div>
</div>

<!-- 热门应用 -->
<?php if (!empty($global_stats['top_apps'])): ?>
<div class="card">
    <div class="card-header">
        <h2>🔥 热门应用</h2>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th>应用名称</th>
                    <th>平台</th>
                    <th>下载量</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($global_stats['top_apps'] as $index => $app): ?>
                <tr>
                    <td style="text-align: center; font-weight: 600;"><?php echo $index + 1; ?></td>
                    <td><?php echo htmlspecialchars($app['app_name']); ?></td>
                    <td><?php echo getPlatformIcon($app['platform']); ?></td>
                    <td><?php echo $app['download_count']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 最近应用 -->
<div class="card">
    <div class="card-header">
        <h2>📱 最近上传</h2>
        <a href="?page=apps" class="btn btn-sm btn-outline">查看全部</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>应用名称</th>
                    <th>平台</th>
                    <th>版本</th>
                    <th>下载次数</th>
                    <th>上传时间</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($apps, 0, 10) as $app): ?>
                <tr>
                    <td><?php echo htmlspecialchars($app['app_name']); ?></td>
                    <td><?php echo getPlatformIcon($app['platform']); ?></td>
                    <td><?php echo htmlspecialchars($app['version']); ?></td>
                    <td><?php echo $app['download_count']; ?></td>
                    <td><?php echo isset($app['create_time']) ? $app['create_time'] : ''; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 最近日志 -->
<div class="card">
    <div class="card-header">
        <h2>📋 最近操作</h2>
        <a href="?page=logs" class="btn btn-sm btn-outline">查看全部</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>时间</th>
                    <th>操作</th>
                    <th>描述</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($logs, 0, 10) as $log): ?>
                <tr>
                    <td><?php echo safeOutput($log['create_time'] ?? ''); ?></td>
                    <td><?php echo safeOutput($log['action'] ?? ''); ?></td>
                    <td><?php echo safeOutput($log['description'] ?? ''); ?></td>
                    <td><?php echo safeOutput($log['ip'] ?? ''); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
