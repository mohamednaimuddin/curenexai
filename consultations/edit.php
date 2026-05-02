<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();
$consultationId = $_GET['id'] ?? 0;

// Fetch consultation details
$sql = "SELECT c.*, p.patient_name, p.age, p.gender, p.medical_history, p.allergies
        FROM consultations c
        INNER JOIN patients p ON c.patient_id = p.id
        WHERE c.id = ? AND c.doctor_id = ?";

$consultation = DB::queryOne($sql, [$consultationId, $doctorId]);

if (!$consultation) {
    setFlash('error', 'Consultation not found');
    redirect('/consultations/list.php');
}

// Fetch existing symptoms
$existingSymptoms = DB::query(
    "SELECT * FROM symptoms WHERE consultation_id = ? ORDER BY id",
    [$consultationId]
);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check maintenance mode first
    if (blockIfMaintenance()) {
        header('Location: ' . APP_URL . '/consultations/list.php');
        exit;
    }
    
    $selectedPatientId = $consultation['patient_id'];
    $chiefComplaint = sanitize($_POST['chief_complaint'] ?? '');
    $presentIllness = sanitize($_POST['present_illness'] ?? '');
    $pastHistory = sanitize($_POST['past_history'] ?? '');
    $familyHistory = sanitize($_POST['family_history'] ?? '');
    $causation = sanitize($_POST['causation'] ?? '');
    $physicalExamination = sanitize($_POST['physical_examination'] ?? '');
    $mentalState = sanitize($_POST['mental_state'] ?? '');
    $generalSymptoms = sanitize($_POST['general_symptoms'] ?? '');
    $particularSymptoms = sanitize($_POST['particular_symptoms'] ?? '');
    $modalities = sanitize($_POST['modalities'] ?? '');
    $thermalState = sanitize($_POST['thermal_state'] ?? '');
    $thirst = sanitize($_POST['thirst'] ?? '');
    $appetite = sanitize($_POST['appetite'] ?? '');
    $sleepPattern = sanitize($_POST['sleep_pattern'] ?? '');
    $dreams = sanitize($_POST['dreams'] ?? '');
    $diagnosis = sanitize($_POST['diagnosis'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    $status = sanitize($_POST['status'] ?? 'active');
    $followUpDate = sanitize($_POST['follow_up_date'] ?? '');
    
    // Symptoms array
    $symptoms = $_POST['symptoms'] ?? [];
    
    // Validation
    if (empty($chiefComplaint)) {
        $error = 'Chief complaint is required';
    } else {
        try {
            DB::beginTransaction();
            
            // Update consultation
            // Note: family_history is stored in patients table, not consultations
            $consultationData = [
                'chief_complaint' => $chiefComplaint,
                'present_illness' => $presentIllness,
                'past_history' => $pastHistory,
                'causation' => $causation,
                'physical_examination' => $physicalExamination,
                'mental_state' => $mentalState,
                'general_symptoms' => $generalSymptoms,
                'particular_symptoms' => $particularSymptoms,
                'modalities' => $modalities,
                'thermal_state' => !empty($thermalState) ? $thermalState : null,
                'thirst' => !empty($thirst) ? $thirst : null,
                'appetite' => !empty($appetite) ? $appetite : null,
                'sleep_pattern' => $sleepPattern,
                'dreams' => $dreams,
                'diagnosis' => $diagnosis,
                'notes' => $notes,
                'status' => $status,
                'follow_up_date' => !empty($followUpDate) ? $followUpDate : null
            ];
            
            $updated = DB::update('consultations', $consultationData, 'id = ?', [$consultationId]);
            
            if ($updated === false) {
                throw new Exception('Failed to update consultation');
            }
            
            // Delete existing symptoms
            DB::delete('symptoms', 'consultation_id = ?', [$consultationId]);
            
            // Insert new symptoms
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
            
            logActivity('consultation_updated', "Updated consultation ID: {$consultationId}");
            setFlash('success', 'Consultation updated successfully!');
            redirect('/consultations/view.php?id=' . $consultationId);
            
        } catch (Exception $e) {
            DB::rollback();
            $error = 'Failed to update consultation: ' . $e->getMessage();
            error_log($error);
        }
    }
}

$pageTitle = 'Edit Consultation';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="consultation-form-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <a href="<?php echo APP_URL; ?>/consultations/view.php?id=<?php echo $consultation['id']; ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Consultation
            </a>
            <h1><i class="fas fa-edit"></i> Edit Consultation</h1>
            <p class="text-muted">Update consultation details and symptoms</p>
        </div>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <!-- Consultation Form -->
    <form method="POST" action="" id="consultationForm">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <!-- Patient Info -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Patient Information</h3>
            </div>
            <div class="card-body">
                <div class="patient-info-card">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($consultation['patient_name']); ?></p>
                    <p><strong>Age/Gender:</strong> <?php echo $consultation['age']; ?> years / <?php echo ucfirst($consultation['gender']); ?></p>
                    <?php if (!empty($consultation['medical_history'])): ?>
                        <p><strong>Medical History:</strong> <?php echo truncate(htmlspecialchars($consultation['medical_history']), 100); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($consultation['allergies'])): ?>
                        <p class="text-danger"><strong><i class="fas fa-exclamation-triangle"></i> Allergies:</strong> <?php echo htmlspecialchars($consultation['allergies']); ?></p>
                    <?php endif; ?>
                </div>
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
                        id="chief_complaint" 
                        name="chief_complaint" 
                        class="form-control" 
                        rows="3" 
                        placeholder="Main reason for consultation..."
                        required
                    ><?php echo htmlspecialchars($consultation['chief_complaint']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="present_illness">History of Present Illness</label>
                    <textarea 
                        id="present_illness" 
                        name="present_illness" 
                        class="form-control" 
                        rows="4" 
                        placeholder="Detailed description of current illness, onset, duration, progression..."
                    ><?php echo htmlspecialchars($consultation['present_illness'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Symptoms -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-list-ul"></i> Symptoms</h3>
                <button type="button" class="btn btn-sm btn-success" onclick="addSymptom()">
                    <i class="fas fa-plus"></i> Add Symptom
                </button>
            </div>
            <div class="card-body">
                <div id="symptoms-container">
                    <?php if (!empty($existingSymptoms)): ?>
                        <?php foreach ($existingSymptoms as $index => $symptom): ?>
                            <div class="symptom-card" id="symptom-<?php echo $index; ?>">
                                <div class="symptom-header">
                                    <span class="symptom-number">#<?php echo $index + 1; ?></span>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="removeSymptom(<?php echo $index; ?>)">
                                        <i class="fas fa-times"></i> Remove
                                    </button>
                                </div>
                                <div class="symptom-body">
                                    <div class="form-row">
                                        <div class="form-group" style="position: relative;">
                                            <label>Symptom Description * <small style="color: #667eea;"><i class="fas fa-magic"></i> Smart matching</small></label>
                                            <textarea 
                                                name="symptoms[<?php echo $index; ?>][symptom_text]" 
                                                class="form-control symptom-input-smart" 
                                                rows="2" 
                                                placeholder="Type naturally - e.g., 'gets angry when contradicted'"
                                                autocomplete="off"
                                            ><?php echo htmlspecialchars($symptom['symptom_text']); ?></textarea>
                                            <div class="rubric-suggestions-dropdown" style="display: none;"></div>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Location</label>
                                            <input 
                                                type="text" 
                                                name="symptoms[<?php echo $index; ?>][location]" 
                                                class="form-control" 
                                                placeholder="e.g., Right temple, Left knee"
                                                value="<?php echo htmlspecialchars($symptom['location'] ?? ''); ?>"
                                            >
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Sensation</label>
                                            <input 
                                                type="text" 
                                                name="symptoms[<?php echo $index; ?>][sensation]" 
                                                class="form-control" 
                                                placeholder="e.g., Throbbing, Burning, Sharp"
                                                value="<?php echo htmlspecialchars($symptom['sensation'] ?? ''); ?>"
                                            >
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Modality (Better/Worse)</label>
                                            <input 
                                                type="text" 
                                                name="symptoms[<?php echo $index; ?>][modality]" 
                                                class="form-control" 
                                                placeholder="e.g., Better by cold, Worse at night"
                                                value="<?php echo htmlspecialchars($symptom['modality'] ?? ''); ?>"
                                            >
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Duration</label>
                                            <input 
                                                type="text" 
                                                name="symptoms[<?php echo $index; ?>][duration]" 
                                                class="form-control" 
                                                placeholder="e.g., 2 weeks, Since morning"
                                                value="<?php echo htmlspecialchars($symptom['duration'] ?? ''); ?>"
                                            >
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Intensity</label>
                                            <select name="symptoms[<?php echo $index; ?>][intensity]" class="form-control">
                                                <option value="mild" <?php echo ($symptom['intensity'] ?? '') == 'mild' ? 'selected' : ''; ?>>Mild</option>
                                                <option value="moderate" <?php echo ($symptom['intensity'] ?? 'moderate') == 'moderate' ? 'selected' : ''; ?>>Moderate</option>
                                                <option value="severe" <?php echo ($symptom['intensity'] ?? '') == 'severe' ? 'selected' : ''; ?>>Severe</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label>Category</label>
                                            <select name="symptoms[<?php echo $index; ?>][category]" class="form-control">
                                                <?php foreach (SYMPTOM_CATEGORIES as $cat): ?>
                                                    <option value="<?php echo $cat; ?>" <?php echo ($symptom['category'] ?? '') == $cat ? 'selected' : ''; ?>>
                                                        <?php echo ucfirst($cat); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="empty-symptoms text-center" <?php echo !empty($existingSymptoms) ? 'style="display:none;"' : ''; ?>>
                    <i class="fas fa-list-ul"></i>
                    <p>No symptoms added yet</p>
                    <button type="button" class="btn btn-success" onclick="addSymptom()">
                        <i class="fas fa-plus"></i> Add First Symptom
                    </button>
                </div>
            </div>
        </div>
        
        <!-- General & Particular Symptoms -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-clipboard-list"></i> General & Particular Symptoms</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="general_symptoms">General Symptoms</label>
                    <textarea 
                        id="general_symptoms" 
                        name="general_symptoms" 
                        class="form-control" 
                        rows="3" 
                        placeholder="Overall symptoms affecting the whole body..."
                    ><?php echo htmlspecialchars($consultation['general_symptoms'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="particular_symptoms">Particular Symptoms</label>
                    <textarea 
                        id="particular_symptoms" 
                        name="particular_symptoms" 
                        class="form-control" 
                        rows="3" 
                        placeholder="Local or specific symptoms..."
                    ><?php echo htmlspecialchars($consultation['particular_symptoms'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="modalities">Modalities (Aggravation & Amelioration)</label>
                    <textarea 
                        id="modalities" 
                        name="modalities" 
                        class="form-control" 
                        rows="3" 
                        placeholder="What makes symptoms better or worse (e.g., time of day, temperature, position, motion, food)..."
                    ><?php echo htmlspecialchars($consultation['modalities'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Constitutional Characteristics -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-thermometer-half"></i> Constitutional Characteristics</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label for="thermal_state">Thermal State</label>
                        <select id="thermal_state" name="thermal_state" class="form-control">
                            <option value="">Select</option>
                            <option value="chilly" <?php echo ($consultation['thermal_state'] ?? '') == 'chilly' ? 'selected' : ''; ?>>Chilly</option>
                            <option value="hot" <?php echo ($consultation['thermal_state'] ?? '') == 'hot' ? 'selected' : ''; ?>>Hot</option>
                            <option value="ambithermal" <?php echo ($consultation['thermal_state'] ?? '') == 'ambithermal' ? 'selected' : ''; ?>>Ambithermal</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="thirst">Thirst</label>
                        <select id="thirst" name="thirst" class="form-control">
                            <option value="">Select</option>
                            <option value="thirstless" <?php echo ($consultation['thirst'] ?? '') == 'thirstless' ? 'selected' : ''; ?>>Thirstless</option>
                            <option value="moderate" <?php echo ($consultation['thirst'] ?? '') == 'moderate' ? 'selected' : ''; ?>>Moderate</option>
                            <option value="excessive" <?php echo ($consultation['thirst'] ?? '') == 'excessive' ? 'selected' : ''; ?>>Excessive</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="appetite">Appetite</label>
                        <select id="appetite" name="appetite" class="form-control">
                            <option value="">Select</option>
                            <option value="poor" <?php echo ($consultation['appetite'] ?? '') == 'poor' ? 'selected' : ''; ?>>Poor</option>
                            <option value="normal" <?php echo ($consultation['appetite'] ?? '') == 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="increased" <?php echo ($consultation['appetite'] ?? '') == 'increased' ? 'selected' : ''; ?>>Increased</option>
                            <option value="decreased" <?php echo ($consultation['appetite'] ?? '') == 'decreased' ? 'selected' : ''; ?>>Decreased</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="sleep_pattern">Sleep Pattern</label>
                        <textarea 
                            id="sleep_pattern" 
                            name="sleep_pattern" 
                            class="form-control" 
                            rows="2" 
                            placeholder="Sleep quality, duration, disturbances..."
                        ><?php echo htmlspecialchars($consultation['sleep_pattern'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="dreams">Dreams</label>
                        <textarea 
                            id="dreams" 
                            name="dreams" 
                            class="form-control" 
                            rows="2" 
                            placeholder="Recurring or significant dreams..."
                        ><?php echo htmlspecialchars($consultation['dreams'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Past History & Causation -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Past History & Causation</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="past_history">Past Medical History</label>
                    <textarea 
                        id="past_history" 
                        name="past_history" 
                        class="form-control" 
                        rows="3" 
                        placeholder="Previous illnesses, treatments..."
                    ><?php echo htmlspecialchars($consultation['past_history'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="family_history">Family History</label>
                    <textarea 
                        id="family_history" 
                        name="family_history" 
                        class="form-control" 
                        rows="3" 
                        placeholder="Hereditary conditions, family diseases..."
                    ><?php echo htmlspecialchars($consultation['family_history'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="causation">Causation / Exciting Cause</label>
                    <textarea 
                        id="causation" 
                        name="causation" 
                        class="form-control" 
                        rows="2" 
                        placeholder="What triggered or caused this condition..."
                    ><?php echo htmlspecialchars($consultation['causation'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Physical & Mental Examination -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-stethoscope"></i> Physical & Mental Examination</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="physical_examination">Physical Examination</label>
                    <textarea 
                        id="physical_examination" 
                        name="physical_examination" 
                        class="form-control" 
                        rows="4" 
                        placeholder="Physical examination findings..."
                    ><?php echo htmlspecialchars($consultation['physical_examination'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="mental_state">Mental & Emotional State</label>
                    <textarea 
                        id="mental_state" 
                        name="mental_state" 
                        class="form-control" 
                        rows="4" 
                        placeholder="Mental symptoms, emotional state, behavior..."
                    ><?php echo htmlspecialchars($consultation['mental_state'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Assessment & Plan -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-diagnoses"></i> Assessment & Treatment Plan</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label for="diagnosis">Diagnosis / Assessment</label>
                    <textarea 
                        id="diagnosis" 
                        name="diagnosis" 
                        class="form-control" 
                        rows="3" 
                        placeholder="Clinical diagnosis or assessment..."
                    ><?php echo htmlspecialchars($consultation['diagnosis'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label for="notes">Clinical Notes</label>
                    <textarea 
                        id="notes" 
                        name="notes" 
                        class="form-control" 
                        rows="3" 
                        placeholder="Additional notes, observations..."
                    ><?php echo htmlspecialchars($consultation['notes'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Consultation Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="active" <?php echo ($consultation['status'] ?? '') == 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="completed" <?php echo ($consultation['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="follow_up" <?php echo ($consultation['status'] ?? '') == 'follow_up' ? 'selected' : ''; ?>>Follow-up Required</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="follow_up_date">Follow-up Date</label>
                        <input 
                            type="date" 
                            id="follow_up_date" 
                            name="follow_up_date" 
                            class="form-control"
                            value="<?php echo $consultation['follow_up_date'] ?? ''; ?>"
                        >
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="form-actions">
            <div class="info-text">
                <i class="fas fa-info-circle"></i>
                <span>Last updated: <?php echo formatDate($consultation['updated_at'], 'd M Y, h:i A'); ?></span>
            </div>
            
            <div class="button-group">
                <a href="<?php echo APP_URL; ?>/consultations/view.php?id=<?php echo $consultation['id']; ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Consultation
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Symptom Template -->
<template id="symptom-template">
    <div class="symptom-card" id="symptom-INDEX">
        <div class="symptom-header">
            <span class="symptom-number">#<span class="number">1</span></span>
            <button type="button" class="btn btn-sm btn-danger" onclick="removeSymptom(INDEX)">
                <i class="fas fa-times"></i> Remove
            </button>
        </div>
        <div class="symptom-body">
            <div class="form-row">
                <div class="form-group" style="position: relative;">
                    <label>Symptom Description * <small style="color: #667eea;"><i class="fas fa-magic"></i> Smart matching</small></label>
                    <textarea 
                        name="symptoms[INDEX][symptom_text]" 
                        class="form-control symptom-input-smart" 
                        rows="2" 
                        placeholder="Type naturally - e.g., 'gets angry when contradicted'"
                        autocomplete="off"
                    ></textarea>
                    <div class="rubric-suggestions-dropdown" style="display: none;"></div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Location</label>
                    <input 
                        type="text" 
                        name="symptoms[INDEX][location]" 
                        class="form-control" 
                        placeholder="e.g., Right temple, Left knee"
                    >
                </div>
                
                <div class="form-group">
                    <label>Sensation</label>
                    <input 
                        type="text" 
                        name="symptoms[INDEX][sensation]" 
                        class="form-control" 
                        placeholder="e.g., Throbbing, Burning, Sharp"
                    >
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Modality (Better/Worse)</label>
                    <input 
                        type="text" 
                        name="symptoms[INDEX][modality]" 
                        class="form-control" 
                        placeholder="e.g., Better by cold, Worse at night"
                    >
                </div>
                
                <div class="form-group">
                    <label>Duration</label>
                    <input 
                        type="text" 
                        name="symptoms[INDEX][duration]" 
                        class="form-control" 
                        placeholder="e.g., 2 weeks, Since morning"
                    >
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Intensity</label>
                    <select name="symptoms[INDEX][intensity]" class="form-control">
                        <option value="mild">Mild</option>
                        <option value="moderate" selected>Moderate</option>
                        <option value="severe">Severe</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Category</label>
                    <select name="symptoms[INDEX][category]" class="form-control">
                        <?php foreach (SYMPTOM_CATEGORIES as $cat): ?>
                            <option value="<?php echo $cat; ?>"><?php echo ucfirst($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.consultation-form-container {
    max-width: 1200px;
    margin: 0 auto;
}

.patient-info-card {
    background: var(--gray-50);
    padding: 15px;
    border-radius: 8px;
    border-left: 4px solid var(--primary-color);
}

.patient-info-card p {
    margin: 8px 0;
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
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.symptom-card {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    border-left: 4px solid var(--primary-color);
}

.symptom-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.symptom-number {
    font-weight: 600;
    color: var(--primary-color);
    font-size: 1.1rem;
}

.symptom-body {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.empty-symptoms {
    padding: 40px;
    color: var(--gray-500);
}

.empty-symptoms i {
    font-size: 48px;
    opacity: 0.3;
    margin-bottom: 15px;
}

.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--gray-50);
    border-radius: 12px;
    margin-top: 20px;
}

.info-text {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--gray-600);
    font-size: 0.9rem;
}

.button-group {
    display: flex;
    gap: 10px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
        gap: 15px;
    }
    
    .button-group {
        width: 100%;
        flex-direction: column;
    }
}

/* Smart Rubric Suggestions Styles */
.rubric-suggestions-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #d1d5db;
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

.symptom-input-smart.has-match {
    border-color: #22c55e;
    background: #f0fdf4;
}
</style>

<script>
let symptomIndex = <?php echo count($existingSymptoms); ?>;

function addSymptom() {
    const container = document.getElementById('symptoms-container');
    const template = document.getElementById('symptom-template');
    const emptyState = document.querySelector('.empty-symptoms');
    
    if (emptyState) {
        emptyState.style.display = 'none';
    }
    
    let newSymptom = template.content.cloneNode(true);
    let html = newSymptom.querySelector('.symptom-card').outerHTML;
    html = html.replace(/INDEX/g, symptomIndex);
    html = html.replace('<span class="number">1</span>', '<span class="number">' + (symptomIndex + 1) + '</span>');
    
    container.insertAdjacentHTML('beforeend', html);
    symptomIndex++;
}

function removeSymptom(index) {
    const symptomCard = document.getElementById('symptom-' + index);
    if (symptomCard) {
        symptomCard.remove();
        
        // Show empty state if no symptoms left
        const remainingSymptoms = document.querySelectorAll('.symptom-card');
        if (remainingSymptoms.length === 0) {
            const emptyState = document.querySelector('.empty-symptoms');
            if (emptyState) {
                emptyState.style.display = 'block';
            }
        }
        
        // Renumber remaining symptoms
        updateSymptomNumbers();
    }
}

function updateSymptomNumbers() {
    const symptoms = document.querySelectorAll('.symptom-card');
    symptoms.forEach((symptom, index) => {
        const numberSpan = symptom.querySelector('.symptom-number .number');
        if (numberSpan) {
            numberSpan.textContent = index + 1;
        } else {
            symptom.querySelector('.symptom-number').innerHTML = '#' + (index + 1);
        }
    });
}

// Form validation
document.getElementById('consultationForm').addEventListener('submit', function(e) {
    const chiefComplaint = document.getElementById('chief_complaint').value.trim();
    
    if (!chiefComplaint) {
        e.preventDefault();
        alert('Please enter the chief complaint');
        document.getElementById('chief_complaint').focus();
        return false;
    }
});

// Smart Rubric Search for Symptom Inputs
let rubricSearchTimeout = null;

document.addEventListener('input', function(e) {
    if (e.target.classList.contains('symptom-input-smart')) {
        clearTimeout(rubricSearchTimeout);
        const input = e.target;
        const dropdown = input.parentElement.querySelector('.rubric-suggestions-dropdown');
        const symptomText = input.value.trim();
        
        if (symptomText.length < 3) {
            if (dropdown) dropdown.style.display = 'none';
            return;
        }
        
        rubricSearchTimeout = setTimeout(async function() {
            if (dropdown) {
                dropdown.innerHTML = `
                    <div class="rubric-suggestions-loading">
                        <i class="fas fa-search fa-spin" style="font-size:1.5rem;color:#667eea;display:block;margin-bottom:8px;"></i>
                        <p>Searching rubrics...</p>
                    </div>
                `;
                dropdown.style.display = 'block';
            }
            
            try {
                const formData = new FormData();
                formData.append('symptom', symptomText);
                
                const response = await fetch('<?php echo APP_URL; ?>/api/get_rubric_suggestions.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success && data.rubrics && data.rubrics.length > 0 && dropdown) {
                    let html = `<div style="padding: 8px 15px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-bottom: 1px solid #ddd; font-size: 12px; color: #0369a1;">
                        <i class="fas fa-magic"></i> Found ${data.rubrics.length} matching rubrics
                    </div>`;
                    
                    data.rubrics.slice(0, 8).forEach(rubric => {
                        const sourceBadge = rubric.source === 'ai' 
                            ? '<span class="rubric-meta-badge rubric-meta-ai"><i class="fas fa-robot"></i> AI</span>' 
                            : '';
                        
                        html += `
                            <div class="rubric-suggestion-item" data-rubric-text="${escapeHtmlAttr(rubric.rubric)}">
                                <div class="rubric-suggestion-name">${escapeHtml(rubric.rubric)}</div>
                                <div class="rubric-suggestion-complete">${escapeHtml(rubric.complete_rubric || '')}</div>
                                <div class="rubric-suggestion-meta">
                                    <span class="rubric-meta-badge rubric-meta-category">${escapeHtml(rubric.category)}</span>
                                    <span class="rubric-meta-badge rubric-meta-remedies">${rubric.remedy_count || 0} remedies</span>
                                    ${sourceBadge}
                                </div>
                            </div>
                        `;
                    });
                    
                    dropdown.innerHTML = html;
                } else if (dropdown) {
                    dropdown.innerHTML = `
                        <div style="padding: 20px; text-align: center; color: #64748b;">
                            <p style="margin: 0;">No matching rubrics found</p>
                            <small>Your symptom will be saved as entered</small>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Rubric search error:', error);
                if (dropdown) dropdown.style.display = 'none';
            }
        }, 400);
    }
});

document.addEventListener('click', function(e) {
    const suggestionItem = e.target.closest('.rubric-suggestion-item');
    if (suggestionItem) {
        const formGroup = suggestionItem.closest('.form-group');
        const input = formGroup.querySelector('.symptom-input-smart');
        const dropdown = formGroup.querySelector('.rubric-suggestions-dropdown');
        
        const rubricText = suggestionItem.dataset.rubricText;
        input.value = rubricText;
        input.classList.add('has-match');
        dropdown.style.display = 'none';
    }
    
    if (!e.target.closest('.symptom-input-smart') && !e.target.closest('.rubric-suggestions-dropdown')) {
        document.querySelectorAll('.rubric-suggestions-dropdown').forEach(d => d.style.display = 'none');
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.rubric-suggestions-dropdown').forEach(d => d.style.display = 'none');
    }
});

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function escapeHtmlAttr(text) {
    if (!text) return '';
    return text.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
