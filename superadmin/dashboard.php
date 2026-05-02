<?php
/**
 * Super Admin Dashboard
 */

define('ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'Dashboard';

// Get statistics
$stats = [
    'doctors' => DB::queryOne("SELECT COUNT(*) as count FROM doctors")['count'] ?? 0,
    'active_doctors' => DB::queryOne("SELECT COUNT(*) as count FROM doctors WHERE status = 'active'")['count'] ?? 0,
    'patients' => DB::queryOne("SELECT COUNT(*) as count FROM patients")['count'] ?? 0,
    'consultations' => DB::queryOne("SELECT COUNT(*) as count FROM consultations")['count'] ?? 0,
    'prescriptions' => DB::queryOne("SELECT COUNT(*) as count FROM prescriptions")['count'] ?? 0,
    'today_consultations' => DB::queryOne("SELECT COUNT(*) as count FROM consultations WHERE DATE(consultation_date) = CURDATE()")['count'] ?? 0,
    'this_month_patients' => DB::queryOne("SELECT COUNT(*) as count FROM patients WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")['count'] ?? 0,
];

// Recent doctors
$recentDoctors = DB::query("SELECT * FROM doctors ORDER BY created_at DESC LIMIT 5");

// Recent consultations
$recentConsultations = DB::query("
    SELECT c.*, p.patient_name, d.full_name as doctor_name 
    FROM consultations c 
    JOIN patients p ON c.patient_id = p.id 
    JOIN doctors d ON c.doctor_id = d.id 
    ORDER BY c.created_at DESC LIMIT 5
");

// Doctor activity (last 7 days)
$doctorActivity = DB::query("
    SELECT DATE(consultation_date) as date, COUNT(*) as count 
    FROM consultations 
    WHERE consultation_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
    GROUP BY DATE(consultation_date) 
    ORDER BY date ASC
");

// Recent activity logs
$recentLogs = [];
try {
    $recentLogs = DB::query("
        SELECT al.*, sa.full_name as admin_name 
        FROM admin_activity_logs al 
        JOIN super_admins sa ON al.admin_id = sa.id 
        ORDER BY al.created_at DESC LIMIT 5
    ");
} catch (Exception $e) {
    // Table might not exist yet
}

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Dashboard</h4>
        <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name']); ?>!</p>
    </div>
    <div>
        <span class="text-muted"><i class="bi bi-calendar3 me-2"></i><?php echo date('l, F j, Y'); ?></span>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label mb-1">Total Doctors</p>
                    <h3 class="stat-value mb-0"><?php echo number_format($stats['doctors']); ?></h3>
                </div>
                <div class="stat-icon" style="background: rgba(55, 66, 250, 0.1); color: var(--admin-info);">
                    <i class="bi bi-people"></i>
                </div>
            </div>
            <div class="stat-change positive mt-3">
                <i class="bi bi-check-circle"></i>
                <span><?php echo $stats['active_doctors']; ?> active</span>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label mb-1">Total Patients</p>
                    <h3 class="stat-value mb-0"><?php echo number_format($stats['patients']); ?></h3>
                </div>
                <div class="stat-icon" style="background: rgba(0, 208, 156, 0.1); color: var(--admin-success);">
                    <i class="bi bi-person-badge"></i>
                </div>
            </div>
            <div class="stat-change positive mt-3">
                <i class="bi bi-arrow-up"></i>
                <span><?php echo $stats['this_month_patients']; ?> this month</span>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label mb-1">Consultations</p>
                    <h3 class="stat-value mb-0"><?php echo number_format($stats['consultations']); ?></h3>
                </div>
                <div class="stat-icon" style="background: rgba(255, 184, 0, 0.1); color: var(--admin-warning);">
                    <i class="bi bi-clipboard2-pulse"></i>
                </div>
            </div>
            <div class="stat-change positive mt-3">
                <i class="bi bi-calendar-check"></i>
                <span><?php echo $stats['today_consultations']; ?> today</span>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="stat-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <p class="stat-label mb-1">Prescriptions</p>
                    <h3 class="stat-value mb-0"><?php echo number_format($stats['prescriptions']); ?></h3>
                </div>
                <div class="stat-icon" style="background: rgba(233, 69, 96, 0.1); color: var(--admin-highlight);">
                    <i class="bi bi-file-medical"></i>
                </div>
            </div>
            <div class="stat-change positive mt-3">
                <i class="bi bi-graph-up"></i>
                <span>Active system</span>
            </div>
        </div>
    </div>
</div>

<!-- Charts & Tables Row -->
<div class="row g-4 mb-4">
    <!-- Activity Chart -->
    <div class="col-lg-8">
        <div class="chart-container">
            <h6><i class="bi bi-graph-up me-2"></i>Consultation Activity (Last 7 Days)</h6>
            <canvas id="activityChart" height="100"></canvas>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="col-lg-4">
        <div class="chart-container h-100">
            <h6><i class="bi bi-pie-chart me-2"></i>Doctor Status</h6>
            <canvas id="statusChart"></canvas>
            <div class="mt-3">
                <div class="d-flex justify-content-between mb-2">
                    <span><i class="bi bi-circle-fill text-success me-2"></i>Active</span>
                    <strong><?php echo $stats['active_doctors']; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span><i class="bi bi-circle-fill text-secondary me-2"></i>Inactive</span>
                    <strong><?php echo $stats['doctors'] - $stats['active_doctors']; ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Data -->
<div class="row g-4">
    <!-- Recent Doctors -->
    <div class="col-lg-6">
        <div class="data-table">
            <div class="table-header">
                <h5><i class="bi bi-people me-2"></i>Recent Doctors</h5>
                <a href="doctors.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Doctor</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentDoctors)): ?>
                            <tr><td colspan="4" class="text-center text-muted">No doctors yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentDoctors as $doctor): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="admin-avatar me-2" style="width: 35px; height: 35px; font-size: 0.8rem;">
                                                <?php echo strtoupper(substr($doctor['full_name'], 0, 1)); ?>
                                            </div>
                                            <span><?php echo htmlspecialchars($doctor['full_name']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($doctor['email']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $doctor['status']; ?>">
                                            <?php echo ucfirst($doctor['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($doctor['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Recent Consultations -->
    <div class="col-lg-6">
        <div class="data-table">
            <div class="table-header">
                <h5><i class="bi bi-clipboard2-pulse me-2"></i>Recent Consultations</h5>
                <a href="consultations.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Doctor</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentConsultations)): ?>
                            <tr><td colspan="4" class="text-center text-muted">No consultations yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentConsultations as $consultation): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($consultation['patient_name']); ?></td>
                                    <td><?php echo htmlspecialchars($consultation['doctor_name']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo $consultation['status'] === 'completed' ? 'active' : ($consultation['status'] === 'active' ? 'inactive' : 'suspended'); ?>">
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
</div>

<?php if (!empty($recentLogs)): ?>
<!-- Recent Admin Activity -->
<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="data-table">
            <div class="table-header">
                <h5><i class="bi bi-journal-text me-2"></i>Recent Admin Activity</h5>
                <a href="activity_logs.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Admin</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentLogs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['admin_name']); ?></td>
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
</div>
<?php endif; ?>

<script>
// Activity Chart
const activityCtx = document.getElementById('activityChart').getContext('2d');
const activityData = <?php echo json_encode($doctorActivity); ?>;

new Chart(activityCtx, {
    type: 'line',
    data: {
        labels: activityData.map(d => d.date),
        datasets: [{
            label: 'Consultations',
            data: activityData.map(d => d.count),
            borderColor: '#e94560',
            backgroundColor: 'rgba(233, 69, 96, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Active', 'Inactive'],
        datasets: [{
            data: [<?php echo $stats['active_doctors']; ?>, <?php echo $stats['doctors'] - $stats['active_doctors']; ?>],
            backgroundColor: ['#00d09c', '#6c757d'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        cutout: '70%'
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
