<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();
$consultationId = isset($_GET['consultation_id']) ? (int)$_GET['consultation_id'] : 0;

// Validate consultation ID
if ($consultationId <= 0) {
    setFlash('error', 'Invalid consultation ID. Please select a consultation from the list.');
    redirect('/consultations/list.php');
}

// Fetch consultation details with patient info
$sql = "SELECT c.*, p.patient_name, p.age, p.gender, p.phone, p.email,
               p.blood_group, p.allergies
        FROM consultations c
        INNER JOIN patients p ON c.patient_id = p.id
        WHERE c.id = ? AND c.doctor_id = ?";

$consultation = DB::queryOne($sql, [$consultationId, $doctorId]);

if (!$consultation) {
    setFlash('error', 'Consultation not found or you do not have permission to access it.');
    redirect('/consultations/list.php');
}

// Fetch symptoms for this consultation
$symptoms = DB::query(
    "SELECT * FROM symptoms WHERE consultation_id = ? ORDER BY id",
    [$consultationId]
);

// Fetch available remedies for prescription
$remedies = DB::query("SELECT * FROM remedies ORDER BY remedy_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check maintenance mode first
    if (blockIfMaintenance()) {
        header('Location: ' . APP_URL . '/consultations/list.php');
        exit;
    }
    
    $prescriptionDate = sanitize($_POST['prescription_date'] ?? date('Y-m-d'));
    $remedySelections = $_POST['remedies'] ?? [];
    $dietAdvice = sanitize($_POST['diet_advice'] ?? '');
    $lifestyleAdvice = sanitize($_POST['lifestyle_advice'] ?? '');
    $followUpInstructions = sanitize($_POST['follow_up_instructions'] ?? '');
    $generalInstructions = sanitize($_POST['general_instructions'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    
    // Validation
    if (empty($remedySelections)) {
        $error = 'At least one remedy is required';
    } else {
        try {
            DB::beginTransaction();
            
            // Create prescription
            $prescriptionData = [
                'consultation_id' => $consultationId,
                'patient_id' => $consultation['patient_id'],
                'doctor_id' => $doctorId,
                'prescription_date' => $prescriptionDate,
                'diet_advice' => $dietAdvice,
                'lifestyle_advice' => $lifestyleAdvice,
                'follow_up_instructions' => $followUpInstructions,
                'general_instructions' => $generalInstructions,
                'notes' => $notes
            ];
            
            $prescriptionId = DB::insert('prescriptions', $prescriptionData);
            
            if (!$prescriptionId) {
                throw new Exception('Failed to create prescription');
            }
            
            // Insert remedy selections
            foreach ($remedySelections as $index => $remedy) {
                if (!empty($remedy['remedy_id'])) {
                    $remedyData = [
                        'prescription_id' => $prescriptionId,
                        'remedy_id' => (int)$remedy['remedy_id'],
                        'potency' => sanitize($remedy['potency'] ?? '30C'),
                        'dosage' => sanitize($remedy['dosage'] ?? 'TDS'),
                        'duration' => sanitize($remedy['duration'] ?? '7 days'),
                        'instructions' => sanitize($remedy['instructions'] ?? 'Take as directed')
                    ];
                    
                    DB::insert('prescription_remedies', $remedyData);
                }
            }
            
            DB::commit();
            
            logActivity('prescription_created', "Created prescription ID: {$prescriptionId} for consultation ID: {$consultationId}", $doctorId);
            setFlash('success', 'Prescription created successfully!');
            redirect('/prescriptions/view.php?id=' . $prescriptionId);
            
        } catch (Exception $e) {
            DB::rollback();
            $error = 'Failed to create prescription: ' . $e->getMessage();
            error_log($error);
        }
    }
}

$pageTitle = 'Write Prescription';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<!-- jQuery must load before Select2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<div class="prescription-form-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <a href="<?php echo APP_URL; ?>/consultations/view.php?id=<?php echo $consultation['id']; ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Consultation
            </a>
            <h1><i class="fas fa-prescription"></i> Write Prescription</h1>
            <p class="text-muted">Create a new prescription for the patient</p>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="prescriptionForm">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <!-- Patient & Consultation Info -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-user-injured"></i> Patient & Consultation Details</h3>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Patient Name:</label>
                        <span><?php echo htmlspecialchars($consultation['patient_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Age / Gender:</label>
                        <span><?php echo $consultation['age']; ?> years / <?php echo ucfirst($consultation['gender']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Phone:</label>
                        <span><?php echo htmlspecialchars($consultation['phone'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Blood Group:</label>
                        <span><?php echo htmlspecialchars($consultation['blood_group'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($consultation['allergies'])): ?>
                <div class="alert alert-warning" style="margin-top: 15px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Allergies:</strong> <?php echo htmlspecialchars($consultation['allergies']); ?>
                </div>
                <?php endif; ?>
                
                <div style="margin-top: 15px; padding: 15px; background: var(--gray-50); border-radius: 8px;">
                    <strong>Chief Complaint:</strong>
                    <p style="margin: 8px 0 0 0;"><?php echo nl2br(htmlspecialchars($consultation['chief_complaint'])); ?></p>
                    
                    <?php if (!empty($consultation['diagnosis'])): ?>
                    <strong style="margin-top: 10px; display: block;">Diagnosis:</strong>
                    <p style="margin: 8px 0 0 0;"><?php echo nl2br(htmlspecialchars($consultation['diagnosis'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Prescription Date -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Prescription Date</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="prescription_date">Date</label>
                    <input 
                        type="date" 
                        id="prescription_date" 
                        name="prescription_date" 
                        class="form-control" 
                        value="<?php echo date('Y-m-d'); ?>"
                        required
                    >
                </div>
            </div>
        </div>
        
        <!-- Remedies -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-capsules"></i> Remedies</h3>
                <button type="button" class="btn btn-sm btn-success" onclick="addRemedy()">
                    <i class="fas fa-plus"></i> Add Remedy
                </button>
            </div>
            <div class="card-body">
                <div id="remedies-container">
                    <!-- Remedies will be added here -->
                </div>
                
                <div class="empty-remedies text-center">
                    <i class="fas fa-capsules"></i>
                    <p>No remedies added yet</p>
                    <button type="button" class="btn btn-success" onclick="addRemedy()">
                        <i class="fas fa-plus"></i> Add First Remedy
                    </button>
                </div>
            </div>
        </div>
        
        <!-- AI Remedy Suggestions -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-robot"></i> AI Remedy Suggestions</h3>
                <button type="button" class="btn btn-sm btn-info" onclick="fetchAISuggestions()">
                    <i class="fas fa-sync"></i> Refresh Suggestions
                </button>
            </div>
            <div class="card-body">
                <div id="ai-remedy-suggestions">
                    <div class="loading-state" style="display:none; text-align: center; padding: 20px;">
                        <i class="fas fa-brain fa-spin fa-2x"></i>
                        <p>Analyzing consultation with AI...</p>
                    </div>
                    <div class="ai-suggestions-list"></div>
                </div>
            </div>
        </div>
        
        <!-- Advice & Instructions -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Advice & Instructions</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="diet_advice">Diet Advice</label>
                    <textarea 
                        id="diet_advice" 
                        name="diet_advice" 
                        class="form-control" 
                        rows="3" 
                        placeholder="Dietary recommendations and restrictions..."
                    ></textarea>
                </div>
                
                <div class="form-group">
                    <label for="lifestyle_advice">Lifestyle Advice</label>
                    <textarea 
                        id="lifestyle_advice" 
                        name="lifestyle_advice" 
                        class="form-control" 
                        rows="3" 
                        placeholder="Lifestyle modifications, exercise, sleep patterns..."
                    ></textarea>
                </div>
                
                <div class="form-group">
                    <label for="follow_up_instructions">Follow-up Instructions</label>
                    <textarea 
                        id="follow_up_instructions" 
                        name="follow_up_instructions" 
                        class="form-control" 
                        rows="2" 
                        placeholder="When to return for follow-up, what to monitor..."
                    ></textarea>
                </div>
                
                <div class="form-group">
                    <label for="general_instructions">General Instructions</label>
                    <textarea 
                        id="general_instructions" 
                        name="general_instructions" 
                        class="form-control" 
                        rows="3" 
                        placeholder="General care instructions, precautions..."
                    ></textarea>
                </div>
                
                <div class="form-group">
                    <label for="notes">Private Notes (Not visible to patient)</label>
                    <textarea 
                        id="notes" 
                        name="notes" 
                        class="form-control" 
                        rows="2" 
                        placeholder="Internal notes for your reference..."
                    ></textarea>
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="form-actions">
            <a href="<?php echo APP_URL; ?>/consultations/view.php?id=<?php echo $consultation['id']; ?>" class="btn btn-outline">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Prescription
            </button>
        </div>
    </form>
</div>

<!-- Remedy Template -->
<template id="remedy-template">
    <div class="remedy-card" id="remedy-INDEX">
        <div class="remedy-header">
            <span class="remedy-number">#<span class="number">1</span></span>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeRemedy(INDEX)">
                <i class="fas fa-times"></i> Remove
            </button>
        </div>
        <div class="remedy-body">
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label>Remedy *</label>
                    <select name="remedies[INDEX][remedy_id]" class="form-control remedy-select" required>
                        <option value="">Select a remedy...</option>
                        <?php foreach ($remedies as $remedy): ?>
                            <option value="<?php echo $remedy['id']; ?>">
                                <?php echo htmlspecialchars($remedy['remedy_name']); ?>
                                <?php if (!empty($remedy['common_name'])): ?>
                                    (<?php echo htmlspecialchars($remedy['common_name']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Potency</label>
                    <select name="remedies[INDEX][potency]" class="form-control">
                        <option value="6C">6C</option>
                        <option value="12C">12C</option>
                        <option value="30C" selected>30C</option>
                        <option value="200C">200C</option>
                        <option value="1M">1M</option>
                        <option value="10M">10M</option>
                        <option value="50M">50M</option>
                        <option value="CM">CM</option>
                        <option value="6X">6X</option>
                        <option value="12X">12X</option>
                        <option value="30X">30X</option>
                        <option value="Q">Q (LM)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Dosage</label>
                    <select name="remedies[INDEX][dosage]" class="form-control">
                        <option value="OD">OD (Once daily)</option>
                        <option value="BD">BD (Twice daily)</option>
                        <option value="TDS" selected>TDS (Three times daily)</option>
                        <option value="QID">QID (Four times daily)</option>
                        <option value="SOS">SOS (As needed)</option>
                        <option value="STAT">STAT (Immediately)</option>
                        <option value="HS">HS (At bedtime)</option>
                        <option value="PRN">PRN (When necessary)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Duration</label>
                    <input 
                        type="text" 
                        name="remedies[INDEX][duration]" 
                        class="form-control" 
                        placeholder="e.g., 7 days, 2 weeks"
                        value="7 days"
                    >
                </div>
            </div>
            
            <div class="form-group">
                <label>Special Instructions</label>
                <input 
                    type="text" 
                    name="remedies[INDEX][instructions]" 
                    class="form-control" 
                    placeholder="e.g., Take on empty stomach, 30 minutes before meals..."
                    value="Take as directed"
                >
            </div>
        </div>
    </div>
</template>

<style>
/* Select2 Search Box Styles - Force search box to always show */
.select2-container--default .select2-search--dropdown {
    padding: 10px !important;
    background: #f8f9fa !important;
    display: block !important;
}

.select2-container--default .select2-search--dropdown .select2-search__field {
    padding: 12px 15px !important;
    border: 2px solid var(--primary-color, #14b8a6) !important;
    border-radius: 8px !important;
    font-size: 15px !important;
    width: 100% !important;
    box-sizing: border-box !important;
    background: white !important;
}

.select2-container--default .select2-search--dropdown .select2-search__field:focus {
    outline: none !important;
    border-color: var(--primary-color, #14b8a6) !important;
    box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.2) !important;
}

.select2-dropdown {
    border: 2px solid var(--gray-300, #e5e7eb) !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15) !important;
    max-height: 400px !important;
}

.select2-results {
    max-height: 350px !important;
    overflow-y: auto !important;
}

.select2-results__option {
    padding: 10px 12px !important;
    font-size: 14px !important;
}

.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: var(--primary-color, #14b8a6) !important;
    color: white !important;
}

.select2-container--default .select2-selection--single {
    height: 44px !important;
    border: 2px solid var(--gray-300, #e5e7eb) !important;
    border-radius: 8px !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 40px !important;
    padding-left: 12px !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 42px !important;
}

.prescription-form-container {
    max-width: 1200px;
    margin: 0 auto;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.info-item label {
    font-weight: 600;
    color: var(--gray-600);
    font-size: 0.9rem;
}

.info-item span {
    color: var(--gray-800);
    font-size: 1rem;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--gray-700);
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid var(--gray-300);
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(138, 43, 226, 0.1);
}

.form-row {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.form-row .form-group {
    flex: 1;
    min-width: 150px;
}

.remedy-card {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    border-left: 4px solid var(--success-color);
}

.remedy-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.remedy-number {
    font-weight: 600;
    color: var(--success-color);
    font-size: 1.1rem;
}

.remedy-body {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.empty-remedies {
    padding: 40px;
    color: var(--gray-500);
}

.empty-remedies i {
    font-size: 48px;
    opacity: 0.3;
    margin-bottom: 15px;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding: 20px;
    background: var(--gray-50);
    border-radius: 12px;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        flex-direction: column;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    /* AI Suggestions responsive */
    .ai-dual-columns {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }
    
    .ai-suggestions-list > div {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }
    
    .ai-suggestions-list h4 {
        flex-wrap: wrap;
    }
    
    .remedy-header {
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .prescription-form-container {
        padding: 0 10px;
    }
    
    .card-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start !important;
    }
    
    .card-header h3 {
        font-size: 1rem;
    }
}
</style>

<script>
let remedyIndex = 0;

// Select2 configuration with search enabled
const select2Config = {
    width: '100%',
    placeholder: 'Type to search remedy...',
    allowClear: true,
    minimumResultsForSearch: -1,  // Always show search box
    templateResult: formatRemedyOption,
    templateSelection: formatRemedySelection
};

// Format option in dropdown with highlight
function formatRemedyOption(remedy) {
    if (!remedy.id) {
        return remedy.text;
    }
    return $('<span>' + remedy.text + '</span>');
}

// Format selected option
function formatRemedySelection(remedy) {
    return remedy.text || remedy.id;
}

// Add first remedy on page load and initialize Select2
document.addEventListener('DOMContentLoaded', function() {
    addRemedy();
    initSelect2();
});

function initSelect2() {
    setTimeout(function() {
        // Destroy existing Select2 instances first
        if ($('.remedy-select').hasClass('select2-hidden-accessible')) {
            $('.remedy-select').select2('destroy');
        }
        
        // Initialize Select2 with search enabled
        $('.remedy-select').select2(select2Config);
    }, 150);
}

function addRemedy(remedyData = null) {
    const container = document.getElementById('remedies-container');
    const template = document.getElementById('remedy-template');
    const emptyState = document.querySelector('.empty-remedies');
    
    if (emptyState) {
        emptyState.style.display = 'none';
    }
    
    let newRemedy = template.content.cloneNode(true);
    let html = newRemedy.querySelector('.remedy-card').outerHTML;
    html = html.replace(/INDEX/g, remedyIndex);
    html = html.replace('<span class="number">1</span>', '<span class="number">' + (remedyIndex + 1) + '</span>');
    
    container.insertAdjacentHTML('beforeend', html);
    remedyIndex++;
    
    // Initialize Select2 for the new remedy select
    setTimeout(function() {
        const newSelect = $('#remedy-' + (remedyIndex - 1) + ' .remedy-select');
        if (newSelect.hasClass('select2-hidden-accessible')) {
            newSelect.select2('destroy');
        }
        newSelect.select2(select2Config);
    }, 150);
    
    // If remedy data is provided, set the values
    if (remedyData) {
        const lastRemedyCard = container.lastElementChild;
        const remedyIdSelect = lastRemedyCard.querySelector('select[name*="[remedy_id]"]');
        const potencySelect = lastRemedyCard.querySelector('select[name*="[potency]"]');
        
        if (remedyIdSelect) {
            remedyIdSelect.value = remedyData.remedy_id;
            $(remedyIdSelect).trigger('change');
        }
        
        if (potencySelect) {
            potencySelect.value = remedyData.potency;
        }
    }
}

function removeRemedy(index) {
    const remedyCard = document.getElementById('remedy-' + index);
    if (remedyCard) {
        remedyCard.remove();
        
        // Show empty state if no remedies left
        const remainingRemedies = document.querySelectorAll('.remedy-card');
        if (remainingRemedies.length === 0) {
            const emptyState = document.querySelector('.empty-remedies');
            if (emptyState) {
                emptyState.style.display = 'block';
            }
        }
        
        // Renumber remaining remedies
        updateRemedyNumbers();
    }
}

function updateRemedyNumbers() {
    const remedies = document.querySelectorAll('.remedy-card');
    remedies.forEach((remedy, index) => {
        const numberSpan = remedy.querySelector('.remedy-number .number');
        if (numberSpan) {
            numberSpan.textContent = index + 1;
        } else {
            remedy.querySelector('.remedy-number').innerHTML = '#' + (index + 1);
        }
    });
}

// AI Remedy Suggestions - Dual RAG + Gemini
function fetchAISuggestions() {
    const container = document.getElementById('ai-remedy-suggestions');
    const loadingState = container.querySelector('.loading-state');
    const suggestionsList = container.querySelector('.ai-suggestions-list');
    
    // Show enhanced AI loading state
    loadingState.style.display = 'block';
    loadingState.innerHTML = `
        <div class="ai-brain-loader">
            <i class="fas fa-brain brain-icon"></i>
            <div class="loader-title">Analyzing Patient Case</div>
            <div class="loader-subtitle">Consulting AI models for remedy suggestions...</div>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
        </div>
    `;
    suggestionsList.innerHTML = '';
    
    // Use dual AI suggestions API
    fetch('<?php echo APP_URL; ?>/api/get_dual_ai_suggestions.php?consultation_id=<?php echo $consultationId; ?>')
        .then(res => res.json())
        .then(data => {
            container.querySelector('.loading-state').style.display = 'none';
            
            if (!data.success) {
                container.querySelector('.ai-suggestions-list').innerHTML = `<div class="alert alert-danger">${data.error || 'Failed to fetch suggestions'}</div>`;
                return;
            }
            
            let html = '<div class="ai-dual-columns" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
            
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
                        ${remedy.reasoning ? `<p style="font-size: 12px; color: #555; margin: 8px 0 5px 28px; line-height: 1.4;">${remedy.reasoning.substring(0, 120)}${remedy.reasoning.length > 120 ? '...' : ''}</p>` : ''}
                        <div style="margin-top: 8px; margin-left: 28px;">
                            <button type="button" class="btn btn-sm btn-success" onclick="addAISuggestedRemedy('${remedy.name}', '${remedy.potency || '30C'}')">
                                <i class="fas fa-plus"></i> Add to Prescription
                            </button>
                        </div>
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
                        ${remedy.reasoning ? `<p style="font-size: 12px; color: #555; margin: 8px 0 5px 28px; line-height: 1.4;">${remedy.reasoning.substring(0, 120)}${remedy.reasoning.length > 120 ? '...' : ''}</p>` : ''}
                        ${remedy.reference ? `<small style="color: #888; margin-left: 28px; font-style: italic;">${remedy.reference}</small>` : ''}
                        <div style="margin-top: 8px; margin-left: 28px;">
                            <button type="button" class="btn btn-sm btn-info" onclick="addAISuggestedRemedy('${remedy.name}', '${remedy.potency || '30C'}')">
                                <i class="fas fa-plus"></i> Add to Prescription
                            </button>
                        </div>
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
                // Check if it's an overload error and show a friendlier message with retry
                const isOverloaded = data.gemini.error.toLowerCase().includes('overload');
                if (isOverloaded) {
                    html += `
                    <div style="text-align: center; padding: 20px;">
                        <i class="fas fa-server" style="font-size: 32px; color: #8e44ad; opacity: 0.5; margin-bottom: 10px;"></i>
                        <p style="color: #4a235a; font-weight: 500; margin-bottom: 5px;">Gemini AI is temporarily busy</p>
                        <p style="color: #666; font-size: 12px; margin-bottom: 15px;">The AI model is experiencing high demand. Please try again in a moment.</p>
                        <button type="button" onclick="fetchAISuggestions()" class="btn btn-sm" style="background: #8e44ad; color: white; border: none;">
                            <i class="fas fa-redo"></i> Retry
                        </button>
                    </div>`;
                } else {
                    html += `<p style="color: #666; font-style: italic; padding: 10px;"><i class="fas fa-exclamation-circle" style="margin-right: 5px;"></i>${data.gemini.error}</p>`;
                }
            } else {
                html += '<p style="color: #666; font-style: italic; padding: 10px;">No Gemini suggestions available</p>';
            }
            html += '</div>';
            
            html += '</div>'; // Close grid
            
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
            
            container.querySelector('.ai-suggestions-list').innerHTML = html;
        })
        .catch(err => {
            container.querySelector('.loading-state').style.display = 'none';
            container.querySelector('.ai-suggestions-list').innerHTML = `<div class="alert alert-danger">Failed to fetch AI suggestions: ${err.message}</div>`;
        });
}

function addAISuggestedRemedy(remedyName, potency) {
    // Find remedy ID by name
    let remedyId = null;
    <?php foreach ($remedies as $remedy): ?>
    if (remedyName === <?php echo json_encode($remedy['remedy_name']); ?>) remedyId = <?php echo $remedy['id']; ?>;
    <?php endforeach; ?>
    if (!remedyId) {
        alert('Remedy not found in database.');
        return;
    }
    // Add remedy to form
    addRemedy({ remedy_id: remedyId, potency: potency });
}

// Fetch AI suggestions on page load
fetchAISuggestions();

// Form validation
document.getElementById('prescriptionForm').addEventListener('submit', function(e) {
    const remedySelects = document.querySelectorAll('.remedy-select');
    let hasRemedy = false;
    
    remedySelects.forEach(select => {
        if (select.value) {
            hasRemedy = true;
        }
    });
    
    if (!hasRemedy) {
        e.preventDefault();
        alert('Please add at least one remedy');
        return false;
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
