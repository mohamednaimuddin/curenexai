<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();
$patientId = $_GET['id'] ?? 0;

// Fetch patient details
$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM consultations WHERE patient_id = p.id) as consultation_count,
        (SELECT MAX(consultation_date) FROM consultations WHERE patient_id = p.id) as last_consultation
        FROM patients p
        WHERE p.id = ? AND p.doctor_id = ?";

$patient = DB::queryOne($sql, [$patientId, $doctorId]);

if (!$patient) {
    setFlash('error', 'Patient not found');
    redirect('/patients/list.php');
}

// Fetch recent consultations
$consultations = DB::query(
    "SELECT * FROM consultations 
     WHERE patient_id = ? 
     ORDER BY consultation_date DESC 
     LIMIT 5",
    [$patientId]
);

// Fetch all lab reports for this patient - verifying doctor ownership through consultation
$labReports = DB::query(
    "SELECT lr.*, c.consultation_date, c.chief_complaint 
     FROM lab_reports lr
     INNER JOIN consultations c ON lr.consultation_id = c.id
     WHERE c.patient_id = ? AND c.doctor_id = ?
     ORDER BY lr.uploaded_at DESC",
    [$patientId, $doctorId]
);

// Handle query failure gracefully
if ($labReports === false) {
    $labReports = [];
}

// Count total lab reports
$totalLabReports = count($labReports);

$pageTitle = 'Patient Details - ' . htmlspecialchars($patient['patient_name']);
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="patient-view-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <a href="<?php echo APP_URL; ?>/patients/list.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
            <h1><i class="fas fa-user"></i> <?php echo htmlspecialchars($patient['patient_name']); ?></h1>
            <p class="text-muted">Patient ID: #<?php echo $patient['id']; ?> | Registered on <?php echo formatDate($patient['created_at'], 'd M Y'); ?></p>
        </div>
        <div class="header-actions">
            <a href="edit.php?id=<?php echo $patient['id']; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="<?php echo APP_URL; ?>/consultations/add.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success">
                <i class="fas fa-plus"></i> New Consultation
            </a>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="fas fa-stethoscope"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $patient['consultation_count']; ?></h3>
                <p>Total Consultations</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $patient['last_consultation'] ? formatDate($patient['last_consultation'], 'd M Y') : 'Never'; ?></h3>
                <p>Last Visit</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <i class="fas fa-birthday-cake"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $patient['age']; ?> years</h3>
                <p>Age</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <i class="fas fa-tint"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $patient['blood_group'] ?: 'N/A'; ?></h3>
                <p>Blood Group</p>
            </div>
        </div>
    </div>
    
    <!-- Basic Information -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-id-card"></i> Basic Information</h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Full Name</label>
                    <p><strong><?php echo htmlspecialchars($patient['patient_name']); ?></strong></p>
                </div>
                
                <div class="info-item">
                    <label>Age / Gender</label>
                    <p><?php echo $patient['age']; ?> years / <?php echo ucfirst($patient['gender']); ?></p>
                </div>
                
                <div class="info-item">
                    <label>Phone Number</label>
                    <p>
                        <i class="fas fa-phone"></i> 
                        <a href="tel:<?php echo $patient['phone']; ?>"><?php echo htmlspecialchars($patient['phone']); ?></a>
                    </p>
                </div>
                
                <?php if ($patient['email']): ?>
                <div class="info-item">
                    <label>Email Address</label>
                    <p>
                        <i class="fas fa-envelope"></i> 
                        <a href="mailto:<?php echo $patient['email']; ?>"><?php echo htmlspecialchars($patient['email']); ?></a>
                    </p>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['blood_group']): ?>
                <div class="info-item">
                    <label>Blood Group</label>
                    <p><span class="badge badge-danger"><?php echo htmlspecialchars($patient['blood_group']); ?></span></p>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['occupation']): ?>
                <div class="info-item">
                    <label>Occupation</label>
                    <p><?php echo htmlspecialchars($patient['occupation']); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['marital_status']): ?>
                <div class="info-item">
                    <label>Marital Status</label>
                    <p><?php echo ucfirst($patient['marital_status']); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['address']): ?>
                <div class="info-item full-width">
                    <label>Address</label>
                    <p><?php echo nl2br(htmlspecialchars($patient['address'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Emergency Contact -->
    <?php if ($patient['emergency_contact'] || $patient['emergency_phone']): ?>
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Emergency Contact</h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <?php if ($patient['emergency_contact']): ?>
                <div class="info-item">
                    <label>Contact Person</label>
                    <p><strong><?php echo htmlspecialchars($patient['emergency_contact']); ?></strong></p>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['emergency_phone']): ?>
                <div class="info-item">
                    <label>Phone Number</label>
                    <p>
                        <i class="fas fa-phone"></i> 
                        <a href="tel:<?php echo $patient['emergency_phone']; ?>"><?php echo htmlspecialchars($patient['emergency_phone']); ?></a>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Medical History -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-notes-medical"></i> Medical History</h3>
        </div>
        <div class="card-body">
            <?php if ($patient['medical_history'] || $patient['surgical_history'] || $patient['family_history'] || $patient['allergies'] || $patient['current_medications']): ?>
                <?php if ($patient['medical_history']): ?>
                <div class="history-section">
                    <h4>Past Medical History</h4>
                    <p><?php echo nl2br(htmlspecialchars($patient['medical_history'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['surgical_history']): ?>
                <div class="history-section">
                    <h4>Surgical History</h4>
                    <p><?php echo nl2br(htmlspecialchars($patient['surgical_history'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['family_history']): ?>
                <div class="history-section">
                    <h4>Family History</h4>
                    <p><?php echo nl2br(htmlspecialchars($patient['family_history'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['allergies']): ?>
                <div class="history-section">
                    <h4>Allergies</h4>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo nl2br(htmlspecialchars($patient['allergies'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['current_medications']): ?>
                <div class="history-section">
                    <h4>Current Medications</h4>
                    <p><?php echo nl2br(htmlspecialchars($patient['current_medications'])); ?></p>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-notes-medical"></i>
                    <p>No medical history recorded</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Lifestyle -->
    <?php if ($patient['diet'] || $patient['exercise'] || $patient['sleep_pattern'] || $patient['addictions']): ?>
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-heartbeat"></i> Lifestyle & Habits</h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <?php if ($patient['diet']): ?>
                <div class="info-item">
                    <label>Diet</label>
                    <p><span class="badge badge-info"><?php echo ucfirst($patient['diet']); ?></span></p>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['exercise']): ?>
                <div class="info-item">
                    <label>Exercise</label>
                    <p><span class="badge badge-success"><?php echo ucfirst($patient['exercise']); ?></span></p>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['sleep_pattern']): ?>
                <div class="info-item">
                    <label>Sleep Pattern</label>
                    <p><span class="badge badge-primary"><?php echo ucfirst($patient['sleep_pattern']); ?></span></p>
                </div>
                <?php endif; ?>
                
                <?php if ($patient['addictions']): ?>
                <div class="info-item full-width">
                    <label>Addictions / Habits</label>
                    <p><?php echo nl2br(htmlspecialchars($patient['addictions'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Recent Consultations -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Consultations</h3>
            <a href="<?php echo APP_URL; ?>/consultations/add.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-success">
                <i class="fas fa-plus"></i> New Consultation
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($consultations)): ?>
                <div class="empty-state">
                    <i class="fas fa-stethoscope"></i>
                    <p>No consultations yet</p>
                    <a href="<?php echo APP_URL; ?>/consultations/add.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success">
                        <i class="fas fa-plus"></i> Create First Consultation
                    </a>
                </div>
            <?php else: ?>
                <div class="consultations-list">
                    <?php foreach ($consultations as $consultation): ?>
                    <div class="consultation-item">
                        <div class="consultation-header">
                            <div>
                                <strong><?php echo formatDate($consultation['consultation_date'], 'd M Y'); ?></strong>
                                <span class="text-muted"><?php echo date('h:i A', strtotime($consultation['consultation_date'])); ?></span>
                            </div>
                            <span class="badge badge-<?php echo $consultation['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $consultation['status'])); ?>
                            </span>
                        </div>
                        <div class="consultation-content">
                            <p><strong>Chief Complaint:</strong> <?php echo truncate(htmlspecialchars($consultation['chief_complaint']), 100); ?></p>
                            <?php if ($consultation['diagnosis']): ?>
                                <p><strong>Diagnosis:</strong> <?php echo truncate(htmlspecialchars($consultation['diagnosis']), 80); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="consultation-actions">
                            <a href="<?php echo APP_URL; ?>/consultations/view.php?id=<?php echo $consultation['id']; ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($patient['consultation_count'] > 5): ?>
                <div class="text-center" style="margin-top: 20px;">
                    <a href="<?php echo APP_URL; ?>/consultations/list.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-outline">
                        View All Consultations (<?php echo $patient['consultation_count']; ?>)
                    </a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Lab Reports -->
    <?php if (!empty($labReports)): ?>
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-file-medical"></i> Lab Reports (<?php echo $totalLabReports; ?>)</h3>
            <a href="<?php echo APP_URL; ?>/lab.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-success">
                <i class="fas fa-upload"></i> Upload New Report
            </a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Report Name</th>
                            <th>Type</th>
                            <th>Consultation</th>
                            <th>Upload Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($labReports as $report): ?>
                        <tr>
                            <td>
                                <i class="fas fa-<?php echo ($report['file_type'] === 'pdf') ? 'file-pdf' : 'file-image'; ?>" 
                                   style="color: <?php echo ($report['file_type'] === 'pdf') ? '#dc2626' : '#2563eb'; ?>; margin-right: 8px;">
                                </i>
                                <?php echo htmlspecialchars($report['report_name']); ?>
                            </td>
                            <td><span class="badge badge-secondary"><?php echo htmlspecialchars($report['report_type']); ?></span></td>
                            <td>
                                <a href="<?php echo APP_URL; ?>/consultations/view.php?id=<?php echo $report['consultation_id']; ?>" class="text-link">
                                    <?php echo formatDate($report['consultation_date'], 'd M Y'); ?>
                                </a>
                            </td>
                            <td><?php echo formatDate($report['created_at'], 'd M Y'); ?></td>
                            <td>
                                <?php if ($report['file_path']): ?>
                                    <button type="button" class="btn btn-sm btn-primary" 
                                            onclick="viewLabReport('<?php echo htmlspecialchars($report['file_path']); ?>', '<?php echo htmlspecialchars($report['report_name']); ?>', '<?php echo $report['file_type']; ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Empty Lab Reports Section -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-file-medical"></i> Lab Reports</h3>
            <a href="<?php echo APP_URL; ?>/lab.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-sm btn-success">
                <i class="fas fa-upload"></i> Upload Report
            </a>
        </div>
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-file-medical-alt"></i>
                <p>No lab reports uploaded yet</p>
                <a href="<?php echo APP_URL; ?>/lab.php?patient_id=<?php echo $patient['id']; ?>" class="btn btn-success">
                    <i class="fas fa-upload"></i> Upload First Lab Report
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Lab Report Viewer Modal -->
<div class="modal" id="labReportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-medical"></i> <span id="reportTitle">Lab Report</span></h5>
                <button type="button" class="modal-close" onclick="closeLabModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="reportPreview" style="min-height: 400px;">
                    <!-- Preview will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <a href="#" id="downloadReportBtn" class="btn btn-primary" target="_blank">
                    <i class="fas fa-download"></i> Download
                </a>
                <button type="button" class="btn btn-secondary" onclick="closeLabModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Modal styles for lab report viewer */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9999;
    justify-content: center;
    align-items: center;
    padding: 20px;
}
.modal.show {
    display: flex;
}
.modal-dialog {
    width: 100%;
    max-width: 900px;
    max-height: 90vh;
}
.modal-content {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    max-height: 90vh;
}
.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}
.modal-title {
    margin: 0;
    font-size: 1.1rem;
    color: #1f2937;
}
.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
    line-height: 1;
}
.modal-close:hover {
    color: #1f2937;
}
.modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}
.modal-footer {
    padding: 15px 20px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
#reportPreview img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
}
#reportPreview iframe {
    width: 100%;
    height: 500px;
    border: none;
    border-radius: 8px;
}
</style>

<script>
function viewLabReport(filePath, reportName, fileType) {
    const modal = document.getElementById('labReportModal');
    const preview = document.getElementById('reportPreview');
    const title = document.getElementById('reportTitle');
    const downloadBtn = document.getElementById('downloadReportBtn');
    
    title.textContent = reportName;
    downloadBtn.href = '<?php echo APP_URL; ?>/' + filePath;
    
    if (fileType === 'pdf') {
        preview.innerHTML = '<iframe src="<?php echo APP_URL; ?>/' + filePath + '" title="Lab Report"></iframe>';
    } else {
        preview.innerHTML = '<img src="<?php echo APP_URL; ?>/' + filePath + '" alt="Lab Report" style="max-width:100%;">';
    }
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeLabModal() {
    const modal = document.getElementById('labReportModal');
    modal.classList.remove('show');
    document.body.style.overflow = '';
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLabModal();
});

// Close modal on overlay click
document.getElementById('labReportModal').addEventListener('click', function(e) {
    if (e.target === this) closeLabModal();
});
</script>

<style>
.patient-view-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px;
    box-sizing: border-box;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--white);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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

.stat-content h3 {
    margin: 0;
    font-size: 1.8rem;
    color: var(--gray-800);
}

.stat-content p {
    margin: 5px 0 0;
    color: var(--gray-600);
    font-size: 0.9rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.info-item {
    padding: 10px 0;
}

.info-item.full-width {
    grid-column: 1 / -1;
}

.info-item label {
    display: block;
    color: var(--gray-600);
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 5px;
}

.info-item p {
    margin: 0;
    color: var(--gray-800);
}

.info-item a {
    color: var(--primary-color);
    text-decoration: none;
}

.info-item a:hover {
    text-decoration: underline;
}

.history-section {
    padding: 15px 0;
    border-bottom: 1px solid var(--gray-200);
}

.history-section:last-child {
    border-bottom: none;
}

.history-section h4 {
    color: var(--primary-color);
    margin-bottom: 10px;
    font-size: 1.1rem;
}

.history-section p {
    color: var(--gray-700);
    line-height: 1.6;
}

.consultations-list {
    display: grid;
    gap: 15px;
}

.consultation-item {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 15px;
    border-left: 4px solid var(--primary-color);
}

.consultation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.consultation-content {
    margin-bottom: 10px;
}

.consultation-content p {
    margin: 5px 0;
    color: var(--gray-700);
    font-size: 0.95rem;
}

.consultation-actions {
    display: flex;
    gap: 10px;
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

.badge-success {
    background: #43e97b;
    color: #fff;
    border-radius: 6px;
    padding: 2px 8px;
    font-size: 0.85em;
}

.badge-secondary {
    background: #a0aec0;
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

.table-responsive {
    overflow-x: auto;
}

@media (max-width: 900px) {
    .patient-view-container {
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
    .patient-view-container {
        padding: 4px;
        min-width: 0;
    }
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .dashboard-card {
        padding: 10px 2px;
    }
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
    .info-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    .data-table th, .data-table td {
        padding: 4px 1px;
        font-size: 0.92em;
    }
    .consultations-list {
        gap: 8px;
    }
}

@media (max-width: 400px) {
    .patient-view-container {
        padding: 2px;
    }
    .dashboard-card {
        padding: 6px 1px;
    }
    .info-item {
        padding: 6px 0;
    }
    .data-table th, .data-table td {
        padding: 2px 0;
        font-size: 0.9em;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
