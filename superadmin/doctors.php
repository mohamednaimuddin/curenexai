<?php
/**
 * Super Admin - Doctor Management
 */

define('ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'Manage Doctors';
generateCsrfToken();

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $doctorId = intval($_POST['doctor_id'] ?? 0);
    
    if ($doctorId > 0) {
        switch ($action) {
            case 'activate':
                DB::update('doctors', ['status' => 'active'], 'id = ?', [$doctorId]);
                logAdminActivity($_SESSION['admin_id'], 'activate_doctor', "Activated doctor ID: $doctorId", 'doctor', $doctorId);
                $success = 'Doctor activated successfully.';
                break;
                
            case 'deactivate':
                DB::update('doctors', ['status' => 'inactive'], 'id = ?', [$doctorId]);
                logAdminActivity($_SESSION['admin_id'], 'deactivate_doctor', "Deactivated doctor ID: $doctorId", 'doctor', $doctorId);
                $success = 'Doctor deactivated successfully.';
                break;
                
            case 'suspend':
                DB::update('doctors', ['status' => 'suspended'], 'id = ?', [$doctorId]);
                logAdminActivity($_SESSION['admin_id'], 'suspend_doctor', "Suspended doctor ID: $doctorId", 'doctor', $doctorId);
                $success = 'Doctor suspended successfully.';
                break;
                
            case 'delete':
                // Get doctor info first
                $doctor = DB::queryOne("SELECT full_name, email FROM doctors WHERE id = ?", [$doctorId]);
                if ($doctor) {
                    DB::delete('doctors', 'id = ?', [$doctorId]);
                    logAdminActivity($_SESSION['admin_id'], 'delete_doctor', "Deleted doctor: {$doctor['full_name']} ({$doctor['email']})", 'doctor', $doctorId);
                    $success = 'Doctor deleted successfully.';
                }
                break;
                
            case 'reset_password':
                $newPassword = bin2hex(random_bytes(4)); // 8 char random password
                $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                DB::update('doctors', ['password' => $hashedPassword], 'id = ?', [$doctorId]);
                logAdminActivity($_SESSION['admin_id'], 'reset_password', "Reset password for doctor ID: $doctorId", 'doctor', $doctorId);
                $success = "Password reset successfully. New password: <strong>$newPassword</strong> (Please share this securely)";
                break;
        }
    }
}

// Filters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = [];
$params = [];

if ($statusFilter) {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $whereConditions[] = "(full_name LIKE ? OR email LIKE ? OR registration_number LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$totalCount = DB::queryOne("SELECT COUNT(*) as count FROM doctors $whereClause", $params)['count'];
$totalPages = ceil($totalCount / $perPage);

// Get doctors - Security: Cast pagination to integers to prevent SQL injection
$safePerPage = (int)$perPage;
$safeOffset = (int)$offset;
$doctors = DB::query("
    SELECT d.*, 
           (SELECT COUNT(*) FROM patients WHERE doctor_id = d.id) as patient_count,
           (SELECT COUNT(*) FROM consultations WHERE doctor_id = d.id) as consultation_count
    FROM doctors d 
    $whereClause 
    ORDER BY d.created_at DESC 
    LIMIT $safePerPage OFFSET $safeOffset
", $params);

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Doctor Management</h4>
        <p class="text-muted mb-0">Manage and monitor all registered doctors</p>
    </div>
    <div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDoctorModal">
            <i class="bi bi-plus-lg me-2"></i>Add Doctor
        </button>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filters -->
<div class="data-table mb-4">
    <div class="p-3">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" name="search" placeholder="Search doctors..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="suspended" <?php echo $statusFilter === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
            </div>
            <div class="col-md-2">
                <a href="doctors.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>
</div>

<!-- Doctors Table -->
<div class="data-table">
    <div class="table-header">
        <h5><i class="bi bi-people me-2"></i>Doctors (<?php echo $totalCount; ?>)</h5>
        <div>
            <span class="badge bg-success me-2"><?php echo DB::queryOne("SELECT COUNT(*) as c FROM doctors WHERE status = 'active'")['c']; ?> Active</span>
            <span class="badge bg-secondary me-2"><?php echo DB::queryOne("SELECT COUNT(*) as c FROM doctors WHERE status = 'inactive'")['c']; ?> Inactive</span>
            <span class="badge bg-danger"><?php echo DB::queryOne("SELECT COUNT(*) as c FROM doctors WHERE status = 'suspended'")['c']; ?> Suspended</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Doctor</th>
                    <th>Contact</th>
                    <th>Registration</th>
                    <th>Patients</th>
                    <th>Consultations</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($doctors)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No doctors found</td></tr>
                <?php else: ?>
                    <?php foreach ($doctors as $doctor): ?>
                        <tr>
                            <td><strong>#<?php echo $doctor['id']; ?></strong></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="admin-avatar me-2" style="width: 40px; height: 40px; font-size: 0.9rem;">
                                        <?php echo strtoupper(substr($doctor['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($doctor['full_name']); ?></strong>
                                        <?php if ($doctor['qualification']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($doctor['qualification']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars($doctor['email']); ?></small>
                                <?php if ($doctor['phone']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($doctor['phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($doctor['registration_number'] ?? '-'); ?></td>
                            <td><span class="badge bg-info"><?php echo $doctor['patient_count']; ?></span></td>
                            <td><span class="badge bg-secondary"><?php echo $doctor['consultation_count']; ?></span></td>
                            <td>
                                <span class="status-badge status-<?php echo $doctor['status']; ?>">
                                    <?php echo ucfirst($doctor['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($doctor['created_at'])); ?></td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        Actions
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="doctor_details.php?id=<?php echo $doctor['id']; ?>">
                                                <i class="bi bi-eye me-2"></i>View Details
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        
                                        <?php if ($doctor['status'] !== 'active'): ?>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                                    <button type="submit" class="dropdown-item text-success">
                                                        <i class="bi bi-check-circle me-2"></i>Activate
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($doctor['status'] !== 'inactive'): ?>
                                            <li>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="deactivate">
                                                    <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                                    <button type="submit" class="dropdown-item text-warning">
                                                        <i class="bi bi-pause-circle me-2"></i>Deactivate
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php if ($doctor['status'] !== 'suspended'): ?>
                                            <li>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Suspend this doctor?')">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="suspend">
                                                    <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bi bi-slash-circle me-2"></i>Suspend
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <li><hr class="dropdown-divider"></li>
                                        
                                        <li>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Reset password for this doctor?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="reset_password">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bi bi-key me-2"></i>Reset Password
                                                </button>
                                            </form>
                                        </li>
                                        
                                        <li><hr class="dropdown-divider"></li>
                                        
                                        <li>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('DELETE this doctor and ALL their data? This cannot be undone!')">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bi bi-trash me-2"></i>Delete
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
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
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($searchQuery); ?>">Previous</a>
                        </li>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($searchQuery); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($searchQuery); ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<!-- Add Doctor Modal -->
<div class="modal fade" id="addDoctorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New Doctor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="add_doctor.php">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" minlength="8" required>
                        <small class="text-muted">Minimum 8 characters</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Registration Number</label>
                        <input type="text" class="form-control" name="registration_number">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Qualification</label>
                        <input type="text" class="form-control" name="qualification" placeholder="e.g., BHMS, MD">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Doctor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
