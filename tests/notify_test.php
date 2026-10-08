<?php
require_once __DIR__ . '/../php/config.php';
$db = Database::getConnection();
$db->beginTransaction();
try {
    $uname = 'tester_n_' . uniqid();
    $email = 'tn_' . uniqid() . '@test.local';
    $db->exec("INSERT INTO users (username,email,password_hash,role,full_name) VALUES
        ('$uname', '$email', 'x', 'student', 'T')");
    $uid = (int)$db->lastInsertId();
    notify($uid, 'Hi', 'Hello');
    $unread = getUnreadNotifications($uid);
    assert(count($unread) === 1 && $unread[0]['title'] === 'Hi');
    markNotificationRead((int)$unread[0]['id'], $uid);
    assert(count(getUnreadNotifications($uid)) === 0);
    echo "PASS: in-app notification\n";
    $db->rollBack();
} catch (Throwable $e) {
    $db->rollBack();
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}

// ── Test: email + in-app notification (channel 'both') ──────────────────
$db->beginTransaction();
try {
    $uname = 'tester_e_' . uniqid();
    $email = 'tn_' . uniqid() . '@test.local';
    $db->exec("INSERT INTO users (username,email,password_hash,role,full_name) VALUES
        ('$uname', '$email', 'x', 'student', 'T')");
    $uid = (int)$db->lastInsertId();

    notify($uid, 'Application accepted', 'Your application is now accepted.', 'success', true);

    $n = $db->query("SELECT channel FROM notifications WHERE user_id = {$uid} ORDER BY id DESC LIMIT 1")->fetch();
    assert($n['channel'] === 'both', 'Expected channel "both", got ' . $n['channel']);
    echo "PASS: email + in-app notification\n";
    $db->rollBack();
} catch (Throwable $e) {
    $db->rollBack();
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
