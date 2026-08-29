<?php
/**
 * Materia Medica - List All Remedies
 */
require_once '../includes/init.php';

// Check if logged in
if (!isLoggedIn()) {
    redirect('/login.php');
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$family = $_GET['family'] ?? '';
$book = $_GET['book'] ?? '';
$sort = $_GET['sort'] ?? 'name';

// Pagination settings
$perPage = 12; // Remedies per page
$page = max(1, intval($_GET['page'] ?? 1));

// Build query
$query = "SELECT * FROM remedies WHERE 1=1";
$params = [];

$bookFilters = [
    'kent' => [
        'label' => "Kent's Lectures / Kent Materia Medica",
        'pattern' => '%Kent%'
    ],
    'boericke' => [
        'label' => 'Boericke Materia Medica / Pocket Manual',
        'pattern' => '%Boericke%'
    ],
    'murphy' => [
        'label' => "Murphy's Lotus Materia Medica",
        'pattern' => '%Murphy%'
    ],
    'allen' => [
        'label' => "Allen's Keynotes / Encyclopedia",
        'pattern' => '%Allen%'
    ],
    'clarke' => [
        'label' => "Clarke's Dictionary",
        'pattern' => '%Clarke%'
    ],
    'hering' => [
        'label' => "Hering's Guiding Symptoms",
        'pattern' => '%Hering%'
    ],
    'vermeulen' => [
        'label' => 'Vermeulen',
        'pattern' => '%Vermeulen%'
    ],
    'indian_materia_medica' => [
        'label' => 'Indian Materia Medica',
        'pattern' => '%Indian Materia Medica%'
    ],
    'modern_provings' => [
        'label' => 'Modern Provings',
        'pattern' => '%Modern Provings%'
    ],
    'schuessler' => [
        'label' => "Schuessler Tissue Salts",
        'pattern' => '%Schuessler%'
    ],
    'foubister' => [
        'label' => 'Foubister',
        'pattern' => '%Foubister%'
    ],
];

if (!empty($search)) {
    // Extended search to include more symptom fields for better results
    $query .= " AND (
        LOWER(remedy_name) LIKE ? OR 
        LOWER(common_name) LIKE ? OR 
        LOWER(remedy_short_name) LIKE ? OR 
        LOWER(keynote_symptoms) LIKE ? OR
        LOWER(clinical_indications) LIKE ? OR
        LOWER(characteristic_symptoms) LIKE ? OR
        LOWER(mind_symptoms) LIKE ? OR
        LOWER(head_symptoms) LIKE ? OR
        LOWER(stomach_symptoms) LIKE ? OR
        LOWER(abdomen_symptoms) LIKE ? OR
        LOWER(respiratory_symptoms) LIKE ? OR
        LOWER(back_symptoms) LIKE ? OR
        LOWER(extremities_symptoms) LIKE ? OR
        LOWER(skin_symptoms) LIKE ? OR
        LOWER(modalities) LIKE ?
    )";
    $searchTerm = "%" . mb_strtolower(trim($search)) . "%";
    // 15 search fields
    for ($i = 0; $i < 15; $i++) {
        $params[] = $searchTerm;
    }
}

if (!empty($family)) {
    $query .= " AND family = ?";
    $params[] = $family;
}

if (!empty($book) && isset($bookFilters[$book])) {
    $query .= " AND book_reference LIKE ?";
    $params[] = $bookFilters[$book]['pattern'];
}

// Sorting
$validSorts = ['name' => 'remedy_name', 'family' => 'family', 'recent' => 'created_at DESC'];
$orderBy = $validSorts[$sort] ?? 'remedy_name';
$query .= " ORDER BY $orderBy";

// Get total count for pagination
$countQuery = str_replace('SELECT *', 'SELECT COUNT(*) as total', $query);
$countResult = DB::query($countQuery, $params);
$totalRemedies = is_array($countResult) && !empty($countResult) ? (int)$countResult[0]['total'] : 0;
$totalPages = max(1, ceil($totalRemedies / $perPage));
$page = min($page, $totalPages); // Ensure page doesn't exceed total
$offset = ($page - 1) * $perPage;

// Add pagination to query - Security: Cast to integers to prevent SQL injection
$safePerPage = (int)$perPage;
$safeOffset = (int)$offset;
$query .= " LIMIT $safePerPage OFFSET $safeOffset";

// Execute query
$remedies = DB::query($query, $params);
if (!is_array($remedies)) {
    $remedies = [];
}

// Get all families for filter
$families = DB::query("SELECT DISTINCT family FROM remedies WHERE family IS NOT NULL ORDER BY family");
if (!is_array($families)) {
    $families = [];
}

// Build pagination URL parameters
$paginationParams = [];
if (!empty($search)) $paginationParams['search'] = $search;
if (!empty($family)) $paginationParams['family'] = $family;
if (!empty($book)) $paginationParams['book'] = $book;
if ($sort !== 'name') $paginationParams['sort'] = $sort;

$pageTitle = 'Materia Medica';
require_once '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-book-medical"></i> Materia Medica</h1>
        <nav class="breadcrumb">
            <a href="<?= APP_URL ?>/dashboard.php">Dashboard</a>
            <span>/</span>
            <span>Materia Medica</span>
        </nav>
    </div>
    <div class="page-actions">
        <button class="btn btn-secondary" onclick="window.print()">
            <i class="fas fa-print"></i> Print
        </button>
        <button class="btn btn-secondary" onclick="exportToCSV()">
            <i class="fas fa-download"></i> Export CSV
        </button>
    </div>
</div>

<div class="alert alert-info">
    <i class="fas fa-info-circle"></i>
    <strong>Materia Medica Reference:</strong> Explore detailed information about homeopathic remedies including keynote symptoms, clinical indications, and modalities.
</div>

<!-- Search and Filter -->
<div class="card">
    <div class="card-body">
        <form method="GET" action="" class="search-filter-form">
            <div class="form-row">
                <div class="form-group" style="flex: 2;">
                    <label><i class="fas fa-search"></i> Search Remedies</label>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control" 
                        placeholder="Search by remedy name, latin name, or common name..."
                        value="<?= htmlspecialchars($search) ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-book"></i> Reference Book</label>
                    <select name="book" class="form-control">
                        <option value="">All Books</option>
                        <?php foreach ($bookFilters as $bookKey => $bookFilter): ?>
                            <option value="<?= htmlspecialchars($bookKey) ?>" <?= $book === $bookKey ? 'selected' : '' ?>>
                                <?= htmlspecialchars($bookFilter['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-filter"></i> Family</label>
                    <select name="family" class="form-control">
                        <option value="">All Families</option>
                        <?php foreach ($families as $f): ?>
                            <option value="<?= htmlspecialchars($f['family']) ?>" 
                                <?= $family === $f['family'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($f['family']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-sort"></i> Sort By</label>
                    <select name="sort" class="form-control">
                        <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name (A-Z)</option>
                        <option value="family" <?= $sort === 'family' ? 'selected' : '' ?>>Family</option>
                        <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Recently Added</option>
                    </select>
                </div>
                
                <div class="form-group" style="display: flex; align-items: flex-end; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <a href="list.php" class="btn btn-secondary" onclick="event.preventDefault(); window.location='list.php';">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Results Summary -->
<div class="card">
    <div class="card-header">
        <h3>
            <i class="fas fa-pills"></i> 
            Remedies Found: <?= $totalRemedies ?>
            <?php if ($search || $family || $book): ?>
                <small class="text-muted">(filtered)</small>
            <?php endif; ?>
            <?php if ($totalPages > 1): ?>
                <small class="text-muted">| Page <?= $page ?> of <?= $totalPages ?></small>
            <?php endif; ?>
        </h3>
    </div>
    <div class="card-body">
        <?php if (empty($remedies)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>No Remedies Found</h3>
                <p>Try adjusting your search criteria</p>
                <a href="list.php" class="btn btn-primary">View All Remedies</a>
            </div>
        <?php else: ?>
            <div class="remedy-grid">
                <?php foreach ($remedies as $remedy): ?>
                    <div class="remedy-card">
                        <div class="remedy-header">
                            <h3>
                                <a href="<?= APP_URL ?>/materia-medica/view.php?id=<?= $remedy['id'] ?>">
                                    <?= htmlspecialchars($remedy['remedy_name']) ?>
                                </a>
                            </h3>
                            <?php if (!empty($remedy['family'])): ?>
                                <span class="badge badge-primary"><?= htmlspecialchars($remedy['family']) ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($remedy['latin_name'])): ?>
                            <div class="remedy-latin">
                                <i class="fas fa-leaf"></i>
                                <em><?= htmlspecialchars($remedy['latin_name']) ?></em>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($remedy['common_name'])): ?>
                            <div class="remedy-common">
                                <strong>Common:</strong> <?= htmlspecialchars($remedy['common_name']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($remedy['book_reference'])): ?>
                            <div class="remedy-reference">
                                <i class="fas fa-book"></i>
                                <small><?= htmlspecialchars($remedy['book_reference']) ?></small>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($remedy['keynote_symptoms'])): ?>
                            <div class="remedy-keynote">
                                <strong><i class="fas fa-key"></i> Keynote:</strong>
                                <p><?= nl2br(htmlspecialchars(substr($remedy['keynote_symptoms'], 0, 150))) ?>
                                <?= strlen($remedy['keynote_symptoms']) > 150 ? '...' : '' ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="remedy-footer">
                            <a href="<?= APP_URL ?>/materia-medica/view.php?id=<?= $remedy['id'] ?>" 
                               class="btn btn-primary btn-sm">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                            <button class="btn btn-secondary btn-sm" onclick="quickView(<?= $remedy['id'] ?>)">
                                <i class="fas fa-info-circle"></i> Quick Info
                            </button>
                            <!-- OoRep.com button removed -->
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <!-- Pagination -->
            <nav class="pagination-nav">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li>
                            <a href="?<?= http_build_query(array_merge($paginationParams, ['page' => 1])) ?>" class="pagination-link" title="First">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        </li>
                        <li>
                            <a href="?<?= http_build_query(array_merge($paginationParams, ['page' => $page - 1])) ?>" class="pagination-link" title="Previous">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    // Calculate range of pages to show
                    $range = 2;
                    $startPage = max(1, $page - $range);
                    $endPage = min($totalPages, $page + $range);
                    
                    if ($startPage > 1): ?>
                        <li><a href="?<?= http_build_query(array_merge($paginationParams, ['page' => 1])) ?>" class="pagination-link">1</a></li>
                        <?php if ($startPage > 2): ?><li class="pagination-ellipsis">...</li><?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <li>
                            <a href="?<?= http_build_query(array_merge($paginationParams, ['page' => $i])) ?>" 
                               class="pagination-link <?= $i === $page ? 'active' : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($endPage < $totalPages): ?>
                        <?php if ($endPage < $totalPages - 1): ?><li class="pagination-ellipsis">...</li><?php endif; ?>
                        <li><a href="?<?= http_build_query(array_merge($paginationParams, ['page' => $totalPages])) ?>" class="pagination-link"><?= $totalPages ?></a></li>
                    <?php endif; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <li>
                            <a href="?<?= http_build_query(array_merge($paginationParams, ['page' => $page + 1])) ?>" class="pagination-link" title="Next">
                                <i class="fas fa-angle-right"></i>
                            </a>
                        </li>
                        <li>
                            <a href="?<?= http_build_query(array_merge($paginationParams, ['page' => $totalPages])) ?>" class="pagination-link" title="Last">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
                
                <div class="pagination-info">
                    Showing <?= (($page - 1) * $perPage) + 1 ?> - <?= min($page * $perPage, $totalRemedies) ?> of <?= $totalRemedies ?> remedies
                </div>
            </nav>
            <?php endif; ?>
            
        <?php endif; ?>
    </div>
</div>

<!-- Quick View Modal -->
<div id="quickViewModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Remedy Information</h2>
            <button class="close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Loading...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">Close</button>
            <a id="viewFullBtn" href="#" class="btn btn-primary">View Full Details</a>
        </div>
    </div>
</div>

<style>
/* Remedy Grid */
.remedy-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.remedy-card {
    background: white;
    border: 2px solid var(--gray-200);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s ease;
    position: relative;
}

.remedy-card:hover {
    border-color: var(--primary-color);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.15);
    transform: translateY(-3px);
}

.remedy-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    gap: 10px;
}

.remedy-header h3 {
    margin: 0;
    font-size: 20px;
    flex: 1;
}

.remedy-header h3 a {
    color: var(--primary-color);
    text-decoration: none;
    transition: color 0.3s;
}

.remedy-header h3 a:hover {
    color: var(--secondary-color);
}

.remedy-latin {
    color: var(--success-color);
    font-size: 14px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.remedy-common {
    color: var(--gray-600);
    font-size: 13px;
    margin-bottom: 12px;
}

.remedy-keynote {
    background: var(--gray-50);
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 15px;
    border-left: 3px solid var(--primary-color);
}

.remedy-keynote strong {
    display: block;
    color: var(--primary-color);
    margin-bottom: 8px;
    font-size: 13px;
}

.remedy-keynote p {
    margin: 0;
    font-size: 13px;
    line-height: 1.6;
    color: var(--gray-700);
}

.remedy-footer {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--gray-200);
}

.remedy-footer .btn {
    flex: 1;
}

/* Search Filter Form */
.search-filter-form .form-row {
    display: flex;
    gap: 15px;
    align-items: flex-end;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: var(--gray-500);
}

.empty-state i {
    font-size: 64px;
    margin-bottom: 20px;
    color: var(--gray-400);
}

.empty-state h3 {
    color: var(--gray-700);
    margin-bottom: 10px;
}

.empty-state p {
    margin-bottom: 20px;
}

/* Modal Styles */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal-content {
    background: white;
    border-radius: 15px;
    max-width: 700px;
    width: 100%;
    max-height: 80vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: 20px;
    border-bottom: 2px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 22px;
    color: var(--primary-color);
}

.modal-header .close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: var(--gray-500);
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 5px;
}

.modal-header .close:hover {
    background: var(--gray-100);
    color: var(--danger-color);
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    padding: 15px 20px;
    border-top: 2px solid var(--gray-200);
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Loading Spinner */
.loading-spinner {
    text-align: center;
    padding: 40px;
}

.loading-spinner i {
    font-size: 48px;
    color: var(--primary-color);
    margin-bottom: 15px;
}

/* Responsive */
@media (max-width: 767.98px) {
    .remedy-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .search-filter-form .form-row {
        flex-direction: column;
    }
    
    .search-filter-form .form-group {
        width: 100%;
    }
    
    .remedy-footer {
        flex-direction: column;
    }
    
    .modal-content {
        max-width: 100%;
        max-height: 90vh;
        margin: 10px;
    }
}

/* Pagination Styles */
.pagination-nav {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid var(--gray-200);
}

.pagination {
    display: flex;
    list-style: none;
    padding: 0;
    margin: 0;
    gap: 5px;
    flex-wrap: wrap;
    justify-content: center;
}

.pagination li {
    display: flex;
}

.pagination-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0 12px;
    border: 2px solid var(--gray-300);
    border-radius: 8px;
    background: white;
    color: var(--gray-700);
    text-decoration: none;
    font-weight: 500;
    transition: all 0.2s ease;
}

.pagination-link:hover {
    border-color: var(--primary-color);
    color: var(--primary-color);
    background: var(--primary-light, #f0f4ff);
}

.pagination-link.active {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}

.pagination-ellipsis {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    color: var(--gray-500);
}

.pagination-info {
    font-size: 14px;
    color: var(--gray-600);
}

@media (max-width: 767.98px) {
    .pagination-link {
        min-width: 36px;
        height: 36px;
        padding: 0 8px;
        font-size: 14px;
    }
    
    .pagination-info {
        text-align: center;
        font-size: 13px;
    }
}

@media print {
    .page-actions,
    .search-filter-form,
    .remedy-footer,
    .pagination-nav,
    .no-print {
        display: none !important;
    }
    
    .remedy-grid {
        display: block;
    }
    
    .remedy-card {
        page-break-inside: avoid;
        margin-bottom: 20px;
        border: 1px solid #333;
    }
}
</style>

<script>

// Quick View Modal (unchanged)
function quickView(remedyId) {
    const modal = document.getElementById('quickViewModal');
    const modalBody = document.getElementById('modalBody');
    const modalTitle = document.getElementById('modalTitle');
    const viewFullBtn = document.getElementById('viewFullBtn');
    modal.style.display = 'flex';
    modalBody.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>';
    fetch('<?= APP_URL ?>/api/get_remedy.php?id=' + remedyId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const remedy = data.remedy;
                modalTitle.textContent = remedy.remedy_name;
                viewFullBtn.href = '<?= APP_URL ?>/materia-medica/view.php?id=' + remedyId;
                let html = '<div class="remedy-details">';
                if (remedy.latin_name) {
                    html += `<p><strong><i class="fas fa-leaf"></i> Latin Name:</strong> <em>${remedy.latin_name}</em></p>`;
                }
                if (remedy.common_name) {
                    html += `<p><strong>Common Name:</strong> ${remedy.common_name}</p>`;
                }
                if (remedy.family) {
                    html += `<p><strong>Family:</strong> <span class="badge badge-primary">${remedy.family}</span></p>`;
                }
                if (remedy.keynote_symptoms) {
                    html += `<div style="margin-top: 15px;"><strong><i class="fas fa-key"></i> Keynote Symptoms:</strong><p style="margin-top: 8px; line-height: 1.6;">${remedy.keynote_symptoms.replace(/\n/g, '<br>')}</p></div>`;
                }
                html += '</div>';
                modalBody.innerHTML = html;
            } else {
                modalBody.innerHTML = '<p class="text-danger">Failed to load remedy information.</p>';
            }
        })
        .catch(error => {
            modalBody.innerHTML = '<p class="text-danger">Error loading remedy information.</p>';
        });
}
function closeModal() {
    document.getElementById('quickViewModal').style.display = 'none';
}
document.getElementById('quickViewModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
function exportToCSV() {
    const remedies = <?= json_encode($remedies) ?>;
    if (remedies.length === 0) {
        alert('No remedies to export');
        return;
    }
    let csv = 'Remedy Name,Latin Name,Common Name,Family,Keynote Symptoms\n';
    remedies.forEach(remedy => {
        csv += `"${remedy.remedy_name}",`;
        csv += `"${remedy.latin_name || ''}",`;
        csv += `"${remedy.common_name || ''}",`;
        csv += `"${remedy.family || ''}",`;
        csv += `"${(remedy.keynote_symptoms || '').replace(/"/g, '""')}"\n`;
    });
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'materia-medica-' + new Date().toISOString().split('T')[0] + '.csv';
    a.click();
    window.URL.revokeObjectURL(url);
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// LIVE SEARCH: Update remedies as user types
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    const familySelect = document.querySelector('select[name="family"]');
    const sortSelect = document.querySelector('select[name="sort"]');
    const remedyGrid = document.querySelector('.remedy-grid');
    const resultsSummary = document.querySelector('.card-header h3');

    let lastQuery = '';
    function fetchRemedies() {
        const search = searchInput.value;
        const family = familySelect.value;
        const sort = sortSelect.value;
        lastQuery = search;
        remedyGrid.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i><p>Searching...</p></div>';
        fetch(`<?= APP_URL ?>/api/get_remedy.php?search=${encodeURIComponent(search)}&family=${encodeURIComponent(family)}&sort=${encodeURIComponent(sort)}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && Array.isArray(data.remedies)) {
                    resultsSummary.innerHTML = `<i class="fas fa-pills"></i> Remedies Found: ${data.remedies.length} ${(search || family) ? '<small class=\"text-muted\">(filtered)</small>' : ''}`;
                    if (data.remedies.length === 0) {
                        remedyGrid.innerHTML = `<div class=\"empty-state\"><i class=\"fas fa-search\"></i><h3>No Remedies Found</h3><p>Try adjusting your search criteria</p></div>`;
                    } else {
                        remedyGrid.innerHTML = data.remedies.map(remedy => `
                            <div class=\"remedy-card\">
                                <div class=\"remedy-header\">
                                    <h3><a href=\"<?= APP_URL ?>/materia-medica/view.php?id=${remedy.id}\">${remedy.remedy_name}</a></h3>
                                    ${remedy.family ? `<span class=\"badge badge-primary\">${remedy.family}</span>` : ''}
                                </div>
                                ${remedy.latin_name ? `<div class=\"remedy-latin\"><i class=\"fas fa-leaf\"></i> <em>${remedy.latin_name}</em></div>` : ''}
                                ${remedy.common_name ? `<div class=\"remedy-common\"><strong>Common:</strong> ${remedy.common_name}</div>` : ''}
                                ${remedy.keynote_symptoms ? `<div class=\"remedy-keynote\"><strong><i class=\"fas fa-key\"></i> Keynote:</strong><p>${remedy.keynote_symptoms.substring(0,150)}${remedy.keynote_symptoms.length>150?'...':''}</p></div>` : ''}
                                <div class=\"remedy-footer\">
                                    <a href=\"<?= APP_URL ?>/materia-medica/view.php?id=${remedy.id}\" class=\"btn btn-primary btn-sm\"><i class=\"fas fa-eye\"></i> View Details</a>
                                    <button class=\"btn btn-secondary btn-sm\" onclick=\"quickView(${remedy.id})\"><i class=\"fas fa-info-circle\"></i> Quick Info</button>
                                </div>
                            </div>
                        `).join('');
                    }
                } else {
                    remedyGrid.innerHTML = `<div class=\"empty-state\"><i class=\"fas fa-search\"></i><h3>No Remedies Found</h3><p>Error loading remedies</p></div>`;
                }
            })
            .catch(() => {
                remedyGrid.innerHTML = `<div class=\"empty-state\"><i class=\"fas fa-search\"></i><h3>No Remedies Found</h3><p>Error loading remedies</p></div>`;
            });
    }
    searchInput.addEventListener('input', function() {
        fetchRemedies();
    });
    familySelect.addEventListener('change', function() {
        fetchRemedies();
    });
    sortSelect.addEventListener('change', function() {
        fetchRemedies();
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
