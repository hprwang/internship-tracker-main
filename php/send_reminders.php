<?php
/**
 * send_reminders.php — scheduled notifications for InternTrack (CLI only)
 *
 * Honors each student's Settings → Notification Preferences:
 *   interview  → "Interview Reminders"   (the day before an interview)
 *   deadlines  → "Application Deadlines" (applications starting soon)
 *   weekly     → "Weekly Reports"        (7-day progress summary)
 *   email      → "Email Notifications"   (handled inside notify(): in-app
 *                                         alert is always created, the email
 *                                         is only sent when this is ON)
 *
 * Usage:
 *   php php/send_reminders.php             # interview + deadline reminders
 *   php php/send_reminders.php --weekly    # ...plus weekly reports
 *   php php/send_reminders.php --dry-run   # print what WOULD be sent, send nothing
 *
 * Schedule it once a day (see README → "Scheduled reminders").
 * Safe to run more than once a day: duplicates are skipped.
 *
 * NOTE: the schema has no interview date/time or posting-closing-date column.
 * Like the dashboard, this treats an internship's start_date as the relevant
 * date: for status 'interview' it is the interview date, for 'applied' /
 * 'accepted' it is the "deadline" the student is counting down to.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden: run this script from the command line.\n");
}

require_once __DIR__ . '/config.php';

const DEADLINE_WINDOW_DAYS = 3;   // remind when start_date is within this many days

$weekly = in_array('--weekly', $argv ?? [], true);
$dryRun = in_array('--dry-run', $argv ?? [], true);

$db = Database::getConnection();
$prefsCache = [];
$counts = ['interview' => 0, 'deadlines' => 0, 'weekly' => 0, 'skipped_pref' => 0, 'skipped_dupe' => 0];

function prefsFor(PDO $db, int $uid, array &$cache): array {
    return $cache[$uid] ??= getNotificationPrefs($db, $uid);
}

function alreadySent(PDO $db, int $uid, string $title, string $message, int $hours): bool {
    $stmt = $db->prepare(
        "SELECT 1 FROM notifications
         WHERE user_id = ? AND title = ? AND message = ?
           AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR) LIMIT 1"
    );
    $stmt->bindValue(1, $uid, PDO::PARAM_INT);
    $stmt->bindValue(2, $title);
    $stmt->bindValue(3, $message);
    $stmt->bindValue(4, $hours, PDO::PARAM_INT);
    $stmt->execute();
    return (bool)$stmt->fetchColumn();
}

function send(PDO $db, bool $dryRun, array &$counts, string $bucket, int $uid, string $title, string $message, string $type, int $dedupeHours): void {
    $title = function_exists('mb_substr') ? mb_substr($title, 0, 200) : substr($title, 0, 200);
    if (alreadySent($db, $uid, $title, $message, $dedupeHours)) {
        $counts['skipped_dupe']++;
        return;
    }
    echo ($dryRun ? '[dry-run] ' : '') . "user #$uid — $title\n";
    if (!$dryRun) {
        notify($uid, $title, $message, $type, true); // email only goes out if user's Email pref is ON
    }
    $counts[$bucket]++;
}

// ── 1) Interview reminders: interviews happening tomorrow ───────────────────
$stmt = $db->query(
    "SELECT i.id, i.student_id, i.title, i.start_date, c.name AS company
     FROM internships i
     JOIN companies c ON c.id = i.company_id
     JOIN users u ON u.id = i.student_id AND u.is_active = 1
     WHERE i.status = 'interview'
       AND i.start_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
);
foreach ($stmt->fetchAll() as $r) {
    $uid = (int)$r['student_id'];
    if (empty(prefsFor($db, $uid, $prefsCache)['interview'])) { $counts['skipped_pref']++; continue; }
    send($db, $dryRun, $counts, 'interview', $uid,
        "Interview tomorrow: {$r['title']}",
        "Reminder: your interview for \"{$r['title']}\" at {$r['company']} is scheduled for {$r['start_date']}.",
        'warning', 20);
}

// ── 2) Application deadlines: applications starting within the window ───────
$stmt = $db->prepare(
    "SELECT i.id, i.student_id, i.title, i.start_date, i.status, c.name AS company,
            DATEDIFF(i.start_date, CURDATE()) AS days_left
     FROM internships i
     JOIN companies c ON c.id = i.company_id
     JOIN users u ON u.id = i.student_id AND u.is_active = 1
     WHERE i.status IN ('applied','accepted')
       AND i.start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)"
);
$stmt->bindValue(1, DEADLINE_WINDOW_DAYS, PDO::PARAM_INT);
$stmt->execute();
foreach ($stmt->fetchAll() as $r) {
    $uid = (int)$r['student_id'];
    if (empty(prefsFor($db, $uid, $prefsCache)['deadlines'])) { $counts['skipped_pref']++; continue; }
    $d = (int)$r['days_left'];
    $when = $d === 0 ? 'today' : ($d === 1 ? 'tomorrow' : "in $d days");
    send($db, $dryRun, $counts, 'deadlines', $uid,
        "Deadline approaching: {$r['title']}",
        "\"{$r['title']}\" at {$r['company']} ({$r['status']}) is due {$when} ({$r['start_date']}).",
        'warning', 20);
}

// ── 3) Weekly reports (only with --weekly) ──────────────────────────────────
if ($weekly) {
    $students = $db->query("SELECT id, full_name FROM users WHERE role = 'student' AND is_active = 1")->fetchAll();
    foreach ($students as $s) {
        $uid = (int)$s['id'];
        if (empty(prefsFor($db, $uid, $prefsCache)['weekly'])) { $counts['skipped_pref']++; continue; }

        $logs = $db->prepare(
            "SELECT COUNT(*) AS n, COALESCE(SUM(p.hours_worked), 0) AS hrs
             FROM progress_logs p JOIN internships i ON i.id = p.internship_id
             WHERE i.student_id = ? AND p.log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
        );
        $logs->execute([$uid]);
        $l = $logs->fetch();

        $st = $db->prepare("SELECT status, COUNT(*) AS n FROM internships WHERE student_id = ? GROUP BY status");
        $st->execute([$uid]);
        $parts = [];
        foreach ($st->fetchAll() as $row) $parts[] = "{$row['n']} {$row['status']}";

        $message = sprintf(
            'Last 7 days: %d progress log%s (%s hours). Your internships: %s.',
            (int)$l['n'], (int)$l['n'] === 1 ? '' : 's', rtrim(rtrim(number_format((float)$l['hrs'], 1), '0'), '.') ?: '0',
            $parts ? implode(', ', $parts) : 'none tracked yet'
        );
        send($db, $dryRun, $counts, 'weekly', $uid, 'Your weekly progress summary', $message, 'info', 144);
    }
}

echo sprintf(
    "Done%s. interview=%d deadlines=%d weekly=%d | skipped (pref off)=%d, skipped (already sent)=%d\n",
    $dryRun ? ' (dry run)' : '',
    $counts['interview'], $counts['deadlines'], $counts['weekly'], $counts['skipped_pref'], $counts['skipped_dupe']
);
