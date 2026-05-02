<?php
// lab.php - Lab Report Upload & AI Analysis
set_time_limit(120);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/lab_ai.php';

/**
 * Security: Validate image file magic bytes for lab reports
 */
function validateLabFileMagicBytes($filePath, $ext) {
    $handle = fopen($filePath, 'rb');
    if (!$handle) return false;
    
    $bytes = fread($handle, 12);
    fclose($handle);
    
    if ($bytes === false || strlen($bytes) < 3) return false;
    
    // JPEG: FF D8 FF
    if (in_array($ext, ['jpg', 'jpeg']) && substr($bytes, 0, 3) === "\xFF\xD8\xFF") return true;
    // PNG: 89 50 4E 47 0D 0A 1A 0A
    if ($ext === 'png' && substr($bytes, 0, 8) === "\x89PNG\r\n\x1A\n") return true;
    
    return false;
}

/**
 * Security: Validate PDF file magic bytes
 */
function validatePdfMagicBytes($filePath) {
    $handle = fopen($filePath, 'rb');
    if (!$handle) return false;
    
    $bytes = fread($handle, 5);
    fclose($handle);
    
    // PDF: 25 50 44 46 2D (%PDF-)
    return $bytes !== false && substr($bytes, 0, 5) === '%PDF-';
}

// Ensure session is started with correct name
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

if (!isLoggedIn()) {
    redirect('/login.php');
}

$doctor_id = $_SESSION['doctor_id'];
$pageTitle = 'Lab Report Analysis';
$error = '';
$success = '';
$ai_suggestions = [];
$consultation = null;
$uploaded_file_info = null;

// Fetch patient list for dropdown
$patients = DB::query("SELECT id, patient_name, age, gender FROM patients WHERE doctor_id = ? ORDER BY patient_name ASC", [$doctor_id]);

// Get patient_id from GET or POST
$selected_patient_id = $_GET['patient_id'] ?? $_POST['patient_id'] ?? '';

// Get consultation_id from GET or POST
$selected_consultation_id = $_GET['consultation_id'] ?? $_POST['consultation_id'] ?? '';

// Fetch consultations for selected patient
$consultations_list = [];
if ($selected_patient_id) {
    $consultations_list = DB::query(
        "SELECT id, consultation_date, chief_complaint, diagnosis FROM consultations WHERE patient_id = ? AND doctor_id = ? ORDER BY consultation_date DESC", 
        [$selected_patient_id, $doctor_id]
    );
}

// If a consultation_id is provided, use that consultation for analysis
if ($selected_consultation_id) {
    $consultation = DB::queryOne("SELECT * FROM consultations WHERE id = ? AND doctor_id = ?", [$selected_consultation_id, $doctor_id]);
} elseif ($selected_patient_id) {
    $consultation = DB::queryOne("SELECT * FROM consultations WHERE patient_id = ? AND doctor_id = ? ORDER BY consultation_date DESC LIMIT 1", [$selected_patient_id, $doctor_id]);
} else {
    $consultation = null;
}

// Get selected patient info
$selected_patient = null;
if ($selected_patient_id) {
    $selected_patient = DB::queryOne("SELECT * FROM patients WHERE id = ? AND doctor_id = ?", [$selected_patient_id, $doctor_id]);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['lab_report'])) {
    $file = $_FILES['lab_report'];
    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
    $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    // Validate file extension
    if (!in_array($ext, $allowedExtensions)) {
        $error = 'Invalid file type. Only PDF, JPG, and PNG files are allowed.';
    }
    // Validate MIME type
    elseif (!in_array(mime_content_type($file['tmp_name']), $allowedMimeTypes)) {
        $error = 'Invalid file content. File does not appear to be a valid document.';
    }
    // Validate magic bytes for images
    elseif ($ext !== 'pdf' && !validateLabFileMagicBytes($file['tmp_name'], $ext)) {
        $error = 'Invalid file. File signature verification failed.';
    }
    // Validate PDF magic bytes
    elseif ($ext === 'pdf' && !validatePdfMagicBytes($file['tmp_name'])) {
        $error = 'Invalid PDF file. File signature verification failed.';
    }
    elseif ($file['size'] > 5 * 1024 * 1024) {
        $error = 'File too large. Maximum size is 5MB.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error occurred. Please try again.';
    } else {
        $targetDir = __DIR__ . '/uploads/lab_reports/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $filename = uniqid('lab_') . '.' . $ext;
        $targetFile = $targetDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            $success = 'Lab report uploaded successfully!';
            $uploaded_file_info = [
                'name' => $file['name'],
                'size' => round($file['size'] / 1024, 2) . ' KB',
                'type' => strtoupper($ext)
            ];
            
            // Extract text from lab report
            $lab_text = '';
            if ($ext === 'pdf') {
                try {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($targetFile);
                    $lab_text = $pdf->getText();
                } catch (Exception $e) {
                    $lab_text = 'PDF parsing error: ' . $e->getMessage();
                }
            } else {
                // Use Gemini Vision API for image OCR (much better than tesseract for lab reports)
                try {
                    $lab_text = extractLabImageTextWithGemini($targetFile);
                    
                    // Check if extraction was successful
                    if (empty($lab_text) || strpos($lab_text, 'Error:') === 0) {
                        // Fallback: try to indicate the issue
                        if (empty($lab_text)) {
                            $lab_text = 'No text could be extracted from the image. Please ensure the image is clear and readable.';
                        }
                        error_log('Lab image OCR issue: ' . $lab_text);
                    }
                } catch (Exception $e) {
                    $lab_text = 'Image OCR error: ' . $e->getMessage();
                    error_log('Lab image OCR exception: ' . $e->getMessage());
                }
            }
            
            // Clean encoding issues
            if (is_string($lab_text)) {
                $lab_text = mb_convert_encoding($lab_text, 'UTF-8', 'UTF-8');
                $lab_text = preg_replace('/[\x{FFFD}\x{0}-\x{8}\x{B}\x{C}\x{E}-\x{1F}]/u', '', $lab_text);
            }
            
            // Get consultation for analysis
            if ($selected_consultation_id) {
                $consultation = DB::queryOne(
                    "SELECT * FROM consultations WHERE id = ? AND patient_id = ? AND doctor_id = ?", 
                    [$selected_consultation_id, $selected_patient_id, $doctor_id]
                );
            } elseif ($selected_patient_id) {
                $consultation = DB::queryOne(
                    "SELECT * FROM consultations WHERE patient_id = ? AND doctor_id = ? ORDER BY consultation_date DESC LIMIT 1", 
                    [$selected_patient_id, $doctor_id]
                );
            }
            
            // Save lab report to database for future viewing
            if ($consultation) {
                try {
                    DB::insert('lab_reports', [
                        'consultation_id' => $consultation['id'],
                        'report_name' => $file['name'],
                        'report_type' => strtoupper($ext),
                        'file_path' => 'uploads/lab_reports/' . $filename,
                        'file_type' => ($ext === 'pdf') ? 'pdf' : 'image',
                        'report_date' => date('Y-m-d'),
                        'notes' => 'Uploaded via Lab Analysis'
                    ]);
                } catch (Exception $e) {
                    // Log error but continue with analysis
                    error_log('Failed to save lab report to database: ' . $e->getMessage());
                }
            }
            
            // AI analysis (with privacy check - pass doctor_id for consent verification)
            $ai_suggestions = analyzeLabAndConsultation($lab_text, $consultation, $doctor_id);
        } else {
            $error = 'Failed to upload file. Please check folder permissions.';
        }
    }
}

include 'includes/header.php';
?>

<style>
    .lab-analysis-container { position: relative; }
    .lab-analysis-container::before {
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
        .lab-analysis-container::before { left: 0; top: 60px; }
    }
</style>

<div class="lab-analysis-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-flask"></i> Lab Report Analysis</h1>
            <p class="text-muted">Upload lab reports for AI-powered analysis and remedy suggestions</p>
        </div>
        <div class="header-actions">
            <a href="<?php echo APP_URL; ?>/consultations/list.php" class="btn btn-outline">
                <i class="fas fa-stethoscope"></i> <span>Consultations</span>
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($error); ?></span>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>

    <?php if ($success && empty($ai_suggestions)): ?>
    <div class="alert alert-success alert-dismissible">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($success); ?></span>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>

    <div class="lab-grid">
        <!-- Upload Section -->
        <div class="lab-upload-section">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-cloud-upload-alt"></i> Upload Lab Report</h3>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" id="labUploadForm">
                        <!-- Patient Selection -->
                        <div class="form-group">
                            <label for="patient_id">
                                <i class="fas fa-user"></i> Select Patient
                            </label>
                            <select name="patient_id" id="patient_id" class="form-control" required>
                                <option value="">-- Choose a patient --</option>
                                <?php foreach ($patients as $p): ?>
                                <option value="<?php echo $p['id']; ?>" <?php echo ($selected_patient_id == $p['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($p['patient_name']); ?> 
                                    (<?php echo $p['age']; ?>y, <?php echo ucfirst($p['gender']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Consultation Selection -->
                        <div class="form-group" id="consultationGroup" style="<?php echo empty($consultations_list) ? 'display:none;' : ''; ?>">
                            <label for="consultation_id">
                                <i class="fas fa-clipboard-list"></i> Select Consultation
                            </label>
                            <select name="consultation_id" id="consultation_id" class="form-control">
                                <option value="">-- Latest consultation --</option>
                                <?php foreach ($consultations_list as $c): ?>
                                <option value="<?php echo $c['id']; ?>" <?php echo ($selected_consultation_id == $c['id']) ? 'selected' : ''; ?>>
                                    <?php echo date('d M Y', strtotime($c['consultation_date'])); ?> - 
                                    <?php echo htmlspecialchars(truncate($c['chief_complaint'], 40)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- File Upload Area -->
                        <div class="form-group">
                            <label><i class="fas fa-file-medical"></i> Lab Report File</label>
                            <div class="file-upload-area" id="dropZone">
                                <input type="file" name="lab_report" id="lab_report" class="file-input" accept=".pdf,.jpg,.jpeg,.png,image/*" required>
                                <div class="upload-content">
                                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                    <h4>Drag & Drop your file here</h4>
                                    <p>or click to browse / take photo</p>
                                    <span class="upload-hint">PDF, JPG, PNG - Images auto-compressed</span>
                                </div>
                                <div class="file-preview" id="filePreview" style="display: none;">
                                    <i class="fas fa-file-alt file-icon"></i>
                                    <div class="file-info">
                                        <span class="file-name" id="fileName"></span>
                                        <span class="file-size" id="fileSize"></span>
                                    </div>
                                    <button type="button" class="remove-file" id="removeFile">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary btn-block btn-lg" id="submitBtn">
                            <i class="fas fa-magic"></i> Analyze with AI
                        </button>
                    </form>
                </div>
            </div>

            <!-- Info Card -->
            <div class="dashboard-card info-card">
                <div class="card-body">
                    <h4><i class="fas fa-info-circle"></i> How it works</h4>
                    <ul class="info-list">
                        <li>
                            <span class="step-number">1</span>
                            <span>Select the patient whose lab report you're uploading</span>
                        </li>
                        <li>
                            <span class="step-number">2</span>
                            <span>Choose the relevant consultation (optional)</span>
                        </li>
                        <li>
                            <span class="step-number">3</span>
                            <span>Upload the lab report (PDF or image)</span>
                        </li>
                        <li>
                            <span class="step-number">4</span>
                            <span>Get AI-powered analysis and remedy suggestions</span>
                        </li>
                    </ul>
                    <div class="info-note">
                        <i class="fas fa-lightbulb"></i>
                        <p>The AI will analyze your lab results alongside the patient's consultation data to provide comprehensive homeopathic remedy suggestions.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div class="lab-results-section">
            <?php if (!empty($ai_suggestions) && !empty($ai_suggestions['analysis'])): ?>
            <!-- AI Analysis Results -->
            <div class="dashboard-card results-card">
                <div class="card-header results-header">
                    <h3><i class="fas fa-brain"></i> AI Analysis Results</h3>
                    <span class="badge badge-success"><i class="fas fa-check"></i> Analysis Complete</span>
                </div>
                <div class="card-body">
                    <!-- Patient & File Info -->
                    <?php if ($selected_patient): ?>
                    <div class="result-info-bar">
                        <div class="info-item">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($selected_patient['patient_name']); ?></span>
                        </div>
                        <?php if ($uploaded_file_info): ?>
                        <div class="info-item">
                            <i class="fas fa-file"></i>
                            <span><?php echo $uploaded_file_info['name']; ?> (<?php echo $uploaded_file_info['size']; ?>)</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Analysis Tabs -->
                    <div class="analysis-tabs">
                        <button class="tab-btn active" data-tab="analysis">
                            <i class="fas fa-search"></i> <span>Analysis</span>
                        </button>
                        <button class="tab-btn" data-tab="remedies">
                            <i class="fas fa-pills"></i> <span>Remedies</span>
                        </button>
                        <button class="tab-btn" data-tab="raw">
                            <i class="fas fa-file-alt"></i> <span>Raw Data</span>
                        </button>
                    </div>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- Analysis Tab -->
                        <div class="tab-pane active" id="analysis">
                            <div class="analysis-box">
                                <h4><i class="fas fa-clipboard-check"></i> AI Interpretation</h4>
                                <div class="analysis-content-wrapper">
                                    <?php 
                                    // RAG Analysis (HTML formatted)
                                    if (!empty($ai_suggestions['rag']['case_analysis'])) {
                                        echo '<div class="rag-analysis-wrapper">';
                                        echo '<div class="analysis-source-header"><i class="fas fa-database"></i> RAG Database Analysis:</div>';
                                        // Output HTML directly (already sanitized in buildRAGCaseAnalysis)
                                        echo $ai_suggestions['rag']['case_analysis'];
                                        echo '</div>';
                                    }
                                    
                                    // Gemini Analysis (plain text, needs escaping)
                                    if (!empty($ai_suggestions['gemini']['case_analysis'])) {
                                        echo '<div class="gemini-analysis-wrapper">';
                                        echo '<div class="analysis-source-header"><i class="fas fa-robot"></i> Gemini AI Analysis:</div>';
                                        echo '<div class="gemini-analysis-text">' . nl2br(htmlspecialchars($ai_suggestions['gemini']['case_analysis'])) . '</div>';
                                        echo '</div>';
                                    }
                                    
                                    // Fallback for old format
                                    if (empty($ai_suggestions['rag']['case_analysis']) && empty($ai_suggestions['gemini']['case_analysis'])) {
                                        $analysis = is_array($ai_suggestions['analysis']) 
                                            ? json_encode($ai_suggestions['analysis'], JSON_PRETTY_PRINT) 
                                            : ($ai_suggestions['analysis'] ?? 'No analysis available');
                                        echo '<div class="analysis-text">' . nl2br(htmlspecialchars($analysis)) . '</div>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Remedies Tab -->
                        <div class="tab-pane" id="remedies">
                            <?php 
                            $hasRagRemedies = !empty($ai_suggestions['rag']['remedies']);
                            $hasGeminiRemedies = !empty($ai_suggestions['gemini']['remedies']);
                            ?>
                            
                            <?php if ($hasRagRemedies || $hasGeminiRemedies): ?>
                            
                            <!-- RAG Database Remedies -->
                            <?php if ($hasRagRemedies): ?>
                            <div class="remedy-source-section">
                                <h4 class="remedy-source-title">
                                    <i class="fas fa-database"></i> RAG Database Suggestions
                                    <span class="source-badge rag-badge">Local DB</span>
                                </h4>
                                <div class="remedies-grid">
                                    <?php foreach ($ai_suggestions['rag']['remedies'] as $index => $remedy): ?>
                                    <div class="remedy-card-modern">
                                        <div class="remedy-rank-badge"><?php echo $index + 1; ?></div>
                                        <?php if (is_array($remedy)): ?>
                                        <div class="remedy-content">
                                            <h4 class="remedy-name"><?php echo htmlspecialchars($remedy['name'] ?? 'Unknown'); ?></h4>
                                            <?php if (!empty($remedy['potency'])): ?>
                                            <span class="potency-badge">
                                                <i class="fas fa-prescription-bottle"></i>
                                                <?php echo htmlspecialchars($remedy['potency']); ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if (!empty($remedy['match_percentage'])): ?>
                                            <div class="match-meter">
                                                <div class="match-label">Match Score</div>
                                                <div class="match-bar-container">
                                                    <div class="match-bar" style="width: <?php echo intval($remedy['match_percentage']); ?>%"></div>
                                                </div>
                                                <span class="match-value"><?php echo intval($remedy['match_percentage']); ?>%</span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($remedy['reasoning'])): ?>
                                            <p class="remedy-reasoning"><?php echo htmlspecialchars($remedy['reasoning']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($remedy['matched_indicators']) && is_array($remedy['matched_indicators'])): ?>
                                            <div class="matching-symptoms-list">
                                                <strong><i class="fas fa-check-circle"></i> Matched Indicators:</strong>
                                                <ul>
                                                    <?php foreach (array_slice($remedy['matched_indicators'], 0, 5) as $indicator): ?>
                                                    <li><?php echo htmlspecialchars($indicator); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                        <div class="remedy-content">
                                            <p><?php echo htmlspecialchars($remedy); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="remedy-source-section">
                                <h4 class="remedy-source-title">
                                    <i class="fas fa-database"></i> RAG Database Suggestions
                                    <span class="source-badge rag-badge">Local DB</span>
                                </h4>
                                <div class="empty-state-small">
                                    <i class="fas fa-info-circle"></i>
                                    <p>No matching remedies found in local database for these lab indicators.</p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Gemini AI Remedies -->
                            <?php if ($hasGeminiRemedies): ?>
                            <div class="remedy-source-section">
                                <h4 class="remedy-source-title">
                                    <i class="fas fa-robot"></i> Gemini AI Suggestions
                                    <span class="source-badge gemini-badge">AI</span>
                                </h4>
                                <div class="remedies-grid">
                                    <?php foreach ($ai_suggestions['gemini']['remedies'] as $index => $remedy): ?>
                                    <div class="remedy-card-modern">
                                        <div class="remedy-rank-badge"><?php echo $index + 1; ?></div>
                                        <?php if (is_array($remedy)): ?>
                                        <div class="remedy-content">
                                            <h4 class="remedy-name"><?php echo htmlspecialchars($remedy['name'] ?? 'Unknown'); ?></h4>
                                            <?php if (!empty($remedy['potency'])): ?>
                                            <span class="potency-badge">
                                                <i class="fas fa-prescription-bottle"></i>
                                                <?php echo htmlspecialchars($remedy['potency']); ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if (!empty($remedy['match_percentage'])): ?>
                                            <div class="match-meter">
                                                <div class="match-label">Match Score</div>
                                                <div class="match-bar-container">
                                                    <div class="match-bar" style="width: <?php echo intval($remedy['match_percentage']); ?>%"></div>
                                                </div>
                                                <span class="match-value"><?php echo intval($remedy['match_percentage']); ?>%</span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($remedy['reasoning'])): ?>
                                            <p class="remedy-reasoning"><?php echo htmlspecialchars($remedy['reasoning']); ?></p>
                                            <?php endif; ?>
                                            <?php if (!empty($remedy['reference'])): ?>
                                            <div class="remedy-reference">
                                                <i class="fas fa-book"></i>
                                                <?php echo htmlspecialchars($remedy['reference']); ?>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($remedy['matching_symptoms']) && is_array($remedy['matching_symptoms'])): ?>
                                            <div class="matching-symptoms-list">
                                                <strong><i class="fas fa-check-circle"></i> Matching Symptoms:</strong>
                                                <ul>
                                                    <?php foreach (array_slice($remedy['matching_symptoms'], 0, 5) as $symptom): ?>
                                                    <li><?php echo htmlspecialchars($symptom); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                        <div class="remedy-content">
                                            <p><?php echo htmlspecialchars($remedy); ?></p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-pills"></i>
                                <p>No specific remedies suggested</p>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Raw Data Tab -->
                        <div class="tab-pane" id="raw">
                            <div class="raw-data-grid">
                                <div class="raw-data-box">
                                    <h5><i class="fas fa-file-medical-alt"></i> Extracted Lab Text</h5>
                                    <pre class="raw-content"><?php 
                                        $labText = is_array($ai_suggestions['lab_text']) 
                                            ? json_encode($ai_suggestions['lab_text'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) 
                                            : $ai_suggestions['lab_text'];
                                        echo htmlspecialchars($labText);
                                    ?></pre>
                                </div>
                                <?php if (!empty($ai_suggestions['consultation'])): ?>
                                <div class="raw-data-box">
                                    <h5><i class="fas fa-stethoscope"></i> Consultation Data</h5>
                                    <pre class="raw-content"><?php 
                                        $consultData = is_array($ai_suggestions['consultation']) 
                                            ? json_encode($ai_suggestions['consultation'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) 
                                            : $ai_suggestions['consultation'];
                                        echo htmlspecialchars($consultData);
                                    ?></pre>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Disclaimer -->
                    <div class="disclaimer-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p><strong>Disclaimer:</strong> These AI-generated suggestions are for educational and reference purposes only. Always verify recommendations against authoritative homeopathic texts and consider individual patient characteristics before prescribing.</p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Empty State -->
            <div class="dashboard-card empty-results-card">
                <div class="card-body">
                    <div class="empty-illustration">
                        <i class="fas fa-microscope"></i>
                    </div>
                    <h3>No Analysis Yet</h3>
                    <p>Upload a lab report to get AI-powered analysis and homeopathic remedy suggestions based on the patient's data.</p>
                    <div class="features-preview">
                        <div class="feature-item">
                            <i class="fas fa-robot"></i>
                            <span>AI-Powered Analysis</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-pills"></i>
                            <span>Remedy Suggestions</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-chart-line"></i>
                            <span>Match Scoring</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div class="page-loader hidden" id="pageLoader">
    <div class="ai-brain-loader">
        <i class="fas fa-brain brain-icon"></i>
        <div class="loader-title" id="loaderTitle">Analyzing Lab Report</div>
        <div class="loader-subtitle" id="loaderSubtitle">This may take a few moments...</div>
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        <div class="loader-steps" id="loaderSteps">
            <div class="step" id="step1"><i class="fas fa-circle-notch fa-spin"></i> Uploading file...</div>
            <div class="step" id="step2"><i class="fas fa-circle"></i> Extracting text from report...</div>
            <div class="step" id="step3"><i class="fas fa-circle"></i> Analyzing with local database...</div>
            <div class="step" id="step4"><i class="fas fa-circle"></i> Getting AI suggestions...</div>
        </div>
        <div class="loader-timeout-msg hidden" id="loaderTimeoutMsg">
            <i class="fas fa-exclamation-triangle"></i>
            <span>Taking longer than expected. Please wait...</span>
        </div>
        <button type="button" class="btn btn-outline btn-sm hidden" id="cancelBtn" onclick="cancelAnalysis()">
            <i class="fas fa-times"></i> Cancel
        </button>
    </div>
</div>

<style>
/* Lab Analysis Page Styles */
.lab-analysis-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px;
}

.lab-grid {
    display: grid;
    grid-template-columns: 400px 1fr;
    gap: 24px;
    align-items: start;
}

/* Upload Section */
.lab-upload-section {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* File Upload Area */
.file-upload-area {
    position: relative;
    border: 2px dashed var(--gray-300);
    border-radius: var(--border-radius-lg);
    padding: 40px 20px;
    text-align: center;
    background: var(--gray-50);
    transition: all var(--transition);
    cursor: pointer;
}

.file-upload-area:hover,
.file-upload-area.drag-over {
    border-color: var(--primary-500);
    background: var(--primary-50);
}

.file-upload-area .file-input {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.upload-content {
    pointer-events: none;
}

.upload-content .upload-icon {
    font-size: 3rem;
    color: var(--primary-400);
    margin-bottom: 16px;
}

.upload-content h4 {
    margin: 0 0 8px;
    color: var(--gray-700);
    font-size: 1.1rem;
}

.upload-content p {
    margin: 0;
    color: var(--gray-500);
    font-size: 0.9rem;
}

.upload-hint {
    display: inline-block;
    margin-top: 12px;
    padding: 6px 12px;
    background: var(--white);
    border-radius: 20px;
    font-size: 0.8rem;
    color: var(--gray-600);
    border: 1px solid var(--gray-200);
}

/* File Preview */
.file-preview {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: var(--white);
    border-radius: var(--border-radius);
    border: 1px solid var(--gray-200);
}

.file-preview .file-icon {
    font-size: 2rem;
    color: var(--primary-500);
}

.file-preview .file-info {
    flex: 1;
    text-align: left;
}

.file-preview .file-name {
    display: block;
    font-weight: 600;
    color: var(--gray-800);
    word-break: break-all;
}

.file-preview .file-size {
    font-size: 0.85rem;
    color: var(--gray-500);
}

.file-preview .remove-file {
    width: 32px;
    height: 32px;
    border: none;
    background: var(--danger-50);
    color: var(--danger-500);
    border-radius: 50%;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.file-preview .remove-file:hover {
    background: var(--danger-500);
    color: var(--white);
}

/* Info Card */
.info-card .card-body h4 {
    margin: 0 0 20px;
    color: var(--primary-600);
    font-size: 1rem;
}

.info-list {
    list-style: none;
    padding: 0;
    margin: 0 0 20px;
}

.info-list li {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 16px;
    font-size: 0.9rem;
    color: var(--gray-700);
}

.step-number {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary-500), var(--secondary-500));
    color: var(--white);
    border-radius: 50%;
    font-size: 0.8rem;
    font-weight: 600;
}

.info-note {
    display: flex;
    gap: 12px;
    padding: 16px;
    background: var(--info-50);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--info-500);
}

.info-note i {
    color: var(--info-500);
    flex-shrink: 0;
    margin-top: 2px;
}

.info-note p {
    margin: 0;
    font-size: 0.85rem;
    color: var(--gray-600);
    line-height: 1.6;
}

/* Results Section */
.results-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.result-info-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    padding: 16px;
    background: var(--gray-50);
    border-radius: var(--border-radius);
    margin-bottom: 20px;
}

.result-info-bar .info-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
    color: var(--gray-700);
}

.result-info-bar .info-item i {
    color: var(--primary-500);
}

/* Analysis Tabs */
.analysis-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--gray-200);
    padding-bottom: 0;
}

.tab-btn {
    padding: 12px 20px;
    background: transparent;
    border: none;
    color: var(--gray-600);
    font-size: 0.9rem;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all var(--transition-fast);
}

.tab-btn:hover {
    color: var(--primary-600);
}

.tab-btn.active {
    color: var(--primary-600);
    border-bottom-color: var(--primary-600);
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Loader Steps */
.loader-steps {
    margin-top: 20px;
    text-align: left;
    max-width: 280px;
}

.loader-steps .step {
    padding: 8px 0;
    font-size: 0.9rem;
    color: var(--gray-400);
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.3s ease;
}

.loader-steps .step i {
    width: 16px;
    text-align: center;
    font-size: 0.75rem;
}

.loader-steps .step.active {
    color: var(--primary-600);
    font-weight: 500;
}

.loader-steps .step.active i {
    color: var(--primary-500);
}

.loader-steps .step.completed {
    color: var(--success-600);
}

.loader-steps .step.completed i {
    color: var(--success-500);
}

.loader-timeout-msg {
    margin-top: 20px;
    padding: 12px 16px;
    background: var(--warning-50);
    border: 1px solid var(--warning-200);
    border-radius: var(--border-radius);
    color: var(--warning-700);
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.loader-timeout-msg.hidden {
    display: none;
}

.loader-timeout-msg i {
    color: var(--warning-500);
}

#cancelBtn {
    margin-top: 16px;
}

#cancelBtn.hidden {
    display: none;
}

/* Analysis Box */
.analysis-box {
    background: linear-gradient(135deg, var(--primary-50), var(--white));
    border-radius: var(--border-radius-lg);
    padding: 24px;
    border-left: 4px solid var(--primary-500);
}

.analysis-box h4 {
    margin: 0 0 16px;
    color: var(--primary-700);
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.analysis-text {
    font-size: 0.95rem;
    line-height: 1.8;
    color: var(--gray-700);
}

/* ============== NEW RAG ANALYSIS STYLES ============== */
.analysis-content-wrapper {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.analysis-source-header {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--primary-600);
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--primary-200);
    display: flex;
    align-items: center;
    gap: 8px;
}

.gemini-analysis-wrapper {
    background: linear-gradient(135deg, #f0fdf4, #fff);
    border-radius: var(--border-radius-lg);
    padding: 20px;
    border-left: 4px solid #22c55e;
}

.gemini-analysis-text {
    font-size: 0.95rem;
    line-height: 1.8;
    color: var(--gray-700);
}

/* RAG Analysis Container */
.rag-analysis-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.analysis-section {
    background: var(--white);
    border-radius: var(--border-radius-lg);
    padding: 20px;
    border: 1px solid var(--gray-200);
}

.section-title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--gray-800);
    margin: 0 0 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-title i {
    color: var(--primary-500);
}

/* Lab Values Grid */
.lab-values-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 16px;
    margin-top: 16px;
}

.lab-value-card {
    background: var(--white);
    border-radius: var(--border-radius);
    padding: 16px;
    border: 2px solid var(--gray-200);
    transition: all var(--transition);
}

.lab-value-card:hover {
    box-shadow: var(--shadow-md);
}

/* Value Status Styles */
.lab-value-card.value-low {
    border-color: #3b82f6;
    background: linear-gradient(135deg, #eff6ff, #fff);
}

.lab-value-card.value-high {
    border-color: #ef4444;
    background: linear-gradient(135deg, #fef2f2, #fff);
}

.lab-value-card.severity-critical {
    border-width: 3px;
    animation: pulse-critical 2s infinite;
}

.lab-value-card.severity-high {
    border-width: 2px;
}

@keyframes pulse-critical {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
}

.lab-value-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.param-name {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.95rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.value-low {
    background: #3b82f6;
    color: white;
}

.status-badge.value-high {
    background: #ef4444;
    color: white;
}

.lab-value-body {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.current-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--gray-900);
}

.reference-range {
    font-size: 0.85rem;
    color: var(--gray-500);
    display: flex;
    align-items: center;
    gap: 6px;
}

.severity-alert {
    margin-top: 12px;
    padding: 8px 12px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: var(--border-radius-sm);
    color: #b91c1c;
    font-size: 0.8rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.severity-warning {
    margin-top: 12px;
    padding: 8px 12px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: var(--border-radius-sm);
    color: #b45309;
    font-size: 0.8rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Alert Badges */
.alert-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: var(--border-radius);
    font-weight: 600;
    font-size: 0.9rem;
}

.alert-badge.warning {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #92400e;
    border: 1px solid #f59e0b;
}

.alert-badge.success {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #065f46;
    border: 1px solid #10b981;
}

/* Normal Values */
.normal-values-container {
    text-align: center;
    padding: 20px;
}

.normal-message {
    color: var(--gray-600);
    margin-top: 12px;
}

/* Clinical Alerts */
.clinical-warnings-section .section-title {
    color: #d97706;
}

.clinical-alert {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    border-radius: var(--border-radius);
    margin-bottom: 12px;
}

.clinical-alert.alert-critical {
    background: #fef2f2;
    border: 2px solid #ef4444;
    color: #b91c1c;
}

.clinical-alert.alert-warning {
    background: #fffbeb;
    border: 1px solid #f59e0b;
    color: #92400e;
}

.clinical-alert i {
    font-size: 1.2rem;
    flex-shrink: 0;
    margin-top: 2px;
}

.alert-content {
    flex: 1;
    font-size: 0.9rem;
    line-height: 1.6;
}

/* Chief Complaint Section */
.complaint-details {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.detail-row {
    display: flex;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-100);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    min-width: 180px;
    font-weight: 500;
    color: var(--gray-600);
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.9rem;
}

.detail-label i {
    color: var(--primary-400);
    width: 16px;
}

.detail-value {
    flex: 1;
    color: var(--gray-800);
    font-size: 0.95rem;
}

.detail-value.primary-complaint {
    font-weight: 600;
    color: var(--primary-700);
}

.detail-value.modalities {
    font-style: italic;
}

.thermal-badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
}

.thermal-badge.thermal-hot {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    color: #b45309;
}

.thermal-badge.thermal-cold {
    background: linear-gradient(135deg, #e0f2fe, #bae6fd);
    color: #0369a1;
}

.thermal-badge.thermal-ambithermal {
    background: linear-gradient(135deg, #f3e8ff, #e9d5ff);
    color: #7c3aed;
}

/* Rationale Grid */
.rationale-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 16px;
}

.rationale-card {
    padding: 16px;
    border-radius: var(--border-radius);
    border: 1px solid var(--gray-200);
}

.rationale-card.symptom-based {
    background: linear-gradient(135deg, #eff6ff, #fff);
    border-color: #3b82f6;
}

.rationale-card.lab-based {
    background: linear-gradient(135deg, #fef3c7, #fff);
    border-color: #f59e0b;
}

.rationale-header {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--gray-700);
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.remedy-names {
    font-weight: 700;
    color: var(--primary-600);
    font-size: 1rem;
    margin-bottom: 8px;
}

.rationale-note {
    font-size: 0.8rem;
    color: var(--gray-500);
}

/* Important Note */
.important-note {
    display: flex;
    gap: 16px;
    padding: 16px;
    background: linear-gradient(135deg, #fef3c7, #fff);
    border: 1px solid #f59e0b;
    border-radius: var(--border-radius);
    margin-top: 16px;
}

.important-note i {
    color: #f59e0b;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.note-content {
    flex: 1;
}

.note-content strong {
    color: #92400e;
}

.note-content ol {
    margin: 8px 0 0 20px;
    padding: 0;
    color: var(--gray-700);
    font-size: 0.9rem;
}

.note-content ol li {
    margin: 4px 0;
}

/* Disclaimer */
.analysis-disclaimer {
    padding: 12px 16px;
    background: var(--gray-50);
    border-radius: var(--border-radius);
    font-size: 0.85rem;
    color: var(--gray-500);
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.analysis-disclaimer i {
    color: var(--gray-400);
    margin-top: 2px;
}

/* ============================================================================
   CLINICAL INTELLIGENCE STYLES
   ============================================================================ */

/* Severity Overview Section */
.severity-overview-section {
    background: linear-gradient(135deg, #fdf4ff, #fff);
    border-color: #a855f7;
}

.severity-dashboard {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.severity-gauge {
    position: relative;
    height: 40px;
    background: var(--gray-200);
    border-radius: 20px;
    overflow: hidden;
}

.gauge-fill {
    position: absolute;
    top: 0;
    left: 0;
    height: 100%;
    border-radius: 20px;
    transition: width 1s ease;
}

.severity-gauge.severity-mild .gauge-fill {
    background: linear-gradient(90deg, #22c55e, #86efac);
}

.severity-gauge.severity-moderate .gauge-fill {
    background: linear-gradient(90deg, #f59e0b, #fcd34d);
}

.severity-gauge.severity-critical .gauge-fill {
    background: linear-gradient(90deg, #ef4444, #f87171);
}

.gauge-label {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: flex;
    align-items: baseline;
    gap: 8px;
    font-weight: 600;
    color: var(--gray-800);
    text-shadow: 0 1px 2px rgba(255,255,255,0.8);
}

.gauge-label .score {
    font-size: 1.2rem;
}

.gauge-label .label {
    font-size: 0.85rem;
    font-weight: 500;
}

.severity-breakdown {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.breakdown-item {
    padding: 6px 12px;
    background: var(--gray-100);
    border-radius: 20px;
    font-size: 0.8rem;
    color: var(--gray-600);
    display: flex;
    align-items: center;
    gap: 6px;
}

.critical-findings {
    padding: 12px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: var(--border-radius);
    color: #b91c1c;
    font-size: 0.85rem;
}

/* Comorbidity Section */
.comorbidity-section {
    background: linear-gradient(135deg, #fef3c7, #fff);
    border-color: #f59e0b;
}

.risk-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: var(--border-radius);
    font-size: 0.9rem;
    margin-bottom: 16px;
}

.risk-badge.risk-low {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #22c55e;
}

.risk-badge.risk-moderate {
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #f59e0b;
}

.risk-badge.risk-high {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #ef4444;
}

.comorbidity-list {
    margin-bottom: 16px;
}

.condition-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.condition-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: var(--white);
    border: 1px solid var(--gray-200);
    border-radius: 20px;
    font-size: 0.85rem;
    color: var(--gray-700);
}

.condition-tag i {
    color: #22c55e;
}

/* Clinical Patterns */
.clinical-patterns {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.pattern-card {
    padding: 16px;
    border-radius: var(--border-radius);
    border: 2px solid var(--gray-200);
}

.pattern-card.pattern-critical {
    border-color: #ef4444;
    background: linear-gradient(135deg, #fef2f2, #fff);
}

.pattern-card.pattern-high {
    border-color: #f59e0b;
    background: linear-gradient(135deg, #fffbeb, #fff);
}

.pattern-card.pattern-moderate {
    border-color: #3b82f6;
    background: linear-gradient(135deg, #eff6ff, #fff);
}

.pattern-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.pattern-header strong {
    flex: 1;
    color: var(--gray-800);
}

.pattern-severity {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
}

.pattern-critical .pattern-severity {
    background: #ef4444;
    color: white;
}

.pattern-high .pattern-severity {
    background: #f59e0b;
    color: white;
}

.pattern-moderate .pattern-severity {
    background: #3b82f6;
    color: white;
}

.pattern-components {
    color: var(--gray-600);
    margin-bottom: 8px;
}

.pattern-implications {
    padding: 8px 12px;
    background: rgba(0,0,0,0.05);
    border-radius: var(--border-radius-sm);
    font-size: 0.85rem;
    color: var(--gray-700);
    margin: 8px 0;
}

.pattern-remedies {
    color: var(--primary-600);
    font-size: 0.85rem;
}

/* Physical Exam Section */
.physical-exam-section {
    background: linear-gradient(135deg, #ecfdf5, #fff);
    border-color: #10b981;
}

.vital-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.vital-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: var(--white);
    border-radius: var(--border-radius);
    border: 2px solid var(--gray-200);
}

.vital-card.vital-normal {
    border-color: #22c55e;
}

.vital-card.vital-warning {
    border-color: #f59e0b;
    background: linear-gradient(135deg, #fffbeb, #fff);
}

.vital-card.vital-critical {
    border-color: #ef4444;
    background: linear-gradient(135deg, #fef2f2, #fff);
}

.vital-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--gray-100);
    border-radius: 50%;
    font-size: 1rem;
    color: var(--gray-600);
}

.vital-card.vital-warning .vital-icon {
    background: #fef3c7;
    color: #f59e0b;
}

.vital-card.vital-critical .vital-icon {
    background: #fee2e2;
    color: #ef4444;
}

.vital-content {
    flex: 1;
}

.vital-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--gray-800);
}

.vital-label {
    font-size: 0.8rem;
    color: var(--gray-500);
}

.clinical-implications {
    background: var(--gray-50);
    padding: 12px 16px;
    border-radius: var(--border-radius);
}

.clinical-implications ul {
    margin: 8px 0 0 20px;
    padding: 0;
    color: var(--gray-700);
    font-size: 0.9rem;
}

.clinical-implications ul li {
    margin: 4px 0;
}

/* Constitutional Profile Section */
.constitutional-section {
    background: linear-gradient(135deg, #f0fdf4, #fff);
    border-color: #22c55e;
}

.constitutional-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.constitution-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 12px;
    background: var(--white);
    border-radius: var(--border-radius);
    border: 1px solid var(--gray-200);
    text-align: center;
}

.constitution-item i {
    font-size: 1.5rem;
    color: var(--gray-400);
}

.constitution-item.thermal-hot i {
    color: #f59e0b;
}

.constitution-item.thermal-cold i {
    color: #3b82f6;
}

.const-label {
    font-size: 0.75rem;
    color: var(--gray-500);
    text-transform: uppercase;
    font-weight: 500;
}

.const-value {
    font-weight: 600;
    color: var(--gray-800);
    font-size: 0.9rem;
}

.constitutional-type {
    padding: 12px;
    background: var(--gray-50);
    border-radius: var(--border-radius);
    margin-bottom: 8px;
}

.miasmatic-tendency, .constitutional-remedies {
    padding: 8px 12px;
    color: var(--gray-600);
}

/* Compliance Section */
.compliance-section {
    background: linear-gradient(135deg, #fff7ed, #fff);
    border-color: #fb923c;
}

.compliance-gauge {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
}

.gauge-circle {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    border: 4px solid;
}

.compliance-good .gauge-circle {
    border-color: #22c55e;
    background: #dcfce7;
}

.compliance-moderate .gauge-circle {
    border-color: #f59e0b;
    background: #fef3c7;
}

.compliance-poor .gauge-circle {
    border-color: #ef4444;
    background: #fee2e2;
}

.gauge-value {
    font-weight: 700;
    font-size: 0.9rem;
    color: var(--gray-800);
}

.gauge-info strong {
    display: block;
    color: var(--gray-700);
}

.compliance-issues, .lifestyle-factors, .stress-indicators, .compliance-recommendations {
    margin-bottom: 16px;
}

.issue-tags, .factor-tags, .stress-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 8px;
}

.issue-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #fee2e2;
    border: 1px solid #fecaca;
    border-radius: 20px;
    font-size: 0.8rem;
    color: #b91c1c;
}

.factor-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #fef3c7;
    border: 1px solid #fde68a;
    border-radius: 20px;
    font-size: 0.8rem;
    color: #92400e;
}

.stress-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #fce7f3;
    border: 1px solid #fbcfe8;
    border-radius: 20px;
    font-size: 0.8rem;
    color: #be185d;
}

.compliance-recommendations ul {
    margin: 8px 0 0 20px;
    padding: 0;
}

.compliance-recommendations ul li {
    margin: 4px 0;
    color: var(--gray-700);
    font-size: 0.9rem;
}

/* ============================================================================
   END CLINICAL INTELLIGENCE STYLES
   ============================================================================ */

/* Remedies Grid */
.remedies-grid {
    display: grid;
    gap: 16px;
}

.remedy-card-modern {
    position: relative;
    background: var(--white);
    border-radius: var(--border-radius-lg);
    padding: 20px 20px 20px 60px;
    border: 1px solid var(--gray-200);
    transition: all var(--transition);
}

.remedy-card-modern:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

.remedy-rank-badge {
    position: absolute;
    left: 16px;
    top: 20px;
    width: 36px;
    height: 36px;
    background: linear-gradient(135deg, var(--primary-500), var(--secondary-500));
    color: var(--white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
}

.remedy-content h4.remedy-name {
    margin: 0 0 8px;
    color: var(--gray-800);
    font-size: 1.1rem;
}

.potency-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    background: var(--secondary-500);
    color: var(--white);
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Match Meter */
.match-meter {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 12px 0;
}

.match-label {
    font-size: 0.8rem;
    color: var(--gray-600);
    white-space: nowrap;
}

.match-bar-container {
    flex: 1;
    height: 8px;
    background: var(--gray-200);
    border-radius: 4px;
    overflow: hidden;
}

.match-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--success-500), var(--primary-500));
    border-radius: 4px;
    transition: width 0.5s ease;
}

.match-value {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--primary-600);
}

.remedy-reasoning {
    margin: 12px 0;
    font-size: 0.9rem;
    color: var(--gray-600);
    line-height: 1.6;
}

.remedy-reference {
    font-size: 0.85rem;
    color: var(--gray-500);
    display: flex;
    align-items: center;
    gap: 8px;
}

.matching-symptoms-list {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed var(--gray-200);
}

.matching-symptoms-list strong {
    font-size: 0.85rem;
    color: var(--success-600);
    display: flex;
    align-items: center;
    gap: 6px;
}

.matching-symptoms-list ul {
    margin: 8px 0 0;
    padding-left: 20px;
    font-size: 0.85rem;
    color: var(--gray-600);
}

.matching-symptoms-list li {
    margin-bottom: 4px;
}

/* Remedy Source Sections */
.remedy-source-section {
    margin-bottom: 24px;
    padding: 16px;
    background: var(--gray-50);
    border-radius: var(--border-radius-lg);
    border: 1px solid var(--gray-200);
}

.remedy-source-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 16px 0;
    font-size: 1rem;
    color: var(--gray-700);
}

.source-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.rag-badge {
    background: linear-gradient(135deg, #059669, #10b981);
    color: white;
}

.gemini-badge {
    background: linear-gradient(135deg, #7c3aed, #a78bfa);
    color: white;
}

.empty-state-small {
    padding: 16px;
    text-align: center;
    color: var(--gray-500);
}

.empty-state-small i {
    font-size: 1.5rem;
    margin-bottom: 8px;
    display: block;
}

.empty-state-small p {
    margin: 0;
    font-size: 0.9rem;
}

/* Raw Data */
.raw-data-grid {
    display: grid;
    gap: 16px;
}

.raw-data-box {
    background: var(--gray-50);
    border-radius: var(--border-radius);
    overflow: hidden;
}

.raw-data-box h5 {
    margin: 0;
    padding: 12px 16px;
    background: var(--gray-100);
    color: var(--gray-700);
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

.raw-content {
    padding: 16px;
    margin: 0;
    font-size: 0.85rem;
    white-space: pre-wrap;
    word-break: break-word;
    max-height: 300px;
    overflow-y: auto;
    background: var(--gray-800);
    color: var(--gray-100);
    font-family: 'Fira Code', 'Consolas', monospace;
}

/* Disclaimer */
.disclaimer-box {
    display: flex;
    gap: 12px;
    padding: 16px;
    background: var(--warning-50);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--warning-500);
    margin-top: 20px;
}

.disclaimer-box i {
    color: var(--warning-600);
    flex-shrink: 0;
}

.disclaimer-box p {
    margin: 0;
    font-size: 0.85rem;
    color: var(--gray-700);
    line-height: 1.5;
}

/* Empty Results Card */
.empty-results-card .card-body {
    text-align: center;
    padding: 60px 40px;
}

.empty-illustration {
    width: 120px;
    height: 120px;
    margin: 0 auto 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary-100), var(--secondary-100));
    border-radius: 50%;
}

.empty-illustration i {
    font-size: 3rem;
    background: linear-gradient(135deg, var(--primary-500), var(--secondary-500));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.empty-results-card h3 {
    margin: 0 0 12px;
    color: var(--gray-800);
}

.empty-results-card p {
    color: var(--gray-600);
    max-width: 400px;
    margin: 0 auto 32px;
}

.features-preview {
    display: flex;
    justify-content: center;
    gap: 32px;
}

.feature-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.feature-item i {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--gray-100);
    color: var(--primary-500);
    border-radius: var(--border-radius);
    font-size: 1.2rem;
}

.feature-item span {
    font-size: 0.85rem;
    color: var(--gray-600);
}

/* Alert Styles */
.alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px 20px;
    border-radius: var(--border-radius);
    margin-bottom: 20px;
}

.alert-danger {
    background: var(--danger-50);
    border: 1px solid var(--danger-200);
    color: var(--danger-700);
}

.alert-success {
    background: var(--success-50);
    border: 1px solid var(--success-200);
    color: var(--success-700);
}

.alert-dismissible {
    position: relative;
    padding-right: 50px;
}

.alert-close {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: transparent;
    border: none;
    cursor: pointer;
    color: inherit;
    opacity: 0.7;
}

.alert-close:hover {
    opacity: 1;
}

/* Button Block & Large */
.btn-block {
    display: block;
    width: 100%;
}

.btn-lg {
    padding: 14px 24px;
    font-size: 1rem;
}

/* ============================================
   RESPONSIVE STYLES
   ============================================ */
@media (max-width: 1200px) {
    .lab-grid {
        grid-template-columns: 350px 1fr;
    }
}

@media (max-width: 991px) {
    .lab-grid {
        grid-template-columns: 1fr;
    }
    
    .lab-upload-section {
        order: 1;
    }
    
    .lab-results-section {
        order: 2;
    }
}

@media (max-width: 768px) {
    .lab-analysis-container {
        padding: 16px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .header-actions span {
        display: none;
    }
    
    .analysis-tabs {
        flex-wrap: wrap;
    }
    
    .tab-btn {
        flex: 1;
        justify-content: center;
        min-width: 100px;
    }
    
    .result-info-bar {
        flex-direction: column;
        gap: 12px;
    }
    
    .features-preview {
        flex-direction: column;
        gap: 16px;
    }
    
    .empty-results-card .card-body {
        padding: 40px 20px;
    }
}

@media (max-width: 576px) {
    .lab-analysis-container {
        padding: 12px;
    }
    
    .page-header h1 {
        font-size: 1.4rem;
    }
    
    .file-upload-area {
        padding: 30px 15px;
    }
    
    .upload-content .upload-icon {
        font-size: 2.5rem;
    }
    
    .tab-btn {
        padding: 10px 12px;
        font-size: 0.85rem;
    }
    
    .tab-btn span {
        display: none;
    }
    
    .analysis-box {
        padding: 16px;
    }
    
    .remedy-card-modern {
        padding: 16px 16px 16px 50px;
    }
    
    .remedy-rank-badge {
        width: 28px;
        height: 28px;
        font-size: 0.85rem;
        left: 12px;
        top: 16px;
    }
    
    .match-meter {
        flex-wrap: wrap;
    }
    
    .raw-content {
        font-size: 0.75rem;
        max-height: 200px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const form = document.getElementById('labUploadForm');
    const patientSelect = document.getElementById('patient_id');
    const consultationGroup = document.getElementById('consultationGroup');
    const consultationSelect = document.getElementById('consultation_id');
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('lab_report');
    const uploadContent = dropZone.querySelector('.upload-content');
    const filePreview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const removeFile = document.getElementById('removeFile');
    const submitBtn = document.getElementById('submitBtn');
    const pageLoader = document.getElementById('pageLoader');
    
    // Store compressed file for upload
    let compressedFile = null;
    
    // Image compression function for mobile camera images
    async function compressImage(file, maxWidth = 1600, maxHeight = 1600, quality = 0.8) {
        return new Promise((resolve, reject) => {
            // Only compress images, not PDFs
            if (!file.type.startsWith('image/')) {
                resolve(file);
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = new Image();
                img.onload = function() {
                    let width = img.width;
                    let height = img.height;
                    
                    // Calculate new dimensions while maintaining aspect ratio
                    if (width > maxWidth || height > maxHeight) {
                        const ratio = Math.min(maxWidth / width, maxHeight / height);
                        width = Math.round(width * ratio);
                        height = Math.round(height * ratio);
                    }
                    
                    // Create canvas and draw resized image
                    const canvas = document.createElement('canvas');
                    canvas.width = width;
                    canvas.height = height;
                    const ctx = canvas.getContext('2d');
                    
                    // Use better quality for downscaling
                    ctx.imageSmoothingEnabled = true;
                    ctx.imageSmoothingQuality = 'high';
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    // Convert to blob
                    canvas.toBlob(function(blob) {
                        if (blob) {
                            // Create new file with same name
                            const compressedFile = new File([blob], file.name, {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });
                            console.log('Image compressed:', (file.size / 1024).toFixed(1) + 'KB ->', (compressedFile.size / 1024).toFixed(1) + 'KB');
                            resolve(compressedFile);
                        } else {
                            resolve(file); // Return original if compression fails
                        }
                    }, 'image/jpeg', quality);
                };
                img.onerror = () => resolve(file);
                img.src = e.target.result;
            };
            reader.onerror = () => resolve(file);
            reader.readAsDataURL(file);
        });
    }
    
    // Tab functionality
    const tabBtns = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            
            tabBtns.forEach(b => b.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById(tabId)?.classList.add('active');
        });
    });
    
    // Patient selection - fetch consultations
    patientSelect.addEventListener('change', function() {
        const patientId = this.value;
        if (patientId) {
            // Redirect to fetch consultations
            window.location.href = `<?php echo APP_URL; ?>/lab.php?patient_id=${patientId}`;
        } else {
            consultationGroup.style.display = 'none';
        }
    });
    
    // File drag and drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.add('drag-over');
        }, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => {
            dropZone.classList.remove('drag-over');
        }, false);
    });
    
    dropZone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length > 0) {
            fileInput.files = files;
            handleFileSelect(files[0]);
        }
    });
    
    // File input change
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            handleFileSelect(this.files[0]);
        }
    });
    
    async function handleFileSelect(file) {
        const validTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        const maxSizeRaw = 20 * 1024 * 1024; // 20MB raw (before compression)
        const maxSizeFinal = 5 * 1024 * 1024; // 5MB after compression
        
        if (!validTypes.includes(file.type) && !file.name.match(/\.(pdf|jpg|jpeg|png)$/i)) {
            alert('Invalid file type. Please upload PDF, JPG, or PNG files only.');
            fileInput.value = '';
            compressedFile = null;
            return;
        }
        
        // For PDFs, check size directly
        if (file.type === 'application/pdf' || file.name.match(/\.pdf$/i)) {
            if (file.size > maxSizeFinal) {
                alert('PDF is too large. Maximum size is 5MB.');
                fileInput.value = '';
                compressedFile = null;
                return;
            }
            compressedFile = file;
        } else {
            // For images, allow larger raw size and compress
            if (file.size > maxSizeRaw) {
                alert('Image is too large. Maximum size is 20MB.');
                fileInput.value = '';
                compressedFile = null;
                return;
            }
            
            // Show compression indicator for large images
            if (file.size > 2 * 1024 * 1024) {
                uploadContent.style.display = 'none';
                filePreview.style.display = 'flex';
                fileName.textContent = 'Compressing image...';
                fileSize.textContent = 'Please wait';
            }
            
            // Compress image
            compressedFile = await compressImage(file, 1600, 1600, 0.85);
            
            // Check if compressed size is acceptable
            if (compressedFile.size > maxSizeFinal) {
                // Try again with lower quality
                compressedFile = await compressImage(file, 1400, 1400, 0.7);
            }
            
            if (compressedFile.size > maxSizeFinal) {
                alert('Image is still too large after compression. Please use a smaller image.');
                fileInput.value = '';
                compressedFile = null;
                uploadContent.style.display = 'block';
                filePreview.style.display = 'none';
                return;
            }
        }
        
        // Show file preview with compressed size
        uploadContent.style.display = 'none';
        filePreview.style.display = 'flex';
        fileName.textContent = file.name;
        
        // Show original and compressed size for images
        if (compressedFile !== file && file.type.startsWith('image/')) {
            fileSize.textContent = formatFileSize(compressedFile.size) + ' (compressed from ' + formatFileSize(file.size) + ')';
        } else {
            fileSize.textContent = formatFileSize(compressedFile.size);
        }
    }
    
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
    
    // Remove file
    removeFile.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fileInput.value = '';
        compressedFile = null;
        uploadContent.style.display = 'block';
        filePreview.style.display = 'none';
    });
    
    // Progress tracking
    let analysisStartTime = null;
    let progressInterval = null;
    let timeoutWarningTimer = null;
    let abortController = null;
    
    const loaderTitle = document.getElementById('loaderTitle');
    const loaderSubtitle = document.getElementById('loaderSubtitle');
    const progressFill = document.getElementById('progressFill');
    const loaderSteps = document.getElementById('loaderSteps');
    const loaderTimeoutMsg = document.getElementById('loaderTimeoutMsg');
    const cancelBtn = document.getElementById('cancelBtn');
    const steps = ['step1', 'step2', 'step3', 'step4'];
    
    function updateStep(stepNum, status) {
        const step = document.getElementById('step' + stepNum);
        if (!step) return;
        const icon = step.querySelector('i');
        step.classList.remove('active', 'completed');
        
        if (status === 'active') {
            step.classList.add('active');
            icon.className = 'fas fa-circle-notch fa-spin';
        } else if (status === 'completed') {
            step.classList.add('completed');
            icon.className = 'fas fa-check-circle';
        } else {
            icon.className = 'fas fa-circle';
        }
    }
    
    function simulateProgress() {
        let progress = 0;
        let currentStep = 1;
        const progressSteps = [
            { time: 2000, progress: 15, step: 1, msg: 'Uploading file...' },
            { time: 5000, progress: 30, step: 2, msg: 'Extracting text from report...' },
            { time: 10000, progress: 50, step: 3, msg: 'Analyzing with local database...' },
            { time: 20000, progress: 70, step: 4, msg: 'Getting AI suggestions...' },
            { time: 40000, progress: 85, step: 4, msg: 'Almost done...' },
            { time: 60000, progress: 90, step: 4, msg: 'Finalizing analysis...' }
        ];
        
        progressInterval = setInterval(() => {
            const elapsed = Date.now() - analysisStartTime;
            
            for (let i = progressSteps.length - 1; i >= 0; i--) {
                if (elapsed >= progressSteps[i].time) {
                    progress = progressSteps[i].progress;
                    loaderSubtitle.textContent = progressSteps[i].msg;
                    
                    // Update step indicators
                    for (let s = 1; s <= 4; s++) {
                        if (s < progressSteps[i].step) {
                            updateStep(s, 'completed');
                        } else if (s === progressSteps[i].step) {
                            updateStep(s, 'active');
                        } else {
                            updateStep(s, 'pending');
                        }
                    }
                    break;
                }
            }
            
            progressFill.style.width = progress + '%';
        }, 500);
        
        // Show timeout warning after 30 seconds
        timeoutWarningTimer = setTimeout(() => {
            loaderTimeoutMsg.classList.remove('hidden');
            cancelBtn.classList.remove('hidden');
        }, 30000);
    }
    
    function hideLoader() {
        pageLoader.classList.add('hidden');
        if (progressInterval) clearInterval(progressInterval);
        if (timeoutWarningTimer) clearTimeout(timeoutWarningTimer);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-magic"></i> Analyze with AI';
        loaderTimeoutMsg.classList.add('hidden');
        cancelBtn.classList.add('hidden');
        progressFill.style.width = '0%';
        steps.forEach((_, i) => updateStep(i + 1, 'pending'));
    }
    
    window.cancelAnalysis = function() {
        if (abortController) {
            abortController.abort();
        }
        hideLoader();
        alert('Analysis cancelled. Please try again.');
    };
    
    // Form submission with AJAX
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!compressedFile && !fileInput.files.length) {
            alert('Please select a file to upload.');
            return;
        }
        
        if (!patientSelect.value) {
            alert('Please select a patient.');
            return;
        }
        
        // Show loader and start progress
        pageLoader.classList.remove('hidden');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analyzing...';
        analysisStartTime = Date.now();
        updateStep(1, 'active');
        simulateProgress();
        
        // Create FormData with compressed file
        const formData = new FormData();
        formData.append('patient_id', patientSelect.value);
        formData.append('consultation_id', consultationSelect ? consultationSelect.value : '');
        
        // Use compressed file if available, otherwise use original
        if (compressedFile) {
            formData.append('lab_report', compressedFile, compressedFile.name);
        } else {
            formData.append('lab_report', fileInput.files[0]);
        }
        
        abortController = new AbortController();
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData,
            signal: abortController.signal
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Server error: ' + response.status);
            }
            return response.text();
        })
        .then(html => {
            // Complete progress
            progressFill.style.width = '100%';
            steps.forEach((_, i) => updateStep(i + 1, 'completed'));
            loaderSubtitle.textContent = 'Complete!';
            
            // Replace page content with response
            setTimeout(() => {
                document.open();
                document.write(html);
                document.close();
            }, 300);
        })
        .catch(error => {
            hideLoader();
            if (error.name === 'AbortError') {
                return; // User cancelled
            }
            console.error('Analysis error:', error);
            alert('Analysis failed: ' + error.message + '\n\nPlease check your internet connection and try again.');
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
