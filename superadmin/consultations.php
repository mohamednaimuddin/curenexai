<?php
/**
 * Super Admin - Consultations Overview
 */

define('ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'All Consultations';

// Filters
$doctorFilter = intval($_GET['doctor'] ?? 0);
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = [];
$params = [];

if ($doctorFilter) {
    $whereConditions[] = "c.doctor_id = ?";
    $params[] = $doctorFilter;
}

if ($statusFilter) {
    $whereConditions[] = "c.status = ?";
    $params[] = $statusFilter;
}

if ($dateFrom) {
    $whereConditions[] = "DATE(c.consultation_date) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = "DATE(c.consultation_date) <= ?";
    $params[] = $dateTo;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$totalCount = DB::queryOne("SELECT COUNT(*) as count FROM consultations c $whereClause", $params)['count'];
$totalPages = ceil($totalCount / $perPage);

// Get consultations - Security: Cast pagination to integers to prevent SQL injection
$safePerPage = (int)$perPage;
$safeOffset = (int)$offset;
$consultations = DB::query("
    SELECT c.*, p.patient_name, d.full_name as doctor_name
    FROM consultations c
    JOIN patients p ON c.patient_id = p.id
    JOIN doctors d ON c.doctor_id = d.id
    $whereClause
    ORDER BY c.consultation_date DESC
    LIMIT $safePerPage OFFSET $safeOffset
", $params);

// Get doctors for filter
$doctors = DB::query("SELECT id, full_name FROM doctors ORDER BY full_name");

// Stats
$stats = [
    'total' => DB::queryOne("SELECT COUNT(*) as c FROM consultations")['c'],
    'today' => DB::queryOne("SELECT COUNT(*) as c FROM consultations WHERE DATE(consultation_date) = CURDATE()")['c'],
    'active' => DB::queryOne("SELECT COUNT(*) as c FROM consultations WHERE status = 'active'")['c'],
    'completed' => DB::queryOne("SELECT COUNT(*) as c FROM consultations WHERE status = 'completed'")['c'],
];

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Consultations Overview</h4>
        <p class="text-muted mb-0">View all consultations across all doctors</p>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div>
                    <p class="stat-label mb-1">Total</p>
                    <h4 class="stat-value mb-0"><?php echo number_format($stats['total']); ?></h4>
                </div>
                <div class="stat-icon" style="background: rgba(55, 66, 250, 0.1); color: var(--admin-info);">
                    <i class="bi bi-clipboard2-pulse"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div>
                    <p class="stat-label mb-1">Today</p>
                    <h4 class="stat-value mb-0"><?php echo $stats['today']; ?></h4>
                </div>
                <div class="stat-icon" style="background: rgba(233, 69, 96, 0.1); color: var(--admin-highlight);">
                    <i class="bi bi-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div>
                    <p class="stat-label mb-1">Active</p>
                    <h4 class="stat-value mb-0"><?php echo $stats['active']; ?></h4>
                </div>
                <div class="stat-icon" style="background: rgba(255, 184, 0, 0.1); color: var(--admin-warning);">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div>
                    <p class="stat-label mb-1">Completed</p>
                    <h4 class="stat-value mb-0"><?php echo $stats['completed']; ?></h4>
                </div>
                <div class="stat-icon" style="background: rgba(0, 208, 156, 0.1); color: var(--admin-success);">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="data-table mb-4">
    <div class="p-3">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <select class="form-select" name="doctor">
                    <option value="">All Doctors</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?php echo $doc['id']; ?>" <?php echo $doctorFilter == $doc['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($doc['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="follow_up" <?php echo $statusFilter === 'follow_up' ? 'selected' : ''; ?>>Follow Up</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_from" placeholder="From" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_to" placeholder="To" value="<?php echo $dateTo; ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
            </div>
            <div class="col-md-1">
                <a href="consultations.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Consultations Table -->
<div class="data-table">
    <div class="table-header">
        <h5><i class="bi bi-clipboard2-pulse me-2"></i>Consultations (<?php echo $totalCount; ?>)</h5>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Chief Complaint</th>
                    <th>Diagnosis</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($consultations)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No consultations found</td></tr>
                <?php else: ?>
                    <?php foreach ($consultations as $consultation): ?>
                        <tr>
                            <td><strong>#<?php echo $consultation['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($consultation['patient_name']); ?></td>
                            <td>
                                <a href="doctor_details.php?id=<?php echo $consultation['doctor_id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($consultation['doctor_name']); ?>
                                </a>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars(substr($consultation['chief_complaint'] ?? '-', 0, 50)); ?>
                                <?php echo strlen($consultation['chief_complaint'] ?? '') > 50 ? '...' : ''; ?></small>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars(substr($consultation['diagnosis'] ?? '-', 0, 40)); ?>
                                <?php echo strlen($consultation['diagnosis'] ?? '') > 40 ? '...' : ''; ?></small>
                            </td>
                            <td>
                                <?php
                                $statusClass = 'inactive';
                                if ($consultation['status'] === 'completed') $statusClass = 'active';
                                elseif ($consultation['status'] === 'active') $statusClass = 'suspended';
                                ?>
                                <span class="status-badge status-<?php echo $statusClass; ?>">
                                    <?php echo ucfirst($consultation['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($consultation['consultation_date'])); ?></td>
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
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&doctor=<?php echo $doctorFilter; ?>&status=<?php echo $statusFilter; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&doctor=<?php echo $doctorFilter; ?>&status=<?php echo $statusFilter; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&doctor=<?php echo $doctorFilter; ?>&status=<?php echo $statusFilter; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
