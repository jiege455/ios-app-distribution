<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 认证模块

// 检查admins表是否存在，不存在则创建
$create_admins_table = "CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$db->query($create_admins_table);

// 处理登录
if (isset($_POST['login'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } else {
        $login_query = "SELECT * FROM admins WHERE username = ?";
        $stmt = $db->prepare($login_query);
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($admin = $result->fetch_assoc()) {
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                logOperation($db, 'login', '管理员登录: ' . $username);
                header('Location: ?page=dashboard');
                exit;
            }
        }
        
        $error = '用户名或密码错误';
    }
}

// 处理登出
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: ?page=login');
    exit;
}

// 检查登录状态
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}
?>
