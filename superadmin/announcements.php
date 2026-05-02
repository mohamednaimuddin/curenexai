<?php
/**
 * Super Admin - Announcements
 */

define('ADMIN_PAGE', true);
require_once __DIR__ . '/../includes/init.php';

$pageTitle = 'Announcements';
generateCsrfToken();

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $title = sanitize($_POST['title'] ?? '');
            $content = sanitize($_POST['content'] ?? '');
            $type = sanitize($_POST['type'] ?? 'info');
            $targetAudience = sanitize($_POST['target_audience'] ?? 'all');
            $startDate = $_POST['start_date'] ?? null;
            $endDate = $_POST['end_date'] ?? null;
            $showPopup = isset($_POST['show_popup']) ? 1 : 0;
            $priority = intval($_POST['priority'] ?? 0);
            $targetDoctors = $_POST['target_doctors'] ?? [];
            
            if (empty($title) || empty($content)) {
                $error = 'Title and content are required.';
            } else {
                try {
                    $targetDoctorsJson = !empty($targetDoctors) ? json_encode(array_map('intval', $targetDoctors)) : null;
                    
                    DB::insert('announcements', [
                        'admin_id' => $_SESSION['admin_id'],
                        'title' => $title,
                        'content' => $content,
                        'type' => $type,
                        'target_audience' => $targetAudience,
                        'target_doctors' => $targetDoctorsJson,
                        'start_date' => $startDate ?: null,
                        'end_date' => $endDate ?: null,
                        'show_popup' => $showPopup,
                        'priority' => $priority,
                        'is_active' => true
                    ]);
                    
                    logAdminActivity($_SESSION['admin_id'], 'create_announcement', "Created announcement: $title");
                    $success = 'Announcement created successfully.';
                } catch (Exception $e) {
                    $error = 'Failed to create announcement: ' . $e->getMessage();
                }
            }
            break;
            
        case 'toggle':
            $announcementId = intval($_POST['announcement_id'] ?? 0);
            if ($announcementId > 0) {
                $current = DB::queryOne("SELECT is_active FROM announcements WHERE id = ?", [$announcementId]);
                if ($current) {
                    $newStatus = $current['is_active'] ? 0 : 1;
                    DB::update('announcements', ['is_active' => $newStatus], 'id = ?', [$announcementId]);
                    $success = $newStatus ? 'Announcement activated.' : 'Announcement deactivated.';
                }
            }
            break;
            
        case 'delete':
            $announcementId = intval($_POST['announcement_id'] ?? 0);
            if ($announcementId > 0) {
                DB::delete('announcements', 'id = ?', [$announcementId]);
                logAdminActivity($_SESSION['admin_id'], 'delete_announcement', "Deleted announcement #$announcementId");
                $success = 'Announcement deleted.';
            }
            break;
    }
}

// Get announcements
$announcements = [];
try {
    $announcements = DB::query("
        SELECT a.*, sa.full_name as admin_name 
        FROM announcements a 
        JOIN super_admins sa ON a.admin_id = sa.id 
        ORDER BY a.priority DESC, a.created_at DESC
    ");
} catch (Exception $e) {
    // Table might not exist
}

// Get all doctors for the dropdown
$doctors = [];
try {
    $doctors = DB::query("SELECT id, full_name, email FROM doctors WHERE status = 'active' ORDER BY full_name");
} catch (Exception $e) {
    // Table might not exist
}

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Announcements</h4>
        <p class="text-muted mb-0">Broadcast messages to doctors and users</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
        <i class="bi bi-plus-lg me-2"></i>New Announcement
    </button>
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

<!-- Announcements List -->
<div class="row g-4">
    <?php if (empty($announcements)): ?>
        <div class="col-12">
            <div class="data-table">
                <div class="p-5 text-center">
                    <i class="bi bi-megaphone display-1 text-muted"></i>
                    <h5 class="mt-3">No Announcements Yet</h5>
                    <p class="text-muted">Create your first announcement to broadcast to users.</p>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($announcements as $announcement): ?>
            <?php
            $typeColors = [
                'info' => 'primary',
                'warning' => 'warning',
                'success' => 'success',
                'danger' => 'danger'
            ];
            $bgColor = $typeColors[$announcement['type']] ?? 'primary';
            ?>
            <div class="col-md-6">
                <div class="data-table h-100">
                    <div class="p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <span class="badge bg-<?php echo $bgColor; ?>">
                                <?php echo ucfirst($announcement['type']); ?>
                            </span>
                            <div class="d-flex gap-2">
                                <?php if ($announcement['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <h5 class="mb-2"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                        <p class="text-muted mb-3"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                        
                        <div class="d-flex justify-content-between align-items-center text-muted small">
                            <span>
                                <i class="bi bi-person me-1"></i>
                                <?php echo htmlspecialchars($announcement['admin_name']); ?>
                            </span>
                            <span>
                                <i class="bi bi-clock me-1"></i>
                                <?php echo date('M j, Y', strtotime($announcement['created_at'])); ?>
                            </span>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <div>
                                <span class="badge bg-light text-dark me-1">
                                    <i class="bi bi-people me-1"></i>
                                    <?php echo ucfirst($announcement['target_audience']); ?>
                                </span>
                                <?php if (!empty($announcement['target_doctors'])): ?>
                                    <span class="badge bg-info text-white">
                                        <i class="bi bi-person-check me-1"></i>
                                        <?php 
                                        $targetIds = json_decode($announcement['target_doctors'], true);
                                        echo count($targetIds) . ' specific doctor(s)';
                                        ?>
                                    </span>
                                <?php endif; ?>
                                <?php if (isset($announcement['show_popup']) && $announcement['show_popup']): ?>
                                    <span class="badge bg-purple text-white" style="background-color: #6f42c1;">
                                        <i class="bi bi-window-stack me-1"></i>Popup
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="btn-group">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-<?php echo $announcement['is_active'] ? 'warning' : 'success'; ?>">
                                        <?php echo $announcement['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this announcement?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add Announcement Modal -->
<div class="modal fade" id="addAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-megaphone me-2"></i>New Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="create">
                    
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="content" rows="4" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Type</label>
                            <select class="form-select" name="type">
                                <option value="info">Info (Blue)</option>
                                <option value="success">Success (Green)</option>
                                <option value="warning">Warning (Yellow)</option>
                                <option value="danger">Danger (Red)</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Target Audience</label>
                            <select class="form-select" name="target_audience" id="targetAudience" onchange="toggleDoctorSelect()">
                                <option value="all">All Users</option>
                                <option value="doctors">Doctors Only</option>
                                <option value="patients">Patients Only</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-select" name="priority">
                                <option value="0">Normal</option>
                                <option value="1">High</option>
                                <option value="2">Urgent</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="specificDoctorsDiv" style="display: none;">
                        <label class="form-label">Target Specific Doctors (Optional)</label>
                        <select class="form-select" name="target_doctors[]" id="targetDoctors" multiple size="5">
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>">
                                    <?php echo htmlspecialchars($doctor['full_name']); ?> (<?php echo htmlspecialchars($doctor['email']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Hold Ctrl/Cmd to select multiple doctors. Leave empty to target all in audience.</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date (Optional)</label>
                            <input type="datetime-local" class="form-control" name="start_date">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date (Optional)</label>
                            <input type="datetime-local" class="form-control" name="end_date">
                        </div>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="show_popup" id="showPopup" checked>
                        <label class="form-check-label" for="showPopup">
                            <i class="bi bi-window-stack me-1"></i> Show as popup when doctor logs in
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleDoctorSelect() {
    const audience = document.getElementById('targetAudience').value;
    const doctorDiv = document.getElementById('specificDoctorsDiv');
    
    if (audience === 'doctors' || audience === 'all') {
        doctorDiv.style.display = 'block';
    } else {
        doctorDiv.style.display = 'none';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleDoctorSelect();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
