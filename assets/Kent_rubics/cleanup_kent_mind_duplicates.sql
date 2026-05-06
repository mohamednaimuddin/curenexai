-- Auto-generated. Removes legacy duplicate Mind rubrics that are
-- already covered by Kent_Mind_1-10 / Kent_Mind_1-30 PDF imports.
-- Safe to run on the VPS once. Idempotent.

START TRANSACTION;

DROP TEMPORARY TABLE IF EXISTS _pdf_names;
CREATE TEMPORARY TABLE _pdf_names (
    name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci PRIMARY KEY
) DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO _pdf_names (name) VALUES
  ('ABANDONED'),
  ('ABRUPT'),
  ('ABSENT-MINDED'),
  ('ABSORBED'),
  ('ABUSIVE'),
  ('ACUTENESS'),
  ('AFFECTION'),
  ('AFFECTIONATE'),
  ('AGITATION'),
  ('AMOROUS'),
  ('ANGUISH'),
  ('ANTHROPOPHOBIA'),
  ('ANXIETY'),
  ('APATHY'),
  ('APHASIA'),
  ('APPREHENSIONS'),
  ('ARDENT'),
  ('ARROGANCE'),
  ('ATTENTION'),
  ('AUDACITY'),
  ('AUTOMATIC'),
  ('AVARICE'),
  ('BARKING'),
  ('BASHFUL'),
  ('BELLOWING'),
  ('BEMOANING'),
  ('BENEVOLENCE'),
  ('BENUMBED'),
  ('BEWILDERED'),
  ('BITING'),
  ('BOLDNESS'),
  ('BUFFOONERY'),
  ('CALMNESS'),
  ('CAPRICIOUSNESS'),
  ('CAREFULNESS'),
  ('CARELESS'),
  ('CARPHOLOGIA'),
  ('CAUTIOUS'),
  ('CHAGRIN'),
  ('CHANGEABLE'),
  ('CHAOTIC'),
  ('CLAIRVOYANCE'),
  ('CONFIDING'),
  ('CONTENTED'),
  ('CONTENTIONS'),
  ('COSMOPOLITAN'),
  ('COURAGEOUS'),
  ('COVETOUS'),
  ('COWARDICE'),
  ('CRAZY'),
  ('CRITICAL'),
  ('CROAKING'),
  ('CURSING'),
  ('DANCING'),
  ('DECEITFUL'),
  ('DEFIANT'),
  ('DEJECTION'),
  ('DELIRIUM');

DROP TEMPORARY TABLE IF EXISTS _to_delete;
CREATE TEMPORARY TABLE _to_delete (id INT PRIMARY KEY);
INSERT INTO _to_delete (id)
SELECT DISTINCT r.id
  FROM repertory r
  JOIN _pdf_names p
    ON UPPER(TRIM(r.rubric)) COLLATE utf8mb4_general_ci = p.name
    OR UPPER(TRIM(r.rubric)) COLLATE utf8mb4_general_ci LIKE CONCAT(p.name, ',%')
    OR UPPER(TRIM(r.rubric)) COLLATE utf8mb4_general_ci LIKE CONCAT(p.name, ' %')
 WHERE LOWER(r.category) = 'mind'
   AND ( r.verified_source IS NULL OR r.verified_source NOT LIKE 'Kent\_Mind\_%' );

SELECT COUNT(*) AS rubric_rows_to_delete FROM _to_delete;

DELETE rr FROM repertory_remedies rr
 JOIN _to_delete d ON d.id = rr.repertory_id;

DELETE r FROM repertory r
 JOIN _to_delete d ON d.id = r.id;

COMMIT;
DROP TEMPORARY TABLE IF EXISTS _to_delete;
DROP TEMPORARY TABLE IF EXISTS _pdf_names;
