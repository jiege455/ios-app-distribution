<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 定时清理脚本 - 用于宝塔面板定时任务
// 访问此链接即删除所有应用数据

// 加载配置
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// 验证token
$token = isset($_GET['token']) ? $_GET['token'] : '';
$expected_token = md5($config['security']['secret_key']);

if ($token !== $expected_token) {
    http_response_code(403);
    die('Access Denied');
}

// 查询所有应用
$apps_query = "SELECT * FROM urls";
$result = $db->query($apps_query);

$deleted_count = 0;
$deleted_size = 0;

while ($app = $result->fetch_assoc()) {
    // 删除应用文件
    if (!empty($app['ipa_path'])) {
        $file_path = __DIR__ . '/' . $app['ipa_path'];
        if (file_exists($file_path)) {
            $deleted_size += filesize($file_path);
            unlink($file_path);
        }
        
        // 删除plist文件（iOS应用）
        if (isset($app['platform']) && $app['platform'] === 'ios') {
            $plist_file = __DIR__ . '/manifests/' . $app['uid'] . '.plist';
            if (file_exists($plist_file)) {
                unlink($plist_file);
            }
        }
    }
    
    // 删除图标
    if (!empty($app['icon'])) {
        $icon_path = __DIR__ . '/' . $app['icon'];
        if (file_exists($icon_path)) {
            unlink($icon_path);
        }
    }
    
    // 删除数据库记录
    $delete_sql = "DELETE FROM urls WHERE uid = ?";
    $stmt = $db->prepare($delete_sql);
    $stmt->bind_param('s', $app['uid']);
    $stmt->execute();
    $stmt->close();
    
    $deleted_count++;
}

// 清空操作日志
$db->query("TRUNCATE TABLE operation_logs");

// 清空举报记录
$db->query("TRUNCATE TABLE reports");

// 格式化文件大小
function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// 记录日志
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$log_message = sprintf(
    "[%s] 清理完成: 删除 %d 个应用, 释放 %s 空间\n",
    date('Y-m-d H:i:s'),
    $deleted_count,
    formatSize($deleted_size)
);

file_put_contents($log_dir . '/cleanup.log', $log_message, FILE_APPEND);

// 输出结果
echo json_encode(array(
    'success' => true,
    'message' => "清理完成，删除了 {$deleted_count} 个应用，释放 " . formatSize($deleted_size) . " 空间",
    'deleted_count' => $deleted_count,
    'deleted_size' => formatSize($deleted_size)
));
?>
