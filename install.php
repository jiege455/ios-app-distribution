<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 安装页面，支持响应式设计

// 检查是否已安装
if (!file_exists(__DIR__ . '/includes/installed.lock')) {
    header('Location: setup.php');
    exit;
}

// 加载配置
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/Storage.php';

// 检查维护模式
if (!empty($config['maintenance_mode'])) {
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>维护中 - <?php echo htmlspecialchars($config['site_name']); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
            .maintenance-box { background: white; padding: 50px 40px; border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); text-align: center; max-width: 500px; }
            .icon { font-size: 64px; margin-bottom: 20px; }
            h1 { color: #333; margin-bottom: 15px; font-size: 24px; }
            p { color: #666; font-size: 16px; line-height: 1.6; }
        </style>
    </head>
    <body>
        <div class="maintenance-box">
            <div class="icon">🔧</div>
            <h1>系统维护中</h1>
            <p><?php echo htmlspecialchars($config['maintenance_message'] ?? '系统维护中，请稍后访问'); ?></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 获取应用UID
$uid = isset($_GET['uid']) ? $_GET['uid'] : '';

if (empty($uid)) {
    die('无效的链接');
}

// 检查应用是否违规
$is_violation = false;
$violation_query = "SELECT is_violation FROM urls WHERE uid = '" . $db->real_escape_string($uid) . "'";
$violation_result = $db->query($violation_query);
if ($violation_result && $violation_row = $violation_result->fetch_assoc()) {
    $is_violation = (isset($violation_row['is_violation']) && $violation_row['is_violation'] == 1);
}

if ($is_violation) {
    http_response_code(404);
    die('<div style="text-align:center;padding:50px;font-family:Arial;"><h1>404</h1><p>该应用不存在或已被下架</p></div>');
}

// 查询应用信息
$app_info = null;
$query = "SELECT * FROM urls WHERE uid = '" . $db->real_escape_string($uid) . "'";
$result = $db->query($query);
if ($result && $row = $result->fetch_assoc()) {
    $app_info = $row;
}

if (!$app_info) {
    die('未找到应用');
}

// 检查密码
$password_required = !empty($app_info['password']);
$password_correct = true;

if ($password_required && isset($_POST['password'])) {
    $password_correct = $_POST['password'] === $app_info['password'];
    if (!$password_correct) {
        $error = '密码错误';
    }
} elseif ($password_required && !isset($_POST['password'])) {
    $password_correct = false;
}

// 生成安装链接
$install_url = '';
$storage = new Storage($config['storage']);
$file_url = $storage->getUrl($app_info['ipa_path']);

if ($app_info['platform'] === 'ios') {
    // 生成plist文件
    $plist_content = generatePlist($app_info, $file_url);
    $plist_file = __DIR__ . '/manifests/' . $uid . '.plist';
    file_put_contents($plist_file, $plist_content);
    $install_url = 'itms-services://?action=download-manifest&url=' . urlencode($config['site_url'] . '/manifests/' . $uid . '.plist');
} else {
    // Android直接下载
    $install_url = $file_url;
}

// 生成plist文件内容
function generatePlist($app_info, $file_url) {
    global $config;
    
    $app_url = $file_url;
    $display_name = htmlspecialchars($app_info['app_name']);
    $bundle_id = htmlspecialchars($app_info['bundle_id']);
    $version = htmlspecialchars($app_info['version']);
    
    $plist = <<<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>items</key>
    <array>
        <dict>
            <key>assets</key>
            <array>
                <dict>
                    <key>kind</key>
                    <string>software-package</string>
                    <key>url</key>
                    <string>$app_url</string>
                </dict>
            </array>
            <key>metadata</key>
            <dict>
                <key>bundle-identifier</key>
                <string>$bundle_id</string>
                <key>bundle-version</key>
                <string>$version</string>
                <key>kind</key>
                <string>software</string>
                <key>title</key>
                <string>$display_name</string>
            </dict>
        </dict>
    </array>
</dict>
</plist>
EOF;
    
    return $plist;
}

// 增加下载次数
if (!$password_required || ($password_required && $password_correct)) {
    $update_query = "UPDATE urls SET download_count = download_count + 1 WHERE uid = '" . $db->real_escape_string($uid) . "'";
    $db->query($update_query);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($app_info['app_name']); ?> - 安装</title>
    <style>
        /* 全局样式 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            display: flex;
            flex-direction: column;
        }
        
        /* 容器 */
        .container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        /* 安装卡片 */
        .install-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        /* 应用图标 */
        .app-icon {
            width: 120px;
            height: 120px;
            border-radius: 24px;
            background: #f0f0f0;
            margin: 0 auto 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 48px;
            color: #667eea;
            border: 2px solid #e0e0e0;
        }
        
        /* 应用信息 */
        .app-info {
            margin-bottom: 30px;
        }
        
        .app-info h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .app-info p {
            font-size: 16px;
            color: #666;
            margin-bottom: 5px;
        }
        
        /* 安装按钮 */
        .install-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 18px 40px;
            font-size: 18px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            width: 100%;
            margin-bottom: 20px;
            text-decoration: none;
            display: inline-block;
        }
        
        .install-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .install-btn.ios {
            background: linear-gradient(135deg, #5ac8fa 0%, #007aff 100%);
        }
        
        .install-btn.android {
            background: linear-gradient(135deg, #4cd964 0%, #34aadc 100%);
        }
        
        /* 密码表单 */
        .password-form {
            margin-bottom: 30px;
        }
        
        .password-form h3 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .password-form input {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .password-form button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px;
            font-size: 16px;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
        }
        
        .password-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* 错误信息 */
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        /* 举报按钮 */
        .report-btn {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 14px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .report-btn:hover {
            background: #ee5a52;
            transform: translateY(-2px);
        }
        
        /* 举报模态框 */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        
        .modal-content h3 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .modal-content .form-group {
            margin-bottom: 15px;
            text-align: left;
        }
        
        .modal-content label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }
        
        .modal-content input,
        .modal-content select,
        .modal-content textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .modal-content textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .modal-buttons {
            margin-top: 20px;
            text-align: right;
        }
        
        .modal-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            margin-left: 10px;
        }
        
        .modal-buttons .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .modal-buttons .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        /* 底部信息 */
        .footer {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            text-align: center;
            color: white;
            font-size: 14px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        
        .footer a {
            color: white;
            text-decoration: underline;
            transition: color 0.3s ease;
        }
        
        .footer a:hover {
            color: #f0f0f0;
        }
        
        .footer .infringement {
            margin-top: 10px;
            font-size: 12px;
            opacity: 0.8;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .install-card {
                padding: 30px 20px;
            }
            
            .app-icon {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }
            
            .app-info h1 {
                font-size: 24px;
            }
            
            .install-btn {
                padding: 15px 30px;
                font-size: 16px;
            }
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 10px;
            }
            
            .install-card {
                padding: 20px 15px;
            }
            
            .app-icon {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
            
            .app-info h1 {
                font-size: 20px;
            }
            
            .app-info p {
                font-size: 14px;
            }
        }
        
        /* 平板和iPad适配 */
        @media (min-width: 768px) and (max-width: 1024px) {
            .install-card {
                max-width: 600px;
                padding: 40px;
            }
            
            .app-icon {
                width: 140px;
                height: 140px;
                font-size: 56px;
            }
            
            .app-info h1 {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部广告 -->
    <?php if (!empty($config['ad_install_top_enabled']) && !empty($config['ad_install_top'])): ?>
    <div style="padding: 10px; text-align: center; background: rgba(255,255,255,0.9);">
        <?php echo $config['ad_install_top']; ?>
    </div>
    <?php endif; ?>
    
    <div class="container">
        <div class="install-card">
            <!-- 应用图标 -->
            <div class="app-icon">
                <?php 
                // 显示实际图标或默认emoji
                if (!empty($app_info['icon']) && file_exists(__DIR__ . '/' . $app_info['icon'])) {
                    echo '<img src="' . htmlspecialchars($config['site_url'] . '/' . $app_info['icon']) . '" alt="应用图标" style="width: 100%; height: 100%; object-fit: cover; border-radius: 22%;">';
                } else {
                    echo $app_info['platform'] === 'ios' ? '🍎' : '🤖';
                }
                ?>
            </div>
            
            <!-- 应用信息 -->
            <div class="app-info">
                <h1><?php echo htmlspecialchars($app_info['app_name']); ?></h1>
                <p>版本：<?php echo htmlspecialchars($app_info['version']); ?></p>
                <p>平台：<?php echo $app_info['platform'] === 'ios' ? 'iOS' : 'Android'; ?></p>
                <p>包名：<?php echo htmlspecialchars($app_info['bundle_id']); ?></p>
                <p>大小：<?php echo round($app_info['file_size'] / (1024 * 1024), 2); ?> MB</p>
                <p>下载次数：<?php echo htmlspecialchars($app_info['download_count']); ?></p>
            </div>
            
            <!-- 二维码 -->
            <div class="qrcode-section" style="margin: 20px 0; text-align: center;">
                <p style="font-size: 14px; color: #666; margin-bottom: 10px;">扫描二维码安装</p>
                <img src="qrcode.php?uid=<?php echo htmlspecialchars($app_info['uid']); ?>" alt="安装二维码" style="max-width: 150px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            </div>
            
            <!-- 密码保护 -->
            <?php if ($password_required && !$password_correct): ?>
                <div class="error">
                    <?php echo isset($error) ? $error : '请输入密码'; ?>
                </div>
                
                <form class="password-form" method="post">
                    <h3>需要密码</h3>
                    <input type="password" name="password" placeholder="请输入密码" required>
                    <button type="submit">确认</button>
                </form>
            <?php elseif (!$password_required || $password_correct): ?>
                <!-- 安装按钮 -->
                <?php if ($app_info['platform'] === 'ios'): ?>
                    <a href="<?php echo $install_url; ?>" class="install-btn ios">
                        安装应用
                    </a>
                    <p style="font-size: 14px; color: #666; margin-top: 10px;">
                        点击上方按钮开始安装，按照提示完成操作
                    </p>
                <?php else: ?>
                    <a href="<?php echo $file_url; ?>" class="install-btn android" download>
                        下载APK
                    </a>
                    <p style="font-size: 14px; color: #666; margin-top: 10px;">
                        下载完成后请打开文件进行安装
                    </p>
                <?php endif; ?>
                
                <!-- 举报按钮 -->
                <button class="report-btn" onclick="openReportModal()">
                    举报该应用
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 举报模态框 -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <h3>举报应用</h3>
            <form id="reportForm" method="post" action="api.php">
                <input type="hidden" name="action" value="report">
                <input type="hidden" name="app_uid" value="<?php echo htmlspecialchars($app_info['uid']); ?>">
                <input type="hidden" name="app_name" value="<?php echo htmlspecialchars($app_info['app_name']); ?>">
                <input type="hidden" name="app_version" value="<?php echo htmlspecialchars($app_info['version']); ?>">
                <input type="hidden" name="app_platform" value="<?php echo htmlspecialchars($app_info['platform']); ?>">
                <input type="hidden" name="app_bundle_id" value="<?php echo htmlspecialchars($app_info['bundle_id']); ?>">
                
                <div class="form-group">
                    <label for="report_type">举报类型</label>
                    <select id="report_type" name="report_type" required>
                        <option value="">请选择举报类型</option>
                        <option value="infringement">侵犯版权</option>
                        <option value="malware">恶意软件</option>
                        <option value="pornography">色情内容</option>
                        <option value="other">其他原因</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="report_desc">详细说明</label>
                    <textarea id="report_desc" name="report_desc" placeholder="请详细描述您的举报原因" required></textarea>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-secondary" onclick="closeReportModal()">取消</button>
                    <button type="submit" class="btn-primary">提交举报</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 底部信息 -->
    <div class="footer">
        <p>
            <a href="http://192.168.31.199" target="_blank">iOS应用分发平台</a>
        </p>
        <p class="infringement">
            本平台仅提供应用分发服务，不存储任何应用内容。如有侵权请联系我们删除。
        </p>
    </div>
    
    <script>
        // 举报模态框
        function openReportModal() {
            document.getElementById('reportModal').style.display = 'block';
        }
        
        function closeReportModal() {
            document.getElementById('reportModal').style.display = 'none';
        }
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modal = document.getElementById('reportModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        // 处理举报表单提交
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('举报成功，我们会尽快处理');
                    closeReportModal();
                } else {
                    alert('举报失败：' + data.message);
                }
            })
            .catch(error => {
                alert('举报失败，请稍后重试');
            });
        });
    </script>
    
    <!-- 底部广告 -->
    <?php if (!empty($config['ad_install_bottom_enabled']) && !empty($config['ad_install_bottom'])): ?>
    <div style="padding: 10px; text-align: center; background: rgba(255,255,255,0.9); position: fixed; bottom: 0; left: 0; right: 0; z-index: 1000;">
        <?php echo $config['ad_install_bottom']; ?>
    </div>
    <?php endif; ?>
</body>
</html>