<?php
/**
 * add_company_ownership.php — InternTrack schema patch (CLI only)
 *
 * Problem: companies a student adds (from the Companies page or the Add
 * Internship form) were stored in one global `companies` table with no
 * owner, so every student saw every other student's companies, and a
 * table-wide UNIQUE KEY on `name` meant two students couldn't even track
 * the same employer under their own accounts.
 *
 * This patch:
 *   1. Adds companies.created_by (NULL = official/global company, visible
 *      to everyone; set = added by that student, visible only to them).
 *   2. Adds a generated companies.owner_bucket column and swaps the old
 *      table-wide unique key on `name` for one scoped per owner
 *      (owner_bucket, name), so duplicate names are still blocked within
 *      the official list and within each student's own companies, but no
 *      longer collide across different students.
 *   3. Links companies.created_by to users(id) with ON DELETE SET NULL.
 *
 * Idempotent — safe to run more than once; every step checks information_schema
 * first. This is the script to run against an EXISTING database (one that
 * already has data). Fresh installs get the same shape directly from
 * sql/unified_schema.sql.
 *
 * Usage:
 *   php sql/add_company_ownership.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../php/config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('This script may only be run from the command line.');
}

$db = Database::getConnection();
$dbName = DB_NAME;

function columnExists(PDO $db, string $dbName, string $table, string $col): bool {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND COLUMN_NAME = :c");
    $st->execute([':db' => $dbName, ':t' => $table, ':c' => $col]);
    return (int)$st->fetchColumn() > 0;
}

function indexExists(PDO $db, string $dbName, string $table, string $index): bool {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND INDEX_NAME = :i");
    $st->execute([':db' => $dbName, ':t' => $table, ':i' => $index]);
    return (int)$st->fetchColumn() > 0;
}

function fkExists(PDO $db, string $dbName, string $constraint): bool {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = :db AND CONSTRAINT_NAME = :c");
    $st->execute([':db' => $dbName, ':c' => $constraint]);
    return (int)$st->fetchColumn() > 0;
}

echo "[add_company_ownership] target database: {$dbName}\n";

// Step 1 — companies.created_by
if (!columnExists($db, $dbName, 'companies', 'created_by')) {
    $db->exec("ALTER TABLE companies ADD COLUMN created_by INT DEFAULT NULL AFTER status");
    echo "  + added companies.created_by\n";
} else {
    echo "  = companies.created_by already present\n";
}

if (!indexExists($db, $dbName, 'companies', 'idx_created_by')) {
    $db->exec("ALTER TABLE companies ADD INDEX idx_created_by (created_by)");
    echo "  + added index companies.idx_created_by\n";
}

// Step 2 — generated owner_bucket column (NULL created_by -> shared bucket 0)
if (!columnExists($db, $dbName, 'companies', 'owner_bucket')) {
    $db->exec("ALTER TABLE companies ADD COLUMN owner_bucket INT AS (COALESCE(created_by, 0)) STORED AFTER created_by");
    echo "  + added generated column companies.owner_bucket\n";
} else {
    echo "  = companies.owner_bucket already present\n";
}

// Step 3 — drop the old table-wide unique key on name, if it's still there
if (indexExists($db, $dbName, 'companies', 'uk_company_name')) {
    $db->exec("ALTER TABLE companies DROP INDEX uk_company_name");
    echo "  + dropped old table-wide unique key uk_company_name\n";
}

// Step 4 — dedupe within each new bucket before adding the scoped unique key
// (keeps the lowest id of any (owner_bucket, name) collision).
$db->exec("DELETE c FROM companies c
  JOIN companies k ON k.name = c.name
                   AND k.id < c.id
                   AND COALESCE(k.created_by, 0) = COALESCE(c.created_by, 0)");

// Step 5 — scoped unique key: unique per owner (all official/global rows
// share bucket 0, each student gets their own bucket).
if (!indexExists($db, $dbName, 'companies', 'uk_company_owner_name')) {
    try {
        $db->exec("ALTER TABLE companies ADD UNIQUE KEY uk_company_owner_name (owner_bucket, name)");
        echo "  + added scoped unique key uk_company_owner_name (owner_bucket, name)\n";
    } catch (PDOException $e) {
        echo "  ! could not add uk_company_owner_name: {$e->getMessage()}\n";
    }
} else {
    echo "  = uk_company_owner_name already present\n";
}

// Step 6 — FK back to users(id), now that every existing created_by value
// (currently all NULL, since the column is brand new) is guaranteed valid.
if (!fkExists($db, $dbName, 'fk_companies_created_by')) {
    try {
        $db->exec("ALTER TABLE companies ADD CONSTRAINT fk_companies_created_by
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
        echo "  + added FK companies.created_by -> users.id\n";
    } catch (PDOException $e) {
        echo "  ! could not add FK fk_companies_created_by: {$e->getMessage()}\n";
    }
} else {
    echo "  = fk_companies_created_by already present\n";
}

echo "[add_company_ownership] DONE. Existing companies were left as created_by = NULL\n";
echo "  (i.e. treated as official/global — visible to everyone, same as before).\n";
echo "  Only companies added from now on are attributed to the student who added them.\n";
