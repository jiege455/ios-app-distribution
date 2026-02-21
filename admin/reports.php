<?php
// 开发者：杰哥网络科技
// QQ: 2711793818
// 举报处理模块

$page_title = '举报处理';

// 处理举报
if (isset($_POST['process_report'])) {
    $report_id = $_POST['report_id'];
    $status = $_POST['status'];
    $app_uid = $_POST['app_uid'];
    
    $update_report_sql = "UPDATE reports SET status = ?, process_time = NOW() WHERE id = ?";
    $stmt = $db->prepare($update_report_sql);
    $stmt->bind_param('si', $status, $report_id);
    $stmt->execute();
    
    if ($status === 'processed') {
        $update_app_sql = "UPDATE urls SET is_violation = 1, violation_time = NOW() WHERE uid = ?";
        $stmt2 = $db->prepare($update_app_sql);
        $stmt2->bind_param('s', $app_uid);
        $stmt2->execute();
        logOperation($db, 'mark_violation', '标记应用违规: ' . $app_uid);
    }
    
    logOperation($db, 'process_report', '处理举报: ' . $report_id . ', 状态: ' . $status);
    $success = '操作成功';
}

// 删除单条举报
if (isset($_POST['delete_report'])) {
    $report_id = (int)$_POST['report_id'];
    $delete_sql = "DELETE FROM reports WHERE id = ?";
    $stmt = $db->prepare($delete_sql);
    $stmt->bind_param('i', $report_id);
    $stmt->execute();
    logOperation($db, 'delete_report', '删除举报记录: ' . $report_id);
    $success = '举报记录已删除';
}

// 批量删除举报
if (isset($_POST['batch_delete_reports'])) {
    $report_ids = isset($_POST['report_ids']) ? $_POST['report_ids'] : array();
    
    if (!empty($report_ids)) {
        $deleted_count = 0;
        foreach ($report_ids as $id) {
            $id = (int)$id;
            $delete_sql = "DELETE FROM reports WHERE id = ?";
            $stmt = $db->prepare($delete_sql);
            $stmt->bind_param('i', $id);
            if ($stmt->execute()) {
                $deleted_count++;
            }
        }
        logOperation($db, 'batch_delete_reports', '批量删除举报记录: ' . $deleted_count . '条');
        $success = '成功删除 ' . $deleted_count . ' 条举报记录';
    } else {
        $error = '请选择要删除的记录';
    }
}

// 获取举报列表
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where_clause = '';
if ($filter === 'pending') {
    $where_clause = " WHERE r.status = 'pending'";
} elseif ($filter === 'processed') {
    $where_clause = " WHERE r.status = 'processed'";
}

$reports_result = $db->query("SELECT r.*, u.app_name, u.platform as app_platform FROM reports r LEFT JOIN urls u ON r.app_uid = u.uid $where_clause ORDER BY r.create_time DESC");
$reports = array();
while ($row = $reports_result->fetch_assoc()) {
    $reports[] = $row;
}

include 'header.php';
?>

<div class="card">
    <div class="card-header">
        <h2>⚠️ 举报处理</h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="?page=reports&filter=all" class="btn btn-sm <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline'; ?>">全部</a>
            <a href="?page=reports&filter=pending" class="btn btn-sm <?php echo $filter === 'pending' ? 'btn-primary' : 'btn-outline'; ?>">待处理</a>
            <a href="?page=reports&filter=processed" class="btn btn-sm <?php echo $filter === 'processed' ? 'btn-primary' : 'btn-outline'; ?>">已处理</a>
            <form method="post" id="reportBatchForm" onsubmit="return confirm('确定要删除选中的举报记录吗？');" style="display: inline;">
                <button type="submit" name="batch_delete_reports" class="btn btn-sm btn-danger" id="batchDeleteBtn" disabled>批量删除</button>
                <span id="selectedCount" style="margin-left: 10px; font-size: 12px; color: var(--gray-500);"></span>
            </form>
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">
                        <input type="checkbox" id="selectAll" onclick="toggleSelectAll(this, 'report-checkbox')">
                    </th>
                    <th>应用名称</th>
                    <th>平台</th>
                    <th>举报类型</th>
                    <th>举报时间</th>
                    <th>状态</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <?php $status = isset($report['status']) ? $report['status'] : 'pending'; ?>
                <tr>
                    <td>
                        <input type="checkbox" name="report_ids[]" value="<?php echo $report['id']; ?>" class="report-checkbox" form="reportBatchForm" onchange="updateSelectedCount('report-checkbox')">
                    </td>
                    <td><?php echo htmlspecialchars($report['app_name'] ?? '未知'); ?></td>
                    <td><?php echo getPlatformIcon($report['app_platform'] ?? 'android'); ?></td>
                    <td><?php echo getReportTypeLabel($report['report_type']); ?></td>
                    <td><?php echo $report['create_time']; ?></td>
                    <td><?php echo getStatusBadge($status); ?></td>
                    <td>
                        <div class="action-btns">
                            <?php if ($status === 'pending'): ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                <input type="hidden" name="app_uid" value="<?php echo htmlspecialchars($report['app_uid']); ?>">
                                <select name="status" style="padding: 6px 10px; border-radius: 6px; border: 1px solid var(--gray-300); font-size: 12px;">
                                    <option value="processed">标记违规</option>
                                    <option value="ignored">忽略</option>
                                </select>
                                <button type="submit" name="process_report" class="btn btn-sm btn-primary">处理</button>
                            </form>
                            <?php endif; ?>
                            <form method="post" style="display: inline;" onsubmit="return confirm('确定要删除这条举报记录吗？');">
                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                <button type="submit" name="delete_report" class="btn btn-sm btn-danger">删除</button>
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
