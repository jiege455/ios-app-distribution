<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 存储适配器类 - 支持多种存储方式

class Storage {
    private $config;
    private $storage_type;
    
    /**
     * 构造函数
     * @param array $config 存储配置
     */
    public function __construct($config) {
        $this->config = $config;
        $this->storage_type = isset($config['storage_type']) ? $config['storage_type'] : 'local';
    }
    
    /**
     * 上传文件
     * @param string $local_file 本地文件路径
     * @param string $remote_file 远程文件名
     * @return array 上传结果 array('success' => bool, 'path' => string, 'url' => string, 'error' => string)
     */
    public function upload($local_file, $remote_file) {
        switch ($this->storage_type) {
            case 'qiniu':
                return $this->uploadToQiniu($local_file, $remote_file);
            case 'aliyun_oss':
                return $this->uploadToAliyunOss($local_file, $remote_file);
            case 'tencent_cos':
                return $this->uploadToTencentCos($local_file, $remote_file);
            case 'ftp':
                return $this->uploadToFtp($local_file, $remote_file);
            case 'local':
            default:
                return $this->uploadToLocal($local_file, $remote_file);
        }
    }
    
    /**
     * 删除文件
     * @param string $file_path 文件路径
     * @return bool 是否成功
     */
    public function delete($file_path) {
        switch ($this->storage_type) {
            case 'qiniu':
                return $this->deleteFromQiniu($file_path);
            case 'aliyun_oss':
                return $this->deleteFromAliyunOss($file_path);
            case 'tencent_cos':
                return $this->deleteFromTencentCos($file_path);
            case 'ftp':
                return $this->deleteFromFtp($file_path);
            case 'local':
            default:
                return $this->deleteFromLocal($file_path);
        }
    }
    
    /**
     * 获取文件URL
     * @param string $file_path 文件路径
     * @return string 文件URL
     */
    public function getUrl($file_path) {
        switch ($this->storage_type) {
            case 'qiniu':
                return $this->getQiniuUrl($file_path);
            case 'aliyun_oss':
                return $this->getAliyunOssUrl($file_path);
            case 'tencent_cos':
                return $this->getTencentCosUrl($file_path);
            case 'ftp':
                return $this->getFtpUrl($file_path);
            case 'local':
            default:
                return $this->getLocalUrl($file_path);
        }
    }
    
    // ==================== 本地存储 ====================
    
    private function uploadToLocal($local_file, $remote_file) {
        $upload_dir = __DIR__ . '/../' . $this->config['local']['upload_dir'];
        $dest_path = $upload_dir . '/' . $remote_file;
        
        // 确保目录存在
        $dir = dirname($dest_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // 先尝试 rename，如果失败则使用 copy + unlink
        if (rename($local_file, $dest_path)) {
            return array(
                'success' => true,
                'path' => $this->config['local']['upload_dir'] . '/' . $remote_file,
                'url' => $this->getLocalUrl($this->config['local']['upload_dir'] . '/' . $remote_file)
            );
        }
        
        // rename 失败，尝试 copy
        if (copy($local_file, $dest_path)) {
            // 删除原文件
            @unlink($local_file);
            return array(
                'success' => true,
                'path' => $this->config['local']['upload_dir'] . '/' . $remote_file,
                'url' => $this->getLocalUrl($this->config['local']['upload_dir'] . '/' . $remote_file)
            );
        }
        
        // 记录详细错误
        $error = error_get_last();
        $error_msg = isset($error['message']) ? $error['message'] : '未知错误';
        
        return array(
            'success' => false,
            'error' => '本地存储失败: ' . $error_msg . ' (源: ' . $local_file . ', 目标: ' . $dest_path . ')'
        );
    }
    
    private function deleteFromLocal($file_path) {
        $full_path = __DIR__ . '/../' . $file_path;
        if (file_exists($full_path)) {
            return unlink($full_path);
        }
        return true;
    }
    
    private function getLocalUrl($file_path) {
        global $config;
        return $config['site_url'] . '/' . $file_path;
    }
    
    // ==================== 七牛云存储 ====================
    
    private function uploadToQiniu($local_file, $remote_file) {
        $qiniu = $this->config['qiniu'];
        
        if (empty($qiniu['access_key']) || empty($qiniu['secret_key']) || empty($qiniu['bucket'])) {
            return array('success' => false, 'error' => '七牛云配置不完整');
        }
        
        // 构建上传凭证
        $access_key = $qiniu['access_key'];
        $secret_key = $qiniu['secret_key'];
        $bucket = $qiniu['bucket'];
        $key = $remote_file;
        
        // 使用简单的上传方式
        $put_policy = json_encode(array(
            'scope' => $bucket . ':' . $key,
            'deadline' => time() + 3600
        ));
        
        $encoded_put_policy = base64_urlsafe_encode($put_policy);
        $sign = hash_hmac('sha1', $encoded_put_policy, $secret_key, true);
        $encoded_sign = base64_urlsafe_encode($sign);
        $upload_token = $access_key . ':' . $encoded_sign . ':' . $encoded_put_policy;
        
        // 上传文件
        $url = 'http://upload.qiniup.com/upload/' . $key;
        $data = array(
            'token' => $upload_token,
            'key' => $key,
            'file' => new CURLFile($local_file)
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://upload.qiniup.com/');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        
        $response = json_decode($result, true);
        if (isset($response['key'])) {
            return array(
                'success' => true,
                'path' => $response['key'],
                'url' => $this->getQiniuUrl($response['key'])
            );
        } else {
            return array(
                'success' => false,
                'error' => '七牛云上传失败: ' . (isset($response['error']) ? $response['error'] : '未知错误')
            );
        }
    }
    
    private function deleteFromQiniu($file_path) {
        $qiniu = $this->config['qiniu'];
        
        // 构建删除请求
        $bucket = $qiniu['bucket'];
        $key = $file_path;
        $encoded_entry = base64_urlsafe_encode($bucket . ':' . $key);
        
        $access_key = $qiniu['access_key'];
        $secret_key = $qiniu['secret_key'];
        
        $sign = hash_hmac('sha1', '/delete/' . $encoded_entry, $secret_key, true);
        $encoded_sign = base64_urlsafe_encode($sign);
        $auth = $access_key . ':' . $encoded_sign;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://rs.qiniu.com/delete/' . $encoded_entry);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: QBox ' . $auth));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
        
        return true;
    }
    
    private function getQiniuUrl($file_path) {
        $qiniu = $this->config['qiniu'];
        return rtrim($qiniu['domain'], '/') . '/' . $file_path;
    }
    
    // ==================== 阿里云OSS ====================
    
    private function uploadToAliyunOss($local_file, $remote_file) {
        $oss = $this->config['aliyun_oss'];
        
        if (empty($oss['access_key_id']) || empty($oss['access_key_secret']) || empty($oss['bucket']) || empty($oss['endpoint'])) {
            return array('success' => false, 'error' => '阿里云OSS配置不完整');
        }
        
        $access_key_id = $oss['access_key_id'];
        $access_key_secret = $oss['access_key_secret'];
        $bucket = $oss['bucket'];
        $endpoint = $oss['endpoint'];
        $object = $remote_file;
        
        // 构建请求
        $url = 'https://' . $bucket . '.' . $endpoint . '/' . $object;
        $date = gmdate('D, d M Y H:i:s T');
        $content_type = 'application/octet-stream';
        $content_length = filesize($local_file);
        
        $string_to_sign = "PUT\n\n{$content_type}\n{$date}\n/{$bucket}/{$object}";
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $access_key_secret, true));
        $authorization = "OSS {$access_key_id}:{$signature}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, fopen($local_file, 'r'));
        curl_setopt($ch, CURLOPT_INFILESIZE, $content_length);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Date: ' . $date,
            'Content-Type: ' . $content_type,
            'Authorization: ' . $authorization
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300) {
            return array(
                'success' => true,
                'path' => $object,
                'url' => $this->getAliyunOssUrl($object)
            );
        } else {
            return array(
                'success' => false,
                'error' => '阿里云OSS上传失败'
            );
        }
    }
    
    private function deleteFromAliyunOss($file_path) {
        $oss = $this->config['aliyun_oss'];
        
        $access_key_id = $oss['access_key_id'];
        $access_key_secret = $oss['access_key_secret'];
        $bucket = $oss['bucket'];
        $endpoint = $oss['endpoint'];
        $object = $file_path;
        
        $url = 'https://' . $bucket . '.' . $endpoint . '/' . $object;
        $date = gmdate('D, d M Y H:i:s T');
        
        $string_to_sign = "DELETE\n\n\n{$date}\n/{$bucket}/{$object}";
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $access_key_secret, true));
        $authorization = "OSS {$access_key_id}:{$signature}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Date: ' . $date,
            'Authorization: ' . $authorization
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
        
        return true;
    }
    
    private function getAliyunOssUrl($file_path) {
        $oss = $this->config['aliyun_oss'];
        if (!empty($oss['domain'])) {
            return rtrim($oss['domain'], '/') . '/' . $file_path;
        }
        return 'https://' . $oss['bucket'] . '.' . $oss['endpoint'] . '/' . $file_path;
    }
    
    // ==================== 腾讯云COS ====================
    
    private function uploadToTencentCos($local_file, $remote_file) {
        $cos = $this->config['tencent_cos'];
        
        if (empty($cos['secret_id']) || empty($cos['secret_key']) || empty($cos['bucket']) || empty($cos['region'])) {
            return array('success' => false, 'error' => '腾讯云COS配置不完整');
        }
        
        $secret_id = $cos['secret_id'];
        $secret_key = $cos['secret_key'];
        $bucket = $cos['bucket'];
        $region = $cos['region'];
        $object = $remote_file;
        
        $url = 'https://' . $bucket . '.cos.' . $region . '.myqcloud.com/' . $object;
        $content_length = filesize($local_file);
        
        // 计算签名
        $timestamp = time();
        $key_time = $timestamp . ';' . ($timestamp + 3600);
        $sign_key = hash_hmac('sha1', $key_time, $secret_key);
        
        $http_string = "put\n/" . $object . "\n\nhost=" . $bucket . '.cos.' . $region . ".myqcloud.com\n";
        $string_to_sign = "sha1\n" . $key_time . "\n" . sha1($http_string) . "\n";
        $signature = hash_hmac('sha1', $string_to_sign, $sign_key);
        
        $authorization = "q-sign-algorithm=sha1&q-ak=" . $secret_id . "&q-sign-time=" . $key_time . "&q-key-time=" . $key_time . "&q-header-list=host&q-url-param-list=&q-signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, fopen($local_file, 'r'));
        curl_setopt($ch, CURLOPT_INFILESIZE, $content_length);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Host: ' . $bucket . '.cos.' . $region . '.myqcloud.com',
            'Authorization: ' . $authorization
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code >= 200 && $http_code < 300) {
            return array(
                'success' => true,
                'path' => $object,
                'url' => $this->getTencentCosUrl($object)
            );
        } else {
            return array(
                'success' => false,
                'error' => '腾讯云COS上传失败'
            );
        }
    }
    
    private function deleteFromTencentCos($file_path) {
        $cos = $this->config['tencent_cos'];
        
        $secret_id = $cos['secret_id'];
        $secret_key = $cos['secret_key'];
        $bucket = $cos['bucket'];
        $region = $cos['region'];
        $object = $file_path;
        
        $url = 'https://' . $bucket . '.cos.' . $region . '.myqcloud.com/' . $object;
        
        $timestamp = time();
        $key_time = $timestamp . ';' . ($timestamp + 3600);
        $sign_key = hash_hmac('sha1', $key_time, $secret_key);
        
        $http_string = "delete\n/" . $object . "\n\nhost=" . $bucket . '.cos.' . $region . ".myqcloud.com\n";
        $string_to_sign = "sha1\n" . $key_time . "\n" . sha1($http_string) . "\n";
        $signature = hash_hmac('sha1', $string_to_sign, $sign_key);
        
        $authorization = "q-sign-algorithm=sha1&q-ak=" . $secret_id . "&q-sign-time=" . $key_time . "&q-key-time=" . $key_time . "&q-header-list=host&q-url-param-list=&q-signature=" . $signature;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Host: ' . $bucket . '.cos.' . $region . '.myqcloud.com',
            'Authorization: ' . $authorization
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
        
        return true;
    }
    
    private function getTencentCosUrl($file_path) {
        $cos = $this->config['tencent_cos'];
        if (!empty($cos['domain'])) {
            return rtrim($cos['domain'], '/') . '/' . $file_path;
        }
        return 'https://' . $cos['bucket'] . '.cos.' . $cos['region'] . '.myqcloud.com/' . $file_path;
    }
    
    // ==================== FTP存储 ====================
    
    private function uploadToFtp($local_file, $remote_file) {
        $ftp = $this->config['ftp'];
        
        if (empty($ftp['host']) || empty($ftp['username']) || empty($ftp['password'])) {
            return array('success' => false, 'error' => 'FTP配置不完整');
        }
        
        $conn = ftp_connect($ftp['host'], $ftp['port'], 30);
        if (!$conn) {
            return array('success' => false, 'error' => 'FTP连接失败');
        }
        
        if (!ftp_login($conn, $ftp['username'], $ftp['password'])) {
            ftp_close($conn);
            return array('success' => false, 'error' => 'FTP登录失败');
        }
        
        ftp_pasv($conn, true);
        
        $remote_path = rtrim($ftp['path'], '/') . '/' . $remote_file;
        
        // 确保目录存在
        $dir = dirname($remote_path);
        $this->ftp_mkdir_recursive($conn, $dir);
        
        if (ftp_put($conn, $remote_path, $local_file, FTP_BINARY)) {
            ftp_close($conn);
            return array(
                'success' => true,
                'path' => $remote_path,
                'url' => $this->getFtpUrl($remote_path)
            );
        } else {
            ftp_close($conn);
            return array('success' => false, 'error' => 'FTP上传失败');
        }
    }
    
    private function ftp_mkdir_recursive($conn, $path) {
        $parts = explode('/', $path);
        $current = '';
        foreach ($parts as $part) {
            if (empty($part)) continue;
            $current .= '/' . $part;
            @ftp_mkdir($conn, $current);
        }
    }
    
    private function deleteFromFtp($file_path) {
        $ftp = $this->config['ftp'];
        
        $conn = ftp_connect($ftp['host'], $ftp['port'], 30);
        if (!$conn) return false;
        
        if (!ftp_login($conn, $ftp['username'], $ftp['password'])) {
            ftp_close($conn);
            return false;
        }
        
        ftp_delete($conn, $file_path);
        ftp_close($conn);
        
        return true;
    }
    
    private function getFtpUrl($file_path) {
        global $config;
        // FTP通常需要配置一个HTTP访问地址
        return $config['site_url'] . '/ftp_proxy.php?file=' . urlencode($file_path);
    }
}

// 辅助函数：URL安全的Base64编码
if (!function_exists('base64_urlsafe_encode')) {
    function base64_urlsafe_encode($data) {
        return str_replace(array('+', '/'), array('-', '_'), base64_encode($data));
    }
}

// 辅助函数：URL安全的Base64解码
if (!function_exists('base64_urlsafe_decode')) {
    function base64_urlsafe_decode($data) {
        return base64_decode(str_replace(array('-', '_'), array('+', '/'), $data));
    }
}
?>
