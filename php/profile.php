<?php
/**
 * Profile API Handler (achievements)
 */
session_start();
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
$user = requireAuth();
$db   = Database::getConnection();
ensureAchievementsTable();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'achievement_add':    addAchievement($user, $db);    break;
    case 'achievement_delete': deleteAchievement($user, $db); break;
    case 'achievement_list':   listAchievements($user, $db);  break;
    case 'profile_get':        profileGet($user, $db);        break;
    case 'profile_save':       profileSave($user, $db);       break;
    case 'document_upload':    documentUpload($user, $db);    break;
    case 'document_delete':    documentDelete($user, $db);    break;
    case 'toggle_2fa':         toggle2FA($user, $db);         break;
    default:                   jsonResponse(false, 'Unknown action: ' . $action);
}

function profileColumns(): array {
    return ['university','faculty','major','gpa','graduation_date','coursework',
            'career_field','portfolio','linkedin','github','languages','location',
            'skills','internship_type','expected_stipend','industries',
            'availability_date','pref_locations'];
}

function profileGet(array $user, PDO $db): void {
    try {
        $cols = implode(',', profileColumns());
        $stmt = $db->prepare("SELECT $cols, notification_prefs, twofa_enabled FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(false, 'Profile not found.');
        foreach ($row as $k => $v) {
            if (is_string($v) && $v === '') $row[$k] = null;
        }
        $row['skills']            = json_decode((string)$row['skills'] ?? '[]', true) ?: [];
        $row['internship_type']   = $row['internship_type'] ? array_values(array_filter(array_map('trim', explode(',', $row['internship_type'])))) : [];
        $row['notification_prefs'] = json_decode((string)$row['notification_prefs'], true) ?: [];
        $docs = $db->prepare("SELECT id, kind, original_name, file_size, uploaded_at FROM profile_documents WHERE student_id = ? ORDER BY uploaded_at DESC");
        $docs->execute([$user['id']]);
        $row['documents'] = $docs->fetchAll();
        jsonResponse(true, '', ['profile' => $row]);
    } catch (Exception $e) {
        error_log('profile_get: ' . $e->getMessage());
        jsonResponse(false, 'Failed to load profile.');
    }
}

function profileSave(array $user, PDO $db): void {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid request token.');

    $fields = [];
    $params = [];
    foreach (profileColumns() as $col) {
        $val = trim((string)($_POST[$col] ?? ''));
        $fields[] = "`$col` = ?";
        $params[] = $val === '' ? null : $val;
    }

    $skills = $_POST['skills'] ?? [];
    if (is_string($skills)) $skills = json_decode($skills, true) ?: [];
    if (is_array($skills)) {
        $skills = array_values(array_filter(array_map('trim', $skills), fn($s) => $s !== ''));
        $fields[] = "`skills` = ?";
        $params[] = json_encode($skills, JSON_UNESCAPED_UNICODE);
    }

    $type = $_POST['internship_type'] ?? [];
    if (is_array($type)) {
        $type = array_values(array_filter(array_map('trim', $type), fn($t) => $t !== ''));
        $fields[] = "`internship_type` = ?";
        $params[] = implode(',', $type);
    }

    $prefs = [
        'email'      => !empty($_POST['notify_email']) ? 1 : 0,
        'interview'  => !empty($_POST['notify_interview']) ? 1 : 0,
        'deadlines'  => !empty($_POST['notify_deadlines']) ? 1 : 0,
        'weekly'     => !empty($_POST['notify_weekly']) ? 1 : 0,
    ];
    $fields[] = "`notification_prefs` = ?";
    $params[] = json_encode($prefs, JSON_UNESCAPED_UNICODE);

    $fields[] = "`updated_at` = CURRENT_TIMESTAMP";
    $params[] = $user['id'];

    try {
        $db->prepare("UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
        logActivity((int)$user['id'], 'profile_update');
        jsonResponse(true, 'Profile saved!');
    } catch (Exception $e) {
        error_log('profile_save: ' . $e->getMessage());
        jsonResponse(false, 'Failed to save profile.');
    }
}

function documentUpload(array $user, PDO $db): void {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid request token.');
    if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        jsonResponse(false, 'No file selected.');
    }

    $file   = $_FILES['file'];
    $kind   = preg_replace('/[^a-z0-9_-]/i', '', (string)($_POST['kind'] ?? 'resume'));
    $ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf','doc','docx','txt','png','jpg','jpeg'];
    if (!in_array($ext, $allowed, true)) jsonResponse(false, 'Unsupported file type.');
    if ($file['size'] > 5 * 1024 * 1024) jsonResponse(false, 'File must be under 5 MB.');

    try {
        $dir = __DIR__ . '/../uploads/profile/' . (int)$user['id'];
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) jsonResponse(false, 'Upload folder unavailable.');
        $filename = uniqid('doc_', true) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) jsonResponse(false, 'Could not store the file.');
        $stmt = $db->prepare("INSERT INTO profile_documents (student_id, kind, filename, original_name, file_size) VALUES (?,?,?,?,?)");
        $stmt->execute([$user['id'], $kind, $filename, $file['name'], $file['size']]);
        $newId = (int)$db->lastInsertId();
        logActivity((int)$user['id'], 'document_upload');
        jsonResponse(true, 'Document uploaded!', ['id' => $newId, 'original_name' => $file['name'], 'kind' => $kind]);
    } catch (Exception $e) {
        error_log('document_upload: ' . $e->getMessage());
        jsonResponse(false, 'Upload failed.');
    }
}

function documentDelete(array $user, PDO $db): void {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid request token.');
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid document.');

    try {
        $sel = $db->prepare("SELECT filename FROM profile_documents WHERE id = ? AND student_id = ?");
        $sel->execute([$id, $user['id']]);
        $row = $sel->fetch();
        if (!$row) jsonResponse(false, 'Document not found.');
        @unlink(__DIR__ . '/../uploads/profile/' . (int)$user['id'] . '/' . $row['filename']);
        $db->prepare("DELETE FROM profile_documents WHERE id = ? AND student_id = ?")->execute([$id, $user['id']]);
        logActivity((int)$user['id'], 'document_delete');
        jsonResponse(true, 'Document removed.');
    } catch (Exception $e) {
        error_log('document_delete: ' . $e->getMessage());
        jsonResponse(false, 'Failed to remove document.');
    }
}

function toggle2FA(array $user, PDO $db): void {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid request token.');
    try {
        $sel = $db->prepare("SELECT twofa_enabled FROM users WHERE id = ?");
        $sel->execute([$user['id']]);
        $current = (int)($sel->fetchColumn() ?: 0);
        $new = $current ? 0 : 1;
        $db->prepare("UPDATE users SET twofa_enabled = ? WHERE id = ?")->execute([$new, $user['id']]);
        if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
            $_SESSION['user']['twofa_enabled'] = $new;
        }
        logActivity((int)$user['id'], 'toggle_2fa');
        jsonResponse(true, $new ? 'Two-factor authentication enabled.' : 'Two-factor authentication disabled.', ['twofa_enabled' => $new]);
    } catch (Exception $e) {
        error_log('toggle_2fa: ' . $e->getMessage());
        jsonResponse(false, 'Failed to update security settings.');
    }
}

function addAchievement(array $user, PDO $db): void {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid request token.');

    $title = trim($_POST['title'] ?? '');
    $date  = trim($_POST['achievement_date'] ?? '');

    if ($title === '') jsonResponse(false, 'Achievement title is required.');
    if (mb_strlen($title) > 255) jsonResponse(false, 'Achievement title is too long.');
    if (mb_strlen($date) > 100) $date = mb_substr($date, 0, 100);

    try {
        $stmt = $db->prepare("INSERT INTO achievements (student_id, title, achievement_date) VALUES (?, ?, ?)");
        $stmt->execute([$user['id'], $title, $date]);
        $id = (int)$db->lastInsertId();
        logActivity((int)$user['id'], 'achievement_add');
        jsonResponse(true, 'Achievement added!', ['id' => $id]);
    } catch (Exception $e) {
        error_log('achievement add: ' . $e->getMessage());
        jsonResponse(false, 'Failed to save achievement. Please try again.');
    }
}

function deleteAchievement(array $user, PDO $db): void {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid request token.');

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid achievement.');

    try {
        $stmt = $db->prepare("DELETE FROM achievements WHERE id = ? AND student_id = ?");
        $stmt->execute([$id, $user['id']]);
        logActivity((int)$user['id'], 'achievement_delete');
        jsonResponse(true, 'Achievement removed.');
    } catch (Exception $e) {
        error_log('achievement delete: ' . $e->getMessage());
        jsonResponse(false, 'Failed to remove achievement.');
    }
}

function listAchievements(array $user, PDO $db): void {
    try {
        $stmt = $db->prepare("SELECT id, title, achievement_date FROM achievements WHERE student_id = ? ORDER BY created_at DESC");
        $stmt->execute([$user['id']]);
        jsonResponse(true, '', ['achievements' => $stmt->fetchAll()]);
    } catch (Exception $e) {
        error_log('achievement list: ' . $e->getMessage());
        jsonResponse(false, 'Failed to load achievements.');
    }
}