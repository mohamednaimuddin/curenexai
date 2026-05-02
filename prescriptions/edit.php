<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();
$prescriptionId = $_GET['id'] ?? 0;

// Fetch prescription - SECURED: Only fetch if doctor_id matches
$sql = "SELECT * FROM prescriptions WHERE id = ? AND doctor_id = ?";
$prescription = DB::queryOne($sql, [$prescriptionId, $doctorId]);

if (!$prescription) {
    setFlash('error', 'Prescription not found or access denied.');
    redirect('/prescriptions/list.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check maintenance mode first
    if (blockIfMaintenance()) {
        header('Location: ' . APP_URL . '/prescriptions/list.php');
        exit;
    }
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        setFlash('error', 'Invalid request. Please try again.');
    } else {
        $dietAdvice = sanitize($_POST['diet_advice'] ?? '');
        $lifestyleAdvice = sanitize($_POST['lifestyle_advice'] ?? '');
        $followUpInstructions = sanitize($_POST['follow_up_instructions'] ?? '');
        $generalInstructions = sanitize($_POST['general_instructions'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        
        $updateData = [
            'diet_advice' => $dietAdvice,
            'lifestyle_advice' => $lifestyleAdvice,
            'follow_up_instructions' => $followUpInstructions,
            'general_instructions' => $generalInstructions,
            'notes' => $notes
        ];
        
        $updated = DB::update('prescriptions', $updateData, 'id = ? AND doctor_id = ?', [$prescriptionId, $doctorId]);
        
        if ($updated !== false) {
            logActivity('prescription_updated', "Updated prescription ID: {$prescriptionId}", $doctorId);
            setFlash('success', 'Prescription updated successfully!');
            redirect('/prescriptions/view.php?id=' . $prescriptionId);
        } else {
            setFlash('error', 'Failed to update prescription. Please try again.');
        }
    }
}

$pageTitle = 'Edit Prescription';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="dashboard-card prescription-edit-container">
    <h1>Edit Prescription</h1>
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <div class="form-group">
            <label for="diet_advice">Diet Advice</label>
            <input type="text" name="diet_advice" id="diet_advice" class="form-control" value="<?php echo htmlspecialchars($prescription['diet_advice']); ?>">
        </div>
        <div class="form-group">
            <label for="lifestyle_advice">Lifestyle Advice</label>
            <input type="text" name="lifestyle_advice" id="lifestyle_advice" class="form-control" value="<?php echo htmlspecialchars($prescription['lifestyle_advice']); ?>">
        </div>
        <div class="form-group">
            <label for="follow_up_instructions">Follow-up Instructions</label>
            <input type="text" name="follow_up_instructions" id="follow_up_instructions" class="form-control" value="<?php echo htmlspecialchars($prescription['follow_up_instructions']); ?>">
        </div>
        <div class="form-group">
            <label for="general_instructions">General Instructions</label>
            <input type="text" name="general_instructions" id="general_instructions" class="form-control" value="<?php echo htmlspecialchars($prescription['general_instructions']); ?>">
        </div>
        <div class="form-group">
            <label for="notes">Private Notes</label>
            <textarea name="notes" id="notes" class="form-control"><?php echo htmlspecialchars($prescription['notes']); ?></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            <a href="<?php echo APP_URL; ?>/prescriptions/view.php?id=<?php echo $prescriptionId; ?>" class="btn btn-outline">Cancel</a>
        </div>
    </form>
</div>

<style>
.prescription-edit-container {
    max-width: 700px;
    margin: 40px auto;
    padding: 30px;
    border-radius: 12px;
    background: #fff;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.prescription-edit-container h1 {
    font-size: 2rem;
    margin-bottom: 25px;
    color: var(--primary-color, #764ba2);
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    font-weight: 500;
    color: var(--gray-700, #333);
    margin-bottom: 8px;
    display: block;
}
.form-control {
    width: 100%;
    padding: 10px 15px;
    border: 2px solid var(--gray-300, #e0e0e0);
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
}
.form-control:focus {
    outline: none;
    border-color: var(--primary-color, #764ba2);
    box-shadow: 0 0 0 3px rgba(138, 43, 226, 0.1);
}
.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 20px;
}
@media (max-width: 768px) {
    .prescription-edit-container {
        padding: 10px;
        max-width: 100%;
    }
    .form-actions {
        flex-direction: column;
        gap: 10px;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
