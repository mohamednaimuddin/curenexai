<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();
$patientId = $_GET['patient_id'] ?? null;
$patient = null;
$error = '';
$success = '';

// Fetch patient details if patient_id is provided
if ($patientId) {
    $patient = DB::queryOne(
        "SELECT * FROM patients WHERE id = ? AND doctor_id = ?",
        [$patientId, $doctorId]
    );
    
    if (!$patient) {
        setFlash('danger', 'Patient not found');
        redirect('/patients/list.php');
    }
}

// Fetch all patients for dropdown
$allPatients = DB::query(
    "SELECT id, patient_name, age, gender FROM patients WHERE doctor_id = ? ORDER BY patient_name ASC",
    [$doctorId]
);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check maintenance mode first
    if (blockIfMaintenance()) {
        header('Location: ' . APP_URL . '/consultations/list.php');
        exit;
    }
    
    $selectedPatientId = sanitize($_POST['patient_id'] ?? '');
    $chiefComplaint = sanitize($_POST['chief_complaint'] ?? '');
    $presentIllness = sanitize($_POST['present_illness'] ?? '');
    $pastHistory = sanitize($_POST['past_history'] ?? '');
    $physicalExamination = sanitize($_POST['physical_examination'] ?? '');
    $mentalState = sanitize($_POST['mental_state'] ?? '');
    $generalSymptoms = sanitize($_POST['general_symptoms'] ?? '');
    $particularSymptoms = sanitize($_POST['particular_symptoms'] ?? '');
    $modalities = sanitize($_POST['modalities'] ?? '');
    $causation = sanitize($_POST['causation'] ?? '');
    $thermalState = sanitize($_POST['thermal_state'] ?? '');
    $thirst = sanitize($_POST['thirst'] ?? '');
    $appetite = sanitize($_POST['appetite'] ?? '');
    $sleepPattern = sanitize($_POST['sleep_pattern'] ?? '');
    $dreams = sanitize($_POST['dreams'] ?? '');
    $diagnosis = sanitize($_POST['diagnosis'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $followUpDate = sanitize($_POST['follow_up_date'] ?? '');
    
    // Symptoms array
    $symptoms = $_POST['symptoms'] ?? [];
    
    // Validation
    if (empty($selectedPatientId)) {
        $error = 'Please select a patient';
    } elseif (empty($chiefComplaint)) {
        $error = 'Chief complaint is required';
    } else {
        try {
            DB::beginTransaction();
            
            // Insert consultation
            $consultationData = [
                'patient_id' => $selectedPatientId,
                'doctor_id' => $doctorId,
                'chief_complaint' => $chiefComplaint,
                'present_illness' => $presentIllness,
                'past_history' => $pastHistory,
                'physical_examination' => $physicalExamination,
                'mental_state' => $mentalState,
                'general_symptoms' => $generalSymptoms,
                'particular_symptoms' => $particularSymptoms,
                'modalities' => $modalities,
                'causation' => $causation,
                'thermal_state' => !empty($thermalState) ? $thermalState : null,
                'thirst' => !empty($thirst) ? $thirst : null,
                'appetite' => !empty($appetite) ? $appetite : null,
                'sleep_pattern' => $sleepPattern,
                'dreams' => $dreams,
                'diagnosis' => $diagnosis,
                'notes' => $notes,
                'status' => 'active',
                'follow_up_date' => !empty($followUpDate) ? $followUpDate : null
            ];
            
            $consultationId = DB::insert('consultations', $consultationData);
            
            if (!$consultationId) {
                throw new Exception('Failed to create consultation');
            }
            
            // Insert symptoms
            if (!empty($symptoms)) {
                foreach ($symptoms as $symptom) {
                    if (!empty($symptom['symptom_text'])) {
                        $symptomData = [
                            'consultation_id' => $consultationId,
                            'symptom_text' => sanitize($symptom['symptom_text']),
                            'location' => sanitize($symptom['location'] ?? ''),
                            'sensation' => sanitize($symptom['sensation'] ?? ''),
                            'modality' => sanitize($symptom['modality'] ?? ''),
                            'intensity' => sanitize($symptom['intensity'] ?? 'moderate'),
                            'duration' => sanitize($symptom['duration'] ?? ''),
                            'category' => sanitize($symptom['category'] ?? 'general')
                        ];
                        
                        DB::insert('symptoms', $symptomData);
                    }
                }
            }
            
            DB::commit();
            
            logActivity('consultation_created', "Created consultation for patient ID: {$selectedPatientId}");
            setFlash('success', 'Consultation created successfully!');
            redirect('/consultations/view.php?id=' . $consultationId);
            
        } catch (Exception $e) {
            DB::rollback();
            $error = 'Failed to create consultation: ' . $e->getMessage();
            error_log($error);
        }
    }
}

$pageTitle = 'New Consultation';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="consultation-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-stethoscope"></i> New Consultation</h1>
            <p class="text-muted">Record patient case history and symptoms</p>
        </div>
        <div class="header-actions">
            <a href="<?php echo APP_URL; ?>/consultations/list.php" class="btn btn-outline">
                <i class="fas fa-list"></i> View All Consultations
            </a>
        </div>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <!-- Consultation Form -->
    <form method="POST" action="add.php<?php echo $patientId ? '?patient_id=' . $patientId : ''; ?>" id="consultationForm">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <!-- Patient Selection -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Patient Information</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label for="patient_id">Select Patient *</label>
                        <select name="patient_id" id="patient_id" class="form-control" required <?php echo $patient ? 'disabled' : ''; ?>>
                            <option value="">-- Select Patient --</option>
                            <?php foreach ($allPatients as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo ($patient && $p['id'] == $patient['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['patient_name']); ?> - 
                                    <?php echo $p['age']; ?> years / <?php echo ucfirst($p['gender']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($patient): ?>
                            <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="consultation_date">Consultation Date</label>
                        <input 
                            type="date" 
                            id="consultation_date" 
                            class="form-control" 
                            value="<?php echo date('Y-m-d'); ?>"
                            readonly
                        >
                        <small class="text-muted">Automatically set to today</small>
                    </div>
                </div>
                
                <?php if ($patient): ?>
                    <div class="patient-info-card">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['patient_name']); ?></p>
                        <p><strong>Age/Gender:</strong> <?php echo $patient['age']; ?> years / <?php echo ucfirst($patient['gender']); ?></p>
                        <?php if (!empty($patient['medical_history'])): ?>
                            <p><strong>Medical History:</strong> <?php echo truncate(htmlspecialchars($patient['medical_history']), 100); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($patient['allergies'])): ?>
                            <p class="text-danger"><strong><i class="fas fa-exclamation-triangle"></i> Allergies:</strong> <?php echo htmlspecialchars($patient['allergies']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Chief Complaint & Present Illness -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-notes-medical"></i> Chief Complaint & Present Illness</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="chief_complaint">Chief Complaint *</label>
                    <textarea 
                        name="chief_complaint" 
                        id="chief_complaint" 
                        class="form-control" 
                        rows="3"
                        placeholder="Main presenting complaint..."
                        required
                    ><?php echo htmlspecialchars($_POST['chief_complaint'] ?? ''); ?></textarea>
                    <small class="text-muted">The main reason for consultation</small>
                </div>
                
                <div class="form-group">
                    <label for="present_illness">History of Present Illness</label>
                    <textarea 
                        name="present_illness" 
                        id="present_illness" 
                        class="form-control" 
                        rows="4"
                        placeholder="Duration, onset, progression, associated symptoms..."
                    ><?php echo htmlspecialchars($_POST['present_illness'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="causation">Causation / Exciting Factor</label>
                    <textarea 
                        name="causation" 
                        id="causation" 
                        class="form-control" 
                        rows="2"
                        placeholder="What triggered this condition? (e.g., grief, cold exposure, injury)"
                    ><?php echo htmlspecialchars($_POST['causation'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Symptoms Section -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-thermometer"></i> Symptoms</h3>
                <button type="button" class="btn btn-sm btn-primary" onclick="addSymptom()">
                    <i class="fas fa-plus"></i> Add Symptom
                </button>
            </div>
            <div class="card-body">
                <div id="symptomsContainer">
                    <!-- Symptoms will be added here dynamically -->
                    <p class="text-muted">Click "Add Symptom" to record individual symptoms with details</p>
                </div>
            </div>
        </div>
        
        <!-- General & Particular Symptoms -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-clipboard-list"></i> General & Particular Symptoms</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="general_symptoms">General Symptoms</label>
                        <textarea 
                            name="general_symptoms" 
                            id="general_symptoms" 
                            class="form-control" 
                            rows="4"
                            placeholder="Overall symptoms affecting the whole body..."
                        ><?php echo htmlspecialchars($_POST['general_symptoms'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="particular_symptoms">Particular Symptoms</label>
                        <textarea 
                            name="particular_symptoms" 
                            id="particular_symptoms" 
                            class="form-control" 
                            rows="4"
                            placeholder="Symptoms specific to certain parts or organs..."
                        ><?php echo htmlspecialchars($_POST['particular_symptoms'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modalities">Modalities (Aggravation & Amelioration)</label>
                    <textarea 
                        name="modalities" 
                        id="modalities" 
                        class="form-control" 
                        rows="3"
                        placeholder="What makes symptoms better or worse? (time, weather, position, etc.)"
                    ><?php echo htmlspecialchars($_POST['modalities'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Physical & Mental State -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-brain"></i> Physical & Mental State</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="physical_examination">Physical Examination</label>
                    <textarea 
                        name="physical_examination" 
                        id="physical_examination" 
                        class="form-control" 
                        rows="3"
                        placeholder="Vital signs, physical findings..."
                    ><?php echo htmlspecialchars($_POST['physical_examination'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="mental_state">Mental & Emotional State</label>
                    <textarea 
                        name="mental_state" 
                        id="mental_state" 
                        class="form-control" 
                        rows="4"
                        placeholder="Mood, emotions, fears, anxieties, mental symptoms..."
                    ><?php echo htmlspecialchars($_POST['mental_state'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Constitutional Characteristics -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-temperature-high"></i> Constitutional Characteristics</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="thermal_state">Thermal State</label>
                        <select name="thermal_state" id="thermal_state" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="chilly">Chilly (Sensitive to cold)</option>
                            <option value="hot">Hot (Sensitive to heat)</option>
                            <option value="ambithermal">Ambithermal (Both)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="thirst">Thirst</label>
                        <select name="thirst" id="thirst" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="thirstless">Thirstless</option>
                            <option value="moderate">Moderate thirst</option>
                            <option value="excessive">Excessive thirst</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="appetite">Appetite</label>
                        <select name="appetite" id="appetite" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="poor">Poor</option>
                            <option value="normal">Normal</option>
                            <option value="increased">Increased</option>
                            <option value="decreased">Decreased</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="sleep_pattern">Sleep Pattern</label>
                        <textarea 
                            name="sleep_pattern" 
                            id="sleep_pattern" 
                            class="form-control" 
                            rows="2"
                            placeholder="Quality, duration, difficulty falling asleep, waking times..."
                        ><?php echo htmlspecialchars($_POST['sleep_pattern'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="dreams">Dreams</label>
                        <textarea 
                            name="dreams" 
                            id="dreams" 
                            class="form-control" 
                            rows="2"
                            placeholder="Recurring themes, nightmares, memorable dreams..."
                        ><?php echo htmlspecialchars($_POST['dreams'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Past History -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Past Medical History</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="past_history">Past History</label>
                    <textarea 
                        name="past_history" 
                        id="past_history" 
                        class="form-control" 
                        rows="4"
                        placeholder="Previous illnesses, surgeries, treatments, medications..."
                    ><?php echo htmlspecialchars($_POST['past_history'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- AI Diagnosis Suggestions (RAG) -->
        <div class="dashboard-card" id="aiDiagnosisCard">
            <div class="card-header">
                <h3><i class="fas fa-robot"></i> AI Diagnosis Suggestions</h3>
                <button type="button" class="btn btn-sm btn-primary" id="getDiagnosisBtn" onclick="getAIDiagnosis()">
                    <i class="fas fa-search-plus"></i> Analyze Symptoms
                </button>
            </div>
            <div class="card-body">
                <div id="diagnosisSuggestionsContainer">
                    <p class="text-muted"><i class="fas fa-info-circle"></i> Fill in symptoms and chief complaint, then click "Analyze Symptoms" to get AI-powered diagnosis suggestions based on your local database.</p>
                </div>
            </div>
        </div>

        <!-- Diagnosis & Notes -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-diagnoses"></i> Assessment & Plan</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="diagnosis">Diagnosis / Assessment</label>
                    <textarea 
                        name="diagnosis" 
                        id="diagnosis" 
                        class="form-control" 
                        rows="3"
                        placeholder="Your clinical diagnosis or assessment..."
                    ><?php echo htmlspecialchars($_POST['diagnosis'] ?? ''); ?></textarea>
                    <small class="text-muted">Click a suggested diagnosis above to auto-fill</small>
                </div>
                
                <div class="form-group">
                    <label for="notes">Additional Notes</label>
                    <textarea 
                        name="notes" 
                        id="notes" 
                        class="form-control" 
                        rows="3"
                        placeholder="Any additional observations, instructions, or notes..."
                    ><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="follow_up_date">Follow-up Date (Optional)</label>
                    <input 
                        type="date" 
                        name="follow_up_date" 
                        id="follow_up_date" 
                        class="form-control"
                        min="<?php echo date('Y-m-d'); ?>"
                        value="<?php echo htmlspecialchars($_POST['follow_up_date'] ?? ''); ?>"
                    >
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> Save Consultation
            </button>
            <a href="<?php echo APP_URL; ?>/consultations/list.php" class="btn btn-outline btn-lg">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<!-- Symptom Template (Hidden) -->
<template id="symptomTemplate">
    <div class="symptom-item">
        <div class="symptom-header">
            <span class="symptom-number">Symptom #<span class="number"></span></span>
            <button type="button" class="btn-remove" onclick="removeSymptom(this)">
                <i class="fas fa-times"></i> Remove
            </button>
        </div>
        <div class="symptom-body">
            <div class="form-row">
                <div class="form-group" style="flex: 2; position: relative;">
                    <label>Symptom Description * <small style="color: #667eea;"><i class="fas fa-magic"></i> Type naturally - Smart matching enabled</small></label>
                    <input 
                        type="text" 
                        name="symptoms[INDEX][symptom_text]" 
                        class="form-control symptom-input-smart" 
                        placeholder="e.g., 'patient gets angry when contradicted' or 'blue lips when angry'"
                        required
                        autocomplete="off"
                    >
                    <div class="rubric-suggestions-dropdown" style="display: none;"></div>
                    <input type="hidden" name="symptoms[INDEX][matched_rubric_id]" class="matched-rubric-id">
                </div>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select name="symptoms[INDEX][category]" class="form-control">
                        <?php foreach (SYMPTOM_CATEGORIES as $key => $label): ?>
                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Location</label>
                    <input 
                        type="text" 
                        name="symptoms[INDEX][location]" 
                        class="form-control" 
                        placeholder="e.g., Left side, forehead"
                    >
                </div>
                <div class="form-group">
                    <label>Sensation</label>
                    <input 
                        type="text" 
                        name="symptoms[INDEX][sensation]" 
                        class="form-control" 
                        placeholder="e.g., Throbbing, burning"
                    >
                </div>
                <div class="form-group">
                    <label>Intensity</label>
                    <select name="symptoms[INDEX][intensity]" class="form-control">
                        <option value="mild">Mild</option>
                        <option value="moderate" selected>Moderate</option>
                        <option value="severe">Severe</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Modality / Aggravation</label>
                    <input 
                        type="text" 
                        name="symptoms[INDEX][modality]" 
                        class="form-control" 
                        placeholder="e.g., Worse in morning, better lying down"
                    >
                </div>
                <div class="form-group">
                    <label>Duration</label>
                    <input 
                        type="text" 
                        name="symptoms[INDEX][duration]" 
                        class="form-control" 
                        placeholder="e.g., 2 weeks, 3 days"
                    >
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.consultation-container {
    max-width: 1200px;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 0;
}

.form-row .form-group {
    flex: 1;
}

.patient-info-card {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
    display: flex;
    gap: 30px;
}

.patient-info-card p {
    margin: 0;
}

#symptomsContainer {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.symptom-item {
    background: #f8f9fa;
    border: 2px solid #e0e0e0;
    border-radius: 10px;
    overflow: hidden;
}

.symptom-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.symptom-number {
    font-weight: 600;
    font-size: 14px;
}

.btn-remove {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.3s;
}

.btn-remove:hover {
    background: rgba(255, 255, 255, 0.3);
}

.symptom-body {
    padding: 20px;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin: 30px 0;
}

.btn-lg {
    padding: 14px 40px;
    font-size: 16px;
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
    }
    
    .patient-info-card {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<script>
let symptomIndex = 0;

function addSymptom() {
    const container = document.getElementById('symptomsContainer');
    const template = document.getElementById('symptomTemplate');
    const clone = template.content.cloneNode(true);
    
    // Update index in all name attributes
    const html = clone.querySelector('.symptom-item').outerHTML;
    const updatedHtml = html.replace(/INDEX/g, symptomIndex);
    
    // Create temp div to hold the HTML
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = updatedHtml;
    
    // Update symptom number
    tempDiv.querySelector('.symptom-number .number').textContent = symptomIndex + 1;
    
    // Clear the "no symptoms" message if present
    if (container.querySelector('.text-muted')) {
        container.innerHTML = '';
    }
    
    // Append to container
    container.appendChild(tempDiv.firstElementChild);
    
    symptomIndex++;
}

function removeSymptom(button) {
    const symptomItem = button.closest('.symptom-item');
    symptomItem.remove();
    
    // If no symptoms left, show message
    const container = document.getElementById('symptomsContainer');
    if (container.children.length === 0) {
        container.innerHTML = '<p class="text-muted">Click "Add Symptom" to record individual symptoms with details</p>';
    } else {
        // Renumber remaining symptoms
        const symptoms = container.querySelectorAll('.symptom-item');
        symptoms.forEach((symptom, index) => {
            symptom.querySelector('.symptom-number .number').textContent = index + 1;
        });
    }
}

// Add first symptom automatically
document.addEventListener('DOMContentLoaded', function() {
    // You can uncomment this to add a symptom by default
    // addSymptom();
});

// Form validation
document.getElementById('consultationForm').addEventListener('submit', function(e) {
    const patientId = document.getElementById('patient_id').value;
    const chiefComplaint = document.getElementById('chief_complaint').value.trim();
    
    if (!patientId) {
        e.preventDefault();
        alert('Please select a patient');
        return false;
    }
    
    if (!chiefComplaint) {
        e.preventDefault();
        alert('Chief complaint is required');
        return false;
    }
});

// AI Diagnosis Function (RAG-based)
async function getAIDiagnosis() {
    const chiefComplaint = document.getElementById('chief_complaint').value.trim();
    const presentIllness = document.getElementById('present_illness').value.trim();
    const generalSymptoms = document.getElementById('general_symptoms').value.trim();
    const particularSymptoms = document.getElementById('particular_symptoms').value.trim();
    const physicalExam = document.getElementById('physical_examination').value.trim();
    
    // Collect individual symptoms
    const symptomInputs = document.querySelectorAll('input[name*="[symptom_text]"]');
    let symptoms = [];
    symptomInputs.forEach(input => {
        if (input.value.trim()) symptoms.push(input.value.trim());
    });
    
    // Combine all symptom data
    const allSymptoms = [
        ...symptoms,
        generalSymptoms,
        particularSymptoms
    ].filter(s => s).join(', ');
    
    if (!chiefComplaint && !allSymptoms) {
        alert('Please enter chief complaint or symptoms first');
        return;
    }
    
    const container = document.getElementById('diagnosisSuggestionsContainer');
    const btn = document.getElementById('getDiagnosisBtn');
    
    // Show loading
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
    container.innerHTML = `
        <div class="diagnosis-loading">
            <div class="spinner"></div>
            <p>Searching database for matching conditions...</p>
        </div>
    `;
    
    try {
        const response = await fetch('<?php echo APP_URL; ?>/api/get_disease_diagnosis.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                symptoms: allSymptoms,
                chief_complaint: chiefComplaint + ' ' + presentIllness,
                physical_exam: physicalExam
            })
        });
        
        const data = await response.json();
        
        if (data.success && data.diagnoses && data.diagnoses.length > 0) {
            let html = '<div class="diagnosis-suggestions">';
            
            data.diagnoses.forEach((d, index) => {
                const confidenceClass = d.confidence.toLowerCase();
                html += `
                    <div class="diagnosis-suggestion-item" onclick="selectDiagnosis('${escapeHtml(d.diagnosis)}', '${escapeHtml(d.notes_for_doctor || '')}')">
                        <div class="suggestion-header">
                            <span class="suggestion-name">${d.diagnosis}</span>
                            <span class="confidence-badge confidence-${confidenceClass}">${d.confidence}</span>
                        </div>
                        <div class="suggestion-symptoms">
                            <strong>Matched:</strong> ${d.matching_symptoms.join(', ') || 'Pattern match'}
                        </div>
                        ${d.supporting_findings ? `<div class="suggestion-findings"><strong>Findings:</strong> ${d.supporting_findings}</div>` : ''}
                        ${d.notes_for_doctor ? `<div class="suggestion-notes"><i class="fas fa-exclamation-triangle"></i> ${d.notes_for_doctor}</div>` : ''}
                    </div>
                `;
            });
            
            html += '</div>';
            html += '<p class="text-muted mt-2"><small><i class="fas fa-mouse-pointer"></i> Click a suggestion to auto-fill diagnosis field</small></p>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `
                <div class="no-diagnosis-results">
                    <i class="fas fa-search"></i>
                    <p>No matching conditions found in database</p>
                    <small>Try adding more detailed symptoms</small>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error:', error);
        container.innerHTML = `
            <div class="diagnosis-error">
                <i class="fas fa-exclamation-triangle"></i>
                <p>Error fetching diagnosis suggestions</p>
                <small>${error.message}</small>
            </div>
        `;
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-search-plus"></i> Analyze Symptoms';
    }
}

function selectDiagnosis(diagnosis, notes) {
    const diagnosisField = document.getElementById('diagnosis');
    const notesField = document.getElementById('notes');
    
    diagnosisField.value = diagnosis;
    if (notes && notesField.value === '') {
        notesField.value = notes;
    }
    
    // Scroll to diagnosis field
    diagnosisField.scrollIntoView({ behavior: 'smooth', block: 'center' });
    diagnosisField.focus();
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML.replace(/'/g, "\\'").replace(/"/g, '\\"');
}

// Smart Rubric Search for Symptom Inputs
let rubricSearchTimeout = null;

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('symptom-input-smart')) {
        clearTimeout(rubricSearchTimeout);
        const input = e.target;
        const dropdown = input.parentElement.querySelector('.rubric-suggestions-dropdown');
        const symptomText = input.value.trim();
        
        if (symptomText.length < 3) {
            dropdown.style.display = 'none';
            return;
        }
        
        // Show loading after a brief delay
        rubricSearchTimeout = setTimeout(async function() {
            dropdown.innerHTML = `
                <div class="rubric-suggestions-loading">
                    <i class="fas fa-search fa-spin"></i>
                    <p>Searching rubrics...</p>
                </div>
            `;
            dropdown.style.display = 'block';
            
            try {
                const formData = new FormData();
                formData.append('symptom', symptomText);
                
                // Get category if selected
                const categorySelect = input.closest('.symptom-item')?.querySelector('select[name*="[category]"]');
                if (categorySelect && categorySelect.value) {
                    formData.append('category', categorySelect.value);
                }
                
                const response = await fetch('<?php echo APP_URL; ?>/api/get_rubric_suggestions.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success && data.rubrics && data.rubrics.length > 0) {
                    let html = `<div style="padding: 8px 15px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-bottom: 1px solid #ddd; font-size: 12px; color: #0369a1;">
                        <i class="fas fa-magic"></i> Found ${data.rubrics.length} matching rubrics ${data.ai_enhanced ? '(AI-enhanced)' : ''}
                    </div>`;
                    
                    data.rubrics.slice(0, 10).forEach(rubric => {
                        const sourceBadge = rubric.source === 'ai' 
                            ? '<span class="rubric-meta-badge rubric-meta-ai"><i class="fas fa-robot"></i> AI</span>' 
                            : '';
                        
                        html += `
                            <div class="rubric-suggestion-item" data-rubric-id="${rubric.id}" data-rubric-text="${escapeHtmlForAttr(rubric.rubric)}">
                                <div class="rubric-suggestion-name">${escapeHtmlDisplay(rubric.rubric)}</div>
                                <div class="rubric-suggestion-complete">${escapeHtmlDisplay(rubric.complete_rubric || '')}</div>
                                <div class="rubric-suggestion-meta">
                                    <span class="rubric-meta-badge rubric-meta-category">${escapeHtmlDisplay(rubric.category)}</span>
                                    <span class="rubric-meta-badge rubric-meta-remedies">${rubric.remedy_count || 0} remedies</span>
                                    ${sourceBadge}
                                </div>
                            </div>
                        `;
                    });
                    
                    dropdown.innerHTML = html;
                } else {
                    dropdown.innerHTML = `
                        <div style="padding: 20px; text-align: center; color: #64748b;">
                            <i class="fas fa-info-circle" style="font-size: 1.5rem; margin-bottom: 8px; display: block; opacity: 0.5;"></i>
                            <p style="margin: 0;">No matching rubrics found</p>
                            <small>Your symptom will be saved as entered</small>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Rubric search error:', error);
                dropdown.style.display = 'none';
            }
        }, 400);
    }
});

// Handle clicking on a rubric suggestion
document.addEventListener('click', function(e) {
    const suggestionItem = e.target.closest('.rubric-suggestion-item');
    if (suggestionItem) {
        const input = suggestionItem.closest('.form-group').querySelector('.symptom-input-smart');
        const hiddenInput = suggestionItem.closest('.form-group').querySelector('.matched-rubric-id');
        const dropdown = suggestionItem.closest('.rubric-suggestions-dropdown');
        
        // Set the rubric text as the symptom
        const rubricText = suggestionItem.dataset.rubricText;
        const rubricId = suggestionItem.dataset.rubricId;
        
        input.value = rubricText;
        input.classList.add('has-match');
        if (hiddenInput) {
            hiddenInput.value = rubricId;
        }
        
        dropdown.style.display = 'none';
    }
});

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.symptom-input-smart') && !e.target.closest('.rubric-suggestions-dropdown')) {
        document.querySelectorAll('.rubric-suggestions-dropdown').forEach(dropdown => {
            dropdown.style.display = 'none';
        });
    }
});

// Close dropdown on Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.rubric-suggestions-dropdown').forEach(dropdown => {
            dropdown.style.display = 'none';
        });
    }
});

function escapeHtmlForAttr(text) {
    if (!text) return '';
    return text.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function escapeHtmlDisplay(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<style>
/* AI Diagnosis Styles */
#aiDiagnosisCard {
    border: 2px solid #e0f2fe;
    background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%);
}

#aiDiagnosisCard .card-header {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
    color: white;
}

#aiDiagnosisCard .card-header h3 {
    color: white;
}

.diagnosis-loading {
    text-align: center;
    padding: 30px;
}

.diagnosis-loading .spinner {
    width: 36px;
    height: 36px;
    border: 3px solid #e5e7eb;
    border-top-color: #0ea5e9;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 12px;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.diagnosis-suggestions {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.diagnosis-suggestion-item {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 14px 16px;
    cursor: pointer;
    transition: all 0.2s;
}

.diagnosis-suggestion-item:hover {
    border-color: #0ea5e9;
    box-shadow: 0 2px 8px rgba(14, 165, 233, 0.15);
    transform: translateY(-1px);
}

.suggestion-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.suggestion-name {
    font-weight: 600;
    font-size: 15px;
    color: #1e293b;
}

.confidence-badge {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.confidence-high {
    background: #dcfce7;
    color: #166534;
}

.confidence-medium {
    background: #fef3c7;
    color: #92400e;
}

.confidence-low {
    background: #f3f4f6;
    color: #4b5563;
}

.suggestion-symptoms {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 6px;
}

.suggestion-findings {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 6px;
}

.suggestion-notes {
    font-size: 12px;
    color: #b45309;
    background: #fef3c7;
    padding: 6px 10px;
    border-radius: 6px;
    margin-top: 8px;
}

.no-diagnosis-results,
.diagnosis-error {
    text-align: center;
    padding: 30px;
    color: #64748b;
}

.no-diagnosis-results i,
.diagnosis-error i {
    font-size: 32px;
    color: #cbd5e1;
    margin-bottom: 10px;
}

.diagnosis-error i {
    color: #ef4444;
}

/* Smart Rubric Suggestions Styles */
.rubric-suggestions-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid var(--gray-300, #d1d5db);
    border-radius: 8px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    max-height: 350px;
    overflow-y: auto;
    z-index: 1000;
    margin-top: 5px;
}

.rubric-suggestion-item {
    padding: 12px 15px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.2s;
}

.rubric-suggestion-item:hover {
    background: #f0f9ff;
}

.rubric-suggestion-item:last-child {
    border-bottom: none;
}

.rubric-suggestion-name {
    font-weight: 600;
    color: #1e293b;
    font-size: 14px;
}

.rubric-suggestion-complete {
    font-size: 12px;
    color: #64748b;
    margin-top: 2px;
}

.rubric-suggestion-meta {
    display: flex;
    gap: 8px;
    margin-top: 5px;
    font-size: 11px;
}

.rubric-meta-badge {
    padding: 2px 8px;
    border-radius: 10px;
}

.rubric-meta-category {
    background: #e0f2fe;
    color: #0369a1;
}

.rubric-meta-remedies {
    background: #fef3c7;
    color: #92400e;
}

.rubric-meta-ai {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.rubric-suggestions-loading {
    padding: 20px;
    text-align: center;
    color: #64748b;
}

.rubric-suggestions-loading i {
    font-size: 1.5rem;
    color: #667eea;
    margin-bottom: 8px;
    display: block;
}

.symptom-input-smart {
    padding-right: 35px;
}

.symptom-input-smart.has-match {
    border-color: #22c55e;
    background: #f0fdf4;
}

.matched-rubric-indicator {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #22c55e;
    font-size: 1.2rem;
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
