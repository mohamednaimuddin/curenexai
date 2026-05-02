<?php
/**
 * Privacy Settings - AI Data Sharing Preferences
 * Allows doctors to control whether their patient data is sent to external AI services
 */
require_once __DIR__ . '/includes/init.php';
requireLogin();

$pageTitle = 'Privacy & AI Settings';
$doctor_id = $_SESSION['doctor_id'];
$success = '';
$error = '';

// Get current settings
$doctor = DB::queryOne("SELECT * FROM doctors WHERE id = ?", [$doctor_id]);
$current_ai_consent = $doctor['ai_consent'] ?? 1; // Default to enabled for existing users

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $ai_consent = isset($_POST['ai_consent']) ? 1 : 0;
        
        try {
            DB::update('doctors', ['ai_consent' => $ai_consent], 'id = ?', [$doctor_id]);
            $current_ai_consent = $ai_consent;
            $success = 'Privacy settings updated successfully!';
            
            // Log the change for audit
            error_log("Doctor ID {$doctor_id} changed AI consent to: " . ($ai_consent ? 'ENABLED' : 'DISABLED'));
        } catch (Exception $e) {
            $error = 'Failed to update settings. Please try again.';
            error_log('Privacy settings update error: ' . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="dashboard-card privacy-settings-card">
    <div class="card-header">
        <h1><i class="fas fa-shield-alt"></i> Privacy & AI Settings</h1>
        <p class="text-muted">Control how your patient data is used with AI services</p>
    </div>
    
    <div class="card-body">
        <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <!-- AI Consent Section -->
            <div class="privacy-section">
                <h3><i class="fas fa-robot"></i> External AI Services</h3>
                <p class="section-description">
                    Some features use Google's Gemini AI to provide enhanced analysis and suggestions. 
                    When enabled, anonymized medical data may be sent to external AI servers.
                </p>
                
                <div class="consent-toggle-wrapper">
                    <label class="consent-toggle">
                        <input type="checkbox" name="ai_consent" <?php echo $current_ai_consent ? 'checked' : ''; ?>>
                        <span class="toggle-slider"></span>
                        <span class="toggle-label">
                            <strong>Enable External AI Analysis</strong>
                            <small>Allow sending anonymized data to Gemini AI for enhanced suggestions</small>
                        </span>
                    </label>
                </div>
                
                <div class="privacy-info-box <?php echo $current_ai_consent ? 'enabled' : 'disabled'; ?>" id="privacyInfoBox">
                    <?php if ($current_ai_consent): ?>
                    <div class="info-enabled">
                        <h4><i class="fas fa-check-circle"></i> AI Analysis Enabled</h4>
                        <p>When using features like Lab Report Analysis, Dermo Skin Analysis, and AI Suggestions:</p>
                        <ul>
                            <li><i class="fas fa-user-secret"></i> <strong>Patient names are removed</strong> before sending</li>
                            <li><i class="fas fa-id-card-alt"></i> <strong>Patient IDs are anonymized</strong></li>
                            <li><i class="fas fa-phone-slash"></i> <strong>Contact information is stripped</strong></li>
                            <li><i class="fas fa-file-medical"></i> Only medical data (symptoms, lab values) is shared</li>
                            <li><i class="fas fa-database"></i> RAG (local database) suggestions always work</li>
                        </ul>
                    </div>
                    <?php else: ?>
                    <div class="info-disabled">
                        <h4><i class="fas fa-ban"></i> AI Analysis Disabled</h4>
                        <p>External AI features are turned off. You will still have access to:</p>
                        <ul>
                            <li><i class="fas fa-database"></i> <strong>RAG Database Suggestions</strong> - Local remedy matching</li>
                            <li><i class="fas fa-book-medical"></i> <strong>Disease Diagnosis</strong> - 100% local processing</li>
                            <li><i class="fas fa-search"></i> <strong>Repertory Search</strong> - Local database only</li>
                        </ul>
                        <p class="text-muted"><small>Gemini AI suggestions will not be available in Lab Analysis, Dermo, and other AI features.</small></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Data Handling Info -->
            <div class="privacy-section">
                <h3><i class="fas fa-info-circle"></i> How We Protect Your Data</h3>
                <div class="protection-grid">
                    <div class="protection-item">
                        <i class="fas fa-user-shield"></i>
                        <div>
                            <strong>Anonymization</strong>
                            <p>Patient names, IDs, and contact info are automatically removed before any AI processing</p>
                        </div>
                    </div>
                    <div class="protection-item">
                        <i class="fas fa-lock"></i>
                        <div>
                            <strong>Encryption</strong>
                            <p>All data is encrypted in transit using TLS/HTTPS</p>
                        </div>
                    </div>
                    <div class="protection-item">
                        <i class="fas fa-trash-alt"></i>
                        <div>
                            <strong>No Storage</strong>
                            <p>Google Gemini does not store or train on your data</p>
                        </div>
                    </div>
                    <div class="protection-item">
                        <i class="fas fa-clipboard-check"></i>
                        <div>
                            <strong>Audit Trail</strong>
                            <p>All AI usage is logged for your records</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Save Privacy Settings
                </button>
                <a href="settings.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Settings
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.privacy-settings-card {
    max-width: 800px;
    margin: 40px auto;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    border-radius: 16px;
    background: #fff;
}

.privacy-settings-card .card-header {
    padding: 28px 32px 16px;
    border-bottom: 1px solid #eee;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px 16px 0 0;
    color: #fff;
}

.privacy-settings-card .card-header h1 {
    font-size: 1.8rem;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.privacy-settings-card .card-header .text-muted {
    color: rgba(255,255,255,0.85);
    margin: 0;
}

.privacy-settings-card .card-body {
    padding: 32px;
}

.privacy-section {
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid #eee;
}

.privacy-section:last-of-type {
    border-bottom: none;
}

.privacy-section h3 {
    font-size: 1.2rem;
    color: #333;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.privacy-section h3 i {
    color: #667eea;
}

.section-description {
    color: #666;
    margin-bottom: 20px;
    line-height: 1.6;
}

/* Toggle Switch */
.consent-toggle-wrapper {
    margin: 20px 0;
}

.consent-toggle {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    cursor: pointer;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
    transition: all 0.3s ease;
}

.consent-toggle:hover {
    border-color: #667eea;
    background: #f0f4ff;
}

.consent-toggle input {
    display: none;
}

.toggle-slider {
    width: 56px;
    height: 30px;
    background: #ccc;
    border-radius: 30px;
    position: relative;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.toggle-slider::after {
    content: '';
    position: absolute;
    width: 24px;
    height: 24px;
    background: #fff;
    border-radius: 50%;
    top: 3px;
    left: 3px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.consent-toggle input:checked + .toggle-slider {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.consent-toggle input:checked + .toggle-slider::after {
    left: 29px;
}

.toggle-label {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.toggle-label strong {
    font-size: 1.1rem;
    color: #333;
}

.toggle-label small {
    color: #666;
    font-size: 0.9rem;
}

/* Privacy Info Box */
.privacy-info-box {
    padding: 20px;
    border-radius: 12px;
    margin-top: 16px;
}

.privacy-info-box.enabled {
    background: #e8f5e9;
    border: 1px solid #a5d6a7;
}

.privacy-info-box.disabled {
    background: #fff3e0;
    border: 1px solid #ffcc80;
}

.privacy-info-box h4 {
    margin: 0 0 12px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-enabled h4 {
    color: #2e7d32;
}

.info-disabled h4 {
    color: #e65100;
}

.privacy-info-box ul {
    margin: 12px 0;
    padding-left: 0;
    list-style: none;
}

.privacy-info-box li {
    padding: 6px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.privacy-info-box li i {
    width: 20px;
    color: #667eea;
}

/* Protection Grid */
.protection-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-top: 16px;
}

.protection-item {
    display: flex;
    gap: 14px;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 10px;
}

.protection-item i {
    font-size: 1.5rem;
    color: #667eea;
    flex-shrink: 0;
}

.protection-item strong {
    display: block;
    margin-bottom: 4px;
    color: #333;
}

.protection-item p {
    margin: 0;
    font-size: 0.85rem;
    color: #666;
    line-height: 1.4;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 16px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 1px solid #eee;
}

.form-actions .btn-lg {
    padding: 14px 28px;
    font-size: 1rem;
}

/* Responsive */
@media (max-width: 768px) {
    .privacy-settings-card {
        margin: 16px;
    }
    
    .privacy-settings-card .card-body {
        padding: 20px;
    }
    
    .protection-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.querySelector('input[name="ai_consent"]');
    const infoBox = document.getElementById('privacyInfoBox');
    
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            infoBox.className = 'privacy-info-box enabled';
            infoBox.innerHTML = `
                <div class="info-enabled">
                    <h4><i class="fas fa-check-circle"></i> AI Analysis Enabled</h4>
                    <p>When using features like Lab Report Analysis, Dermo Skin Analysis, and AI Suggestions:</p>
                    <ul>
                        <li><i class="fas fa-user-secret"></i> <strong>Patient names are removed</strong> before sending</li>
                        <li><i class="fas fa-id-card-alt"></i> <strong>Patient IDs are anonymized</strong></li>
                        <li><i class="fas fa-phone-slash"></i> <strong>Contact information is stripped</strong></li>
                        <li><i class="fas fa-file-medical"></i> Only medical data (symptoms, lab values) is shared</li>
                        <li><i class="fas fa-database"></i> RAG (local database) suggestions always work</li>
                    </ul>
                </div>
            `;
        } else {
            infoBox.className = 'privacy-info-box disabled';
            infoBox.innerHTML = `
                <div class="info-disabled">
                    <h4><i class="fas fa-ban"></i> AI Analysis Disabled</h4>
                    <p>External AI features are turned off. You will still have access to:</p>
                    <ul>
                        <li><i class="fas fa-database"></i> <strong>RAG Database Suggestions</strong> - Local remedy matching</li>
                        <li><i class="fas fa-book-medical"></i> <strong>Disease Diagnosis</strong> - 100% local processing</li>
                        <li><i class="fas fa-search"></i> <strong>Repertory Search</strong> - Local database only</li>
                    </ul>
                    <p class="text-muted"><small>Gemini AI suggestions will not be available in Lab Analysis, Dermo, and other AI features.</small></p>
                </div>
            `;
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
