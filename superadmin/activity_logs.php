<?php
/**
 * Super Admin - Admin Activity Logs
 */

define('ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'Admin Activity Logs';

// Filters
$adminFilter = intval($_GET['admin'] ?? 0);
$actionFilter = $_GET['action'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = [];
$params = [];

if ($adminFilter) {
    $whereConditions[] = "al.admin_id = ?";
    $params[] = $adminFilter;
}

if ($actionFilter) {
    $whereConditions[] = "al.action = ?";
    $params[] = $actionFilter;
}

if ($dateFrom) {
    $whereConditions[] = "DATE(al.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = "DATE(al.created_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get logs (with error handling in case table doesn't exist)
$logs = [];
$totalCount = 0;
$totalPages = 0;
$admins = [];
$actions = [];

try {
    $totalCount = DB::queryOne("SELECT COUNT(*) as count FROM admin_activity_logs al $whereClause", $params)['count'];
    $totalPages = ceil($totalCount / $perPage);
    
    // Security: Cast pagination to integers to prevent SQL injection
    $safePerPage = (int)$perPage;
    $safeOffset = (int)$offset;
    $logs = DB::query("
        SELECT al.*, sa.full_name as admin_name, sa.username
        FROM admin_activity_logs al
        JOIN super_admins sa ON al.admin_id = sa.id
        $whereClause
        ORDER BY al.created_at DESC
        LIMIT $safePerPage OFFSET $safeOffset
    ", $params);
    
    $admins = DB::query("SELECT id, full_name, username FROM super_admins ORDER BY full_name");
    $actions = DB::query("SELECT DISTINCT action FROM admin_activity_logs ORDER BY action");
} catch (Exception $e) {
    // Tables might not exist yet
}

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Admin Activity Logs</h4>
        <p class="text-muted mb-0">Track all administrative actions</p>
    </div>
    <div>
        <span class="badge bg-secondary fs-6"><?php echo number_format($totalCount); ?> Log Entries</span>
    </div>
</div>

<?php if (empty($logs) && $totalCount === 0): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        No activity logs yet. Logs will appear here when admins perform actions.
        <?php if (!$admins): ?>
            <br><br><strong>Note:</strong> The super admin tables may not exist yet. Please run the SQL schema in <code>database/superadmin_schema.sql</code>.
        <?php endif; ?>
    </div>
<?php else: ?>

<!-- Filters -->
<div class="data-table mb-4">
    <div class="p-3">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <select class="form-select" name="admin">
                    <option value="">All Admins</option>
                    <?php foreach ($admins as $admin): ?>
                        <option value="<?php echo $admin['id']; ?>" <?php echo $adminFilter == $admin['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($admin['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="action">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?php echo $action['action']; ?>" <?php echo $actionFilter === $action['action'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($action['action']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_from" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_to" value="<?php echo $dateTo; ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="activity_logs.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="data-table">
    <div class="table-header">
        <h5><i class="bi bi-journal-text me-2"></i>Activity Logs</h5>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>Target</th>
                    <th>IP Address</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No logs found</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><strong>#<?php echo $log['id']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($log['admin_name']); ?></strong>
                                <br><small class="text-muted">@<?php echo htmlspecialchars($log['username']); ?></small>
                            </td>
                            <td>
                                <?php
                                $actionColors = [
                                    'login' => 'success',
                                    'logout' => 'secondary',
                                    'create_doctor' => 'primary',
                                    'activate_doctor' => 'success',
                                    'deactivate_doctor' => 'warning',
                                    'suspend_doctor' => 'danger',
                                    'delete_doctor' => 'danger',
                                    'reset_password' => 'info',
                                ];
                                $color = $actionColors[$log['action']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>">
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td><small><?php echo htmlspecialchars($log['details'] ?? '-'); ?></small></td>
                            <td>
                                <?php if ($log['target_type'] && $log['target_id']): ?>
                                    <span class="badge bg-light text-dark">
                                        <?php echo ucfirst($log['target_type']); ?> #<?php echo $log['target_id']; ?>
                                    </span>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></code></td>
                            <td>
                                <?php echo date('M j, Y', strtotime($log['created_at'])); ?>
                                <br><small class="text-muted"><?php echo date('g:i:s A', strtotime($log['created_at'])); ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if ($totalPages > 1): ?>
        <div class="p-3 border-top">
            <nav>
                <ul class="pagination mb-0 justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&admin=<?php echo $adminFilter; ?>&action=<?php echo urlencode($actionFilter); ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&admin=<?php echo $adminFilter; ?>&action=<?php echo urlencode($actionFilter); ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&admin=<?php echo $adminFilter; ?>&action=<?php echo urlencode($actionFilter); ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
