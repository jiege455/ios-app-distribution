<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 应用管理模块

$page_title = '应用管理';

// 处理删除单个应用
if (isset($_POST['delete_app'])) {
    $app_uid = $_POST['app_uid'];
    
    $app_query = "SELECT * FROM urls WHERE uid = ?";
    $stmt = $db->prepare($app_query);
    $stmt->bind_param('s', $app_uid);
    $stmt->execute();
    $result = $stmt->get_result();
    $app = $result->fetch_assoc();
    
    if ($app) {
        $file_path = dirname(__DIR__) . '/' . $app['ipa_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        $icon_path = dirname(__DIR__) . '/' . $app['icon'];
        if (!empty($app['icon']) && file_exists($icon_path)) {
            unlink($icon_path);
        }
        
        $manifest_path = dirname(__DIR__) . '/manifests/' . $app_uid . '.plist';
        if (file_exists($manifest_path)) {
            unlink($manifest_path);
        }
        
        $delete_query = "DELETE FROM urls WHERE uid = ?";
        $stmt = $db->prepare($delete_query);
        $stmt->bind_param('s', $app_uid);
        $stmt->execute();
        
        logOperation($db, 'delete_app', '删除应用: ' . $app['app_name']);
        $success = '应用已删除';
    }
}

// 处理批量删除
if (isset($_POST['batch_delete'])) {
    $app_uids = isset($_POST['app_uids']) ? $_POST['app_uids'] : array();
    
    if (!empty($app_uids) && is_array($app_uids)) {
        $deleted_count = 0;
        foreach ($app_uids as $uid) {
            $app_query = "SELECT * FROM urls WHERE uid = ?";
            $stmt = $db->prepare($app_query);
            $stmt->bind_param('s', $uid);
            $stmt->execute();
            $app = $stmt->get_result()->fetch_assoc();
            
            if ($app) {
                $file_path = dirname(__DIR__) . '/' . $app['ipa_path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                
                $icon_path = dirname(__DIR__) . '/' . $app['icon'];
                if (!empty($app['icon']) && file_exists($icon_path)) {
                    unlink($icon_path);
                }
                
                $manifest_path = dirname(__DIR__) . '/manifests/' . $uid . '.plist';
                if (file_exists($manifest_path)) {
                    unlink($manifest_path);
                }
                
                $delete_query = "DELETE FROM urls WHERE uid = ?";
                $stmt = $db->prepare($delete_query);
                $stmt->bind_param('s', $uid);
                $stmt->execute();
                $deleted_count++;
            }
        }
        logOperation($db, 'batch_delete_apps', '批量删除应用: ' . $deleted_count . '个');
        $success = '成功删除 ' . $deleted_count . ' 个应用';
    }
}

// 处理取消违规标记
if (isset($_POST['unmark_violation'])) {
    $app_uid = $_POST['app_uid'];
    $update_query = "UPDATE urls SET is_violation = 0, violation_time = NULL WHERE uid = ?";
    $stmt = $db->prepare($update_query);
    $stmt->bind_param('s', $app_uid);
    $stmt->execute();
    logOperation($db, 'unmark_violation', '取消违规标记: ' . $app_uid);
    $success = '已取消违规标记';
}

// 获取应用列表
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = '';
if (!empty($search)) {
    $where_clause = " WHERE app_name LIKE '%" . $db->real_escape_string($search) . "%' OR bundle_id LIKE '%" . $db->real_escape_string($search) . "%'";
}

$apps_result = $db->query("SELECT * FROM urls $where_clause ORDER BY create_time DESC");
$apps = array();
while ($row = $apps_result->fetch_assoc()) {
    $apps[] = $row;
}

include 'header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>📱 应用管理</h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <form method="get" style="display: flex; gap: 10px;">
                <input type="hidden" name="page" value="apps">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="搜索应用..." style="padding: 6px 12px; border: 1px solid var(--gray-300); border-radius: 6px;">
                <button type="submit" class="btn btn-sm btn-outline">搜索</button>
            </form>
            <form method="post" id="batchForm" onsubmit="return confirmBatchDelete();" style="display: inline;">
                <button type="submit" name="batch_delete" class="btn btn-sm btn-danger" id="batchDeleteBtn" disabled>批量删除</button>
                <span id="selectedCount" style="margin-left: 10px; font-size: 12px; color: var(--gray-500);"></span>
            </form>
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">
                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this, 'app-checkbox')">
                    </th>
                    <th>应用名称</th>
                    <th>平台</th>
                    <th>版本</th>
                    <th>大小</th>
                    <th>下载次数</th>
                    <th>上传时间</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($apps as $app): ?>
                <tr>
                    <td>
                        <input type="checkbox" name="app_uids[]" value="<?php echo $app['uid']; ?>" class="app-checkbox" form="batchForm" onchange="updateSelectedCount('app-checkbox')">
                    </td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <?php if (!empty($app['icon'])): ?>
                                <img src="<?php echo dirname($_SERVER['PHP_SELF']) . '/../' . $app['icon']; ?>" style="width: 40px; height: 40px; border-radius: 8px; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 40px; height: 40px; background: var(--gray-200); border-radius: 8px; display: flex; align-items: center; justify-content: center;">📱</div>
                            <?php endif; ?>
                            <div>
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($app['app_name']); ?></div>
                                <div style="font-size: 11px; color: var(--gray-500);"><?php echo htmlspecialchars($app['bundle_id']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?php echo getPlatformIcon($app['platform']); ?></td>
                    <td><?php echo htmlspecialchars($app['version']); ?></td>
                    <td><?php echo formatFileSize($app['file_size']); ?></td>
                    <td><?php echo $app['download_count']; ?></td>
                    <td><?php echo isset($app['create_time']) ? $app['create_time'] : ''; ?></td>
                    <td>
                        <?php if (isset($app['is_violation']) && $app['is_violation'] == 1): ?>
                            <span class="badge badge-danger">违规</span>
                        <?php else: ?>
                            <span class="badge badge-success">正常</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-btns">
                            <a href="<?php echo dirname($_SERVER['PHP_SELF']) . '/../install.php?uid=' . $app['uid']; ?>" target="_blank" class="btn btn-sm btn-primary">查看</a>
                            <?php if (isset($app['is_violation']) && $app['is_violation'] == 1): ?>
                                <form method="post" style="display: inline;">
                                    <input type="hidden" name="app_uid" value="<?php echo $app['uid']; ?>">
                                    <button type="submit" name="unmark_violation" class="btn btn-sm btn-success">取消违规</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" style="display: inline;" onsubmit="return confirm('确定要删除此应用吗？');">
                                <input type="hidden" name="app_uid" value="<?php echo $app['uid']; ?>">
                                <button type="submit" name="delete_app" class="btn btn-sm btn-danger">删除</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>
