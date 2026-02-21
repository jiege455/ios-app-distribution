<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 后台头部模板
if (!defined('ADMIN_LOADED')) {
    die('直接访问不允许');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>后台管理 - iOS应用分发平台</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #667eea;
            --primary-dark: #764ba2;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-900: #111827;
            --shadow: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.1);
            --radius: 12px;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--gray-100);
            color: var(--gray-900);
            min-height: 100vh;
            line-height: 1.5;
        }
        
        .top-nav {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 0 20px;
            height: 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
        }
        
        .top-nav .logo {
            font-size: 18px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .top-nav .logo span {
            display: none;
        }
        
        .top-nav .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .top-nav .logout-btn {
            background: rgba(255,255,255,0.15);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 13px;
            transition: all 0.2s;
        }
        
        .top-nav .logout-btn:hover {
            background: rgba(255,255,255,0.25);
        }
        
        .menu-toggle {
            display: block;
            background: none;
            border: none;
            color: white;
            font-size: 24px;
            cursor: pointer;
            padding: 5px;
        }
        
        .sidebar {
            position: fixed;
            top: 60px;
            left: -260px;
            width: 260px;
            height: calc(100vh - 60px);
            background: white;
            box-shadow: var(--shadow-lg);
            transition: left 0.3s ease;
            z-index: 999;
            overflow-y: auto;
        }
        
        .sidebar.open {
            left: 0;
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 60px;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 998;
        }
        
        .sidebar-overlay.show {
            display: block;
        }
        
        .nav-menu {
            padding: 15px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: var(--gray-700);
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 5px;
            transition: all 0.2s;
            font-size: 14px;
        }
        
        .nav-item:hover {
            background: var(--gray-100);
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .nav-item .icon {
            font-size: 18px;
            width: 24px;
            text-align: center;
        }
        
        .main-content {
            margin-top: 60px;
            padding: 20px;
            min-height: calc(100vh - 60px);
        }
        
        .message {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .message.error {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .stat-card .icon {
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .stat-card .number {
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .stat-card .label {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 4px;
        }
        
        .card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 600px;
        }
        
        table th, table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }
        
        table th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
            white-space: nowrap;
        }
        
        table tr:hover td {
            background: var(--gray-50);
        }
        
        /* 移动端表格优化 */
        @media (max-width: 767px) {
            table {
                min-width: 500px;
            }
            
            .table-wrapper {
                margin: 0 -20px;
                padding: 0 20px;
            }
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-secondary {
            background: var(--gray-500);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-300);
            color: var(--gray-700);
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-check input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }
        
        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }
        
        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .divider {
            height: 1px;
            background: var(--gray-200);
            margin: 20px 0;
        }
        
        .platform-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .platform-ios {
            background: #f0f9ff;
        }
        
        .platform-android {
            background: #f0fdf4;
        }
        
        .action-btns {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        /* 手机端 - 小于768px */
        @media (max-width: 767px) {
            .top-nav .logo span {
                display: none;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .card-header h2 {
                font-size: 15px;
            }
            
            table {
                font-size: 12px;
            }
            
            table th, table td {
                padding: 8px 6px;
            }
            
            .btn {
                padding: 6px 12px;
                font-size: 12px;
            }
            
            .form-group input,
            .form-group select,
            .form-group textarea {
                font-size: 16px;
            }
        }
        
        /* iPad端 - 768px到1024px */
        @media (min-width: 768px) and (max-width: 1023px) {
            .top-nav .logo span {
                display: inline;
            }
            
            .menu-toggle {
                display: none;
            }
            
            .sidebar {
                left: 0;
                width: 220px;
            }
            
            .sidebar-overlay {
                display: none !important;
            }
            
            .main-content {
                margin-left: 220px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .nav-item {
                padding: 10px 12px;
                font-size: 13px;
            }
        }
        
        /* 桌面端 - 大于1024px */
        @media (min-width: 768px) {
            .top-nav .logo span {
                display: inline;
            }
            
            .menu-toggle {
                display: none;
            }
            
            .sidebar {
                left: 0;
            }
            
            .sidebar-overlay {
                display: none !important;
            }
            
            .main-content {
                margin-left: 260px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (min-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(6, 1fr);
            }
        }
    </style>
</head>
<body>
    <nav class="top-nav">
        <div class="logo">
            <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
            <span>📱</span>
            <span>后台管理</span>
        </div>
        <div class="user-info">
            <a href="?action=logout" class="logout-btn">退出登录</a>
        </div>
    </nav>
    
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    
    <aside class="sidebar">
        <nav class="nav-menu">
            <a href="?page=dashboard" class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>" data-section="dashboard">
                <span class="icon">📊</span>
                <span>仪表盘</span>
            </a>
            <a href="?page=apps" class="nav-item <?php echo $current_page === 'apps' ? 'active' : ''; ?>" data-section="apps">
                <span class="icon">📱</span>
                <span>应用管理</span>
            </a>
            <a href="?page=reports" class="nav-item <?php echo $current_page === 'reports' ? 'active' : ''; ?>" data-section="reports">
                <span class="icon">⚠️</span>
                <span>举报处理</span>
            </a>
            <a href="?page=announcement" class="nav-item <?php echo $current_page === 'announcement' ? 'active' : ''; ?>" data-section="announcement">
                <span class="icon">📢</span>
                <span>公告管理</span>
            </a>
            <a href="?page=ads" class="nav-item <?php echo $current_page === 'ads' ? 'active' : ''; ?>" data-section="ads">
                <span class="icon">💰</span>
                <span>广告管理</span>
            </a>
            <a href="?page=logs" class="nav-item <?php echo $current_page === 'logs' ? 'active' : ''; ?>" data-section="logs">
                <span class="icon">📋</span>
                <span>操作日志</span>
            </a>
            <a href="?page=settings" class="nav-item <?php echo $current_page === 'settings' ? 'active' : ''; ?>" data-section="settings">
                <span class="icon">⚙️</span>
                <span>系统设置</span>
            </a>
            <a href="?page=update" class="nav-item <?php echo $current_page === 'update' ? 'active' : ''; ?>" data-section="update">
                <span class="icon">🔄</span>
                <span>系统更新</span>
            </a>
        </nav>
        
        <div style="padding: 15px; border-top: 1px solid var(--gray-200); margin-top: auto;">
            <a href="mailto:2711793818@qq.com?subject=应用分发平台反馈" class="btn btn-primary" style="width: 100%; justify-content: center;">
                📧 问题反馈
            </a>
        </div>
    </aside>
    
    <main class="main-content">
        <?php if (isset($success)): ?>
            <div class="message success">✓ <?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="message error">✕ <?php echo $error; ?></div>
        <?php endif; ?>
