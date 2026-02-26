<?php
// 开发者：杰哥网络科技
//2711793818
// 一键安装功能 - 完整版

// 检查是否已安装
if (file_exists(__DIR__ . '/includes/installed.lock')) {
    die('<div style="text-align:center;padding:50px;font-family:Arial;"><h1>系统已安装</h1><p>如需重新安装，请删除 includes/installed.lock 文件</p><p><a href="index.php">返回首页</a> | <a href="admin.php">进入后台</a></p></div>');
}

// 环境检查函数
function checkEnvironment() {
    $errors = array();
    $warnings = array();
    
    // PHP版本检查
    if (version_compare(PHP_VERSION, '7.0.0', '<')) {
        $errors[] = 'PHP版本必须 >= 7.0，当前版本：' . PHP_VERSION;
    }
    
    // 必需扩展检查
    $required_extensions = array('mysqli', 'zip', 'json', 'mbstring');
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "缺少必需的PHP扩展：{$ext}";
        }
    }
    
    // 推荐扩展检查
    $recommended_extensions = array('gd', 'curl', 'xml');
    foreach ($recommended_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $warnings[] = "建议安装PHP扩展：{$ext}";
        }
    }
    
    // 目录权限检查
    $dirs = array(
        __DIR__ . '/uploads',
        __DIR__ . '/uploads/icons',
        __DIR__ . '/manifests',
        __DIR__ . '/data',
        __DIR__ . '/logs',
        __DIR__ . '/includes'
    );
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                $errors[] = "无法创建目录：{$dir}";
            }
        } elseif (!is_writable($dir)) {
            $errors[] = "目录不可写：{$dir}";
        }
    }
    
    return array('errors' => $errors, 'warnings' => $warnings);
}

// 获取当前步骤
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;

// 处理安装请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    
    // 测试数据库连接
    if ($action === 'test_db') {
        $host = isset($_POST['host']) ? trim($_POST['host']) : 'localhost';
        $port = isset($_POST['port']) ? intval($_POST['port']) : 3306;
        $user = isset($_POST['user']) ? trim($_POST['user']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $database = isset($_POST['database']) ? trim($_POST['database']) : '';
        
        $conn = @new mysqli($host, $user, $password, $database, $port);
        if ($conn->connect_error) {
            echo json_encode(array('success' => false, 'message' => '数据库连接失败：' . $conn->connect_error));
        } else {
            $conn->set_charset('utf8mb4');
            echo json_encode(array('success' => true, 'message' => '数据库连接成功！'));
            $conn->close();
        }
        exit;
    }
    
    // 执行安装
    if ($action === 'install') {
        $host = isset($_POST['host']) ? trim($_POST['host']) : 'localhost';
        $port = isset($_POST['port']) ? intval($_POST['port']) : 3306;
        $user = isset($_POST['user']) ? trim($_POST['user']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $database = isset($_POST['database']) ? trim($_POST['database']) : '';
        $site_name = isset($_POST['site_name']) ? trim($_POST['site_name']) : 'iOS应用分发平台';
        $site_url = isset($_POST['site_url']) ? rtrim(trim($_POST['site_url']), '/') : '';
        $admin_user = isset($_POST['admin_user']) ? trim($_POST['admin_user']) : 'admin';
        $admin_pass = isset($_POST['admin_pass']) ? $_POST['admin_pass'] : '';
        $install_type = isset($_POST['install_type']) ? $_POST['install_type'] : 'new';
        
        try {
            // 连接数据库
            $conn = new mysqli($host, $user, $password, $database, $port);
            if ($conn->connect_error) {
                throw new Exception('数据库连接失败：' . $conn->connect_error);
            }
            $conn->set_charset('utf8mb4');
            
            // 如果是覆盖安装，先删除现有表
            if ($install_type === 'overwrite') {
                $tables_to_drop = array('urls', 'reports', 'operation_logs', 'app_groups', 'admins', 'app_stats');
                foreach ($tables_to_drop as $table) {
                    $conn->query("DROP TABLE IF EXISTS `{$table}`");
                }
            }
            
            // 创建表
            $tables = array(
                "CREATE TABLE IF NOT EXISTS urls (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    uid VARCHAR(50) UNIQUE,
                    app_group_id VARCHAR(50) DEFAULT NULL,
                    app_name VARCHAR(255),
                    version VARCHAR(50),
                    bundle_id VARCHAR(255),
                    ipa_path TEXT,
                    create_time DATETIME,
                    password VARCHAR(100),
                    download_count INT DEFAULT 0,
                    icon TEXT,
                    platform VARCHAR(20) DEFAULT 'ios',
                    file_size BIGINT DEFAULT 0,
                    is_violation TINYINT(1) DEFAULT 0,
                    violation_time DATETIME DEFAULT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                
                "CREATE TABLE IF NOT EXISTS reports (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    app_uid VARCHAR(64) NOT NULL,
                    app_name VARCHAR(255) NOT NULL,
                    app_version VARCHAR(50) NOT NULL,
                    app_platform VARCHAR(20) NOT NULL,
                    app_bundle_id VARCHAR(255) NOT NULL,
                    report_type VARCHAR(32) NOT NULL,
                    report_desc TEXT,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent TEXT,
                    status ENUM('pending', 'processed', 'ignored') DEFAULT 'pending',
                    create_time DATETIME NOT NULL,
                    process_time DATETIME,
                    INDEX idx_app_uid (app_uid),
                    INDEX idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                
                "CREATE TABLE IF NOT EXISTS operation_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    action VARCHAR(50),
                    description TEXT,
                    ip VARCHAR(45),
                    user_agent TEXT,
                    create_time DATETIME
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                
                "CREATE TABLE IF NOT EXISTS app_groups (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    group_id VARCHAR(50) UNIQUE,
                    ios_app_uid VARCHAR(50),
                    android_app_uid VARCHAR(50),
                    create_time DATETIME,
                    INDEX idx_group_id (group_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                
                "CREATE TABLE IF NOT EXISTS admins (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) UNIQUE,
                    password VARCHAR(255),
                    create_time DATETIME,
                    last_login DATETIME
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                
                "CREATE TABLE IF NOT EXISTS download_stats (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    app_uid VARCHAR(50) NOT NULL,
                    download_time DATETIME NOT NULL,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    platform VARCHAR(20),
                    status ENUM('started', 'completed', 'failed') DEFAULT 'started',
                    error_message TEXT,
                    INDEX idx_app_uid (app_uid),
                    INDEX idx_download_time (download_time)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                
                "CREATE TABLE IF NOT EXISTS install_stats (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    app_uid VARCHAR(50) NOT NULL,
                    install_time DATETIME NOT NULL,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    platform VARCHAR(20),
                    status ENUM('started', 'completed', 'failed') DEFAULT 'started',
                    error_message TEXT,
                    INDEX idx_app_uid (app_uid),
                    INDEX idx_install_time (install_time)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
                
                "CREATE TABLE IF NOT EXISTS daily_stats (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    app_uid VARCHAR(50) NOT NULL,
                    stat_date DATE NOT NULL,
                    download_count INT DEFAULT 0,
                    install_count INT DEFAULT 0,
                    install_success_count INT DEFAULT 0,
                    UNIQUE KEY uk_app_date (app_uid, stat_date),
                    INDEX idx_stat_date (stat_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            
            foreach ($tables as $sql) {
                if (!$conn->query($sql)) {
                    throw new Exception('创建表失败：' . $conn->error);
                }
            }
            
            // 创建管理员账号
            if (!empty($admin_pass)) {
                // 检查管理员是否已存在
                $check_admin = $conn->query("SELECT id FROM admins WHERE username = '{$admin_user}'");
                if ($check_admin && $check_admin->num_rows > 0) {
                    // 管理员已存在，更新密码
                    $hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE username = ?");
                    $stmt->bind_param('ss', $hashed_pass, $admin_user);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // 创建新管理员
                    $hashed_pass = password_hash($admin_pass, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("INSERT INTO admins (username, password, create_time) VALUES (?, ?, NOW())");
                    $stmt->bind_param('ss', $admin_user, $hashed_pass);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            $conn->close();
            
            // 保存数据库配置
            $db_config_content = "<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 数据库配置文件

\$db_config = array(
    'host' => '{$host}',
    'user' => '{$user}',
    'password' => '{$password}',
    'database' => '{$database}',
    'port' => {$port}
);

\$db = new mysqli(
    \$db_config['host'],
    \$db_config['user'],
    \$db_config['password'],
    \$db_config['database'],
    \$db_config['port']
);

if (\$db->connect_error) {
    die('数据库连接失败: ' . \$db->connect_error);
}

\$db->set_charset('utf8mb4');

if (!function_exists('createTables')) {
    function createTables(\$db) {
        // 表已在安装时创建
    }
}

createTables(\$db);
?>";
            
            file_put_contents(__DIR__ . '/includes/db.php', $db_config_content);
            
            // 保存网站配置
            $site_config = array(
                'site_name' => $site_name,
                'site_url' => $site_url,
                'site_description' => '专业的iOS和Android应用分发平台',
                'site_keywords' => 'iOS,Android,应用分发,IPA,APK',
                'announcement' => '',
                'announcement_enabled' => false,
                'footer_text' => '开发者：杰哥网络科技 | QQ: 2711793818',
            );
            
            $config_dir = __DIR__ . '/data';
            if (!is_dir($config_dir)) {
                mkdir($config_dir, 0755, true);
            }
            file_put_contents($config_dir . '/site_config.json', json_encode($site_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // 创建安装锁定文件
            file_put_contents(__DIR__ . '/includes/installed.lock', 'Installed at ' . date('Y-m-d H:i:s'));
            
            echo json_encode(array('success' => true, 'message' => '安装成功！'));
            
        } catch (Exception $e) {
            echo json_encode(array('success' => false, 'message' => $e->getMessage()));
        }
        exit;
    }
}

// 环境检查
$env_check = checkEnvironment();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统安装 - iOS应用分发平台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .install-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
            max-width: 700px;
            margin: 0 auto;
        }
        .install-title {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        .install-subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step-dot {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 10px;
            font-weight: bold;
            color: #999;
        }
        .step-dot.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .step-dot.done {
            background: #4CAF50;
            color: white;
        }
        .step-line {
            width: 60px;
            height: 2px;
            background: #e0e0e0;
            margin-top: 19px;
        }
        .step-line.done {
            background: #4CAF50;
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
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group input:focus {
            border-color: #667eea;
            outline: none;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .error-box {
            background: #fff3f3;
            border: 1px solid #ffcdd2;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .error-box h4 {
            color: #c62828;
            margin-bottom: 10px;
        }
        .error-box ul {
            color: #c62828;
            margin-left: 20px;
        }
        .warning-box {
            background: #fff8e1;
            border: 1px solid #ffecb3;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .warning-box h4 {
            color: #f57c00;
            margin-bottom: 10px;
        }
        .warning-box ul {
            color: #f57c00;
            margin-left: 20px;
        }
        .success-box {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        .success-box h3 {
            color: #2e7d32;
            margin-bottom: 10px;
        }
        .success-box p {
            color: #388e3c;
            margin-bottom: 20px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .test-result {
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            display: none;
        }
        .test-result.success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .test-result.error {
            background: #ffebee;
            color: #c62828;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #fff;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        /* 安装进度动画样式 */
        .install-progress {
            padding: 20px 0;
        }
        .progress-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 25px;
        }
        .progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        .progress-steps {
            text-align: left;
            max-height: 300px;
            overflow-y: auto;
        }
        .progress-step {
            display: flex;
            align-items: center;
            padding: 10px 15px;
            margin-bottom: 6px;
            background: #f5f5f5;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 13px;
        }
        .progress-step.active {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border-left: 3px solid #667eea;
            transform: translateX(5px);
        }
        .progress-step.done {
            background: #e8f5e9;
            border-left: 3px solid #4caf50;
        }
        .progress-step .step-icon {
            font-size: 16px;
            margin-right: 10px;
            width: 22px;
            text-align: center;
        }
        .progress-step .step-text {
            font-size: 13px;
            color: #333;
        }
        .progress-step.active .step-text {
            color: #667eea;
            font-weight: 500;
        }
        .progress-step.done .step-text {
            color: #4caf50;
        }
        
        /* 安装选项样式 */
        .install-options {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        .install-option {
            flex: 1;
            min-width: 200px;
            cursor: pointer;
        }
        .install-option input {
            display: none;
        }
        .install-option .option-content {
            padding: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            transition: all 0.3s ease;
            text-align: center;
        }
        .install-option .option-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        .install-option .option-desc {
            font-size: 12px;
            color: #666;
            line-height: 1.5;
        }
        .install-option input:checked + .option-content {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea10 0%, #764ba210 100%);
        }
        .install-option input:checked + .option-content .option-title {
            color: #667eea;
        }
        .install-option:hover .option-content {
            border-color: #667eea;
        }
        .install-option.warning input:checked + .option-content {
            border-color: #ff9800;
            background: #fff3e0;
        }
        .install-option.warning input:checked + .option-content .option-title {
            color: #e65100;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .hidden { display: none; }
    </style>
</head>
<body>
    <div class="install-container">
        <h1 class="install-title">iOS应用分发平台</h1>
        <p class="install-subtitle">开发者：杰哥网络科技 | QQ: 2711793818</p>
        
        <!-- 步骤指示器 -->
        <div class="step-indicator">
            <div class="step-dot <?php echo $step >= 1 ? 'active' : ''; ?>" id="step1">1</div>
            <div class="step-line <?php echo $step >= 2 ? 'done' : ''; ?>"></div>
            <div class="step-dot <?php echo $step >= 2 ? ($step > 2 ? 'done' : 'active') : ''; ?>" id="step2">2</div>
            <div class="step-line <?php echo $step >= 3 ? 'done' : ''; ?>"></div>
            <div class="step-dot <?php echo $step >= 3 ? 'active' : ''; ?>" id="step3">3</div>
        </div>
        
        <!-- 步骤1：环境检查 -->
        <div id="step1-content" class="<?php echo $step != 1 ? 'hidden' : ''; ?>">
            <h2 style="margin-bottom: 20px;">步骤1：环境检查</h2>
            
            <?php if (!empty($env_check['errors'])): ?>
            <div class="error-box">
                <h4>❌ 存在错误，无法继续安装</h4>
                <ul>
                    <?php foreach ($env_check['errors'] as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($env_check['warnings'])): ?>
            <div class="warning-box">
                <h4>⚠️ 警告</h4>
                <ul>
                    <?php foreach ($env_check['warnings'] as $warning): ?>
                    <li><?php echo htmlspecialchars($warning); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (empty($env_check['errors'])): ?>
            <div class="success-box" style="background: #e3f2fd; border-color: #90caf9; margin-bottom: 20px;">
                <p style="color: #1565c0;">✅ 环境检查通过，可以继续安装</p>
            </div>
            <div style="text-align: center;">
                <a href="?step=2" class="btn btn-primary">下一步：数据库配置</a>
            </div>
            <?php else: ?>
            <div style="text-align: center;">
                <button class="btn btn-secondary" disabled>请先解决上述错误</button>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 步骤2：数据库配置 -->
        <div id="step2-content" class="<?php echo $step != 2 ? 'hidden' : ''; ?>">
            <h2 style="margin-bottom: 20px;">步骤2：数据库配置</h2>
            
            <form id="dbForm">
                <div style="display: flex; gap: 20px;">
                    <div style="flex: 1;">
                        <div class="form-group">
                            <label>数据库主机</label>
                            <input type="text" name="host" value="localhost" required>
                        </div>
                    </div>
                    <div style="width: 100px;">
                        <div class="form-group">
                            <label>端口</label>
                            <input type="number" name="port" value="3306" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>数据库名</label>
                    <input type="text" name="database" placeholder="请输入数据库名" required>
                </div>
                
                <div class="form-group">
                    <label>数据库用户名</label>
                    <input type="text" name="user" placeholder="请输入数据库用户名" required>
                </div>
                
                <div class="form-group">
                    <label>数据库密码</label>
                    <input type="password" name="password" placeholder="请输入数据库密码">
                </div>
                
                <div class="test-result" id="testResult"></div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="testDbConnection()">测试连接</button>
                    <a href="?step=1" class="btn btn-secondary" style="text-decoration: none;">上一步</a>
                    <button type="button" class="btn btn-primary" onclick="goToStep3()" id="nextStepBtn" disabled>下一步</button>
                </div>
            </form>
        </div>
        
        <!-- 步骤3：系统设置 -->
        <div id="step3-content" class="<?php echo $step != 3 ? 'hidden' : ''; ?>">
            <h2 style="margin-bottom: 20px;">步骤3：系统设置</h2>
            
            <form id="installForm">
                <input type="hidden" name="action" value="install">
                <input type="hidden" name="host" id="final_host">
                <input type="hidden" name="port" id="final_port">
                <input type="hidden" name="user" id="final_user">
                <input type="hidden" name="password" id="final_password">
                <input type="hidden" name="database" id="final_database">
                
                <div class="form-group">
                    <label>网站名称</label>
                    <input type="text" name="site_name" value="iOS应用分发平台" required>
                </div>
                
                <div class="form-group">
                    <label>网站URL</label>
                    <input type="text" name="site_url" placeholder="http://your-domain.com" value="http://<?php echo isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost'; ?>" required>
                </div>
                
                <hr style="margin: 20px 0; border: none; border-top: 1px solid #eee;">
                
                <div class="form-group">
                    <label>数据库安装方式</label>
                    <div class="install-options">
                        <label class="install-option">
                            <input type="radio" name="install_type" value="new" checked onchange="toggleInstallType(this)">
                            <div class="option-content">
                                <div class="option-title">🆕 全新安装</div>
                                <div class="option-desc">创建新的数据表，不影响现有数据</div>
                            </div>
                        </label>
                        <label class="install-option">
                            <input type="radio" name="install_type" value="overwrite" onchange="toggleInstallType(this)">
                            <div class="option-content">
                                <div class="option-title">🔄 覆盖安装</div>
                                <div class="option-desc">删除现有数据表并重新创建，所有数据将被清空</div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <hr style="margin: 20px 0; border: none; border-top: 1px solid #eee;">
                
                <div class="form-group">
                    <label>管理员账号</label>
                    <input type="text" name="admin_user" value="admin" required>
                </div>
                
                <div class="form-group">
                    <label>管理员密码</label>
                    <input type="password" name="admin_pass" placeholder="请设置管理员密码" required>
                </div>
                
                <div class="test-result" id="installResult"></div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <a href="?step=2" class="btn btn-secondary" style="text-decoration: none;">上一步</a>
                    <button type="submit" class="btn btn-primary" id="installBtn">开始安装</button>
                </div>
            </form>
        </div>
        
        <!-- 安装成功 -->
        <div id="success-content" class="hidden">
            <div class="success-box">
                <h3>🎉 安装成功！</h3>
                <p>系统已成功安装并初始化完成。</p>
                <div class="btn-group">
                    <a href="index.php" class="btn btn-primary">进入首页</a>
                    <a href="admin.php" class="btn btn-secondary" style="text-decoration: none;">进入后台</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    let dbConfigValid = false;
    
    function testDbConnection() {
        const form = document.getElementById('dbForm');
        const formData = new FormData(form);
        formData.append('action', 'test_db');
        
        const testResult = document.getElementById('testResult');
        testResult.style.display = 'block';
        testResult.className = 'test-result';
        testResult.innerHTML = '测试中...';
        
        fetch('setup.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                testResult.className = 'test-result success';
                testResult.innerHTML = '✅ ' + data.message;
                dbConfigValid = true;
                document.getElementById('nextStepBtn').disabled = false;
            } else {
                testResult.className = 'test-result error';
                testResult.innerHTML = '❌ ' + data.message;
                dbConfigValid = false;
                document.getElementById('nextStepBtn').disabled = true;
            }
        })
        .catch(error => {
            testResult.className = 'test-result error';
            testResult.innerHTML = '❌ 请求失败：' + error.message;
        });
    }
    
    function toggleInstallType(radio) {
        const options = document.querySelectorAll('.install-option');
        options.forEach(opt => opt.classList.remove('warning'));
        
        if (radio.value === 'overwrite') {
            radio.closest('.install-option').classList.add('warning');
        }
    }
    
    function goToStep3() {
        if (!dbConfigValid) {
            alert('请先测试数据库连接');
            return;
        }
        
        const form = document.getElementById('dbForm');
        document.getElementById('final_host').value = form.host.value;
        document.getElementById('final_port').value = form.port.value;
        document.getElementById('final_user').value = form.user.value;
        document.getElementById('final_password').value = form.password.value;
        document.getElementById('final_database').value = form.database.value;
        
        document.getElementById('step2-content').classList.add('hidden');
        document.getElementById('step3-content').classList.remove('hidden');
        document.getElementById('step2').classList.remove('active');
        document.getElementById('step2').classList.add('done');
        document.getElementById('step3').classList.add('active');
    }
    
    document.getElementById('installForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const formData = new FormData(form);
        const installBtn = document.getElementById('installBtn');
        const installResult = document.getElementById('installResult');
        
        installBtn.disabled = true;
        installBtn.innerHTML = '<span class="loading"></span>安装中...';
        installResult.style.display = 'block';
        installResult.className = 'test-result';
        
        // 获取安装类型
        const installType = document.querySelector('input[name="install_type"]:checked').value;
        
        // 根据安装类型显示不同的步骤
        let stepsHTML = `
            <div class="install-progress">
                <div class="progress-bar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
                <div class="progress-steps" id="progressSteps">
        `;
        
        let stepIndex = 0;
        
        // 连接数据库
        stepsHTML += `
            <div class="progress-step" id="step-${stepIndex}">
                <span class="step-icon">⏳</span>
                <span class="step-text">正在连接数据库...</span>
            </div>
        `;
        stepIndex++;
        
        // 如果是覆盖安装，添加删除表的步骤
        if (installType === 'overwrite') {
            stepsHTML += `
                <div class="progress-step" id="step-${stepIndex}">
                    <span class="step-icon">⏳</span>
                    <span class="step-text">删除应用信息表...</span>
                </div>
            `;
            stepIndex++;
            stepsHTML += `
                <div class="progress-step" id="step-${stepIndex}">
                    <span class="step-icon">⏳</span>
                    <span class="step-text">删除举报记录表...</span>
                </div>
            `;
            stepIndex++;
            stepsHTML += `
                <div class="progress-step" id="step-${stepIndex}">
                    <span class="step-icon">⏳</span>
                    <span class="step-text">删除操作日志表...</span>
                </div>
            `;
            stepIndex++;
            stepsHTML += `
                <div class="progress-step" id="step-${stepIndex}">
                    <span class="step-icon">⏳</span>
                    <span class="step-text">删除应用分组表...</span>
                </div>
            `;
            stepIndex++;
            stepsHTML += `
                <div class="progress-step" id="step-${stepIndex}">
                    <span class="step-icon">⏳</span>
                    <span class="step-text">删除管理员表...</span>
                </div>
            `;
            stepIndex++;
        }
        
        // 创建表
        stepsHTML += `
            <div class="progress-step" id="step-${stepIndex}">
                <span class="step-icon">⏳</span>
                <span class="step-text">创建应用信息表...</span>
            </div>
        `;
        stepIndex++;
        stepsHTML += `
            <div class="progress-step" id="step-${stepIndex}">
                <span class="step-icon">⏳</span>
                <span class="step-text">创建举报记录表...</span>
            </div>
        `;
        stepIndex++;
        stepsHTML += `
            <div class="progress-step" id="step-${stepIndex}">
                <span class="step-icon">⏳</span>
                <span class="step-text">创建操作日志表...</span>
            </div>
        `;
        stepIndex++;
        stepsHTML += `
            <div class="progress-step" id="step-${stepIndex}">
                <span class="step-icon">⏳</span>
                <span class="step-text">创建应用分组表...</span>
            </div>
        `;
        stepIndex++;
        stepsHTML += `
            <div class="progress-step" id="step-${stepIndex}">
                <span class="step-icon">⏳</span>
                <span class="step-text">创建管理员表...</span>
            </div>
        `;
        stepIndex++;
        stepsHTML += `
            <div class="progress-step" id="step-${stepIndex}">
                <span class="step-icon">⏳</span>
                <span class="step-text">创建管理员账号...</span>
            </div>
        `;
        stepIndex++;
        stepsHTML += `
            <div class="progress-step" id="step-${stepIndex}">
                <span class="step-icon">⏳</span>
                <span class="step-text">保存数据库配置...</span>
            </div>
        `;
        stepIndex++;
        stepsHTML += `
            <div class="progress-step" id="step-${stepIndex}">
                <span class="step-icon">⏳</span>
                <span class="step-text">保存网站配置...</span>
            </div>
        `;
        stepIndex++;
        stepsHTML += `
            <div class="progress-step" id="step-${stepIndex}">
                <span class="step-icon">⏳</span>
                <span class="step-text">创建安装锁文件...</span>
            </div>
        `;
        stepIndex++;
        stepsHTML += `
            <div class="progress-step" id="step-${stepIndex}">
                <span class="step-icon">⏳</span>
                <span class="step-text">完成安装...</span>
            </div>
        `;
        stepIndex++;
        
        stepsHTML += `
                </div>
            </div>
        `;
        
        installResult.innerHTML = stepsHTML;
        
        const totalSteps = stepIndex;
        let currentStep = 0;
        const progressFill = document.getElementById('progressFill');
        
        function updateProgress() {
            const percent = (currentStep / totalSteps) * 100;
            progressFill.style.width = percent + '%';
            
            // 更新当前步骤状态
            for (let i = 0; i < totalSteps; i++) {
                const stepEl = document.getElementById('step-' + i);
                if (stepEl) {
                    if (i < currentStep) {
                        stepEl.classList.remove('active');
                        stepEl.classList.add('done');
                        stepEl.querySelector('.step-icon').textContent = '✅';
                    } else if (i === currentStep) {
                        stepEl.classList.add('active');
                    }
                }
            }
        }
        
        // 开始进度动画，每个步骤间隔1.5秒
        const progressInterval = setInterval(() => {
            if (currentStep < totalSteps - 1) {
                currentStep++;
                updateProgress();
            }
        }, 1500);
        
        updateProgress();
        
        fetch('setup.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            clearInterval(progressInterval);
            
            // 完成所有步骤
            for (let i = 0; i < totalSteps; i++) {
                const stepEl = document.getElementById('step-' + i);
                if (stepEl) {
                    stepEl.classList.remove('active');
                    stepEl.classList.add('done');
                    stepEl.querySelector('.step-icon').textContent = '✅';
                }
            }
            progressFill.style.width = '100%';
            
            if (data.success) {
                setTimeout(() => {
                    document.getElementById('step3-content').classList.add('hidden');
                    document.getElementById('success-content').classList.remove('hidden');
                    document.getElementById('step3').classList.remove('active');
                    document.getElementById('step3').classList.add('done');
                }, 800);
            } else {
                installResult.className = 'test-result error';
                installResult.innerHTML = '❌ ' + data.message;
                installBtn.disabled = false;
                installBtn.innerHTML = '开始安装';
            }
        })
        .catch(error => {
            installResult.className = 'test-result error';
            installResult.innerHTML = '❌ 请求失败：' + error.message;
            installBtn.disabled = false;
            installBtn.innerHTML = '开始安装';
        });
    });
    </script>
</body>
</html>
