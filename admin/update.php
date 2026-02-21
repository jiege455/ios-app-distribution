<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 系统更新模块

$page_title = '系统更新';

// 加载更新日志
$changelog_file = dirname(__DIR__) . '/data/changelog.json';
$changelog = array();
if (file_exists($changelog_file)) {
    $changelog = json_decode(file_get_contents($changelog_file), true);
}

// 检查更新
$update_info = null;
$check_error = null;

if (isset($_POST['check_update'])) {
    $current_version = $config['system']['version'] ?? '1.0.0';
    $update_url = $config['system']['update_url'] ?? '';
    
    if (!empty($update_url)) {
        $context = stream_context_create(array(
            'http' => array(
                'timeout' => 10,
                'method' => 'POST',
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'content' => http_build_query(array(
                    'version' => $current_version,
                    'domain' => $_SERVER['HTTP_HOST'] ?? 'unknown',
                ))
            )
        ));
        
        $response = @file_get_contents($update_url, false, $context);
        if ($response !== false) {
            $update_info = json_decode($response, true);
            if ($update_info && isset($update_info['success']) && $update_info['success']) {
                // 保存更新信息到临时文件
                file_put_contents(dirname(__DIR__) . '/data/update_info.json', json_encode($update_info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $check_error = $update_info['error'] ?? '检查更新失败';
            }
        } else {
            $check_error = '无法连接到更新服务器';
        }
    } else {
        $check_error = '未配置更新服务器地址';
    }
}

// 加载已保存的更新信息
$update_info_file = dirname(__DIR__) . '/data/update_info.json';
if (!$update_info && file_exists($update_info_file)) {
    $saved_info = json_decode(file_get_contents($update_info_file), true);
    if ($saved_info && isset($saved_info['expire_time']) && $saved_info['expire_time'] > time()) {
        $update_info = $saved_info;
    }
}

// 执行更新
$update_result = null;
if (isset($_POST['do_update']) && $update_info) {
    $backup_dir = dirname(__DIR__) . '/backup_' . date('YmdHis');
    
    // 创建备份目录
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }
    
    // 备份重要文件
    $backup_files = array(
        'data/site_config.json',
        'data/storage_config.json',
        'includes/config.php',
    );
    
    foreach ($backup_files as $file) {
        $src = dirname(__DIR__) . '/' . $file;
        $dst = $backup_dir . '/' . $file;
        if (file_exists($src)) {
            $dst_dir = dirname($dst);
            if (!is_dir($dst_dir)) {
                mkdir($dst_dir, 0755, true);
            }
            copy($src, $dst);
        }
    }
    
    // 下载更新包
    if (!empty($update_info['download_url'])) {
        $update_package = dirname(__DIR__) . '/data/update.zip';
        $download_result = @file_get_contents($update_info['download_url']);
        
        if ($download_result !== false) {
            file_put_contents($update_package, $download_result);
            
            // 解压更新包
            $zip = new ZipArchive;
            if ($zip->open($update_package) === TRUE) {
                $zip->extractTo(dirname(__DIR__));
                $zip->close();
                
                // 删除更新包
                unlink($update_package);
                
                // 同步更新日志到本地
                if (!empty($update_info['changelog'])) {
                    $new_version_entry = array(
                        'version' => $update_info['latest_version'],
                        'title' => $update_info['title'] ?? '系统更新',
                        'date' => date('Y-m-d'),
                        'updates' => $update_info['changelog']
                    );
                    
                    // 读取现有日志
                    $existing_changelog = array();
                    if (file_exists($changelog_file)) {
                        $existing_changelog = json_decode(file_get_contents($changelog_file), true) ?: array();
                    }
                    
                    // 检查是否已存在该版本
                    $version_exists = false;
                    foreach ($existing_changelog as $entry) {
                        if (isset($entry['version']) && $entry['version'] === $update_info['latest_version']) {
                            $version_exists = true;
                            break;
                        }
                    }
                    
                    // 如果不存在，添加到开头
                    if (!$version_exists) {
                        array_unshift($existing_changelog, $new_version_entry);
                        file_put_contents($changelog_file, json_encode($existing_changelog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }
                }
                
                // 删除更新信息
                if (file_exists($update_info_file)) {
                    unlink($update_info_file);
                }
                
                $update_result = array(
                    'success' => true,
                    'message' => '更新成功！备份文件保存在: ' . basename($backup_dir),
                    'new_version' => $update_info['latest_version'] ?? '未知'
                );
                
                logOperation($db, 'system_update', '系统更新到版本: ' . ($update_info['latest_version'] ?? '未知'));
            } else {
                $update_result = array(
                    'success' => false,
                    'message' => '更新包解压失败'
                );
            }
        } else {
            $update_result = array(
                'success' => false,
                'message' => '更新包下载失败'
            );
        }
    } else {
        $update_result = array(
            'success' => false,
            'message' => '更新包地址无效'
        );
    }
}

$current_version = $config['system']['version'] ?? '1.0.0';

include 'header.php';
?>

<style>
.version-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 20px;
}
.version-card .current {
    font-size: 14px;
    opacity: 0.8;
    margin-bottom: 5px;
}
.version-card .number {
    font-size: 36px;
    font-weight: 700;
    margin-bottom: 10px;
}
.version-card .author {
    font-size: 13px;
    opacity: 0.7;
}

.update-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 20px;
    box-shadow: var(--shadow);
}
.update-card .header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}
.update-card .title {
    font-size: 18px;
    font-weight: 600;
}
.update-card .badge-new {
    background: #10b981;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
}

.update-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.update-list li {
    padding: 12px 0;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.update-list li:last-child {
    border-bottom: none;
}
.update-list .icon {
    font-size: 18px;
    flex-shrink: 0;
}
.update-list .content {
    flex: 1;
}
.update-list .title {
    font-weight: 500;
    margin-bottom: 3px;
}
.update-list .desc {
    font-size: 13px;
    color: var(--gray-500);
}

.manual-steps {
    background: var(--gray-50);
    border-radius: 10px;
    padding: 20px;
    margin-top: 20px;
}
.manual-steps h4 {
    margin-bottom: 15px;
    font-size: 15px;
}
.manual-steps ol {
    margin: 0;
    padding-left: 20px;
}
.manual-steps li {
    margin-bottom: 10px;
    font-size: 14px;
    color: var(--gray-700);
}
</style>

<div class="version-card">
    <div class="current">当前版本</div>
    <div class="number">v<?php echo $current_version; ?></div>
    <div class="author">开发者：<?php echo $config['system']['author'] ?? '杰哥网络科技'; ?> | QQ: <?php echo $config['system']['qq'] ?? '2711793818'; ?></div>
</div>

<?php if ($update_result): ?>
<div class="message <?php echo $update_result['success'] ? 'success' : 'error'; ?>">
    <?php echo $update_result['success'] ? '✓' : '✕'; ?> <?php echo $update_result['message']; ?>
</div>
<?php endif; ?>

<?php if ($check_error): ?>
<div class="message error">✕ <?php echo $check_error; ?></div>
<?php endif; ?>

<div class="update-card">
    <div class="header">
        <span class="title">🔍 检查更新</span>
    </div>
    
    <form method="post">
        <p style="color: var(--gray-600); margin-bottom: 20px;">
            点击检查更新按钮，系统将自动检测是否有新版本可用。
        </p>
        <button type="submit" name="check_update" class="btn btn-primary">检查更新</button>
    </form>
</div>

<?php if ($update_info && isset($update_info['has_update']) && $update_info['has_update']): ?>
<div class="update-card">
    <div class="header">
        <span class="title">🎉 发现新版本</span>
        <span class="badge-new">v<?php echo $update_info['latest_version']; ?></span>
    </div>
    
    <?php if (!empty($update_info['changelog'])): ?>
    <h4 style="margin-bottom: 15px;">更新内容</h4>
    <ul class="update-list">
        <?php foreach ($update_info['changelog'] as $item): ?>
        <li>
            <span class="icon">
                <?php
                $icons = array(
                    'feature' => '✨',
                    'fix' => '🐛',
                    'security' => '🔒',
                    'performance' => '⚡',
                    'ui' => '🎨'
                );
                echo $icons[$item['type'] ?? 'feature'] ?? '📦';
                ?>
            </span>
            <div class="content">
                <div class="title"><?php echo htmlspecialchars($item['title'] ?? ''); ?></div>
                <div class="desc"><?php echo htmlspecialchars($item['desc'] ?? ''); ?></div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    
    <?php if (!empty($update_info['download_url'])): ?>
    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid var(--gray-200);">
        <form method="post" onsubmit="return confirm('确定要执行更新吗？更新前会自动备份重要数据。');">
            <button type="submit" name="do_update" class="btn btn-primary">一键更新</button>
            <a href="<?php echo $update_info['download_url']; ?>" class="btn btn-outline" style="margin-left: 10px;" target="_blank">手动下载</a>
        </form>
    </div>
    <?php else: ?>
    <div class="manual-steps">
        <h4>📦 手动更新步骤</h4>
        <ol>
            <li>下载最新版本的更新包</li>
            <li>解压更新包到网站目录</li>
            <li>访问网站检查是否正常</li>
            <li>如有问题，可使用备份文件恢复</li>
        </ol>
        <p style="margin-top: 15px;">
            <strong>获取更新：</strong>
            联系开发者 QQ: <?php echo $config['system']['qq'] ?? '2711793818'; ?>
        </p>
    </div>
    <?php endif; ?>
</div>
<?php elseif ($update_info && !$update_info['has_update']): ?>
<div class="update-card">
    <div style="text-align: center; padding: 30px;">
        <div style="font-size: 48px; margin-bottom: 15px;">✅</div>
        <h3 style="margin-bottom: 10px;">已是最新版本</h3>
        <p style="color: var(--gray-500);">当前版本 v<?php echo $current_version; ?> 已是最新版本</p>
    </div>
</div>
<?php endif; ?>

<div class="update-card">
    <div class="header">
        <span class="title">📋 更新说明</span>
    </div>
    
    <div style="color: var(--gray-600); font-size: 14px; line-height: 1.8;">
        <p><strong>自动更新：</strong>点击"一键更新"按钮，系统会自动下载并安装更新，同时备份重要数据。</p>
        <p><strong>手动更新：</strong>如果自动更新失败，可以手动下载更新包进行更新。</p>
        <p><strong>数据备份：</strong>每次更新前，系统会自动备份配置文件到 backup_时间戳 目录。</p>
        <p><strong>遇到问题：</strong>如更新过程中遇到问题，请联系开发者 QQ: <?php echo $config['system']['qq'] ?? '2711793818'; ?></p>
    </div>
</div>

<div class="update-card">
    <div class="header">
        <span class="title">📝 更新日志</span>
    </div>
    
    <div style="max-height: 400px; overflow-y: auto;">
        <?php if (!empty($changelog)): ?>
            <?php foreach ($changelog as $version): ?>
            <div style="margin-bottom: 20px; padding: 20px; background: var(--gray-50); border-radius: 10px; border-left: 4px solid var(--primary);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: white; padding: 4px 12px; border-radius: 15px; font-size: 12px; font-weight: 500;">v<?php echo $version['version']; ?></span>
                        <strong style="font-size: 15px;"><?php echo htmlspecialchars($version['title'] ?? ''); ?></strong>
                    </div>
                    <span style="color: var(--gray-500); font-size: 13px;"><?php echo $version['date'] ?? ''; ?></span>
                </div>
                <?php if (!empty($version['updates'])): ?>
                <ul style="list-style: none; padding: 0; margin: 0; font-size: 13px;">
                    <?php foreach ($version['updates'] as $update): ?>
                    <li style="padding: 6px 0; display: flex; gap: 10px; align-items: flex-start;">
                        <?php
                        $type_classes = array('feature' => 'badge-success', 'security' => 'badge-danger', 'fix' => 'badge-warning', 'performance' => 'badge-info');
                        $type_labels = array('feature' => '新功能', 'security' => '安全', 'fix' => '修复', 'performance' => '优化');
                        ?>
                        <span class="badge <?php echo $type_classes[$update['type']] ?? 'badge-info'; ?>" style="flex-shrink: 0;"><?php echo $type_labels[$update['type']] ?? $update['type']; ?></span>
                        <span><?php echo htmlspecialchars($update['content'] ?? ''); ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: var(--gray-500); text-align: center; padding: 40px;">暂无更新记录</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
