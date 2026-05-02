<?php
/**
 * Super Admin - Doctor Details
 */

define('ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'Doctor Details';
$doctorId = intval($_GET['id'] ?? 0);

if (!$doctorId) {
    header('Location: doctors.php');
    exit;
}

$doctor = DB::queryOne("SELECT * FROM doctors WHERE id = ?", [$doctorId]);

if (!$doctor) {
    setFlash('error', 'Doctor not found.');
    header('Location: doctors.php');
    exit;
}

// Get statistics
$stats = [
    'patients' => DB::queryOne("SELECT COUNT(*) as c FROM patients WHERE doctor_id = ?", [$doctorId])['c'],
    'consultations' => DB::queryOne("SELECT COUNT(*) as c FROM consultations WHERE doctor_id = ?", [$doctorId])['c'],
    'prescriptions' => DB::queryOne("SELECT COUNT(*) as c FROM prescriptions WHERE doctor_id = ?", [$doctorId])['c'],
    'this_month' => DB::queryOne("SELECT COUNT(*) as c FROM consultations WHERE doctor_id = ? AND MONTH(consultation_date) = MONTH(CURDATE())", [$doctorId])['c']
];

// Recent patients
$recentPatients = DB::query("SELECT * FROM patients WHERE doctor_id = ? ORDER BY created_at DESC LIMIT 10", [$doctorId]);

// Recent consultations
$recentConsultations = DB::query("
    SELECT c.*, p.patient_name 
    FROM consultations c 
    JOIN patients p ON c.patient_id = p.id 
    WHERE c.doctor_id = ? 
    ORDER BY c.consultation_date DESC LIMIT 10
", [$doctorId]);

// Activity log (if exists)
$activityLogs = [];
try {
    $activityLogs = DB::query("
        SELECT * FROM doctor_activity_logs 
        WHERE doctor_id = ? 
        ORDER BY created_at DESC LIMIT 20
    ", [$doctorId]);
} catch (Exception $e) {
    // Table might not exist
}

include __DIR__ . '/includes/header.php';
?>

<style>
    .profile-header {
        background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
        border-radius: 15px;
        padding: 30px;
        color: white;
        margin-bottom: 30px;
    }
    
    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--admin-highlight), #ff6b6b);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: 600;
        border: 4px solid rgba(255,255,255,0.2);
    }
    
    .info-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        height: 100%;
    }
    
    .info-card h6 {
        font-weight: 600;
        margin-bottom: 20px;
        color: var(--admin-primary);
    }
    
    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f0f2f5;
    }
    
    .info-item:last-child {
        border-bottom: none;
    }
    
    .info-item .label {
        color: #6c757d;
    }
    
    .info-item .value {
        font-weight: 500;
    }
</style>

<!-- Back Button -->
<div class="mb-4">
    <a href="doctors.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back to Doctors
    </a>
</div>

<!-- Profile Header -->
<div class="profile-header">
    <div class="row align-items-center">
        <div class="col-auto">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($doctor['full_name'], 0, 1)); ?>
            </div>
        </div>
        <div class="col">
            <h3 class="mb-1"><?php echo htmlspecialchars($doctor['full_name']); ?></h3>
            <p class="mb-2 opacity-75">
                <?php echo htmlspecialchars($doctor['qualification'] ?? 'Homeopathic Doctor'); ?>
                <?php if ($doctor['registration_number']): ?>
                    • Reg: <?php echo htmlspecialchars($doctor['registration_number']); ?>
                <?php endif; ?>
            </p>
            <span class="status-badge status-<?php echo $doctor['status']; ?>" style="background: rgba(255,255,255,0.2);">
                <?php echo ucfirst($doctor['status']); ?>
            </span>
        </div>
        <div class="col-auto">
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">
                    Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if ($doctor['status'] !== 'active'): ?>
                        <li>
                            <form method="POST" action="doctors.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="activate">
                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                <button type="submit" class="dropdown-item text-success">
                                    <i class="bi bi-check-circle me-2"></i>Activate
                                </button>
                            </form>
                        </li>
                    <?php endif; ?>
                    
                    <?php if ($doctor['status'] !== 'suspended'): ?>
                        <li>
                            <form method="POST" action="doctors.php" onsubmit="return confirm('Suspend this doctor?')">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="suspend">
                                <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-slash-circle me-2"></i>Suspend
                                </button>
                            </form>
                        </li>
                    <?php endif; ?>
                    
                    <li>
                        <form method="POST" action="doctors.php" onsubmit="return confirm('Reset password?')">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="reset_password">
                            <input type="hidden" name="doctor_id" value="<?php echo $doctor['id']; ?>">
                            <button type="submit" class="dropdown-item">
                                <i class="bi bi-key me-2"></i>Reset Password
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div>
                    <p class="stat-label mb-1">Total Patients</p>
                    <h3 class="stat-value mb-0"><?php echo $stats['patients']; ?></h3>
                </div>
                <div class="stat-icon" style="background: rgba(55, 66, 250, 0.1); color: var(--admin-info);">
                    <i class="bi bi-people"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div>
                    <p class="stat-label mb-1">Consultations</p>
                    <h3 class="stat-value mb-0"><?php echo $stats['consultations']; ?></h3>
                </div>
                <div class="stat-icon" style="background: rgba(0, 208, 156, 0.1); color: var(--admin-success);">
                    <i class="bi bi-clipboard2-pulse"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div>
                    <p class="stat-label mb-1">Prescriptions</p>
                    <h3 class="stat-value mb-0"><?php echo $stats['prescriptions']; ?></h3>
                </div>
                <div class="stat-icon" style="background: rgba(255, 184, 0, 0.1); color: var(--admin-warning);">
                    <i class="bi bi-file-medical"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between">
                <div>
                    <p class="stat-label mb-1">This Month</p>
                    <h3 class="stat-value mb-0"><?php echo $stats['this_month']; ?></h3>
                </div>
                <div class="stat-icon" style="background: rgba(233, 69, 96, 0.1); color: var(--admin-highlight);">
                    <i class="bi bi-calendar-check"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Info Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="info-card">
            <h6><i class="bi bi-person me-2"></i>Personal Information</h6>
            <div class="info-item">
                <span class="label">Full Name</span>
                <span class="value"><?php echo htmlspecialchars($doctor['full_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Email</span>
                <span class="value"><?php echo htmlspecialchars($doctor['email']); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Phone</span>
                <span class="value"><?php echo htmlspecialchars($doctor['phone'] ?? '-'); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Address</span>
                <span class="value"><?php echo htmlspecialchars($doctor['address'] ?? '-'); ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="info-card">
            <h6><i class="bi bi-award me-2"></i>Professional Information</h6>
            <div class="info-item">
                <span class="label">Registration No.</span>
                <span class="value"><?php echo htmlspecialchars($doctor['registration_number'] ?? '-'); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Qualification</span>
                <span class="value"><?php echo htmlspecialchars($doctor['qualification'] ?? '-'); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Specialization</span>
                <span class="value"><?php echo htmlspecialchars($doctor['specialization'] ?? '-'); ?></span>
            </div>
            <div class="info-item">
                <span class="label">Member Since</span>
                <span class="value"><?php echo date('F j, Y', strtotime($doctor['created_at'])); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#patients">
            <i class="bi bi-people me-2"></i>Recent Patients
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#consultations">
            <i class="bi bi-clipboard2-pulse me-2"></i>Recent Consultations
        </button>
    </li>
    <?php if (!empty($activityLogs)): ?>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#activity">
            <i class="bi bi-journal-text me-2"></i>Activity Log
        </button>
    </li>
    <?php endif; ?>
</ul>

<div class="tab-content">
    <!-- Patients Tab -->
    <div class="tab-pane fade show active" id="patients">
        <div class="data-table">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Age/Gender</th>
                            <th>Phone</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPatients)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No patients yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentPatients as $patient): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($patient['patient_name']); ?></strong></td>
                                    <td><?php echo $patient['age'] ?? '-'; ?> / <?php echo ucfirst($patient['gender'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($patient['phone'] ?? '-'); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($patient['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Consultations Tab -->
    <div class="tab-pane fade" id="consultations">
        <div class="data-table">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Chief Complaint</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentConsultations)): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">No consultations yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentConsultations as $consultation): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($consultation['patient_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars(substr($consultation['chief_complaint'] ?? '-', 0, 50)); ?>...</td>
                                    <td>
                                        <span class="status-badge status-<?php echo $consultation['status'] === 'completed' ? 'active' : 'inactive'; ?>">
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
        </div>
    </div>
    
    <?php if (!empty($activityLogs)): ?>
    <!-- Activity Tab -->
    <div class="tab-pane fade" id="activity">
        <div class="data-table">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activityLogs as $log): ?>
                            <tr>
                                <td><span class="badge bg-secondary"><?php echo htmlspecialchars($log['action']); ?></span></td>
                                <td><?php echo htmlspecialchars($log['details'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                <td><?php echo date('M j, g:i A', strtotime($log['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
