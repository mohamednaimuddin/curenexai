<?php
// Enable error logging for this page
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

try {
    require_once __DIR__ . '/../includes/init.php';
    requireLogin();
} catch (Exception $e) {
    error_log("Init Error in patients/add.php: " . $e->getMessage());
    die("An error occurred during initialization. Please contact support.");
} catch (Error $e) {
    error_log("Fatal Error in patients/add.php: " . $e->getMessage());
    die("A critical error occurred. Please contact support.");
}

$doctorId = getLoggedInDoctorId();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check maintenance mode first
    if (blockIfMaintenance()) {
        header('Location: ' . APP_URL . '/patients/list.php');
        exit;
    }
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        setFlash('error', 'Invalid request. Please try again.');
    } else {
        // Sanitize and validate input
        $patientName = sanitize($_POST['patient_name'] ?? '');
        $age = intval($_POST['age'] ?? 0);
        $gender = sanitize($_POST['gender'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $occupation = sanitize($_POST['occupation'] ?? '');
        $maritalStatus = sanitize($_POST['marital_status'] ?? '');
        $bloodGroup = sanitize($_POST['blood_group'] ?? '');
        $emergencyContact = sanitize($_POST['emergency_contact'] ?? '');
        $emergencyPhone = sanitize($_POST['emergency_phone'] ?? '');
        
        // Medical History
        $medicalHistory = sanitize($_POST['medical_history'] ?? '');
        $surgicalHistory = sanitize($_POST['surgical_history'] ?? '');
        $familyHistory = sanitize($_POST['family_history'] ?? '');
        $allergies = sanitize($_POST['allergies'] ?? '');
        $currentMedications = sanitize($_POST['current_medications'] ?? '');
        
        // Lifestyle
        $diet = sanitize($_POST['diet'] ?? '');
        $exercise = sanitize($_POST['exercise'] ?? '');
        $sleepPattern = sanitize($_POST['sleep_pattern'] ?? '');
        $addictions = sanitize($_POST['addictions'] ?? '');
        
        $errors = [];
        
        // Validation
        if (empty($patientName)) {
            $errors[] = 'Patient name is required';
        }
        
        if ($age <= 0 || $age > 150) {
            $errors[] = 'Please enter a valid age';
        }
        
        if (empty($gender) || !in_array($gender, ['male', 'female', 'other'])) {
            $errors[] = 'Please select a valid gender';
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address';
        }
        
        if (empty($phone)) {
            $errors[] = 'Phone number is required';
        } elseif (!preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number';
        }
        
        // Check if phone or email already exists
        if (!empty($phone)) {
            $existingPhone = DB::queryOne(
                "SELECT id FROM patients WHERE phone = ? AND doctor_id = ?",
                [$phone, $doctorId]
            );
            if ($existingPhone) {
                $errors[] = 'A patient with this phone number already exists';
            }
        }
        
        if (!empty($email)) {
            $existingEmail = DB::queryOne(
                "SELECT id FROM patients WHERE email = ? AND doctor_id = ?",
                [$email, $doctorId]
            );
            if ($existingEmail) {
                $errors[] = 'A patient with this email address already exists';
            }
        }
        
        if (empty($errors)) {
            try {
                // Insert patient
                $patientData = [
                    'doctor_id' => $doctorId,
                    'patient_name' => $patientName,
                    'age' => $age,
                    'gender' => $gender,
                    'email' => $email ?: null,
                    'phone' => $phone,
                    'address' => $address ?: null,
                    'occupation' => $occupation ?: null,
                    'marital_status' => $maritalStatus ?: null,
                    'blood_group' => $bloodGroup ?: null,
                    'emergency_contact' => $emergencyContact ?: null,
                    'emergency_phone' => $emergencyPhone ?: null,
                    'medical_history' => $medicalHistory ?: null,
                    'surgical_history' => $surgicalHistory ?: null,
                    'family_history' => $familyHistory ?: null,
                    'allergies' => $allergies ?: null,
                    'current_medications' => $currentMedications ?: null,
                    'diet' => $diet ?: null,
                    'exercise' => $exercise ?: null,
                    'sleep_pattern' => $sleepPattern ?: null,
                    'addictions' => $addictions ?: null
                ];
                
                // Enable PDO error mode to get detailed errors
                $patientId = DB::insert('patients', $patientData);
                
                if ($patientId) {
                    // Log activity
                    logActivity('patient_created', "Created new patient: {$patientName} (ID: {$patientId})", $doctorId);
                    
                    setFlash('success', "Patient '{$patientName}' has been added successfully!");
                    
                    // Check if user wants to create consultation immediately
                    if (isset($_POST['create_consultation']) && $_POST['create_consultation'] == '1') {
                        redirect('/consultations/add.php?patient_id=' . $patientId);
                    } else {
                        redirect('/patients/view.php?id=' . $patientId);
                    }
                } else {
                    // Security: Log detailed error but show generic message to user
                    $lastError = error_get_last();
                    if ($lastError) {
                        error_log("Patient Add Failed: " . $lastError['message']);
                    }
                    $errors[] = 'Failed to add patient. Please check all fields and try again.';
                    $errors[] = 'If the problem persists, please contact support.';
                }
            } catch (Exception $e) {
                // Security: Log full error but show generic message to user
                error_log("Patient Add Error: " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
                $errors[] = 'An error occurred while adding the patient. Please try again.';
            }
        }
        
        // Store errors in flash message
        if (!empty($errors)) {
            setFlash('error', implode('<br>', $errors));
        }
    }
}

$pageTitle = 'Add New Patient';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="patient-form-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <a href="<?php echo APP_URL; ?>/patients/list.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Patients
            </a>
            <h1><i class="fas fa-user-plus"></i> Add New Patient</h1>
            <p class="text-muted">Enter patient details and medical history</p>
        </div>
    </div>
    
    <!-- Patient Form -->
    <form method="POST" action="" class="patient-form" id="patientForm">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <!-- Basic Information -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Basic Information</h3>
                <span class="required-note">* Required fields</span>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="patient_name">Patient Name <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="patient_name" 
                            name="patient_name" 
                            class="form-control" 
                            placeholder="Enter full name"
                            value="<?php echo htmlspecialchars($_POST['patient_name'] ?? ''); ?>"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="age">Age <span class="required">*</span></label>
                        <input 
                            type="number" 
                            id="age" 
                            name="age" 
                            class="form-control" 
                            placeholder="Enter age"
                            min="0" 
                            max="150"
                            value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender <span class="required">*</span></label>
                        <select id="gender" name="gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            class="form-control" 
                            placeholder="+91 98765 43210"
                            value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-control" 
                            placeholder="patient@example.com"
                            value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="blood_group">Blood Group</label>
                        <select id="blood_group" name="blood_group" class="form-control">
                            <option value="">Select Blood Group</option>
                            <?php 
                            $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
                            foreach ($bloodGroups as $group): 
                            ?>
                                <option value="<?php echo $group; ?>" <?php echo (isset($_POST['blood_group']) && $_POST['blood_group'] == $group) ? 'selected' : ''; ?>>
                                    <?php echo $group; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="occupation">Occupation</label>
                        <input 
                            type="text" 
                            id="occupation" 
                            name="occupation" 
                            class="form-control" 
                            placeholder="e.g., Teacher, Engineer"
                            value="<?php echo htmlspecialchars($_POST['occupation'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="marital_status">Marital Status</label>
                        <select id="marital_status" name="marital_status" class="form-control">
                            <option value="">Select Status</option>
                            <option value="single" <?php echo (isset($_POST['marital_status']) && $_POST['marital_status'] == 'single') ? 'selected' : ''; ?>>Single</option>
                            <option value="married" <?php echo (isset($_POST['marital_status']) && $_POST['marital_status'] == 'married') ? 'selected' : ''; ?>>Married</option>
                            <option value="divorced" <?php echo (isset($_POST['marital_status']) && $_POST['marital_status'] == 'divorced') ? 'selected' : ''; ?>>Divorced</option>
                            <option value="widowed" <?php echo (isset($_POST['marital_status']) && $_POST['marital_status'] == 'widowed') ? 'selected' : ''; ?>>Widowed</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="address">Address</label>
                        <textarea 
                            id="address" 
                            name="address" 
                            class="form-control" 
                            rows="2" 
                            placeholder="Enter full address"
                        ><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Emergency Contact -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-phone-alt"></i> Emergency Contact</h3>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="emergency_contact">Emergency Contact Name</label>
                        <input 
                            type="text" 
                            id="emergency_contact" 
                            name="emergency_contact" 
                            class="form-control" 
                            placeholder="Contact person name"
                            value="<?php echo htmlspecialchars($_POST['emergency_contact'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="emergency_phone">Emergency Phone Number</label>
                        <input 
                            type="tel" 
                            id="emergency_phone" 
                            name="emergency_phone" 
                            class="form-control" 
                            placeholder="Emergency contact number"
                            value="<?php echo htmlspecialchars($_POST['emergency_phone'] ?? ''); ?>"
                        >
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Medical History -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-notes-medical"></i> Medical History</h3>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="medical_history">Past Medical History</label>
                        <textarea 
                            id="medical_history" 
                            name="medical_history" 
                            class="form-control" 
                            rows="3" 
                            placeholder="Previous illnesses, chronic conditions, etc."
                        ><?php echo htmlspecialchars($_POST['medical_history'] ?? ''); ?></textarea>
                        <small class="form-text">Include any chronic diseases, previous illnesses, hospitalizations</small>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="surgical_history">Surgical History</label>
                        <textarea 
                            id="surgical_history" 
                            name="surgical_history" 
                            class="form-control" 
                            rows="3" 
                            placeholder="Previous surgeries and dates"
                        ><?php echo htmlspecialchars($_POST['surgical_history'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="family_history">Family History</label>
                        <textarea 
                            id="family_history" 
                            name="family_history" 
                            class="form-control" 
                            rows="3" 
                            placeholder="Hereditary conditions, family diseases"
                        ><?php echo htmlspecialchars($_POST['family_history'] ?? ''); ?></textarea>
                        <small class="form-text">Include family history of diabetes, hypertension, cancer, tuberculosis, etc.</small>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="allergies">Allergies</label>
                        <textarea 
                            id="allergies" 
                            name="allergies" 
                            class="form-control" 
                            rows="2" 
                            placeholder="Drug allergies, food allergies, environmental allergies"
                        ><?php echo htmlspecialchars($_POST['allergies'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="current_medications">Current Medications</label>
                        <textarea 
                            id="current_medications" 
                            name="current_medications" 
                            class="form-control" 
                            rows="3" 
                            placeholder="List all current medications with dosage"
                        ><?php echo htmlspecialchars($_POST['current_medications'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Lifestyle Information -->
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-heartbeat"></i> Lifestyle & Habits</h3>
            </div>
            <div class="card-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="diet">Diet Type</label>
                        <select id="diet" name="diet" class="form-control">
                            <option value="">Select Diet</option>
                            <option value="vegetarian" <?php echo (isset($_POST['diet']) && $_POST['diet'] == 'vegetarian') ? 'selected' : ''; ?>>Vegetarian</option>
                            <option value="non-vegetarian" <?php echo (isset($_POST['diet']) && $_POST['diet'] == 'non-vegetarian') ? 'selected' : ''; ?>>Non-Vegetarian</option>
                            <option value="vegan" <?php echo (isset($_POST['diet']) && $_POST['diet'] == 'vegan') ? 'selected' : ''; ?>>Vegan</option>
                            <option value="mixed" <?php echo (isset($_POST['diet']) && $_POST['diet'] == 'mixed') ? 'selected' : ''; ?>>Mixed</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="exercise">Exercise Routine</label>
                        <select id="exercise" name="exercise" class="form-control">
                            <option value="">Select Exercise Level</option>
                            <option value="none" <?php echo (isset($_POST['exercise']) && $_POST['exercise'] == 'none') ? 'selected' : ''; ?>>None/Sedentary</option>
                            <option value="light" <?php echo (isset($_POST['exercise']) && $_POST['exercise'] == 'light') ? 'selected' : ''; ?>>Light (1-2 days/week)</option>
                            <option value="moderate" <?php echo (isset($_POST['exercise']) && $_POST['exercise'] == 'moderate') ? 'selected' : ''; ?>>Moderate (3-4 days/week)</option>
                            <option value="active" <?php echo (isset($_POST['exercise']) && $_POST['exercise'] == 'active') ? 'selected' : ''; ?>>Active (5-7 days/week)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="sleep_pattern">Sleep Pattern</label>
                        <select id="sleep_pattern" name="sleep_pattern" class="form-control">
                            <option value="">Select Sleep Pattern</option>
                            <option value="poor" <?php echo (isset($_POST['sleep_pattern']) && $_POST['sleep_pattern'] == 'poor') ? 'selected' : ''; ?>>Poor (< 5 hours)</option>
                            <option value="average" <?php echo (isset($_POST['sleep_pattern']) && $_POST['sleep_pattern'] == 'average') ? 'selected' : ''; ?>>Average (5-6 hours)</option>
                            <option value="good" <?php echo (isset($_POST['sleep_pattern']) && $_POST['sleep_pattern'] == 'good') ? 'selected' : ''; ?>>Good (7-8 hours)</option>
                            <option value="excellent" <?php echo (isset($_POST['sleep_pattern']) && $_POST['sleep_pattern'] == 'excellent') ? 'selected' : ''; ?>>Excellent (8+ hours)</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label for="addictions">Addictions / Habits</label>
                        <textarea 
                            id="addictions" 
                            name="addictions" 
                            class="form-control" 
                            rows="2" 
                            placeholder="Smoking, alcohol, tobacco, caffeine consumption, etc."
                        ><?php echo htmlspecialchars($_POST['addictions'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="form-actions">
            <div class="checkbox-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="create_consultation" value="1">
                    <span>Create consultation immediately after adding patient</span>
                </label>
            </div>
            
            <div class="button-group">
                <a href="<?php echo APP_URL; ?>/patients/list.php" class="btn btn-outline">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Patient
                </button>
            </div>
        </div>
    </form>
</div>

<style>
.patient-form-container {
    max-width: 1000px;
    margin: 0 auto;
}

.patient-form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group.full-width {
    grid-column: 1 / -1;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--gray-700);
    font-weight: 500;
    font-size: 0.95rem;
}

.form-group .required {
    color: var(--danger-color);
}

.form-control {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid var(--gray-300);
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: var(--white);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(138, 43, 226, 0.1);
}

.form-control::placeholder {
    color: var(--gray-400);
}

.form-text {
    display: block;
    margin-top: 5px;
    color: var(--gray-500);
    font-size: 0.85rem;
}

.required-note {
    color: var(--gray-600);
    font-size: 0.875rem;
    font-weight: normal;
}

.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--gray-50);
    border-radius: 12px;
    margin-top: 10px;
}

.checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 0.95rem;
    color: var(--gray-700);
}

.checkbox-label input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--primary-color);
}

.button-group {
    display: flex;
    gap: 10px;
}

/* Responsive */
@media (max-width: 768px) {
    .form-grid {
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
    
    .button-group .btn {
        width: 100%;
    }
}

/* Form validation styles */
.form-control:invalid:not(:placeholder-shown) {
    border-color: var(--danger-color);
}

.form-control:valid:not(:placeholder-shown) {
    border-color: var(--success-color);
}

/* Loading state */
.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('patientForm');
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Form validation
    form.addEventListener('submit', function(e) {
        const patientName = document.getElementById('patient_name').value.trim();
        const age = parseInt(document.getElementById('age').value);
        const gender = document.getElementById('gender').value;
        const phone = document.getElementById('phone').value.trim();
        
        let errors = [];
        
        if (!patientName) {
            errors.push('Patient name is required');
        }
        
        if (!age || age <= 0 || age > 150) {
            errors.push('Please enter a valid age (1-150)');
        }
        
        if (!gender) {
            errors.push('Please select a gender');
        }
        
        if (!phone) {
            errors.push('Phone number is required');
        }
        
        if (errors.length > 0) {
            e.preventDefault();
            alert('Please fix the following errors:\n\n' + errors.join('\n'));
            return false;
        }
        
        // Disable submit button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding Patient...';
    });
    
    // Phone number formatting (basic)
    const phoneInput = document.getElementById('phone');
    phoneInput.addEventListener('input', function(e) {
        let value = e.target.value.replace(/[^\d+\-\s()]/g, '');
        e.target.value = value;
    });
    
    // Auto-capitalize patient name
    const nameInput = document.getElementById('patient_name');
    nameInput.addEventListener('blur', function(e) {
        e.target.value = e.target.value.split(' ')
            .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
            .join(' ');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
