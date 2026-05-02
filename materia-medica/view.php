<?php
/**
 * Materia Medica - View Remedy Details
 */
require_once '../includes/init.php';

// Check if logged in
if (!isLoggedIn()) {
    redirect('/login.php');
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    setFlash('danger', 'Invalid remedy ID');
    redirect('/materia-medica/list.php');
}

// Get remedy details
$remedy = DB::queryOne("SELECT * FROM remedies WHERE id = ?", [$id]);

if (!$remedy) {
    setFlash('danger', 'Remedy not found');
    redirect('/materia-medica/list.php');
}

// Get related rubrics from the live repertory schema
$relatedRubrics = DB::query("
    SELECT r.id            AS rubric_id,
           r.rubric        AS rubric_text,
           r.complete_rubric,
           r.category,
           r.sub_category,
           r.repertory_source,
           rr.grade,
           rr.is_verified,
           rr.verified_source,
           rr.verified_page
      FROM repertory_remedies rr
      JOIN repertory r ON r.id = rr.repertory_id
     WHERE rr.remedy_id = ?
     ORDER BY rr.is_verified DESC,
              r.category,
              rr.grade DESC,
              r.complete_rubric
", [$remedy['id']]);

// Log activity
logActivity('view_remedy', 'Viewed remedy: ' . $remedy['remedy_name']);

$pageTitle = $remedy['remedy_name'] . ' - Materia Medica';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>
            <i class="fas fa-pills"></i> 
            <?= htmlspecialchars($remedy['remedy_name']) ?>
        </h1>
        <nav class="breadcrumb">
            <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
            <span>/</span>
            <a href="<?= APP_URL ?>/materia-medica/list.php">Materia Medica</a>
            <span>/</span>
            <span><?= htmlspecialchars($remedy['remedy_name']) ?></span>
        </nav>
    </div>
    <div class="page-actions no-print">
        <a href="<?= APP_URL ?>/materia-medica/list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
        <!-- OoRep.com button removed -->
        <button class="btn btn-secondary" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<div class="remedy-view">
    <!-- Basic Information -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-info-circle"></i> Basic Information</h2>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label><i class="fas fa-tag"></i> Remedy Name:</label>
                    <div class="info-value remedy-name-large">
                        <?= htmlspecialchars($remedy['remedy_name']) ?>
                    </div>
                </div>
                
                <?php if (!empty($remedy['latin_name'])): ?>
                <div class="info-item">
                    <label><i class="fas fa-leaf"></i> Latin Name:</label>
                    <div class="info-value latin-name">
                        <em><?= htmlspecialchars($remedy['latin_name']) ?></em>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($remedy['common_name'])): ?>
                <div class="info-item">
                    <label><i class="fas fa-seedling"></i> Common Name:</label>
                    <div class="info-value">
                        <?= htmlspecialchars($remedy['common_name']) ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($remedy['family'])): ?>
                <div class="info-item">
                    <label><i class="fas fa-sitemap"></i> Family:</label>
                    <div class="info-value">
                        <span class="badge badge-primary badge-lg">
                            <?= htmlspecialchars($remedy['family']) ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($remedy['book_reference'])): ?>
                <div class="info-item">
                    <label><i class="fas fa-book"></i> Book Reference:</label>
                    <div class="info-value book-reference">
                        <?= htmlspecialchars($remedy['book_reference']) ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Keynote Symptoms -->
    <?php if (!empty($remedy['keynote_symptoms'])): ?>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-key"></i> Keynote Symptoms</h2>
        </div>
        <div class="card-body">
            <div class="keynote-content">
                <?= nl2br(htmlspecialchars($remedy['keynote_symptoms'])) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Clinical Indications -->
    <?php if (!empty($remedy['clinical_indications'])): ?>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-stethoscope"></i> Clinical Indications</h2>
        </div>
        <div class="card-body">
            <div class="clinical-content">
                <?= nl2br(htmlspecialchars($remedy['clinical_indications'])) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modalities -->
    <?php if (!empty($remedy['modalities'])): ?>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-adjust"></i> Modalities</h2>
        </div>
        <div class="card-body">
            <div class="modalities-grid">
                <?php 
                // Parse modalities (assuming format: "Better: xxx\nWorse: yyy")
                $modalitiesText = $remedy['modalities'];
                $lines = explode("\n", $modalitiesText);
                
                $better = [];
                $worse = [];
                $current = null;
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    if (stripos($line, 'better') === 0 || stripos($line, 'amelioration') === 0) {
                        $current = 'better';
                        $line = preg_replace('/^(better|amelioration):?\s*/i', '', $line);
                        if (!empty($line)) $better[] = $line;
                    } elseif (stripos($line, 'worse') === 0 || stripos($line, 'aggravation') === 0) {
                        $current = 'worse';
                        $line = preg_replace('/^(worse|aggravation):?\s*/i', '', $line);
                        if (!empty($line)) $worse[] = $line;
                    } else {
                        if ($current === 'better') $better[] = $line;
                        elseif ($current === 'worse') $worse[] = $line;
                    }
                }
                ?>
                
                <?php if (!empty($better)): ?>
                <div class="modality-section modality-better">
                    <h3><i class="fas fa-arrow-up"></i> Better (Amelioration)</h3>
                    <ul>
                        <?php foreach ($better as $item): ?>
                            <li><?= htmlspecialchars($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($worse)): ?>
                <div class="modality-section modality-worse">
                    <h3><i class="fas fa-arrow-down"></i> Worse (Aggravation)</h3>
                    <ul>
                        <?php foreach ($worse as $item): ?>
                            <li><?= htmlspecialchars($item) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
                
                <?php if (empty($better) && empty($worse)): ?>
                <div style="padding: 20px; color: var(--gray-600);">
                    <?= nl2br(htmlspecialchars($modalitiesText)) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Related Repertory Rubrics -->
    <?php if (!empty($relatedRubrics)): ?>
    <div class="card">
        <div class="card-header">
            <h2>
                <i class="fas fa-book"></i> 
                Related Repertory Rubrics (<?= count($relatedRubrics) ?>)
            </h2>
        </div>
        <div class="card-body">
            <div class="rubrics-list">
                <?php 
                $currentCategory = '';
                foreach ($relatedRubrics as $rubric): 
                    if ($currentCategory !== $rubric['category']):
                        if ($currentCategory !== '') echo '</div>'; // Close previous category
                        $currentCategory = $rubric['category'];
                ?>
                    <div class="rubric-category">
                        <h3><?= htmlspecialchars(ucfirst($currentCategory)) ?></h3>
                <?php endif; ?>
                
                        <div class="rubric-item">
                            <div class="rubric-text">
                                <i class="fas fa-bookmark"></i>
                                <?= htmlspecialchars($rubric['complete_rubric'] ?: $rubric['rubric_text']) ?>
                                <?php if (!empty($rubric['is_verified'])): ?>
                                    <span class="verified-badge"
                                          title="Verified from <?= htmlspecialchars((string)$rubric['verified_source']) ?> (p.<?= (int)$rubric['verified_page'] ?>)">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                <?php endif; ?>
                                <?php if (!empty($rubric['repertory_source'])): ?>
                                    <span class="source-badge"
                                          title="Repertory source: <?= htmlspecialchars((string)$rubric['repertory_source']) ?>">
                                        <i class="fas fa-book"></i>
                                        <?= htmlspecialchars((string)$rubric['repertory_source']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php if ($rubric['grade']): ?>
                                <span class="grade-badge grade-<?= (int)$rubric['grade'] ?>">
                                    Grade <?= (int)$rubric['grade'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                
                <?php endforeach; ?>
                    </div> <!-- Close last category -->
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Additional Notes -->
    <?php if (!empty($remedy['additional_notes'])): ?>
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-sticky-note"></i> Additional Notes</h2>
        </div>
        <div class="card-body">
            <div class="notes-content">
                <?= nl2br(htmlspecialchars($remedy['additional_notes'])) ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- External Resources -->
    <div class="card no-print">
        <div class="card-header bg-info text-white">
            <h2><i class="fas fa-external-link-alt"></i> External Resources</h2>
        </div>
        <div class="card-body">
            <div class="external-links">
                <div class="external-link-card">
                    <div class="external-link-icon">
                        <i class="fas fa-book-medical"></i>
                    </div>
                    <div class="external-link-content">
                        <h4>Online Homeopathic Repertory</h4>
                        <p>Search comprehensive repertory and materia medica database</p>
                        <!-- OoRep.com external resource removed -->
                    </div>
                </div>
                
                <div class="external-link-card">
                    <div class="external-link-icon">
                        <i class="fas fa-pills"></i>
                    </div>
                    <div class="external-link-content">
                        <h4>Remedy Database</h4>
                        <p>Explore complete repertory with remedy information</p>
                        <!-- OoRep.com external resource removed -->
                    </div>
                </div>
                
                <div class="external-link-card">
                    <div class="external-link-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <div class="external-link-content">
                        <h4>Reference Resources</h4>
                        <p>Access complete online homeopathic repertory resources</p>
                        <!-- OoRep.com external resource removed -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card no-print">
        <div class="card-header">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
        </div>
        <div class="card-body">
            <div class="action-buttons">
                <a href="<?= APP_URL ?>/repertory/search.php?remedy=<?= urlencode($remedy['remedy_name']) ?>" 
                   class="btn btn-primary">
                    <i class="fas fa-search"></i> Search in Repertory
                </a>
                <a href="<?= APP_URL ?>/prescriptions/add.php?remedy=<?= urlencode($remedy['remedy_name']) ?>" 
                   class="btn btn-success">
                    <i class="fas fa-prescription"></i> Create Prescription
                </a>
                <button class="btn btn-secondary" onclick="copyRemedyInfo()">
                    <i class="fas fa-copy"></i> Copy Information
                </button>
                <a href="<?= APP_URL ?>/materia-medica/list.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.remedy-view {
    max-width: 1200px;
}

/* Info Grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.info-item {
    padding: 15px;
    background: var(--gray-50);
    border-radius: 8px;
    border-left: 3px solid var(--primary-color);
}

.info-item label {
    display: block;
    font-size: 13px;
    color: var(--gray-600);
    margin-bottom: 8px;
    font-weight: 600;
}

.info-item label i {
    margin-right: 5px;
    color: var(--primary-color);
}

.info-value {
    font-size: 16px;
    color: var(--dark-color);
    font-weight: 500;
}

.remedy-name-large {
    font-size: 24px;
    color: var(--primary-color);
    font-weight: 700;
}

.latin-name {
    color: var(--success-color);
    font-size: 18px;
}

.badge-lg {
    padding: 8px 16px;
    font-size: 14px;
}

/* Content Sections */
.keynote-content,
.clinical-content,
.notes-content {
    font-size: 15px;
    line-height: 1.8;
    color: var(--gray-700);
    padding: 20px;
    background: var(--gray-50);
    border-radius: 8px;
}

/* Modalities */
.modalities-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.modality-section {
    padding: 20px;
    border-radius: 10px;
    border: 2px solid;
}

.modality-better {
    background: #f0fdf4;
    border-color: var(--success-color);
}

.modality-worse {
    background: #fef2f2;
    border-color: var(--danger-color);
}

.modality-section h3 {
    font-size: 16px;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.modality-better h3 {
    color: var(--success-color);
}

.modality-worse h3 {
    color: var(--danger-color);
}

.modality-section ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.modality-section li {
    padding: 8px 0 8px 25px;
    position: relative;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.modality-section li:last-child {
    border-bottom: none;
}

.modality-better li:before {
    content: '✓';
    position: absolute;
    left: 0;
    color: var(--success-color);
    font-weight: bold;
}

.modality-worse li:before {
    content: '✗';
    position: absolute;
    left: 0;
    color: var(--danger-color);
    font-weight: bold;
}

/* Rubrics List */
.rubrics-list {
    padding: 10px 0;
}

.rubric-category {
    margin-bottom: 25px;
}

.rubric-category h3 {
    font-size: 18px;
    color: var(--primary-color);
    margin-bottom: 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--primary-color);
}

.rubric-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 15px;
    background: var(--gray-50);
    margin-bottom: 8px;
    border-radius: 8px;
    transition: all 0.3s;
}

.rubric-item:hover {
    background: var(--gray-100);
    transform: translateX(5px);
}

.rubric-text {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 10px;
}

.rubric-text i {
    color: var(--success-color);
    font-size: 14px;
}

.grade-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.grade-1 { background: #dbeafe; color: #1e40af; }
.grade-2 { background: #fef3c7; color: #92400e; }
.grade-3 { background: #fecaca; color: #991b1b; }

/* Verified badge (matches repertory/search.php) */
.verified-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-left: 8px;
    padding: 2px 9px;
    background: linear-gradient(135deg, #16a34a, #15803d);
    color: #fff;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.4;
    vertical-align: middle;
    box-shadow: 0 1px 2px rgba(22,163,74,.25);
}
.verified-badge i { font-size: 11px; }

.source-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-left: 6px;
    padding: 2px 9px;
    background: #eef2ff;
    color: #3730a3;
    border: 1px solid #c7d2fe;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    line-height: 1.4;
    vertical-align: middle;
}
.source-badge i { font-size: 10px; }

/* External Resources */
.external-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
}

.external-link-card {
    display: flex;
    gap: 15px;
    padding: 20px;
    background: var(--gray-50);
    border-radius: 10px;
    border: 2px solid var(--gray-200);
    transition: all 0.3s;
}

.external-link-card:hover {
    border-color: var(--info-color);
    background: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}

.external-link-icon {
    flex-shrink: 0;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--info-color), #3b82f6);
    color: white;
    border-radius: 10px;
    font-size: 24px;
}

.external-link-content h4 {
    margin: 0 0 8px 0;
    color: var(--dark-color);
    font-size: 16px;
}

.external-link-content p {
    margin: 0 0 12px 0;
    color: var(--gray-600);
    font-size: 13px;
    line-height: 1.5;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.action-buttons .btn {
    flex: 1;
    min-width: 200px;
}

/* Responsive */
@media (max-width: 767.98px) {
    .external-links {
        grid-template-columns: 1fr;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .modalities-grid {
        grid-template-columns: 1fr;
    }
    
    .rubric-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons .btn {
        width: 100%;
        min-width: auto;
    }
}

@media print {
    .remedy-view {
        max-width: 100%;
    }
    
    .card {
        page-break-inside: avoid;
        margin-bottom: 20px;
        border: 1px solid #333;
    }
    
    .modality-better {
        border-color: #666;
    }
    
    .modality-worse {
        border-color: #666;
    }
}
</style>

<script>
// Copy remedy information
function copyRemedyInfo() {
    const remedyName = <?= json_encode($remedy['remedy_name']) ?>;
    const latinName = <?= json_encode($remedy['latin_name'] ?? '') ?>;
    const keynotes = <?= json_encode($remedy['keynote_symptoms'] ?? '') ?>;
    
    let text = `Remedy: ${remedyName}\n`;
    if (latinName) text += `Latin Name: ${latinName}\n`;
    if (keynotes) text += `\nKeynote Symptoms:\n${keynotes}`;
    
    navigator.clipboard.writeText(text).then(() => {
        alert('Remedy information copied to clipboard!');
    }).catch(() => {
        alert('Failed to copy to clipboard');
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>
