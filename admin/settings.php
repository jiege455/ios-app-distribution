<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 系统设置模块

$page_title = '系统设置';

$config_file = dirname(__DIR__) . '/data/site_config.json';
$storage_config_file = dirname(__DIR__) . '/data/storage_config.json';

// 处理网站设置
if (isset($_POST['save_site'])) {
    $site_config = array(
        'site_name' => isset($_POST['site_name']) ? trim($_POST['site_name']) : 'iOS应用分发平台',
        'site_url' => isset($_POST['site_url']) ? rtrim(trim($_POST['site_url']), '/') : '',
        'site_description' => isset($_POST['site_description']) ? trim($_POST['site_description']) : '',
        'site_keywords' => isset($_POST['site_keywords']) ? trim($_POST['site_keywords']) : '',
        'announcement' => isset($_POST['announcement']) ? trim($_POST['announcement']) : '',
        'announcement_enabled' => isset($_POST['announcement_enabled']) ? true : false,
        'footer_text' => isset($_POST['footer_text']) ? trim($_POST['footer_text']) : '',
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? true : false,
        'maintenance_message' => isset($_POST['maintenance_message']) ? trim($_POST['maintenance_message']) : '系统维护中，请稍后访问',
        'ad_home_top' => isset($_POST['ad_home_top']) ? trim($_POST['ad_home_top']) : '',
        'ad_home_top_enabled' => isset($_POST['ad_home_top_enabled']) ? true : false,
        'ad_install_top' => isset($_POST['ad_install_top']) ? trim($_POST['ad_install_top']) : '',
        'ad_install_top_enabled' => isset($_POST['ad_install_top_enabled']) ? true : false,
        'ad_install_bottom' => isset($_POST['ad_install_bottom']) ? trim($_POST['ad_install_bottom']) : '',
        'ad_install_bottom_enabled' => isset($_POST['ad_install_bottom_enabled']) ? true : false,
        'keep_days' => isset($_POST['keep_days']) ? intval($_POST['keep_days']) : 30,
    );
    
    if (file_put_contents($config_file, json_encode($site_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        logOperation($db, 'save_site', '保存网站设置');
        $success = '设置保存成功';
        $config['site_name'] = $site_config['site_name'];
        $config['site_url'] = $site_config['site_url'];
        $config['site_description'] = $site_config['site_description'];
        $config['site_keywords'] = $site_config['site_keywords'];
        $config['announcement'] = $site_config['announcement'];
        $config['announcement_enabled'] = $site_config['announcement_enabled'];
        $config['footer_text'] = $site_config['footer_text'];
        $config['maintenance_mode'] = $site_config['maintenance_mode'];
        $config['maintenance_message'] = $site_config['maintenance_message'];
        $config['ad_home_top'] = $site_config['ad_home_top'];
        $config['ad_home_top_enabled'] = $site_config['ad_home_top_enabled'];
        $config['ad_install_top'] = $site_config['ad_install_top'];
        $config['ad_install_top_enabled'] = $site_config['ad_install_top_enabled'];
        $config['ad_install_bottom'] = $site_config['ad_install_bottom'];
        $config['ad_install_bottom_enabled'] = $site_config['ad_install_bottom_enabled'];
        $config['keep_days'] = $site_config['keep_days'];
    } else {
        $error = '设置保存失败';
    }
}

// 处理存储设置
if (isset($_POST['save_storage'])) {
    $storage_config = array(
        'storage_type' => isset($_POST['storage_type']) ? $_POST['storage_type'] : 'local',
        'local' => array(
            'upload_dir' => 'uploads',
        ),
        'qiniu' => array(
            'access_key' => isset($_POST['qiniu_access_key']) ? trim($_POST['qiniu_access_key']) : '',
            'secret_key' => isset($_POST['qiniu_secret_key']) ? trim($_POST['qiniu_secret_key']) : '',
            'bucket' => isset($_POST['qiniu_bucket']) ? trim($_POST['qiniu_bucket']) : '',
            'domain' => isset($_POST['qiniu_domain']) ? rtrim(trim($_POST['qiniu_domain']), '/') : '',
            'region' => 'auto',
        ),
        'aliyun_oss' => array(
            'access_key_id' => isset($_POST['aliyun_access_key_id']) ? trim($_POST['aliyun_access_key_id']) : '',
            'access_key_secret' => isset($_POST['aliyun_access_key_secret']) ? trim($_POST['aliyun_access_key_secret']) : '',
            'bucket' => isset($_POST['aliyun_bucket']) ? trim($_POST['aliyun_bucket']) : '',
            'endpoint' => isset($_POST['aliyun_endpoint']) ? trim($_POST['aliyun_endpoint']) : '',
            'domain' => isset($_POST['aliyun_domain']) ? rtrim(trim($_POST['aliyun_domain']), '/') : '',
        ),
        'tencent_cos' => array(
            'secret_id' => isset($_POST['tencent_secret_id']) ? trim($_POST['tencent_secret_id']) : '',
            'secret_key' => isset($_POST['tencent_secret_key']) ? trim($_POST['tencent_secret_key']) : '',
            'bucket' => isset($_POST['tencent_bucket']) ? trim($_POST['tencent_bucket']) : '',
            'region' => isset($_POST['tencent_region']) ? trim($_POST['tencent_region']) : '',
            'domain' => isset($_POST['tencent_domain']) ? rtrim(trim($_POST['tencent_domain']), '/') : '',
        ),
        'ftp' => array(
            'host' => isset($_POST['ftp_host']) ? trim($_POST['ftp_host']) : '',
            'port' => isset($_POST['ftp_port']) ? (int)$_POST['ftp_port'] : 21,
            'username' => isset($_POST['ftp_username']) ? trim($_POST['ftp_username']) : '',
            'password' => isset($_POST['ftp_password']) ? $_POST['ftp_password'] : '',
            'path' => isset($_POST['ftp_path']) ? trim($_POST['ftp_path']) : '/uploads',
        ),
    );
    
    if (file_put_contents($storage_config_file, json_encode($storage_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        logOperation($db, 'save_storage', '保存存储设置');
        $success = '存储设置保存成功';
        $config['storage'] = $storage_config;
    } else {
        $error = '存储设置保存失败';
    }
}

// 处理清理
if (isset($_POST['cleanup'])) {
    $result = cleanupAllFiles($db);
    logOperation($db, 'cleanup', '清理全部数据: ' . $result['count'] . '个, 大小: ' . formatFileSize($result['size']));
    $success = '清理完成！删除 ' . $result['count'] . ' 个应用，释放 ' . formatFileSize($result['size']) . ' 空间';
}

// 处理清理缓存
if (isset($_POST['clear_cache'])) {
    $cache_messages = clearSystemCache();
    logOperation($db, 'clear_cache', '清理缓存: ' . implode(', ', $cache_messages));
    $success = '缓存清理完成！已清理: ' . implode(', ', $cache_messages);
}

// 当前标签
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'site';

include 'header.php';
?>

<style>
.settings-tabs {
    display: flex;
    gap: 5px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.tab-btn {
    padding: 10px 20px;
    border: none;
    background: white;
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}
.tab-btn:hover {
    background: var(--gray-100);
}
.tab-btn.active {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}
.storage-config {
    display: none;
    padding: 20px;
    background: var(--gray-50);
    border-radius: 10px;
    margin-top: 15px;
}
.storage-config.active {
    display: block;
}

@media (max-width: 767px) {
    .settings-tabs {
        gap: 5px;
    }
    .tab-btn {
        padding: 8px 12px;
        font-size: 13px;
        flex: 1;
        justify-content: center;
        min-width: calc(50% - 5px);
    }
}
</style>

<div class="settings-tabs">
    <button class="tab-btn <?php echo $current_tab === 'site' ? 'active' : ''; ?>" onclick="switchTab('site')">
        ⚙️ 网站设置
    </button>
    <button class="tab-btn <?php echo $current_tab === 'storage' ? 'active' : ''; ?>" onclick="switchTab('storage')">
        ☁️ 存储设置
    </button>
    <button class="tab-btn <?php echo $current_tab === 'maintenance' ? 'active' : ''; ?>" onclick="switchTab('maintenance')">
        ⏰ 定时清理
    </button>
    <button class="tab-btn <?php echo $current_tab === 'info' ? 'active' : ''; ?>" onclick="switchTab('info')">
        📊 系统信息
    </button>
</div>

<!-- 网站设置 -->
<div id="tab-site" class="tab-content <?php echo $current_tab === 'site' ? 'active' : ''; ?>">
    <div class="card">
        <div class="card-header">
            <h2>⚙️ 网站基本设置</h2>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="form-group">
                    <label>网站名称</label>
                    <input type="text" name="site_name" value="<?php echo htmlspecialchars($config['site_name']); ?>">
                </div>
                <div class="form-group">
                    <label>网站URL</label>
                    <input type="url" name="site_url" value="<?php echo htmlspecialchars($config['site_url']); ?>" placeholder="https://example.com">
                </div>
                <div class="form-group">
                    <label>网站描述</label>
                    <textarea name="site_description" rows="2"><?php echo htmlspecialchars($config['site_description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label>网站关键词</label>
                    <input type="text" name="site_keywords" value="<?php echo htmlspecialchars($config['site_keywords'] ?? ''); ?>" placeholder="关键词1,关键词2,关键词3">
                </div>
                <div class="form-group">
                    <label>页脚文字</label>
                    <input type="text" name="footer_text" value="<?php echo htmlspecialchars($config['footer_text'] ?? ''); ?>">
                </div>
                
                <div style="padding: 20px; background: <?php echo ($config['maintenance_mode'] ?? false) ? '#fee2e2' : 'var(--gray-50)'; ?>; border-radius: 10px; margin-bottom: 20px; border-left: 4px solid <?php echo ($config['maintenance_mode'] ?? false) ? 'var(--danger)' : 'var(--gray-300)'; ?>;">
                    <div class="form-group" style="margin-bottom: 15px;">
                        <label class="form-check" style="display: flex; align-items: center; gap: 10px;">
                            <input type="checkbox" name="maintenance_mode" <?php echo ($config['maintenance_mode'] ?? false) ? 'checked' : ''; ?> style="width: 20px; height: 20px;">
                            <div>
                                <strong style="font-size: 15px; color: <?php echo ($config['maintenance_mode'] ?? false) ? 'var(--danger)' : 'inherit'; ?>;">🔧 开启维护模式</strong>
                                <div style="font-size: 12px; color: var(--gray-500); margin-top: 3px;">开启后前端将无法访问，仅后台可正常使用</div>
                            </div>
                        </label>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>维护提示信息</label>
                        <input type="text" name="maintenance_message" value="<?php echo htmlspecialchars($config['maintenance_message'] ?? '系统维护中，请稍后访问'); ?>" placeholder="系统维护中，请稍后访问">
                    </div>
                </div>
                
                <button type="submit" name="save_site" class="btn btn-primary">保存设置</button>
            </form>
        </div>
    </div>
</div>

<!-- 存储设置 -->
<div id="tab-storage" class="tab-content <?php echo $current_tab === 'storage' ? 'active' : ''; ?>">
    <div class="card">
        <div class="card-header">
            <h2>☁️ 存储设置</h2>
        </div>
        <div class="card-body">
            <form method="post">
                <div class="form-group">
                    <label>存储方式</label>
                    <select name="storage_type" id="storage_type" onchange="toggleStorageConfig(this.value)" style="font-size: 14px; padding: 12px;">
                        <option value="local" <?php echo ($config['storage']['storage_type'] ?? 'local') === 'local' ? 'selected' : ''; ?>>本地存储</option>
                        <option value="qiniu" <?php echo ($config['storage']['storage_type'] ?? '') === 'qiniu' ? 'selected' : ''; ?>>七牛云</option>
                        <option value="aliyun_oss" <?php echo ($config['storage']['storage_type'] ?? '') === 'aliyun_oss' ? 'selected' : ''; ?>>阿里云OSS</option>
                        <option value="tencent_cos" <?php echo ($config['storage']['storage_type'] ?? '') === 'tencent_cos' ? 'selected' : ''; ?>>腾讯云COS</option>
                        <option value="ftp" <?php echo ($config['storage']['storage_type'] ?? '') === 'ftp' ? 'selected' : ''; ?>>FTP</option>
                    </select>
                </div>
                
                <!-- 七牛云配置 -->
                <div id="qiniu_config" class="storage-config <?php echo ($config['storage']['storage_type'] ?? '') === 'qiniu' ? 'active' : ''; ?>">
                    <h3 style="margin-bottom: 15px; font-size: 15px;">七牛云配置</h3>
                    <div class="form-group">
                        <label>Access Key</label>
                        <input type="text" name="qiniu_access_key" value="<?php echo htmlspecialchars($config['storage']['qiniu']['access_key'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Secret Key</label>
                        <input type="password" name="qiniu_secret_key" value="<?php echo htmlspecialchars($config['storage']['qiniu']['secret_key'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Bucket（存储空间名称）</label>
                        <input type="text" name="qiniu_bucket" value="<?php echo htmlspecialchars($config['storage']['qiniu']['bucket'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>域名（如：http://cdn.example.com）</label>
                        <input type="text" name="qiniu_domain" value="<?php echo htmlspecialchars($config['storage']['qiniu']['domain'] ?? ''); ?>">
                    </div>
                </div>
                
                <!-- 阿里云OSS配置 -->
                <div id="aliyun_config" class="storage-config <?php echo ($config['storage']['storage_type'] ?? '') === 'aliyun_oss' ? 'active' : ''; ?>">
                    <h3 style="margin-bottom: 15px; font-size: 15px;">阿里云OSS配置</h3>
                    <div class="form-group">
                        <label>Access Key ID</label>
                        <input type="text" name="aliyun_access_key_id" value="<?php echo htmlspecialchars($config['storage']['aliyun_oss']['access_key_id'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Access Key Secret</label>
                        <input type="password" name="aliyun_access_key_secret" value="<?php echo htmlspecialchars($config['storage']['aliyun_oss']['access_key_secret'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Bucket（存储空间名称）</label>
                        <input type="text" name="aliyun_bucket" value="<?php echo htmlspecialchars($config['storage']['aliyun_oss']['bucket'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Endpoint（如：oss-cn-hangzhou.aliyuncs.com）</label>
                        <input type="text" name="aliyun_endpoint" value="<?php echo htmlspecialchars($config['storage']['aliyun_oss']['endpoint'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>自定义域名（可选）</label>
                        <input type="text" name="aliyun_domain" value="<?php echo htmlspecialchars($config['storage']['aliyun_oss']['domain'] ?? ''); ?>">
                    </div>
                </div>
                
                <!-- 腾讯云COS配置 -->
                <div id="tencent_config" class="storage-config <?php echo ($config['storage']['storage_type'] ?? '') === 'tencent_cos' ? 'active' : ''; ?>">
                    <h3 style="margin-bottom: 15px; font-size: 15px;">腾讯云COS配置</h3>
                    <div class="form-group">
                        <label>Secret ID</label>
                        <input type="text" name="tencent_secret_id" value="<?php echo htmlspecialchars($config['storage']['tencent_cos']['secret_id'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Secret Key</label>
                        <input type="password" name="tencent_secret_key" value="<?php echo htmlspecialchars($config['storage']['tencent_cos']['secret_key'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Bucket（存储桶名称）</label>
                        <input type="text" name="tencent_bucket" value="<?php echo htmlspecialchars($config['storage']['tencent_cos']['bucket'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Region（如：ap-guangzhou）</label>
                        <input type="text" name="tencent_region" value="<?php echo htmlspecialchars($config['storage']['tencent_cos']['region'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>自定义域名（可选）</label>
                        <input type="text" name="tencent_domain" value="<?php echo htmlspecialchars($config['storage']['tencent_cos']['domain'] ?? ''); ?>">
                    </div>
                </div>
                
                <!-- FTP配置 -->
                <div id="ftp_config" class="storage-config <?php echo ($config['storage']['storage_type'] ?? '') === 'ftp' ? 'active' : ''; ?>">
                    <h3 style="margin-bottom: 15px; font-size: 15px;">FTP配置</h3>
                    <div class="form-group">
                        <label>FTP服务器地址</label>
                        <input type="text" name="ftp_host" value="<?php echo htmlspecialchars($config['storage']['ftp']['host'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>端口</label>
                        <input type="number" name="ftp_port" value="<?php echo $config['storage']['ftp']['port'] ?? 21; ?>">
                    </div>
                    <div class="form-group">
                        <label>用户名</label>
                        <input type="text" name="ftp_username" value="<?php echo htmlspecialchars($config['storage']['ftp']['username'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>密码</label>
                        <input type="password" name="ftp_password" value="<?php echo htmlspecialchars($config['storage']['ftp']['password'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>上传路径</label>
                        <input type="text" name="ftp_path" value="<?php echo htmlspecialchars($config['storage']['ftp']['path'] ?? '/uploads'); ?>">
                    </div>
                </div>
                
                <button type="submit" name="save_storage" class="btn btn-primary" style="margin-top: 20px;">保存存储设置</button>
            </form>
        </div>
    </div>
</div>

<!-- 定时清理 -->
<div id="tab-maintenance" class="tab-content <?php echo $current_tab === 'maintenance' ? 'active' : ''; ?>">
    <div class="card">
        <div class="card-header">
            <h2>⏰ 定时清理</h2>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
                <form method="post" style="text-align: center;">
                    <div style="padding: 20px; background: var(--gray-50); border-radius: 10px;">
                        <div style="font-size: 32px; margin-bottom: 10px;">🧹</div>
                        <div style="font-weight: 600; margin-bottom: 5px;">清理全部数据</div>
                        <div style="font-size: 12px; color: var(--gray-500); margin-bottom: 15px;">删除所有应用和记录</div>
                        <button type="submit" name="cleanup" class="btn btn-primary" onclick="return confirm('⚠️ 确定要删除所有数据吗？此操作不可恢复！');">执行清理</button>
                    </div>
                </form>
                
                <form method="post" style="text-align: center;">
                    <div style="padding: 20px; background: var(--gray-50); border-radius: 10px;">
                        <div style="font-size: 32px; margin-bottom: 10px;">⚡</div>
                        <div style="font-weight: 600; margin-bottom: 5px;">清理缓存</div>
                        <div style="font-size: 12px; color: var(--gray-500); margin-bottom: 15px;">清理临时文件和缓存</div>
                        <button type="submit" name="clear_cache" class="btn btn-secondary" onclick="return confirm('确定要清理缓存吗？');">清理缓存</button>
                    </div>
                </form>
            </div>
            
            <div style="padding: 20px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-radius: 10px;">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <span style="font-size: 20px;">⏰</span>
                    <strong>定时清理设置（宝塔面板）</strong>
                </div>
                <p style="color: var(--gray-600); font-size: 13px; margin-bottom: 15px;">将以下URL添加到宝塔面板的定时任务中，访问即删除所有数据：</p>
                <?php
                $current_url = $config['site_url'];
                if (empty($current_url)) {
                    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
                    $current_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
                }
                ?>
                <div style="display: flex; align-items: center; gap: 10px; background: white; padding: 12px 15px; border-radius: 8px; border: 1px solid var(--gray-200);">
                    <code id="cleanupUrl" style="flex: 1; word-break: break-all; font-size: 12px;"><?php echo htmlspecialchars($current_url); ?>/cleanup.php?token=<?php echo md5($config['security']['secret_key']); ?></code>
                    <button type="button" class="btn btn-sm btn-primary" onclick="copyCleanupUrl()">复制</button>
                </div>
                <p style="color: var(--gray-500); font-size: 12px; margin-top: 10px;">
                    💡 宝塔面板 → 计划任务 → 添加任务 → 访问URL → 粘贴上面的链接
                </p>
            </div>
        </div>
    </div>
</div>

<!-- 系统信息 -->
<div id="tab-info" class="tab-content <?php echo $current_tab === 'info' ? 'active' : ''; ?>">
    <div class="card">
        <div class="card-header">
            <h2>📊 系统信息</h2>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                <div style="padding: 15px; background: var(--gray-50); border-radius: 10px;">
                    <div style="font-size: 12px; color: var(--gray-500); margin-bottom: 5px;">PHP版本</div>
                    <div style="font-size: 18px; font-weight: 600;"><?php echo phpversion(); ?></div>
                </div>
                <div style="padding: 15px; background: var(--gray-50); border-radius: 10px;">
                    <div style="font-size: 12px; color: var(--gray-500); margin-bottom: 5px;">MySQL版本</div>
                    <div style="font-size: 18px; font-weight: 600;"><?php echo $db->server_info ?? '未知'; ?></div>
                </div>
                <div style="padding: 15px; background: var(--gray-50); border-radius: 10px;">
                    <div style="font-size: 12px; color: var(--gray-500); margin-bottom: 5px;">最大上传限制</div>
                    <div style="font-size: 18px; font-weight: 600;"><?php echo ini_get('upload_max_filesize'); ?></div>
                </div>
                <div style="padding: 15px; background: var(--gray-50); border-radius: 10px;">
                    <div style="font-size: 12px; color: var(--gray-500); margin-bottom: 5px;">存储方式</div>
                    <div style="font-size: 18px; font-weight: 600;"><?php
                        $storage_type = $config['storage']['storage_type'] ?? 'local';
                        $storage_names = array('local' => '本地存储', 'qiniu' => '七牛云', 'aliyun_oss' => '阿里云OSS', 'tencent_cos' => '腾讯云COS', 'ftp' => 'FTP');
                        echo $storage_names[$storage_type] ?? $storage_type;
                    ?></div>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: var(--gray-50); border-radius: 10px;">
                <div style="font-size: 12px; color: var(--gray-500); margin-bottom: 5px;">上传目录</div>
                <div style="font-size: 14px; font-family: monospace; word-break: break-all;"><?php echo realpath(dirname(__DIR__) . '/uploads'); ?></div>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById('tab-' + tab).classList.add('active');
    event.target.classList.add('active');
    
    history.replaceState(null, '', '?page=settings&tab=' + tab);
}

function toggleStorageConfig(type) {
    document.querySelectorAll('.storage-config').forEach(el => el.classList.remove('active'));
    
    if (type === 'qiniu') {
        document.getElementById('qiniu_config').classList.add('active');
    } else if (type === 'aliyun_oss') {
        document.getElementById('aliyun_config').classList.add('active');
    } else if (type === 'tencent_cos') {
        document.getElementById('tencent_config').classList.add('active');
    } else if (type === 'ftp') {
        document.getElementById('ftp_config').classList.add('active');
    }
}
</script>

<?php include 'footer.php'; ?>
