<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();
$prescriptionId = $_GET['id'] ?? 0;

// Fetch prescription with patient and doctor info - SECURED: Only fetch if doctor_id matches

$sql = "SELECT p.*, 
           pat.patient_name, pat.age, pat.gender, pat.phone, pat.email, pat.address,
           pat.weight, pat.height, pat.blood_group,
           d.full_name AS doctor_name, d.qualification, d.registration_number, d.phone as doctor_phone, d.email as doctor_email,
           c.chief_complaint, c.diagnosis
    FROM prescriptions p
    INNER JOIN patients pat ON p.patient_id = pat.id
    INNER JOIN doctors d ON p.doctor_id = d.id
    INNER JOIN consultations c ON p.consultation_id = c.id
    WHERE p.id = ? AND p.doctor_id = ?";

$prescription = DB::queryOne($sql, [$prescriptionId, $doctorId]);

if (!$prescription) {
    header('Location: ' . APP_URL . '/prescriptions/not_found.php');
    exit;
}

// Fetch prescribed remedies
$remedies = DB::query(
    "SELECT pr.*, r.remedy_name, r.common_name, r.family
     FROM prescription_remedies pr
     INNER JOIN remedies r ON pr.remedy_id = r.id
     WHERE pr.prescription_id = ?
     ORDER BY pr.id",
    [$prescriptionId]
);

$pageTitle = 'View Prescription';
?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="prescription-view-container">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <a href="<?php echo APP_URL; ?>/consultations/view.php?id=<?php echo $prescription['consultation_id']; ?>" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Consultation
            </a>
            <h1><i class="fas fa-prescription"></i> Prescription Details</h1>
            <p class="text-muted">Prescription ID: #<?php echo str_pad($prescription['id'], 6, '0', STR_PAD_LEFT); ?></p>
        </div>
        <div class="header-actions">
            <button onclick="window.print()" class="btn btn-success">
                <i class="fas fa-print"></i> Print
            </button>
            <a href="<?php echo APP_URL; ?>/prescriptions/edit.php?id=<?php echo $prescription['id']; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>
    </div>
    
    <!-- Prescription Card -->
    <div class="prescription-card" id="printable-prescription">
        <!-- Header -->
        <div class="prescription-header" style="display: flex; justify-content: space-between; align-items: flex-start; width: 100%;">
            <div class="doctor-info" style="flex: 1; text-align: left;">
                <h2 style="color: #2563eb; margin: 0 0 5px 0;"><?php echo htmlspecialchars($prescription['doctor_name'] ?? ''); ?></h2>
                <p style="margin: 3px 0; color: #475569;"><?php echo htmlspecialchars($prescription['qualification'] ?? ''); ?></p>
                <p style="margin: 3px 0; color: #475569;">Reg. No: <?php echo htmlspecialchars($prescription['registration_number'] ?? ''); ?></p>
                <?php if (!empty($prescription['doctor_phone'])): ?>
                <p style="margin: 3px 0; color: #475569;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($prescription['doctor_phone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($prescription['doctor_email'])): ?>
                <p style="margin: 3px 0; color: #475569;"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($prescription['doctor_email']); ?></p>
                <?php endif; ?>
            </div>
            <div class="prescription-logo" style="flex: 0 0 auto; text-align: right; margin-left: auto;">
                <img src="<?php echo APP_URL; ?>/assets/image/CURENEXAI ICON.png" alt="CurenexAI" style="width: 60px; height: 60px; display: block; margin-left: auto;">
                <h3 style="color: #2563eb; margin: 10px 0 0 0; text-align: right;">Curenex AI Prescription</h3>
            </div>
        </div>
        
        <hr style="border: 2px solid #2563eb; margin: 20px 0;">
        
        <!-- Patient Info -->
        <div class="patient-section" style="margin: 15px 0;">
            <div class="section-title" style="display: flex; align-items: center; gap: 10px; background: #f1f5f9; padding: 8px 15px; border-left: 4px solid #2563eb; margin-bottom: 10px;">
                <i class="fas fa-user-injured" style="color: #2563eb;"></i>
                <h3 style="margin: 0; color: #1e293b; font-size: 14px;">Patient Information</h3>
            </div>
            <div class="info-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px 15px;">
                <div class="info-item" style="display: flex; flex-direction: row; align-items: baseline; gap: 5px;">
                    <label style="font-weight: 600; color: #475569; font-size: 12px;">Name:</label>
                    <span style="color: #1e293b; font-size: 12px;"><?php echo htmlspecialchars($prescription['patient_name']); ?></span>
                </div>
                <div class="info-item" style="display: flex; flex-direction: row; align-items: baseline; gap: 5px;">
                    <label style="font-weight: 600; color: #475569; font-size: 12px;">Age/Gender:</label>
                    <span style="color: #1e293b; font-size: 12px;"><?php echo $prescription['age']; ?>yrs/<?php echo ucfirst($prescription['gender']); ?></span>
                </div>
                <div class="info-item" style="display: flex; flex-direction: row; align-items: baseline; gap: 5px;">
                    <label style="font-weight: 600; color: #475569; font-size: 12px;">Phone:</label>
                    <span style="color: #1e293b; font-size: 12px;"><?php echo htmlspecialchars($prescription['phone'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-item" style="display: flex; flex-direction: row; align-items: baseline; gap: 5px;">
                    <label style="font-weight: 600; color: #475569; font-size: 12px;">Date:</label>
                    <span style="color: #1e293b; font-size: 12px;"><?php echo formatDate($prescription['prescription_date'], 'd M Y'); ?></span>
                </div>
                <?php if (!empty($prescription['blood_group'])): ?>
                <div class="info-item" style="display: flex; flex-direction: row; align-items: baseline; gap: 5px;">
                    <label style="font-weight: 600; color: #475569; font-size: 12px;">Blood Group:</label>
                    <span style="color: #1e293b; font-size: 12px;"><?php echo htmlspecialchars($prescription['blood_group']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($prescription['weight'])): ?>
                <div class="info-item" style="display: flex; flex-direction: row; align-items: baseline; gap: 5px;">
                    <label style="font-weight: 600; color: #475569; font-size: 12px;">Weight:</label>
                    <span style="color: #1e293b; font-size: 12px;"><?php echo $prescription['weight']; ?> kg</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Diagnosis -->
        <div class="diagnosis-section" style="margin: 10px 0;">
            <div class="section-title" style="display: flex; align-items: center; gap: 8px; background: #f1f5f9; padding: 6px 12px; border-left: 4px solid #2563eb; margin-bottom: 8px;">
                <i class="fas fa-stethoscope" style="color: #2563eb;"></i>
                <h3 style="margin: 0; color: #1e293b; font-size: 13px;">Clinical Information</h3>
            </div>
            <div class="diagnosis-content" style="padding: 0 10px; font-size: 12px;">
                <strong style="color: #475569; font-size: 12px;">Chief Complaint:</strong>
                <span style="color: #1e293b; margin-left: 5px;"><?php echo htmlspecialchars($prescription['chief_complaint']); ?></span>
                
                <?php if (!empty($prescription['diagnosis'])): ?>
                <br><strong style="color: #475569; font-size: 12px;">Diagnosis:</strong>
                <span style="color: #1e293b; margin-left: 5px;"><?php echo htmlspecialchars($prescription['diagnosis']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Rx Symbol -->
        <div class="rx-symbol" style="text-align: center; margin: 15px 0;">
            <span style="font-size: 42px; color: #2563eb; font-weight: bold;">℞</span>
        </div>
        
        <!-- Remedies -->
        <div class="remedies-section" style="margin: 10px 0;">
            <div class="section-title" style="display: flex; align-items: center; gap: 8px; background: #f1f5f9; padding: 6px 12px; border-left: 4px solid #2563eb; margin-bottom: 8px;">
                <i class="fas fa-capsules" style="color: #2563eb;"></i>
                <h3 style="margin: 0; color: #1e293b; font-size: 13px;">Prescribed Remedies</h3>
            </div>
            <?php if (!empty($remedies)): ?>
            <table class="remedies-table" style="width: 100%; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr>
                        <th style="background: #f1f5f9; padding: 6px 8px; text-align: left; border: 1px solid #e2e8f0; color: #475569; font-weight: 600; font-size: 11px;">#</th>
                        <th style="background: #f1f5f9; padding: 6px 8px; text-align: left; border: 1px solid #e2e8f0; color: #475569; font-weight: 600; font-size: 11px;">Remedy</th>
                        <th style="background: #f1f5f9; padding: 6px 8px; text-align: left; border: 1px solid #e2e8f0; color: #475569; font-weight: 600; font-size: 11px;">Potency</th>
                        <th style="background: #f1f5f9; padding: 6px 8px; text-align: left; border: 1px solid #e2e8f0; color: #475569; font-weight: 600; font-size: 11px;">Dosage</th>
                        <th style="background: #f1f5f9; padding: 6px 8px; text-align: left; border: 1px solid #e2e8f0; color: #475569; font-weight: 600; font-size: 11px;">Duration</th>
                        <th style="background: #f1f5f9; padding: 6px 8px; text-align: left; border: 1px solid #e2e8f0; color: #475569; font-weight: 600; font-size: 11px;">Instructions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($remedies as $index => $remedy): ?>
                    <tr>
                        <td style="padding: 6px 8px; border: 1px solid #e2e8f0; color: #1e293b;"><?php echo $index + 1; ?></td>
                        <td style="padding: 6px 8px; border: 1px solid #e2e8f0; color: #1e293b;">
                            <strong><?php echo htmlspecialchars($remedy['remedy_name']); ?></strong>
                            <?php if (!empty($remedy['common_name'])): ?>
                            <br><small style="color: #64748b; font-size: 10px;">(<?php echo htmlspecialchars($remedy['common_name']); ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 6px 8px; border: 1px solid #e2e8f0; color: #1e293b;"><?php echo htmlspecialchars($remedy['potency']); ?></td>
                        <td style="padding: 6px 8px; border: 1px solid #e2e8f0; color: #1e293b;"><?php echo htmlspecialchars($remedy['dosage']); ?></td>
                        <td style="padding: 6px 8px; border: 1px solid #e2e8f0; color: #1e293b;"><?php echo htmlspecialchars($remedy['duration']); ?></td>
                        <td style="padding: 6px 8px; border: 1px solid #e2e8f0; color: #1e293b;"><?php echo htmlspecialchars($remedy['instructions']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p style="text-align: center; padding: 20px; color: #64748b;">No remedies prescribed yet.</p>
            <?php endif; ?>
        </div>
        
        <!-- Advice & Instructions -->
        <?php if (!empty($prescription['diet_advice']) || !empty($prescription['lifestyle_advice']) || 
                   !empty($prescription['follow_up_instructions']) || !empty($prescription['general_instructions'])): ?>
        <div class="advice-section" style="margin: 15px 0;">
            <div class="section-title" style="display: flex; align-items: center; gap: 10px; background: #f1f5f9; padding: 8px 15px; border-left: 4px solid #2563eb; margin-bottom: 10px;">
                <i class="fas fa-info-circle" style="color: #2563eb;"></i>
                <h3 style="margin: 0; color: #1e293b; font-size: 14px;">Advice & Instructions</h3>
            </div>
            
            <div class="advice-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px 20px;">
                <?php if (!empty($prescription['diet_advice'])): ?>
                <div class="advice-item" style="display: flex; flex-direction: row; align-items: baseline; gap: 5px;">
                    <strong style="color: #475569; font-size: 12px; white-space: nowrap;"><i class="fas fa-utensils" style="color: #2563eb; margin-right: 4px;"></i>Diet:</strong>
                    <span style="color: #1e293b; font-size: 12px;"><?php echo htmlspecialchars($prescription['diet_advice']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($prescription['lifestyle_advice'])): ?>
                <div class="advice-item" style="display: flex; flex-direction: row; align-items: baseline; gap: 5px;">
                    <strong style="color: #475569; font-size: 12px; white-space: nowrap;"><i class="fas fa-walking" style="color: #2563eb; margin-right: 4px;"></i>Lifestyle:</strong>
                    <span style="color: #1e293b; font-size: 12px;"><?php echo htmlspecialchars($prescription['lifestyle_advice']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($prescription['follow_up_instructions'])): ?>
                <div class="advice-item" style="display: flex; flex-direction: row; align-items: baseline; gap: 5px;">
                    <strong style="color: #475569; font-size: 12px; white-space: nowrap;"><i class="fas fa-calendar-check" style="color: #2563eb; margin-right: 4px;"></i>Follow-up:</strong>
                    <span style="color: #1e293b; font-size: 12px;"><?php echo htmlspecialchars($prescription['follow_up_instructions']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($prescription['general_instructions'])): ?>
                <div class="advice-item" style="display: flex; flex-direction: row; align-items: baseline; gap: 5px;">
                    <strong style="color: #475569; font-size: 12px; white-space: nowrap;"><i class="fas fa-clipboard-list" style="color: #2563eb; margin-right: 4px;"></i>General:</strong>
                    <span style="color: #1e293b; font-size: 12px;"><?php echo htmlspecialchars($prescription['general_instructions']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Signature -->
        <div class="signature-section" style="margin-top: 60px; display: flex; justify-content: flex-end;">
            <div class="signature-box" style="text-align: center; min-width: 200px;">
                <div class="signature-line" style="border-top: 2px solid #1e293b; margin-bottom: 10px;"></div>
                <p style="margin: 5px 0; color: #1e293b;"><strong>Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?></strong></p>
                <p style="margin: 3px 0; color: #475569; font-size: 14px;"><?php echo htmlspecialchars($prescription['qualification']); ?></p>
                <p style="margin: 3px 0; color: #475569; font-size: 14px;">Reg. No: <?php echo htmlspecialchars($prescription['registration_number']); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Private Notes (Not printed) -->
    <?php if (!empty($prescription['notes'])): ?>
    <div class="dashboard-card no-print">
        <div class="card-header">
            <h3><i class="fas fa-lock"></i> Private Notes</h3>
        </div>
        <div class="card-body">
            <p><?php echo nl2br(htmlspecialchars($prescription['notes'])); ?></p>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.prescription-view-container {
    max-width: 1000px;
    margin: 0 auto;
}

.prescription-card {
    background: white;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin: 20px 0;
}

.prescription-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
}

.doctor-info h2 {
    color: var(--primary-color);
    margin: 0 0 5px 0;
}

.doctor-info p {
    margin: 3px 0;
    color: var(--gray-700);
}

.prescription-logo {
    text-align: right;
}

.prescription-logo img {
    width: 60px;
    height: 60px;
    display: block;
    margin-left: auto;
}

.prescription-logo h3 {
    color: var(--primary-color);
    margin: 10px 0 0 0;
}

.patient-section,
.diagnosis-section,
.remedies-section,
.advice-section {
    margin: 30px 0;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--gray-300);
}

.section-title i {
    color: var(--primary-color);
    font-size: 1.2rem;
}

.section-title h3 {
    margin: 0;
    color: var(--gray-800);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.info-item {
    display: flex;
    gap: 8px;
}

.info-item label {
    font-weight: 600;
    color: var(--gray-600);
    min-width: 80px;
}

.info-item span {
    color: var(--gray-800);
}

.diagnosis-content strong {
    display: block;
    color: var(--gray-700);
    margin-top: 10px;
    margin-bottom: 5px;
}

.diagnosis-content p {
    margin: 0;
    color: var(--gray-800);
    line-height: 1.6;
}

.rx-symbol {
    text-align: center;
    margin: 30px 0;
}

.rx-symbol span {
    font-size: 72px;
    color: var(--primary-color);
    font-weight: bold;
}

.remedies-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.remedies-table th,
.remedies-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid var(--gray-300);
}

.remedies-table th {
    background: var(--gray-100);
    font-weight: 600;
    color: var(--gray-700);
}

.remedies-table tr:hover {
    background: var(--gray-50);
}

.advice-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px 20px;
}

.advice-item {
    display: flex;
    flex-direction: row;
    align-items: baseline;
    gap: 5px;
}

.advice-item strong {
    display: inline;
    color: var(--gray-700);
    font-size: 12px;
    white-space: nowrap;
}

.advice-item strong i {
    color: var(--primary-color);
    margin-right: 4px;
}

.advice-item span {
    color: var(--gray-800);
    font-size: 12px;
}

.signature-section {
    margin-top: 60px;
    display: flex;
    justify-content: flex-end;
}

.signature-box {
    text-align: center;
    min-width: 250px;
}

.signature-line {
    border-top: 2px solid var(--gray-800);
    margin-bottom: 10px;
}

.signature-box p {
    margin: 5px 0;
    color: var(--gray-700);
}

/* Font Awesome print support */
@media print {
    @font-face {
        font-family: 'Font Awesome 6 Free';
        font-display: block;
    }
    @font-face {
        font-family: 'Font Awesome 6 Solid';
        font-display: block;
    }
}

/* Print Styles - Match the view.php screen layout */
@media print {
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    
    html, body {
        background: white !important;
        padding: 0 !important;
        margin: 0 !important;
        height: auto !important;
        overflow: visible !important;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
    }
    
    /* Hide navigation elements */
    .no-print,
    .page-header,
    .header-actions,
    nav,
    footer,
    .btn,
    .sidebar,
    .navbar,
    .main-header,
    .top-header,
    .page-loader {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    
    .prescription-view-container {
        max-width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .prescription-card {
        box-shadow: none !important;
        padding: 25px !important;
        border: 2px solid #2563eb !important;
        border-radius: 8px !important;
        margin: 0 !important;
        display: block !important;
        visibility: visible !important;
    }
    
    /* Doctor Header Section */
    .prescription-header {
        display: flex !important;
        flex-direction: row !important;
        justify-content: space-between !important;
        align-items: flex-start !important;
        margin-bottom: 15px !important;
        width: 100% !important;
    }
    
    .doctor-info {
        display: block !important;
        flex: 1 !important;
        text-align: left !important;
    }
    
    .doctor-info h2 {
        color: #2563eb !important;
        font-size: 16pt !important;
        margin: 0 0 5px 0 !important;
    }
    
    .doctor-info p {
        margin: 3px 0 !important;
        font-size: 10pt !important;
        color: #475569 !important;
    }
    
    .prescription-logo {
        text-align: right !important;
        display: block !important;
        flex: 0 0 auto !important;
        margin-left: auto !important;
    }
    
    .prescription-logo img {
        width: 50px !important;
        height: 50px !important;
        display: block !important;
        margin-left: auto !important;
    }
    
    .prescription-logo h3 {
        font-size: 12pt !important;
        color: #2563eb !important;
        margin: 5px 0 0 0 !important;
        text-align: right !important;
    }
    
    /* Horizontal divider */
    .prescription-card hr {
        border: 2px solid #2563eb !important;
        margin: 10px 0 !important;
        display: block !important;
    }
    
    /* All sections visible */
    .patient-section,
    .diagnosis-section,
    .remedies-section,
    .advice-section,
    .rx-symbol {
        display: block !important;
        visibility: visible !important;
        margin: 8px 0 !important;
    }
    
    .signature-section {
        display: block !important;
        visibility: visible !important;
        margin: 20px 0 0 0 !important;
    }
    
    /* Section titles with icon and border */
    .section-title {
        display: flex !important;
        align-items: center !important;
        gap: 6px !important;
        background: #f1f5f9 !important;
        padding: 5px 10px !important;
        border-left: 3px solid #2563eb !important;
        margin-bottom: 6px !important;
        border-radius: 0 4px 4px 0 !important;
    }
    
    .section-title i {
        color: #2563eb !important;
        font-size: 9pt !important;
    }
    
    .section-title h3 {
        font-size: 9pt !important;
        color: #1e293b !important;
        margin: 0 !important;
        font-weight: 600 !important;
    }
    
    /* Patient info grid - 4 columns, compact inline layout */
    .info-grid {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
        gap: 4px 8px !important;
    }
    
    .info-item {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        white-space: nowrap !important;
        align-items: baseline !important;
        gap: 4px !important;
    }
    
    .info-item label {
        font-weight: 600 !important;
        color: #475569 !important;
        font-size: 9pt !important;
        white-space: nowrap !important;
    }
    
    .info-item span {
        color: #1e293b !important;
        font-size: 9pt !important;
        white-space: nowrap !important;
    }
    
    /* Diagnosis content - compact inline */
    .diagnosis-content {
        padding: 5px 10px !important;
        font-size: 9pt !important;
    }
    
    .diagnosis-content strong {
        color: #475569 !important;
        font-size: 9pt !important;
    }
    
    .diagnosis-content span {
        color: #1e293b !important;
        font-size: 9pt !important;
    }
    
    /* Rx Symbol - smaller */
    .rx-symbol {
        text-align: center !important;
        margin: 10px 0 !important;
    }
    
    .rx-symbol span {
        font-size: 32pt !important;
        color: #2563eb !important;
        font-weight: bold !important;
    }
    
    /* Remedies table - compact */
    .remedies-table {
        width: 100% !important;
        border-collapse: collapse !important;
        display: table !important;
        visibility: visible !important;
        font-size: 9pt !important;
    }
    
    .remedies-table thead {
        display: table-header-group !important;
    }
    
    .remedies-table th {
        background: #f1f5f9 !important;
        color: #475569 !important;
        font-weight: 600 !important;
        padding: 5px 6px !important;
        font-size: 8pt !important;
        text-align: left !important;
        border: 1px solid #e2e8f0 !important;
    }
    
    .remedies-table td {
        padding: 5px 6px !important;
        font-size: 8pt !important;
        border: 1px solid #e2e8f0 !important;
        color: #1e293b !important;
    }
    
    .remedies-table tr {
        page-break-inside: avoid;
    }
    
    .remedies-table tbody tr:nth-child(even) {
        background: #f8fafc !important;
    }
    
    /* Advice grid - 2 columns */
    .advice-grid {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 4px 12px !important;
    }
    
    .advice-item {
        display: flex !important;
        flex-direction: row !important;
        align-items: baseline !important;
        gap: 4px !important;
        margin: 0 !important;
        padding: 0 !important;
        background: transparent !important;
        border: none !important;
        border-left: none !important;
    }
    
    .advice-item strong {
        display: inline !important;
        color: #475569 !important;
        margin-bottom: 0 !important;
        font-size: 8pt !important;
        white-space: nowrap !important;
    }
    
    .advice-item strong i {
        color: #2563eb !important;
        margin-right: 3px !important;
        font-size: 8pt !important;
    }
    
    .advice-item span {
        color: #1e293b !important;
        font-size: 8pt !important;
    }
    
    /* Signature section */
    .signature-section {
        margin-top: 25px !important;
        display: flex !important;
        justify-content: flex-end !important;
        page-break-inside: avoid;
    }
    
    .signature-box {
        text-align: center !important;
        min-width: 150px !important;
    }
    
    .signature-line {
        border-top: 2px solid #1e293b !important;
        margin-bottom: 8px !important;
    }
    
    .signature-box p {
        margin: 3px 0 !important;
        font-size: 9pt !important;
        color: #475569 !important;
    }
    
    .signature-box p strong {
        color: #1e293b !important;
    }
    
    /* Page break controls */
    .patient-section,
    .diagnosis-section {
        page-break-inside: avoid;
    }
    
    @page {
        size: A4;
        margin: 12mm;
    }
}

@media (max-width: 768px) {
    .prescription-card {
        padding: 20px;
    }
    
    .prescription-header {
        flex-direction: column;
        gap: 20px;
    }
    
    .prescription-logo {
        text-align: left;
    }
    
    .prescription-logo img {
        margin-left: 0;
    }
    
    .prescription-logo h3 {
        text-align: left;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .remedies-table {
        font-size: 0.85rem;
    }
    
    .remedies-table th,
    .remedies-table td {
        padding: 8px 5px;
    }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
