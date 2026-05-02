<?php
require_once 'includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();

// Fetch statistics
$stats = [];

// Total patients
$stats['total_patients'] = DB::queryOne(
    "SELECT COUNT(*) as count FROM patients WHERE doctor_id = ?", 
    [$doctorId]
)['count'];

// Total consultations
$stats['total_consultations'] = DB::queryOne(
    "SELECT COUNT(*) as count FROM consultations WHERE doctor_id = ?", 
    [$doctorId]
)['count'];

// Active consultations (this month)
$stats['active_consultations'] = DB::queryOne(
    "SELECT COUNT(*) as count FROM consultations 
    WHERE doctor_id = ? AND MONTH(consultation_date) = MONTH(CURRENT_DATE()) 
    AND YEAR(consultation_date) = YEAR(CURRENT_DATE())", 
    [$doctorId]
)['count'];

// Total prescriptions
$stats['total_prescriptions'] = DB::queryOne(
    "SELECT COUNT(*) as count FROM prescriptions WHERE doctor_id = ?", 
    [$doctorId]
)['count'];

// Recent patients (last 5)
$recentPatients = DB::query(
    "SELECT p.*, MAX(c.consultation_date) as last_visit 
    FROM patients p 
    LEFT JOIN consultations c ON p.id = c.patient_id 
    WHERE p.doctor_id = ? 
    GROUP BY p.id 
    ORDER BY last_visit DESC 
    LIMIT 5",
    [$doctorId]
);

// Upcoming follow-ups
$upcomingFollowups = DB::query(
    "SELECT c.*, p.patient_name, p.age, p.gender 
    FROM consultations c 
    INNER JOIN patients p ON c.patient_id = p.id 
    WHERE c.doctor_id = ? AND c.follow_up_date >= CURDATE() 
    ORDER BY c.follow_up_date ASC 
    LIMIT 5",
    [$doctorId]
);

// Recent consultations
$recentConsultations = DB::query(
    "SELECT c.*, p.patient_name, p.age, p.gender 
    FROM consultations c 
    INNER JOIN patients p ON c.patient_id = p.id 
    WHERE c.doctor_id = ? 
    ORDER BY c.consultation_date DESC 
    LIMIT 5",
    [$doctorId]
);

$pageTitle = 'Dashboard';
?>
<?php require_once 'includes/header.php'; ?>

<style>
    /* Dashboard Background */
    .dashboard-container {
        position: relative;
    }
    
    .dashboard-container::before {
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
        .dashboard-container::before {
            left: 0;
            top: 60px;
        }
    }
</style>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1>
                <i class="fas fa-home"></i> Dashboard
            </h1>
            <p class="text-muted">Welcome back, Dr. <?php echo htmlspecialchars(getLoggedInDoctor()['full_name']); ?></p>
        </div>
        <div class="header-actions">
            <a href="<?php echo APP_URL; ?>/consultations/add.php" class="btn btn-primary">
                <i class="fas fa-stethoscope"></i> <span class="hide-mobile">New Consultation</span>
            </a>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $stats['total_patients']; ?></h3>
                <p>Total Patients</p>
            </div>
        </div>
        
        <div class="stat-card stat-success">
            <div class="stat-icon">
                <i class="fas fa-stethoscope"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $stats['active_consultations']; ?></h3>
                <p>This Month</p>
            </div>
        </div>
        
        <div class="stat-card stat-info">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo count($upcomingFollowups); ?></h3>
                <p>Follow-ups Due</p>
            </div>
        </div>
        
        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <i class="fas fa-prescription"></i>
            </div>
            <div class="stat-details">
                <h3><?php echo $stats['total_prescriptions']; ?></h3>
                <p>Prescriptions</p>
            </div>
        </div>
    </div>
    
    <!-- Main Content Grid -->
    <div class="dashboard-grid">
        <!-- Recent Patients -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Recent Patients</h3>
                <a href="<?php echo APP_URL; ?>/patients/list.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recentPatients)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No patients yet</p>
                        <a href="<?php echo APP_URL; ?>/patients/add.php" class="btn btn-primary btn-sm">Add First Patient</a>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Age/Gender</th>
                                <th>Last Visit</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPatients as $patient): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($patient['patient_name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo $patient['age']; ?> / 
                                        <span class="badge badge-<?php echo $patient['gender'] == 'male' ? 'primary' : 'danger'; ?>">
                                            <?php echo ucfirst($patient['gender']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $patient['last_visit'] ? formatDate($patient['last_visit']) : 'Never'; ?></td>
                                    <td>
                                        <a href="<?php echo APP_URL; ?>/patients/view.php?id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Upcoming Follow-ups -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-check"></i> Upcoming Follow-ups</h3>
                <a href="<?php echo APP_URL; ?>/consultations/followups.php" class="btn btn-sm btn-outline">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($upcomingFollowups)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <p>No upcoming follow-ups</p>
                    </div>
                <?php else: ?>
                    <div class="followup-list">
                        <?php foreach ($upcomingFollowups as $followup): ?>
                            <div class="followup-item">
                                <div class="followup-date">
                                    <span class="day"><?php echo date('d', strtotime($followup['follow_up_date'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($followup['follow_up_date'])); ?></span>
                                </div>
                                <div class="followup-details">
                                    <strong><?php echo htmlspecialchars($followup['patient_name']); ?></strong>
                                    <p class="text-muted"><?php echo truncate($followup['chief_complaint'], 50); ?></p>
                                </div>
                                <div class="followup-action">
                                    <a href="<?php echo APP_URL; ?>/consultations/view.php?id=<?php echo $followup['id']; ?>" class="btn btn-sm btn-primary">
                                        View
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="actions-grid">
            <a href="<?php echo APP_URL; ?>/patients/add.php" class="action-card">
                <i class="fas fa-user-plus"></i>
                <span>Add New Patient</span>
            </a>
            
            <a href="<?php echo APP_URL; ?>/consultations/add.php" class="action-card">
                <i class="fas fa-stethoscope"></i>
                <span>New Consultation</span>
            </a>
            
            <a href="<?php echo APP_URL; ?>/repertory/search.php" class="action-card">
                <i class="fas fa-book"></i>
                <span>Search Repertory</span>
            </a>
            
            <a href="<?php echo APP_URL; ?>/materia-medica/list.php" class="action-card">
                <i class="fas fa-flask"></i>
                <span>Materia Medica</span>
            </a>
            
            <?php if (AI_ENABLED): ?>
            <a href="<?php echo APP_URL; ?>/consultations/list.php?highlight=ai" class="action-card">
                <i class="fas fa-brain"></i>
                <span>AI Suggestions</span>
            </a>
            <?php endif; ?>
            
            <a href="<?php echo APP_URL; ?>/prescriptions/list.php" class="action-card">
                <i class="fas fa-prescription"></i>
                <span>Prescriptions</span>
            </a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
