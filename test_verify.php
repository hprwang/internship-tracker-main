<?php
require_once __DIR__ . '/php/config.php';
$db = Database::getConnection();

foreach ([21] as $uid) {
    $data = studentDashboardData($uid);
    echo "user=$uid\n";
    echo "  total=" . $data['total'] . "\n";
    echo "  byStatus="; print_r($data['byStatus']);
    echo "  myApplications=" . count($data['myApplications']) . "\n";
    echo "  recent=" . count($data['recent']) . "\n";
    echo "  interviews=" . count($data['interviews']) . "\n";

    echo "  --- recent (manual internships) ---\n";
    foreach ($data['recent'] as $r) {
        echo "    id=" . $r['id'] . " title=" . $r['title'] . " status=" . $r['status'] . " company=" . $r['company_name'] . " start=" . $r['start_date'] . "\n";
    }
    echo "  --- myApplications (browse-apply) ---\n";
    foreach ($data['myApplications'] as $a) {
        echo "    id=" . $a['id'] . " status=" . $a['status'] . " applied_at=" . $a['applied_at'] . " title=" . $a['internship_title'] . " company=" . $a['company_name'] . "\n";
    }
    echo "\n";
}

