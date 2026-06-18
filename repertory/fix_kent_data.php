<?php
/**
 * Comprehensive Kent-fidelity cleanup for the repertory.
 *
 * Three safe, reversible transformations:
 *
 *   A. Normalise mixed-case category values ('Head' -> 'head', 'Eye' -> 'eye').
 *      Verified beforehand that there are zero collisions.
 *
 *   B. Strip the chapter-name prefix from the `rubric` column where it was
 *      duplicated by a flat-CSV import (e.g. "Vertigo morning" -> "morning",
 *      "Head pain forehead" -> "pain forehead",
 *      "Stomach, appetite, increased" -> "appetite, increased").
 *
 *   C. Merge "monster" rows whose rubric column captured a remedy-list dump
 *      (e.g. "CAPRICIOUSNESS: Acon., agar., ..."). For each, extract the
 *      headword before the colon and merge into the existing clean row
 *      (same chapter, rubric=<headword>, same sub_category).
 *
 * Each step runs in dry-run unless --apply is passed. Always wrapped in a
 * single transaction so it can be rolled back atomically on error.
 *
 * Usage:
 *   php repertory/fix_kent_data.php           # dry-run
 *   php repertory/fix_kent_data.php --apply
 */

define('APP_ACCESS', true);
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../includes/database.php';

$apply = in_array('--apply', $argv ?? [], true);
$pdo   = Database::getInstance()->getConnection();

echo "Kent-fidelity repertory cleanup — " . ($apply ? "APPLY" : "DRY-RUN") . "\n\n";

// Reusable mapping-move statement (same pattern used by cleanup_chapters.php)
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

if ($apply) $pdo->beginTransaction();

try {
    /* ----------------------------------------------------------------- */
    /* STEP A: lowercase mixed-case category values                       */
    /* ----------------------------------------------------------------- */
    $stmt = $pdo->query("SELECT DISTINCT category FROM repertory WHERE BINARY category <> LOWER(category)");
    $mixed = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $stepA = 0;
    foreach ($mixed as $cat) {
        $c = $pdo->prepare("SELECT COUNT(*) FROM repertory WHERE category = BINARY ?");
        $c->execute([$cat]);
        $n = (int)$c->fetchColumn();
        if ($apply) {
            $u = $pdo->prepare("UPDATE repertory SET category = LOWER(category) WHERE category = BINARY ?");
            $u->execute([$cat]);
        }
        echo "  [A] '$cat' -> '" . strtolower($cat) . "' : $n rows\n";
        $stepA += $n;
    }
    echo "  -> Step A total: $stepA rows\n\n";

    /* ----------------------------------------------------------------- */
    /* STEP B: strip chapter-name prefix from rubric column               */
    /* ----------------------------------------------------------------- */
    $cats = $pdo->query("SELECT DISTINCT LOWER(category) c FROM repertory")->fetchAll(PDO::FETCH_COLUMN);
    $stepB = 0;
    foreach ($cats as $cat) {
        $title = ucfirst($cat);
        // Match rubric beginning with the chapter name followed by space or comma
        $sel = $pdo->prepare("
            SELECT id, rubric FROM repertory
            WHERE LOWER(category) = ?
              AND (LOWER(rubric) LIKE CONCAT(?, ' %') OR LOWER(rubric) LIKE CONCAT(?, ',%'))
        ");
        $sel->execute([$cat, $cat, $cat]);
        $rows = $sel->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) continue;

        $upd = $pdo->prepare("UPDATE repertory SET rubric = ? WHERE id = ?");
        $catCount = 0;
        foreach ($rows as $r) {
            $orig = $r['rubric'];
            // Strip "Chapter " or "Chapter, " prefix (case-insensitive)
            $new = preg_replace(
                '/^' . preg_quote($cat, '/') . '[\s,]+/i',
                '',
                $orig
            );
            if ($new === '' || $new === $orig) continue;
            // Capitalise first letter to match repertorial style
            $new = mb_strtoupper(mb_substr($new, 0, 1)) . mb_substr($new, 1);
            if ($apply) $upd->execute([$new, (int)$r['id']]);
            $catCount++;
        }
        if ($catCount) echo "  [B] $cat: $catCount rubrics stripped\n";
        $stepB += $catCount;
    }
    echo "  -> Step B total: $stepB rows\n\n";

    /* ----------------------------------------------------------------- */
    /* STEP C: merge "monster" rows into their clean counterparts         */
    /* ----------------------------------------------------------------- */
    // Pattern: rubric like "WORD: Acon., agar., ..." — headword is everything
    // before the first colon, provided it's UPPERCASE-ish (Kent main rubric)
    // and the tail contains at least one comma-separated remedy abbreviation.
    $stmt = $pdo->query("
        SELECT id, category, rubric, sub_category
        FROM repertory
        WHERE rubric REGEXP ':[[:space:]]+[A-Za-z]+[.,]'
    ");
    $monsters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stepC_merged = 0; $stepC_unmatched = 0; $stepC_maps = 0;
    $findClean = $pdo->prepare("
        SELECT id FROM repertory
        WHERE LOWER(category) = LOWER(?)
          AND LOWER(TRIM(rubric)) = LOWER(?)
          AND LOWER(TRIM(IFNULL(sub_category,''))) = LOWER(?)
          AND id <> ?
        ORDER BY id ASC LIMIT 1
    ");
    foreach ($monsters as $row) {
        $rubric = (string)$row['rubric'];
        if (!preg_match('/^\s*([^:]{2,80}?)\s*:/', $rubric, $m)) continue;
        $headword = trim($m[1]);
        $sub = strtolower(trim((string)$row['sub_category']));

        $findClean->execute([$row['category'], $headword, $sub, (int)$row['id']]);
        $cleanId = $findClean->fetchColumn();
        if (!$cleanId) { $stepC_unmatched++; continue; }

        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM repertory_remedies WHERE repertory_id=" . (int)$row['id'])->fetchColumn();
        $stepC_maps += $cnt;
        if ($apply) {
            $stmtMoveMap->execute([(int)$cleanId, (int)$row['id']]);
            $stmtDelMaps->execute([(int)$row['id']]);
            $stmtDelRub->execute([(int)$row['id']]);
        }
        $stepC_merged++;
    }
    echo "  [C] monsters merged into clean rows : $stepC_merged\n";
    echo "  [C] monster maps merged             : $stepC_maps\n";
    echo "  [C] monsters without clean target   : $stepC_unmatched (kept in place)\n";

    if ($apply) $pdo->commit();
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

echo "\n" . ($apply ? "[COMMITTED]" : "[DRY-RUN]") . "\n";
echo "Next: re-run repertory/cleanup_chapters.php --apply to relabel complete_rubric\n";
echo "      and merge any new (rubric, sub_category) duplicates produced by Step B.\n";
