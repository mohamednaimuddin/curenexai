<?php
/**
 * Automated AI Consultation Testing Page
 * 
 * Runs automated consultations, tests RAG & Gemini output,
 * validates results, and generates PDF/XML journals.
 * 
 * @author CurenexAI
 * @version 1.0.0
 */

require_once __DIR__ . '/includes/init.php';
requireLogin();

// Check if user has permission (doctor or admin)
$doctorId = getLoggedInDoctorId();

$pageTitle = 'Automated AI Testing';
$results = null;
$error = null;
$exportedFiles = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        // Load the automator classes
        require_once __DIR__ . '/includes/consultation_automator.php';
        require_once __DIR__ . '/includes/journal_exporter.php';
        
        if ($action === 'run_tests') {
            // Run automated tests
            $automator = new ConsultationAutomator($doctorId);
            $testResults = $automator->runAllTests();
            
            // Store results in session for export
            $_SESSION['last_test_results'] = $testResults;
            
            $results = $testResults;
            
            // Auto cleanup test data if requested
            if (isset($_POST['cleanup']) && $_POST['cleanup'] === '1') {
                $automator->cleanup();
            }
        }
        
        if ($action === 'export_journal') {
            // Export last test results
            if (empty($_SESSION['last_test_results'])) {
                throw new Exception('No test results available to export. Please run tests first.');
            }
            
            $testResults = $_SESSION['last_test_results'];
            $format = $_POST['format'] ?? 'both';
            
            $exporter = new JournalExporter(
                $testResults['results'],
                $testResults['summary'],
                $testResults['journal']
            );
            
            if ($format === 'pdf') {
                $exportedFiles = ['pdf' => $exporter->exportToPDF()];
            } elseif ($format === 'xml') {
                $exportedFiles = ['xml' => $exporter->exportToXML()];
            } else {
                $exportedFiles = $exporter->exportAll();
            }
            
            $results = $testResults;
        }
        
        if ($action === 'run_single_case') {
            // Run a specific test case
            require_once __DIR__ . '/includes/consultation_automator.php';
            
            $caseData = [
                'name' => sanitize($_POST['case_name'] ?? 'Custom Test'),
                'patient' => [
                    'patient_name' => sanitize($_POST['patient_name'] ?? 'Custom Patient'),
                    'age' => (int)($_POST['age'] ?? 30),
                    'gender' => sanitize($_POST['gender'] ?? 'male'),
                    'blood_group' => sanitize($_POST['blood_group'] ?? 'Unknown'),
                    'allergies' => sanitize($_POST['allergies'] ?? 'None')
                ],
                'consultation' => [
                    'chief_complaint' => sanitize($_POST['chief_complaint'] ?? ''),
                    'present_illness' => sanitize($_POST['present_illness'] ?? ''),
                    'thermal_state' => sanitize($_POST['thermal_state'] ?? ''),
                    'thirst' => sanitize($_POST['thirst'] ?? ''),
                    'appetite' => sanitize($_POST['appetite'] ?? ''),
                    'mental_state' => sanitize($_POST['mental_state'] ?? ''),
                    'modalities' => sanitize($_POST['modalities'] ?? '')
                ],
                'symptoms' => [],
                'expected_remedies' => array_filter(array_map('trim', explode(',', $_POST['expected_remedies'] ?? '')))
            ];
            
            // Parse symptoms
            $symptomTexts = $_POST['symptoms'] ?? [];
            $symptomIntensities = $_POST['symptom_intensities'] ?? [];
            $symptomLocations = $_POST['symptom_locations'] ?? [];
            
            for ($i = 0; $i < count($symptomTexts); $i++) {
                if (!empty($symptomTexts[$i])) {
                    $caseData['symptoms'][] = [
                        'symptom_text' => sanitize($symptomTexts[$i]),
                        'intensity' => sanitize($symptomIntensities[$i] ?? 'moderate'),
                        'location' => sanitize($symptomLocations[$i] ?? '')
                    ];
                }
            }
            
            $automator = new ConsultationAutomator($doctorId);
            $singleResult = $automator->runTestCase($caseData, 1);
            
            $results = [
                'success' => true,
                'results' => [$singleResult],
                'summary' => [
                    'total_tests' => 1,
                    'passed' => $singleResult['overall_status'] === 'PASS' ? 1 : 0,
                    'failed' => $singleResult['overall_status'] === 'FAIL' ? 1 : 0,
                    'warnings' => $singleResult['overall_status'] === 'WARN' ? 1 : 0,
                    'errors' => $singleResult['overall_status'] === 'ERROR' ? 1 : 0,
                    'pass_rate' => $singleResult['overall_status'] === 'PASS' ? 100 : 0
                ],
                'journal' => $automator->getJournal()
            ];
            
            $_SESSION['last_test_results'] = $results;
            
            // Cleanup if requested
            if (isset($_POST['cleanup']) && $_POST['cleanup'] === '1') {
                $automator->cleanup();
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get existing results from session
if (!$results && !empty($_SESSION['last_test_results'])) {
    $results = $_SESSION['last_test_results'];
}

?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<style>
.test-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.test-card {
    background: var(--card-bg);
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow: hidden;
}

.test-card-header {
    padding: 15px 20px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.test-card-body {
    padding: 20px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 5px 12px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 13px;
}

.status-pass { background: #d4edda; color: #155724; }
.status-fail { background: #f8d7da; color: #721c24; }
.status-warn { background: #fff3cd; color: #856404; }
.status-error { background: #f5c6cb; color: #721c24; }

.test-result-item {
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 15px;
    overflow: hidden;
}

.test-result-header {
    padding: 12px 15px;
    background: var(--light);
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
}

.test-result-content {
    padding: 15px;
    display: none;
}

.test-result-content.expanded {
    display: block;
}

.ai-section {
    background: var(--light);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

.ai-section h5 {
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.ai-section.rag h5 { color: #16A085; }
.ai-section.gemini h5 { color: #8E44AD; }

.remedy-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.remedy-tag {
    background: var(--primary);
    color: white;
    padding: 4px 10px;
    border-radius: 15px;
    font-size: 12px;
}

.remark-list {
    list-style: none;
    padding: 0;
}

.remark-list li {
    padding: 8px 12px;
    margin-bottom: 5px;
    border-radius: 5px;
    font-size: 13px;
}

.remark-success { background: #d4edda; color: #155724; }
.remark-info { background: #d1ecf1; color: #0c5460; }
.remark-warning { background: #fff3cd; color: #856404; }
.remark-error { background: #f8d7da; color: #721c24; }

.validation-table {
    width: 100%;
    font-size: 13px;
}

.validation-table th,
.validation-table td {
    padding: 8px;
    border: 1px solid var(--border);
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.stat-item {
    text-align: center;
    padding: 20px;
    background: var(--light);
    border-radius: 10px;
}

.stat-value {
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    color: var(--muted);
    font-size: 13px;
}

.export-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.export-alert {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.export-alert a {
    color: #155724;
    font-weight: 600;
}

.custom-test-form {
    display: grid;
    gap: 15px;
}

.symptom-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 10px;
    align-items: end;
}

.add-symptom-btn {
    background: var(--success);
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 5px;
    cursor: pointer;
}

.remove-symptom-btn {
    background: var(--danger);
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 5px;
    cursor: pointer;
}

.tabs {
    display: flex;
    border-bottom: 2px solid var(--border);
    margin-bottom: 20px;
}

.tab {
    padding: 12px 25px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    transition: all 0.3s;
}

.tab.active {
    border-bottom-color: var(--primary);
    color: var(--primary);
    font-weight: 600;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}
</style>

<div class="test-container">
    <div class="page-header mb-4">
        <h1><i class="fas fa-robot"></i> Automated AI Consultation Testing</h1>
        <p class="text-muted">Test RAG & Gemini AI outputs with automated consultations and generate detailed journals</p>
    </div>
    
    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    
    <?php if ($exportedFiles): ?>
    <div class="export-alert">
        <h5><i class="fas fa-check-circle"></i> Journal Exported Successfully!</h5>
        <p>Your test journal has been exported to:</p>
        <ul>
            <?php foreach ($exportedFiles as $format => $path): ?>
            <li>
                <strong><?= strtoupper($format) ?>:</strong> 
                <a href="<?= APP_URL ?>/logs/journals/<?= basename($path) ?>" target="_blank">
                    <?= basename($path) ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Tabs -->
    <div class="tabs">
        <div class="tab active" onclick="switchTab('standard')">
            <i class="fas fa-list"></i> Standard Test Suite
        </div>
        <div class="tab" onclick="switchTab('custom')">
            <i class="fas fa-edit"></i> Custom Test Case
        </div>
        <?php if ($results): ?>
        <div class="tab" onclick="switchTab('results')">
            <i class="fas fa-chart-bar"></i> Latest Results
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Standard Test Tab -->
    <div id="tab-standard" class="tab-content active">
        <div class="test-card">
            <div class="test-card-header">
                <h4><i class="fas fa-play-circle"></i> Run Standard Test Suite</h4>
            </div>
            <div class="test-card-body">
                <p>Run 5 pre-configured test cases covering common consultation scenarios:</p>
                <ul>
                    <li><strong>Respiratory:</strong> Cold & Cough symptoms</li>
                    <li><strong>Digestive:</strong> Gastritis symptoms</li>
                    <li><strong>Musculoskeletal:</strong> Joint pain/arthritis</li>
                    <li><strong>Mental:</strong> Anxiety & panic attacks</li>
                    <li><strong>Skin:</strong> Eczema symptoms</li>
                </ul>
                
                <form method="POST" class="mt-4">
                    <input type="hidden" name="action" value="run_tests">
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="cleanup" name="cleanup" value="1" checked>
                        <label class="form-check-label" for="cleanup">
                            Clean up test data after completion (recommended)
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-rocket"></i> Run All Tests
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Custom Test Tab -->
    <div id="tab-custom" class="tab-content">
        <div class="test-card">
            <div class="test-card-header">
                <h4><i class="fas fa-flask"></i> Custom Test Case</h4>
            </div>
            <div class="test-card-body">
                <form method="POST" class="custom-test-form">
                    <input type="hidden" name="action" value="run_single_case">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h5><i class="fas fa-user"></i> Patient Information</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Test Case Name</label>
                                <input type="text" class="form-control" name="case_name" value="Custom Test Case" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Patient Name</label>
                                    <input type="text" class="form-control" name="patient_name" value="Test Patient">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Age</label>
                                    <input type="number" class="form-control" name="age" value="30">
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Gender</label>
                                    <select class="form-select" name="gender">
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Blood Group</label>
                                    <input type="text" class="form-control" name="blood_group" value="Unknown">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Allergies</label>
                                    <input type="text" class="form-control" name="allergies" value="None">
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h5><i class="fas fa-stethoscope"></i> Consultation Details</h5>
                            
                            <div class="mb-3">
                                <label class="form-label">Chief Complaint *</label>
                                <textarea class="form-control" name="chief_complaint" rows="2" required 
                                    placeholder="Main complaint e.g., Persistent headache for 3 days"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Present Illness</label>
                                <textarea class="form-control" name="present_illness" rows="2" 
                                    placeholder="History of present illness..."></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Thermal</label>
                                    <select class="form-select" name="thermal_state">
                                        <option value="">Select...</option>
                                        <option value="chilly">Chilly</option>
                                        <option value="warm">Warm</option>
                                        <option value="normal">Normal</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Thirst</label>
                                    <select class="form-select" name="thirst">
                                        <option value="">Select...</option>
                                        <option value="increased">Increased</option>
                                        <option value="reduced">Reduced</option>
                                        <option value="normal">Normal</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Appetite</label>
                                    <select class="form-select" name="appetite">
                                        <option value="">Select...</option>
                                        <option value="good">Good</option>
                                        <option value="reduced">Reduced</option>
                                        <option value="variable">Variable</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Mental State</label>
                                <input type="text" class="form-control" name="mental_state" 
                                    placeholder="e.g., Anxious, irritable, restless">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Modalities</label>
                                <input type="text" class="form-control" name="modalities" 
                                    placeholder="e.g., Worse: heat, Better: cold">
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mt-4"><i class="fas fa-list-ul"></i> Symptoms</h5>
                    <div id="symptoms-container">
                        <div class="symptom-row mb-2">
                            <div>
                                <label class="form-label">Symptom</label>
                                <input type="text" class="form-control" name="symptoms[]" placeholder="Symptom description">
                            </div>
                            <div>
                                <label class="form-label">Intensity</label>
                                <select class="form-select" name="symptom_intensities[]">
                                    <option value="mild">Mild</option>
                                    <option value="moderate" selected>Moderate</option>
                                    <option value="severe">Severe</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Location</label>
                                <input type="text" class="form-control" name="symptom_locations[]" placeholder="Body part">
                            </div>
                            <div>
                                <button type="button" class="remove-symptom-btn" onclick="removeSymptom(this)" style="margin-top: 32px;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="add-symptom-btn mt-2" onclick="addSymptom()">
                        <i class="fas fa-plus"></i> Add Symptom
                    </button>
                    
                    <div class="mt-4">
                        <label class="form-label">Expected Remedies (comma separated, for validation)</label>
                        <input type="text" class="form-control" name="expected_remedies" 
                            placeholder="e.g., nux vomica, arsenicum album, belladonna">
                    </div>
                    
                    <div class="form-check mt-3">
                        <input type="checkbox" class="form-check-input" id="cleanup2" name="cleanup" value="1" checked>
                        <label class="form-check-label" for="cleanup2">
                            Clean up test data after completion
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-success btn-lg mt-4">
                        <i class="fas fa-vial"></i> Run Custom Test
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Results Tab -->
    <?php if ($results): ?>
    <div id="tab-results" class="tab-content">
        <!-- Summary Card -->
        <div class="test-card">
            <div class="test-card-header">
                <h4><i class="fas fa-chart-pie"></i> Test Summary</h4>
                <span class="status-badge status-<?= strtolower($results['summary']['pass_rate'] >= 70 ? 'pass' : ($results['summary']['pass_rate'] >= 50 ? 'warn' : 'fail')) ?>">
                    <?= $results['summary']['pass_rate'] ?>% Pass Rate
                </span>
            </div>
            <div class="test-card-body">
                <div class="summary-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?= $results['summary']['total_tests'] ?></div>
                        <div class="stat-label">Total Tests</div>
                    </div>
                    <div class="stat-item" style="color: #27AE60;">
                        <div class="stat-value"><?= $results['summary']['passed'] ?></div>
                        <div class="stat-label">Passed</div>
                    </div>
                    <div class="stat-item" style="color: #E74C3C;">
                        <div class="stat-value"><?= $results['summary']['failed'] ?></div>
                        <div class="stat-label">Failed</div>
                    </div>
                    <div class="stat-item" style="color: #F39C12;">
                        <div class="stat-value"><?= $results['summary']['warnings'] ?></div>
                        <div class="stat-label">Warnings</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $results['summary']['avg_rag_response_time'] ?>s</div>
                        <div class="stat-label">Avg RAG Time</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $results['summary']['avg_gemini_response_time'] ?>s</div>
                        <div class="stat-label">Avg Gemini Time</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $results['summary']['total_execution_time'] ?>s</div>
                        <div class="stat-label">Total Time</div>
                    </div>
                </div>
                
                <div class="export-buttons mt-4">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="export_journal">
                        <input type="hidden" name="format" value="pdf">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-file-pdf"></i> Export PDF
                        </button>
                    </form>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="export_journal">
                        <input type="hidden" name="format" value="xml">
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-file-code"></i> Export XML
                        </button>
                    </form>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="export_journal">
                        <input type="hidden" name="format" value="both">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-file-archive"></i> Export Both
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Detailed Results -->
        <div class="test-card">
            <div class="test-card-header">
                <h4><i class="fas fa-list-alt"></i> Detailed Results</h4>
            </div>
            <div class="test-card-body">
                <?php foreach ($results['results'] as $result): ?>
                <div class="test-result-item">
                    <div class="test-result-header" onclick="toggleResult(this)">
                        <span>
                            <strong>#<?= $result['test_number'] ?> - <?= htmlspecialchars($result['name']) ?></strong>
                            <span class="text-muted ms-2">(<?= $result['execution_time'] ?>s)</span>
                        </span>
                        <span class="status-badge status-<?= strtolower($result['overall_status']) ?>">
                            <?= $result['overall_status'] ?>
                        </span>
                    </div>
                    <div class="test-result-content">
                        <!-- RAG Results -->
                        <div class="ai-section rag">
                            <h5><i class="fas fa-search"></i> RAG Results</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Method:</strong> <?= $result['rag_result']['method'] ?? 'N/A' ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Response Time:</strong> <?= $result['rag_result']['response_time'] ?? 0 ?>s
                                </div>
                                <div class="col-md-4">
                                    <strong>Remedies:</strong> <?= count($result['rag_result']['remedies'] ?? []) ?>
                                </div>
                            </div>
                            <?php if (!empty($result['rag_result']['remedies'])): ?>
                            <div class="remedy-list mt-2">
                                <?php foreach (array_slice($result['rag_result']['remedies'], 0, 8) as $remedy): ?>
                                <span class="remedy-tag"><?= htmlspecialchars($remedy['name'] ?? 'Unknown') ?></span>
                                <?php endforeach; ?>
                                <?php if (count($result['rag_result']['remedies']) > 8): ?>
                                <span class="text-muted">+<?= count($result['rag_result']['remedies']) - 8 ?> more</span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($result['rag_result']['error'])): ?>
                            <div class="alert alert-danger mt-2 mb-0">
                                Error: <?= htmlspecialchars($result['rag_result']['error']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Gemini Results -->
                        <div class="ai-section gemini">
                            <h5><i class="fas fa-brain"></i> Gemini AI Results</h5>
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Model:</strong> <?= $result['gemini_result']['model'] ?? 'N/A' ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Response Time:</strong> <?= $result['gemini_result']['response_time'] ?? 0 ?>s
                                </div>
                                <div class="col-md-4">
                                    <strong>Remedies:</strong> <?= count($result['gemini_result']['remedies'] ?? []) ?>
                                </div>
                            </div>
                            <?php if (!empty($result['gemini_result']['remedies'])): ?>
                            <div class="remedy-list mt-2">
                                <?php foreach (array_slice($result['gemini_result']['remedies'], 0, 8) as $remedy): ?>
                                <span class="remedy-tag"><?= htmlspecialchars($remedy['name'] ?? 'Unknown') ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($result['gemini_result']['case_analysis'])): ?>
                            <div class="mt-2">
                                <strong>Case Analysis:</strong>
                                <p class="mb-0 small"><?= nl2br(htmlspecialchars(substr($result['gemini_result']['case_analysis'], 0, 300))) ?>...</p>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($result['gemini_result']['error'])): ?>
                            <div class="alert alert-danger mt-2 mb-0">
                                Error: <?= htmlspecialchars($result['gemini_result']['error']) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Remarks -->
                        <?php if (!empty($result['remarks'])): ?>
                        <h5 class="mt-3"><i class="fas fa-comment-dots"></i> Remarks</h5>
                        <ul class="remark-list">
                            <?php foreach ($result['remarks'] as $remark): ?>
                            <li class="remark-<?= $remark['type'] ?>">
                                <strong>[<?= strtoupper($remark['source']) ?>]</strong>
                                <?= htmlspecialchars($remark['message']) ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        
                        <!-- Validation -->
                        <?php if (!empty($result['validation'])): ?>
                        <h5 class="mt-3"><i class="fas fa-check-circle"></i> Validation Checks</h5>
                        <table class="validation-table">
                            <tr>
                                <th>Check</th>
                                <th>Status</th>
                                <th>Expected</th>
                                <th>Actual</th>
                            </tr>
                            <?php foreach ($result['validation'] as $checkName => $check): ?>
                            <tr>
                                <td><?= ucwords(str_replace('_', ' ', $checkName)) ?></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($check['status']) ?>" style="padding: 2px 8px;">
                                        <?= $check['status'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($check['expected']) ?></td>
                                <td><?= htmlspecialchars($check['actual']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    
    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
}

function toggleResult(header) {
    const content = header.nextElementSibling;
    content.classList.toggle('expanded');
}

function addSymptom() {
    const container = document.getElementById('symptoms-container');
    const row = document.createElement('div');
    row.className = 'symptom-row mb-2';
    row.innerHTML = `
        <div>
            <input type="text" class="form-control" name="symptoms[]" placeholder="Symptom description">
        </div>
        <div>
            <select class="form-select" name="symptom_intensities[]">
                <option value="mild">Mild</option>
                <option value="moderate" selected>Moderate</option>
                <option value="severe">Severe</option>
            </select>
        </div>
        <div>
            <input type="text" class="form-control" name="symptom_locations[]" placeholder="Body part">
        </div>
        <div>
            <button type="button" class="remove-symptom-btn" onclick="removeSymptom(this)">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    container.appendChild(row);
}

function removeSymptom(btn) {
    const rows = document.querySelectorAll('.symptom-row');
    if (rows.length > 1) {
        btn.closest('.symptom-row').remove();
    }
}

// Auto-expand first result if any
document.addEventListener('DOMContentLoaded', function() {
    const firstResult = document.querySelector('.test-result-content');
    if (firstResult) {
        firstResult.classList.add('expanded');
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
