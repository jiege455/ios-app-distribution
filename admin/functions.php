<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 后台公共函数

// 日志记录函数
function logOperation($db, $action, $description) {
    $ip = getClientIP();
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
    $create_time = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO operation_logs (action, description, ip, user_agent, create_time) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('sssss', $action, $description, $ip, $user_agent, $create_time);
        $stmt->execute();
        $stmt->close();
    }
}

// 获取客户端IP
function getClientIP() {
    $ip = '';
    
    if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        return $ip;
    }
    
    return 'unknown';
}

// 格式化文件大小
function formatFileSize($bytes) {
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

// 举报类型映射
function getReportTypeLabel($type) {
    $types = array(
        'copyright' => '版权侵权',
        'malware' => '恶意软件',
        'inappropriate' => '不当内容',
        'spam' => '垃圾应用',
        'other' => '其他'
    );
    return isset($types[$type]) ? $types[$type] : $type;
}

// 状态标签
function getStatusBadge($status) {
    $badges = array(
        'pending' => '<span class="badge badge-warning">待处理</span>',
        'processed' => '<span class="badge badge-danger">已处理</span>',
        'ignored' => '<span class="badge badge-success">已忽略</span>'
    );
    return isset($badges[$status]) ? $badges[$status] : '<span class="badge badge-info">' . htmlspecialchars($status) . '</span>';
}

// 平台图标
function getPlatformIcon($platform) {
    if ($platform === 'ios') {
        return '<span class="platform-icon platform-ios">🍎</span>';
    } else {
        return '<span class="platform-icon platform-android">🤖</span>';
    }
}

// 安全输出
function safeOutput($value, $default = '') {
    if (is_array($value)) {
        return $default;
    }
    return htmlspecialchars(isset($value) ? $value : $default);
}

// 清理缓存
function clearSystemCache() {
    $cache_cleared = 0;
    $cache_messages = array();
    
    $rate_limit_dir = dirname(__DIR__) . '/data/rate_limits';
    if (is_dir($rate_limit_dir)) {
        $files = glob($rate_limit_dir . '/*.json');
        foreach ($files as $file) {
            if (unlink($file)) {
                $cache_cleared++;
            }
        }
        $cache_messages[] = '频率限制缓存: ' . count($files) . ' 个文件';
    }
    
    $temp_dirs = array(
        dirname(__DIR__) . '/uploads/icons/*_temp.png',
        dirname(__DIR__) . '/uploads/*.tmp'
    );
    $temp_count = 0;
    foreach ($temp_dirs as $pattern) {
        $files = glob($pattern);
        foreach ($files as $file) {
            if (is_file($file) && unlink($file)) {
                $temp_count++;
            }
        }
    }
    if ($temp_count > 0) {
        $cache_messages[] = '临时文件: ' . $temp_count . ' 个';
    }
    
    return $cache_messages;
}

// 清理过期文件
function cleanupExpiredFiles($db, $days = 30) {
    $deleted_count = 0;
    $deleted_size = 0;
    
    $query = "SELECT * FROM urls WHERE create_time < DATE_SUB(NOW(), INTERVAL ? DAY)";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $file_path = dirname(__DIR__) . '/' . $row['ipa_path'];
        if (file_exists($file_path)) {
            $file_size = filesize($file_path);
            if (unlink($file_path)) {
                $deleted_count++;
                $deleted_size += $file_size;
            }
        }
        
        $icon_path = dirname(__DIR__) . '/' . $row['icon'];
        if (!empty($row['icon']) && file_exists($icon_path)) {
            unlink($icon_path);
        }
        
        $manifest_path = dirname(__DIR__) . '/manifests/' . $row['uid'] . '.plist';
        if (file_exists($manifest_path)) {
            unlink($manifest_path);
        }
        
        $delete_query = "DELETE FROM urls WHERE id = ?";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bind_param('i', $row['id']);
        $delete_stmt->execute();
    }
    
    return array('count' => $deleted_count, 'size' => $deleted_size);
}

// 清理全部数据
function cleanupAllFiles($db) {
    $deleted_count = 0;
    $deleted_size = 0;
    
    $query = "SELECT * FROM urls";
    $result = $db->query($query);
    
    while ($row = $result->fetch_assoc()) {
        // 删除应用文件
        if (!empty($row['ipa_path'])) {
            $file_path = dirname(__DIR__) . '/' . $row['ipa_path'];
            if (file_exists($file_path)) {
                $file_size = filesize($file_path);
                if (unlink($file_path)) {
                    $deleted_size += $file_size;
                }
            }
        }
        
        // 删除图标
        if (!empty($row['icon'])) {
            $icon_path = dirname(__DIR__) . '/' . $row['icon'];
            if (file_exists($icon_path)) {
                unlink($icon_path);
            }
        }
        
        // 删除plist文件
        $manifest_path = dirname(__DIR__) . '/manifests/' . $row['uid'] . '.plist';
        if (file_exists($manifest_path)) {
            unlink($manifest_path);
        }
        
        $deleted_count++;
    }
    
    // 清空数据表
    $db->query("TRUNCATE TABLE urls");
    $db->query("TRUNCATE TABLE operation_logs");
    $db->query("TRUNCATE TABLE reports");
    
    return array('count' => $deleted_count, 'size' => $deleted_size);
}
?>
