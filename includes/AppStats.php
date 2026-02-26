<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 应用统计类

class AppStats {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->createTables();
    }
    
    /**
     * 创建统计表
     */
    private function createTables() {
        // 下载统计表
        $this->db->query('CREATE TABLE IF NOT EXISTS download_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            app_uid VARCHAR(50) NOT NULL,
            download_time DATETIME NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            platform VARCHAR(20),
            status ENUM("started", "completed", "failed") DEFAULT "started",
            error_message TEXT,
            INDEX idx_app_uid (app_uid),
            INDEX idx_download_time (download_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        
        // 安装统计表
        $this->db->query('CREATE TABLE IF NOT EXISTS install_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            app_uid VARCHAR(50) NOT NULL,
            install_time DATETIME NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            platform VARCHAR(20),
            status ENUM("started", "completed", "failed") DEFAULT "started",
            error_message TEXT,
            INDEX idx_app_uid (app_uid),
            INDEX idx_install_time (install_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        
        // 每日统计汇总表
        $this->db->query('CREATE TABLE IF NOT EXISTS daily_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            app_uid VARCHAR(50) NOT NULL,
            stat_date DATE NOT NULL,
            download_count INT DEFAULT 0,
            install_count INT DEFAULT 0,
            install_success_count INT DEFAULT 0,
            UNIQUE KEY uk_app_date (app_uid, stat_date),
            INDEX idx_stat_date (stat_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }
    
    /**
     * 记录下载开始
     */
    public function recordDownloadStart($app_uid, $platform = 'unknown') {
        $ip = $this->getClientIP();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $time = date('Y-m-d H:i:s');
        
        $stmt = $this->db->prepare('INSERT INTO download_stats (app_uid, download_time, ip_address, user_agent, platform, status) VALUES (?, ?, ?, ?, ?, "started")');
        $stmt->bind_param('sssss', $app_uid, $time, $ip, $user_agent, $platform);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        
        return $id;
    }
    
    /**
     * 记录下载完成
     */
    public function recordDownloadComplete($id) {
        $stmt = $this->db->prepare('UPDATE download_stats SET status = "completed" WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        
        // 更新每日统计
        $this->updateDailyStats();
    }
    
    /**
     * 记录下载失败
     */
    public function recordDownloadFailed($id, $error = '') {
        $stmt = $this->db->prepare('UPDATE download_stats SET status = "failed", error_message = ? WHERE id = ?');
        $stmt->bind_param('si', $error, $id);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * 记录安装开始
     */
    public function recordInstallStart($app_uid, $platform = 'unknown') {
        $ip = $this->getClientIP();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $time = date('Y-m-d H:i:s');
        
        $stmt = $this->db->prepare('INSERT INTO install_stats (app_uid, install_time, ip_address, user_agent, platform, status) VALUES (?, ?, ?, ?, ?, "started")');
        $stmt->bind_param('sssss', $app_uid, $time, $ip, $user_agent, $platform);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        
        return $id;
    }
    
    /**
     * 记录安装完成
     */
    public function recordInstallComplete($id) {
        $stmt = $this->db->prepare('UPDATE install_stats SET status = "completed" WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        
        // 更新每日统计
        $this->updateDailyStats();
    }
    
    /**
     * 记录安装失败
     */
    public function recordInstallFailed($id, $error = '') {
        $stmt = $this->db->prepare('UPDATE install_stats SET status = "failed", error_message = ? WHERE id = ?');
        $stmt->bind_param('si', $error, $id);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * 更新每日统计
     */
    private function updateDailyStats() {
        $today = date('Y-m-d');
        
        // 获取今天所有应用的下载和安装统计
        $sql = "SELECT 
                    ds.app_uid,
                    COUNT(DISTINCT CASE WHEN dl.status = 'completed' THEN dl.id END) as download_count,
                    COUNT(DISTINCT CASE WHEN ins.status = 'completed' THEN ins.id END) as install_count,
                    COUNT(DISTINCT CASE WHEN ins.status = 'completed' THEN ins.id END) as install_success_count
                FROM urls ds
                LEFT JOIN download_stats dl ON ds.uid = dl.app_uid AND DATE(dl.download_time) = ?
                LEFT JOIN install_stats ins ON ds.uid = ins.app_uid AND DATE(ins.install_time) = ?
                GROUP BY ds.app_uid";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ss', $today, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if (empty($row['app_uid'])) continue;
            
            $download_count = (int)$row['download_count'];
            $install_count = (int)$row['install_count'];
            $install_success_count = (int)$row['install_success_count'];
            
            // 插入或更新
            $upsert = $this->db->prepare('INSERT INTO daily_stats (app_uid, stat_date, download_count, install_count, install_success_count) 
                VALUES (?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                download_count = VALUES(download_count), 
                install_count = VALUES(install_count), 
                install_success_count = VALUES(install_success_count)');
            $upsert->bind_param('ssiii', $row['app_uid'], $today, $download_count, $install_count, $install_success_count);
            $upsert->execute();
            $upsert->close();
        }
        $stmt->close();
    }
    
    /**
     * 获取应用统计
     */
    public function getAppStats($app_uid) {
        $stats = array(
            'total_downloads' => 0,
            'total_installs' => 0,
            'install_success_rate' => 0,
            'today_downloads' => 0,
            'today_installs' => 0,
            'last_7_days' => array()
        );
        
        // 总下载量
        $result = $this->db->query("SELECT COUNT(*) as count FROM download_stats WHERE app_uid = '" . $this->db->real_escape_string($app_uid) . "' AND status = 'completed'");
        $row = $result->fetch_assoc();
        $stats['total_downloads'] = (int)$row['count'];
        
        // 总安装量
        $result = $this->db->query("SELECT COUNT(*) as count FROM install_stats WHERE app_uid = '" . $this->db->real_escape_string($app_uid) . "' AND status = 'completed'");
        $row = $result->fetch_assoc();
        $stats['total_installs'] = (int)$row['count'];
        
        // 安装成功率
        $result = $this->db->query("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as success
            FROM install_stats WHERE app_uid = '" . $this->db->real_escape_string($app_uid) . "'");
        $row = $result->fetch_assoc();
        if ($row['total'] > 0) {
            $stats['install_success_rate'] = round(($row['success'] / $row['total']) * 100, 1);
        }
        
        // 今日统计
        $today = date('Y-m-d');
        $result = $this->db->query("SELECT download_count, install_count FROM daily_stats WHERE app_uid = '" . $this->db->real_escape_string($app_uid) . "' AND stat_date = '$today'");
        if ($row = $result->fetch_assoc()) {
            $stats['today_downloads'] = (int)$row['download_count'];
            $stats['today_installs'] = (int)$row['install_count'];
        }
        
        // 最近7天统计
        $sql = "SELECT stat_date, download_count, install_count, install_success_count 
                FROM daily_stats 
                WHERE app_uid = '" . $this->db->real_escape_string($app_uid) . "' 
                AND stat_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
                ORDER BY stat_date ASC";
        $result = $this->db->query($sql);
        while ($row = $result->fetch_assoc()) {
            $stats['last_7_days'][] = $row;
        }
        
        return $stats;
    }
    
    /**
     * 获取全局统计
     */
    public function getGlobalStats() {
        $stats = array(
            'total_apps' => 0,
            'total_downloads' => 0,
            'total_installs' => 0,
            'today_downloads' => 0,
            'today_installs' => 0,
            'top_apps' => array()
        );
        
        // 总应用数
        $result = $this->db->query("SELECT COUNT(*) as count FROM urls");
        $row = $result->fetch_assoc();
        $stats['total_apps'] = (int)$row['count'];
        
        // 总下载量（从urls表获取）
        $result = $this->db->query("SELECT SUM(download_count) as total FROM urls");
        $row = $result->fetch_assoc();
        $stats['total_downloads'] = (int)($row['total'] ?? 0);
        
        // 总安装量（从urls表获取，如果没有install_count字段则使用download_count）
        $result = $this->db->query("SHOW COLUMNS FROM urls LIKE 'install_count'");
        if ($result->num_rows > 0) {
            $result = $this->db->query("SELECT SUM(install_count) as total FROM urls");
            $row = $result->fetch_assoc();
            $stats['total_installs'] = (int)($row['total'] ?? 0);
        } else {
            $stats['total_installs'] = $stats['total_downloads'];
        }
        
        // 今日统计（简化版，直接显示总下载量）
        // 如果需要精确的今日统计，需要确保daily_stats表有数据
        $today = date('Y-m-d');
        $result = $this->db->query("SELECT SUM(download_count) as downloads FROM daily_stats WHERE stat_date = '$today'");
        if ($row = $result->fetch_assoc()) {
            $stats['today_downloads'] = (int)($row['downloads'] ?? 0);
        }
        
        // 如果今日统计为空，显示提示
        if ($stats['today_downloads'] == 0 && $stats['total_downloads'] > 0) {
            $stats['today_downloads'] = '-'; // 表示暂无今日数据
        }
        
        // 热门应用（按下载量）
        $sql = "SELECT uid, app_name, platform, download_count 
                FROM urls 
                ORDER BY download_count DESC 
                LIMIT 10";
        $result = $this->db->query($sql);
        while ($row = $result->fetch_assoc()) {
            if ($row['download_count'] > 0) {
                $stats['top_apps'][] = $row;
            }
        }
        
        // 如果没有下载数据，使用总下载量作为备用
        if (empty($stats['top_apps'])) {
            $stats['total_downloads'] = 0;
            $result = $this->db->query("SELECT SUM(download_count) as total FROM urls");
            if ($row = $result->fetch_assoc()) {
                $stats['total_downloads'] = (int)$row['total'];
            }
        }
        
        return $stats;
    }
    
    /**
     * 获取客户端IP
     */
    private function getClientIP() {
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
}
?>
