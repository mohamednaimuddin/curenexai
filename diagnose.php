<?php
/**
 * Disease Diagnosis Page - RAG-based Medical Diagnosis
 * Uses ONLY local database data for diagnosis suggestions
 */
require_once __DIR__ . '/includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();

// Get recent consultations for quick diagnosis
$recentConsultations = DB::query(
    "SELECT c.id, c.chief_complaint, c.present_illness, c.physical_examination,
            c.general_symptoms, c.particular_symptoms, c.diagnosis, c.created_at,
            p.patient_name, p.age, p.gender
     FROM consultations c 
     JOIN patients p ON c.patient_id = p.id 
     WHERE c.doctor_id = ? 
     ORDER BY c.created_at DESC 
     LIMIT 10",
    [$doctorId]
);

$pageTitle = 'Disease Diagnosis';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .diagnose-container { position: relative; }
    .diagnose-container::before {
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
        .diagnose-container::before { left: 0; top: 60px; }
    }
</style>

<div class="diagnose-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-diagnoses"></i> Disease Diagnosis</h1>
            <p class="text-muted">RAG-based diagnosis using local medical database</p>
        </div>
    </div>

    <div class="diagnose-layout">
        <!-- Left Panel - Input -->
        <div class="diagnose-input-panel">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-edit"></i> Enter Patient Information</h3>
                </div>
                <div class="card-body">
                    <form id="diagnoseForm">
                        <div class="form-group">
                            <label for="chief_complaint"><i class="fas fa-notes-medical"></i> Chief Complaint <span class="badge badge-primary">Important</span></label>
                            <textarea 
                                name="chief_complaint" 
                                id="chief_complaint" 
                                class="form-control" 
                                rows="3"
                                placeholder="Main problem (e.g., 'Weight gain, fatigue, feeling cold all the time for 3 months' or 'Severe one-sided headache with nausea, worse in bright light')..."
                            ></textarea>
                            <small class="form-text text-muted">Describe the primary issue - include duration, location, and modalities for best results</small>
                        </div>

                        <div class="form-group">
                            <label for="symptoms"><i class="fas fa-thermometer"></i> Associated Symptoms</label>
                            <textarea 
                                name="symptoms" 
                                id="symptoms" 
                                class="form-control" 
                                rows="4"
                                placeholder="List all symptoms (e.g., dry skin, hair loss, constipation, depression, slow heart rate, irregular periods, muscle cramps)..."
                            ></textarea>
                            <small class="form-text text-muted">Include physical, mental, and emotional symptoms - be specific (e.g., 'pain worse at night' instead of just 'pain')</small>
                        </div>

                        <div class="form-group">
                            <label for="lab_tests"><i class="fas fa-flask"></i> Lab Results (Optional)</label>
                            <textarea 
                                name="lab_tests" 
                                id="lab_tests" 
                                class="form-control" 
                                rows="2"
                                placeholder="Lab findings (e.g., TSH elevated, T3/T4 low, high cholesterol, low hemoglobin, high uric acid, elevated ESR)..."
                            ></textarea>
                        </div>

                        <div class="form-group">
                            <label for="physical_exam"><i class="fas fa-stethoscope"></i> Physical Examination (Optional)</label>
                            <textarea 
                                name="physical_exam" 
                                id="physical_exam" 
                                class="form-control" 
                                rows="2"
                                placeholder="Physical findings (e.g., goiter present, bradycardia, delayed reflexes, puffy face, dry coarse skin)..."
                            ></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg" id="diagnoseBtn">
                                <i class="fas fa-database"></i> RAG Diagnosis
                            </button>
                            <button type="button" class="btn btn-gemini btn-lg" id="geminiDiagnoseBtn">
                                <i class="fas fa-robot"></i> Gemini AI
                            </button>
                            <button type="button" class="btn btn-outline" onclick="clearForm()">
                                <i class="fas fa-eraser"></i> Clear
                            </button>
                        </div>
                    </form>

                    <!-- Quick Examples -->
                    <div class="quick-examples">
                        <p class="text-muted mb-2"><strong>Quick Examples:</strong> <small>(Click to load sample case)</small></p>
                        <div class="example-buttons">
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('hypothyroidism')">
                                <i class="fas fa-weight"></i> Hypothyroid
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('pcos')">
                                <i class="fas fa-venus"></i> PCOS
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('migraine')">
                                <i class="fas fa-head-side-virus"></i> Migraine
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('diabetes')">
                                <i class="fas fa-tint"></i> Diabetes
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('gout')">
                                <i class="fas fa-bone"></i> Gout
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('appendicitis')">
                                <i class="fas fa-stomach"></i> Appendicitis
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('ibs')">
                                <i class="fas fa-stomach"></i> IBS
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('fibromyalgia')">
                                <i class="fas fa-user-injured"></i> Fibromyalgia
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('anxiety')">
                                <i class="fas fa-brain"></i> Anxiety
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('insomnia')">
                                <i class="fas fa-moon"></i> Insomnia
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('heart_attack')">
                                <i class="fas fa-heartbeat"></i> Heart Attack
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('typhoid')">
                                <i class="fas fa-temperature-high"></i> Typhoid
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('ringworm')">
                                <i class="fas fa-circle-notch"></i> Ringworm
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('scabies')">
                                <i class="fas fa-bug"></i> Scabies
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('acne')">
                                <i class="fas fa-face-meh"></i> Acne
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('common_cold')">
                                <i class="fas fa-head-side-cough"></i> Common Cold
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('gastritis')">
                                <i class="fas fa-stomach"></i> Gastritis
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('kidney_stones')">
                                <i class="fas fa-gem"></i> Kidney Stones
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('frozen_shoulder')">
                                <i class="fas fa-shoulder"></i> Frozen Shoulder
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('hemorrhoids')">
                                <i class="fas fa-toilet"></i> Hemorrhoids
                            </button>
                            <button type="button" class="btn btn-sm btn-outline" onclick="loadExample('vertigo')">
                                <i class="fas fa-dizzy"></i> Vertigo
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Consultations -->
            <?php if (!empty($recentConsultations)): ?>
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Consultations</h3>
                </div>
                <div class="card-body p-0">
                    <div class="recent-consultations-list">
                        <?php foreach ($recentConsultations as $consult): ?>
                        <div class="consultation-item" onclick="loadFromConsultation(<?php echo htmlspecialchars(json_encode($consult)); ?>)">
                            <div class="consult-info">
                                <strong><?php echo htmlspecialchars($consult['patient_name']); ?></strong>
                                <small><?php echo $consult['age']; ?>y / <?php echo ucfirst($consult['gender']); ?></small>
                            </div>
                            <div class="consult-complaint">
                                <?php echo htmlspecialchars(truncate($consult['chief_complaint'], 50)); ?>
                            </div>
                            <div class="consult-date">
                                <?php echo date('M d', strtotime($consult['created_at'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Panel - Results -->
        <div class="diagnose-results-panel">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-check"></i> Diagnosis Results</h3>
                    <span class="badge badge-info" id="resultsCount" style="display: none;">0 matches</span>
                </div>
                <div class="card-body">
                    <div id="diagnosisResults">
                        <div class="empty-state">
                            <i class="fas fa-search-plus"></i>
                            <p>Enter patient symptoms and click a diagnosis button</p>
                            <small class="text-muted">
                                <strong>RAG Diagnosis:</strong> Local database (offline capable)<br>
                                <strong>Gemini AI:</strong> Advanced AI analysis (requires internet)
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Warning Card -->
            <div class="dashboard-card warning-card">
                <div class="card-body">
                    <h4><i class="fas fa-exclamation-triangle text-warning"></i> Important Disclaimer</h4>
                    <div class="disclaimer-content">
                        <p><strong>⚠️ FOR EDUCATIONAL & REFERENCE PURPOSES ONLY</strong></p>
                        <ul class="warning-list">
                            <li>This tool provides <strong>diagnostic suggestions only</strong>, NOT a final diagnosis</li>
                            <li>Results are based on symptom pattern matching - clinical correlation is essential</li>
                            <li>Always confirm diagnosis with proper clinical examination and investigations</li>
                            <li>Homeopathic remedies shown are suggestions based on classical literature</li>
                            <li>Individual case-taking (constitution, modalities, mental symptoms) is essential for accurate prescription</li>
                            <li><strong>Do NOT prescribe based solely on this tool's suggestions</strong></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Info Card -->
            <div class="dashboard-card info-card">
                <div class="card-body">
                    <h4><i class="fas fa-info-circle text-primary"></i> How it Works</h4>
                    <ul class="info-list">
                        <li><i class="fas fa-database"></i> <strong>RAG Diagnosis:</strong> Uses local database - 100% offline capable</li>
                        <li><i class="fas fa-robot"></i> <strong>Gemini AI:</strong> Uses Google's AI for advanced analysis</li>
                        <li><i class="fas fa-search"></i> Matches symptoms against <?php 
                            $diseaseCount = DB::queryOne("SELECT COUNT(*) as cnt FROM diseases");
                            $symptomCount = DB::queryOne("SELECT COUNT(*) as cnt FROM symptom_master");
                            $mappingCount = DB::queryOne("SELECT COUNT(*) as cnt FROM disease_symptoms");
                            echo $diseaseCount['cnt'] ?? 0;
                        ?> diseases with <?php echo $symptomCount['cnt'] ?? 0; ?> symptoms</li>
                        <li><i class="fas fa-link"></i> <?php echo $mappingCount['cnt'] ?? 0; ?> disease-symptom mappings for accurate matching</li>
                        <li><i class="fas fa-calculator"></i> Calculates confidence based on symptom match score</li>
                        <li><i class="fas fa-user-md"></i> Final diagnosis must be confirmed by physician</li>
                    </ul>
                    <?php if (($diseaseCount['cnt'] ?? 0) == 0): ?>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Database Empty!</strong> 
                        Please import the disease database:
                        <code>mysql homeo_db &lt; database/diseases_schema.sql</code><br>
                        <code>mysql homeo_db &lt; database/diseases_data.sql</code>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.diagnose-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.diagnose-layout {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.diagnose-input-panel,
.diagnose-results-panel {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    flex-wrap: wrap;
}

/* Badge styles */
.badge {
    display: inline-block;
    padding: 2px 8px;
    font-size: 10px;
    font-weight: 600;
    border-radius: 10px;
    text-transform: uppercase;
    vertical-align: middle;
    margin-left: 6px;
}

.badge-primary {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
}

.form-text {
    font-size: 12px;
    margin-top: 4px;
    color: #6b7280;
}

/* Gemini Button Styles */
.btn-gemini {
    background: linear-gradient(135deg, #4285f4, #8e44ef);
    color: white;
    border: none;
    position: relative;
    overflow: hidden;
}

.btn-gemini:hover {
    background: linear-gradient(135deg, #3b78e7, #7c3aed);
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(130, 68, 239, 0.4);
}

.btn-gemini::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        to bottom right,
        rgba(255, 255, 255, 0.3) 0%,
        rgba(255, 255, 255, 0) 60%
    );
    transform: rotate(45deg);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%) rotate(45deg); }
    100% { transform: translateX(100%) rotate(45deg); }
}

.quick-examples {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.example-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.example-buttons .btn {
    font-size: 12px;
}

.recent-consultations-list {
    max-height: 250px;
    overflow-y: auto;
}

.consultation-item {
    display: grid;
    grid-template-columns: 1fr 2fr auto;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background 0.2s;
}

.consultation-item:hover {
    background: #f8f9fa;
}

.consultation-item:last-child {
    border-bottom: none;
}

.consult-info strong {
    display: block;
    font-size: 13px;
}

.consult-info small {
    color: #666;
}

.consult-complaint {
    font-size: 13px;
    color: #333;
}

.consult-date {
    font-size: 12px;
    color: #888;
}

/* Results Styling */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #666;
}

.empty-state i {
    font-size: 48px;
    color: #ddd;
    margin-bottom: 16px;
}

.empty-state p {
    font-size: 16px;
    margin-bottom: 8px;
}

.diagnosis-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    margin-bottom: 16px;
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
}

.diagnosis-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.diagnosis-card-header {
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #f0f0f0;
}

.diagnosis-card-header h4 {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.confidence-badge {
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.confidence-high {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.confidence-medium {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.confidence-low {
    background: linear-gradient(135deg, #6b7280, #4b5563);
    color: white;
}

.diagnosis-card-body {
    padding: 20px;
}

.diagnosis-section {
    margin-bottom: 16px;
}

.diagnosis-section:last-child {
    margin-bottom: 0;
}

.diagnosis-section h5 {
    font-size: 12px;
    text-transform: uppercase;
    color: #888;
    margin-bottom: 8px;
    font-weight: 600;
}

.symptom-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.symptom-tag {
    background: #e0f2fe;
    color: #0369a1;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
}

.findings-text {
    color: #333;
    font-size: 14px;
    line-height: 1.6;
}

.doctor-notes {
    background: #fef3c7;
    border-left: 3px solid #f59e0b;
    padding: 12px 16px;
    border-radius: 0 8px 8px 0;
    font-size: 13px;
    color: #92400e;
}

/* Remedies Section Styles */
.remedies-section {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border-radius: 12px;
    padding: 16px;
    margin-top: 12px;
    border: 1px solid #bbf7d0;
}

.remedies-section h5 {
    color: #166534;
    margin-bottom: 12px;
}

.remedies-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.remedy-item {
    background: white;
    border-radius: 8px;
    padding: 12px 14px;
    border-left: 4px solid #22c55e;
}

.remedy-item.remedy-primary {
    border-left-color: #16a34a;
    background: #f0fdf4;
}

.remedy-item.remedy-secondary {
    border-left-color: #65a30d;
    background: #fefce8;
}

.remedy-item.remedy-supportive {
    border-left-color: #0891b2;
    background: #f0f9ff;
}

.remedy-header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 6px;
}

.remedy-header strong {
    font-size: 14px;
    color: #1f2937;
}

.remedy-header .common-name {
    color: #6b7280;
    font-size: 12px;
}

.indication-badge {
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 10px;
    text-transform: uppercase;
    font-weight: 600;
}

.indication-badge.primary {
    background: #dcfce7;
    color: #166534;
}

.indication-badge.secondary {
    background: #fef9c3;
    color: #854d0e;
}

.indication-badge.supportive {
    background: #e0f2fe;
    color: #0369a1;
}

.remedy-indication {
    font-size: 13px;
    color: #4b5563;
    margin: 6px 0;
    line-height: 1.5;
}

.potency-tag {
    display: inline-block;
    font-size: 11px;
    color: #7c3aed;
    background: #f3e8ff;
    padding: 2px 8px;
    border-radius: 4px;
}

.potency-tag i {
    margin-right: 4px;
}

.remedy-disclaimer {
    font-size: 11px;
    color: #6b7280;
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px dashed #d1d5db;
}

/* Warning Card Styles */
.warning-card {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border: 1px solid #f59e0b;
}

.warning-card h4 {
    margin: 0 0 12px 0;
    font-size: 16px;
    color: #92400e;
}

.disclaimer-content p {
    margin: 0 0 10px 0;
    color: #92400e;
}

.warning-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.warning-list li {
    padding: 6px 0;
    font-size: 12px;
    color: #78350f;
    display: flex;
    align-items: flex-start;
    gap: 8px;
}

.warning-list li::before {
    content: "⚠";
    font-size: 10px;
}

/* Remedy Warning Styles */
.remedy-warning {
    background: #fef3c7;
    border: 1px solid #f59e0b;
    border-radius: 8px;
    padding: 12px;
    margin-top: 12px;
}

.remedy-warning .remedy-disclaimer {
    border-top: none;
    padding-top: 0;
    margin-top: 0;
    margin-bottom: 8px;
    color: #92400e;
    font-size: 12px;
    font-weight: 600;
}

.remedy-warning-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.remedy-warning-list li {
    padding: 3px 0;
    font-size: 11px;
    color: #78350f;
    padding-left: 12px;
    position: relative;
}

.remedy-warning-list li::before {
    content: "•";
    position: absolute;
    left: 0;
    color: #f59e0b;
}

.remedy-disclaimer i {
    color: #9ca3af;
    margin-right: 4px;
}

/* Info Card */
.info-card {
    background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
    border: 1px solid #bae6fd;
}

.info-card h4 {
    margin: 0 0 16px 0;
    font-size: 16px;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-list li {
    padding: 8px 0;
    font-size: 13px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.info-list li i {
    color: #0284c7;
    width: 16px;
}

/* Loading State */
.loading-state {
    text-align: center;
    padding: 40px 20px;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid #e5e7eb;
    border-top-color: #6366f1;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 16px;
}

.loading-spinner.gemini-spinner {
    border-top-color: #8e44ef;
    border-right-color: #4285f4;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Provider Badges */
.provider-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.provider-badge.gemini {
    background: linear-gradient(135deg, #4285f4, #8e44ef);
    color: white;
}

.provider-badge.rag {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

/* Source Tag */
.source-tag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    margin-left: 8px;
    font-size: 12px;
}

.source-tag.gemini {
    background: linear-gradient(135deg, #4285f4, #8e44ef);
    color: white;
}

/* Gemini Result Card */
.diagnosis-card.gemini-result {
    border: 2px solid transparent;
    background: linear-gradient(white, white) padding-box,
                linear-gradient(135deg, #4285f4, #8e44ef) border-box;
}

.diagnosis-card.gemini-result .diagnosis-card-header {
    background: linear-gradient(135deg, rgba(66, 133, 244, 0.1), rgba(142, 68, 239, 0.1));
}

/* Responsive */
@media (max-width: 992px) {
    .diagnose-layout {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Example cases
const examples = {
    hypothyroidism: {
        symptoms: 'weight gain, fatigue, cold intolerance, dry skin, hair loss, constipation, depression, slow heart rate, muscle weakness, brittle nails, puffy face, hoarse voice',
        chief_complaint: 'Patient complains of unexplained weight gain of 8kg over 3 months despite no change in diet. Feels extremely tired and fatigued all day. Cannot tolerate cold weather - always feeling cold when others are comfortable. Hair falling excessively.',
        lab_tests: 'TSH elevated at 12.5 mIU/L (high), T4 low at 0.5 ng/dL, T3 low, high cholesterol',
        physical_exam: 'Goiter present, dry coarse skin, bradycardia (HR 55), delayed ankle reflexes, periorbital edema, puffy face'
    },
    pcos: {
        symptoms: 'irregular periods, missed periods, weight gain, acne, facial hair, hair thinning on scalp, difficulty getting pregnant, mood swings, fatigue',
        chief_complaint: 'Female patient age 28 has irregular menstrual cycles - periods come every 2-3 months. Noticed increased facial hair (hirsutism) and persistent acne. Has gained 10kg in past year. Trying to conceive for 1 year without success.',
        lab_tests: 'LH:FSH ratio elevated, testosterone slightly elevated, fasting insulin high, PCOS on ultrasound (multiple ovarian cysts)',
        physical_exam: 'BMI 29, hirsutism on face and chin, acne on face and back, acanthosis nigricans on neck'
    },
    migraine: {
        symptoms: 'severe unilateral throbbing headache, nausea, vomiting, photophobia, phonophobia, visual aura',
        chief_complaint: 'Patient has severe throbbing migraine headache on the right side of head for 12 hours. Unable to tolerate light (photophobia) or sound (phonophobia). Associated nausea and one episode of vomiting. History of similar episodes.',
        lab_tests: 'Usually not needed - clinical diagnosis',
        physical_exam: 'Photophobia, phonophobia, normal neurological examination, no papilledema, no neck stiffness'
    },
    diabetes: {
        symptoms: 'increased thirst, frequent urination, fatigue, blurred vision, weight loss, slow wound healing, tingling in hands and feet',
        chief_complaint: 'Patient complains of excessive thirst and urination for 2 weeks. Has lost 5kg weight despite eating well. Feeling tired and weak. Small cut on foot not healing for 3 weeks.',
        lab_tests: 'Fasting blood glucose 280 mg/dL, HbA1c 9.5%, urine sugar present',
        physical_exam: 'Dehydrated, dry skin, fruity breath odor, decreased sensation in feet'
    },
    gout: {
        symptoms: 'gout, severe joint pain, red swollen joint, pain in big toe, nocturnal onset, uric acid elevated',
        chief_complaint: 'Patient woke up with extremely painful right big toe - classic gout attack. Joint is red, hot and swollen. Cannot bear weight on the foot. Pain started suddenly at night (nocturnal onset).',
        lab_tests: 'Serum uric acid 9.2 mg/dL (elevated), ESR elevated, Joint fluid shows urate crystals - confirms gout',
        physical_exam: 'First MTP joint swollen, erythematous, extremely tender, limited ROM - podagra'
    },
    appendicitis: {
        symptoms: 'abdominal pain, nausea, vomiting, loss of appetite, fever',
        chief_complaint: 'Patient complains of severe pain in the right lower abdomen for 2 days. Pain started around the navel and moved to the right lower quadrant. Pain worsens with movement.',
        lab_tests: 'WBC count elevated at 14,000/µL',
        physical_exam: 'Tenderness and guarding in right lower quadrant, positive rebound tenderness, McBurney point tender'
    },
    ibs: {
        symptoms: 'abdominal pain, bloating, gas, alternating constipation and diarrhea, mucus in stool, cramping relieved by bowel movement',
        chief_complaint: 'Patient has recurrent abdominal pain and bloating for 6 months. Symptoms worse after eating. Bowel habits alternate between constipation and diarrhea. Pain typically relieved after passing stool. No blood in stool, no weight loss.',
        lab_tests: 'CBC normal, stool test negative for blood/parasites, colonoscopy normal',
        physical_exam: 'Mild abdominal tenderness, bloating, no masses palpable, normal bowel sounds'
    },
    fibromyalgia: {
        symptoms: 'widespread body pain, fatigue, sleep problems, brain fog, headaches, tender points, morning stiffness, anxiety, depression',
        chief_complaint: 'Patient has chronic widespread pain all over body for more than 3 months. Pain is worse in morning with significant stiffness. Extremely fatigued despite sleeping 8+ hours. Difficulty concentrating (brain fog). No specific injury or cause.',
        lab_tests: 'All blood tests normal - CBC, ESR, CRP, thyroid, RA factor all negative',
        physical_exam: 'Multiple tender points on examination (neck, shoulders, back, hips, knees), no joint swelling, normal range of motion, soft tissue tenderness'
    },
    anxiety: {
        symptoms: 'excessive worry, restlessness, racing heart, palpitations, shortness of breath, sweating, trembling, difficulty sleeping, irritability, muscle tension',
        chief_complaint: 'Patient experiences constant worry and anxiety about everyday matters for past 6 months. Has panic attacks with racing heart, sweating, and fear of dying. Difficulty falling asleep due to racing thoughts. Avoids social situations.',
        lab_tests: 'Thyroid function normal, ECG normal (to rule out cardiac cause)',
        physical_exam: 'Appears anxious, tremor in hands, tachycardia at rest (HR 95), sweaty palms, muscle tension in neck and shoulders'
    },
    insomnia: {
        symptoms: 'difficulty falling asleep, waking up frequently, early morning awakening, daytime fatigue, irritability, poor concentration, mood changes',
        chief_complaint: 'Patient unable to fall asleep for past 2 months. Takes 2-3 hours to fall asleep. Wakes up multiple times at night and cannot go back to sleep. Wakes up feeling unrefreshed. Affecting work performance due to fatigue.',
        lab_tests: 'Thyroid function normal, no anemia',
        physical_exam: 'Dark circles under eyes, appears fatigued, otherwise normal examination'
    },
    heart_attack: {
        symptoms: 'chest pain, shortness of breath, sweating, nausea, left arm pain, jaw pain, feeling of impending doom',
        chief_complaint: 'Patient experiencing severe crushing chest pain radiating to left arm and jaw for 30 minutes. Associated with profuse sweating and feeling of impending doom.',
        lab_tests: 'Troponin elevated, ECG shows ST elevation',
        physical_exam: 'Diaphoretic, pale, blood pressure 90/60, heart rate 110'
    },
    typhoid: {
        symptoms: 'typhoid fever, step-ladder fever, headache, abdominal pain, constipation, rose spots, coated tongue',
        chief_complaint: 'Patient has step-ladder fever pattern (typhoid) for 7 days, gradually increasing. Associated with severe headache and abdominal discomfort. Initially constipated then diarrhea.',
        lab_tests: 'Widal test positive (TO 1:160, TH 1:80), WBC normal/low, Blood culture pending - suggestive of typhoid',
        physical_exam: 'Coated tongue (typhoid tongue), relative bradycardia, soft splenomegaly, rose spots on trunk'
    },
    ringworm: {
        symptoms: 'circular red patches, ring-shaped rash, itchy skin, scaly border, clear center, spreading outward, dry flaky skin, hair loss in affected area',
        chief_complaint: 'Patient has circular, red, scaly patches on skin for 2 weeks. The patches have raised red borders with clearer center - classic ring shape. Extremely itchy, worse at night. Patches are spreading and increasing in size.',
        lab_tests: 'KOH preparation shows fungal hyphae, Wood lamp examination positive, Fungal culture positive for dermatophytes',
        physical_exam: 'Circular erythematous patches with raised scaly borders, central clearing, satellite lesions present, mild scaling'
    },
    scabies: {
        symptoms: 'intense itching worse at night, small red bumps, burrow tracks, itchy rash in skin folds, fingers web spaces affected',
        chief_complaint: 'Patient has severe itching especially at night for 3 weeks. Itchy rash in finger web spaces, wrists, and groin area. Family members also affected. Cannot sleep due to itching.',
        lab_tests: 'Skin scraping shows Sarcoptes scabiei mites under microscopy',
        physical_exam: 'Linear burrow tracks in finger webs, papules on wrists, elbows, axillae, waistline, and genitalia'
    },
    acne: {
        symptoms: 'blackheads, whiteheads, pimples, oily skin, pustules on face, scarring, cysts',
        chief_complaint: 'Teenager with multiple pimples on face, chest and back for 6 months. Has blackheads and whiteheads. Some lesions are painful and pus-filled. Very oily skin. Previous scars present.',
        lab_tests: 'Usually clinical diagnosis, hormonal panel normal',
        physical_exam: 'Open and closed comedones on face, inflammatory papules and pustules, seborrhea, few nodules on back'
    },
    common_cold: {
        symptoms: 'runny nose, sneezing, sore throat, nasal congestion, mild cough, watery eyes, headache',
        chief_complaint: 'Patient has runny nose and sneezing for 3 days. Started with sore throat, now has nasal congestion. Mild headache and watery eyes. Low-grade fever.',
        lab_tests: 'Usually not needed - clinical diagnosis',
        physical_exam: 'Nasal congestion, clear rhinorrhea, pharyngeal erythema, no exudates, no lymphadenopathy'
    },
    gastritis: {
        symptoms: 'upper abdominal pain, nausea, vomiting, loss of appetite, bloating, belching, heartburn',
        chief_complaint: 'Patient has burning pain in upper abdomen for 1 week. Pain worse after eating spicy food. Associated nausea and occasional vomiting. Lost appetite.',
        lab_tests: 'H. pylori stool antigen positive',
        physical_exam: 'Epigastric tenderness, no guarding, no rebound tenderness, normal bowel sounds'
    },
    kidney_stones: {
        symptoms: 'severe flank pain, colicky pain, pain radiating to groin, blood in urine, nausea, vomiting, frequent urination',
        chief_complaint: 'Patient has severe sharp pain in right flank since morning. Pain comes in waves (colicky) and radiates to groin. Associated nausea and vomiting. Noticed blood in urine.',
        lab_tests: 'Urinalysis shows microscopic hematuria, CT KUB shows 6mm stone in right ureter',
        physical_exam: 'Right costovertebral angle tenderness, patient restless and unable to find comfortable position'
    },
    frozen_shoulder: {
        symptoms: 'shoulder pain, progressive stiffness, difficulty raising arm, night pain, limited movement all directions',
        chief_complaint: 'Patient has gradually worsening right shoulder pain and stiffness for 4 months. Cannot raise arm above head. Difficulty reaching behind back. Pain disturbs sleep at night.',
        lab_tests: 'X-ray shoulder normal, MRI shows thickened capsule',
        physical_exam: 'Global restriction of shoulder ROM - external rotation most limited, cannot lift arm beyond 90 degrees, pain at end range'
    },
    hemorrhoids: {
        symptoms: 'rectal bleeding bright red, anal itching, pain during bowel movement, lump at anus, mucous discharge',
        chief_complaint: 'Patient notices bright red blood on toilet paper after bowel movements for 2 weeks. Has anal itching and feels a lump at anus. Pain during defecation. History of constipation.',
        lab_tests: 'CBC normal, colonoscopy shows internal hemorrhoids grade 2',
        physical_exam: 'External hemorrhoid visible at 6 o\'clock position, no thrombosis, digital rectal exam normal'
    },
    vertigo: {
        symptoms: 'spinning sensation, dizziness with position change, nausea, unsteadiness, brief episodes',
        chief_complaint: 'Patient experiences brief spinning sensation when lying down or turning head. Episodes last less than a minute. Associated nausea. Started 5 days ago.',
        lab_tests: 'Audiometry normal - no hearing loss',
        physical_exam: 'Positive Dix-Hallpike test with nystagmus, no hearing loss, normal neurological examination'
    }
};

function loadExample(type) {
    const example = examples[type];
    if (example) {
        document.getElementById('symptoms').value = example.symptoms;
        document.getElementById('chief_complaint').value = example.chief_complaint;
        document.getElementById('lab_tests').value = example.lab_tests;
        document.getElementById('physical_exam').value = example.physical_exam;
    }
}

function loadFromConsultation(consult) {
    let symptoms = [];
    if (consult.general_symptoms) symptoms.push(consult.general_symptoms);
    if (consult.particular_symptoms) symptoms.push(consult.particular_symptoms);
    
    document.getElementById('symptoms').value = symptoms.join(', ');
    document.getElementById('chief_complaint').value = consult.chief_complaint || '';
    document.getElementById('physical_exam').value = consult.physical_examination || '';
    document.getElementById('lab_tests').value = '';
}

function clearForm() {
    document.getElementById('diagnoseForm').reset();
    document.getElementById('diagnosisResults').innerHTML = `
        <div class="empty-state">
            <i class="fas fa-search-plus"></i>
            <p>Enter patient symptoms and click a diagnosis button</p>
            <small class="text-muted">
                <strong>RAG Diagnosis:</strong> Local database (offline capable)<br>
                <strong>Gemini AI:</strong> Advanced AI analysis (requires internet)
            </small>
        </div>
    `;
    document.getElementById('resultsCount').style.display = 'none';
}

// Form submission - RAG Diagnosis
document.getElementById('diagnoseForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    await performDiagnosis('rag');
});

// Gemini AI Diagnosis Button
document.getElementById('geminiDiagnoseBtn').addEventListener('click', async function() {
    await performDiagnosis('gemini');
});

// Common diagnosis function
async function performDiagnosis(provider) {
    const symptoms = document.getElementById('symptoms').value.trim();
    const chiefComplaint = document.getElementById('chief_complaint').value.trim();
    const labTests = document.getElementById('lab_tests').value.trim();
    const physicalExam = document.getElementById('physical_exam').value.trim();
    
    if (!symptoms && !chiefComplaint) {
        alert('Please enter symptoms or chief complaint');
        return;
    }
    
    const resultsDiv = document.getElementById('diagnosisResults');
    const resultsCount = document.getElementById('resultsCount');
    const ragBtn = document.getElementById('diagnoseBtn');
    const geminiBtn = document.getElementById('geminiDiagnoseBtn');
    
    // Show loading
    ragBtn.disabled = true;
    geminiBtn.disabled = true;
    
    if (provider === 'gemini') {
        geminiBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> AI Analyzing...';
        resultsDiv.innerHTML = `
            <div class="loading-state">
                <div class="loading-spinner gemini-spinner"></div>
                <p><strong>Gemini AI</strong> is analyzing your case...</p>
                <small>This may take a few seconds</small>
            </div>
        `;
    } else {
        ragBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Searching...';
        resultsDiv.innerHTML = `
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <p>Searching database for matching conditions...</p>
            </div>
        `;
    }
    
    const apiUrl = provider === 'gemini' 
        ? '<?php echo APP_URL; ?>/api/get_gemini_diagnosis.php'
        : '<?php echo APP_URL; ?>/api/get_disease_diagnosis.php';
    
    try {
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                symptoms: symptoms,
                chief_complaint: chiefComplaint,
                lab_tests: labTests,
                physical_exam: physicalExam
            })
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }
        
        const data = await response.json();
        
        console.log('Diagnosis API response:', data);
        
        if (data.success && data.diagnoses && data.diagnoses.length > 0) {
            const providerBadge = data.provider === 'gemini' 
                ? '<span class="provider-badge gemini"><i class="fas fa-robot"></i> Gemini AI</span>'
                : '<span class="provider-badge rag"><i class="fas fa-database"></i> RAG Database</span>';
            
            resultsCount.innerHTML = data.diagnoses.length + ' matches ' + providerBadge;
            resultsCount.style.display = 'inline-flex';
            resultsCount.style.alignItems = 'center';
            resultsCount.style.gap = '10px';
            
            let html = '';
            data.diagnoses.forEach((d, index) => {
                const confidenceClass = d.confidence.toLowerCase();
                const symptomsHtml = d.matching_symptoms.map(s => 
                    `<span class="symptom-tag">${s}</span>`
                ).join('');
                
                // Build remedies HTML
                let remediesHtml = '';
                if (d.homeopathic_remedies && d.homeopathic_remedies.length > 0) {
                    remediesHtml = `
                    <div class="diagnosis-section remedies-section">
                        <h5><i class="fas fa-flask"></i> Suggested Homeopathic Remedies</h5>
                        <div class="remedies-list">
                            ${d.homeopathic_remedies.map(r => `
                                <div class="remedy-item remedy-${r.indication_strength || 'primary'}">
                                    <div class="remedy-header">
                                        <strong>${r.remedy_name}</strong>
                                        ${r.common_name ? `<small class="common-name">(${r.common_name})</small>` : ''}
                                        <span class="indication-badge ${r.indication_strength || 'primary'}">${r.indication_strength || 'primary'}</span>
                                    </div>
                                    ${r.specific_indication ? `<p class="remedy-indication">${r.specific_indication}</p>` : ''}
                                    ${r.potency ? `<span class="potency-tag"><i class="fas fa-prescription"></i> ${r.potency}</span>` : ''}
                                </div>
                            `).join('')}
                        </div>
                        <div class="remedy-warning">
                            <p class="remedy-disclaimer"><i class="fas fa-exclamation-triangle"></i> <strong>IMPORTANT:</strong> These are reference suggestions only, NOT prescriptions.</p>
                            <ul class="remedy-warning-list">
                                <li>Based on classical homeopathic materia medica (Boericke, Kent, Allen)</li>
                                <li>Proper individualized case-taking is essential before prescribing</li>
                                <li>Consider patient's constitution, modalities, and mental symptoms</li>
                                <li>Potency and dosage should be determined by the treating physician</li>
                            </ul>
                        </div>
                    </div>
                    `;
                }
                
                const sourceTag = d.source === 'gemini' 
                    ? '<span class="source-tag gemini"><i class="fas fa-robot"></i></span>' 
                    : '';
                
                html += `
                    <div class="diagnosis-card ${d.source === 'gemini' ? 'gemini-result' : ''}">
                        <div class="diagnosis-card-header">
                            <h4>${index + 1}. ${d.diagnosis} ${sourceTag}</h4>
                            <span class="confidence-badge confidence-${confidenceClass}">${d.confidence} Confidence</span>
                        </div>
                        <div class="diagnosis-card-body">
                            <div class="diagnosis-section">
                                <h5><i class="fas fa-check-circle"></i> Matching Symptoms</h5>
                                <div class="symptom-tags">${symptomsHtml || '<span class="text-muted">General symptom pattern match</span>'}</div>
                            </div>
                            ${d.supporting_findings ? `
                            <div class="diagnosis-section">
                                <h5><i class="fas fa-microscope"></i> Supporting Findings</h5>
                                <p class="findings-text">${d.supporting_findings}</p>
                            </div>
                            ` : ''}
                            ${d.notes_for_doctor ? `
                            <div class="diagnosis-section">
                                <h5><i class="fas fa-user-md"></i> Notes for Doctor</h5>
                                <div class="doctor-notes">${d.notes_for_doctor}</div>
                            </div>
                            ` : ''}
                            ${remediesHtml}
                        </div>
                    </div>
                `;
            });
            
            resultsDiv.innerHTML = html;
        } else {
            resultsCount.style.display = 'none';
            let errorMsg = data.error || 'No matching conditions found';
            let hint = 'Try different symptom descriptions or add more details';
            
            resultsDiv.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <p>${errorMsg}</p>
                    <small class="text-muted">${hint}</small>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error:', error);
        resultsCount.style.display = 'none';
        resultsDiv.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-exclamation-triangle text-danger"></i>
                <p>Error occurred while searching</p>
                <small class="text-muted">${error.message}</small>
            </div>
        `;
    } finally {
        ragBtn.disabled = false;
        geminiBtn.disabled = false;
        ragBtn.innerHTML = '<i class="fas fa-database"></i> RAG Diagnosis';
        geminiBtn.innerHTML = '<i class="fas fa-robot"></i> Gemini AI';
    }
};
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
