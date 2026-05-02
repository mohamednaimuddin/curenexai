<?php
/**
 * Super Admin - Prescriptions Overview
 */

define('ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'All Prescriptions';

// Filters
$doctorFilter = intval($_GET['doctor'] ?? 0);
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = [];
$params = [];

if ($doctorFilter) {
    $whereConditions[] = "pr.doctor_id = ?";
    $params[] = $doctorFilter;
}

if ($dateFrom) {
    $whereConditions[] = "DATE(pr.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = "DATE(pr.created_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$totalCount = DB::queryOne("SELECT COUNT(*) as count FROM prescriptions pr $whereClause", $params)['count'];
$totalPages = ceil($totalCount / $perPage);

// Get prescriptions - Security: Cast pagination to integers to prevent SQL injection
$safePerPage = (int)$perPage;
$safeOffset = (int)$offset;
$prescriptions = DB::query("
    SELECT pr.*, p.patient_name, d.full_name as doctor_name
    FROM prescriptions pr
    JOIN patients p ON pr.patient_id = p.id
    JOIN doctors d ON pr.doctor_id = d.id
    $whereClause
    ORDER BY pr.created_at DESC
    LIMIT $safePerPage OFFSET $safeOffset
", $params);

// Get doctors for filter
$doctors = DB::query("SELECT id, full_name FROM doctors ORDER BY full_name");

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Prescriptions Overview</h4>
        <p class="text-muted mb-0">View all prescriptions across all doctors</p>
    </div>
    <div>
        <span class="badge bg-primary fs-6"><?php echo number_format($totalCount); ?> Total Prescriptions</span>
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
                <input type="date" class="form-control" name="date_from" placeholder="From" value="<?php echo $dateFrom; ?>">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control" name="date_to" placeholder="To" value="<?php echo $dateTo; ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
            </div>
            <div class="col-md-2">
                <a href="prescriptions.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Prescriptions Table -->
<div class="data-table">
    <div class="table-header">
        <h5><i class="bi bi-file-medical me-2"></i>Prescriptions</h5>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Patient</th>
                    <th>Doctor</th>
                    <th>Remedies</th>
                    <th>Duration</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($prescriptions)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No prescriptions found</td></tr>
                <?php else: ?>
                    <?php foreach ($prescriptions as $prescription): ?>
                        <tr>
                            <td><strong>#<?php echo $prescription['id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($prescription['patient_name']); ?></td>
                            <td>
                                <a href="doctor_details.php?id=<?php echo $prescription['doctor_id']; ?>" class="text-decoration-none">
                                    <?php echo htmlspecialchars($prescription['doctor_name']); ?>
                                </a>
                            </td>
                            <td>
                                <?php 
                                $remedies = $prescription['remedy_name'] ?? $prescription['medicines'] ?? '-';
                                echo htmlspecialchars(substr($remedies, 0, 50)); 
                                echo strlen($remedies) > 50 ? '...' : '';
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($prescription['duration'] ?? '-'); ?></td>
                            <td><?php echo date('M j, Y', strtotime($prescription['created_at'])); ?></td>
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
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&doctor=<?php echo $doctorFilter; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&doctor=<?php echo $doctorFilter; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&doctor=<?php echo $doctorFilter; ?>&date_from=<?php echo $dateFrom; ?>&date_to=<?php echo $dateTo; ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
