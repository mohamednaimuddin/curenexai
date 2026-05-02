<?php
/**
 * Dermo - Skin Analysis & Homeopathic Remedy Suggestions
 * Features:
 * 1. Upload skin images for AI analysis
 * 2. Live camera capture for real-time skin analysis
 * Both provide AI + RAG based homeopathic remedy suggestions
 */
set_time_limit(120);

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/dermo_ai.php';

/**
 * Security: Validate image file magic bytes to prevent malicious uploads
 */
function validateImageMagicBytes($filePath) {
    $handle = fopen($filePath, 'rb');
    if (!$handle) return false;
    
    $bytes = fread($handle, 12);
    fclose($handle);
    
    if ($bytes === false || strlen($bytes) < 3) return false;
    
    // JPEG: FF D8 FF
    if (substr($bytes, 0, 3) === "\xFF\xD8\xFF") return true;
    // PNG: 89 50 4E 47 0D 0A 1A 0A
    if (substr($bytes, 0, 8) === "\x89PNG\r\n\x1A\n") return true;
    // WebP: 52 49 46 46 ... 57 45 42 50
    if (strlen($bytes) >= 12 && substr($bytes, 0, 4) === "RIFF" && substr($bytes, 8, 4) === "WEBP") return true;
    // GIF: 47 49 46 38
    if (substr($bytes, 0, 4) === "GIF8") return true;
    
    return false;
}

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

if (!isLoggedIn()) {
    redirect('/login.php');
}

$doctor_id = $_SESSION['doctor_id'];
$pageTitle = 'Dermo - Skin Analysis';
$error = '';
$success = '';
$analysis_result = null;

// Fetch patient list for dropdown
$patients = DB::query("SELECT id, patient_name, age, gender FROM patients WHERE doctor_id = ? ORDER BY patient_name ASC", [$doctor_id]);

// Get patient_id from GET or POST
$selected_patient_id = $_GET['patient_id'] ?? $_POST['patient_id'] ?? '';

// Get selected patient info
$selected_patient = null;
if ($selected_patient_id) {
    $selected_patient = DB::queryOne("SELECT * FROM patients WHERE id = ? AND doctor_id = ?", [$selected_patient_id, $doctor_id]);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['skin_image'])) {
    $file = $_FILES['skin_image'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $skin_area = $_POST['skin_area'] ?? 'general';
    $symptoms_description = $_POST['symptoms_description'] ?? '';
    $analysis_mode = $_POST['analysis_mode'] ?? 'both';
    $skip_ai = ($analysis_mode === 'rag_only');
    
    // Validate file extension
    if (!in_array($ext, $allowedExtensions)) {
        $error = 'Invalid file type. Only JPG, PNG, and WebP images are allowed.';
    }
    // Validate MIME type
    elseif (!in_array(mime_content_type($file['tmp_name']), $allowedMimeTypes)) {
        $error = 'Invalid file content. File does not appear to be a valid image.';
    }
    // Validate file magic bytes
    elseif (!validateImageMagicBytes($file['tmp_name'])) {
        $error = 'Invalid image file. File signature verification failed.';
    }
    elseif ($file['size'] > 10 * 1024 * 1024) {
        $error = 'File too large. Maximum size is 10MB.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload error occurred. Please try again.';
    } else {
        $targetDir = __DIR__ . '/uploads/skin_images/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $filename = uniqid('skin_') . '.' . $ext;
        $targetFile = $targetDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            $success = 'Image uploaded successfully!';
            
            // Perform skin analysis (with privacy check - pass doctor_id for consent verification)
            $analysis_result = analyzeSkinImage(
                $targetFile, 
                $skin_area, 
                $symptoms_description,
                $selected_patient,
                $skip_ai,
                $doctor_id  // Pass doctor_id for AI consent check
            );
            
            // Save analysis to database
            if ($selected_patient_id && !empty($analysis_result)) {
                try {
                    DB::insert('skin_analyses', [
                        'patient_id' => $selected_patient_id,
                        'doctor_id' => $doctor_id,
                        'image_path' => 'uploads/skin_images/' . $filename,
                        'skin_area' => $skin_area,
                        'symptoms_description' => $symptoms_description,
                        'ai_analysis' => json_encode($analysis_result['ai_analysis'] ?? []),
                        'rag_remedies' => json_encode($analysis_result['rag_remedies'] ?? []),
                        'analysis_date' => date('Y-m-d H:i:s')
                    ]);
                } catch (Exception $e) {
                    error_log('Failed to save skin analysis: ' . $e->getMessage());
                }
            }
        } else {
            $error = 'Failed to upload file. Please check folder permissions.';
        }
    }
}

// Handle base64 camera capture
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['camera_image'])) {
    $base64_image = $_POST['camera_image'];
    $skin_area = $_POST['skin_area'] ?? 'general';
    $symptoms_description = $_POST['symptoms_description'] ?? '';
    $analysis_mode = $_POST['analysis_mode_camera'] ?? 'both';
    $skip_ai = ($analysis_mode === 'rag_only');
    
    // Security: Limit base64 size (10MB image = ~13MB base64)
    $maxBase64Size = 15 * 1024 * 1024;
    if (strlen($base64_image) > $maxBase64Size) {
        $error = 'Image data too large. Please use a smaller image.';
    } else {
        // Decode and save base64 image
        $image_parts = explode(";base64,", $base64_image);
        if (count($image_parts) == 2) {
            $image_data = base64_decode($image_parts[1]);
            
            $targetDir = __DIR__ . '/uploads/skin_images/';
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            $filename = uniqid('skin_cam_') . '.png';
            $targetFile = $targetDir . $filename;
        
            if (file_put_contents($targetFile, $image_data)) {
                $success = 'Camera image captured successfully!';
                
                // Perform skin analysis
                $analysis_result = analyzeSkinImage(
                    $targetFile, 
                    $skin_area, 
                    $symptoms_description,
                    $selected_patient,
                    $skip_ai
                );
                
                // Save analysis to database
                if ($selected_patient_id && !empty($analysis_result)) {
                    try {
                        DB::insert('skin_analyses', [
                            'patient_id' => $selected_patient_id,
                            'doctor_id' => $doctor_id,
                            'image_path' => 'uploads/skin_images/' . $filename,
                            'skin_area' => $skin_area,
                            'symptoms_description' => $symptoms_description,
                            'ai_analysis' => json_encode($analysis_result['ai_analysis'] ?? []),
                            'rag_remedies' => json_encode($analysis_result['rag_remedies'] ?? []),
                            'analysis_date' => date('Y-m-d H:i:s')
                        ]);
                    } catch (Exception $e) {
                        error_log('Failed to save skin analysis: ' . $e->getMessage());
                    }
                }
            } else {
                $error = 'Failed to save camera image.';
            }
        } else {
            $error = 'Invalid image data from camera.';
        }
    }
}

include 'includes/header.php';
?>

<style>
    .dermo-container { position: relative; }
    .dermo-container::before {
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
        .dermo-container::before { left: 0; top: 60px; }
    }
</style>

<div class="dermo-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1><i class="fas fa-hand-holding-medical"></i> Dermo - Skin Analysis</h1>
            <p class="text-muted">AI-powered skin analysis with homeopathic remedy suggestions</p>
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

    <?php if ($success && empty($analysis_result)): ?>
    <div class="alert alert-success alert-dismissible">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($success); ?></span>
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endif; ?>

    <div class="dermo-grid">
        <!-- Input Section -->
        <div class="dermo-input-section">
            <!-- Mode Tabs -->
            <div class="mode-tabs">
                <button class="mode-tab active" data-mode="upload">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Upload Image</span>
                </button>
                <button class="mode-tab" data-mode="camera">
                    <i class="fas fa-camera"></i>
                    <span>Live Camera</span>
                </button>
            </div>

            <!-- Upload Mode -->
            <div class="mode-panel active" id="uploadPanel">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-cloud-upload-alt"></i> Upload Skin Image</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <!-- Patient Selection -->
                            <div class="form-group">
                                <label for="patient_id_upload">
                                    <i class="fas fa-user"></i> Select Patient (Optional)
                                </label>
                                <select name="patient_id" id="patient_id_upload" class="form-control">
                                    <option value="">-- No patient selected --</option>
                                    <?php foreach ($patients as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo ($selected_patient_id == $p['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['patient_name']); ?> 
                                        (<?php echo $p['age']; ?>y, <?php echo ucfirst($p['gender']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Skin Area Selection -->
                            <div class="form-group">
                                <label for="skin_area_upload">
                                    <i class="fas fa-body-text"></i> Affected Area
                                </label>
                                <select name="skin_area" id="skin_area_upload" class="form-control" required>
                                    <option value="face">Face</option>
                                    <option value="scalp">Scalp</option>
                                    <option value="neck">Neck</option>
                                    <option value="chest">Chest</option>
                                    <option value="back">Back</option>
                                    <option value="abdomen">Abdomen</option>
                                    <option value="arms">Arms</option>
                                    <option value="hands">Hands</option>
                                    <option value="legs">Legs</option>
                                    <option value="feet">Feet</option>
                                    <option value="general" selected>General/Multiple Areas</option>
                                </select>
                            </div>

                            <!-- Symptoms Description -->
                            <div class="form-group">
                                <label for="symptoms_upload">
                                    <i class="fas fa-notes-medical"></i> Describe Symptoms
                                </label>
                                <textarea name="symptoms_description" id="symptoms_upload" class="form-control" rows="3" placeholder="Describe the skin condition: itching, burning, color changes, duration, what makes it better or worse..."></textarea>
                            </div>

                            <!-- Analysis Mode Toggle -->
                            <div class="form-group">
                                <label><i class="fas fa-cogs"></i> Analysis Mode</label>
                                <div class="analysis-mode-options">
                                    <label class="mode-option">
                                        <input type="radio" name="analysis_mode" value="both" checked>
                                        <div>
                                            <strong>AI + RAG</strong>
                                            <small>Use AI vision + database matching</small>
                                        </div>
                                    </label>
                                    <label class="mode-option">
                                        <input type="radio" name="analysis_mode" value="rag_only">
                                        <div>
                                            <strong>RAG Only</strong>
                                            <small>Pattern matching only (faster)</small>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- File Upload Area -->
                            <div class="form-group">
                                <label><i class="fas fa-image"></i> Skin Image</label>
                                <div class="file-upload-area" id="dropZone">
                                    <input type="file" name="skin_image" id="skin_image" class="file-input" accept="image/jpeg,image/png,image/webp" required>
                                    <div class="upload-content">
                                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                        <h4>Drag & Drop your image here</h4>
                                        <p>or click to browse</p>
                                        <span class="upload-hint">JPG, PNG, WebP (Max 10MB)</span>
                                    </div>
                                    <div class="image-preview-container" id="imagePreview" style="display: none;">
                                        <img id="previewImage" src="" alt="Preview">
                                        <button type="button" class="remove-image" id="removeImage">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-primary btn-block btn-lg" id="uploadBtn">
                                <i class="fas fa-magic"></i> Analyze Skin
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Camera Mode -->
            <div class="mode-panel" id="cameraPanel">
                <div class="dashboard-card">
                    <div class="card-header">
                        <h3><i class="fas fa-camera"></i> Live Camera Analysis</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="cameraForm">
                            <input type="hidden" name="camera_image" id="cameraImageInput">
                            
                            <!-- Patient Selection -->
                            <div class="form-group">
                                <label for="patient_id_camera">
                                    <i class="fas fa-user"></i> Select Patient (Optional)
                                </label>
                                <select name="patient_id" id="patient_id_camera" class="form-control">
                                    <option value="">-- No patient selected --</option>
                                    <?php foreach ($patients as $p): ?>
                                    <option value="<?php echo $p['id']; ?>" <?php echo ($selected_patient_id == $p['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['patient_name']); ?> 
                                        (<?php echo $p['age']; ?>y, <?php echo ucfirst($p['gender']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Skin Area Selection -->
                            <div class="form-group">
                                <label for="skin_area_camera">
                                    <i class="fas fa-body-text"></i> Affected Area
                                </label>
                                <select name="skin_area" id="skin_area_camera" class="form-control" required>
                                    <option value="face">Face</option>
                                    <option value="scalp">Scalp</option>
                                    <option value="neck">Neck</option>
                                    <option value="chest">Chest</option>
                                    <option value="back">Back</option>
                                    <option value="abdomen">Abdomen</option>
                                    <option value="arms">Arms</option>
                                    <option value="hands">Hands</option>
                                    <option value="legs">Legs</option>
                                    <option value="feet">Feet</option>
                                    <option value="general" selected>General/Multiple Areas</option>
                                </select>
                            </div>

                            <!-- Symptoms Description -->
                            <div class="form-group">
                                <label for="symptoms_camera">
                                    <i class="fas fa-notes-medical"></i> Describe Symptoms
                                </label>
                                <textarea name="symptoms_description" id="symptoms_camera" class="form-control" rows="3" placeholder="Describe the skin condition: itching, burning, color changes, duration, what makes it better or worse..."></textarea>
                            </div>

                            <!-- Analysis Mode Toggle (Camera) -->
                            <div class="form-group">
                                <label><i class="fas fa-cogs"></i> Analysis Mode</label>
                                <div class="analysis-mode-options">
                                    <label class="mode-option">
                                        <input type="radio" name="analysis_mode_camera" value="both" checked>
                                        <div>
                                            <strong>AI + RAG</strong>
                                            <small>Use AI vision + database</small>
                                        </div>
                                    </label>
                                    <label class="mode-option">
                                        <input type="radio" name="analysis_mode_camera" value="rag_only">
                                        <div>
                                            <strong>RAG Only</strong>
                                            <small>Pattern matching (faster)</small>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Camera View -->
                            <div class="camera-container">
                                <video id="cameraVideo" autoplay playsinline></video>
                                <canvas id="cameraCanvas" style="display: none;"></canvas>
                                <div class="camera-focus-guide" id="cameraFocusGuide" style="display: none;"></div>
                                <div class="camera-quality-indicator" id="cameraQualityIndicator" style="display: none;">
                                    <i class="fas fa-video"></i>
                                    <span id="cameraResolution">--</span>
                                </div>
                                <div class="camera-overlay" id="cameraOverlay">
                                    <i class="fas fa-camera"></i>
                                    <p>Click "Start Camera" to begin</p>
                                    <small style="opacity: 0.7; margin-top: 10px;">Ensure good lighting for best results</small>
                                </div>
                                <div class="captured-preview" id="capturedPreview" style="display: none;">
                                    <img id="capturedImage" src="" alt="Captured">
                                    <div class="captured-quality-badge" id="capturedQualityBadge">
                                        <i class="fas fa-check-circle"></i>
                                        <span id="capturedResolution">--</span>
                                    </div>
                                    <button type="button" class="retake-btn" id="retakeBtn">
                                        <i class="fas fa-redo"></i> Retake
                                    </button>
                                </div>
                            </div>

                            <!-- Camera Controls -->
                            <div class="camera-controls">
                                <button type="button" class="btn btn-outline" id="startCameraBtn">
                                    <i class="fas fa-video"></i> Start Camera
                                </button>
                                <button type="button" class="btn btn-primary" id="captureBtn" disabled>
                                    <i class="fas fa-camera"></i> Capture
                                </button>
                                <button type="button" class="btn btn-outline" id="switchCameraBtn" style="display: none;">
                                    <i class="fas fa-sync-alt"></i> Switch
                                </button>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit" class="btn btn-primary btn-block btn-lg" id="analyzeCameraBtn" disabled>
                                <i class="fas fa-magic"></i> Analyze Captured Image
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Info Card -->
            <div class="dashboard-card info-card">
                <div class="card-body">
                    <h4><i class="fas fa-info-circle"></i> Skin Analysis Tips</h4>
                    <ul class="info-list">
                        <li>
                            <span class="step-number">1</span>
                            <span>Ensure good lighting - natural daylight works best</span>
                        </li>
                        <li>
                            <span class="step-number">2</span>
                            <span>Capture the affected area clearly and up close</span>
                        </li>
                        <li>
                            <span class="step-number">3</span>
                            <span>Include surrounding healthy skin for comparison</span>
                        </li>
                        <li>
                            <span class="step-number">4</span>
                            <span>Describe symptoms in detail for better analysis</span>
                        </li>
                    </ul>
                    <div class="info-note">
                        <i class="fas fa-lightbulb"></i>
                        <p>The AI analyzes skin conditions and suggests homeopathic remedies based on both visual analysis and symptom correlation from our medical database.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <div class="dermo-results-section">
            <?php if (!empty($analysis_result)): ?>
            
            <?php if (!empty($analysis_result['error'])): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>
                    <?php if (strpos($analysis_result['error'], '429') !== false || strpos($analysis_result['error'], 'rate') !== false): ?>
                        <strong>AI temporarily unavailable</strong> - API rate limit reached. RAG remedies from our database are shown below.
                    <?php else: ?>
                        AI Analysis Error: <?php echo htmlspecialchars($analysis_result['error']); ?>
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
            
            <!-- AI Analysis Results -->
            <div class="dashboard-card results-card">
                <div class="card-header results-header">
                    <h3><i class="fas fa-brain"></i> Skin Analysis Results</h3>
                    <span class="badge badge-success"><i class="fas fa-check"></i> Analysis Complete</span>
                </div>
                <div class="card-body">
                    <!-- Patient Info -->
                    <?php if ($selected_patient): ?>
                    <div class="result-info-bar">
                        <div class="info-item">
                            <i class="fas fa-user"></i>
                            <span><?php echo htmlspecialchars($selected_patient['patient_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Area: <?php echo ucfirst($_POST['skin_area'] ?? 'General'); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php 
                    // Determine which tab should be active
                    $hasAiData = !empty($analysis_result['ai_analysis']) && 
                        (isset($analysis_result['ai_analysis']['condition']) || 
                         isset($analysis_result['ai_analysis']['description']));
                    $hasRagData = !empty($analysis_result['rag_remedies']);
                    $showRagFirst = !$hasAiData && $hasRagData;
                    ?>

                    <!-- Analysis Tabs -->
                    <div class="analysis-tabs">
                        <button class="tab-btn <?php echo !$showRagFirst ? 'active' : ''; ?>" data-tab="ai-analysis">
                            <i class="fas fa-robot"></i> <span>AI Analysis</span>
                        </button>
                        <button class="tab-btn" data-tab="ai-remedies">
                            <i class="fas fa-pills"></i> <span>AI Remedies</span>
                        </button>
                        <button class="tab-btn <?php echo $showRagFirst ? 'active' : ''; ?>" data-tab="rag-remedies">
                            <i class="fas fa-database"></i> <span>RAG Remedies</span>
                            <?php if ($hasRagData): ?>
                            <span class="badge badge-success" style="margin-left: 5px; font-size: 10px;"><?php echo count($analysis_result['rag_remedies']); ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- AI Analysis Tab -->
                        <div class="tab-pane <?php echo !$showRagFirst ? 'active' : ''; ?>" id="ai-analysis">
                            <div class="analysis-box">
                                <h4><i class="fas fa-eye"></i> Visual Analysis</h4>
                                <?php 
                                $hasAiAnalysis = !empty($analysis_result['ai_analysis']) && 
                                    (isset($analysis_result['ai_analysis']['condition']) || 
                                     isset($analysis_result['ai_analysis']['description']));
                                ?>
                                
                                <?php if ($hasAiAnalysis): ?>
                                    <?php if (!empty($analysis_result['ai_analysis']['condition'])): ?>
                                    <div class="condition-badge">
                                        <strong>Detected Condition:</strong>
                                        <span class="badge badge-info"><?php echo htmlspecialchars($analysis_result['ai_analysis']['condition']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($analysis_result['ai_analysis']['description'])): ?>
                                    <div class="analysis-text">
                                        <?php echo nl2br(htmlspecialchars($analysis_result['ai_analysis']['description'])); ?>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($analysis_result['ai_analysis']['characteristics']) && is_array($analysis_result['ai_analysis']['characteristics'])): ?>
                                    <div class="characteristics-list">
                                        <h5><i class="fas fa-list-ul"></i> Observed Characteristics</h5>
                                        <ul>
                                            <?php foreach ($analysis_result['ai_analysis']['characteristics'] as $char): ?>
                                            <li><?php echo htmlspecialchars($char); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($analysis_result['ai_analysis']['severity'])): ?>
                                    <div class="severity-indicator">
                                        <strong>Severity:</strong>
                                        <span class="severity-badge severity-<?php echo strtolower($analysis_result['ai_analysis']['severity']); ?>">
                                            <?php echo htmlspecialchars($analysis_result['ai_analysis']['severity']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($analysis_result['ai_analysis']['recommendations'])): ?>
                                    <div class="recommendations-box">
                                        <h5><i class="fas fa-lightbulb"></i> Recommendations</h5>
                                        <p><?php echo nl2br(htmlspecialchars($analysis_result['ai_analysis']['recommendations'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-robot"></i>
                                        <p>AI analysis could not be completed. Please check your Gemini API key configuration or try again.</p>
                                        <?php if (!empty($analysis_result['error'])): ?>
                                        <small class="text-muted">Error: <?php echo htmlspecialchars($analysis_result['error']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- AI Remedies Tab -->
                        <div class="tab-pane" id="ai-remedies">
                            <?php if (!empty($analysis_result['ai_analysis']['remedies']) && is_array($analysis_result['ai_analysis']['remedies'])): ?>
                            <div class="remedies-grid">
                                <?php foreach ($analysis_result['ai_analysis']['remedies'] as $index => $remedy): ?>
                                <div class="remedy-card ai-remedy">
                                    <div class="remedy-rank"><?php echo $index + 1; ?></div>
                                    <div class="remedy-content">
                                        <h5><?php echo htmlspecialchars($remedy['name'] ?? 'Unknown'); ?></h5>
                                        <?php if (!empty($remedy['potency'])): ?>
                                        <span class="potency-badge"><?php echo htmlspecialchars($remedy['potency']); ?></span>
                                        <?php endif; ?>
                                        <p class="remedy-indication"><?php echo htmlspecialchars($remedy['indication'] ?? ''); ?></p>
                                    </div>
                                    <span class="remedy-source"><i class="fas fa-robot"></i> AI</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-robot"></i>
                                <p>AI remedy suggestions not available</p>
                                <small class="text-muted">Please check RAG Remedies tab for database-based suggestions</small>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- RAG Remedies Tab -->
                        <div class="tab-pane <?php echo $showRagFirst ? 'active' : ''; ?>" id="rag-remedies">
                            <?php 
                            // Show RAG analysis info first
                            if (!empty($analysis_result['rag_analysis'])):
                                $ragAnalysis = $analysis_result['rag_analysis'];
                            ?>
                            <div class="rag-analysis-summary">
                                <h4><i class="fas fa-search-plus"></i> Pattern-Based Analysis</h4>
                                
                                <?php if (!empty($ragAnalysis['condition'])): ?>
                                <div class="rag-condition">
                                    <strong>Detected Condition:</strong> 
                                    <span class="condition-badge-pill"><?php echo htmlspecialchars($ragAnalysis['condition']); ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($ragAnalysis['description'])): ?>
                                <p class="rag-description"><?php echo htmlspecialchars($ragAnalysis['description']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($ragAnalysis['characteristics']) && is_array($ragAnalysis['characteristics'])): ?>
                                <div class="rag-characteristics">
                                    <strong>Identified Characteristics:</strong>
                                    <div class="characteristics-tags">
                                        <?php foreach ($ragAnalysis['characteristics'] as $char): ?>
                                        <span class="char-tag"><?php echo htmlspecialchars($char); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($ragAnalysis['severity'])): ?>
                                <div class="rag-severity">
                                    <strong>Severity Assessment:</strong>
                                    <span class="severity-badge severity-<?php echo strtolower($ragAnalysis['severity']); ?>">
                                        <?php echo htmlspecialchars($ragAnalysis['severity']); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($ragAnalysis['matched_patterns'])): ?>
                                <div class="matched-patterns">
                                    <small><i class="fas fa-check-circle"></i> Matched patterns: 
                                        <?php 
                                        $patternNames = array_column($ragAnalysis['matched_patterns'], 'condition');
                                        echo htmlspecialchars(implode(', ', array_slice($patternNames, 0, 3)));
                                        ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($analysis_result['rag_remedies'])): ?>
                            <h5 class="remedies-heading"><i class="fas fa-pills"></i> Recommended Remedies (<?php echo count($analysis_result['rag_remedies']); ?>)</h5>
                            <div class="remedies-grid">
                                <?php foreach ($analysis_result['rag_remedies'] as $index => $remedy): ?>
                                <div class="remedy-card rag-remedy">
                                    <div class="remedy-rank"><?php echo $index + 1; ?></div>
                                    <div class="remedy-content">
                                        <h5 class="remedy-name">
                                            <?php echo htmlspecialchars($remedy['remedy_name'] ?? $remedy['name'] ?? 'Unknown'); ?>
                                        </h5>
                                        <?php if (!empty($remedy['common_name'])): ?>
                                        <div class="common-name">
                                            <?php echo htmlspecialchars($remedy['common_name']); ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($remedy['potency'])): ?>
                                        <div class="potency-row">
                                            <span class="potency-badge rag-potency">
                                                <i class="fas fa-flask"></i> <?php echo htmlspecialchars($remedy['potency']); ?>
                                            </span>
                                            <?php if (!empty($remedy['dosage'])): ?>
                                            <span class="dosage-text">
                                                <?php echo htmlspecialchars($remedy['dosage']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($remedy['score'])): ?>
                                        <div class="match-score">
                                            <div class="score-bar rag-score" style="width: <?php echo min(100, $remedy['score'] * 10); ?>%;"></div>
                                            <span class="score-text">Relevance: <?php echo $remedy['score']; ?>/10</span>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($remedy['why_indicated'])): ?>
                                        <div class="why-indicated">
                                            <strong><i class="fas fa-lightbulb"></i> Why This Remedy:</strong>
                                            <p><?php echo htmlspecialchars($remedy['why_indicated']); ?></p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($remedy['skin_indications'])): ?>
                                        <div class="skin-indications">
                                            <strong><i class="fas fa-hand-holding-medical"></i> Skin Indications:</strong>
                                            <p><?php echo htmlspecialchars($remedy['skin_indications']); ?></p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($remedy['keynote_symptoms'])): ?>
                                        <div class="keynotes">
                                            <strong><i class="fas fa-key"></i> Keynotes:</strong>
                                            <p>
                                                <?php 
                                                $keynotes = $remedy['keynote_symptoms'];
                                                echo htmlspecialchars(strlen($keynotes) > 300 ? substr($keynotes, 0, 300) . '...' : $keynotes); 
                                                ?>
                                            </p>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($remedy['modalities']) && is_array($remedy['modalities'])): ?>
                                        <div class="modalities">
                                            <?php if (!empty($remedy['modalities']['worse'])): ?>
                                            <div class="modality-item worse">
                                                <small><i class="fas fa-arrow-down"></i> <strong>Worse:</strong></small>
                                                <div class="modality-text">
                                                    <?php echo htmlspecialchars(implode(', ', $remedy['modalities']['worse'])); ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (!empty($remedy['modalities']['better'])): ?>
                                            <div class="modality-item better">
                                                <small><i class="fas fa-arrow-up"></i> <strong>Better:</strong></small>
                                                <div class="modality-text">
                                                    <?php echo htmlspecialchars(implode(', ', $remedy['modalities']['better'])); ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="remedy-source"><i class="fas fa-database"></i> RAG</span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-database"></i>
                                <p>No RAG remedy matches found</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Disclaimer -->
                    <div class="disclaimer-box">
                        <i class="fas fa-exclamation-triangle"></i>
                        <p><strong>Disclaimer:</strong> This AI analysis is for informational purposes only and should not replace professional medical diagnosis. Always consult with a qualified healthcare provider for proper diagnosis and treatment.</p>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- Empty State -->
            <div class="dashboard-card empty-results">
                <div class="card-body text-center">
                    <div class="empty-illustration">
                        <i class="fas fa-hand-holding-medical"></i>
                    </div>
                    <h3>Ready to Analyze</h3>
                    <p class="text-muted">Upload a skin image or use the camera to capture and analyze skin conditions. Get AI-powered analysis and homeopathic remedy suggestions.</p>
                    <div class="features-preview">
                        <div class="feature-item">
                            <i class="fas fa-eye"></i>
                            <span>Visual Analysis</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-robot"></i>
                            <span>AI Remedies</span>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-database"></i>
                            <span>RAG Database</span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Quick Skin Conditions -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-list"></i> Common Skin Conditions</h3>
                </div>
                <div class="card-body">
                    <div class="condition-tags">
                        <span class="condition-tag" data-condition="eczema"><i class="fas fa-circle"></i> Eczema</span>
                        <span class="condition-tag" data-condition="psoriasis"><i class="fas fa-circle"></i> Psoriasis</span>
                        <span class="condition-tag" data-condition="acne"><i class="fas fa-circle"></i> Acne</span>
                        <span class="condition-tag" data-condition="dermatitis"><i class="fas fa-circle"></i> Dermatitis</span>
                        <span class="condition-tag" data-condition="urticaria"><i class="fas fa-circle"></i> Urticaria</span>
                        <span class="condition-tag" data-condition="fungal"><i class="fas fa-circle"></i> Fungal Infection</span>
                        <span class="condition-tag" data-condition="vitiligo"><i class="fas fa-circle"></i> Vitiligo</span>
                        <span class="condition-tag" data-condition="herpes"><i class="fas fa-circle"></i> Herpes</span>
                        <span class="condition-tag" data-condition="scabies"><i class="fas fa-circle"></i> Scabies</span>
                        <span class="condition-tag" data-condition="ringworm"><i class="fas fa-circle"></i> Ringworm</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Dermo Page Styles */
.dermo-container {
    padding: 20px;
    max-width: 1400px;
    margin: 0 auto;
}

.dermo-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

/* Page Header Responsive */
.page-header {
    flex-wrap: wrap;
    gap: 15px;
}

.header-actions .btn span {
    display: inline;
}

/* Mode Tabs */
.mode-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.mode-tab {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 15px 20px;
    background: var(--bg-secondary);
    border: 2px solid var(--border-color);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.mode-tab:hover {
    border-color: var(--primary-color);
    background: var(--primary-light);
}

.mode-tab.active {
    border-color: var(--primary-color);
    background: var(--primary-color);
    color: white;
}

.mode-tab i {
    font-size: 1.2rem;
}

/* ========== RESPONSIVE STYLES ========== */

/* Large tablets and small desktops */
@media (max-width: 1200px) {
    .dermo-container {
        padding: 15px;
    }
    
    .dermo-grid {
        gap: 20px;
    }
}

/* Tablets */
@media (max-width: 1024px) {
    .dermo-grid {
        grid-template-columns: 1fr;
    }
    
    .dermo-results-section {
        order: -1;
    }
}

/* Mobile devices */
@media (max-width: 768px) {
    .dermo-container {
        padding: 10px;
    }
    
    .dermo-grid {
        gap: 15px;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .page-header h1 {
        font-size: 1.4rem;
    }
    
    .page-header p {
        font-size: 0.85rem;
    }
    
    .header-actions {
        width: 100%;
    }
    
    .header-actions .btn {
        width: 100%;
        justify-content: center;
    }
    
    /* Mode tabs mobile */
    .mode-tabs {
        gap: 8px;
    }
    
    .mode-tab {
        padding: 12px 10px;
        flex-direction: column;
        gap: 5px;
        font-size: 0.85rem;
    }
    
    .mode-tab i {
        font-size: 1.1rem;
    }
    
    .mode-tab span {
        font-size: 0.8rem;
    }
    
    /* Dashboard cards */
    .dashboard-card .card-header {
        padding: 12px 15px;
    }
    
    .dashboard-card .card-header h3 {
        font-size: 1rem;
    }
    
    .dashboard-card .card-body {
        padding: 15px;
    }
    
    /* Form elements */
    .form-group {
        margin-bottom: 15px;
    }
    
    .form-group label {
        font-size: 0.9rem;
        margin-bottom: 6px;
    }
    
    .form-control {
        padding: 10px 12px;
        font-size: 0.95rem;
    }
    
    textarea.form-control {
        min-height: 80px;
    }
    
    /* Analysis mode options - responsive */
    .analysis-mode-options {
        flex-direction: column !important;
        gap: 10px !important;
    }
    
    .analysis-mode-options .mode-option {
        padding: 12px !important;
        flex: none !important;
        width: 100% !important;
    }
    
    .analysis-mode-options .mode-option div {
        flex: 1;
    }
    
    .analysis-mode-options .mode-option strong {
        font-size: 0.95rem !important;
    }
    
    .analysis-mode-options .mode-option small {
        font-size: 0.8rem !important;
    }
    
    /* File upload area */
    .file-upload-area {
        padding: 20px 15px;
    }
    
    .upload-content .upload-icon {
        font-size: 36px;
    }
    
    .upload-content h4 {
        font-size: 1rem;
    }
    
    .upload-content p {
        font-size: 0.85rem;
    }
    
    .upload-hint {
        font-size: 0.75rem;
    }
    
    /* Buttons */
    .btn-lg {
        padding: 12px 20px;
        font-size: 0.95rem;
    }
    
    .btn-block {
        padding: 14px;
    }
    
    /* Camera controls */
    .camera-controls {
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .camera-controls .btn {
        flex: 1 1 calc(50% - 4px);
        min-width: 0;
        padding: 10px 8px;
        font-size: 0.85rem;
    }
    
    .camera-controls .btn i {
        margin-right: 4px;
    }
    
    /* Camera container */
    .camera-container {
        aspect-ratio: 4/3;
        border-radius: 10px;
    }
    
    .camera-focus-guide {
        width: 80%;
        height: 80%;
    }
    
    .camera-focus-guide::before {
        font-size: 10px;
        bottom: -25px;
    }
    
    .retake-btn {
        padding: 8px 15px;
        font-size: 0.85rem;
        bottom: 10px;
        right: 10px;
    }
    
    /* Info card */
    .info-list li {
        gap: 10px;
        font-size: 0.9rem;
    }
    
    .step-number {
        width: 22px;
        height: 22px;
        font-size: 0.75rem;
    }
    
    .info-note {
        padding: 10px;
    }
    
    .info-note p {
        font-size: 0.85rem;
    }
    
    /* Results section */
    .results-header {
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .results-header h3 {
        font-size: 1rem;
    }
    
    .results-header .badge {
        font-size: 0.75rem;
        padding: 4px 10px;
    }
    
    /* Result info bar */
    .result-info-bar {
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 12px;
    }
    
    .result-info-bar .info-item {
        font-size: 0.85rem;
        gap: 6px;
    }
    
    /* Analysis tabs */
    .analysis-tabs {
        flex-wrap: wrap;
        gap: 6px;
        padding-bottom: 8px;
    }
    
    .tab-btn {
        flex: 1 1 auto;
        min-width: calc(33% - 6px);
        padding: 8px 10px;
        font-size: 0.8rem;
        justify-content: center;
    }
    
    .tab-btn i {
        font-size: 0.9rem;
    }
    
    .tab-btn span {
        display: none;
    }
    
    .tab-btn .badge {
        margin-left: 3px !important;
    }
    
    /* Analysis box */
    .analysis-box {
        padding: 15px;
        border-radius: 10px;
    }
    
    .analysis-box h4 {
        font-size: 1rem;
    }
    
    .condition-badge .badge {
        font-size: 0.9rem;
        padding: 6px 12px;
    }
    
    .analysis-text {
        font-size: 0.9rem;
        line-height: 1.6;
    }
    
    .characteristics-list h5 {
        font-size: 0.95rem;
    }
    
    .characteristics-list li {
        font-size: 0.9rem;
    }
    
    .severity-badge {
        font-size: 0.85rem;
        padding: 4px 12px;
    }
    
    /* Remedies grid */
    .remedies-grid {
        gap: 12px;
    }
    
    .remedy-card {
        padding: 12px;
        flex-direction: column;
        gap: 10px;
    }
    
    .remedy-rank {
        width: 28px;
        height: 28px;
        font-size: 0.85rem;
    }
    
    .remedy-content h5 {
        font-size: 1rem !important;
    }
    
    .remedy-content .common-name {
        font-size: 0.85rem !important;
    }
    
    .potency-badge {
        font-size: 0.75rem !important;
        padding: 3px 8px !important;
    }
    
    .remedy-indication,
    .remedy-keynotes,
    .remedy-indications {
        font-size: 0.85rem;
    }
    
    .match-score span {
        font-size: 0.75rem !important;
    }
    
    .remedy-source {
        position: static;
        display: inline-block;
        margin-top: 8px;
        font-size: 0.7rem;
    }
    
    .why-indicated,
    .skin-indications {
        padding: 10px !important;
    }
    
    .why-indicated strong,
    .skin-indications strong {
        font-size: 0.8rem !important;
    }
    
    .why-indicated p,
    .skin-indications p {
        font-size: 0.85rem !important;
    }
    
    .keynotes strong {
        font-size: 0.8rem !important;
    }
    
    .keynotes p {
        font-size: 0.85rem !important;
    }
    
    .modalities {
        flex-direction: column !important;
        gap: 8px !important;
    }
    
    .modalities > div {
        min-width: 100% !important;
    }
    
    /* RAG analysis summary */
    .rag-analysis-summary {
        padding: 15px !important;
    }
    
    .rag-analysis-summary h4 {
        font-size: 1rem !important;
    }
    
    .rag-analysis-summary p {
        font-size: 0.9rem !important;
    }
    
    .rag-condition span {
        font-size: 0.85rem !important;
        padding: 3px 10px !important;
    }
    
    /* Empty state */
    .empty-state {
        padding: 25px 15px;
    }
    
    .empty-state i {
        font-size: 36px;
    }
    
    .empty-state p {
        font-size: 0.9rem;
    }
    
    .empty-results .empty-illustration {
        font-size: 50px;
    }
    
    .empty-results h3 {
        font-size: 1.2rem;
    }
    
    .features-preview {
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .feature-item {
        flex: 0 0 calc(33% - 10px);
        font-size: 0.8rem;
    }
    
    .feature-item i {
        font-size: 20px;
    }
    
    /* Condition tags */
    .condition-tags {
        gap: 8px;
    }
    
    .condition-tag {
        padding: 6px 12px;
        font-size: 0.8rem;
    }
    
    /* Disclaimer box */
    .disclaimer-box {
        flex-direction: column;
        gap: 8px;
        padding: 12px;
        font-size: 0.85rem;
    }
    
    .disclaimer-box i {
        font-size: 1rem;
    }
    
    /* Alert messages */
    .alert {
        padding: 12px 15px;
        font-size: 0.9rem;
    }
    
    .alert i {
        font-size: 1rem;
    }
    
    /* Loading overlay */
    .analyzing-spinner {
        width: 50px;
        height: 50px;
    }
    
    .analyzing-text {
        font-size: 1rem;
    }
}

/* Extra small devices */
@media (max-width: 480px) {
    .dermo-container {
        padding: 8px;
    }
    
    .page-header h1 {
        font-size: 1.2rem;
    }
    
    .mode-tab {
        padding: 10px 8px;
    }
    
    .mode-tab span {
        font-size: 0.75rem;
    }
    
    .dashboard-card .card-header {
        padding: 10px 12px;
    }
    
    .dashboard-card .card-body {
        padding: 12px;
    }
    
    .camera-controls .btn {
        flex: 1 1 100%;
    }
    
    .tab-btn {
        padding: 8px 6px;
        font-size: 0.75rem;
    }
    
    .features-preview {
        gap: 10px;
    }
    
    .feature-item {
        flex: 0 0 100%;
    }
    
    .condition-tag {
        padding: 5px 10px;
        font-size: 0.75rem;
    }
    
    .remedy-card {
        padding: 10px;
    }
    
    .info-card {
        margin-top: 15px;
    }
}

/* Landscape orientation on mobile */
@media (max-width: 768px) and (orientation: landscape) {
    .camera-container {
        aspect-ratio: 16/9;
        max-height: 50vh;
    }
}

/* Mode Panels */
.mode-panel {
    display: none;
}

.mode-panel.active {
    display: block;
}

/* Analysis Mode Options - Base Styles */
.analysis-mode-options {
    display: flex;
    gap: 15px;
    margin-top: 8px;
}

.analysis-mode-options .mode-option {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 10px 15px;
    background: #f3f4f6;
    border-radius: 8px;
    flex: 1;
    border: 2px solid transparent;
    transition: all 0.2s;
}

.analysis-mode-options .mode-option input[type="radio"] {
    accent-color: #3b82f6;
    flex-shrink: 0;
}

.analysis-mode-options .mode-option input[value="rag_only"] {
    accent-color: #10b981;
}

.analysis-mode-options .mode-option div {
    min-width: 0;
}

.analysis-mode-options .mode-option strong {
    display: block;
    color: #1f2937;
}

.analysis-mode-options .mode-option small {
    color: #6b7280;
    display: block;
    line-height: 1.3;
}

/* Camera Styles */
.camera-container {
    position: relative;
    width: 100%;
    aspect-ratio: 4/3;
    background: #000;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 15px;
}

.camera-container video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Camera focus guide overlay */
.camera-focus-guide {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 70%;
    height: 70%;
    border: 2px dashed rgba(255, 255, 255, 0.5);
    border-radius: 12px;
    pointer-events: none;
}

.camera-focus-guide::before {
    content: 'Position skin area here';
    position: absolute;
    bottom: -30px;
    left: 50%;
    transform: translateX(-50%);
    color: rgba(255, 255, 255, 0.8);
    font-size: 12px;
    white-space: nowrap;
    background: rgba(0, 0, 0, 0.5);
    padding: 4px 10px;
    border-radius: 4px;
}

/* Camera quality indicator */
.camera-quality-indicator {
    position: absolute;
    top: 10px;
    right: 10px;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.camera-quality-indicator i {
    font-size: 10px;
}

.camera-quality-indicator.hd {
    background: rgba(16, 185, 129, 0.8);
}

.camera-quality-indicator.sd {
    background: rgba(245, 158, 11, 0.8);
}

.camera-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.8);
    color: white;
}

.camera-overlay i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.7;
}

.camera-overlay.hidden {
    display: none;
}

.captured-preview {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
}

.captured-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000;
}

/* Captured image quality badge */
.captured-quality-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: rgba(16, 185, 129, 0.9);
    color: white;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.retake-btn {
    position: absolute;
    bottom: 15px;
    right: 15px;
    padding: 10px 20px;
    background: rgba(255, 255, 255, 0.9);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
}

.camera-controls {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

.camera-controls .btn {
    flex: 1;
}

/* Image Preview */
.image-preview-container {
    position: relative;
    width: 100%;
    padding-top: 75%;
    background: #f0f0f0;
    border-radius: 8px;
    overflow: hidden;
}

.image-preview-container img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.remove-image {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 32px;
    height: 32px;
    background: rgba(239, 68, 68, 0.9);
    color: white;
    border: none;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* File Upload */
.file-upload-area {
    border: 2px dashed var(--border-color);
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.file-upload-area:hover,
.file-upload-area.dragover {
    border-color: var(--primary-color);
    background: var(--primary-light);
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

.upload-content .upload-icon {
    font-size: 48px;
    color: var(--primary-color);
    margin-bottom: 15px;
}

.upload-content h4 {
    margin-bottom: 5px;
    color: var(--text-primary);
}

.upload-content p {
    color: var(--text-secondary);
    margin-bottom: 10px;
}

.upload-hint {
    font-size: 0.85rem;
    color: var(--text-muted);
}

/* Results Styles */
.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.result-info-bar {
    display: flex;
    gap: 20px;
    padding: 12px 15px;
    background: var(--bg-secondary);
    border-radius: 8px;
    margin-bottom: 20px;
}

.result-info-bar .info-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.result-info-bar .info-item i {
    color: var(--primary-color);
}

/* Analysis Tabs */
.analysis-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 10px;
}

.tab-btn {
    padding: 10px 20px;
    background: transparent;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.tab-btn:hover {
    background: var(--bg-secondary);
}

.tab-btn.active {
    background: var(--primary-color);
    color: white;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

/* Analysis Box */
.analysis-box {
    background: var(--bg-secondary);
    padding: 20px;
    border-radius: 12px;
}

.analysis-box h4 {
    margin-bottom: 15px;
    color: var(--text-primary);
}

.condition-badge {
    margin-bottom: 15px;
}

.condition-badge .badge {
    font-size: 1rem;
    padding: 8px 16px;
}

.analysis-text {
    line-height: 1.7;
    color: var(--text-secondary);
}

.characteristics-list {
    margin-top: 20px;
}

.characteristics-list h5 {
    margin-bottom: 10px;
}

.characteristics-list ul {
    list-style: disc;
    padding-left: 20px;
}

.characteristics-list li {
    margin-bottom: 5px;
}

.severity-indicator {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--border-color);
}

.severity-badge {
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: 500;
    margin-left: 10px;
}

.severity-mild {
    background: #d1fae5;
    color: #065f46;
}

.severity-moderate {
    background: #fef3c7;
    color: #92400e;
}

.severity-severe {
    background: #fee2e2;
    color: #991b1b;
}

/* Remedies Grid */
.remedies-grid {
    display: grid;
    gap: 15px;
}

.remedy-card {
    display: flex;
    gap: 15px;
    padding: 15px;
    background: var(--bg-secondary);
    border-radius: 12px;
    border-left: 4px solid var(--primary-color);
    position: relative;
}

.remedy-card.ai-remedy {
    border-left-color: #8b5cf6;
}

.remedy-card.rag-remedy {
    border-left-color: #10b981;
}

/* Analysis Mode Options */
.analysis-mode-options input[type="radio"]:checked + div strong {
    color: #1e40af;
}

.analysis-mode-options label:has(input:checked) {
    border-color: #3b82f6;
    background: #eff6ff;
}

.analysis-mode-options label:has(input[value="rag_only"]:checked) {
    border-color: #10b981;
    background: #f0fdf4;
}

.analysis-mode-options label:has(input[value="rag_only"]:checked) strong {
    color: #059669;
}

.remedy-rank {
    width: 32px;
    height: 32px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    flex-shrink: 0;
}

.ai-remedy .remedy-rank {
    background: #8b5cf6;
}

.rag-remedy .remedy-rank {
    background: #10b981;
}

.remedy-content {
    flex: 1;
}

.remedy-content h5 {
    margin-bottom: 5px;
    color: var(--text-primary);
}

.potency-badge {
    display: inline-block;
    padding: 2px 10px;
    background: var(--primary-light);
    color: var(--primary-color);
    border-radius: 12px;
    font-size: 0.8rem;
    margin-bottom: 8px;
}

.remedy-indication,
.remedy-keynotes,
.remedy-indications {
    font-size: 0.9rem;
    color: var(--text-secondary);
    line-height: 1.5;
}

.match-score {
    margin: 8px 0;
}

.score-bar {
    height: 4px;
    background: var(--primary-color);
    border-radius: 2px;
    margin-bottom: 5px;
}

.remedy-source {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* Empty States */
.empty-state {
    text-align: center;
    padding: 40px;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-results .empty-illustration {
    font-size: 80px;
    color: var(--primary-color);
    opacity: 0.3;
    margin-bottom: 20px;
}

.features-preview {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-top: 30px;
}

.feature-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    color: var(--text-secondary);
}

.feature-item i {
    font-size: 24px;
    color: var(--primary-color);
}

/* Disclaimer */
.disclaimer-box {
    display: flex;
    gap: 12px;
    padding: 15px;
    background: #fef3c7;
    border-radius: 8px;
    margin-top: 20px;
    font-size: 0.9rem;
}

.disclaimer-box i {
    color: #d97706;
    font-size: 1.2rem;
    flex-shrink: 0;
}

.disclaimer-box p {
    color: #92400e;
    margin: 0;
}

/* Condition Tags */
.condition-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.condition-tag {
    padding: 8px 16px;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 20px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.condition-tag:hover {
    border-color: var(--primary-color);
    background: var(--primary-light);
}

.condition-tag i {
    font-size: 8px;
    margin-right: 5px;
    color: var(--primary-color);
}

/* Info Card */
.info-card {
    margin-top: 20px;
}

.info-list {
    list-style: none;
    padding: 0;
}

.info-list li {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
}

.step-number {
    width: 24px;
    height: 24px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
    flex-shrink: 0;
}

.info-note {
    display: flex;
    gap: 10px;
    padding: 12px;
    background: var(--primary-light);
    border-radius: 8px;
    margin-top: 15px;
}

.info-note i {
    color: var(--primary-color);
}

.info-note p {
    margin: 0;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

/* Loading State */
.analyzing-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.analyzing-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top-color: var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

.analyzing-text {
    color: white;
    margin-top: 20px;
    font-size: 1.1rem;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ========== NEW CSS CLASSES FOR RESPONSIVE DESIGN ========== */

/* RAG Analysis Summary Styles */
.rag-analysis-summary {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    border: 1px solid #86efac;
}

.rag-analysis-summary h4 {
    color: #166534;
    margin-bottom: 15px;
}

.rag-condition {
    margin-bottom: 12px;
}

.rag-condition strong {
    color: #166534;
}

.condition-badge-pill {
    background: #166534;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    margin-left: 8px;
    display: inline-block;
}

.rag-description {
    color: #14532d;
    margin-bottom: 12px;
}

.rag-characteristics {
    margin-bottom: 12px;
}

.rag-characteristics strong {
    color: #166534;
    display: block;
    margin-bottom: 8px;
}

.characteristics-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.char-tag {
    background: white;
    color: #166534;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 13px;
    border: 1px solid #86efac;
}

.rag-severity {
    margin-bottom: 8px;
}

.rag-severity strong {
    color: #166534;
}

.matched-patterns {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed #86efac;
}

.matched-patterns small {
    color: #166534;
}

/* Remedy Card Styles */
.remedies-heading {
    margin-bottom: 15px;
    color: #374151;
}

.remedy-name {
    font-size: 18px;
    color: #1e40af;
    margin-bottom: 5px;
}

.common-name {
    color: #6b7280;
    font-style: italic;
    margin-bottom: 10px;
}

.potency-row {
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}

.rag-potency {
    background: #3b82f6;
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
}

.dosage-text {
    color: #6b7280;
    font-size: 12px;
}

.rag-score {
    background: linear-gradient(90deg, #10b981, #059669);
}

.score-text {
    font-size: 12px;
    color: #059669;
}

/* Why Indicated Box */
.why-indicated {
    background: #fef3c7;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 12px;
    border-left: 4px solid #f59e0b;
}

.why-indicated strong {
    color: #92400e;
    font-size: 13px;
}

.why-indicated p {
    color: #78350f;
    margin: 8px 0 0 0;
    font-size: 13px;
    line-height: 1.5;
}

/* Skin Indications Box */
.skin-indications {
    background: #eff6ff;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 12px;
}

.skin-indications strong {
    color: #1e40af;
    font-size: 13px;
}

.skin-indications p {
    color: #1e3a8a;
    margin: 8px 0 0 0;
    font-size: 13px;
    line-height: 1.5;
}

/* Keynotes */
.keynotes {
    margin-bottom: 10px;
}

.keynotes strong {
    color: #374151;
    font-size: 13px;
}

.keynotes p {
    color: #4b5563;
    margin: 6px 0 0 0;
    font-size: 13px;
    line-height: 1.5;
}

/* Recommendations Box */
.recommendations-box {
    margin-top: 15px;
    padding: 15px;
    background: #f0f9ff;
    border-radius: 8px;
}

.recommendations-box h5 {
    color: #1e40af;
    margin-bottom: 10px;
}

.recommendations-box p {
    color: #1e3a8a;
    margin: 0;
    line-height: 1.6;
}

/* Modalities */
.modalities {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.modality-item {
    flex: 1;
    min-width: 120px;
}

.modality-item.worse small {
    color: #dc2626;
}

.modality-item.worse .modality-text {
    color: #991b1b;
    font-size: 12px;
    margin-top: 4px;
}

.modality-item.better small {
    color: #059669;
}

.modality-item.better .modality-text {
    color: #047857;
    font-size: 12px;
    margin-top: 4px;
}

/* Mobile adjustments for new classes */
@media (max-width: 768px) {
    .rag-analysis-summary {
        padding: 15px;
    }
    
    .rag-analysis-summary h4 {
        font-size: 1rem;
    }
    
    .condition-badge-pill {
        font-size: 0.85rem;
        padding: 3px 10px;
        margin-left: 0;
        margin-top: 6px;
        display: inline-block;
    }
    
    .rag-condition {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .characteristics-tags {
        gap: 5px;
    }
    
    .char-tag {
        font-size: 12px;
        padding: 3px 8px;
    }
    
    .remedy-name {
        font-size: 16px;
    }
    
    .rag-potency {
        font-size: 11px;
        padding: 3px 10px;
    }
    
    .why-indicated,
    .skin-indications,
    .keynotes {
        padding: 10px;
    }
    
    .why-indicated strong,
    .skin-indications strong,
    .keynotes strong {
        font-size: 12px;
    }
    
    .why-indicated p,
    .skin-indications p,
    .keynotes p {
        font-size: 12px;
    }
    
    .modalities {
        flex-direction: column;
        gap: 8px;
    }
    
    .modality-item {
        min-width: 100%;
    }
}

@media (max-width: 480px) {
    .rag-analysis-summary {
        padding: 12px;
    }
    
    .remedy-name {
        font-size: 14px;
    }
    
    .why-indicated,
    .skin-indications {
        padding: 8px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mode tabs
    const modeTabs = document.querySelectorAll('.mode-tab');
    const modePanels = document.querySelectorAll('.mode-panel');
    
    modeTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const mode = this.dataset.mode;
            
            modeTabs.forEach(t => t.classList.remove('active'));
            modePanels.forEach(p => p.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById(mode + 'Panel').classList.add('active');
            
            // Stop camera when switching to upload mode
            if (mode === 'upload' && cameraStream) {
                stopCamera();
            }
        });
    });
    
    // Analysis tabs
    const analysisTabs = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    analysisTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            
            analysisTabs.forEach(t => t.classList.remove('active'));
            tabPanes.forEach(p => p.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // File upload preview
    const fileInput = document.getElementById('skin_image');
    const dropZone = document.getElementById('dropZone');
    const imagePreview = document.getElementById('imagePreview');
    const previewImage = document.getElementById('previewImage');
    const uploadContent = dropZone.querySelector('.upload-content');
    const removeBtn = document.getElementById('removeImage');
    
    fileInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImage.src = e.target.result;
                uploadContent.style.display = 'none';
                imagePreview.style.display = 'block';
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
    
    removeBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        fileInput.value = '';
        previewImage.src = '';
        imagePreview.style.display = 'none';
        uploadContent.style.display = 'block';
    });
    
    // Drag and drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
    });
    
    dropZone.addEventListener('drop', function(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        if (files.length) {
            fileInput.files = files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });
    
    // Camera functionality
    let cameraStream = null;
    let currentFacingMode = 'environment';
    const video = document.getElementById('cameraVideo');
    const canvas = document.getElementById('cameraCanvas');
    const cameraOverlay = document.getElementById('cameraOverlay');
    const capturedPreview = document.getElementById('capturedPreview');
    const capturedImage = document.getElementById('capturedImage');
    const startCameraBtn = document.getElementById('startCameraBtn');
    const captureBtn = document.getElementById('captureBtn');
    const switchCameraBtn = document.getElementById('switchCameraBtn');
    const retakeBtn = document.getElementById('retakeBtn');
    const cameraImageInput = document.getElementById('cameraImageInput');
    const analyzeCameraBtn = document.getElementById('analyzeCameraBtn');
    
    startCameraBtn.addEventListener('click', startCamera);
    captureBtn.addEventListener('click', captureImage);
    switchCameraBtn.addEventListener('click', switchCamera);
    retakeBtn.addEventListener('click', retakeImage);
    
    async function startCamera() {
        try {
            const constraints = {
                video: {
                    facingMode: currentFacingMode,
                    width: { ideal: 1920, min: 1280 },
                    height: { ideal: 1080, min: 720 },
                    aspectRatio: { ideal: 4/3 },
                    frameRate: { ideal: 30 },
                    focusMode: 'continuous',
                    exposureMode: 'continuous',
                    whiteBalanceMode: 'continuous'
                }
            };
            
            cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
            
            // Apply advanced track settings for better quality
            const track = cameraStream.getVideoTracks()[0];
            if (track && track.getCapabilities) {
                const capabilities = track.getCapabilities();
                const settings = {};
                
                // Enable autofocus if available
                if (capabilities.focusMode && capabilities.focusMode.includes('continuous')) {
                    settings.focusMode = 'continuous';
                }
                // Set to maximum resolution available
                if (capabilities.width) {
                    settings.width = Math.min(capabilities.width.max, 1920);
                }
                if (capabilities.height) {
                    settings.height = Math.min(capabilities.height.max, 1080);
                }
                // Enable auto exposure
                if (capabilities.exposureMode && capabilities.exposureMode.includes('continuous')) {
                    settings.exposureMode = 'continuous';
                }
                
                if (Object.keys(settings).length > 0) {
                    try {
                        await track.applyConstraints(settings);
                    } catch (e) {
                        console.log('Could not apply advanced camera settings:', e);
                    }
                }
            }
            video.srcObject = cameraStream;
            
            // Show quality indicator and focus guide
            const focusGuide = document.getElementById('cameraFocusGuide');
            const qualityIndicator = document.getElementById('cameraQualityIndicator');
            const resolutionSpan = document.getElementById('cameraResolution');
            
            // Wait for video to load metadata to get actual resolution
            video.addEventListener('loadedmetadata', function() {
                const width = video.videoWidth;
                const height = video.videoHeight;
                resolutionSpan.textContent = `${width}x${height}`;
                
                // Update quality indicator class based on resolution
                if (width >= 1280) {
                    qualityIndicator.classList.add('hd');
                    qualityIndicator.classList.remove('sd');
                } else {
                    qualityIndicator.classList.add('sd');
                    qualityIndicator.classList.remove('hd');
                }
                
                focusGuide.style.display = 'block';
                qualityIndicator.style.display = 'flex';
            }, { once: true });
            
            cameraOverlay.classList.add('hidden');
            captureBtn.disabled = false;
            startCameraBtn.innerHTML = '<i class="fas fa-stop"></i> Stop Camera';
            startCameraBtn.onclick = stopCamera;
            
            // Check if multiple cameras available
            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices.filter(d => d.kind === 'videoinput');
            if (videoDevices.length > 1) {
                switchCameraBtn.style.display = 'block';
            }
        } catch (err) {
            console.error('Error accessing camera:', err);
            alert('Unable to access camera. Please ensure camera permissions are granted.');
        }
    }
    
    function stopCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
        }
        video.srcObject = null;
        cameraOverlay.classList.remove('hidden');
        captureBtn.disabled = true;
        startCameraBtn.innerHTML = '<i class="fas fa-video"></i> Start Camera';
        startCameraBtn.onclick = startCamera;
        switchCameraBtn.style.display = 'none';
        
        // Hide quality indicators
        document.getElementById('cameraFocusGuide').style.display = 'none';
        document.getElementById('cameraQualityIndicator').style.display = 'none';
    }
    
    async function switchCamera() {
        currentFacingMode = currentFacingMode === 'environment' ? 'user' : 'environment';
        stopCamera();
        await startCamera();
    }
    
    function captureImage() {
        // Use actual video dimensions for maximum quality
        const videoWidth = video.videoWidth;
        const videoHeight = video.videoHeight;
        
        // Set canvas to full video resolution
        canvas.width = videoWidth;
        canvas.height = videoHeight;
        
        const ctx = canvas.getContext('2d', { 
            alpha: false,
            desynchronized: false,
            willReadFrequently: false
        });
        
        // Enable image smoothing for better quality
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        
        // Draw the current video frame
        ctx.drawImage(video, 0, 0, videoWidth, videoHeight);
        
        // Apply slight sharpening for clearer skin details
        try {
            const imageDataObj = ctx.getImageData(0, 0, videoWidth, videoHeight);
            const sharpened = applySharpen(imageDataObj);
            ctx.putImageData(sharpened, 0, 0);
        } catch (e) {
            console.log('Sharpening skipped:', e);
        }
        
        // Use high-quality JPEG for better skin detail preservation (0.95 quality)
        const imageData = canvas.toDataURL('image/jpeg', 0.95);
        capturedImage.src = imageData;
        cameraImageInput.value = imageData;
        
        // Update captured quality badge
        const capturedResolution = document.getElementById('capturedResolution');
        capturedResolution.textContent = `${videoWidth}x${videoHeight} HD`;
        
        capturedPreview.style.display = 'block';
        video.style.display = 'none';
        analyzeCameraBtn.disabled = false;
        
        // Hide camera UI elements
        document.getElementById('cameraFocusGuide').style.display = 'none';
        document.getElementById('cameraQualityIndicator').style.display = 'none';
        
        // Show capture info
        console.log(`Captured image: ${videoWidth}x${videoHeight}`);
        
        stopCamera();
    }
    
    // Image sharpening function for clearer skin details
    function applySharpen(imageData) {
        const data = imageData.data;
        const width = imageData.width;
        const height = imageData.height;
        const output = new Uint8ClampedArray(data.length);
        
        // Sharpen kernel (light sharpening)
        const kernel = [
            0, -0.5, 0,
            -0.5, 3, -0.5,
            0, -0.5, 0
        ];
        
        for (let y = 1; y < height - 1; y++) {
            for (let x = 1; x < width - 1; x++) {
                for (let c = 0; c < 3; c++) {
                    let sum = 0;
                    for (let ky = -1; ky <= 1; ky++) {
                        for (let kx = -1; kx <= 1; kx++) {
                            const idx = ((y + ky) * width + (x + kx)) * 4 + c;
                            sum += data[idx] * kernel[(ky + 1) * 3 + (kx + 1)];
                        }
                    }
                    const idx = (y * width + x) * 4 + c;
                    output[idx] = Math.min(255, Math.max(0, sum));
                }
                // Alpha channel
                output[(y * width + x) * 4 + 3] = data[(y * width + x) * 4 + 3];
            }
        }
        
        // Copy edge pixels
        for (let y = 0; y < height; y++) {
            for (let x = 0; x < width; x++) {
                if (y === 0 || y === height - 1 || x === 0 || x === width - 1) {
                    const idx = (y * width + x) * 4;
                    output[idx] = data[idx];
                    output[idx + 1] = data[idx + 1];
                    output[idx + 2] = data[idx + 2];
                    output[idx + 3] = data[idx + 3];
                }
            }
        }
        
        return new ImageData(output, width, height);
    }
    
    function retakeImage() {
        capturedPreview.style.display = 'none';
        video.style.display = 'block';
        cameraImageInput.value = '';
        analyzeCameraBtn.disabled = true;
        startCamera();
    }
    
    // Condition tags - fill symptoms
    document.querySelectorAll('.condition-tag').forEach(tag => {
        tag.addEventListener('click', function() {
            const condition = this.dataset.condition;
            const conditionSymptoms = {
                'eczema': 'Dry, itchy, red patches on skin. May have flaking or crusting. Worse from heat and scratching.',
                'psoriasis': 'Thick, red, scaly patches with silvery scales. Commonly on elbows, knees, scalp.',
                'acne': 'Pimples, blackheads, whiteheads. May have oily skin. Commonly on face, back, chest.',
                'dermatitis': 'Red, itchy, inflamed skin. May be from contact with irritants or allergens.',
                'urticaria': 'Raised, itchy welts or hives. May come and go. Can be triggered by allergens.',
                'fungal': 'Red, itchy patches with defined borders. May have ring-shaped pattern. Scaling or peeling.',
                'vitiligo': 'White patches on skin due to loss of pigment. Smooth texture, no itching.',
                'herpes': 'Clusters of small blisters. Painful, burning sensation. May crust over.',
                'scabies': 'Intense itching, worse at night. Small red bumps. Track-like burrows in skin.',
                'ringworm': 'Circular, red, scaly patch with clearer center. Itchy. Spreads outward.'
            };
            
            const symptomsText = conditionSymptoms[condition] || '';
            document.getElementById('symptoms_upload').value = symptomsText;
            document.getElementById('symptoms_camera').value = symptomsText;
        });
    });
    
    // Form submission loading
    document.getElementById('uploadForm').addEventListener('submit', function() {
        showAnalyzingOverlay();
    });
    
    document.getElementById('cameraForm').addEventListener('submit', function() {
        showAnalyzingOverlay();
    });
    
    function showAnalyzingOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'analyzing-overlay';
        overlay.innerHTML = `
            <div class="analyzing-spinner"></div>
            <div class="analyzing-text">Analyzing skin condition...</div>
        `;
        document.body.appendChild(overlay);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
