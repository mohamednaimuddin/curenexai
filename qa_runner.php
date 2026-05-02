<?php
/**
 * QA Testing Runner - Web Interface
 * Comprehensive AI testing with Excel report generation
 */

require_once __DIR__ . '/includes/init.php';
requireLogin();

$conn = Database::getInstance()->getConnection();
$pageTitle = 'QA Testing Suite';
$error = '';
$success = '';
$testRunning = false;
$reportPath = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'run_test':
                // Start test in background or handle via AJAX
                $_SESSION['qa_test_config'] = [
                    'patient_count' => intval($_POST['patient_count'] ?? 100),
                    'consultations_per_patient' => intval($_POST['consultations_per_patient'] ?? 2),
                    'test_ai' => isset($_POST['test_ai']),
                    'cleanup_after' => isset($_POST['cleanup_after']),
                    'selected_diseases' => $_POST['diseases'] ?? []
                ];
                header('Location: qa_runner.php?run=1');
                exit;
                
            case 'download_report':
                $file = $_POST['report_file'] ?? '';
                if ($file && file_exists(__DIR__ . '/tests/reports/' . basename($file))) {
                    $filepath = __DIR__ . '/tests/reports/' . basename($file);
                    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                    header('Content-Length: ' . filesize($filepath));
                    readfile($filepath);
                    exit;
                }
                $error = 'Report file not found.';
                break;
                
            case 'cleanup':
                require_once __DIR__ . '/tests/qa_testing_framework.php';
                $framework = new QATestingFramework($conn);
                $framework->cleanup();
                $success = 'Test data cleaned up successfully.';
                break;
        }
    }
}

// Check if running test
if (isset($_GET['run']) && $_GET['run'] == '1') {
    $testRunning = true;
}

// Check for completed report
if (isset($_SESSION['qa_report_path']) && file_exists($_SESSION['qa_report_path'])) {
    $reportPath = $_SESSION['qa_report_path'];
}

// Get existing reports
$reports = [];
$reportsDir = __DIR__ . '/tests/reports/';
if (is_dir($reportsDir)) {
    $files = glob($reportsDir . 'QA_Report_*.xlsx');
    foreach ($files as $file) {
        $reports[] = [
            'name' => basename($file),
            'date' => filemtime($file),
            'size' => filesize($file)
        ];
    }
    usort($reports, fn($a, $b) => $b['date'] - $a['date']);
}

// Disease patterns available
$diseasePatterns = [
    'diabetes' => 'Diabetes Mellitus',
    'hypertension' => 'Hypertension',
    'gastritis' => 'Gastritis / GERD',
    'migraine' => 'Migraine',
    'pcos' => 'PCOS',
    'arthritis' => 'Arthritis',
    'asthma' => 'Asthma / Respiratory',
    'skin_disorders' => 'Skin Disorders',
    'thyroid_disorders' => 'Thyroid Disorders',
    'anxiety_depression' => 'Anxiety / Depression',
    'fever' => 'Fever / Infections'
];

include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-4 py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">
                <i class="bi bi-check2-circle me-2"></i>QA Testing Suite
            </h1>
            <p class="text-muted">Comprehensive AI diagnosis and remedy validation testing</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Test Configuration -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear me-2"></i>Test Configuration
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="testForm">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="action" value="run_test">
                        
                        <div class="mb-3">
                            <label class="form-label">Number of Test Patients</label>
                            <input type="range" class="form-range" name="patient_count" 
                                   id="patientCount" min="10" max="200" value="100"
                                   oninput="document.getElementById('patientCountDisplay').textContent = this.value">
                            <div class="d-flex justify-content-between">
                                <small>10</small>
                                <strong id="patientCountDisplay">100</strong>
                                <small>200</small>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Consultations per Patient</label>
                            <select name="consultations_per_patient" class="form-select">
                                <option value="1">1 consultation each</option>
                                <option value="2" selected>1-2 consultations (random)</option>
                                <option value="3">1-3 consultations (random)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Disease Patterns to Test</label>
                            <div class="row">
                                <?php foreach ($diseasePatterns as $key => $label): ?>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="diseases[]" value="<?= $key ?>" 
                                               id="disease_<?= $key ?>" checked>
                                        <label class="form-check-label" for="disease_<?= $key ?>">
                                            <?= htmlspecialchars($label) ?>
                                        </label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" 
                                        onclick="toggleAll(true)">Select All</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" 
                                        onclick="toggleAll(false)">Deselect All</button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="test_ai" 
                                       id="testAI" checked>
                                <label class="form-check-label" for="testAI">
                                    <strong>Test AI Systems</strong> (RAG + Gemini)
                                </label>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cleanup_after" 
                                       id="cleanupAfter" checked>
                                <label class="form-check-label" for="cleanupAfter">
                                    Clean up test data after completion
                                </label>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" id="runTestBtn">
                                <i class="bi bi-play-fill me-2"></i>Run QA Tests
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Test Progress / Results -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-activity me-2"></i>Test Progress
                    </h5>
                </div>
                <div class="card-body">
                    <div id="testProgress" style="display: <?= $testRunning ? 'block' : 'none' ?>;">
                        <div class="text-center mb-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Running...</span>
                            </div>
                        </div>
                        <h5 class="text-center" id="progressStatus">Initializing tests...</h5>
                        <div class="progress mb-3" style="height: 25px;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" id="progressBar" 
                                 style="width: 0%;">0%</div>
                        </div>
                        <div id="progressDetails" class="small text-muted">
                            <p><i class="bi bi-person me-1"></i>Patients: <span id="patientProgress">0/0</span></p>
                            <p><i class="bi bi-clipboard2-pulse me-1"></i>Consultations: <span id="consultationProgress">0/0</span></p>
                            <p><i class="bi bi-robot me-1"></i>AI Tests: <span id="aiTestProgress">0/0</span></p>
                        </div>
                    </div>

                    <div id="testResults" style="display: <?= $reportPath ? 'block' : 'none' ?>;">
                        <div class="alert alert-success">
                            <h5><i class="bi bi-check-circle me-2"></i>Test Complete!</h5>
                            <p class="mb-0">Report generated successfully.</p>
                        </div>
                        <form method="POST" class="d-grid">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="download_report">
                            <input type="hidden" name="report_file" value="<?= htmlspecialchars(basename($reportPath)) ?>">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-file-earmark-excel me-2"></i>Download Excel Report
                            </button>
                        </form>
                    </div>

                    <div id="noTestRun" style="display: <?= (!$testRunning && !$reportPath) ? 'block' : 'none' ?>;">
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-clipboard-check" style="font-size: 3rem;"></i>
                            <p class="mt-3">Configure test parameters and click "Run QA Tests" to begin.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Previous Reports -->
            <?php if (!empty($reports)): ?>
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-folder me-2"></i>Previous Reports
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush" style="max-height: 200px; overflow-y: auto;">
                        <?php foreach (array_slice($reports, 0, 10) as $report): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-file-earmark-excel text-success me-2"></i>
                                <small><?= htmlspecialchars($report['name']) ?></small>
                                <br>
                                <small class="text-muted"><?= date('M j, Y g:i A', $report['date']) ?></small>
                            </div>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="download_report">
                                <input type="hidden" name="report_file" value="<?= htmlspecialchars($report['name']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-download"></i>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="cleanup">
                                <button type="submit" class="btn btn-outline-danger w-100" 
                                        onclick="return confirm('This will remove all QA test patients and consultations. Continue?')">
                                    <i class="bi bi-trash me-2"></i>Clean Up Test Data
                                </button>
                            </form>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="tests/view_test_log.php" class="btn btn-outline-info w-100">
                                <i class="bi bi-journal-text me-2"></i>View Test Logs
                            </a>
                        </div>
                        <div class="col-md-4 mb-3">
                            <a href="documentation.php#qa-testing" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-book me-2"></i>Documentation
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAll(checked) {
    document.querySelectorAll('input[name="diseases[]"]').forEach(cb => {
        cb.checked = checked;
    });
}

// If test is running, start the test and poll for status
<?php if ($testRunning): ?>
let pollInterval;
let testStarted = false;
const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?>';

function startTest() {
    if (testStarted) return;
    testStarted = true;
    
    // Start the test in background
    fetch('api/start_qa_test.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': CSRF_TOKEN
        }
    })
    .then(response => response.json())
    .then(data => {
        console.log('Test initiated:', data);
        if (!data.success) {
            alert('Failed to start test: ' + (data.message || 'Unknown error'));
            window.location.href = 'qa_runner.php';
        }
    })
    .catch(error => {
        console.error('Start error:', error);
        alert('Failed to start test: ' + error.message);
        window.location.href = 'qa_runner.php';
    });
}

function pollTestStatus() {
    fetch('api/qa_test_status.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'running') {
                document.getElementById('progressBar').style.width = data.progress + '%';
                document.getElementById('progressBar').textContent = Math.round(data.progress) + '%';
                document.getElementById('progressStatus').textContent = data.message;
                document.getElementById('patientProgress').textContent = data.patients;
                document.getElementById('consultationProgress').textContent = data.consultations;
                document.getElementById('aiTestProgress').textContent = data.ai_tests;
            } else if (data.status === 'completed') {
                clearInterval(pollInterval);
                document.getElementById('testProgress').style.display = 'none';
                document.getElementById('testResults').style.display = 'block';
                
                if (data.report_file) {
                    document.querySelector('input[name="report_file"]').value = data.report_file;
                }
            } else if (data.status === 'error') {
                clearInterval(pollInterval);
                alert('Test failed: ' + data.message);
                window.location.href = 'qa_runner.php';
            }
        })
        .catch(error => {
            console.error('Error polling status:', error);
        });
}

// Start test and polling
startTest();
pollInterval = setInterval(pollTestStatus, 1500);
<?php endif; ?>

// Form submission with loading state
document.getElementById('testForm').addEventListener('submit', function(e) {
    document.getElementById('runTestBtn').disabled = true;
    document.getElementById('runTestBtn').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Starting...';
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
