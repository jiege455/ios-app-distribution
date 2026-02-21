<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 公告管理模块

$page_title = '公告管理';

$config_file = dirname(__DIR__) . '/data/site_config.json';

// 处理公告设置
if (isset($_POST['save_announcement'])) {
    $site_config = array(
        'site_name' => $config['site_name'],
        'site_url' => $config['site_url'],
        'site_description' => $config['site_description'] ?? '',
        'site_keywords' => $config['site_keywords'] ?? '',
        'announcement' => isset($_POST['announcement']) ? trim($_POST['announcement']) : '',
        'announcement_enabled' => isset($_POST['announcement_enabled']) ? true : false,
        'footer_text' => $config['footer_text'] ?? '',
        'ad_home_top' => $config['ad_home_top'] ?? '',
        'ad_home_top_enabled' => $config['ad_home_top_enabled'] ?? false,
        'ad_install_top' => $config['ad_install_top'] ?? '',
        'ad_install_top_enabled' => $config['ad_install_top_enabled'] ?? false,
        'ad_install_bottom' => $config['ad_install_bottom'] ?? '',
        'ad_install_bottom_enabled' => $config['ad_install_bottom_enabled'] ?? false,
    );
    
    if (file_put_contents($config_file, json_encode($site_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        logOperation($db, 'save_announcement', '保存公告设置');
        $success = '公告设置保存成功';
        $config['announcement'] = $site_config['announcement'];
        $config['announcement_enabled'] = $site_config['announcement_enabled'];
    } else {
        $error = '公告设置保存失败';
    }
}

include 'header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>📢 公告管理</h2>
    </div>
    <div class="card-body">
        <form method="post">
            <div class="form-group">
                <label class="form-check" style="display: flex; align-items: center; gap: 10px; padding: 15px; background: var(--gray-50); border-radius: 10px;">
                    <input type="checkbox" name="announcement_enabled" <?php echo ($config['announcement_enabled'] ?? false) ? 'checked' : ''; ?> style="width: 20px; height: 20px;">
                    <div>
                        <strong style="font-size: 15px;">启用公告</strong>
                        <div style="font-size: 12px; color: var(--gray-500); margin-top: 3px;">开启后公告将在首页顶部显示</div>
                    </div>
                </label>
            </div>
            
            <div class="form-group">
                <label>公告内容</label>
                <textarea name="announcement" rows="6" placeholder="输入公告内容，支持HTML标签" style="font-size: 14px; line-height: 1.6;"><?php echo htmlspecialchars($config['announcement'] ?? ''); ?></textarea>
                <p style="font-size: 12px; color: var(--gray-500); margin-top: 8px;">
                    💡 支持HTML标签，如 &lt;b&gt;加粗&lt;/b&gt;、&lt;a href="链接"&gt;文字&lt;/a&gt; 等
                </p>
            </div>
            
            <button type="submit" name="save_announcement" class="btn btn-primary">保存公告</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>📋 公告预览</h2>
    </div>
    <div class="card-body">
        <?php if (!empty($config['announcement_enabled']) && !empty($config['announcement'])): ?>
            <div style="padding: 15px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 10px; border-left: 4px solid #f59e0b;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <span>📢</span>
                    <strong>公告</strong>
                </div>
                <div style="font-size: 14px; line-height: 1.6;"><?php echo $config['announcement']; ?></div>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: var(--gray-500);">
                <div style="font-size: 48px; margin-bottom: 15px;">🔇</div>
                <p>公告未启用或内容为空</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>
