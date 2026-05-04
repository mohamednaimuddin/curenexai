<?php
require_once __DIR__ . '/../includes/init.php';
requireLogin();

$doctorId = getLoggedInDoctorId();
$prescriptionId = $_GET['id'] ?? 0;

// Fetch prescription with patient and doctor info
$sql = "SELECT p.*, 
               pat.patient_name, pat.age, pat.gender, pat.phone, pat.email, pat.address,
               pat.weight, pat.height, pat.blood_group, pat.allergies,
               d.full_name as doctor_name, d.qualification, d.registration_number, 
               d.phone as doctor_phone, d.email as doctor_email,
               c.chief_complaint, c.diagnosis
        FROM prescriptions p
        INNER JOIN patients pat ON p.patient_id = pat.id
        INNER JOIN doctors d ON p.doctor_id = d.id
        INNER JOIN consultations c ON p.consultation_id = c.id
        WHERE p.id = ? AND p.doctor_id = ?";

$prescription = DB::queryOne($sql, [$prescriptionId, $doctorId]);

if (!$prescription) {
    die('Prescription not found or access denied');
}

// Fetch prescribed remedies
$remedies = DB::query(
    "SELECT pr.*, r.remedy_name, r.remedy_short_name, r.common_name, r.family
     FROM prescription_remedies pr
     INNER JOIN remedies r ON pr.remedy_id = r.id
     WHERE pr.prescription_id = ?
     ORDER BY pr.id",
    [$prescriptionId]
);

$pageTitle = 'Print Prescription';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Homeopathy Management System</title>
    <style>@font-face{font-family:'Font Awesome 6 Brands';font-display:swap}@font-face{font-family:'Font Awesome 6 Free';font-display:swap}@font-face{font-family:'Font Awesome 6 Solid';font-display:swap}</style>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/font-awesome.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-600: #475569;
            --gray-800: #1e293b;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.6;
            background: white;
            padding: 20px;
        }

        .prescription-container {
            max-width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            border: 2px solid var(--primary-color);
            border-radius: 10px;
            overflow: hidden;
        }

        .prescription-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #1e40af 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .prescription-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .doctor-info {
            margin-top: 15px;
            font-size: 14px;
        }

        .doctor-info p {
            margin: 5px 0;
        }

        .prescription-body {
            padding: 30px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            background: var(--gray-100);
            padding: 10px 15px;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 15px;
            font-weight: bold;
            font-size: 18px;
            color: var(--gray-800);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            gap: 10px;
        }

        .info-item label {
            font-weight: bold;
            color: var(--gray-600);
            min-width: 120px;
        }

        .info-item span {
            color: var(--gray-800);
        }

        .remedy-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .remedy-table th {
            background: var(--primary-color);
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }

        .remedy-table td {
            padding: 12px;
            border-bottom: 1px solid var(--gray-200);
        }

        .remedy-table tr:hover {
            background: var(--gray-100);
        }

        .remedy-name {
            font-weight: bold;
            color: var(--primary-color);
        }

        .advice-box {
            background: var(--gray-100);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .advice-box h4 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 16px;
        }

        .advice-box p {
            color: var(--gray-800);
            line-height: 1.8;
        }

        .prescription-footer {
            background: var(--gray-100);
            padding: 20px 30px;
            text-align: center;
            border-top: 2px solid var(--primary-color);
        }

        .signature-section {
            margin-top: 40px;
            text-align: right;
            padding-right: 50px;
        }

        .signature-line {
            border-top: 2px solid #333;
            width: 250px;
            display: inline-block;
            margin-top: 50px;
        }

        .signature-text {
            margin-top: 10px;
            font-weight: bold;
        }

        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        @media print {
            body {
                padding: 0;
            }

            .prescription-container {
                border: none;
                border-radius: 0;
                max-width: 100%;
                min-height: auto;
            }

            .no-print {
                display: none !important;
            }

            .prescription-header {
                break-inside: avoid;
            }

            .section {
                break-inside: avoid;
            }

            .remedy-table {
                break-inside: avoid;
            }

            @page {
                margin: 0.5cm;
                size: A4;
            }
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: var(--success-color);
            color: white;
        }

        .rx-symbol {
            font-size: 48px;
            color: var(--primary-color);
            font-weight: bold;
            font-style: italic;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <!-- Print Button -->
    <div class="no-print">
        <button onclick="window.print()" style="padding: 12px 24px; background: var(--primary-color); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <i class="fas fa-print"></i> Print Prescription
        </button>
        <button onclick="window.close()" style="padding: 12px 24px; background: var(--secondary-color); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; margin-left: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            <i class="fas fa-times"></i> Close
        </button>
    </div>

    <div class="prescription-container">
        <!-- Header -->
        <div class="prescription-header">
            <h1><i class="fas fa-heart-pulse"></i> Homeopathic Prescription</h1>
            <div class="doctor-info">
                <h2><?php echo htmlspecialchars($prescription['doctor_name']); ?></h2>
                <p><?php echo htmlspecialchars($prescription['qualification'] ?? 'BHMS'); ?></p>
                <p>Reg. No: <?php echo htmlspecialchars($prescription['registration_number']); ?></p>
                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($prescription['doctor_phone']); ?> | 
                   <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($prescription['doctor_email']); ?></p>
            </div>
        </div>

        <!-- Body -->
        <div class="prescription-body">
            <!-- Patient Information -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-user-injured"></i> Patient Information
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Name:</label>
                        <span><?php echo htmlspecialchars($prescription['patient_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Age / Gender:</label>
                        <span><?php echo $prescription['age']; ?> years / <?php echo ucfirst($prescription['gender']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Date:</label>
                        <span><?php echo formatDate($prescription['prescription_date'], 'd M Y'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Prescription ID:</label>
                        <span>#<?php echo str_pad($prescription['id'], 6, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <?php if (!empty($prescription['phone'])): ?>
                    <div class="info-item">
                        <label>Phone:</label>
                        <span><?php echo htmlspecialchars($prescription['phone']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($prescription['blood_group'])): ?>
                    <div class="info-item">
                        <label>Blood Group:</label>
                        <span><?php echo htmlspecialchars($prescription['blood_group']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($prescription['weight'])): ?>
                    <div class="info-item">
                        <label>Weight:</label>
                        <span><?php echo htmlspecialchars($prescription['weight']); ?> kg</span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($prescription['allergies'])): ?>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <label>Allergies:</label>
                        <span style="color: var(--danger-color); font-weight: bold;">
                            <?php echo htmlspecialchars($prescription['allergies']); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Diagnosis -->
            <?php if (!empty($prescription['chief_complaint']) || !empty($prescription['diagnosis'])): ?>
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-stethoscope"></i> Diagnosis & Chief Complaint
                </div>
                <?php if (!empty($prescription['chief_complaint'])): ?>
                <div class="info-item" style="margin-bottom: 10px;">
                    <label>Chief Complaint:</label>
                    <span><?php echo htmlspecialchars($prescription['chief_complaint']); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($prescription['diagnosis'])): ?>
                <div class="info-item">
                    <label>Diagnosis:</label>
                    <span><?php echo htmlspecialchars($prescription['diagnosis']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Remedies -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-prescription-bottle"></i> ℞ Prescribed Remedies
                </div>
                
                <?php if (!empty($remedies)): ?>
                <table class="remedy-table">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 30%;">Remedy</th>
                            <th style="width: 15%;">Potency</th>
                            <th style="width: 15%;">Dosage</th>
                            <th style="width: 15%;">Duration</th>
                            <th style="width: 20%;">Instructions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($remedies as $index => $remedy): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td>
                                <div class="remedy-name"><?php echo htmlspecialchars($remedy['remedy_name']); ?></div>
                                <?php if (!empty($remedy['common_name'])): ?>
                                <small style="color: var(--gray-600);"><?php echo htmlspecialchars($remedy['common_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($remedy['potency'] ?? '30C'); ?></strong></td>
                            <td><?php echo htmlspecialchars($remedy['dosage'] ?? 'TDS'); ?></td>
                            <td><?php echo htmlspecialchars($remedy['duration'] ?? '7 days'); ?></td>
                            <td><?php echo htmlspecialchars($remedy['instructions'] ?? 'Take as directed'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="text-align: center; color: var(--gray-600); padding: 20px;">No remedies prescribed</p>
                <?php endif; ?>
            </div>

            <!-- Diet Advice -->
            <?php if (!empty($prescription['diet_advice'])): ?>
            <div class="section">
                <div class="advice-box">
                    <h4><i class="fas fa-apple-alt"></i> Diet Advice</h4>
                    <p><?php echo nl2br(htmlspecialchars($prescription['diet_advice'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Lifestyle Advice -->
            <?php if (!empty($prescription['lifestyle_advice'])): ?>
            <div class="section">
                <div class="advice-box">
                    <h4><i class="fas fa-heartbeat"></i> Lifestyle Advice</h4>
                    <p><?php echo nl2br(htmlspecialchars($prescription['lifestyle_advice'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Follow-up Instructions -->
            <?php if (!empty($prescription['follow_up_instructions'])): ?>
            <div class="section">
                <div class="advice-box">
                    <h4><i class="fas fa-calendar-check"></i> Follow-up Instructions</h4>
                    <p><?php echo nl2br(htmlspecialchars($prescription['follow_up_instructions'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- General Instructions -->
            <?php if (!empty($prescription['general_instructions'])): ?>
            <div class="section">
                <div class="advice-box">
                    <h4><i class="fas fa-info-circle"></i> General Instructions</h4>
                    <p><?php echo nl2br(htmlspecialchars($prescription['general_instructions'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Signature -->
            <div class="signature-section">
                <div class="signature-line"></div>
                <div class="signature-text">
                    <?php echo htmlspecialchars($prescription['doctor_name']); ?><br>
                    <?php echo htmlspecialchars($prescription['qualification'] ?? 'BHMS'); ?>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="prescription-footer">
            <p><small>This is a computer-generated prescription. Generated on <?php echo formatDate(date('Y-m-d H:i:s'), 'd M Y, h:i A'); ?></small></p>
            <p><small><i class="fas fa-exclamation-triangle"></i> Keep out of reach of children. Store in a cool, dry place.</small></p>
        </div>
    </div>

    <script>
        // Auto-print on load (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>
