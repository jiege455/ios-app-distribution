<?php
// 检查是否已安装
if (!file_exists(__DIR__ . '/includes/installed.lock')) {
    header('Location: setup.php');
    exit;
}

header('Content-Type: application/xml');
$db = require __DIR__ . '/includes/db.php';
$uid = $_GET['uid'] ?? '';

if (empty($uid)) {
    die('无效的链接');
}

$stmt = $db->prepare("SELECT * FROM urls WHERE uid = ?");
$stmt->bind_param('s', $uid);
$stmt->execute();
$res = $stmt->get_result();
$app = $res->fetch_assoc();
$stmt->close();

if (!$app) {
    die('应用不存在或已被删除');
}

// 生成应用下载链接
$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base_path = dirname($_SERVER['PHP_SELF']);
$base_url = "$scheme://$host$base_path";
$base_url = str_replace('\\', '/', $base_url);

$ipa_url = $app['ipa_path'];
if (strpos($ipa_url, 'http') !== 0) {
    $ipa_url = "$base_url/" . $ipa_url;
}

// 生成图标URL
$icon_url = $app['icon'];
if (empty($icon_url)) {
    $icon_url = "$base_url/assets/default_icon.png";
} elseif (strpos($icon_url, 'http') !== 0) {
    $icon_url = "$base_url/" . $icon_url;
}

?><?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>items</key>
    <array>
        <dict>
            <key>assets</key>
            <array>
                <dict>
                    <key>kind</key>
                    <string>software-package</string>
                    <key>url</key>
                    <string><?= htmlspecialchars($ipa_url) ?></string>
                </dict>
                <dict>
                    <key>kind</key>
                    <string>display-image</string>
                    <key>url</key>
                    <string><?= htmlspecialchars($icon_url) ?></string>
                    <key>needs-shine</key>
                    <true/>
                </dict>
            </array>
            <key>metadata</key>
            <dict>
                <key>bundle-identifier</key>
                <string><?= htmlspecialchars($app['bundle_id']) ?></string>
                <key>bundle-version</key>
                <string><?= htmlspecialchars($app['version']) ?></string>
                <key>kind</key>
                <string>software</string>
                <key>title</key>
                <string><?= htmlspecialchars($app['app_name']) ?></string>
            </dict>
        </dict>
    </array>
</dict>
</plist>