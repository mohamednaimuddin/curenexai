<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();
$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';

// Build query
$where = "doctor_id = ?";
$params = [$doctorId];

if (!empty($search)) {
    $where .= " AND (patient_name LIKE ? OR contact_number LIKE ? OR email LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Fetch patients with pagination
$result = DB::paginate('patients', $page, PATIENTS_PER_PAGE, $where, $params, 'created_at DESC');
$patients = $result['data'];

// Get consultation count for each patient
foreach ($patients as &$patient) {
    $consultationCount = DB::queryOne(
        "SELECT COUNT(*) as count FROM consultations WHERE patient_id = ?",
        [$patient['id']]
    );
    $patient['consultation_count'] = $consultationCount['count'] ?? 0;
    
    // Get last consultation date
    $lastConsultation = DB::queryOne(
        "SELECT MAX(consultation_date) as last_visit FROM consultations WHERE patient_id = ?",
        [$patient['id']]
    );
    $patient['last_visit'] = $lastConsultation['last_visit'] ?? null;
}

$pageTitle = 'Patients';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="patients-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-users"></i> Patients</h1>
            <p class="text-muted">Manage your patient records</p>
        </div>
        <div class="header-actions">
            <a href="<?php echo APP_URL; ?>/patients/add.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Add New Patient
            </a>
        </div>
    </div>
    
    <!-- Search & Filter -->
    <div class="dashboard-card">
        <div class="card-body">
            <form method="GET" action="list.php" class="search-form">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control" 
                        placeholder="Search by name, phone, or email..." 
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="list.php" class="btn btn-outline">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Patients List -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> All Patients (<?php echo $result['total']; ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (empty($patients)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <p>No patients found</p>
                    <?php if (empty($search)): ?>
                        <a href="<?php echo APP_URL; ?>/patients/add.php" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add First Patient
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Patient Name</th>
                                <th>Age/Gender</th>
                                <th>Contact</th>
                                <th>Consultations</th>
                                <th>Last Visit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients as $patient): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($patient['patient_name']); ?></strong>
                                        <?php if (!empty($patient['chronic_conditions'])): ?>
                                            <br>
                                            <span class="badge badge-warning">Chronic</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $patient['age']; ?> years / 
                                        <span class="badge badge-<?php echo $patient['gender'] == 'male' ? 'primary' : 'danger'; ?>">
                                            <?php echo ucfirst($patient['gender']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($patient['contact_number'])): ?>
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['contact_number']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($patient['email'])): ?>
                                            <br><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['email']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $patient['consultation_count']; ?> visits</span>
                                    </td>
                                    <td><?php echo $patient['last_visit'] ? formatDate($patient['last_visit']) : '<span class="text-muted">Never</span>'; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?php echo APP_URL; ?>/consultations/add.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-success" title="New Consultation">
                                                <i class="fas fa-stethoscope"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($result['totalPages'] > 1): ?>
                    <div class="pagination-container">
                        <?php echo pagination($result['page'], $result['totalPages'], 'list.php' . (!empty($search) ? '?search=' . urlencode($search) . '&' : '?')); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.patients-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
    box-sizing: border-box;
}
.page-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    gap: 16px;
}
.page-header h1 {
    font-size: 2rem;
    margin: 0;
}
.page-header p {
    margin: 4px 0 0 0;
    color: #666;
}
.header-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}
.dashboard-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.07);
    padding: 20px 18px;
    min-width: 0;
    margin-bottom: 18px;
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    gap: 8px;
}
.card-header h3 {
    margin: 0;
    font-size: 1.2rem;
    color: #333;
}
.card-body {
    min-width: 0;
}
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.98rem;
}
.data-table th, .data-table td {
    padding: 8px 6px;
    text-align: left;
    border-bottom: 1px solid #eee;
}
.data-table th {
    background: #f8f9fa;
    font-weight: 600;
}
.badge-primary {
    background: #667eea;
    color: #fff;
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 0.85em;
}
.badge-danger {
    background: #e53e3e;
    color: #fff;
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 0.85em;
}
.badge-warning {
    background: #f6ad55;
    color: #fff;
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 0.85em;
}
.badge-info {
    background: #3182ce;
    color: #fff;
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 0.85em;
}
.empty-state {
    text-align: center;
    color: #999;
    padding: 24px 0;
}
.empty-state i {
    font-size: 2.2rem;
    margin-bottom: 8px;
    color: #667eea;
}
.action-buttons {
    display: flex;
    gap: 5px;
}
.table-responsive {
    overflow-x: auto;
}
.pagination-container {
    margin-top: 20px;
    display: flex;
    justify-content: center;
}
.pagination {
    display: flex;
    list-style: none;
    gap: 5px;
}
.pagination li a,
.pagination li span {
    display: block;
    padding: 8px 12px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    color: #2d3748;
    text-decoration: none;
    transition: 0.2s;
}
.pagination li.active span {
    background: #667eea;
    color: #fff;
    border-color: #667eea;
}
.pagination li a:hover {
    background: #667eea;
    color: #fff;
    border-color: #667eea;
}
@media (max-width: 900px) {
    .patients-container {
        padding: 10px;
    }
    .dashboard-card {
        padding: 14px 8px;
    }
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
    .data-table th, .data-table td {
        padding: 6px 2px;
        font-size: 0.95em;
    }
}
@media (max-width: 600px) {
    .patients-container {
        padding: 4px;
        min-width: 0;
    }
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    .dashboard-card {
        padding: 10px 2px;
    }
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
    .data-table th, .data-table td {
        padding: 4px 1px;
        font-size: 0.92em;
    }
    .search-box {
        flex-direction: column;
        align-items: stretch;
        gap: 6px;
        position: static;
    }
    .search-box i {
        position: static;
        margin-bottom: 4px;
        left: unset;
    }
    .search-box .form-control {
        padding-left: 12px;
        width: 100%;
        min-width: 0;
        font-size: 1em;
    }
    .search-box button,
    .search-box .btn {
        width: 100%;
        min-width: 0;
        font-size: 1em;
    }
}
@media (max-width: 400px) {
    .patients-container {
        padding: 2px;
    }
    .dashboard-card {
        padding: 6px 1px;
    }
    .action-buttons {
        gap: 2px;
    }
    .data-table th, .data-table td {
        padding: 2px 0;
        font-size: 0.9em;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
