<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/layout.php';

// Quick stats
$stats = [
    ['icon'=>'🧑‍⚕️', 'label'=>'Total Patients',    'sql'=>'SELECT COUNT(*) AS n FROM PATIENT'],
    ['icon'=>'🛌',  'label'=>'Currently Admitted', 'sql'=>'SELECT COUNT(*) AS n FROM PATIENT WHERE DateDischarged IS NULL'],
    ['icon'=>'🩺',  'label'=>'Doctors',             'sql'=>'SELECT COUNT(*) AS n FROM DOCTOR'],
    ['icon'=>'🏅',  'label'=>'Consultants',          'sql'=>'SELECT COUNT(*) AS n FROM CONSULTANT'],
    ['icon'=>'💉',  'label'=>'Nurses',               'sql'=>'SELECT COUNT(*) AS n FROM NURSE'],
    ['icon'=>'🏥',  'label'=>'Wards',                'sql'=>'SELECT COUNT(*) AS n FROM WARD'],
    ['icon'=>'🛏️',  'label'=>'Beds',                 'sql'=>'SELECT COUNT(*) AS n FROM BED'],
    ['icon'=>'📁',  'label'=>'Treatment Records',    'sql'=>'SELECT COUNT(*) AS n FROM MEDICAL_HISTORY'],
];

$statValues = [];
foreach ($stats as $s) {
    $rows = dbQuery($s['sql']);
    $statValues[] = $rows[0]['n'] ?? 0;
}

// Recent patients
$recent = dbQuery("
    SELECT TOP 6 p.PatientNo, p.Name, p.DateAdmitted,
           w.WardName, cu.CareUnitNo,
           s.Name AS Doctor,
           CASE WHEN p.DateDischarged IS NULL THEN 'Admitted' ELSE 'Discharged' END AS Status
    FROM PATIENT p
    JOIN CARE_UNIT cu ON cu.CareUnitNo = p.CareUnitNo
    JOIN WARD w ON w.WardName = cu.WardName
    JOIN DOCTOR d ON d.StaffNo = p.DoctorInCharge
    JOIN STAFF s ON s.StaffNo = d.StaffNo
    ORDER BY p.DateAdmitted DESC
");

render_header('Dashboard', 'home');
?>

<!-- Stats Grid -->
<div class="stats-grid">
<?php foreach ($stats as $i => $s): ?>
    <div class="stat-card">
        <span class="stat-icon"><?= $s['icon'] ?></span>
        <span class="stat-num"><?= $statValues[$i] ?></span>
        <span class="stat-lbl"><?= $s['label'] ?></span>
    </div>
<?php endforeach; ?>
</div>

<!-- Quick Links -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header"><h2>⚡ Quick Actions</h2></div>
    <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap">
        <a href="pages/patient_record.php?action=add" class="btn btn-teal">➕ Admit New Patient</a>
        <a href="pages/ward_record.php"               class="btn btn-primary">🏥 View Ward Records</a>
        <a href="pages/consultant_team.php"           class="btn btn-primary">🏅 Consultant Teams</a>
        <a href="pages/reports.php"                   class="btn btn-outline">📊 All Reports</a>
    </div>
</div>

<!-- Recent Patients -->
<div class="card">
    <div class="card-header">
        <h2>🕒 Recently Admitted Patients</h2>
        <a href="pages/patient_record.php" class="badge" style="text-decoration:none">View All →</a>
    </div>
    <div class="card-body" style="padding:0">
        <div class="table-wrap">
        <?php if (empty($recent)): ?>
            <div class="empty-state"><div class="es-icon">🏥</div><p>No patients found.</p></div>
        <?php else: ?>
        <table>
            <thead><tr>
                <th>#</th><th>Patient Name</th><th>Ward</th>
                <th>Care Unit</th><th>Doctor</th><th>Admitted</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
            <tr>
                <td><?= h($r['PatientNo']) ?></td>
                <td><strong><?= h($r['Name']) ?></strong></td>
                <td><?= h($r['WardName']) ?></td>
                <td>Unit <?= h($r['CareUnitNo']) ?></td>
                <td><?= h($r['Doctor']) ?></td>
                <td><?= h($r['DateAdmitted']) ?></td>
                <td>
                    <span class="pill <?= $r['Status']==='Admitted' ? 'pill-teal' : 'pill-green' ?>">
                        <?= $r['Status'] ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
    </div>
</div>

<?php render_footer(); ?>