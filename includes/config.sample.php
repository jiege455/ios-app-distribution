<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 示例配置文件 - 请复制为 config.php 并修改

$config = array(
    // 数据库配置
    'db' => array(
        'host' => 'localhost',
        'user' => 'your_db_user',
        'password' => 'your_db_password',
        'database' => 'your_db_name',
        'charset' => 'utf8mb4'
    ),
    
    // 安全配置
    'security' => array(
        'secret_key' => 'your-secret-key-here', // 请修改为随机字符串
        'admin_password' => '' // 安装时自动生成
    ),
    
    // 网站配置
    'site_url' => 'https://your-domain.com',
    'site_name' => '应用分发平台',
    
    // 上传配置
    'upload' => array(
        'max_size' => 500, // MB
        'allowed_types' => array('ipa', 'apk')
    ),
    
    // 存储配置
    'storage' => array(
        'storage_type' => 'local'
    ),
    
    // 系统配置
    'system' => array(
        'version' => '1.6.0',
        'update_url' => 'https://plist.jiege6.cn/update_server/update_api.php',
        'author' => '杰哥网络科技',
        'qq' => '2711793818'
    )
);
?>
