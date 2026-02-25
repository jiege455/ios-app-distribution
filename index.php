<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 首页文件

// 检查是否已安装
if (!file_exists(__DIR__ . '/includes/installed.lock')) {
    header('Location: setup.php');
    exit;
}

// 加载配置
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// 检查维护模式
if (!empty($config['maintenance_mode'])) {
    http_response_code(503);
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>维护中 - <?php echo htmlspecialchars($config['site_name']); ?></title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .maintenance-box {
                background: white;
                padding: 50px 40px;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
                max-width: 500px;
            }
            .icon { font-size: 64px; margin-bottom: 20px; }
            h1 { color: #333; margin-bottom: 15px; font-size: 24px; }
            p { color: #666; font-size: 16px; line-height: 1.6; }
            .footer { margin-top: 30px; font-size: 13px; color: #999; }
        </style>
    </head>
    <body>
        <div class="maintenance-box">
            <div class="icon">🔧</div>
            <h1>系统维护中</h1>
            <p><?php echo htmlspecialchars($config['maintenance_message'] ?? '系统维护中，请稍后访问'); ?></p>
            <div class="footer">开发者：杰哥网络科技 | QQ: 2711793818</div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 页面标题
$page_title = $config['site_name'] . ' - 首页';

// 处理上传成功后的消息
$success_message = '';
if (isset($_GET['success'])) {
    $success_message = '应用上传成功！';
}

// 分页参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12; // 每页显示数量
$offset = ($page - 1) * $per_page;

// 获取总数
$total_query = "SELECT COUNT(*) as total FROM urls";
$total_result = $db->query($total_query);
$total_row = $total_result ? $total_result->fetch_assoc() : array('total' => 0);
$total_count = intval($total_row['total']);
$total_pages = ceil($total_count / $per_page);

// 获取上传历史（分页）
$uploads = array();
$query = "SELECT * FROM urls ORDER BY create_time DESC LIMIT {$offset}, {$per_page}";
$result = $db->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $uploads[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($config['site_description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars(isset($config['site_keywords']) ? $config['site_keywords'] : ''); ?>">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* 公告样式 */
        .announcement {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .announcement-icon {
            font-size: 24px;
        }
        
        .announcement-content {
            flex: 1;
        }
        
        .announcement-title {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .announcement-text {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .announcement-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
        }
        
        /* 消息提示 */
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            font-size: 20px;
        }
        
        .empty-state p {
            font-size: 16px;
        }
        
        /* 应用图标 */
        .app-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: #f0f0f0;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 15px;
            font-size: 24px;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
        }
        
        .app-icon img {
            width: 100%;
            height: 100%;
            border-radius: 12px;
            object-fit: cover;
        }
        
        /* 平台标识 */
        .platform-badge {
            position: absolute;
            bottom: -2px;
            right: -2px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 12px;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .platform-badge.ios {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .platform-badge.android {
            background: linear-gradient(135deg, #3DDC84 0%, #2DA562 100%);
        }
        
        /* 调整历史记录项布局 */
        .history-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #fff;
            border-radius: 12px;
            margin-bottom: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .history-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        
        .app-info {
            flex: 1;
        }
        
        .app-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            display: block;
            margin-bottom: 4px;
        }
        
        .bundle-id {
            display: block;
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 6px;
        }
        
        .app-meta {
            display: inline-block;
            font-size: 0.75rem;
            color: #999;
            margin-right: 12px;
            margin-bottom: 3px;
        }
        
        .app-meta .platform-icon {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .app-meta .platform-icon.ios {
            color: #667eea;
        }
        
        .app-meta .platform-icon.android {
            color: #3DDC84;
        }
        
        /* 上传区域优化 */
        .upload-section {
            border: 2px dashed #ddd;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
            transition: all 0.3s ease;
        }
        
        .upload-section:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f4ff 0%, #e8eeff 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.15);
        }
        
        .upload-section.dragover {
            border-color: #667eea;
            background: linear-gradient(135deg, #e8eeff 0%, #dde5ff 100%);
            transform: scale(1.02);
        }
        
        /* 上传图标美化 */
        .upload-icon-wrapper {
            width: 70px;
            height: 70px;
            margin: 0 auto 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
            position: relative;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        .upload-icon-wrapper::before {
            content: '';
            position: absolute;
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.5; }
        }
        
        .upload-icon-wrapper svg {
            width: 32px;
            height: 32px;
            fill: white;
            position: relative;
            z-index: 1;
        }
        
        .upload-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .upload-subtitle {
            font-size: 13px;
            color: #888;
            margin-bottom: 15px;
        }
        
        .upload-subtitle span {
            display: inline-block;
            background: #f0f0f0;
            padding: 2px 8px;
            border-radius: 20px;
            margin: 0 3px;
            font-weight: 500;
        }
        
        .upload-subtitle span.ios {
            background: #667eea15;
            color: #667eea;
        }
        
        .upload-subtitle span.android {
            background: #3DDC8415;
            color: #3DDC84;
        }
        
        /* 文件选择区域美化 */
        .file-drop-zone {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 20px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        
        .file-drop-zone:hover {
            border-color: #667eea;
            background: #fafbff;
        }
        
        .file-drop-zone.has-file {
            border-color: #4caf50;
            background: #f0fff0;
        }
        
        .file-drop-zone-text {
            color: #888;
            font-size: 13px;
        }
        
        .file-drop-zone-text .browse {
            color: #667eea;
            font-weight: 500;
            cursor: pointer;
        }
        
        .file-drop-zone-filename {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: #4caf50;
            font-weight: 500;
        }
        
        .file-drop-zone.has-file .file-drop-zone-text {
            display: none;
        }
        
        .file-drop-zone.has-file .file-drop-zone-filename {
            display: flex;
        }
        
        .file-icon {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .file-icon svg {
            width: 20px;
            height: 20px;
            fill: white;
        }
        
        /* 上传按钮美化 */
        .upload-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 30px;
            font-size: 15px;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.5);
        }
        
        .upload-btn:active {
            transform: translateY(0);
        }
        
        .upload-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            box-shadow: none;
        }
        
        /* 分页样式 */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            padding: 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .pagination-info {
            color: #666;
            font-size: 14px;
        }
        
        .pagination-links {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pagination-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 12px;
            background: #f5f5f5;
            color: #333;
            border-radius: 8px;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .pagination-btn:hover {
            background: #667eea;
            color: white;
        }
        
        .pagination-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
        }
        
        .pagination-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            color: #999;
        }
        
        /* 进度条优化 */
        #progressContainer {
            margin: 20px 0;
        }
        
        #progressBar {
            transition: width 0.2s ease-out;
        }
        
        /* 处理中状态 */
        .processing {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .processing .spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 600px) {
            .history-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .app-icon {
                margin-bottom: 15px;
                margin-right: 0;
            }
            
            .actions {
                margin-top: 15px;
                width: 100%;
            }
            
            .actions .install-btn,
            .actions .copy-btn {
                width: 48%;
            }
        }
    </style>
</head>
<body>
    <div class="glass-container">
        <!-- 首页顶部广告 -->
        <?php if (!empty($config['ad_home_top_enabled']) && !empty($config['ad_home_top'])): ?>
        <div class="ad-section" style="margin-bottom: 20px; padding: 15px; background: rgba(255,255,255,0.8); border-radius: 10px; text-align: center;">
            <?php echo $config['ad_home_top']; ?>
        </div>
        <?php endif; ?>
        
        <!-- 头部 -->
        <div class="header">
            <h1><?php echo htmlspecialchars($config['site_name']); ?></h1>
            <p><?php echo htmlspecialchars($config['site_description']); ?></p>
        </div>
        
        <!-- 公告 -->
        <?php if (!empty($config['announcement_enabled']) && !empty($config['announcement'])): ?>
        <div class="announcement" id="announcement">
            <div class="announcement-icon">📢</div>
            <div class="announcement-content">
                <div class="announcement-title">公告</div>
                <div class="announcement-text"><?php echo $config['announcement']; ?></div>
            </div>
            <button class="announcement-close" onclick="document.getElementById('announcement').style.display='none'">&times;</button>
        </div>
        <?php endif; ?>
        
        <!-- 消息提示 -->
        <?php if ($success_message): ?>
            <div class="message success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- 上传区域 -->
        <div class="upload-section" id="uploadSection">
            <div class="upload-icon-wrapper">
                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/>
                </svg>
            </div>
            <div class="upload-title">上传应用安装包</div>
            <div class="upload-subtitle">
                支持 <span class="ios">iOS .ipa</span> 和 <span class="android">Android .apk</span> 格式
            </div>
            <form id="uploadForm" class="input-form">
                <div class="form-group">
                    <div class="file-drop-zone" id="fileDropZone" onclick="document.getElementById('file').click()">
                        <div class="file-drop-zone-text">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-bottom: 10px;">
                                <path d="M12 16V8M12 8L9 11M12 8L15 11" stroke="#667eea" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M3 15V17C3 18.1046 3.89543 19 5 19H19C20.1046 19 21 18.1046 21 17V15" stroke="#667eea" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <div>拖拽文件到这里，或 <span class="browse">点击选择文件</span></div>
                        </div>
                        <div class="file-drop-zone-filename" id="fileName">
                            <div class="file-icon">
                                <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                                </svg>
                            </div>
                            <span id="fileNameText">filename.ipa</span>
                        </div>
                    </div>
                    <input type="file" id="file" name="file" required accept=".ipa,.apk" style="display: none;">
                </div>
                
                <div class="form-group">
                    <label for="password">访问密码 (可选)</label>
                    <input type="password" id="password" name="password" placeholder="设置访问密码" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px;">
                </div>
                
                <!-- 上传进度条 -->
                <div id="progressContainer" style="display: none;">
                    <div style="text-align: left; margin-bottom: 5px; font-size: 14px; display: flex; justify-content: space-between; align-items: center;">
                        <span id="progressText">上传中... 0%</span>
                        <span id="progressSize" style="color: #999;"></span>
                    </div>
                    <div style="width: 100%; height: 8px; background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                        <div id="progressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); border-radius: 4px;"></div>
                    </div>
                </div>
                
                <!-- 上传状态 -->
                <div id="uploadStatus" style="margin: 15px 0; padding: 10px; border-radius: 8px; display: none;"></div>
                
                <button type="button" id="uploadBtn" class="submit-btn upload-btn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin-right: 8px;">
                        <path d="M12 16V8M12 8L9 11M12 8L15 11" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M3 15V17C3 18.1046 3.89543 19 5 19H19C20.1046 19 21 18.1046 21 17V15" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    开始上传
                </button>
            </form>
        </div>
        
        <script>
        // 从后端获取上传限制配置
        const uploadConfig = {
            maxSize: <?php echo $config['upload']['max_size']; ?>,
            maxSizeReadable: '<?php echo $config['upload']['max_size_readable']; ?>'
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('uploadForm');
            const fileInput = document.getElementById('file');
            const passwordInput = document.getElementById('password');
            const uploadBtn = document.getElementById('uploadBtn');
            const progressContainer = document.getElementById('progressContainer');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const progressSize = document.getElementById('progressSize');
            const uploadStatus = document.getElementById('uploadStatus');
            const uploadSection = document.getElementById('uploadSection');
            const fileDropZone = document.getElementById('fileDropZone');
            const fileNameText = document.getElementById('fileNameText');
            
            // 文件选择后显示文件名
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    const file = this.files[0];
                    fileNameText.textContent = file.name;
                    fileDropZone.classList.add('has-file');
                }
            });
            
            // 拖拽上传 - 文件放置区
            fileDropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.style.borderColor = '#667eea';
                this.style.background = '#f0f4ff';
            });
            
            fileDropZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.style.borderColor = '';
                this.style.background = '';
            });
            
            fileDropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                this.style.borderColor = '';
                this.style.background = '';
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    fileNameText.textContent = files[0].name;
                    fileDropZone.classList.add('has-file');
                }
            });
            
            // 整个上传区域的拖拽（保持兼容）
            uploadSection.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadSection.classList.add('dragover');
            });
            
            uploadSection.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadSection.classList.remove('dragover');
            });
            
            uploadSection.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadSection.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    fileNameText.textContent = files[0].name;
                    fileDropZone.classList.add('has-file');
                }
            });
            
            uploadBtn.addEventListener('click', function() {
                const file = fileInput.files[0];
                if (!file) {
                    showStatus('请选择要上传的文件', 'error');
                    return;
                }
                
                // 检查文件类型
                const fileExt = file.name.split('.').pop().toLowerCase();
                if (!['ipa', 'apk'].includes(fileExt)) {
                    showStatus('只支持 IPA 和 APK 文件', 'error');
                    return;
                }
                
                // 检查文件大小（使用服务器配置的限制）
                if (file.size > uploadConfig.maxSize) {
                    showStatus('文件大小不能超过 ' + uploadConfig.maxSizeReadable, 'error');
                    return;
                }
                
                // 准备FormData
                const formData = new FormData();
                formData.append('file', file);
                formData.append('password', passwordInput.value);
                
                // 显示进度条
                progressContainer.style.display = 'block';
                uploadStatus.style.display = 'none';
                uploadBtn.disabled = true;
                uploadBtn.textContent = '上传中...';
                
                // 格式化文件大小
                function formatSize(bytes) {
                    if (bytes < 1024) return bytes + ' B';
                    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
                }
                
                // 创建XMLHttpRequest
                const xhr = new XMLHttpRequest();
                let uploadComplete = false;
                
                // 监听进度事件
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = Math.round((e.loaded / e.total) * 100);
                        progressBar.style.width = percentComplete + '%';
                        progressText.textContent = `上传中... ${percentComplete}%`;
                        progressSize.textContent = `${formatSize(e.loaded)} / ${formatSize(e.total)}`;
                        
                        // 上传完成，显示处理中状态
                        if (percentComplete >= 100 && !uploadComplete) {
                            uploadComplete = true;
                            progressText.innerHTML = '<div class="processing"><div class="spinner"></div><span>文件上传完成，正在处理中...</span></div>';
                            progressSize.textContent = '';
                        }
                    }
                });
                
                // 监听上传完成
                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                showStatus('上传成功！正在跳转...', 'success');
                                setTimeout(function() {
                                    window.location.href = 'index.php?success=1';
                                }, 500);
                            } else {
                                showStatus(response.error || '上传失败，请重试', 'error');
                                resetUploadForm();
                            }
                        } catch (e) {
                            // 如果返回的不是JSON，可能是重定向
                            window.location.href = 'index.php?success=1';
                        }
                    } else {
                        showStatus('上传失败，请重试', 'error');
                        resetUploadForm();
                    }
                });
                
                // 监听上传错误
                xhr.addEventListener('error', function() {
                    showStatus('上传失败，请检查网络连接', 'error');
                    resetUploadForm();
                });
                
                // 监听上传超时 - 设置较长的超时时间
                xhr.timeout = 300000; // 5分钟
                xhr.addEventListener('timeout', function() {
                    showStatus('上传超时，请重试', 'error');
                    resetUploadForm();
                });
                
                // 发送请求
                xhr.open('POST', 'api.php', true);
                xhr.send(formData);
            });
            
            // 显示状态信息
            function showStatus(message, type) {
                uploadStatus.textContent = message;
                uploadStatus.style.display = 'block';
                uploadStatus.style.backgroundColor = type === 'error' ? '#f8d7da' : '#d4edda';
                uploadStatus.style.color = type === 'error' ? '#721c24' : '#155724';
                uploadStatus.style.border = type === 'error' ? '1px solid #f5c6cb' : '1px solid #c3e6cb';
            }
            
            // 重置上传表单
            function resetUploadForm() {
                progressContainer.style.display = 'none';
                progressBar.style.width = '0%';
                progressText.textContent = '上传中... 0%';
                progressSize.textContent = '';
                uploadBtn.disabled = false;
                uploadBtn.textContent = '开始上传';
            }
        });
        </script>
        
        <!-- 上传历史 -->
        <div class="history-section">
            <h2>📋 上传历史</h2>
            
            <?php if (empty($uploads)): ?>
                <div class="empty-state">
                    <h3>暂无上传记录</h3>
                    <p>上传您的第一个应用开始使用</p>
                </div>
            <?php else: ?>
                <?php foreach ($uploads as $upload): ?>
                    <?php 
                    $is_ios = (isset($upload['platform']) && $upload['platform'] === 'ios');
                    $platform_class = $is_ios ? 'ios' : 'android';
                    $platform_icon = $is_ios ? '🍎' : '🤖';
                    $platform_name = $is_ios ? 'iOS' : 'Android';
                    ?>
                    <div class="history-item">
                        <!-- 应用图标 -->
                        <div class="app-icon">
                            <?php if (!empty($upload['icon']) && file_exists(__DIR__ . '/' . $upload['icon'])): ?>
                                <img src="<?php echo htmlspecialchars($upload['icon']); ?>?t=<?php echo time(); ?>" alt="应用图标">
                            <?php else: ?>
                                <?php echo $platform_icon; ?>
                            <?php endif; ?>
                            <span class="platform-badge <?php echo $platform_class; ?>">
                                <?php echo $is_ios ? '🍎' : '🤖'; ?>
                            </span>
                        </div>
                        
                        <!-- 应用信息 -->
                        <div class="app-info">
                            <span class="app-name"><?php echo htmlspecialchars($upload['app_name']); ?></span>
                            <span class="bundle-id">包名：<?php echo htmlspecialchars($upload['bundle_id']); ?></span>
                            <div style="margin-top: 4px;">
                                <span class="app-meta">
                                    <span class="platform-icon <?php echo $platform_class; ?>">
                                        <?php echo $platform_icon; ?> <?php echo $platform_name; ?>
                                    </span>
                                </span>
                                <span class="app-meta">v<?php echo htmlspecialchars($upload['version']); ?></span>
                                <span class="app-meta">📦 <?php echo round($upload['file_size'] / (1024 * 1024), 1); ?>MB</span>
                                <span class="app-meta">📥 <?php echo htmlspecialchars($upload['download_count']); ?>次</span>
                            </div>
                            <div style="margin-top: 4px;">
                                <span class="app-meta">🕐 <?php echo htmlspecialchars($upload['create_time']); ?></span>
                                <span class="app-meta">
                                    🔒 <?php 
                                        if (!empty($upload['password'])) {
                                            echo '已加密';
                                        } else {
                                            echo '公开';
                                        }
                                    ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- 操作按钮 -->
                        <div class="actions">
                            <a href="install.php?uid=<?php echo htmlspecialchars($upload['uid']); ?>" class="install-btn">安装</a>
                            <button class="copy-btn" onclick="showQrcode('<?php echo htmlspecialchars($upload['uid']); ?>', '<?php echo htmlspecialchars($upload['app_name']); ?>')">二维码</button>
                            <button class="copy-btn" style="background: #28a745;" onclick="copyLink('<?php echo htmlspecialchars($upload['uid']); ?>')">复制链接</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- 分页导航 -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <div class="pagination-info">
                    共 <?php echo $total_count; ?> 个应用，第 <?php echo $page; ?>/<?php echo $total_pages; ?> 页
                </div>
                <div class="pagination-links">
                    <?php if ($page > 1): ?>
                        <a href="?page=1" class="pagination-btn">首页</a>
                        <a href="?page=<?php echo $page - 1; ?>" class="pagination-btn">上一页</a>
                    <?php endif; ?>
                    
                    <?php
                    // 显示页码
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        if ($i == $page) {
                            echo '<span class="pagination-btn active">' . $i . '</span>';
                        } else {
                            echo '<a href="?page=' . $i . '" class="pagination-btn">' . $i . '</a>';
                        }
                    }
                    
                    if ($end_page < $total_pages) {
                        echo '<span class="pagination-ellipsis">...</span>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>" class="pagination-btn">下一页</a>
                        <a href="?page=<?php echo $total_pages; ?>" class="pagination-btn">末页</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- 底部平台信息 -->
        <div style="margin-top: 40px; padding: 30px; background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(10px); border-radius: 15px; text-align: center;">
            <div style="margin-bottom: 20px;">
                <h3 style="color: #333; margin-bottom: 10px;">关于平台</h3>
                <p style="color: #666; line-height: 1.6;">
                    <?php echo htmlspecialchars($config['site_description']); ?>
                </p>
                <p style="color: #666; line-height: 1.6; margin-top: 10px;">
                    <strong>重要提示：</strong>本平台禁止上传、分发任何侵权、违法或违规内容。
                    如有发现，我们将立即删除相关内容并保留追究法律责任的权利。
                    请用户自觉遵守相关法律法规，共同维护良好的网络环境。
                </p>
            </div>
            <div style="border-top: 1px solid #eee; padding-top: 20px;">
                <p style="color: #999; font-size: 14px;">
                    © <?php echo date('Y'); ?> <?php echo htmlspecialchars($config['site_name']); ?> | <?php echo htmlspecialchars(isset($config['footer_text']) ? $config['footer_text'] : '开发者：杰哥网络科技 | QQ: 2711793818'); ?>
                </p>
            </div>
        </div>
    </div>
    
    <!-- 二维码弹窗 -->
    <div id="qrcodeModal" class="modal" style="display: none;">
        <div class="modal-content" style="background: white; padding: 30px; border-radius: 15px; text-align: center; max-width: 350px; margin: auto; position: relative;">
            <span class="close-btn" onclick="closeQrcode()" style="position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; color: #999;">&times;</span>
            <h3 id="qrcodeTitle" style="margin-bottom: 20px; color: #333;">应用二维码</h3>
            <div id="qrcodeImage" style="margin-bottom: 15px;">
                <img src="" alt="二维码" style="max-width: 200px; border-radius: 10px;">
            </div>
            <p style="color: #666; font-size: 14px;">扫描二维码安装应用</p>
        </div>
    </div>
    
    <style>
    .modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }
    </style>
    
    <script>
    // 获取网站URL
    var siteUrl = '<?php echo $config['site_url']; ?>';
    if (!siteUrl) {
        siteUrl = window.location.origin;
    }
    
    // 显示二维码弹窗
    function showQrcode(uid, appName) {
        var modal = document.getElementById('qrcodeModal');
        var title = document.getElementById('qrcodeTitle');
        var img = document.querySelector('#qrcodeImage img');
        
        title.textContent = appName + ' - 安装二维码';
        img.src = 'qrcode.php?uid=' + uid + '&t=' + new Date().getTime();
        modal.style.display = 'flex';
    }
    
    // 关闭二维码弹窗
    function closeQrcode() {
        var modal = document.getElementById('qrcodeModal');
        modal.style.display = 'none';
    }
    
    // 复制链接
    function copyLink(uid) {
        var link = siteUrl + '/install.php?uid=' + uid;
        
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link).then(function() {
                showToast('链接已复制: ' + link);
            }).catch(function() {
                fallbackCopy(link);
            });
        } else {
            fallbackCopy(link);
        }
    }
    
    // 备用复制方法
    function fallbackCopy(text) {
        var textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            showToast('链接已复制: ' + text);
        } catch (err) {
            showToast('复制失败，请手动复制');
        }
        document.body.removeChild(textArea);
    }
    
    // 显示提示
    function showToast(message) {
        var toast = document.createElement('div');
        toast.style.cssText = 'position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 12px 24px; border-radius: 25px; font-size: 14px; z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s';
            setTimeout(function() {
                document.body.removeChild(toast);
            }, 300);
        }, 2000);
    }
    
    // 点击弹窗外部关闭
    document.getElementById('qrcodeModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeQrcode();
        }
    });
    </script>
</body>
</html>
