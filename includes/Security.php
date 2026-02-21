<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 安全类 - CSRF防护、文件验证、频率限制

class Security {
    
    /**
     * 生成CSRF Token
     */
    public static function generateCsrfToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * 验证CSRF Token
     */
    public static function verifyCsrfToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * 获取CSRF Token字段（用于表单）
     */
    public static function csrfField() {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * 验证文件真实类型
     * @param string $file_path 文件路径
     * @param array $allowed_types 允许的类型数组
     * @return array ['valid' => bool, 'type' => string, 'error' => string]
     */
    public static function verifyFileType($file_path, $allowed_types = array('ipa', 'apk')) {
        $result = array(
            'valid' => false,
            'type' => '',
            'error' => ''
        );
        
        if (!file_exists($file_path)) {
            $result['error'] = '文件不存在';
            return $result;
        }
        
        // 读取文件头
        $handle = fopen($file_path, 'rb');
        $header = fread($handle, 4);
        fclose($handle);
        
        // 检测文件类型
        $detected_type = '';
        
        // ZIP文件头 (IPA是ZIP格式)
        if (bin2hex($header) === '504b0304' || bin2hex($header) === '504b0506' || bin2hex($header) === '504b0708') {
            $detected_type = 'ipa';
            
            // 进一步检查是否是APK (APK也是ZIP格式，但包含AndroidManifest.xml)
            $zip = new ZipArchive();
            if ($zip->open($file_path) === TRUE) {
                if ($zip->getFromName('AndroidManifest.xml') !== false) {
                    $detected_type = 'apk';
                } elseif ($zip->getFromName('Info.plist') !== false || $zip->locateName('Payload/', 0) !== false) {
                    $detected_type = 'ipa';
                }
                $zip->close();
            }
        }
        
        // DEX文件头 (Android DEX)
        if (bin2hex($header) === '6465780a') {
            $detected_type = 'apk';
        }
        
        if (empty($detected_type)) {
            $result['error'] = '无法识别的文件类型';
            return $result;
        }
        
        if (!in_array($detected_type, $allowed_types)) {
            $result['error'] = '不支持的文件类型：' . $detected_type;
            return $result;
        }
        
        $result['valid'] = true;
        $result['type'] = $detected_type;
        return $result;
    }
    
    /**
     * 访问频率限制
     * @param string $key 限制键名（如IP地址）
     * @param int $max_requests 最大请求数
     * @param int $time_window 时间窗口（秒）
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
     */
    public static function rateLimit($key, $max_requests = 100, $time_window = 3600) {
        $rate_dir = __DIR__ . '/../data/rate_limits';
        if (!is_dir($rate_dir)) {
            mkdir($rate_dir, 0755, true);
        }
        
        $file = $rate_dir . '/' . md5($key) . '.json';
        $now = time();
        
        $data = array(
            'count' => 0,
            'reset_time' => $now + $time_window
        );
        
        // 读取现有数据
        if (file_exists($file)) {
            $saved = json_decode(file_get_contents($file), true);
            if (is_array($saved)) {
                // 检查是否过期
                if ($saved['reset_time'] > $now) {
                    $data = $saved;
                }
            }
        }
        
        // 检查是否超过限制
        if ($data['count'] >= $max_requests) {
            return array(
                'allowed' => false,
                'remaining' => 0,
                'reset_time' => $data['reset_time'],
                'retry_after' => $data['reset_time'] - $now
            );
        }
        
        // 增加计数
        $data['count']++;
        file_put_contents($file, json_encode($data));
        
        return array(
            'allowed' => true,
            'remaining' => $max_requests - $data['count'],
            'reset_time' => $data['reset_time']
        );
    }
    
    /**
     * 清理过期的频率限制文件
     */
    public static function cleanupRateLimits() {
        $rate_dir = __DIR__ . '/../data/rate_limits';
        if (!is_dir($rate_dir)) {
            return;
        }
        
        $now = time();
        $files = glob($rate_dir . '/*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data) && $data['reset_time'] < $now) {
                unlink($file);
            }
        }
    }
    
    /**
     * 获取客户端IP
     */
    public static function getClientIP() {
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
        
        // 验证IP格式
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
        
        return 'unknown';
    }
    
    /**
     * 检查密码强度
     * @return array ['valid' => bool, 'score' => int, 'messages' => array]
     */
    public static function checkPasswordStrength($password) {
        $result = array(
            'valid' => true,
            'score' => 0,
            'messages' => array()
        );
        
        $length = strlen($password);
        
        if ($length < 6) {
            $result['valid'] = false;
            $result['messages'][] = '密码长度至少6位';
        } elseif ($length >= 8) {
            $result['score'] += 1;
        }
        
        if ($length >= 12) {
            $result['score'] += 1;
        }
        
        if (preg_match('/[a-z]/', $password)) {
            $result['score'] += 1;
        } else {
            $result['messages'][] = '建议包含小写字母';
        }
        
        if (preg_match('/[A-Z]/', $password)) {
            $result['score'] += 1;
        } else {
            $result['messages'][] = '建议包含大写字母';
        }
        
        if (preg_match('/[0-9]/', $password)) {
            $result['score'] += 1;
        } else {
            $result['messages'][] = '建议包含数字';
        }
        
        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $result['score'] += 1;
        }
        
        return $result;
    }
    
    /**
     * 压缩图片
     * @param string $source_path 源文件路径
     * @param string $dest_path 目标文件路径
     * @param int $quality 质量（0-100）
     * @param int $max_width 最大宽度
     * @param int $max_height 最大高度
     * @return bool 是否成功
     */
    public static function compressImage($source_path, $dest_path, $quality = 85, $max_width = 512, $max_height = 512) {
        if (!extension_loaded('gd')) {
            return false;
        }
        
        $info = getimagesize($source_path);
        if ($info === false) {
            return false;
        }
        
        $mime = $info['mime'];
        $width = $info[0];
        $height = $info[1];
        
        // 创建图像资源
        switch ($mime) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $image = imagecreatefrompng($source_path);
                break;
            case 'image/gif':
                $image = imagecreatefromgif($source_path);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($source_path);
                break;
            default:
                return false;
        }
        
        if ($image === false) {
            return false;
        }
        
        // 计算新尺寸
        $ratio = min($max_width / $width, $max_height / $height);
        if ($ratio < 1) {
            $new_width = (int)($width * $ratio);
            $new_height = (int)($height * $ratio);
            
            $resized = imagecreatetruecolor($new_width, $new_height);
            
            // PNG和GIF需要保持透明度
            if ($mime === 'image/png') {
                // 关闭混色模式
                imagealphablending($resized, false);
                // 开启保存alpha通道
                imagesavealpha($resized, true);
                // 填充透明背景
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefill($resized, 0, 0, $transparent);
            } elseif ($mime === 'image/gif') {
                // GIF透明处理
                $transparent_index = imagecolortransparent($image);
                if ($transparent_index >= 0) {
                    $transparent_color = imagecolorsforindex($image, $transparent_index);
                    $transparent_new = imagecolorallocate($resized, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
                    imagefill($resized, 0, 0, $transparent_new);
                    imagecolortransparent($resized, $transparent_new);
                }
            }
            
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            imagedestroy($image);
            $image = $resized;
        }
        
        // 保存图像
        $result = false;
        switch ($mime) {
            case 'image/jpeg':
                $result = imagejpeg($image, $dest_path, $quality);
                break;
            case 'image/png':
                // PNG保存时确保alpha通道开启
                imagesavealpha($image, true);
                $png_quality = (int)(9 - ($quality / 100) * 9);
                $result = imagepng($image, $dest_path, $png_quality);
                break;
            case 'image/gif':
                $result = imagegif($image, $dest_path);
                break;
            case 'image/webp':
                // WebP也支持透明度
                if (function_exists('imagewebp')) {
                    $result = imagewebp($image, $dest_path, $quality);
                }
                break;
        }
        
        imagedestroy($image);
        return $result;
    }
}
?>
