<?php
/**
 * Cleanup + label normalisation for repertory chapters.
 *
 * For each chapter listed in $CHAPTERS:
 *   1. Find every (rubric, sub_category) duplicate group (case/whitespace
 *      insensitive). Pick a canonical id (prefer rows whose verified_source
 *      does not start with 'Kent_*_HTML_'; otherwise smallest id). Merge
 *      every other row's repertory_remedies into the canonical id using
 *      ON DUPLICATE KEY UPDATE grade = GREATEST(...). Delete the duplicates.
 *   2. Normalise every surviving row's complete_rubric to the canonical
 *      "Title, rubric, sub_category" format (e.g. "Vertigo, MORNING" or
 *      "Head, PAIN, headache"), replacing the legacy
 *      "CHAPTER - X - Y" / "Chapter - X - Y" / etc. variants.
 *
 * Usage:
 *   php repertory/cleanup_chapters.php             # dry-run, all chapters
 *   php repertory/cleanup_chapters.php --apply     # commit, all chapters
 *   php repertory/cleanup_chapters.php mind nose   # subset
 *   php repertory/cleanup_chapters.php --apply mind nose
 */

define('APP_ACCESS', true);
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/database.php';

$DEFAULT_CHAPTERS = [
    'mind','vertigo','head','eye','vision','ear','hearing','nose','face','mouth',
    'teeth','throat','stomach','abdomen','rectum','stool','bladder','kidneys',
    'urine','urinary','male','female','larynx','respiration','respiratory',
    'cough','expectoration','chest','heart','back','extremities','skin','sleep',
    'perspiration','fever','general','generalities','chill','generals','urethra',
];

$args  = array_slice($argv ?? [], 1);
$apply = false;
$wanted = [];
foreach ($args as $a) {
    if ($a === '--apply') { $apply = true; continue; }
    if ($a !== '') $wanted[] = strtolower($a);
}
if (!$wanted) $wanted = $DEFAULT_CHAPTERS;

$pdo = Database::getInstance()->getConnection();

echo "Repertory chapter cleanup\n";
echo "Mode: " . ($apply ? 'APPLY' : 'DRY-RUN') . "\n";
echo "Chapters: " . implode(', ', $wanted) . "\n\n";

$stmtMoveMap = $pdo->prepare("
    INSERT INTO repertory_remedies
        (repertory_id, remedy_id, grade, is_verified, verified_source, verified_page, verified_at)
    SELECT ?, src.remedy_id, src.grade, src.is_verified, src.verified_source, src.verified_page, src.verified_at
    FROM repertory_remedies src WHERE src.repertory_id = ?
    ON DUPLICATE KEY UPDATE
        grade           = GREATEST(CAST(repertory_remedies.grade AS UNSIGNED), CAST(VALUES(grade) AS UNSIGNED)),
        is_verified     = GREATEST(repertory_remedies.is_verified, VALUES(is_verified)),
        verified_source = COALESCE(NULLIF(repertory_remedies.verified_source,''), VALUES(verified_source)),
        verified_page   = COALESCE(repertory_remedies.verified_page, VALUES(verified_page)),
        verified_at     = COALESCE(repertory_remedies.verified_at,  VALUES(verified_at))
");
$stmtDelMaps = $pdo->prepare("DELETE FROM repertory_remedies WHERE repertory_id = ?");
$stmtDelRub  = $pdo->prepare("DELETE FROM repertory WHERE id = ?");
$stmtUpdRub  = $pdo->prepare("UPDATE repertory SET complete_rubric = ? WHERE id = ?");

/**
 * Convert a category slug into its display title (e.g. 'extremities' →
 * 'Extremities', 'respiratory' → 'Respiratory').
 */
function chapter_title(string $slug): string {
    // Special-cases where the canonical project label differs from ucfirst()
    static $map = [
        'mind' => 'Mind',
    ];
    return $map[$slug] ?? ucfirst($slug);
}

/**
 * Build the canonical complete_rubric value: "Title, rubric, sub_category".
 */
function canonical_label(string $title, string $rubric, string $sub): string {
    $out = $title . ', ' . trim($rubric);
    if (trim($sub) !== '') $out .= ', ' . trim($sub);
    return $out;
}

$grandDel = 0; $grandMerged = 0; $grandRelabel = 0;

if ($apply) $pdo->beginTransaction();

try {
    foreach ($wanted as $cat) {
        $title = chapter_title($cat);

        // ---- Step 1: duplicate (rubric, sub_category) groups ----
        $g = $pdo->prepare("
            SELECT GROUP_CONCAT(id ORDER BY id) ids,
                   GROUP_CONCAT(COALESCE(verified_source,'') ORDER BY id SEPARATOR '||') srcs,
                   COUNT(*) c
            FROM repertory
            WHERE LOWER(category)=?
            GROUP BY LOWER(TRIM(rubric)), LOWER(TRIM(sub_category))
            HAVING c > 1
        ");
        $g->execute([$cat]);
        $groups = $g->fetchAll(PDO::FETCH_ASSOC);

        $catDel = 0; $catMerged = 0;
        foreach ($groups as $grp) {
            $ids  = array_map('intval', explode(',', $grp['ids']));
            $srcs = explode('||', $grp['srcs']);

            $canonical = null;
            foreach ($ids as $i => $id) {
                $isHtmlImport = (strpos((string)($srcs[$i] ?? ''), '_HTML_') !== false);
                if (!$isHtmlImport && ($canonical === null || $id < $canonical)) $canonical = $id;
            }
            if ($canonical === null) $canonical = min($ids);

            foreach ($ids as $id) {
                if ($id === $canonical) continue;
                $cnt = (int)$pdo->query("SELECT COUNT(*) FROM repertory_remedies WHERE repertory_id=$id")->fetchColumn();
                $catMerged += $cnt;
                if ($apply) {
                    $stmtMoveMap->execute([$canonical, $id]);
                    $stmtDelMaps->execute([$id]);
                    $stmtDelRub->execute([$id]);
                }
                $catDel++;
            }
        }

        // ---- Step 2: relabel complete_rubric to canonical form ----
        $r = $pdo->prepare("SELECT id, rubric, sub_category, complete_rubric
                            FROM repertory WHERE LOWER(category)=?");
        $r->execute([$cat]);
        $rows = $r->fetchAll(PDO::FETCH_ASSOC);

        $catRelabel = 0;
        foreach ($rows as $row) {
            $want = canonical_label($title, (string)$row['rubric'], (string)$row['sub_category']);
            if ($want !== (string)$row['complete_rubric']) {
                if ($apply) $stmtUpdRub->execute([$want, (int)$row['id']]);
                $catRelabel++;
            }
        }

        printf("%-14s  dup-removed=%-4d  maps-merged=%-5d  relabeled=%d\n",
            $cat, $catDel, $catMerged, $catRelabel);

        $grandDel += $catDel; $grandMerged += $catMerged; $grandRelabel += $catRelabel;
    }

    if ($apply) $pdo->commit();
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\n--- TOTAL ---\n";
echo "Duplicate rubrics removed : $grandDel\n";
echo "Remedy mappings merged    : $grandMerged\n";
echo "Labels normalised         : $grandRelabel\n";
echo (! $apply ? "\n[DRY-RUN]\n" : "\n[COMMITTED]\n");
