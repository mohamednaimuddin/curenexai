<?php
/**
 * Super Admin - Doctor Activity Logs
 */

define('ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'Doctor Logs';

// Filters
$doctorFilter = intval($_GET['doctor'] ?? 0);
$actionFilter = $_GET['action'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = [];
$params = [];

if ($doctorFilter) {
    $whereConditions[] = "dl.doctor_id = ?";
    $params[] = $doctorFilter;
}

if ($actionFilter) {
    $whereConditions[] = "dl.action = ?";
    $params[] = $actionFilter;
}

if ($dateFrom) {
    $whereConditions[] = "DATE(dl.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = "DATE(dl.created_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get logs (with error handling)
$logs = [];
$totalCount = 0;
$totalPages = 0;
$doctors = [];
$actions = [];

try {
    $totalCount = DB::queryOne("SELECT COUNT(*) as count FROM doctor_activity_logs dl $whereClause", $params)['count'];
    $totalPages = ceil($totalCount / $perPage);
    
    // Security: Cast pagination to integers to prevent SQL injection
    $safePerPage = (int)$perPage;
    $safeOffset = (int)$offset;
    $logs = DB::query("
        SELECT dl.*, d.full_name as doctor_name, d.email
        FROM doctor_activity_logs dl
        JOIN doctors d ON dl.doctor_id = d.id
        $whereClause
        ORDER BY dl.created_at DESC
        LIMIT $safePerPage OFFSET $safeOffset
    ", $params);
    
    $actions = DB::query("SELECT DISTINCT action FROM doctor_activity_logs ORDER BY action");
} catch (Exception $e) {
    // Table might not exist
}

$doctors = DB::query("SELECT id, full_name FROM doctors ORDER BY full_name");

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Doctor Activity Logs</h4>
        <p class="text-muted mb-0">Monitor all doctor activities</p>
    </div>
    <div>
        <span class="badge bg-secondary fs-6"><?php echo number_format($totalCount); ?> Log Entries</span>
    </div>
</div>

<?php if ($totalCount === 0 && empty($actions)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        No doctor activity logs yet. To enable doctor activity logging:
        <ol class="mt-2 mb-0">
            <li>Ensure the <code>doctor_activity_logs</code> table exists (run <code>database/superadmin_schema.sql</code>)</li>
            <li>Add logging calls in doctor actions (login, patient operations, etc.)</li>
        </ol>
    </div>
    
    <div class="data-table">
        <div class="p-4 text-center">
            <h6>Sample Code to Add Doctor Activity Logging:</h6>
            <pre class="text-start bg-light p-3 rounded"><code>// In doctor login success:
DB::insert('doctor_activity_logs', [
    'doctor_id' => $doctorId,
    'action' => 'login',
    'details' => 'Doctor logged in',
    'ip_address' => $_SERVER['REMOTE_ADDR'],
    'user_agent' => $_SERVER['HTTP_USER_AGENT']
]);

// In patient creation:
DB::insert('doctor_activity_logs', [
    'doctor_id' => $doctorId,
    'action' => 'create_patient',
    'details' => "Created patient: $patientName",
    'target_type' => 'patient',
    'target_id' => $patientId,
    'ip_address' => $_SERVER['REMOTE_ADDR']
]);</code></pre>
        </div>
    </div>
<?php else: ?>

<!-- Filters -->
<div class="data-table mb-4">
    <div class="p-3">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <select class="form-select" name="doctor">
                    <option value="">All Doctors</option>
                    <?php foreach ($doctors as $doctor): ?>
                        <option value="<?php echo $doctor['id']; ?>" <?php echo $doctorFilter == $doctor['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($doctor['full_name']); ?>
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
                <a href="doctor_logs.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Logs Table -->
<div class="data-table">
    <div class="table-header">
        <h5><i class="bi bi-person-lines-fill me-2"></i>Doctor Logs</h5>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Doctor</th>
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
                                <a href="doctor_details.php?id=<?php echo $log['doctor_id']; ?>" class="text-decoration-none">
                                    <strong><?php echo htmlspecialchars($log['doctor_name']); ?></strong>
                                </a>
                                <br><small class="text-muted"><?php echo htmlspecialchars($log['email']); ?></small>
                            </td>
                            <td>
                                <?php
                                $actionColors = [
                                    'login' => 'success',
                                    'logout' => 'secondary',
                                    'create_patient' => 'primary',
                                    'update_patient' => 'info',
                                    'delete_patient' => 'danger',
                                    'create_consultation' => 'primary',
                                    'create_prescription' => 'warning',
                                    'view_patient' => 'light',
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
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&doctor=<?php echo $doctorFilter; ?>&action=<?php echo urlencode($actionFilter); ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&doctor=<?php echo $doctorFilter; ?>&action=<?php echo urlencode($actionFilter); ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&doctor=<?php echo $doctorFilter; ?>&action=<?php echo urlencode($actionFilter); ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
