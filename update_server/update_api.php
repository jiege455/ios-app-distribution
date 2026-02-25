<?php
/**
 * 系统更新服务器API
 * 
 * 此文件由更新管理后台自动管理，无需手动修改
 * 配置文件: update_config.json
 * 管理后台: update_admin.php
 * 
 * 开发者：杰哥网络科技
 * QQ: 2711793818
 */

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// 配置文件路径
$config_file = __DIR__ . '/update_config.json';

// 统计数据文件路径
$stats_file = __DIR__ . '/install_stats.json';

// 默认配置
$default_config = array(
    'latest_version' => '1.0.0',
    'download_url' => '',
    'update_title' => '初始版本',
    'changelog' => array(),
    'update_time' => date('Y-m-d')
);

// 加载配置
$config = $default_config;
if (file_exists($config_file)) {
    $saved_config = json_decode(file_get_contents($config_file), true);
    if ($saved_config) {
        $config = array_merge($default_config, $saved_config);
    }
}

// 获取客户端信息
$client_version = isset($_POST['version']) ? $_POST['version'] : '1.0.0';
$client_domain = isset($_POST['domain']) ? $_POST['domain'] : 'unknown';
$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$client_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

// 记录安装统计
function recordInstallStats($domain, $version, $ip, $user_agent, $stats_file) {
    // 加载现有统计数据
    $stats = array();
    if (file_exists($stats_file)) {
        $stats = json_decode(file_get_contents($stats_file), true);
        if (!$stats) {
            $stats = array();
        }
    }
    
    // 生成域名唯一标识
    $domain_key = md5($domain);
    
    // 获取当前时间
    $current_time = date('Y-m-d H:i:s');
    
    // 判断是否为新安装
    $is_new = !isset($stats['installs'][$domain_key]);
    
    // 更新或添加安装记录
    $stats['installs'][$domain_key] = array(
        'domain' => $domain,
        'version' => $version,
        'ip' => $ip,
        'first_seen' => $is_new ? $current_time : ($stats['installs'][$domain_key]['first_seen'] ?? $current_time),
        'last_seen' => $current_time,
        'check_count' => isset($stats['installs'][$domain_key]['check_count']) ? $stats['installs'][$domain_key]['check_count'] + 1 : 1,
        'user_agent' => $user_agent
    );
    
    // 更新统计汇总
    $stats['summary'] = array(
        'total_installs' => count($stats['installs']),
        'last_update' => $current_time
    );
    
    // 保存统计数据
    @file_put_contents($stats_file, json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 记录统计（异步，不影响响应）
@recordInstallStats($client_domain, $client_version, $client_ip, $client_user_agent, $stats_file);

// 版本比较函数
function compareVersions($v1, $v2) {
    $parts1 = explode('.', $v1);
    $parts2 = explode('.', $v2);
    
    for ($i = 0; $i < max(count($parts1), count($parts2)); $i++) {
        $p1 = isset($parts1[$i]) ? (int)$parts1[$i] : 0;
        $p2 = isset($parts2[$i]) ? (int)$parts2[$i] : 0;
        
        if ($p1 < $p2) return -1;
        if ($p1 > $p2) return 1;
    }
    return 0;
}

// 判断是否需要更新
$has_update = compareVersions($client_version, $config['latest_version']) < 0;

// 构建响应
$response = array(
    'success' => true,
    'has_update' => $has_update,
    'current_version' => $client_version,
    'latest_version' => $config['latest_version'],
    'title' => $config['update_title'],
    'update_time' => $config['update_time'],
    'expire_time' => time() + 3600
);

// 如果有更新，添加详情
if ($has_update) {
    $response['changelog'] = $config['changelog'];
    $response['download_url'] = $config['download_url'];
    
    if (empty($config['download_url'])) {
        $response['message'] = '请联系开发者获取最新版本';
    }
}

// 输出JSON
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
