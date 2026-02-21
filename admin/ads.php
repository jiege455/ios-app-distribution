<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 广告管理模块

$page_title = '广告管理';

$config_file = dirname(__DIR__) . '/data/site_config.json';

// 处理广告设置
if (isset($_POST['save_ads'])) {
    $site_config = array(
        'site_name' => $config['site_name'],
        'site_url' => $config['site_url'],
        'site_description' => $config['site_description'] ?? '',
        'site_keywords' => $config['site_keywords'] ?? '',
        'announcement' => $config['announcement'] ?? '',
        'announcement_enabled' => $config['announcement_enabled'] ?? false,
        'footer_text' => $config['footer_text'] ?? '',
        'ad_home_top' => isset($_POST['ad_home_top']) ? trim($_POST['ad_home_top']) : '',
        'ad_home_top_enabled' => isset($_POST['ad_home_top_enabled']) ? true : false,
        'ad_install_top' => isset($_POST['ad_install_top']) ? trim($_POST['ad_install_top']) : '',
        'ad_install_top_enabled' => isset($_POST['ad_install_top_enabled']) ? true : false,
        'ad_install_bottom' => isset($_POST['ad_install_bottom']) ? trim($_POST['ad_install_bottom']) : '',
        'ad_install_bottom_enabled' => isset($_POST['ad_install_bottom_enabled']) ? true : false,
    );
    
    if (file_put_contents($config_file, json_encode($site_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        logOperation($db, 'save_ads', '保存广告设置');
        $success = '广告设置保存成功';
        $config['ad_home_top'] = $site_config['ad_home_top'];
        $config['ad_home_top_enabled'] = $site_config['ad_home_top_enabled'];
        $config['ad_install_top'] = $site_config['ad_install_top'];
        $config['ad_install_top_enabled'] = $site_config['ad_install_top_enabled'];
        $config['ad_install_bottom'] = $site_config['ad_install_bottom'];
        $config['ad_install_bottom_enabled'] = $site_config['ad_install_bottom_enabled'];
    } else {
        $error = '广告设置保存失败';
    }
}

include 'header.php';
?>

<style>
.ad-card {
    padding: 25px;
    background: var(--gray-50);
    border-radius: 12px;
    margin-bottom: 20px;
    border: 2px solid transparent;
    transition: all 0.2s;
}
.ad-card:hover {
    border-color: var(--primary);
}
.ad-card.disabled {
    opacity: 0.6;
}
.ad-card .header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}
.ad-card .title {
    display: flex;
    align-items: center;
    gap: 10px;
}
.ad-card .location {
    font-size: 12px;
    color: var(--gray-500);
    display: flex;
    align-items: center;
    gap: 5px;
}
.ad-card .switch {
    display: flex;
    align-items: center;
    gap: 10px;
}
.ad-card textarea {
    background: white;
    font-family: monospace;
}
</style>

<div class="card">
    <div class="card-header">
        <h2>💰 广告位管理</h2>
    </div>
    <div class="card-body">
        <form method="post">
            <!-- 首页顶部广告 -->
            <div class="ad-card <?php echo !($config['ad_home_top_enabled'] ?? false) ? 'disabled' : ''; ?>">
                <div class="header">
                    <div class="title">
                        <span style="font-size: 24px;">🏠</span>
                        <div>
                            <strong style="font-size: 16px;">首页顶部广告</strong>
                            <div class="location">📍 位置：首页顶部，应用列表上方</div>
                        </div>
                    </div>
                    <div class="switch">
                        <label class="form-check" style="margin: 0;">
                            <input type="checkbox" name="ad_home_top_enabled" <?php echo ($config['ad_home_top_enabled'] ?? false) ? 'checked' : ''; ?> onchange="toggleAdCard(this)">
                            <span>启用</span>
                        </label>
                    </div>
                </div>
                <textarea name="ad_home_top" rows="4" placeholder="输入广告代码（支持HTML/JS）"><?php echo htmlspecialchars($config['ad_home_top'] ?? ''); ?></textarea>
            </div>
            
            <!-- 安装页顶部广告 -->
            <div class="ad-card <?php echo !($config['ad_install_top_enabled'] ?? false) ? 'disabled' : ''; ?>">
                <div class="header">
                    <div class="title">
                        <span style="font-size: 24px;">📱</span>
                        <div>
                            <strong style="font-size: 16px;">安装页顶部广告</strong>
                            <div class="location">📍 位置：安装页面顶部，应用信息上方</div>
                        </div>
                    </div>
                    <div class="switch">
                        <label class="form-check" style="margin: 0;">
                            <input type="checkbox" name="ad_install_top_enabled" <?php echo ($config['ad_install_top_enabled'] ?? false) ? 'checked' : ''; ?> onchange="toggleAdCard(this)">
                            <span>启用</span>
                        </label>
                    </div>
                </div>
                <textarea name="ad_install_top" rows="4" placeholder="输入广告代码（支持HTML/JS）"><?php echo htmlspecialchars($config['ad_install_top'] ?? ''); ?></textarea>
            </div>
            
            <!-- 安装页底部广告 -->
            <div class="ad-card <?php echo !($config['ad_install_bottom_enabled'] ?? false) ? 'disabled' : ''; ?>">
                <div class="header">
                    <div class="title">
                        <span style="font-size: 24px;">📲</span>
                        <div>
                            <strong style="font-size: 16px;">安装页底部广告</strong>
                            <div class="location">📍 位置：安装页面底部，固定显示</div>
                        </div>
                    </div>
                    <div class="switch">
                        <label class="form-check" style="margin: 0;">
                            <input type="checkbox" name="ad_install_bottom_enabled" <?php echo ($config['ad_install_bottom_enabled'] ?? false) ? 'checked' : ''; ?> onchange="toggleAdCard(this)">
                            <span>启用</span>
                        </label>
                    </div>
                </div>
                <textarea name="ad_install_bottom" rows="4" placeholder="输入广告代码（支持HTML/JS）"><?php echo htmlspecialchars($config['ad_install_bottom'] ?? ''); ?></textarea>
            </div>
            
            <button type="submit" name="save_ads" class="btn btn-primary" style="margin-top: 10px;">保存广告设置</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>💡 广告代码示例</h2>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
            <div style="padding: 15px; background: var(--gray-50); border-radius: 10px;">
                <strong style="display: block; margin-bottom: 10px;">图片广告</strong>
                <code style="display: block; font-size: 12px; white-space: pre-wrap; background: white; padding: 10px; border-radius: 6px;">&lt;a href="链接地址"&gt;
  &lt;img src="图片地址" style="width:100%"&gt;
&lt;/a&gt;</code>
            </div>
            <div style="padding: 15px; background: var(--gray-50); border-radius: 10px;">
                <strong style="display: block; margin-bottom: 10px;">文字广告</strong>
                <code style="display: block; font-size: 12px; white-space: pre-wrap; background: white; padding: 10px; border-radius: 6px;">&lt;a href="链接地址" style="
  color:#667eea;
  font-weight:bold;
"&gt;广告文字&lt;/a&gt;</code>
            </div>
            <div style="padding: 15px; background: var(--gray-50); border-radius: 10px;">
                <strong style="display: block; margin-bottom: 10px;">JS广告代码</strong>
                <code style="display: block; font-size: 12px; white-space: pre-wrap; background: white; padding: 10px; border-radius: 6px;">&lt;script src="广告JS地址"&gt;
&lt;/script&gt;</code>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAdCard(checkbox) {
    var card = checkbox.closest('.ad-card');
    if (checkbox.checked) {
        card.classList.remove('disabled');
    } else {
        card.classList.add('disabled');
    }
}
</script>

<?php include 'footer.php'; ?>
