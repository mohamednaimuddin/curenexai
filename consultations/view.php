<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();
$consultationId = (int)($_GET['id'] ?? 0);

// Fetch consultation with patient info
$sql = "SELECT c.*, 
        p.patient_name, p.age, p.gender, p.email, p.phone, p.address,
        COALESCE(d.full_name, 'Unknown Doctor') as doctor_name
        FROM consultations c
        LEFT JOIN patients p ON c.patient_id = p.id
        LEFT JOIN doctors d ON c.doctor_id = d.id
        WHERE c.id = ? AND c.doctor_id = ?";

$consultation = DB::queryOne($sql, [$consultationId, $doctorId]);

if (!$consultation) {
    // Debug: Check if consultation exists without doctor check
    $debug = DB::queryOne(
        "SELECT c.id, c.doctor_id, c.patient_id, p.patient_name 
         FROM consultations c 
         LEFT JOIN patients p ON c.patient_id = p.id 
         WHERE c.id = ?",
        [$consultationId]
    );
    
    if ($debug) {
        // Both IDs are the same but query still fails? Check if it's a query issue
        $testQuery = DB::queryOne(
            "SELECT c.*, 
                    p.patient_name, p.age, p.gender, p.email, p.phone, p.address
             FROM consultations c 
             LEFT JOIN patients p ON c.patient_id = p.id 
             WHERE c.id = ?",
            [$consultationId]
        );
        
        if ($testQuery) {
            // Query works without doctor check, so let's use it
            $consultation = $testQuery;
            $consultation['doctor_name'] = 'Unknown Doctor';
            
            // Get doctor name separately
            $doctorInfo = DB::queryOne("SELECT name FROM doctors WHERE id = ?", [$doctorId]);
            if ($doctorInfo) {
                $consultation['doctor_name'] = $doctorInfo['name'];
            }
        } else {
            setFlash('error', "Query error: Unable to fetch consultation data");
            redirect('/consultations/list.php');
        }
    } else {
        setFlash('error', 'Consultation not found');
        redirect('/consultations/list.php');
    }
}

// Fetch symptoms
$symptoms = DB::query(
    "SELECT * FROM symptoms WHERE consultation_id = ? ORDER BY id",
    [$consultationId]
);

// Fetch prescriptions
$prescriptions = DB::query(
    "SELECT * FROM prescriptions WHERE consultation_id = ? ORDER BY created_at DESC",
    [$consultationId]
);

// Check if there are AI suggestions
$aiSuggestions = DB::query(
    "SELECT * FROM ai_suggestions_log WHERE consultation_id = ? ORDER BY created_at DESC LIMIT 1",
    [$consultationId]
);

$pageTitle = 'View Consultation';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="consultation-view-container">
    <!-- Header Actions -->
    <div class="page-header">
        <div>
            <a href="<?php echo APP_URL; ?>/consultations/list.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to List
            </a>
            <h1><i class="fas fa-file-medical"></i> Consultation Details</h1>
        </div>
        <div class="header-actions">
            <a href="edit.php?id=<?php echo $consultation['id']; ?>" class="btn btn-warning">
                <i class="fas fa-edit"></i> Edit
            </a>
            <button onclick="regenerateAISuggestions(<?php echo $consultation['id']; ?>)" class="btn btn-primary">
                <i class="fas fa-brain"></i> AI Suggestions
            </button>
            <a href="<?php echo APP_URL; ?>/prescriptions/add.php?consultation_id=<?php echo $consultation['id']; ?>" class="btn btn-success">
                <i class="fas fa-prescription"></i> Write Prescription
            </a>
            <button onclick="window.print()" class="btn btn-info">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    
    <!-- Patient Information -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-user"></i> Patient Information</h3>
            <span class="badge badge-<?php echo $consultation['status'] == 'active' ? 'success' : 'secondary'; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $consultation['status'])); ?>
            </span>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Patient Name</label>
                    <p><strong><?php echo htmlspecialchars($consultation['patient_name']); ?></strong></p>
                </div>
                <div class="info-item">
                    <label>Age / Gender</label>
                    <p><?php echo ($consultation['age'] ?? 'N/A'); ?> years / <?php echo ucfirst($consultation['gender'] ?? 'unknown'); ?></p>
                </div>
                <div class="info-item">
                    <label>Consultation Date</label>
                    <p><?php echo formatDate($consultation['consultation_date'], 'd F Y, h:i A'); ?></p>
                </div>
                <div class="info-item">
                    <label>Consulting Doctor</label>
                    <p><?php echo htmlspecialchars($consultation['doctor_name']); ?></p>
                </div>
                <?php if ($consultation['follow_up_date']): ?>
                <div class="info-item">
                    <label>Follow-up Date</label>
                    <p>
                        <span class="badge badge-warning">
                            <?php echo formatDate($consultation['follow_up_date'], 'd F Y'); ?>
                        </span>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Chief Complaint & Present Illness -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-circle"></i> Chief Complaint & Present Illness</h3>
        </div>
        <div class="card-body">
            <div class="content-section">
                <?php echo nl2br(htmlspecialchars($consultation['chief_complaint'])); ?>
            </div>
        </div>
    </div>
    
    <!-- Symptoms -->
    <?php if (!empty($symptoms)): ?>
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-list-ul"></i> Symptoms Recorded (<?php echo count($symptoms); ?>)</h3>
        </div>
        <div class="card-body">
            <div class="symptoms-list">
                <?php foreach ($symptoms as $index => $symptom): ?>
                <div class="symptom-card">
                    <div class="symptom-header">
                        <span class="symptom-number">#<?php echo $index + 1; ?></span>
                        <span class="badge badge-info"><?php echo htmlspecialchars($symptom['category']); ?></span>
                    </div>
                    <div class="symptom-content">
                        <div class="symptom-row">
                            <label>Symptom Description:</label>
                            <p><?php echo nl2br(htmlspecialchars($symptom['symptom_text'])); ?></p>
                        </div>
                        
                        <?php if ($symptom['location']): ?>
                        <div class="symptom-row">
                            <label>Location:</label>
                            <p><?php echo htmlspecialchars($symptom['location']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($symptom['sensation']): ?>
                        <div class="symptom-row">
                            <label>Sensation:</label>
                            <p><?php echo htmlspecialchars($symptom['sensation']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($symptom['modality']): ?>
                        <div class="symptom-row">
                            <label>Modalities:</label>
                            <p><?php echo nl2br(htmlspecialchars($symptom['modality'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- General & Particular Symptoms -->
    <?php if ($consultation['general_symptoms'] || $consultation['particular_symptoms']): ?>
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-clipboard-list"></i> General & Particular Symptoms</h3>
        </div>
        <div class="card-body">
            <?php if ($consultation['general_symptoms']): ?>
            <div class="content-section">
                <h4>General Symptoms</h4>
                <p><?php echo nl2br(htmlspecialchars($consultation['general_symptoms'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($consultation['particular_symptoms']): ?>
            <div class="content-section">
                <h4>Particular Symptoms</h4>
                <p><?php echo nl2br(htmlspecialchars($consultation['particular_symptoms'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Physical & Mental State -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-brain"></i> Physical & Mental Examination</h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <?php if ($consultation['physical_examination']): ?>
                <div class="info-item full-width">
                    <label>Physical Generals</label>
                    <p><?php echo nl2br(htmlspecialchars($consultation['physical_examination'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($consultation['mental_state']): ?>
                <div class="info-item full-width">
                    <label>Mental & Emotional State</label>
                    <p><?php echo nl2br(htmlspecialchars($consultation['mental_state'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Constitutional Characteristics -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-thermometer-half"></i> Constitutional Characteristics</h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <?php if ($consultation['thermal_state']): ?>
                <div class="info-item">
                    <label>Thermal State</label>
                    <p><span class="badge badge-info"><?php echo ucfirst($consultation['thermal_state']); ?></span></p>
                </div>
                <?php endif; ?>
                
                <?php if ($consultation['thirst']): ?>
                <div class="info-item">
                    <label>Thirst</label>
                    <p><span class="badge badge-info"><?php echo ucfirst($consultation['thirst']); ?></span></p>
                </div>
                <?php endif; ?>
                
                <?php if ($consultation['appetite']): ?>
                <div class="info-item">
                    <label>Appetite</label>
                    <p><span class="badge badge-info"><?php echo ucfirst($consultation['appetite']); ?></span></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Past History & Causation -->
    <?php if (!empty($consultation['past_history']) || !empty($consultation['family_history']) || !empty($consultation['causation'])): ?>
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Past History & Causation</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($consultation['past_history'])): ?>
            <div class="content-section">
                <h4>Past Medical History</h4>
                <p><?php echo nl2br(htmlspecialchars($consultation['past_history'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($consultation['family_history'])): ?>
            <div class="content-section">
                <h4>Family History</h4>
                <p><?php echo nl2br(htmlspecialchars($consultation['family_history'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($consultation['causation']): ?>
            <div class="content-section">
                <h4>Causation / Exciting Cause</h4>
                <p><?php echo nl2br(htmlspecialchars($consultation['causation'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Assessment & Plan -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-diagnoses"></i> Assessment & Treatment Plan</h3>
        </div>
        <div class="card-body">
            <?php if ($consultation['diagnosis']): ?>
            <div class="content-section">
                <h4>Diagnosis / Assessment</h4>
                <p><?php echo nl2br(htmlspecialchars($consultation['diagnosis'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($consultation['notes']): ?>
            <div class="content-section">
                <h4>Clinical Notes</h4>
                <p><?php echo nl2br(htmlspecialchars($consultation['notes'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- AI Suggestions Section (unified - handles both new generation and existing suggestions) -->
    <div id="ai-suggestions-section" class="dashboard-card" <?php echo empty($aiSuggestions) ? 'style="border: 2px dashed var(--primary-color);"' : ''; ?>>
        <div class="card-header" <?php echo !empty($aiSuggestions) ? '' : ''; ?>>
            <h3><i class="fas fa-robot"></i> AI Remedy Suggestions</h3>
            <?php if (!empty($aiSuggestions)): ?>
            <button onclick="regenerateAISuggestions(<?php echo $consultation['id']; ?>)" class="btn btn-sm btn-primary">
                <i class="fas fa-sync"></i> Regenerate
            </button>
            <?php endif; ?>
        </div>
        <div id="ai-content-<?php echo $consultation['id']; ?>" class="card-body">
            <?php if (!empty($aiSuggestions)): ?>
                <?php foreach ($aiSuggestions as $suggestion): ?>
                <?php 
                // Try to decode JSON response
                $aiResponse = $suggestion['ai_response'] ?? '';
                $decoded = json_decode($aiResponse, true);
                $hasRag = isset($decoded['rag']['remedies']) && !empty($decoded['rag']['remedies']);
                $hasGemini = isset($decoded['gemini']['remedies']) && !empty($decoded['gemini']['remedies']);
                ?>
            
            <?php if ($hasRag || $hasGemini): ?>
            <!-- Two Column Layout for RAG and Gemini -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                
                <!-- RAG Database Column -->
                <div style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-radius: 12px; padding: 15px;">
                    <h4 style="color: #155724; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-database"></i> RAG Database
                        <span style="font-size: 11px; background: #155724; color: white; padding: 2px 8px; border-radius: 10px;">Local Materia Medica</span>
                    </h4>
                    
                    <?php if ($hasRag): ?>
                        <?php foreach ($decoded['rag']['remedies'] as $idx => $remedy): ?>
                        <div style="background: white; border-radius: 8px; padding: 12px; margin-bottom: 10px; border-left: 4px solid #28a745;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <span style="background: #155724; color: white; padding: 2px 8px; border-radius: 50%; font-size: 12px; margin-right: 8px;"><?php echo $idx + 1; ?></span>
                                    <strong style="color: #155724;"><?php echo htmlspecialchars($remedy['name']); ?></strong>
                                    <?php if (!empty($remedy['common_name'])): ?>
                                    <br><small style="color: #666; margin-left: 28px;"><?php echo htmlspecialchars($remedy['common_name']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div style="text-align: right;">
                                    <span style="background: #28a745; color: white; padding: 3px 10px; border-radius: 15px; font-weight: bold;"><?php echo $remedy['match_percentage']; ?>%</span>
                                    <br><small style="color: #666;"><?php echo htmlspecialchars($remedy['potency'] ?? '30C'); ?></small>
                                </div>
                            </div>
                            <?php if (!empty($remedy['reasoning'])): ?>
                            <p style="font-size: 12px; color: #555; margin: 8px 0 5px 28px; line-height: 1.4;"><?php echo htmlspecialchars(substr($remedy['reasoning'], 0, 150)); ?><?php echo strlen($remedy['reasoning']) > 150 ? '...' : ''; ?></p>
                            <?php endif; ?>
                            <?php if (!empty($remedy['reference'])): ?>
                            <small style="color: #888; margin-left: 28px; font-style: italic;"><?php echo htmlspecialchars(substr($remedy['reference'], 0, 80)); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (!empty($decoded['rag']['case_analysis'])): ?>
                        <div style="background: rgba(255,255,255,0.7); border-radius: 8px; padding: 10px; margin-top: 10px;">
                            <strong style="color: #155724; font-size: 12px;"><i class="fas fa-clipboard-list"></i> Database Analysis</strong>
                            <p style="font-size: 12px; color: #333; margin: 5px 0 0 0; white-space: pre-line;"><?php echo htmlspecialchars($decoded['rag']['case_analysis']); ?></p>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color: #666; font-style: italic;">No RAG suggestions available</p>
                    <?php endif; ?>
                </div>
                
                <!-- Gemini AI Column -->
                <div style="background: linear-gradient(135deg, #e8daef 0%, #d2b4de 100%); border-radius: 12px; padding: 15px;">
                    <h4 style="color: #4a235a; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-brain"></i> Gemini AI
                        <span style="font-size: 11px; background: #4a235a; color: white; padding: 2px 8px; border-radius: 10px;">AI Analysis</span>
                    </h4>
                    
                    <?php if ($hasGemini): ?>
                        <?php foreach ($decoded['gemini']['remedies'] as $idx => $remedy): ?>
                        <div style="background: white; border-radius: 8px; padding: 12px; margin-bottom: 10px; border-left: 4px solid #8e44ad;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <span style="background: #4a235a; color: white; padding: 2px 8px; border-radius: 50%; font-size: 12px; margin-right: 8px;"><?php echo $idx + 1; ?></span>
                                    <strong style="color: #4a235a;"><?php echo htmlspecialchars($remedy['name']); ?></strong>
                                </div>
                                <div style="text-align: right;">
                                    <span style="background: #8e44ad; color: white; padding: 3px 10px; border-radius: 15px; font-weight: bold;"><?php echo $remedy['match_percentage']; ?>%</span>
                                    <br><small style="color: #666;"><?php echo htmlspecialchars($remedy['potency'] ?? '30C'); ?></small>
                                </div>
                            </div>
                            <?php if (!empty($remedy['reasoning'])): ?>
                            <p style="font-size: 12px; color: #555; margin: 8px 0 5px 28px; line-height: 1.4;"><?php echo htmlspecialchars(substr($remedy['reasoning'], 0, 150)); ?><?php echo strlen($remedy['reasoning']) > 150 ? '...' : ''; ?></p>
                            <?php endif; ?>
                            <?php if (!empty($remedy['reference'])): ?>
                            <small style="color: #888; margin-left: 28px; font-style: italic;"><?php echo htmlspecialchars($remedy['reference']); ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (!empty($decoded['gemini']['case_analysis'])): ?>
                        <div style="background: rgba(255,255,255,0.7); border-radius: 8px; padding: 10px; margin-top: 10px;">
                            <strong style="color: #4a235a; font-size: 12px;"><i class="fas fa-lightbulb"></i> AI Case Analysis</strong>
                            <p style="font-size: 12px; color: #333; margin: 5px 0 0 0;"><?php echo nl2br(htmlspecialchars($decoded['gemini']['case_analysis'])); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($decoded['gemini']['cautions'])): ?>
                        <div style="background: rgba(255,193,7,0.2); border-radius: 8px; padding: 10px; margin-top: 10px;">
                            <strong style="color: #856404; font-size: 12px;"><i class="fas fa-exclamation-triangle"></i> Cautions</strong>
                            <p style="font-size: 12px; color: #333; margin: 5px 0 0 0;"><?php echo nl2br(htmlspecialchars($decoded['gemini']['cautions'])); ?></p>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color: #666; font-style: italic;">No Gemini suggestions available</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <!-- Fallback for old format data -->
            <div class="content-section">
                <?php 
                if ($decoded && isset($decoded['gemini']['case_analysis'])) {
                    echo '<p>' . nl2br(htmlspecialchars($decoded['gemini']['case_analysis'])) . '</p>';
                } elseif (!empty($aiResponse)) {
                    echo '<p>' . nl2br(htmlspecialchars(substr($aiResponse, 0, 500))) . '...</p>';
                } else {
                    echo '<p class="text-muted">AI analysis data available</p>';
                }
                ?>
            </div>
            <?php endif; ?>
            
            <!-- Disclaimer -->
            <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%); border: 1px solid #ffc107; border-radius: 8px; padding: 12px; margin-top: 15px;">
                <p style="margin: 0; font-size: 12px; color: #856404;">
                    <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
                    <strong>Disclaimer:</strong> These AI-generated suggestions are for educational and reference purposes only. 
                    They should not replace professional medical judgment. Always verify remedy selections against authoritative 
                    homeopathic texts and consider individual patient characteristics before prescribing. The practitioner bears 
                    full responsibility for all treatment decisions.
                </p>
            </div>
            
            <small class="text-muted">Generated on <?php echo formatDate($suggestion['created_at'], 'd M Y, h:i A'); ?></small>
            <?php endforeach; ?>
            <?php else: ?>
            <!-- Empty state for no AI suggestions -->
            <div class="text-center" style="padding: 40px 20px;">
                <i class="fas fa-robot" style="font-size: 48px; color: var(--primary-color); opacity: 0.3;"></i>
                <h4>AI Remedy Suggestions</h4>
                <p class="text-muted">Get AI-powered remedy suggestions based on this consultation</p>
                <button onclick="regenerateAISuggestions(<?php echo $consultation['id']; ?>)" class="btn btn-primary">
                    <i class="fas fa-magic"></i> Generate AI Suggestions
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Prescriptions -->
    <div class="dashboard-card">
        <div class="card-header">
            <h3><i class="fas fa-prescription"></i> Prescriptions (<?php echo count($prescriptions); ?>)</h3>
            <a href="<?php echo APP_URL; ?>/prescriptions/add.php?consultation_id=<?php echo $consultation['id']; ?>" class="btn btn-sm btn-success">
                <i class="fas fa-plus"></i> Add Prescription
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($prescriptions)): ?>
                <div class="empty-state">
                    <i class="fas fa-prescription"></i>
                    <p>No prescriptions written yet</p>
                    <a href="<?php echo APP_URL; ?>/prescriptions/add.php?consultation_id=<?php echo $consultation['id']; ?>" class="btn btn-success">
                        <i class="fas fa-plus"></i> Write First Prescription
                    </a>
                </div>
            <?php else: ?>
                <div class="prescriptions-list">
                    <?php foreach ($prescriptions as $prescription): ?>
                    <div class="prescription-card">
                        <div class="prescription-header">
                            <span class="prescription-date">
                                <i class="fas fa-calendar"></i>
                                <?php echo formatDate($prescription['prescription_date'], 'd M Y'); ?>
                            </span>
                            <span class="badge badge-success">Active</span>
                        </div>
                        <div class="prescription-actions">
                            <a href="<?php echo APP_URL; ?>/prescriptions/view.php?id=<?php echo $prescription['id']; ?>" class="btn btn-sm btn-info">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="<?php echo APP_URL; ?>/prescriptions/print.php?id=<?php echo $prescription['id']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                <i class="fas fa-print"></i> Print
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@media print {
    .sidebar, .header-actions, .btn, .no-print {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0;
        padding: 20px;
    }
    
    .dashboard-card {
        page-break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
}

.consultation-view-container {
    max-width: 1200px;
    margin: 0 auto;
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
    font-size: 0.95rem;
}

.content-section {
    padding: 15px 0;
}

.content-section:not(:last-child) {
    border-bottom: 1px solid var(--gray-200);
    margin-bottom: 15px;
}

.content-section h4 {
    color: var(--primary-color);
    margin-bottom: 10px;
    font-size: 1.1rem;
}

.symptoms-list {
    display: grid;
    gap: 15px;
}

.symptom-card {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 15px;
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

.symptom-content {
    display: grid;
    gap: 10px;
}

.symptom-row {
    display: grid;
    grid-template-columns: 150px 1fr;
    gap: 10px;
}

.symptom-row label {
    color: var(--gray-600);
    font-weight: 500;
    font-size: 0.875rem;
}

.symptom-row p {
    margin: 0;
}

.prescriptions-list {
    display: grid;
    gap: 15px;
}

.prescription-card {
    background: var(--gray-50);
    border-radius: 8px;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.prescription-header {
    display: flex;
    gap: 15px;
    align-items: center;
}

.prescription-date {
    color: var(--gray-700);
    font-weight: 500;
}

.prescription-actions {
    display: flex;
    gap: 10px;
}

/* AI Suggestions Inline Styles */
#ai-suggestions-section {
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.loading-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--gray-600);
}

.loading-state i {
    font-size: 48px;
    color: var(--primary-color);
    margin-bottom: 15px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    100% { transform: rotate(360deg); }
}

.ai-error-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--danger-color);
}

.ai-error-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.remedies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.remedy-card {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    border-radius: 12px;
    padding: 20px;
    border-left: 4px solid var(--primary-color);
    transition: transform 0.2s, box-shadow 0.2s;
    word-break: break-word;
    overflow-wrap: break-word;
    max-width: 100%;
}

.remedy-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.remedy-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    flex-wrap: wrap;
}

.remedy-name {
    font-size: 18px;
    font-weight: bold;
    color: #333;
    flex: 1;
    min-width: 0;
}

.match-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.875rem;
}

.match-high { background: #10b981; color: white; }
.match-medium { background: #f59e0b; color: white; }
.match-low { background: #6b7280; color: white; }

.remedy-info {
    display: grid;
    gap: 10px;
}

.info-row {
    display: flex;
    align-items: start;
    gap: 10px;
}

.info-row i {
    color: var(--primary-color);
    margin-top: 3px;
}

.ai-summary {
    background: rgba(102, 126, 234, 0.1);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
}

.ai-summary h4 {
    color: var(--primary-color);
    margin-bottom: 10px;
}

/* ============================================
   MOBILE RESPONSIVE STYLES
   ============================================ */

/* Tablet and below */
@media (max-width: 992px) {
    .consultation-view-container {
        padding: 10px;
    }
    
    .page-header {
        flex-direction: column;
        gap: 15px;
        align-items: stretch;
    }
    
    .header-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: flex-start;
    }
    
    .header-actions .btn {
        flex: 1 1 auto;
        min-width: 120px;
        text-align: center;
    }
    
    .info-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    /* AI Suggestions two-column grid */
    div[style*="grid-template-columns: 1fr 1fr"] {
        grid-template-columns: 1fr !important;
    }
}

/* Mobile phones */
@media (max-width: 768px) {
    .consultation-view-container {
        padding: 5px;
    }
    
    .page-header {
        padding: 10px;
    }
    
    .page-header h1 {
        font-size: 1.25rem;
    }
    
    .header-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
    }
    
    .header-actions .btn {
        font-size: 0.8rem;
        padding: 8px 10px;
        min-width: unset;
    }
    
    .header-actions .btn i {
        margin-right: 4px;
    }
    
    /* Hide button text on very small screens, show only icons */
    .dashboard-card {
        margin-bottom: 15px;
        border-radius: 8px;
    }
    
    .card-header {
        padding: 12px 15px;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .card-header h3 {
        font-size: 1rem;
    }
    
    .card-body {
        padding: 15px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .info-item {
        padding: 8px 0;
        border-bottom: 1px solid var(--gray-200);
    }
    
    .info-item:last-child {
        border-bottom: none;
    }
    
    /* Symptom cards mobile */
    .symptom-card {
        padding: 12px;
    }
    
    .symptom-row {
        grid-template-columns: 1fr;
        gap: 4px;
    }
    
    .symptom-row label {
        font-weight: 600;
        color: var(--primary-color);
    }
    
    /* Prescription cards mobile */
    .prescription-card {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
    }
    
    .prescription-header {
        flex-direction: column;
        gap: 8px;
    }
    
    .prescription-actions {
        justify-content: flex-start;
    }
    
    /* AI suggestions cards */
    .remedy-card {
        padding: 12px;
        font-size: 0.9rem;
    }
    
    .remedy-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .remedy-name {
        font-size: 1rem;
        word-break: break-word;
    }
    
    .remedies-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    /* AI two-column layout - stack on mobile */
    div[style*="display: grid"][style*="gap: 20px"] {
        display: block !important;
    }
    
    div[style*="display: grid"][style*="gap: 20px"] > div {
        margin-bottom: 15px;
    }
}

/* Small mobile phones */
@media (max-width: 480px) {
    .page-header h1 {
        font-size: 1.1rem;
    }
    
    .header-actions {
        grid-template-columns: 1fr 1fr;
    }
    
    .header-actions .btn {
        font-size: 0.75rem;
        padding: 8px 6px;
    }
    
    /* Show only icons on very small screens */
    .header-actions .btn span,
    .header-actions .btn:not(:has(i))::after {
        display: none;
    }
    
    .card-header h3 {
        font-size: 0.95rem;
    }
    
    .card-body {
        padding: 12px;
    }
    
    .info-item label {
        font-size: 0.8rem;
    }
    
    .info-item p {
        font-size: 0.9rem;
    }
    
    .symptom-card {
        padding: 10px;
    }
    
    .badge {
        font-size: 0.7rem;
        padding: 3px 8px;
    }
}

/* ============================================
   AI SUGGESTION COLUMNS - RESPONSIVE STYLES
   ============================================ */
.ai-columns-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.ai-column {
    border-radius: 12px;
    padding: 15px;
}

.ai-column-rag {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
}

.ai-column-gemini {
    background: linear-gradient(135deg, #e8daef 0%, #d2b4de 100%);
}

.ai-column-header {
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.ai-column-header-rag {
    color: #155724;
}

.ai-column-header-gemini {
    color: #4a235a;
}

.ai-column-badge {
    font-size: 11px;
    color: white;
    padding: 2px 8px;
    border-radius: 10px;
}

.ai-column-badge-rag {
    background: #155724;
}

.ai-column-badge-gemini {
    background: #4a235a;
}

.ai-remedy-card {
    background: white;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
}

.ai-remedy-card-rag {
    border-left: 4px solid #28a745;
}

.ai-remedy-card-gemini {
    border-left: 4px solid #8e44ad;
}

.ai-remedy-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 8px;
}

.ai-remedy-info {
    flex: 1;
    min-width: 0;
}

.ai-remedy-number {
    color: white;
    padding: 2px 8px;
    border-radius: 50%;
    font-size: 12px;
    margin-right: 8px;
    display: inline-block;
}

.ai-remedy-number-rag {
    background: #155724;
}

.ai-remedy-number-gemini {
    background: #4a235a;
}

.ai-remedy-name {
    word-break: break-word;
}

.ai-remedy-name-rag {
    color: #155724;
}

.ai-remedy-name-gemini {
    color: #4a235a;
}

.ai-remedy-common-name {
    color: #666;
    margin-left: 28px;
}

.ai-remedy-match {
    text-align: right;
    flex-shrink: 0;
}

.ai-remedy-match-badge {
    color: white;
    padding: 3px 10px;
    border-radius: 15px;
    font-weight: bold;
    font-size: 0.875rem;
}

.ai-remedy-match-badge-rag {
    background: #28a745;
}

.ai-remedy-match-badge-gemini {
    background: #8e44ad;
}

.ai-remedy-potency {
    color: #666;
    font-size: 0.8rem;
}

.ai-remedy-reasoning {
    font-size: 12px;
    color: #555;
    margin: 8px 0 5px 28px;
    line-height: 1.4;
}

.ai-remedy-reference {
    color: #888;
    margin-left: 28px;
    font-style: italic;
    font-size: 0.8rem;
    display: block;
    word-break: break-word;
}

.ai-analysis-box {
    background: rgba(255,255,255,0.7);
    border-radius: 8px;
    padding: 10px;
    margin-top: 10px;
}

.ai-analysis-title {
    font-size: 12px;
    font-weight: bold;
}

.ai-analysis-title-rag {
    color: #155724;
}

.ai-analysis-title-gemini {
    color: #4a235a;
}

.ai-analysis-text {
    font-size: 12px;
    color: #333;
    margin: 5px 0 0 0;
    white-space: pre-line;
}

/* Mobile responsive for AI columns */
@media (max-width: 992px) {
    .ai-columns-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
}

@media (max-width: 768px) {
    .ai-column {
        padding: 12px;
        border-radius: 10px;
    }
    
    .ai-column-header {
        font-size: 0.95rem;
    }
    
    .ai-column-badge {
        font-size: 10px;
    }
    
    .ai-remedy-card {
        padding: 10px;
    }
    
    .ai-remedy-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .ai-remedy-match {
        text-align: left;
        margin-top: 5px;
    }
    
    .ai-remedy-reasoning {
        margin-left: 0;
        margin-top: 10px;
    }
    
    .ai-remedy-reference {
        margin-left: 0;
        margin-top: 8px;
    }
    
    .ai-remedy-common-name {
        margin-left: 0;
        display: block;
        margin-top: 4px;
    }
}

@media (max-width: 480px) {
    .ai-column {
        padding: 10px;
    }
    
    .ai-remedy-card {
        padding: 8px;
    }
    
    .ai-remedy-number {
        padding: 2px 6px;
        font-size: 11px;
    }
    
    .ai-remedy-match-badge {
        padding: 2px 8px;
        font-size: 0.75rem;
    }
    
    .ai-remedy-reasoning,
    .ai-remedy-reference {
        font-size: 11px;
    }
}

/* Additional AI UI components */
.ai-no-results {
    color: #666;
    font-style: italic;
    padding: 10px;
}

.ai-error-text {
    color: #666;
    font-style: italic;
    padding: 10px;
}

.ai-error-text i {
    margin-right: 5px;
}

.ai-busy-state {
    text-align: center;
    padding: 20px;
}

.ai-busy-state i {
    font-size: 32px;
    color: #8e44ad;
    opacity: 0.5;
    margin-bottom: 10px;
    display: block;
}

.ai-busy-title {
    color: #4a235a;
    font-weight: 500;
    margin-bottom: 5px;
}

.ai-busy-text {
    color: #666;
    font-size: 12px;
    margin-bottom: 15px;
}

.ai-retry-btn {
    background: #8e44ad;
    color: white;
    border: none;
}

.ai-caution-box {
    background: rgba(255, 193, 7, 0.2);
    border-radius: 8px;
    padding: 10px;
    margin-top: 10px;
}

.ai-caution-title {
    color: #856404;
    font-size: 12px;
}

.ai-caution-text {
    font-size: 12px;
    color: #333;
    margin: 5px 0 0 0;
}

.ai-disclaimer {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
    border: 1px solid #ffc107;
    border-radius: 8px;
    padding: 12px;
    margin-top: 15px;
}

.ai-disclaimer p {
    margin: 0;
    font-size: 12px;
    color: #856404;
}

.ai-disclaimer i {
    margin-right: 8px;
}

.ai-provider-info {
    text-align: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #e5e7eb;
}

@media (max-width: 768px) {
    .ai-disclaimer {
        padding: 10px;
    }
    
    .ai-disclaimer p {
        font-size: 11px;
        line-height: 1.5;
    }
    
    .ai-busy-state {
        padding: 15px;
    }
    
    .ai-busy-state i {
        font-size: 24px;
    }
}
</style>

<script>
// Regenerate AI suggestions inline
async function regenerateAISuggestions(consultationId) {
    const content = document.getElementById('ai-content-' + consultationId);
    
    // Scroll to the section
    document.getElementById('ai-suggestions-section').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    
    // Show loading state
    content.innerHTML = `
        <div class="loading-state">
            <i class="fas fa-brain fa-spin"></i>
            <p>Analyzing consultation with RAG Database + Gemini AI...</p>
            <small class="text-muted">This may take a few seconds</small>
        </div>
    `;
    
    try {
        const apiUrl = '<?php echo APP_URL; ?>/api/get_dual_ai_suggestions.php?consultation_id=' + consultationId + '&_=' + Date.now();
        console.log('AI API URL:', apiUrl);  // Debug: see which URL is being called
        
        const response = await fetch(apiUrl, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Cache-Control': 'no-cache'
            }
        });
        
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        
        const data = await response.json();
        
        if (data.success) {
            displayAISuggestions(content, data);
        } else {
            throw new Error(data.error || 'Unknown error occurred');
        }
        
    } catch (error) {
        console.error('Error:', error);
        content.innerHTML = `
            <div class="ai-error-state">
                <i class="fas fa-exclamation-triangle"></i>
                <h4>Failed to Generate Suggestions</h4>
                <p>${error.message}</p>
                <div style="margin-top: 20px;">
                    <button onclick="location.reload()" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Refresh Page
                    </button>
                    <button onclick="regenerateAISuggestions(${consultationId})" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Try Again
                    </button>
                </div>
            </div>
        `;
    }
}

function displayAISuggestions(container, data) {
    // Handle dual AI response format (rag + gemini)
    const hasRag = data.rag && data.rag.remedies && data.rag.remedies.length > 0;
    const hasGemini = data.gemini && data.gemini.remedies && data.gemini.remedies.length > 0;
    
    let html = '';
    
    if (hasRag || hasGemini) {
        // Two Column Layout for RAG and Gemini (uses CSS class for responsive)
        html += '<div class="ai-columns-grid">';
        
        // RAG Database Column
        html += `
        <div class="ai-column ai-column-rag">
            <h4 class="ai-column-header ai-column-header-rag">
                <i class="fas fa-database"></i> RAG Database
                <span class="ai-column-badge ai-column-badge-rag">Local Materia Medica</span>
            </h4>`;
        
        if (hasRag) {
            data.rag.remedies.forEach((remedy, idx) => {
                const match = parseInt(remedy.match_percentage) || 0;
                html += `
                <div class="ai-remedy-card ai-remedy-card-rag">
                    <div class="ai-remedy-header">
                        <div class="ai-remedy-info">
                            <span class="ai-remedy-number ai-remedy-number-rag">${idx + 1}</span>
                            <strong class="ai-remedy-name ai-remedy-name-rag">${escapeHtml(remedy.name)}</strong>
                            ${remedy.common_name ? `<br><small class="ai-remedy-common-name">${escapeHtml(remedy.common_name)}</small>` : ''}
                        </div>
                        <div class="ai-remedy-match">
                            <span class="ai-remedy-match-badge ai-remedy-match-badge-rag">${match}%</span>
                            <br><small class="ai-remedy-potency">${escapeHtml(remedy.potency || '30C')}</small>
                        </div>
                    </div>
                    ${remedy.reasoning ? `<p class="ai-remedy-reasoning">${escapeHtml(remedy.reasoning.substring(0, 150))}${remedy.reasoning.length > 150 ? '...' : ''}</p>` : ''}
                    ${remedy.reference ? `<small class="ai-remedy-reference">${escapeHtml(remedy.reference.substring(0, 80))}</small>` : ''}
                </div>`;
            });
            
            if (data.rag.case_analysis) {
                html += `
                <div class="ai-analysis-box">
                    <strong class="ai-analysis-title ai-analysis-title-rag"><i class="fas fa-clipboard-list"></i> Database Analysis</strong>
                    <p class="ai-analysis-text">${escapeHtml(data.rag.case_analysis)}</p>
                </div>`;
            }
        } else {
            html += '<p class="ai-no-results">No RAG suggestions available</p>';
        }
        html += '</div>';
        
        // Gemini AI Column
        html += `
        <div class="ai-column ai-column-gemini">
            <h4 class="ai-column-header ai-column-header-gemini">
                <i class="fas fa-brain"></i> Gemini AI
                <span class="ai-column-badge ai-column-badge-gemini">AI Analysis</span>
            </h4>`;
        
        if (hasGemini) {
            data.gemini.remedies.forEach((remedy, idx) => {
                const match = parseInt(remedy.match_percentage) || 0;
                html += `
                <div class="ai-remedy-card ai-remedy-card-gemini">
                    <div class="ai-remedy-header">
                        <div class="ai-remedy-info">
                            <span class="ai-remedy-number ai-remedy-number-gemini">${idx + 1}</span>
                            <strong class="ai-remedy-name ai-remedy-name-gemini">${escapeHtml(remedy.name)}</strong>
                        </div>
                        <div class="ai-remedy-match">
                            <span class="ai-remedy-match-badge ai-remedy-match-badge-gemini">${match}%</span>
                            <br><small class="ai-remedy-potency">${escapeHtml(remedy.potency || '30C')}</small>
                        </div>
                    </div>
                    ${remedy.reasoning ? `<p class="ai-remedy-reasoning">${escapeHtml(remedy.reasoning.substring(0, 150))}${remedy.reasoning.length > 150 ? '...' : ''}</p>` : ''}
                    ${remedy.reference ? `<small class="ai-remedy-reference">${escapeHtml(remedy.reference)}</small>` : ''}
                </div>`;
            });
            
            if (data.gemini.case_analysis) {
                html += `
                <div class="ai-analysis-box">
                    <strong class="ai-analysis-title ai-analysis-title-gemini"><i class="fas fa-lightbulb"></i> AI Case Analysis</strong>
                    <p class="ai-analysis-text">${escapeHtml(data.gemini.case_analysis)}</p>
                </div>`;
            }
            
            if (data.gemini.cautions) {
                html += `
                <div class="ai-caution-box">
                    <strong class="ai-caution-title"><i class="fas fa-exclamation-triangle"></i> Cautions</strong>
                    <p class="ai-caution-text">${escapeHtml(data.gemini.cautions)}</p>
                </div>`;
            }
        } else if (data.gemini && data.gemini.error) {
            // Check if it's an overload/quota error and show a friendlier message with retry
            const isOverloaded = data.gemini.error.toLowerCase().includes('overload') || 
                                 data.gemini.error.toLowerCase().includes('quota') ||
                                 data.gemini.error.toLowerCase().includes('exceeded');
            if (isOverloaded) {
                html += `
                <div class="ai-busy-state">
                    <i class="fas fa-server"></i>
                    <p class="ai-busy-title">Gemini AI is temporarily busy</p>
                    <p class="ai-busy-text">The AI model is experiencing high demand. Please try again in a moment.</p>
                    <button onclick="regenerateAISuggestions(<?php echo $consultation['id']; ?>)" class="btn btn-sm ai-retry-btn">
                        <i class="fas fa-redo"></i> Retry
                    </button>
                </div>`;
            } else {
                html += `<p class="ai-error-text"><i class="fas fa-exclamation-circle"></i> ${escapeHtml(data.gemini.error)}</p>`;
            }
        } else {
            html += '<p class="ai-no-results">No Gemini suggestions available</p>';
        }
        html += '</div>';
        
        html += '</div>'; // Close grid
    } else {
        html += '<p class="ai-no-results" style="text-align: center;">No AI suggestions available</p>';
    }
    
    // Disclaimer
    html += `
        <div class="ai-disclaimer">
            <p>
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Disclaimer:</strong> These AI-generated suggestions are for educational and reference purposes only. 
                They should not replace professional medical judgment. Always verify remedy selections against authoritative 
                homeopathic texts and consider individual patient characteristics before prescribing. The practitioner bears 
                full responsibility for all treatment decisions.
            </p>
        </div>
    `;
    
    // Provider info
    html += `
        <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
            <small class="text-muted">
                <i class="fas fa-robot"></i> Powered by RAG Database + Gemini AI
            </small>
        </div>
    `;
    
    container.innerHTML = html;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
