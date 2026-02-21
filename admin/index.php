<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 后台管理主入口

// 定义常量
define('ADMIN_LOADED', true);

// 检查是否已安装
if (!file_exists(__DIR__ . '/../includes/installed.lock')) {
    header('Location: ../setup.php');
    exit;
}

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 加载配置
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../includes/AppStats.php';

// 初始化统计
$stats = new AppStats($db);

// 加载认证模块
require_once __DIR__ . '/auth.php';

// 检查登录状态
if (!isLoggedIn()) {
    // 显示登录页面
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title>登录 - 后台管理</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .login-box {
                background: white;
                padding: 40px 30px;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                width: 100%;
                max-width: 400px;
            }
            h1 { text-align: center; margin-bottom: 30px; color: #333; font-size: 24px; }
            .form-group { margin-bottom: 20px; }
            .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px; }
            .form-group input {
                width: 100%;
                padding: 14px 12px;
                border: 1px solid #ddd;
                border-radius: 10px;
                font-size: 16px;
                transition: all 0.2s;
            }
            .form-group input:focus { 
                outline: none; 
                border-color: #667eea; 
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            .btn {
                width: 100%;
                padding: 14px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
            }
            .btn:active {
                transform: scale(0.98);
            }
            .error {
                background: #fee2e2;
                color: #991b1b;
                padding: 12px;
                border-radius: 10px;
                margin-bottom: 20px;
                text-align: center;
                font-size: 14px;
            }
            .hint {
                text-align: center;
                margin-top: 20px;
                color: #666;
                font-size: 13px;
            }
            
            @media (max-width: 480px) {
                .login-box {
                    padding: 30px 20px;
                }
                h1 {
                    font-size: 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔐 后台登录</h1>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="form-group">
                    <label>用户名</label>
                    <input type="text" name="username" placeholder="请输入用户名" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label>密码</label>
                    <input type="password" name="password" placeholder="请输入密码" required autocomplete="current-password">
                </div>
                <button type="submit" name="login" class="btn">登 录</button>
            </form>
            <p class="hint">首次安装请使用安装时设置的账号密码</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 获取当前页面
$current_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// 页面映射
$pages = array(
    'dashboard' => 'dashboard.php',
    'apps' => 'apps.php',
    'reports' => 'reports.php',
    'announcement' => 'announcement.php',
    'ads' => 'ads.php',
    'logs' => 'logs.php',
    'settings' => 'settings.php',
    'update' => 'update.php'
);

// 验证页面是否存在
if (!isset($pages[$current_page])) {
    $current_page = 'dashboard';
}

// 加载页面
require_once __DIR__ . '/' . $pages[$current_page];
?>
