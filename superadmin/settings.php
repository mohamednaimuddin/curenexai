<?php
/**
 * Super Admin - System Settings
 */

define('ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'System Settings';
generateCsrfToken();

$success = '';
$error = '';

// Default settings structure (define before handling POST)
$defaultSettings = [
    'General' => [
        'site_name' => ['label' => 'Site Name', 'type' => 'text', 'default' => 'Homeopathic Doctor Assistant'],
        'site_description' => ['label' => 'Site Description', 'type' => 'textarea', 'default' => ''],
        'maintenance_mode' => ['label' => 'Maintenance Mode', 'type' => 'boolean', 'default' => 'false'],
    ],
    'Registration' => [
        'registration_enabled' => ['label' => 'Allow New Registrations', 'type' => 'boolean', 'default' => 'true'],
        'email_verification_required' => ['label' => 'Require Email Verification', 'type' => 'boolean', 'default' => 'true'],
        'max_patients_per_doctor' => ['label' => 'Max Patients Per Doctor', 'type' => 'number', 'default' => '500'],
    ],
    'AI Features' => [
        'enable_ai_suggestions' => ['label' => 'Enable AI Suggestions', 'type' => 'boolean', 'default' => 'true'],
        'gemini_api_enabled' => ['label' => 'Enable Gemini API', 'type' => 'boolean', 'default' => 'true'],
    ],
    'Security' => [
        'session_timeout_minutes' => ['label' => 'Session Timeout (minutes)', 'type' => 'number', 'default' => '60'],
        'max_login_attempts' => ['label' => 'Max Login Attempts', 'type' => 'number', 'default' => '5'],
        'lockout_duration_minutes' => ['label' => 'Lockout Duration (minutes)', 'type' => 'number', 'default' => '15'],
    ],
];

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $postedSettings = $_POST['settings'] ?? [];
    
    try {
        // Process all settings, including unchecked checkboxes
        foreach ($defaultSettings as $section => $fields) {
            foreach ($fields as $key => $config) {
                // For boolean (checkbox) fields, if not in POST data, set to 'false'
                if ($config['type'] === 'boolean') {
                    $value = isset($postedSettings[$key]) ? 'true' : 'false';
                } else {
                    $value = $postedSettings[$key] ?? $config['default'];
                }
                
                $existing = DB::queryOne("SELECT id FROM system_settings WHERE setting_key = ?", [$key]);
                
                if ($existing) {
                    DB::update('system_settings', [
                        'setting_value' => $value,
                        'setting_type' => $config['type'],
                        'updated_at' => date('Y-m-d H:i:s')
                    ], 'setting_key = ?', [$key]);
                } else {
                    DB::insert('system_settings', [
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'setting_type' => $config['type'],
                        'description' => $config['label']
                    ]);
                }
            }
        }
        
        logAdminActivity($_SESSION['admin_id'], 'update_settings', 'Updated system settings');
        $success = 'Settings saved successfully.';
    } catch (Exception $e) {
        $error = 'Failed to save settings: ' . $e->getMessage();
    }
}

// Get current settings
$settingsData = [];
$tableExists = true;
try {
    $rows = DB::query("SELECT * FROM system_settings ORDER BY setting_key");
    foreach ($rows as $row) {
        $settingsData[$row['setting_key']] = $row;
    }
} catch (Exception $e) {
    $tableExists = false;
    $error = 'Settings table not found. Please run the database migration.';
}

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">System Settings</h4>
        <p class="text-muted mb-0">Configure application-wide settings</p>
    </div>
    <div>
        <button type="button" class="btn btn-outline-secondary" onclick="location.reload()">
            <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!$tableExists): ?>
    <div class="alert alert-warning">
        <h5><i class="bi bi-exclamation-triangle me-2"></i>Database Setup Required</h5>
        <p class="mb-2">The system_settings table does not exist. Run this SQL to create it:</p>
        <pre class="bg-dark text-light p-3 rounded"><code>CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50),
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);</code></pre>
    </div>
<?php else: ?>

<form method="POST" id="settingsForm">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
    
    <div class="row g-4">
        <?php foreach ($defaultSettings as $section => $fields): ?>
            <div class="col-lg-6">
                <div class="data-table h-100">
                    <div class="table-header">
                        <h5 class="mb-0">
                            <?php
                            $icons = [
                                'General' => 'gear-fill',
                                'Registration' => 'person-plus-fill',
                                'AI Features' => 'robot',
                                'Security' => 'shield-lock-fill',
                            ];
                            ?>
                            <i class="bi bi-<?php echo $icons[$section] ?? 'sliders'; ?> me-2 text-primary"></i>
                            <?php echo $section; ?>
                        </h5>
                    </div>
                    <div class="p-4">
                        <?php foreach ($fields as $key => $config): ?>
                            <?php
                            $currentValue = $settingsData[$key]['setting_value'] ?? $config['default'];
                            ?>
                            <div class="mb-4">
                                <label class="form-label fw-semibold d-flex align-items-center gap-2">
                                    <?php echo $config['label']; ?>
                                    <?php if ($config['type'] === 'boolean'): ?>
                                        <span class="badge <?php echo $currentValue === 'true' ? 'bg-success' : 'bg-secondary'; ?> toggle-badge" id="badge_<?php echo $key; ?>">
                                            <?php echo $currentValue === 'true' ? 'ON' : 'OFF'; ?>
                                        </span>
                                    <?php endif; ?>
                                </label>
                                
                                <?php if ($config['type'] === 'boolean'): ?>
                                    <div class="form-check form-switch form-switch-lg">
                                        <input class="form-check-input" type="checkbox" 
                                               name="settings[<?php echo $key; ?>]" 
                                               id="<?php echo $key; ?>"
                                               value="true" 
                                               <?php echo $currentValue === 'true' ? 'checked' : ''; ?>
                                               onchange="updateToggleBadge('<?php echo $key; ?>', this.checked)">
                                    </div>
                                <?php elseif ($config['type'] === 'textarea'): ?>
                                    <textarea class="form-control" name="settings[<?php echo $key; ?>]" rows="2" placeholder="Enter <?php echo strtolower($config['label']); ?>"><?php echo htmlspecialchars($currentValue); ?></textarea>
                                <?php elseif ($config['type'] === 'number'): ?>
                                    <input type="number" class="form-control" name="settings[<?php echo $key; ?>]" 
                                           value="<?php echo htmlspecialchars($currentValue); ?>" min="0">
                                <?php else: ?>
                                    <input type="text" class="form-control" name="settings[<?php echo $key; ?>]" 
                                           value="<?php echo htmlspecialchars($currentValue); ?>" placeholder="Enter <?php echo strtolower($config['label']); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    
    <div class="mt-4 d-flex gap-3 flex-wrap">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="bi bi-check-lg me-2"></i>Save All Settings
        </button>
        <button type="button" class="btn btn-outline-secondary btn-lg" onclick="resetForm()">
            <i class="bi bi-arrow-counterclockwise me-2"></i>Reset Changes
        </button>
    </div>
</form>

<?php endif; ?>

<!-- System Info -->
<div class="row g-4 mt-4">
    <div class="col-12">
        <div class="data-table">
            <div class="table-header">
                <h5 class="mb-0"><i class="bi bi-info-circle me-2 text-info"></i>System Information</h5>
            </div>
            <div class="p-4">
                <div class="row g-4">
                    <div class="col-md-4 col-sm-6">
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                            <div class="bg-primary bg-opacity-10 p-2 rounded">
                                <i class="bi bi-filetype-php text-primary fs-4"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">PHP Version</small>
                                <strong><?php echo phpversion(); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                            <div class="bg-success bg-opacity-10 p-2 rounded">
                                <i class="bi bi-server text-success fs-4"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">Server</small>
                                <strong><?php echo explode('/', $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown')[0]; ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                            <div class="bg-warning bg-opacity-10 p-2 rounded">
                                <i class="bi bi-database text-warning fs-4"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">Database</small>
                                <strong>MySQL / MariaDB</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                            <div class="bg-info bg-opacity-10 p-2 rounded">
                                <i class="bi bi-box-seam text-info fs-4"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">App Version</small>
                                <strong>1.0.0</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                            <div class="bg-danger bg-opacity-10 p-2 rounded">
                                <i class="bi bi-people text-danger fs-4"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">Total Doctors</small>
                                <strong><?php echo number_format(DB::queryOne("SELECT COUNT(*) as c FROM doctors")['c'] ?? 0); ?></strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 col-sm-6">
                        <div class="d-flex align-items-center gap-3 p-3 bg-light rounded">
                            <div class="bg-secondary bg-opacity-10 p-2 rounded">
                                <i class="bi bi-person-badge text-secondary fs-4"></i>
                            </div>
                            <div>
                                <small class="text-muted d-block">Total Patients</small>
                                <strong><?php echo number_format(DB::queryOne("SELECT COUNT(*) as c FROM patients")['c'] ?? 0); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Additional Styles -->
<style>
    .form-switch-lg .form-check-input {
        width: 3em;
        height: 1.5em;
        cursor: pointer;
    }
    
    .form-switch-lg .form-check-input:checked {
        background-color: var(--admin-highlight, #6366f1);
        border-color: var(--admin-highlight, #6366f1);
    }
    
    .toggle-badge {
        font-size: 0.65rem;
        padding: 3px 8px;
        transition: all 0.3s ease;
    }
    
    .data-table .p-4 .mb-4:last-child {
        margin-bottom: 0 !important;
    }
</style>

<script>
    function updateToggleBadge(key, checked) {
        const badge = document.getElementById('badge_' + key);
        if (badge) {
            badge.textContent = checked ? 'ON' : 'OFF';
            badge.className = 'badge toggle-badge ' + (checked ? 'bg-success' : 'bg-secondary');
        }
    }
    
    function resetForm() {
        if (confirm('Are you sure you want to reset all changes? This will reload the page.')) {
            location.reload();
        }
    }
    
    // Form change tracking
    let formChanged = false;
    document.getElementById('settingsForm')?.addEventListener('change', function() {
        formChanged = true;
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (formChanged) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    
    document.getElementById('settingsForm')?.addEventListener('submit', function() {
        formChanged = false;
    });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
