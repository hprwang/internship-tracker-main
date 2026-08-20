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
    default:                   jsonResponse(false, 'Unknown action: ' . $action);
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