<?php
/**
 * 更新服务器管理后台
 * 
 * 用于管理版本更新配置，无需修改代码
 * 访问地址: http://your-server.com/update_admin.php
 * 
 * 开发者：杰哥网络科技
 * QQ: 2711793818
 */

// 配置文件路径
$config_file = __DIR__ . '/update_config.json';

// 统计数据文件路径
$stats_file = __DIR__ . '/install_stats.json';

// 加载配置
$config = array(
    'latest_version' => '1.0.0',
    'download_url' => '',
    'update_title' => '初始版本',
    'changelog' => array(),
    'update_time' => date('Y-m-d'),
    'admin_password' => 'admin123'
);

if (file_exists($config_file)) {
    $saved_config = json_decode(file_get_contents($config_file), true);
    if ($saved_config) {
        $config = array_merge($config, $saved_config);
    }
}

// 加载统计数据
$stats = array(
    'installs' => array(),
    'summary' => array(
        'total_installs' => 0,
        'last_update' => '-'
    )
);

if (file_exists($stats_file)) {
    $saved_stats = json_decode(file_get_contents($stats_file), true);
    if ($saved_stats) {
        $stats = array_merge($stats, $saved_stats);
    }
}

// 处理登录
$logged_in = false;
$error = '';
$success = '';

session_start();

if (isset($_POST['login'])) {
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    if ($password === $config['admin_password']) {
        $_SESSION['update_admin_logged_in'] = true;
        $logged_in = true;
    } else {
        $error = '密码错误';
    }
} elseif (isset($_SESSION['update_admin_logged_in']) && $_SESSION['update_admin_logged_in']) {
    $logged_in = true;
}

// 处理登出
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: update_admin.php');
    exit;
}

// 处理删除安装记录
if ($logged_in && isset($_GET['delete_install'])) {
    $install_key = preg_replace('/[^a-f0-9]/', '', $_GET['delete_install']); // 只允许md5格式的key
    if (!empty($install_key) && isset($stats['installs'][$install_key])) {
        unset($stats['installs'][$install_key]);
        $stats['summary']['total_installs'] = count($stats['installs']);
        file_put_contents($stats_file, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $success = '安装记录已删除';
    }
}

// 处理清空所有统计
if ($logged_in && isset($_POST['clear_stats'])) {
    $stats = array(
        'installs' => array(),
        'summary' => array(
            'total_installs' => 0,
            'last_update' => date('Y-m-d H:i:s')
        )
    );
    file_put_contents($stats_file, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $success = '统计数据已清空';
}

// 处理保存配置
if ($logged_in && isset($_POST['save_config'])) {
    $new_config = array(
        'latest_version' => isset($_POST['latest_version']) ? trim($_POST['latest_version']) : '1.0.0',
        'download_url' => isset($_POST['download_url']) ? trim($_POST['download_url']) : '',
        'update_title' => isset($_POST['update_title']) ? trim($_POST['update_title']) : '系统更新',
        'changelog' => array(),
        'update_time' => date('Y-m-d'),
        'admin_password' => isset($_POST['admin_password']) ? trim($_POST['admin_password']) : $config['admin_password']
    );
    
    // 处理更新日志
    if (isset($_POST['changelog_type']) && is_array($_POST['changelog_type'])) {
        for ($i = 0; $i < count($_POST['changelog_type']); $i++) {
            if (!empty($_POST['changelog_title'][$i])) {
                $new_config['changelog'][] = array(
                    'type' => $_POST['changelog_type'][$i],
                    'title' => $_POST['changelog_title'][$i],
                    'desc' => $_POST['changelog_desc'][$i]
                );
            }
        }
    }
    
    if (file_put_contents($config_file, json_encode($new_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        $success = '配置保存成功！';
        $config = $new_config;
    } else {
        $error = '配置保存失败，请检查文件权限';
    }
}

// 当前标签页
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'stats';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>更新服务器管理 - 杰哥网络科技</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
        }
        
        .card-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .card-header p {
            opacity: 0.8;
            font-size: 14px;
        }
        
        .card-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group input[type="url"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group .hint {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .changelog-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border: 1px solid #e0e0e0;
        }
        
        .changelog-item .row {
            display: flex;
            gap: 15px;
            margin-bottom: 10px;
        }
        
        .changelog-item .row:last-child {
            margin-bottom: 0;
        }
        
        .changelog-item select {
            width: 120px;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }
        
        .changelog-item input {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }
        
        .changelog-item textarea {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            resize: vertical;
            min-height: 60px;
        }
        
        .changelog-item .remove-btn {
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 15px;
            cursor: pointer;
        }
        
        .add-changelog-btn {
            background: #28a745;
            color: white;
            border: 2px dashed #28a745;
            border-radius: 10px;
            padding: 15px;
            width: 100%;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .add-changelog-btn:hover {
            background: #218838;
            border-color: #218838;
        }
        
        .login-form {
            max-width: 400px;
            margin: 0 auto;
            padding: 40px;
        }
        
        .login-form h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .logout-btn {
            color: white;
            text-decoration: none;
            opacity: 0.8;
        }
        
        .logout-btn:hover {
            opacity: 1;
        }
        
        .version-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .api-info {
            background: #f0f4ff;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .api-info h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .api-info code {
            display: block;
            background: white;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 13px;
            color: #667eea;
            word-break: break-all;
        }
        
        /* 标签页样式 */
        .tabs {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 25px;
        }
        
        .tab {
            padding: 12px 25px;
            font-size: 15px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .tab:hover {
            color: #667eea;
        }
        
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        /* 统计卡片样式 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 25px;
            color: white;
            text-align: center;
        }
        
        .stat-card.green {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stat-card.orange {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stat-card.blue {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stat-card .number {
            font-size: 42px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card .label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        /* 安装列表样式 */
        .install-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .install-table th,
        .install-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .install-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .install-table tr:hover {
            background: #f8f9fa;
        }
        
        .install-table .domain {
            color: #667eea;
            font-weight: 600;
        }
        
        .install-table .version-tag {
            display: inline-block;
            background: #e0e0e0;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 12px;
        }
        
        .install-table .version-tag.latest {
            background: #d4edda;
            color: #155724;
        }
        
        .install-table .version-tag.old {
            background: #fff3cd;
            color: #856404;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }
        
        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 15px;
        }
        
        .search-box {
            margin-bottom: 20px;
        }
        
        .search-box input {
            width: 100%;
            max-width: 400px;
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        @media (max-width: 600px) {
            .changelog-item .row {
                flex-direction: column;
            }
            
            .changelog-item select {
                width: 100%;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .install-table {
                font-size: 13px;
            }
            
            .install-table th,
            .install-table td {
                padding: 8px 10px;
            }
            
            .tabs {
                overflow-x: auto;
            }
            
            .tab {
                padding: 10px 15px;
                font-size: 14px;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$logged_in): ?>
        <!-- 登录表单 -->
        <div class="card">
            <div class="card-header">
                <h1>🔐 更新服务器管理</h1>
                <p>请输入管理员密码登录</p>
            </div>
            <div class="card-body login-form">
                <?php if ($error): ?>
                <div class="message error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="form-group">
                        <label>管理员密码</label>
                        <input type="password" name="password" placeholder="请输入密码" required>
                    </div>
                    <button type="submit" name="login" class="btn" style="width: 100%;">登 录</button>
                </form>
                
                <p style="text-align: center; margin-top: 20px; color: #888; font-size: 13px;">
                    默认密码: admin123 (请登录后修改)
                </p>
            </div>
        </div>
        
        <?php else: ?>
        <!-- 管理界面 -->
        <div class="card">
            <div class="card-header">
                <div class="header-actions">
                    <div>
                        <h1>🚀 更新服务器管理</h1>
                        <p>当前版本: <span class="version-badge">v<?php echo htmlspecialchars($config['latest_version']); ?></span></p>
                    </div>
                    <a href="?logout" class="logout-btn">退出登录</a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($success): ?>
                <div class="message success">✓ <?php echo $success; ?></div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="message error">✕ <?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- 标签页导航 -->
                <div class="tabs">
                    <a href="?tab=stats" class="tab <?php echo $current_tab === 'stats' ? 'active' : ''; ?>">📊 安装统计</a>
                    <a href="?tab=config" class="tab <?php echo $current_tab === 'config' ? 'active' : ''; ?>">⚙️ 版本配置</a>
                </div>
                
                <?php if ($current_tab === 'stats'): ?>
                <!-- 安装统计页面 -->
                
                <!-- 统计卡片 -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="number"><?php echo number_format($stats['summary']['total_installs']); ?></div>
                        <div class="label">总安装数</div>
                    </div>
                    <div class="stat-card green">
                        <div class="number"><?php echo number_format(count(array_filter($stats['installs'], function($i) use ($config) { return $i['version'] === $config['latest_version']; }))); ?></div>
                        <div class="label">最新版本</div>
                    </div>
                    <div class="stat-card orange">
                        <div class="number"><?php echo number_format(count(array_filter($stats['installs'], function($i) use ($config) { return $i['version'] !== $config['latest_version']; }))); ?></div>
                        <div class="label">待更新</div>
                    </div>
                    <div class="stat-card blue">
                        <div class="number"><?php echo number_format(array_sum(array_column($stats['installs'], 'check_count'))); ?></div>
                        <div class="label">检查次数</div>
                    </div>
                </div>
                
                <!-- 搜索框 -->
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="🔍 搜索域名..." onkeyup="filterTable()">
                </div>
                
                <!-- 安装列表 -->
                <?php if (!empty($stats['installs'])): ?>
                <div style="overflow-x: auto;">
                    <table class="install-table" id="installTable">
                        <thead>
                            <tr>
                                <th>域名</th>
                                <th>版本</th>
                                <th>首次访问</th>
                                <th>最后访问</th>
                                <th>检查次数</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // 按最后访问时间排序
                            $sorted_installs = $stats['installs'];
                            usort($sorted_installs, function($a, $b) {
                                return strtotime($b['last_seen']) - strtotime($a['last_seen']);
                            });
                            
                            foreach ($sorted_installs as $key => $install): 
                                $is_latest = $install['version'] === $config['latest_version'];
                            ?>
                            <tr>
                                <td>
                                    <span class="domain"><?php echo htmlspecialchars($install['domain']); ?></span>
                                </td>
                                <td>
                                    <span class="version-tag <?php echo $is_latest ? 'latest' : 'old'; ?>">
                                        v<?php echo htmlspecialchars($install['version']); ?>
                                        <?php if (!$is_latest): ?>⚠️<?php endif; ?>
                                    </span>
                                </td>
                                <td><?php echo $install['first_seen']; ?></td>
                                <td><?php echo $install['last_seen']; ?></td>
                                <td><?php echo number_format($install['check_count']); ?></td>
                                <td>
                                    <a href="?tab=stats&delete_install=<?php echo md5($install['domain']); ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('确定要删除此安装记录吗？');">
                                        删除
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top: 20px; text-align: right;">
                    <form method="post" style="display: inline;" onsubmit="return confirm('确定要清空所有统计数据吗？此操作不可恢复！');">
                        <input type="hidden" name="clear_stats" value="1">
                        <button type="submit" class="btn btn-danger btn-sm">清空所有统计</button>
                    </form>
                </div>
                
                <?php else: ?>
                <div class="empty-state">
                    <div class="icon">📭</div>
                    <p>暂无安装记录</p>
                    <p style="font-size: 13px; margin-top: 10px;">当用户系统检查更新时，会自动记录安装信息</p>
                </div>
                <?php endif; ?>
                
                <?php elseif ($current_tab === 'config'): ?>
                <!-- 版本配置页面 -->
                
                <!-- API信息 -->
                <div class="api-info">
                    <h3>📡 API地址</h3>
                    <p style="margin-bottom: 10px; font-size: 14px; color: #666;">将此地址配置到用户系统的 config.php 中：</p>
                    <code><?php echo (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/update_api.php'; ?></code>
                </div>
                
                <form method="post" id="configForm">
                    <!-- 版本信息 -->
                    <div class="form-group">
                        <label>最新版本号</label>
                        <input type="text" name="latest_version" value="<?php echo htmlspecialchars($config['latest_version']); ?>" placeholder="例如: 1.1.0" required>
                        <div class="hint">使用三段式版本号，如: 1.0.0, 1.1.0, 2.0.0</div>
                    </div>
                    
                    <div class="form-group">
                        <label>更新标题</label>
                        <input type="text" name="update_title" value="<?php echo htmlspecialchars($config['update_title']); ?>" placeholder="例如: 功能更新">
                        <div class="hint">本次更新的简短标题</div>
                    </div>
                    
                    <div class="form-group">
                        <label>更新包下载地址</label>
                        <input type="url" name="download_url" value="<?php echo htmlspecialchars($config['download_url']); ?>" placeholder="https://your-server.com/update.zip">
                        <div class="hint">留空则用户需要手动联系您获取更新包</div>
                    </div>
                    
                    <!-- 更新日志 -->
                    <div class="form-group">
                        <label>更新日志</label>
                        <div id="changelogList">
                            <?php if (!empty($config['changelog'])): ?>
                                <?php foreach ($config['changelog'] as $index => $item): ?>
                                <div class="changelog-item">
                                    <div class="row">
                                        <select name="changelog_type[]">
                                            <option value="feature" <?php echo ($item['type'] ?? '') === 'feature' ? 'selected' : ''; ?>>✨ 新功能</option>
                                            <option value="fix" <?php echo ($item['type'] ?? '') === 'fix' ? 'selected' : ''; ?>>🐛 修复</option>
                                            <option value="security" <?php echo ($item['type'] ?? '') === 'security' ? 'selected' : ''; ?>>🔒 安全</option>
                                            <option value="performance" <?php echo ($item['type'] ?? '') === 'performance' ? 'selected' : ''; ?>>⚡ 优化</option>
                                            <option value="ui" <?php echo ($item['type'] ?? '') === 'ui' ? 'selected' : ''; ?>>🎨 界面</option>
                                        </select>
                                        <input type="text" name="changelog_title[]" value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" placeholder="更新标题">
                                        <button type="button" class="remove-btn" onclick="removeChangelog(this)">删除</button>
                                    </div>
                                    <textarea name="changelog_desc[]" placeholder="详细说明"><?php echo htmlspecialchars($item['desc'] ?? ''); ?></textarea>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="add-changelog-btn" onclick="addChangelog()">
                            + 添加更新内容
                        </button>
                    </div>
                    
                    <hr style="margin: 30px 0; border: none; border-top: 1px solid #e0e0e0;">
                    
                    <!-- 安全设置 -->
                    <div class="form-group">
                        <label>管理员密码</label>
                        <input type="password" name="admin_password" value="<?php echo htmlspecialchars($config['admin_password']); ?>" placeholder="留空则不修改">
                        <div class="hint">请设置复杂的密码保护后台安全</div>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <button type="submit" name="save_config" class="btn">💾 保存配置</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        
        <div style="text-align: center; color: rgba(255,255,255,0.8); font-size: 13px;">
            开发者：杰哥网络科技 | QQ: 2711793818
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    function addChangelog() {
        const list = document.getElementById('changelogList');
        const item = document.createElement('div');
        item.className = 'changelog-item';
        item.innerHTML = `
            <div class="row">
                <select name="changelog_type[]">
                    <option value="feature">✨ 新功能</option>
                    <option value="fix">🐛 修复</option>
                    <option value="security">🔒 安全</option>
                    <option value="performance">⚡ 优化</option>
                    <option value="ui">🎨 界面</option>
                </select>
                <input type="text" name="changelog_title[]" placeholder="更新标题">
                <button type="button" class="remove-btn" onclick="removeChangelog(this)">删除</button>
            </div>
            <textarea name="changelog_desc[]" placeholder="详细说明"></textarea>
        `;
        list.appendChild(item);
    }
    
    function removeChangelog(btn) {
        const item = btn.closest('.changelog-item');
        if (item) {
            item.remove();
        }
    }
    
    function filterTable() {
        const input = document.getElementById('searchInput');
        const filter = input.value.toLowerCase();
        const table = document.getElementById('installTable');
        const rows = table.getElementsByTagName('tr');
        
        for (let i = 1; i < rows.length; i++) {
            const domain = rows[i].getElementsByTagName('td')[0];
            if (domain) {
                const text = domain.textContent || domain.innerText;
                if (text.toLowerCase().indexOf(filter) > -1) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
    }
    </script>
</body>
</html>
