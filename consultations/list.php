<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();
$page = $_GET['page'] ?? 1;
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

// Build query
$where = "c.doctor_id = ?";
$params = [$doctorId];

if (!empty($search)) {
    $where .= " AND (p.patient_name LIKE ? OR c.chief_complaint LIKE ? OR c.diagnosis LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($status)) {
    $where .= " AND c.status = ?";
    $params[] = $status;
}

// Fetch consultations with patient info
$sql = "SELECT c.*, p.patient_name, p.age, p.gender,
        (SELECT COUNT(*) FROM symptoms WHERE consultation_id = c.id) as symptom_count
        FROM consultations c
        INNER JOIN patients p ON c.patient_id = p.id
        WHERE {$where}
        ORDER BY c.consultation_date DESC
        LIMIT " . CONSULTATIONS_PER_PAGE . " OFFSET " . (($page - 1) * CONSULTATIONS_PER_PAGE);

$consultations = DB::query($sql, $params);

// Get total count
$countSql = "SELECT COUNT(*) as total FROM consultations c
             INNER JOIN patients p ON c.patient_id = p.id
             WHERE {$where}";
$totalResult = DB::queryOne($countSql, $params);
$total = $totalResult['total'] ?? 0;
$totalPages = ceil($total / CONSULTATIONS_PER_PAGE);

$pageTitle = 'Consultations';
$showAIMessage = isset($_GET['highlight']) && $_GET['highlight'] === 'ai';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
    .consultations-container { position: relative; }
    .consultations-container::before {
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
        .consultations-container::before { left: 0; top: 60px; }
    }
</style>

<div class="consultations-container">
    <?php if ($showAIMessage): ?>
    <div class="alert alert-info" style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; display: flex; align-items: center; gap: 15px; animation: pulse 2s ease-in-out 3;">
        <i class="fas fa-robot" style="font-size: 2em;"></i>
        <div>
            <h4 style="margin: 0 0 5px 0;">� Get AI-Powered Remedy Suggestions with Gemini</h4>
            <p style="margin: 0; font-size: 16px;"><strong>→ Click the purple BRAIN icon (🧠) in the Actions column</strong> next to any consultation below to analyze symptoms and generate AI recommendations!</p>
        </div>
    </div>
    <style>
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); box-shadow: 0 0 20px rgba(102, 126, 234, 0.6); }
        }
    </style>
    <?php endif; ?>
    
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-stethoscope"></i> Consultations</h1>
            <p class="text-muted">Manage patient consultations and case records</p>
        </div>
        <div class="header-actions">
            <a href="<?php echo APP_URL; ?>/consultations/add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> New Consultation
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
                        placeholder="Search by patient name, complaint, or diagnosis..." 
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                    
                    <select name="status" class="form-control" style="max-width: 200px;">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="completed" <?php echo $status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="follow_up" <?php echo $status == 'follow_up' ? 'selected' : ''; ?>>Follow-up</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">Search</button>
                    <?php if (!empty($search) || !empty($status)): ?>
                        <a href="list.php" class="btn btn-outline">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Consultations List -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> All Consultations (<?php echo $total; ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (empty($consultations)): ?>
                <div class="empty-state">
                    <i class="fas fa-stethoscope"></i>
                    <p>No consultations found</p>
                    <?php if (empty($search)): ?>
                        <a href="<?php echo APP_URL; ?>/consultations/add.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create First Consultation
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Chief Complaint</th>
                                <th>Symptoms</th>
                                <th>Status</th>
                                <th>Follow-up</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consultations as $consultation): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo formatDate($consultation['consultation_date'], 'd M Y'); ?></strong><br>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($consultation['consultation_date'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($consultation['patient_name']); ?></strong><br>
                                        <small class="text-muted">
                                            <?php echo $consultation['age']; ?> years / <?php echo ucfirst($consultation['gender']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php echo truncate(htmlspecialchars($consultation['chief_complaint']), 60); ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo $consultation['symptom_count']; ?> symptoms</span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'active' => 'success',
                                            'completed' => 'secondary',
                                            'follow_up' => 'warning'
                                        ];
                                        $color = $statusColors[$consultation['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?php echo $color; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $consultation['status'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($consultation['follow_up_date']): ?>
                                            <?php
                                            $followUpDate = strtotime($consultation['follow_up_date']);
                                            $today = strtotime('today');
                                            $isPast = $followUpDate < $today;
                                            $isToday = date('Y-m-d', $followUpDate) == date('Y-m-d', $today);
                                            ?>
                                            <span class="badge badge-<?php echo $isPast ? 'danger' : ($isToday ? 'warning' : 'info'); ?>">
                                                <?php echo formatDate($consultation['follow_up_date'], 'd M'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view.php?id=<?php echo $consultation['id']; ?>" class="btn btn-sm btn-info" title="View Consultation">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="../lab.php?patient_id=<?php echo $consultation['patient_id']; ?>" class="btn btn-sm btn-success" title="Lab Report">
                                                <i class="fas fa-flask"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $consultation['id']; ?>" class="btn btn-sm btn-warning" title="Edit Consultation">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../prescriptions/add.php?consultation_id=<?php echo $consultation['id']; ?>" class="btn btn-sm btn-primary" title="Write Prescription">
                                                <i class="fas fa-prescription"></i>
                                            </a>
                                            <button type="button"
                                               class="btn btn-sm btn-primary ai-brain-btn" 
                                               title="🧠 Generate AI Remedy Suggestions"
                                               onclick="toggleAISuggestions(<?php echo $consultation['id']; ?>)"
                                               data-consultation-id="<?php echo $consultation['id']; ?>"
                                               style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; font-size: 1.1em; padding: 8px 12px; box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4); transition: all 0.3s ease; cursor: pointer;">
                                                <i class="fas fa-brain"></i> <strong>AI</strong>
                                            </button>
                                            <button type="button"
                                               class="btn btn-sm diagnose-btn" 
                                               title="🩺 Get Disease Diagnosis (RAG)"
                                               onclick="toggleDiagnosis(<?php echo $consultation['id']; ?>)"
                                               data-consultation-id="<?php echo $consultation['id']; ?>"
                                               style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); border: none; color: white; font-size: 1.1em; padding: 8px 12px; box-shadow: 0 4px 8px rgba(14, 165, 233, 0.4); transition: all 0.3s ease; cursor: pointer;">
                                                <i class="fas fa-diagnoses"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <!-- AI Suggestions Expandable Row -->
                                <tr id="ai-row-<?php echo $consultation['id']; ?>" class="ai-suggestion-row" style="display: none;">
                                    <td colspan="7" style="background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); padding: 0;">
                                        <div id="ai-content-<?php echo $consultation['id']; ?>" class="ai-suggestion-content" style="padding: 20px;">
                                            <!-- AI suggestions will be loaded here -->
                                        </div>
                                    </td>
                                </tr>
                                <!-- Disease Diagnosis Expandable Row (RAG) -->
                                <tr id="diagnosis-row-<?php echo $consultation['id']; ?>" class="diagnosis-row" style="display: none;">
                                    <td colspan="7" style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); padding: 0;">
                                        <div id="diagnosis-content-<?php echo $consultation['id']; ?>" class="diagnosis-content" style="padding: 20px;">
                                            <!-- Diagnosis will be loaded here -->
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container">
                        <?php
                        $baseUrl = 'list.php';
                        $queryParams = [];
                        if (!empty($search)) $queryParams[] = 'search=' . urlencode($search);
                        if (!empty($status)) $queryParams[] = 'status=' . urlencode($status);
                        if (!empty($queryParams)) $baseUrl .= '?' . implode('&', $queryParams);
                        echo pagination($page, $totalPages, $baseUrl);
                        ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.consultations-container {
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
    .consultations-container {
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
    .consultations-container {
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
}

@media (max-width: 400px) {
    .consultations-container {
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

/* AI Brain Button Enhancement */
.ai-brain-btn:hover {
    transform: scale(1.15) !important;
    box-shadow: 0 6px 16px rgba(102, 126, 234, 0.6) !important;
    filter: brightness(1.1);
}

.ai-brain-btn:active {
    transform: scale(0.95) !important;
}

/* AI Suggestion Row Styles */
.ai-suggestion-row td {
    padding: 0 !important;
    border: none !important;
}

.ai-suggestion-content {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.ai-loading {
    text-align: center;
    padding: 40px;
    color: #667eea;
}

.ai-loading i {
    font-size: 3em;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.remedy-card {
    background: white;
    border-radius: 10px;
    padding: 15px;
    margin: 10px 0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
}

.remedy-card:hover {
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
    transform: translateY(-2px);
}

.remedy-header {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.remedy-rank {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 18px;
    margin-right: 15px;
}

.remedy-name {
    font-size: 18px;
    font-weight: bold;
    color: #333;
    flex: 1;
}

.match-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    margin-left: 10px;
}

.match-high {
    background: #28a745;
    color: white;
}

.match-medium {
    background: #ffc107;
    color: #333;
}

.match-low {
    background: #6c757d;
    color: white;
}

.ai-close-btn {
    background: #dc3545;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
    cursor: pointer;
    margin-top: 15px;
    transition: all 0.3s ease;
}

.ai-close-btn:hover {
    background: #c82333;
    transform: scale(1.05);
}

/* Dual AI Column Styles */
.dual-ai-container {
    max-width: 100%;
    padding: 20px;
}

.patient-info-header {
    background: white;
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 5px solid #667eea;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.patient-info-header i {
    color: #667eea;
    margin-right: 10px;
}

.ai-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.ai-column {
    background: white;
    border-radius: 12px;
    padding: 0;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.column-header {
    padding: 15px 20px;
    font-size: 1.1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.column-header i {
    font-size: 1.3rem;
}

.column-badge {
    margin-left: auto;
    font-size: 0.75rem;
    padding: 4px 10px;
    border-radius: 12px;
    font-weight: 500;
}

.rag-header {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

.rag-header .column-badge {
    background: rgba(255,255,255,0.25);
    color: white;
}

.gemini-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.gemini-header .column-badge {
    background: rgba(255,255,255,0.25);
    color: white;
}

.ai-column .remedy-card {
    margin: 15px;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
}

.rag-card {
    background: linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%);
    border-left: 4px solid #38a169;
}

.rag-card:hover {
    box-shadow: 0 4px 16px rgba(56, 161, 105, 0.2);
    transform: translateY(-2px);
}

.gemini-card {
    background: linear-gradient(135deg, #f5f3ff 0%, #e9d5ff 100%);
    border-left: 4px solid #7c3aed;
}

.gemini-card:hover {
    box-shadow: 0 4px 16px rgba(124, 58, 237, 0.2);
    transform: translateY(-2px);
}

.ai-column .remedy-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.ai-column .remedy-rank {
    width: 32px;
    height: 32px;
    font-size: 14px;
    flex-shrink: 0;
}

.rag-column .remedy-rank {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.gemini-column .remedy-rank {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.remedy-info {
    flex: 1;
    min-width: 0;
}

.ai-column .remedy-name {
    font-size: 1rem;
    font-weight: 600;
    color: #2d3748;
}

.remedy-common {
    font-size: 0.8rem;
    color: #718096;
    margin-top: 2px;
}

.remedy-details {
    font-size: 0.9rem;
    color: #4a5568;
}

.remedy-potency {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #edf2f7;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #553c9a;
    margin-bottom: 10px;
}

.remedy-reasoning {
    line-height: 1.5;
    margin-bottom: 10px;
}

.remedy-reference {
    font-size: 0.8rem;
    color: #718096;
    padding: 5px 0;
    border-top: 1px dashed #e2e8f0;
}

.repertory-matches {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #e2e8f0;
}

.repertory-matches strong {
    display: block;
    font-size: 0.8rem;
    color: #4a5568;
    margin-bottom: 5px;
}

.rubric-tag {
    display: inline-block;
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 4px;
    margin: 2px;
    background: #edf2f7;
}

.rubric-tag.grade-3 {
    background: #c6f6d5;
    color: #22543d;
    font-weight: 600;
}

.rubric-tag.grade-2 {
    background: #fefcbf;
    color: #744210;
}

.rubric-tag.grade-1 {
    background: #fed7d7;
    color: #742a2a;
}

.matching-symptoms {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px dashed #e2e8f0;
}

.matching-symptoms strong {
    font-size: 0.8rem;
    color: #667eea;
}

.matching-symptoms ul {
    margin: 5px 0 0 0;
    padding-left: 18px;
    font-size: 0.85rem;
}

.matching-symptoms li {
    margin: 3px 0;
}

.case-analysis-box {
    margin: 15px;
    padding: 15px;
    border-radius: 8px;
}

.rag-analysis {
    background: #f0fff4;
    border-left: 4px solid #38a169;
}

.gemini-analysis {
    background: #f5f3ff;
    border-left: 4px solid #7c3aed;
}

.case-analysis-box h5 {
    margin: 0 0 10px 0;
    font-size: 0.95rem;
}

.rag-analysis h5 {
    color: #276749;
}

.gemini-analysis h5 {
    color: #553c9a;
}

.case-analysis-box p {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.6;
    color: #4a5568;
}

.case-analysis-box small {
    display: block;
    margin-top: 10px;
    color: #718096;
    font-size: 0.8rem;
}

.cautions-box {
    margin: 15px;
    padding: 15px;
    border-radius: 8px;
    background: #fffbeb;
    border-left: 4px solid #d69e2e;
}

.cautions-box h5 {
    margin: 0 0 10px 0;
    color: #975a16;
    font-size: 0.95rem;
}

.cautions-box p {
    margin: 0;
    font-size: 0.9rem;
    line-height: 1.6;
    color: #744210;
}

.no-results {
    padding: 40px 20px;
    text-align: center;
    color: #718096;
}

.no-results i {
    font-size: 2.5rem;
    margin-bottom: 15px;
    opacity: 0.5;
}

.ai-actions {
    text-align: center;
    padding: 20px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.ai-actions .btn {
    margin: 5px;
}

@media (max-width: 900px) {
    .ai-columns {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Track which consultations have AI suggestions expanded
const expandedAI = {};

async function toggleAISuggestions(consultationId) {
    const row = document.getElementById('ai-row-' + consultationId);
    const content = document.getElementById('ai-content-' + consultationId);
    const button = document.querySelector(`[data-consultation-id="${consultationId}"]`);
    
    // If already visible, hide it
    if (row.style.display !== 'none') {
        row.style.display = 'none';
        button.innerHTML = '<i class="fas fa-brain"></i> <strong>AI</strong>';
        return;
    }
    
    // Show the row
    row.style.display = 'table-row';
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <strong>Loading...</strong>';
    button.disabled = true;
    
    // If already loaded, just show it
    if (expandedAI[consultationId]) {
        button.innerHTML = '<i class="fas fa-brain"></i> <strong>Hide</strong>';
        button.disabled = false;
        return;
    }
    
    // Show loading state
    content.innerHTML = `
        <div class="ai-loading">
            <i class="fas fa-brain fa-spin"></i>
            <p style="margin-top: 15px; font-size: 16px; font-weight: bold;">Analyzing with RAG Database & Gemini AI...</p>
            <p style="color: #666;">This may take a few seconds</p>
        </div>
    `;
    
    try {
        // Fetch dual AI suggestions via AJAX
        const response = await fetch('<?php echo APP_URL; ?>/api/get_dual_ai_suggestions.php?consultation_id=' + consultationId, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        
        const data = await response.json();
        
        if (data.success) {
            // Display dual column suggestions
            let html = '<div class="dual-ai-container">';
            
            // Patient info header
            if (data.patient) {
                html += `
                    <div class="patient-info-header">
                        <i class="fas fa-user-injured"></i>
                        <strong>${data.patient.name}</strong> (${data.patient.age} yrs, ${data.patient.gender}) | 
                        <strong>Chief Complaint:</strong> ${data.patient.chief_complaint}
                    </div>
                `;
            }
            
            // Two column layout
            html += '<div class="ai-columns">';
            
            // ============================================
            // LEFT COLUMN: RAG Database Results
            // ============================================
            html += '<div class="ai-column rag-column">';
            html += `<div class="column-header rag-header">
                        <i class="fas fa-database"></i> RAG Database
                        <span class="column-badge">Local Materia Medica</span>
                     </div>`;
            
            if (data.rag && data.rag.remedies && data.rag.remedies.length > 0) {
                data.rag.remedies.forEach((remedy, index) => {
                    const matchClass = remedy.match_percentage >= 80 ? 'match-high' : 
                                     (remedy.match_percentage >= 60 ? 'match-medium' : 'match-low');
                    
                    html += `
                        <div class="remedy-card rag-card">
                            <div class="remedy-header">
                                <div class="remedy-rank">${index + 1}</div>
                                <div class="remedy-info">
                                    <div class="remedy-name">${remedy.name}</div>
                                    ${remedy.common_name ? `<div class="remedy-common">${remedy.common_name}</div>` : ''}
                                </div>
                                <span class="match-badge ${matchClass}">${remedy.match_percentage}%</span>
                            </div>
                            <div class="remedy-details">
                                <div class="remedy-potency"><i class="fas fa-pills"></i> ${remedy.potency}</div>
                                <div class="remedy-reasoning">${remedy.reasoning}</div>
                                ${remedy.reference ? `<div class="remedy-reference"><i class="fas fa-book"></i> ${remedy.reference}</div>` : ''}
                                ${remedy.repertory_rubrics && remedy.repertory_rubrics.length > 0 ? `
                                    <div class="repertory-matches">
                                        <strong>Repertory Matches:</strong>
                                        ${remedy.repertory_rubrics.slice(0, 3).map(r => 
                                            `<span class="rubric-tag grade-${r.grade}">${r.rubric} (G${r.grade})</span>`
                                        ).join('')}
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                });
                
                if (data.rag.case_analysis) {
                    html += `
                        <div class="case-analysis-box rag-analysis">
                            <h5><i class="fas fa-search"></i> Database Analysis</h5>
                            <p>${data.rag.case_analysis.replace(/\n/g, '<br>')}</p>
                            ${data.rag.total_remedies_searched ? `<small>Searched ${data.rag.total_remedies_searched} remedies</small>` : ''}
                        </div>
                    `;
                }
            } else {
                html += `
                    <div class="no-results">
                        <i class="fas fa-info-circle"></i>
                        <p>${data.rag?.error || 'No matching remedies found in database'}</p>
                    </div>
                `;
            }
            html += '</div>'; // end rag-column
            
            // ============================================
            // RIGHT COLUMN: Gemini AI Results
            // ============================================
            html += '<div class="ai-column gemini-column">';
            html += `<div class="column-header gemini-header">
                        <i class="fas fa-robot"></i> Gemini AI
                        <span class="column-badge">AI Analysis</span>
                     </div>`;
            
            if (data.gemini && data.gemini.remedies && data.gemini.remedies.length > 0) {
                data.gemini.remedies.forEach((remedy, index) => {
                    const matchClass = remedy.match_percentage >= 80 ? 'match-high' : 
                                     (remedy.match_percentage >= 60 ? 'match-medium' : 'match-low');
                    
                    html += `
                        <div class="remedy-card gemini-card">
                            <div class="remedy-header">
                                <div class="remedy-rank">${index + 1}</div>
                                <div class="remedy-info">
                                    <div class="remedy-name">${remedy.name}</div>
                                </div>
                                <span class="match-badge ${matchClass}">${remedy.match_percentage}%</span>
                            </div>
                            <div class="remedy-details">
                                <div class="remedy-potency"><i class="fas fa-pills"></i> ${remedy.potency}</div>
                                <div class="remedy-reasoning">${remedy.reasoning}</div>
                                ${remedy.reference ? `<div class="remedy-reference"><i class="fas fa-book"></i> ${remedy.reference}</div>` : ''}
                                ${remedy.matching_symptoms && remedy.matching_symptoms.length > 0 ? `
                                    <div class="matching-symptoms">
                                        <strong>Matching Symptoms:</strong>
                                        <ul>${remedy.matching_symptoms.map(s => `<li>${s}</li>`).join('')}</ul>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                });
                
                if (data.gemini.case_analysis) {
                    html += `
                        <div class="case-analysis-box gemini-analysis">
                            <h5><i class="fas fa-brain"></i> AI Case Analysis</h5>
                            <p>${data.gemini.case_analysis.replace(/\n/g, '<br>')}</p>
                        </div>
                    `;
                }
                
                if (data.gemini.cautions) {
                    html += `
                        <div class="cautions-box">
                            <h5><i class="fas fa-exclamation-triangle"></i> Cautions</h5>
                            <p>${data.gemini.cautions.replace(/\n/g, '<br>')}</p>
                        </div>
                    `;
                }
            } else {
                html += `
                    <div class="no-results">
                        <i class="fas fa-info-circle"></i>
                        <p>${data.gemini?.error || 'Gemini AI analysis not available'}</p>
                    </div>
                `;
            }
            html += '</div>'; // end gemini-column
            
            html += '</div>'; // end ai-columns
            
            // Action buttons
            html += `
                <div class="ai-actions">
                    <button onclick="window.open('<?php echo APP_URL; ?>/ai/suggestions.php?consultation_id=${consultationId}', '_blank')" 
                            class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i> Full Details
                    </button>
                    <button onclick="window.location.href='<?php echo APP_URL; ?>/prescriptions/add.php?consultation_id=${consultationId}'" 
                            class="btn btn-success">
                        <i class="fas fa-prescription"></i> Write Prescription
                    </button>
                    <button onclick="toggleAISuggestions(${consultationId})" class="btn btn-danger">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
            
            // Add disclaimer
            html += `
                <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%); border: 1px solid #ffc107; border-radius: 8px; padding: 12px; margin-top: 15px;">
                    <p style="margin: 0; font-size: 12px; color: #856404;">
                        <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
                        <strong>Disclaimer:</strong> These AI-generated suggestions are for educational and reference purposes only. 
                        They should not replace professional medical judgment. Always verify remedy selections against authoritative 
                        homeopathic texts and consider individual patient characteristics before prescribing. The practitioner bears 
                        full responsibility for all treatment decisions.
                    </p>
                </div>
            `;
            
            html += '</div>'; // end dual-ai-container
            content.innerHTML = html;
            expandedAI[consultationId] = true;
            
        } else {
            // Error state
            content.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #dc3545;">
                    <i class="fas fa-exclamation-circle" style="font-size: 3em; margin-bottom: 15px;"></i>
                    <h4>Failed to Generate Suggestions</h4>
                    <p style="font-size: 16px; margin: 15px 0;">${data.error || 'Unknown error occurred'}</p>
                    <button onclick="toggleAISuggestions(${consultationId})" class="ai-close-btn">Close</button>
                </div>
            `;
        }
        
    } catch (error) {
        content.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #dc3545;">
                <i class="fas fa-exclamation-circle" style="font-size: 3em; margin-bottom: 15px;"></i>
                <h4>Connection Error</h4>
                <p>${error.message}</p>
                <button onclick="toggleAISuggestions(${consultationId})" class="ai-close-btn">Close</button>
            </div>
        `;
    } finally {
        button.innerHTML = '<i class="fas fa-brain"></i> <strong>Hide</strong>';
        button.disabled = false;
    }
}

// Track which consultations have diagnosis expanded
const expandedDiagnosis = {};

async function toggleDiagnosis(consultationId) {
    const row = document.getElementById('diagnosis-row-' + consultationId);
    const content = document.getElementById('diagnosis-content-' + consultationId);
    const button = document.querySelector(`.diagnose-btn[data-consultation-id="${consultationId}"]`);
    
    // If already visible, hide it
    if (row.style.display !== 'none') {
        row.style.display = 'none';
        button.innerHTML = '<i class="fas fa-diagnoses"></i>';
        return;
    }
    
    // Hide AI row if visible
    const aiRow = document.getElementById('ai-row-' + consultationId);
    if (aiRow) aiRow.style.display = 'none';
    
    // Show the row
    row.style.display = 'table-row';
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    // If already loaded, just show it
    if (expandedDiagnosis[consultationId]) {
        button.innerHTML = '<i class="fas fa-times"></i>';
        button.disabled = false;
        return;
    }
    
    // Show loading state
    content.innerHTML = `
        <div class="diagnosis-loading" style="text-align: center; padding: 30px;">
            <i class="fas fa-search-plus fa-spin" style="font-size: 2.5em; color: #0ea5e9;"></i>
           <p style="margin-top: 15px; font-size: 16px; font-weight: bold;">Analyzing symptoms with RAG Database...</p>
            <p style="color: #666;">Searching local medical knowledge</p>
        </div>
    `;
    
    try {
        // Get consultation data from the table row
        const tableRow = row.previousElementSibling.previousElementSibling; // Go back past AI row
        const complaint = tableRow.querySelector('td:nth-child(3)').textContent.trim();
        const patientInfo = tableRow.querySelector('td:nth-child(2)').textContent.trim();
        
        // Fetch diagnosis via AJAX
        const response = await fetch('<?php echo APP_URL; ?>/api/get_disease_diagnosis.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                symptoms: complaint,
                chief_complaint: complaint
            })
        });
        
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        
        const data = await response.json();
        
        if (data.success && data.diagnoses && data.diagnoses.length > 0) {
            expandedDiagnosis[consultationId] = true;
            
            let html = `
                <div class="diagnosis-results-container">
                    <div class="diagnosis-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #0ea5e9;">
                        <div>
                            <h4 style="margin: 0; color: #0369a1;"><i class="fas fa-diagnoses"></i> Disease Diagnosis (RAG)</h4>
                            <small style="color: #666;">Based on: "${complaint.substring(0, 60)}..."</small>
                        </div>
                        <button onclick="toggleDiagnosis(${consultationId})" style="background: #0ea5e9; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer;">
                            <i class="fas fa-times"></i> Close
                        </button>
                    </div>
                    <div class="diagnosis-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
            `;
            
            data.diagnoses.forEach((d, index) => {
                const confidenceClass = d.confidence === 'High' ? 'high' : (d.confidence === 'Medium' ? 'medium' : 'low');
                const confidenceColor = d.confidence === 'High' ? '#10b981' : (d.confidence === 'Medium' ? '#f59e0b' : '#6b7280');
                
                html += `
                    <div class="diagnosis-card" style="background: white; border-radius: 10px; padding: 15px; border-left: 4px solid ${confidenceColor}; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                            <h5 style="margin: 0; color: #1e293b; font-size: 16px;">${index + 1}. ${d.diagnosis}</h5>
                            <span style="background: ${confidenceColor}; color: white; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">${d.confidence}</span>
                        </div>
                        <div style="margin-bottom: 8px;">
                            <small style="color: #888; text-transform: uppercase; font-size: 10px;">Matching Symptoms</small>
                            <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px;">
                                ${d.matching_symptoms.map(s => `<span style="background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 10px; font-size: 11px;">${s}</span>`).join('')}
                            </div>
                        </div>
                        ${d.supporting_findings ? `
                        <div style="margin-bottom: 8px;">
                            <small style="color: #888; text-transform: uppercase; font-size: 10px;">Supporting Findings</small>
                            <p style="margin: 4px 0 0; font-size: 12px; color: #333;">${d.supporting_findings}</p>
                        </div>
                        ` : ''}
                        ${d.notes_for_doctor ? `
                        <div style="background: #fef3c7; padding: 8px 10px; border-radius: 6px; margin-top: 8px;">
                            <small style="color: #92400e; font-size: 11px;"><i class="fas fa-exclamation-triangle"></i> ${d.notes_for_doctor}</small>
                        </div>
                        ` : ''}
                    </div>
                `;
            });
            
            html += `
                    </div>
                    <div style="margin-top: 15px; padding: 10px; background: #f8fafc; border-radius: 8px; font-size: 11px; color: #64748b;">
                        <i class="fas fa-info-circle"></i> This is a diagnostic suggestion based on local database matching. Clinical correlation is essential.
                    </div>
                </div>
            `;
            
            content.innerHTML = html;
        } else {
            content.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #64748b;">
                    <i class="fas fa-search" style="font-size: 3em; margin-bottom: 15px; color: #cbd5e1;"></i>
                    <h4>No Matching Conditions Found</h4>
                    <p>The symptoms don't match any conditions in the database.</p>
                    <button onclick="toggleDiagnosis(${consultationId})" style="background: #0ea5e9; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; margin-top: 10px;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            `;
        }
        
    } catch (error) {
        content.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #dc3545;">
                <i class="fas fa-exclamation-circle" style="font-size: 3em; margin-bottom: 15px;"></i>
                <h4>Error Loading Diagnosis</h4>
                <p>${error.message}</p>
                <button onclick="toggleDiagnosis(${consultationId})" style="background: #0ea5e9; color: white; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; margin-top: 10px;">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
        `;
    } finally {
        button.innerHTML = '<i class="fas fa-times"></i>';
        button.disabled = false;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
