<?php
/**
 * Follow-up Consultations Management
 * Track and manage patient follow-ups with AI-powered suggestions
 */
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();

// Get filter parameters
$filter = $_GET['filter'] ?? 'pending';
$search = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query based on filter
$whereConditions = ["c.doctor_id = ?"];
$params = [$doctorId];

if ($search) {
    $whereConditions[] = "(p.patient_name LIKE ? OR c.chief_complaint LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($dateFrom) {
    $whereConditions[] = "c.consultation_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = "c.consultation_date <= ?";
    $params[] = $dateTo;
}

// Filter logic
switch ($filter) {
    case 'pending':
        // Consultations with follow-up instructions but no follow-up consultation yet
        $whereConditions[] = "c.follow_up_date IS NOT NULL";
        $whereConditions[] = "c.follow_up_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $whereConditions[] = "NOT EXISTS (
            SELECT 1 FROM consultations c2 
            WHERE c2.patient_id = c.patient_id 
            AND c2.doctor_id = c.doctor_id 
            AND c2.consultation_date > c.consultation_date
        )";
        break;
    case 'overdue':
        $whereConditions[] = "c.follow_up_date IS NOT NULL";
        $whereConditions[] = "c.follow_up_date < CURDATE()";
        $whereConditions[] = "NOT EXISTS (
            SELECT 1 FROM consultations c2 
            WHERE c2.patient_id = c.patient_id 
            AND c2.doctor_id = c.doctor_id 
            AND c2.consultation_date > c.consultation_date
        )";
        break;
    case 'upcoming':
        $whereConditions[] = "c.follow_up_date IS NOT NULL";
        $whereConditions[] = "c.follow_up_date > CURDATE()";
        $whereConditions[] = "c.follow_up_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
        break;
    case 'completed':
        $whereConditions[] = "EXISTS (
            SELECT 1 FROM consultations c2 
            WHERE c2.patient_id = c.patient_id 
            AND c2.doctor_id = c.doctor_id 
            AND c2.consultation_date > c.consultation_date
            AND c2.is_follow_up = 1
        )";
        break;
    default:
        // All with follow-up dates
        $whereConditions[] = "c.follow_up_date IS NOT NULL";
}

$whereClause = implode(' AND ', $whereConditions);

// Get follow-up consultations
$sql = "SELECT c.*, 
               p.patient_name, p.age, p.gender, p.phone,
               DATEDIFF(c.follow_up_date, CURDATE()) as days_until_followup,
               (SELECT COUNT(*) FROM symptoms WHERE consultation_id = c.id) as symptom_count,
               (SELECT COUNT(*) FROM prescriptions WHERE consultation_id = c.id) as prescription_count
        FROM consultations c
        INNER JOIN patients p ON c.patient_id = p.id
        WHERE $whereClause
        ORDER BY 
            CASE WHEN c.follow_up_date < CURDATE() THEN 0 ELSE 1 END,
            ABS(DATEDIFF(c.follow_up_date, CURDATE())),
            c.consultation_date DESC
        LIMIT 50";

$followups = DB::query($sql, $params);

// Get statistics
$stats = [
    'overdue' => DB::queryOne(
        "SELECT COUNT(*) as cnt FROM consultations c 
         WHERE c.doctor_id = ? AND c.follow_up_date IS NOT NULL 
         AND c.follow_up_date < CURDATE()
         AND NOT EXISTS (SELECT 1 FROM consultations c2 WHERE c2.patient_id = c.patient_id AND c2.doctor_id = c.doctor_id AND c2.consultation_date > c.consultation_date)",
        [$doctorId]
    )['cnt'] ?? 0,
    'today' => DB::queryOne(
        "SELECT COUNT(*) as cnt FROM consultations c 
         WHERE c.doctor_id = ? AND c.follow_up_date = CURDATE()",
        [$doctorId]
    )['cnt'] ?? 0,
    'this_week' => DB::queryOne(
        "SELECT COUNT(*) as cnt FROM consultations c 
         WHERE c.doctor_id = ? AND c.follow_up_date IS NOT NULL 
         AND c.follow_up_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)",
        [$doctorId]
    )['cnt'] ?? 0,
    'this_month' => DB::queryOne(
        "SELECT COUNT(*) as cnt FROM consultations c 
         WHERE c.doctor_id = ? AND c.follow_up_date IS NOT NULL 
         AND c.follow_up_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
        [$doctorId]
    )['cnt'] ?? 0,
];

$pageTitle = 'Follow-up Management';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<style>
.followup-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-3px);
}

.stat-card.overdue {
    border-left: 4px solid #dc3545;
}

.stat-card.today {
    border-left: 4px solid #ffc107;
}

.stat-card.week {
    border-left: 4px solid #17a2b8;
}

.stat-card.month {
    border-left: 4px solid #28a745;
}

.stat-number {
    font-size: 36px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    color: #666;
    font-size: 14px;
}

.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    border-radius: 25px;
    background: #f0f0f0;
    color: #333;
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.filter-tab:hover {
    background: #e0e0e0;
}

.filter-tab.active {
    background: var(--primary-color);
    color: white;
}

.followup-card {
    background: white;
    border-radius: 12px;
    margin-bottom: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
    transition: transform 0.2s;
}

.followup-card:hover {
    transform: translateY(-2px);
}

.followup-card.overdue {
    border-left: 4px solid #dc3545;
}

.followup-card.today {
    border-left: 4px solid #ffc107;
}

.followup-card.upcoming {
    border-left: 4px solid #28a745;
}

.followup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
}

.patient-info h4 {
    margin: 0 0 5px 0;
    color: #333;
}

.patient-meta {
    font-size: 13px;
    color: #666;
}

.followup-status {
    text-align: right;
}

.status-badge {
    display: inline-block;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
}

.status-badge.overdue {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.today {
    background: #fff3cd;
    color: #856404;
}

.status-badge.upcoming {
    background: #d4edda;
    color: #155724;
}

.followup-body {
    padding: 20px;
}

.complaint-section {
    margin-bottom: 15px;
}

.complaint-section strong {
    color: #333;
    display: block;
    margin-bottom: 5px;
}

.symptoms-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.symptom-tag {
    background: #e9ecef;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 12px;
    color: #495057;
}

.followup-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.ai-suggestions-panel {
    display: none;
    background: #f8f9fa;
    padding: 20px;
    border-top: 1px solid #eee;
}

.ai-suggestions-panel.active {
    display: block;
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .followup-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .followup-status {
        text-align: left;
    }
}
</style>

<div class="followup-container">
    <!-- Page Header -->
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
        <div>
            <h1><i class="fas fa-calendar-check"></i> Follow-up Management</h1>
            <p class="text-muted">Track and manage patient follow-up consultations</p>
        </div>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> New Consultation
        </a>
    </div>
    
    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card overdue">
            <div class="stat-number" style="color: #dc3545;"><?php echo $stats['overdue']; ?></div>
            <div class="stat-label">Overdue Follow-ups</div>
        </div>
        <div class="stat-card today">
            <div class="stat-number" style="color: #ffc107;"><?php echo $stats['today']; ?></div>
            <div class="stat-label">Due Today</div>
        </div>
        <div class="stat-card week">
            <div class="stat-number" style="color: #17a2b8;"><?php echo $stats['this_week']; ?></div>
            <div class="stat-label">This Week</div>
        </div>
        <div class="stat-card month">
            <div class="stat-number" style="color: #28a745;"><?php echo $stats['this_month']; ?></div>
            <div class="stat-label">This Month</div>
        </div>
    </div>
    
    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
            <i class="fas fa-clock"></i> Pending
        </a>
        <a href="?filter=overdue" class="filter-tab <?php echo $filter === 'overdue' ? 'active' : ''; ?>">
            <i class="fas fa-exclamation-triangle"></i> Overdue
        </a>
        <a href="?filter=upcoming" class="filter-tab <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i> Upcoming
        </a>
        <a href="?filter=completed" class="filter-tab <?php echo $filter === 'completed' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i> Completed
        </a>
        <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
            <i class="fas fa-list"></i> All
        </a>
    </div>
    
    <!-- Search Box -->
    <div class="dashboard-card" style="margin-bottom: 20px;">
        <div class="card-body">
            <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">
                <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                <div style="flex: 1; min-width: 200px;">
                    <label>Search Patient/Complaint</label>
                    <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div>
                    <label>From Date</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($dateFrom); ?>">
                </div>
                <div>
                    <label>To Date</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($dateTo); ?>">
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="?filter=<?php echo $filter; ?>" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
            </form>
        </div>
    </div>
    
    <!-- Follow-up List -->
    <?php if (empty($followups)): ?>
    <div class="dashboard-card">
        <div class="card-body text-center" style="padding: 60px;">
            <i class="fas fa-calendar-check" style="font-size: 64px; color: #ccc; margin-bottom: 20px;"></i>
            <h3>No Follow-ups Found</h3>
            <p class="text-muted">No follow-up consultations match your criteria.</p>
        </div>
    </div>
    <?php else: ?>
    
    <?php foreach ($followups as $followup): 
        $daysUntil = $followup['days_until_followup'];
        $statusClass = 'upcoming';
        $statusText = "In $daysUntil days";
        
        if ($daysUntil < 0) {
            $statusClass = 'overdue';
            $statusText = abs($daysUntil) . ' days overdue';
        } elseif ($daysUntil == 0) {
            $statusClass = 'today';
            $statusText = 'Due today';
        } elseif ($daysUntil == 1) {
            $statusText = 'Tomorrow';
        }
        
        // Fetch symptoms for this consultation
        $symptoms = DB::query("SELECT * FROM symptoms WHERE consultation_id = ? LIMIT 5", [$followup['id']]);
    ?>
    <div class="followup-card <?php echo $statusClass; ?>">
        <div class="followup-header">
            <div class="patient-info">
                <h4>
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($followup['patient_name']); ?>
                    <span style="font-weight: normal; font-size: 14px; color: #666;">
                        (<?php echo $followup['age']; ?> yrs, <?php echo ucfirst($followup['gender']); ?>)
                    </span>
                </h4>
                <div class="patient-meta">
                    <i class="fas fa-calendar"></i> Consultation: <?php echo formatDate($followup['consultation_date'], 'd M Y'); ?>
                    <?php if ($followup['phone']): ?>
                    &nbsp;|&nbsp; <i class="fas fa-phone"></i> <?php echo htmlspecialchars($followup['phone']); ?>
                    <?php endif; ?>
                    &nbsp;|&nbsp; <i class="fas fa-clipboard-list"></i> <?php echo $followup['symptom_count']; ?> symptoms
                    &nbsp;|&nbsp; <i class="fas fa-prescription"></i> <?php echo $followup['prescription_count']; ?> prescriptions
                </div>
            </div>
            <div class="followup-status">
                <span class="status-badge <?php echo $statusClass; ?>">
                    <?php if ($statusClass === 'overdue'): ?>
                    <i class="fas fa-exclamation-circle"></i>
                    <?php elseif ($statusClass === 'today'): ?>
                    <i class="fas fa-bell"></i>
                    <?php else: ?>
                    <i class="fas fa-clock"></i>
                    <?php endif; ?>
                    <?php echo $statusText; ?>
                </span>
                <div style="margin-top: 5px; font-size: 13px; color: #666;">
                    Follow-up: <?php echo formatDate($followup['follow_up_date'], 'd M Y'); ?>
                </div>
            </div>
        </div>
        
        <div class="followup-body">
            <div class="complaint-section">
                <strong><i class="fas fa-notes-medical"></i> Chief Complaint</strong>
                <?php echo htmlspecialchars($followup['chief_complaint']); ?>
            </div>
            
            <?php if (!empty($symptoms)): ?>
            <div class="symptoms-list">
                <?php foreach ($symptoms as $symptom): ?>
                <span class="symptom-tag">
                    <?php echo htmlspecialchars($symptom['symptom_text']); ?>
                    <?php if ($symptom['intensity']): ?>
                    <span style="color: <?php echo $symptom['intensity'] === 'severe' ? '#dc3545' : ($symptom['intensity'] === 'moderate' ? '#ffc107' : '#28a745'); ?>;">
                        (<?php echo $symptom['intensity']; ?>)
                    </span>
                    <?php endif; ?>
                </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="followup-actions">
                <a href="view.php?id=<?php echo $followup['id']; ?>" class="btn btn-info btn-sm">
                    <i class="fas fa-eye"></i> View Details
                </a>
                <a href="add.php?patient_id=<?php echo $followup['patient_id']; ?>&is_followup=1&previous_consultation_id=<?php echo $followup['id']; ?>" class="btn btn-success btn-sm">
                    <i class="fas fa-plus"></i> Create Follow-up Consultation
                </a>
                <button type="button" class="btn btn-primary btn-sm" onclick="toggleAISuggestions(<?php echo $followup['id']; ?>)">
                    <i class="fas fa-brain"></i> AI Suggestions
                </button>
                <a href="/homeo1/prescriptions/add.php?consultation_id=<?php echo $followup['id']; ?>" class="btn btn-warning btn-sm">
                    <i class="fas fa-prescription"></i> Add Prescription
                </a>
            </div>
        </div>
        
        <!-- AI Suggestions Panel -->
        <div id="ai-panel-<?php echo $followup['id']; ?>" class="ai-suggestions-panel">
            <div class="ai-loading" style="text-align: center; padding: 20px; display: none;">
                <i class="fas fa-brain fa-spin fa-2x"></i>
                <p>Analyzing consultation with AI...</p>
            </div>
            <div class="ai-content"></div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <?php endif; ?>
</div>

<script>
const expandedAI = {};

async function toggleAISuggestions(consultationId) {
    const panel = document.getElementById(`ai-panel-${consultationId}`);
    const loading = panel.querySelector('.ai-loading');
    const content = panel.querySelector('.ai-content');
    
    // Toggle panel
    if (panel.classList.contains('active') && expandedAI[consultationId]) {
        panel.classList.remove('active');
        return;
    }
    
    panel.classList.add('active');
    
    // If already loaded, just show
    if (expandedAI[consultationId]) {
        return;
    }
    
    // Fetch AI suggestions
    loading.style.display = 'block';
    content.innerHTML = '';
    
    try {
        const response = await fetch(`/homeo1/api/get_dual_ai_suggestions.php?consultation_id=${consultationId}`);
        const data = await response.json();
        
        loading.style.display = 'none';
        
        if (!data.success) {
            content.innerHTML = `<div class="alert alert-danger">${data.error || 'Failed to fetch suggestions'}</div>`;
            return;
        }
        
        expandedAI[consultationId] = true;
        displayAISuggestions(content, data);
        
    } catch (error) {
        loading.style.display = 'none';
        content.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
    }
}

function displayAISuggestions(container, data) {
    let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
    
    // RAG Database Column
    html += `
    <div style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-radius: 12px; padding: 15px;">
        <h4 style="color: #155724; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-database"></i> RAG Database
            <span style="font-size: 11px; background: #155724; color: white; padding: 2px 8px; border-radius: 10px;">Local Materia Medica</span>
        </h4>`;
    
    if (data.rag && data.rag.remedies && data.rag.remedies.length > 0) {
        data.rag.remedies.forEach((remedy, idx) => {
            html += `
            <div style="background: white; border-radius: 8px; padding: 12px; margin-bottom: 10px; border-left: 4px solid #28a745;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <span style="background: #155724; color: white; padding: 2px 8px; border-radius: 50%; font-size: 12px; margin-right: 8px;">${idx + 1}</span>
                        <strong style="color: #155724;">${remedy.name}</strong>
                        ${remedy.common_name ? `<br><small style="color: #666; margin-left: 28px;">${remedy.common_name}</small>` : ''}
                    </div>
                    <div style="text-align: right;">
                        <span style="background: #28a745; color: white; padding: 3px 10px; border-radius: 15px; font-weight: bold;">${remedy.match_percentage}%</span>
                        <br><small style="color: #666;">${remedy.potency || '30C'}</small>
                    </div>
                </div>
                ${remedy.reasoning ? `<p style="font-size: 12px; color: #555; margin: 8px 0 5px 28px; line-height: 1.4;">${remedy.reasoning.substring(0, 150)}${remedy.reasoning.length > 150 ? '...' : ''}</p>` : ''}
                ${remedy.reference ? `<small style="color: #888; margin-left: 28px; font-style: italic;">${remedy.reference.substring(0, 80)}</small>` : ''}
            </div>`;
        });
        
        if (data.rag.case_analysis) {
            html += `
            <div style="background: rgba(255,255,255,0.7); border-radius: 8px; padding: 10px; margin-top: 10px;">
                <strong style="color: #155724; font-size: 12px;"><i class="fas fa-clipboard-list"></i> Database Analysis</strong>
                <p style="font-size: 12px; color: #333; margin: 5px 0 0 0; white-space: pre-line;">${data.rag.case_analysis}</p>
            </div>`;
        }
    } else {
        html += '<p style="color: #666; font-style: italic; padding: 10px;">No RAG suggestions available</p>';
    }
    html += '</div>';
    
    // Gemini AI Column
    html += `
    <div style="background: linear-gradient(135deg, #e8daef 0%, #d2b4de 100%); border-radius: 12px; padding: 15px;">
        <h4 style="color: #4a235a; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-brain"></i> Gemini AI
            <span style="font-size: 11px; background: #4a235a; color: white; padding: 2px 8px; border-radius: 10px;">AI Analysis</span>
        </h4>`;
    
    if (data.gemini && data.gemini.remedies && data.gemini.remedies.length > 0) {
        data.gemini.remedies.forEach((remedy, idx) => {
            html += `
            <div style="background: white; border-radius: 8px; padding: 12px; margin-bottom: 10px; border-left: 4px solid #8e44ad;">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <span style="background: #4a235a; color: white; padding: 2px 8px; border-radius: 50%; font-size: 12px; margin-right: 8px;">${idx + 1}</span>
                        <strong style="color: #4a235a;">${remedy.name}</strong>
                    </div>
                    <div style="text-align: right;">
                        <span style="background: #8e44ad; color: white; padding: 3px 10px; border-radius: 15px; font-weight: bold;">${remedy.match_percentage}%</span>
                        <br><small style="color: #666;">${remedy.potency || '30C'}</small>
                    </div>
                </div>
                ${remedy.reasoning ? `<p style="font-size: 12px; color: #555; margin: 8px 0 5px 28px; line-height: 1.4;">${remedy.reasoning.substring(0, 150)}${remedy.reasoning.length > 150 ? '...' : ''}</p>` : ''}
                ${remedy.reference ? `<small style="color: #888; margin-left: 28px; font-style: italic;">${remedy.reference}</small>` : ''}
            </div>`;
        });
        
        if (data.gemini.case_analysis) {
            html += `
            <div style="background: rgba(255,255,255,0.7); border-radius: 8px; padding: 10px; margin-top: 10px;">
                <strong style="color: #4a235a; font-size: 12px;"><i class="fas fa-lightbulb"></i> AI Case Analysis</strong>
                <p style="font-size: 12px; color: #333; margin: 5px 0 0 0;">${data.gemini.case_analysis}</p>
            </div>`;
        }
        
        if (data.gemini.cautions) {
            html += `
            <div style="background: rgba(255,193,7,0.2); border-radius: 8px; padding: 10px; margin-top: 10px;">
                <strong style="color: #856404; font-size: 12px;"><i class="fas fa-exclamation-triangle"></i> Cautions</strong>
                <p style="font-size: 12px; color: #333; margin: 5px 0 0 0;">${data.gemini.cautions}</p>
            </div>`;
        }
    } else if (data.gemini && data.gemini.error) {
        html += `<p style="color: #666; font-style: italic; padding: 10px;">${data.gemini.error}</p>`;
    } else {
        html += '<p style="color: #666; font-style: italic; padding: 10px;">No Gemini suggestions available</p>';
    }
    html += '</div>';
    
    html += '</div>';
    
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
    
    container.innerHTML = html;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
