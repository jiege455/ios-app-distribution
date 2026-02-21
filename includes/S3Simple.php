<?php
class S3Simple {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    public function putFile($filePath, $objectName) {
        if (!file_exists($filePath)) {
            throw new Exception('File not found');
        }
        
        // 这里只是一个简单的实现，实际项目中应该使用AWS SDK
        // 或者其他S3兼容的SDK
        
        // 模拟上传成功，返回URL
        $scheme = $this->config['use_ssl'] ? 'https' : 'http';
        $endpoint = $this->config['endpoint'];
        $bucket = $this->config['bucket'];
        
        if ($this->config['cdn_url']) {
            return rtrim($this->config['cdn_url'], '/') . '/' . $objectName;
        }
        
        return "$scheme://$bucket.$endpoint/$objectName";
    }
    
    public function deleteObject($objectKey) {
        // 模拟删除操作
        return true;
    }
}