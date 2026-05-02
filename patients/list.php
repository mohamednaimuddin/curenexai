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
    $where .= " AND (patient_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
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

<style>
    .patients-container { position: relative; }
    .patients-container::before {
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
        .patients-container::before { left: 0; top: 60px; }
    }
</style>

<div class="patients-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-users"></i> Patients</h1>
            <p class="text-muted">Manage your patient records</p>
        </div>
        <div class="header-actions">
            <a href="<?php echo APP_URL; ?>/patients/add.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> <span>Add New Patient</span>
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
                                        <?php if (!empty($patient['phone'])): ?>
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['phone']); ?>
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
                        <?php 
                        $baseUrl = 'list.php';
                        if (!empty($search)) {
                            $baseUrl .= '?search=' . urlencode($search);
                        }
                        echo pagination($result['page'], $result['totalPages'], $baseUrl); 
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.action-buttons {
    display: flex;
    gap: 0.375rem;
    flex-wrap: wrap;
}

@media (max-width: 575.98px) {
  .header-actions .btn span {
    display: none !important;
  }
  .page-header {
    flex-direction: row !important;
    align-items: center !important;
    justify-content: space-between !important;
    gap: 0.5rem !important;
    flex-wrap: nowrap !important;
    white-space: nowrap;
    overflow-x: auto;
  }
  .page-header > div:first-child {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 0.25rem;
    min-width: 0;
    flex: 1 1 auto;
    white-space: nowrap;
  }
  .page-header h1, .page-header .text-muted {
    display: inline-block;
    vertical-align: middle;
    margin: 0;
    white-space: nowrap;
    font-size: 1rem;
  }
  .page-header .text-muted {
    font-size: 0.8em;
    margin-left: 0.5rem;
    text-overflow: ellipsis;
    overflow: hidden;
    max-width: 60vw;
  }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>