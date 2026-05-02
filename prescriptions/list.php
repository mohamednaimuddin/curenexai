<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = PRESCRIPTIONS_PER_PAGE ?? 20;
$offset = ($page - 1) * $perPage;

// Search and filters
$search = sanitize($_GET['search'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');

// Build query
$where = ['p.doctor_id = ?'];
$params = [$doctorId];

if (!empty($search)) {
    $where[] = '(pat.patient_name LIKE ? OR c.chief_complaint LIKE ? OR c.diagnosis LIKE ?)';
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($dateFrom)) {
    $where[] = 'p.prescription_date >= ?';
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $where[] = 'p.prescription_date <= ?';
    $params[] = $dateTo;
}

$whereClause = implode(' AND ', $where);

// Get total count
$countSql = "SELECT COUNT(*) as total 
             FROM prescriptions p
             INNER JOIN patients pat ON p.patient_id = pat.id
             INNER JOIN consultations c ON p.consultation_id = c.id
             WHERE $whereClause";
$total = DB::queryOne($countSql, $params)['total'];
$totalPages = ceil($total / $perPage);

// Get prescriptions - Security: Cast pagination to integers to prevent SQL injection
$safeOffset = (int)$offset;
$safePerPage = (int)$perPage;
$sql = "SELECT p.*, 
               pat.patient_name, pat.age, pat.gender,
               c.chief_complaint, c.diagnosis,
               (SELECT COUNT(*) FROM prescription_remedies WHERE prescription_id = p.id) as remedy_count
        FROM prescriptions p
        INNER JOIN patients pat ON p.patient_id = pat.id
        INNER JOIN consultations c ON p.consultation_id = c.id
        WHERE $whereClause
        ORDER BY p.prescription_date DESC, p.created_at DESC
        LIMIT $safeOffset, $safePerPage";

$prescriptions = DB::query($sql, $params);

$pageTitle = 'Prescriptions';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
    .prescriptions-list-container { position: relative; }
    .prescriptions-list-container::before {
        content: '';
        position: fixed;
        top: 60px;
        left: 220px;
        right: 0;
        bottom: 0;
        background: url('<?php echo APP_URL; ?>/assets/image/xrunbg.png') center center no-repeat;
        background-size: 45%;
        opacity: 0.08;
        pointer-events: none;
        z-index: 0;
    }
    @media (max-width: 992px) {
        .prescriptions-list-container::before { left: 0; top: 60px; }
    }
</style>

<div class="prescriptions-list-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-prescription"></i> Prescriptions</h1>
            <p class="text-muted">Manage and view all prescriptions</p>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="stats-grid">
        <?php
        $todayCount = DB::queryOne(
            "SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND DATE(prescription_date) = CURDATE()",
            [$doctorId]
        )['count'];
        
        $weekCount = DB::queryOne(
            "SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND prescription_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            [$doctorId]
        )['count'];
        
        $monthCount = DB::queryOne(
            "SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ? AND prescription_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            [$doctorId]
        )['count'];
        ?>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $todayCount; ?></h3>
                <p>Today's Prescriptions</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $weekCount; ?></h3>
                <p>This Week</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $monthCount; ?></h3>
                <p>This Month</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="fas fa-prescription"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $total; ?></h3>
                <p>Total Prescriptions</p>
            </div>
        </div>
    </div>
    
    <!-- Search & Filter -->
    <div class="dashboard-card">
        <div class="card-body">
            <form method="GET" action="" class="search-form">
                <div class="search-row">
                    <div class="search-field">
                        <input 
                            type="text" 
                            name="search" 
                            class="form-control" 
                            placeholder="Search by patient name, complaint, diagnosis..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                    </div>
                    
                    <div class="search-field">
                        <input 
                            type="date" 
                            name="date_from" 
                            class="form-control" 
                            placeholder="From Date"
                            value="<?php echo htmlspecialchars($dateFrom); ?>"
                        >
                    </div>
                    
                    <div class="search-field">
                        <input 
                            type="date" 
                            name="date_to" 
                            class="form-control" 
                            placeholder="To Date"
                            value="<?php echo htmlspecialchars($dateTo); ?>"
                        >
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    
                    <?php if ($search || $dateFrom || $dateTo): ?>
                    <a href="<?php echo APP_URL; ?>/prescriptions/list.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Clear
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Prescriptions List -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Prescriptions (<?php echo $total; ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (empty($prescriptions)): ?>
                <div class="empty-state">
                    <i class="fas fa-prescription"></i>
                    <h3>No prescriptions found</h3>
                    <p>Start by creating a consultation and writing a prescription.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Age/Gender</th>
                                <th>Chief Complaint</th>
                                <th>Remedies</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prescriptions as $prescription): ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo str_pad($prescription['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                </td>
                                <td>
                                    <?php echo formatDate($prescription['prescription_date'], 'd M Y'); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($prescription['patient_name']); ?></strong>
                                </td>
                                <td>
                                    <?php echo $prescription['age']; ?> / <?php echo ucfirst(substr($prescription['gender'], 0, 1)); ?>
                                </td>
                                <td>
                                    <?php echo truncate(htmlspecialchars($prescription['chief_complaint']), 50); ?>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <i class="fas fa-capsules"></i> <?php echo $prescription['remedy_count']; ?> Remedies
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="<?php echo APP_URL; ?>/prescriptions/view.php?id=<?php echo $prescription['id']; ?>" 
                                           class="btn btn-sm btn-primary" 
                                           title="View Prescription">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo APP_URL; ?>/prescriptions/edit.php?id=<?php echo $prescription['id']; ?>" 
                                           class="btn btn-sm btn-success" 
                                           title="Edit Prescription">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?php echo APP_URL; ?>/consultations/view.php?id=<?php echo $prescription['consultation_id']; ?>" 
                                           class="btn btn-sm btn-outline" 
                                           title="View Consultation">
                                            <i class="fas fa-notes-medical"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php
                    $queryParams = $_GET;
                    unset($queryParams['page']);
                    $queryString = http_build_query($queryParams);
                    $queryString = $queryString ? '&' . $queryString : '';
                    ?>
                    
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $queryString; ?>" class="pagination-btn">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                    </span>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $queryString; ?>" class="pagination-btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.prescriptions-list-container {
    max-width: 1400px;
    margin: 0 auto;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.stat-details h3 {
    margin: 0;
    font-size: 2rem;
    color: var(--gray-800);
}

.stat-details p {
    margin: 5px 0 0 0;
    color: var(--gray-600);
    font-size: 0.9rem;
}

.search-form {
    width: 100%;
}

.search-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.search-field {
    flex: 1;
    min-width: 200px;
}

.search-field input {
    width: 100%;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th,
.data-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--gray-200);
}

.data-table th {
    background: var(--gray-100);
    font-weight: 600;
    color: var(--gray-700);
}

.data-table tr:hover {
    background: var(--gray-50);
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
}

.badge-info {
    background: #e3f2fd;
    color: #1976d2;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
}

.empty-state i {
    font-size: 64px;
    opacity: 0.3;
    margin-bottom: 20px;
}

.empty-state h3 {
    margin: 0 0 10px 0;
    color: var(--gray-700);
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--gray-200);
}

.pagination-btn {
    padding: 8px 16px;
    border: 2px solid var(--primary-color);
    border-radius: 8px;
    color: var(--primary-color);
    text-decoration: none;
    transition: all 0.3s ease;
}

.pagination-btn:hover {
    background: var(--primary-color);
    color: white;
}

.pagination-info {
    color: var(--gray-600);
    font-weight: 500;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .search-row {
        flex-direction: column;
    }
    
    .search-field {
        width: 100%;
    }
    
    .data-table {
        font-size: 0.85rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 8px 5px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
