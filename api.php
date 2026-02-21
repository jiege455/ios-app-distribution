<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// API接口文件，处理文件上传等请求

// 检查是否已安装
if (!file_exists(__DIR__ . '/includes/installed.lock')) {
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'error' => '系统未安装，请先访问 setup.php 完成安装'));
    exit;
}

// 设置超时和内存限制
set_time_limit(600); // 10分钟超时
ini_set('memory_limit', '512M');

// 加载配置
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/Security.php';
require_once __DIR__ . '/includes/Storage.php';

// 检查维护模式
if (!empty($config['maintenance_mode'])) {
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'error' => '系统维护中，暂时无法上传'));
    exit;
}

// 频率限制检查
$client_ip = Security::getClientIP();
$rate_limit = Security::rateLimit($client_ip . '_upload', 10, 60); // 每分钟最多10次上传
if (!$rate_limit['allowed']) {
    header('Content-Type: application/json');
    header('HTTP/1.1 429 Too Many Requests');
    header('Retry-After: ' . $rate_limit['retry_after']);
    echo json_encode(array(
        'success' => false, 
        'error' => '请求过于频繁，请稍后再试',
        'retry_after' => $rate_limit['retry_after']
    ));
    exit;
}

// 确保上传目录存在
$upload_dir = __DIR__ . '/' . $config['upload']['upload_dir'];
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// 确保manifest目录存在
$manifest_dir = __DIR__ . '/manifests';
if (!is_dir($manifest_dir)) {
    mkdir($manifest_dir, 0755, true);
}

// 确保icons目录存在
$icons_dir = __DIR__ . '/uploads/icons';
if (!is_dir($icons_dir)) {
    mkdir($icons_dir, 0755, true);
}

// 处理文件上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    try {
        $file = $_FILES['file'];
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        // 记录开始处理
        error_log('['.date('Y-m-d H:i:s').'] 开始处理文件: '.$file['name'].', 大小: '.$file['size'].' bytes', 3, __DIR__.'/error.log');
        
        // 检查文件错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('文件上传失败: 错误码 ' . $file['error']);
        }
        
        // 检查文件大小
        if ($file['size'] > $config['upload']['max_size']) {
            throw new Exception('文件过大，最大支持 ' . $config['upload']['max_size_readable'] . '（服务器限制：upload_max_filesize=' . $config['upload']['upload_max_filesize'] . ', post_max_size=' . $config['upload']['post_max_size'] . '）');
        }
        
        // 检查文件扩展名
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $config['upload']['allowed_types'])) {
            throw new Exception('不支持的文件类型，仅支持 ' . implode(', ', $config['upload']['allowed_types']));
        }
        
        // 生成唯一ID
        $uid = uniqid() . '_' . time();
        $file_name = $uid . '.' . $file_ext;
        
        // 先保存到临时目录进行验证和解析
        $temp_dir = __DIR__ . '/uploads/temp';
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }
        $temp_file = $temp_dir . '/' . $file_name;
        
        // 移动到临时目录
        error_log('['.date('Y-m-d H:i:s').'] 开始移动文件到临时目录: '.$temp_file, 3, __DIR__.'/error.log');
        if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
            throw new Exception('文件移动失败，请检查目录权限');
        }
        error_log('['.date('Y-m-d H:i:s').'] 文件移动成功', 3, __DIR__.'/error.log');
        
        // 验证文件真实类型
        $type_check = Security::verifyFileType($temp_file, $config['upload']['allowed_types']);
        if (!$type_check['valid']) {
            unlink($temp_file);
            throw new Exception('文件类型验证失败: ' . $type_check['error']);
        }
        
        // 如果检测到的类型与扩展名不一致，更正扩展名
        if ($type_check['type'] !== $file_ext) {
            $file_ext = $type_check['type'];
            $file_name = $uid . '.' . $file_ext;
            $new_temp_file = $temp_dir . '/' . $file_name;
            rename($temp_file, $new_temp_file);
            $temp_file = $new_temp_file;
        }
        
        // 解析应用信息
        $app_info = array(
            'app_name' => pathinfo($file['name'], PATHINFO_FILENAME),
            'version' => '1.0.0',
            'bundle_id' => '',
            'platform' => $file_ext === 'ipa' ? 'ios' : 'android',
            'file_size' => $file['size'],
            'icon' => ''
        );
        
        // 尝试解析应用包信息
        if ($config['app']['auto_parse']) {
            if ($file_ext === 'ipa') {
                error_log('['.date('Y-m-d H:i:s').'] 开始解析IPA文件', 3, __DIR__.'/error.log');
                require_once __DIR__ . '/includes/IpaParser.php';
                $parser = new IpaParser();
                $parsed_info = $parser->parse($temp_file);
                if ($parsed_info) {
                    error_log('['.date('Y-m-d H:i:s').'] IPA解析结果: app_name='.$parsed_info['app_name'].', version='.$parsed_info['version'].', bundle_id='.$parsed_info['bundle_id'], 3, __DIR__.'/error.log');
                    $app_info = array_merge($app_info, $parsed_info);
                    error_log('['.date('Y-m-d H:i:s').'] IPA解析成功', 3, __DIR__.'/error.log');
                } else {
                    error_log('['.date('Y-m-d H:i:s').'] IPA解析失败，使用默认信息', 3, __DIR__.'/error.log');
                }
                // 提取iOS应用图标
                error_log('['.date('Y-m-d H:i:s').'] 开始提取IPA图标', 3, __DIR__.'/error.log');
                $app_info['icon'] = extractIpaIcon($temp_file, $uid);
                error_log('['.date('Y-m-d H:i:s').'] 图标提取完成', 3, __DIR__.'/error.log');
            } elseif ($file_ext === 'apk') {
                error_log('['.date('Y-m-d H:i:s').'] 开始解析APK文件', 3, __DIR__.'/error.log');
                require_once __DIR__ . '/includes/ApkParser.php';
                $parser = new ApkParser();
                $parsed_info = $parser->parse($temp_file);
                if ($parsed_info) {
                    error_log('['.date('Y-m-d H:i:s').'] APK解析结果: app_name='.$parsed_info['app_name'].', version='.$parsed_info['version'].', bundle_id='.$parsed_info['bundle_id'], 3, __DIR__.'/error.log');
                    $app_info = array_merge($app_info, $parsed_info);
                    error_log('['.date('Y-m-d H:i:s').'] APK解析成功', 3, __DIR__.'/error.log');
                } else {
                    error_log('['.date('Y-m-d H:i:s').'] APK解析失败，使用默认信息', 3, __DIR__.'/error.log');
                }
                // 提取Android应用图标 - 传入parser对象以获取正确的图标
                error_log('['.date('Y-m-d H:i:s').'] 开始提取APK图标', 3, __DIR__.'/error.log');
                $app_info['icon'] = extractApkIcon($temp_file, $uid, $parser);
                error_log('['.date('Y-m-d H:i:s').'] 图标提取完成', 3, __DIR__.'/error.log');
            }
        }
        
        error_log('['.date('Y-m-d H:i:s').'] 最终应用信息: app_name='.$app_info['app_name'].', version='.$app_info['version'].', bundle_id='.$app_info['bundle_id'], 3, __DIR__.'/error.log');
        
        // 使用Storage类上传文件
        $storage = new Storage($config['storage']);
        $storage_result = $storage->upload($temp_file, $file_name);
        
        // 删除临时文件
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        
        if (!$storage_result['success']) {
            throw new Exception('文件存储失败: ' . $storage_result['error']);
        }
        
        // 生成访问路径
        $ipa_path = $storage_result['path'];
        $file_url = $storage_result['url'];
        
        // 插入数据库
        $create_time = date('Y-m-d H:i:s');
        $sql = "INSERT INTO urls (uid, app_name, version, bundle_id, ipa_path, create_time, password, platform, file_size, icon) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        error_log('['.date('Y-m-d H:i:s').'] 准备写入数据库 - 数据库: ' . ($db_config['database'] ?? 'unknown') . ', 表: urls', 3, __DIR__.'/error.log');
        error_log('['.date('Y-m-d H:i:s').'] 插入数据 - UID: ' . $uid . ', App: ' . $app_info['app_name'] . ', Platform: ' . $app_info['platform'], 3, __DIR__.'/error.log');
        
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            error_log('['.date('Y-m-d H:i:s').'] 数据库预处理失败: ' . $db->error, 3, __DIR__.'/error.log');
            throw new Exception('数据库预处理失败: ' . $db->error);
        }
        
        error_log('['.date('Y-m-d H:i:s').'] 绑定参数', 3, __DIR__.'/error.log');
        $stmt->bind_param('ssssssssss', $uid, $app_info['app_name'], $app_info['version'], $app_info['bundle_id'], $ipa_path, $create_time, $password, $app_info['platform'], $app_info['file_size'], $app_info['icon']);
        
        error_log('['.date('Y-m-d H:i:s').'] 执行SQL', 3, __DIR__.'/error.log');
        if (!$stmt->execute()) {
            error_log('['.date('Y-m-d H:i:s').'] SQL执行失败: ' . $stmt->error, 3, __DIR__.'/error.log');
            throw new Exception('数据库执行失败: ' . $stmt->error);
        }
        
        $affected_rows = $stmt->affected_rows;
        $insert_id = $stmt->insert_id;
        $stmt->close();
        
        error_log('['.date('Y-m-d H:i:s').'] 数据库写入成功 - 数据库: ' . ($db_config['database'] ?? 'unknown') . ', UID: ' . $uid . ', 影响行数: ' . $affected_rows . ', 插入ID: ' . $insert_id, 3, __DIR__.'/error.log');
        
        // 验证数据是否真的写入了
        $check_result = $db->query("SELECT * FROM urls WHERE uid = '$uid'");
        if ($check_result && $check_result->num_rows > 0) {
            error_log('['.date('Y-m-d H:i:s').'] 数据验证成功 - 找到记录', 3, __DIR__.'/error.log');
        } else {
            error_log('['.date('Y-m-d H:i:s').'] 数据验证失败 - 未找到记录! 错误: ' . $db->error, 3, __DIR__.'/error.log');
        }
        
        // 记录操作日志
        logOperation($db, 'upload_app', '上传应用: ' . $app_info['app_name']);
        error_log('['.date('Y-m-d H:i:s').'] 操作日志记录成功', 3, __DIR__.'/error.log');
        
        // 重定向到首页，显示成功消息
        error_log('['.date('Y-m-d H:i:s').'] 开始重定向到首页', 3, __DIR__.'/error.log');
        header('Location: index.php?success=1');
        exit;
        
    } catch (Exception $e) {
        // 记录错误
        error_log('['.date('Y-m-d H:i:s').'] 上传失败: ' . $e->getMessage(), 3, __DIR__ . '/error.log');
        
        // 显示错误页面
        $error_message = htmlspecialchars($e->getMessage());
        echo '<!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>上传失败</title>
            <style>
                body {
                    font-family: "Microsoft YaHei", Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    color: #333;
                }
                .error-container {
                    background: white;
                    border-radius: 15px;
                    padding: 40px;
                    max-width: 600px;
                    text-align: center;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
                }
                h1 {
                    color: #dc3545;
                    margin-bottom: 20px;
                }
                p {
                    font-size: 18px;
                    margin-bottom: 30px;
                    color: #666;
                }
                .btn {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 12px 30px;
                    border-radius: 30px;
                    text-decoration: none;
                    font-size: 16px;
                    transition: all 0.3s ease;
                }
                .btn:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1>上传失败</h1>
                <p>$error_message</p>
                <a href="index.php" class="btn">返回首页</a>
            </div>
        </body>
        </html>';
        exit;
    }
}

// 处理举报请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'report') {
    try {
        $app_uid = $_POST['app_uid'];
        $app_name = $_POST['app_name'];
        $app_version = $_POST['app_version'];
        $app_platform = $_POST['app_platform'];
        $app_bundle_id = $_POST['app_bundle_id'];
        $report_type = $_POST['report_type'];
        $report_desc = $_POST['report_desc'];
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        $create_time = date('Y-m-d H:i:s');
        
        // 插入举报记录
        $sql = "INSERT INTO reports (app_uid, app_name, app_version, app_platform, app_bundle_id, report_type, report_desc, ip_address, user_agent, create_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new Exception('数据库预处理失败: ' . $db->error);
        }
        
        $stmt->bind_param('ssssssssss', $app_uid, $app_name, $app_version, $app_platform, $app_bundle_id, $report_type, $report_desc, $ip_address, $user_agent, $create_time);
        
        if (!$stmt->execute()) {
            throw new Exception('数据库执行失败: ' . $stmt->error);
        }
        
        $stmt->close();
        
        // 记录操作日志
        logOperation($db, 'report_app', '举报应用: ' . $app_name);
        
        // 返回成功响应
        echo json_encode(array('status' => 'success', 'message' => '举报成功，我们会尽快处理'));
        exit;
        
    } catch (Exception $e) {
        echo json_encode(array('status' => 'error', 'message' => '举报失败: ' . $e->getMessage()));
        exit;
    }
}

// 提取IPA应用图标 - 优化版，不解压整个文件
function extractIpaIcon($ipa_path, $uid) {
    $icons_dir = __DIR__ . '/uploads/icons';
    $result = '';
    
    // 确保图标目录存在
    if (!is_dir($icons_dir)) {
        mkdir($icons_dir, 0755, true);
    }
    
    try {
        $zip = new ZipArchive();
        if ($zip->open($ipa_path) !== TRUE) {
            error_log('['.date('Y-m-d H:i:s').'] 无法打开IPA文件: '.$ipa_path, 3, __DIR__.'/error.log');
            return '';
        }
        
        // 查找应用图标 - 直接从ZIP中查找，不解压
        $icon_files = array();
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $basename = basename($filename);
            $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
            
            // 匹配图标文件
            if (in_array($extension, array('png', 'jpg', 'jpeg')) && 
                (preg_match('/^AppIcon/i', $basename) || 
                 preg_match('/icon/i', $basename) || 
                 preg_match('/launch/i', $basename)) &&
                !preg_match('/masked/i', $basename)) {
                $icon_files[] = $filename;
            }
        }
        
        error_log('['.date('Y-m-d H:i:s').'] 找到 '.count($icon_files).' 个图标文件', 3, __DIR__.'/error.log');
        
        // 选择最大的图标
        $selected_icon = '';
        $max_size = 0;
        foreach ($icon_files as $icon_file) {
            $stat = $zip->statName($icon_file);
            if ($stat && $stat['size'] > $max_size && $stat['size'] > 1000) {
                $max_size = $stat['size'];
                $selected_icon = $icon_file;
            }
        }
        
        // 保存图标
        if ($selected_icon) {
            $icon_content = $zip->getFromName($selected_icon);
            if ($icon_content) {
                $icon_name = $uid . '.png';
                $icon_path = $icons_dir . '/' . $icon_name;
                
                // 先保存原始图标
                $temp_path = $icons_dir . '/' . $uid . '_temp.png';
                file_put_contents($temp_path, $icon_content);
                
                // 压缩图片
                if (Security::compressImage($temp_path, $icon_path, 85, 512, 512)) {
                    $result = 'uploads/icons/' . $icon_name;
                    unlink($temp_path); // 删除临时文件
                    error_log('['.date('Y-m-d H:i:s').'] 图标压缩保存成功: '.$result, 3, __DIR__.'/error.log');
                } else {
                    // 压缩失败，使用原图
                    rename($temp_path, $icon_path);
                    $result = 'uploads/icons/' . $icon_name;
                    error_log('['.date('Y-m-d H:i:s').'] 图标保存成功(未压缩): '.$result, 3, __DIR__.'/error.log');
                }
            }
        } else {
            error_log('['.date('Y-m-d H:i:s').'] 未找到合适的图标文件', 3, __DIR__.'/error.log');
        }
        
        $zip->close();
    } catch (Exception $e) {
        error_log('['.date('Y-m-d H:i:s').'] 图标提取异常: '.$e->getMessage(), 3, __DIR__.'/error.log');
    }
    
    return $result;
}

// 提取APK应用图标 - 使用ApkParser的getIconContent方法
function extractApkIcon($apk_path, $uid, $parser = null) {
    $icons_dir = __DIR__ . '/uploads/icons';
    $result = '';
    
    // 确保图标目录存在
    if (!is_dir($icons_dir)) {
        mkdir($icons_dir, 0755, true);
    }
    
    try {
        $icon_content = null;
        
        // 使用ApkParser获取图标内容
        if ($parser !== null) {
            $icon_content = $parser->getIconContent($apk_path);
            if ($icon_content) {
                error_log('['.date('Y-m-d H:i:s').'] ApkParser成功获取图标内容', 3, __DIR__.'/error.log');
            }
        }
        
        // 如果ApkParser没有获取到图标，使用默认查找逻辑
        if (empty($icon_content)) {
            $zip = new ZipArchive();
            if ($zip->open($apk_path) !== TRUE) {
                error_log('['.date('Y-m-d H:i:s').'] 无法打开APK文件: '.$apk_path, 3, __DIR__.'/error.log');
                return '';
            }
            
            $icon_files = array();
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                $basename = basename($filename);
                $extension = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
                
                // 匹配图标文件 - 只在mipmap或drawable目录下查找
                if (in_array($extension, array('png', 'jpg', 'jpeg', 'webp')) && 
                    preg_match('/res\/(mipmap|drawable)/i', $filename) &&
                    (preg_match('/ic_launcher/i', $basename) || 
                     preg_match('/^app_icon/i', $basename) || 
                     preg_match('/^icon$/i', preg_replace('/\.[^.]+$/', '', $basename)) || 
                     preg_match('/launcher/i', $basename)) &&
                    !preg_match('/masked/i', $basename) &&
                    !preg_match('/round/i', $basename) &&
                    !preg_match('/foreground/i', $basename) &&
                    !preg_match('/background/i', $basename)) {
                    $icon_files[] = $filename;
                }
            }
            error_log('['.date('Y-m-d H:i:s').'] 默认查找找到 '.count($icon_files).' 个图标文件', 3, __DIR__.'/error.log');
            
            // 选择最好的图标（优先选择高密度的mipmap图标）
            $selected_icon = '';
            $max_score = 0;
            foreach ($icon_files as $icon_file) {
                $score = 0;
                
                // mipmap优先级高于drawable
                if (stripos($icon_file, 'mipmap') !== false) {
                    $score += 1000;
                }
                
                // 密度分数
                if (stripos($icon_file, 'xxxhdpi') !== false) {
                    $score += 600;
                } elseif (stripos($icon_file, 'xxhdpi') !== false) {
                    $score += 500;
                } elseif (stripos($icon_file, 'xhdpi') !== false) {
                    $score += 400;
                } elseif (stripos($icon_file, 'hdpi') !== false) {
                    $score += 300;
                } elseif (stripos($icon_file, 'mdpi') !== false) {
                    $score += 200;
                }
                
                // 如果分数相同，选择文件大小更大的
                if ($score >= $max_score) {
                    $stat = $zip->statName($icon_file);
                    if ($stat && $stat['size'] > 500) {
                        $max_score = $score;
                        $selected_icon = $icon_file;
                    }
                }
            }
            
            error_log('['.date('Y-m-d H:i:s').'] 选择的图标: '.$selected_icon, 3, __DIR__.'/error.log');
            
            // 保存图标
            if ($selected_icon) {
                $icon_content = $zip->getFromName($selected_icon);
            }
            
            $zip->close();
        }
        
        // 保存图标
        if ($icon_content) {
            $icon_name = $uid . '.png';
            $icon_path = $icons_dir . '/' . $icon_name;
            
            // 先保存原始图标
            $temp_path = $icons_dir . '/' . $uid . '_temp.png';
            file_put_contents($temp_path, $icon_content);
            
            // 压缩图片
            if (Security::compressImage($temp_path, $icon_path, 85, 512, 512)) {
                $result = 'uploads/icons/' . $icon_name;
                unlink($temp_path); // 删除临时文件
                error_log('['.date('Y-m-d H:i:s').'] 图标压缩保存成功: '.$result, 3, __DIR__.'/error.log');
            } else {
                // 压缩失败，使用原图
                rename($temp_path, $icon_path);
                $result = 'uploads/icons/' . $icon_name;
                error_log('['.date('Y-m-d H:i:s').'] 图标保存成功(未压缩): '.$result, 3, __DIR__.'/error.log');
            }
        } else {
            error_log('['.date('Y-m-d H:i:s').'] 未找到合适的图标文件', 3, __DIR__.'/error.log');
        }
        
    } catch (Exception $e) {
        error_log('['.date('Y-m-d H:i:s').'] 图标提取异常: '.$e->getMessage(), 3, __DIR__.'/error.log');
    }
    
    return $result;
}

// 清理目录
function cleanup($directory) {
    if (is_dir($directory)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        
        rmdir($directory);
    }
}

// 日志记录函数
function logOperation($db, $action, $description) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $create_time = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO operation_logs (action, description, ip, user_agent, create_time) VALUES (?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('sssss', $action, $description, $ip, $user_agent, $create_time);
        $stmt->execute();
        $stmt->close();
    }
}
?>