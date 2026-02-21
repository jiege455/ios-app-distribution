<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 二维码生成页面

// 加载配置
require_once __DIR__ . '/includes/config.php';

// 获取参数
$uid = isset($_GET['uid']) ? $_GET['uid'] : '';
$text = isset($_GET['text']) ? $_GET['text'] : '';
$size = isset($_GET['size']) ? intval($_GET['size']) : 150;

// 如果提供了uid，生成安装链接
if (!empty($uid)) {
    $text = $config['site_url'] . '/install.php?uid=' . $uid;
}

// 如果没有内容，显示错误
if (empty($text)) {
    die('缺少参数');
}

// 引入二维码库
require_once __DIR__ . '/qrcode/phpqrcode.php';

// 设置响应头
header('Content-Type: image/png');

// 生成二维码
QRcode::png($text, false, QR_ECLEVEL_L, 4, 2);
?>
