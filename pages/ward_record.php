<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';

$wardName = $_GET['ward'] ?? '';
$wards = dbQuery("SELECT WardName FROM WARD ORDER BY WardName");

$wardData   = null;
$patients   = [];
$careUnits  = [];
$nurses     = [];

if ($wardName) {
    // Get Ward Header Info FIXED: correct columns are DaySisterStaffNo / NightSisterStaffNo
    $rows = dbQuery("
        SELECT w.WardName, w.Specialty,
               w.DaySisterStaffNo, w.NightSisterStaffNo,
               sd.Name AS DaySister,
               sn.Name AS NightSister
        FROM WARD w
        LEFT JOIN STAFF sd ON sd.StaffNo = w.DaySisterStaffNo
        LEFT JOIN STAFF sn ON sn.StaffNo = w.NightSisterStaffNo
        WHERE w.WardName = ?
    ", [$wardName]);
    $wardData = $rows[0] ?? null;

    // Get Patients currently in this Ward
    $patients = dbQuery("
        SELECT p.PatientNo, p.Name, cu.CareUnitNo, p.BedNo,
               sdoc.Name AS Doctor, d.Position,
               scon.Name AS Consultant, p.DateAdmitted
        FROM PATIENT p
        JOIN CARE_UNIT cu ON p.CareUnitNo = cu.CareUnitNo
        JOIN DOCTOR d    ON p.DoctorInCharge = d.StaffNo
        JOIN STAFF sdoc  ON sdoc.StaffNo = d.StaffNo
        LEFT JOIN STAFF scon ON scon.StaffNo = d.ConsultantNo
        WHERE cu.WardName = ? AND p.DateDischarged IS NULL
        ORDER BY cu.CareUnitNo, p.BedNo
    ", [$wardName]);

    // Care units in this ward with their in-charge staff nurse
    $careUnits = dbQuery("
        SELECT cu.CareUnitNo, sn.Name AS InChargeNurse, cu.InChargeStaffNo
        FROM CARE_UNIT cu
        LEFT JOIN STAFF sn ON sn.StaffNo = cu.InChargeStaffNo
        WHERE cu.WardName = ?
        ORDER BY cu.CareUnitNo
    ", [$wardName]);

    // All nurses working in this ward
    $nurses = dbQuery("
        SELECT n.StaffNo, s.Name, n.NurseType, n.CareUnitNo
        FROM NURSE n
        JOIN STAFF s ON s.StaffNo = n.StaffNo
        WHERE n.WardName = ?
        ORDER BY n.NurseType, s.Name
    ", [$wardName]);
}

render_header('Ward Records', 'ward');
?>

<div class="card">
    <div class="card-header"><h2>🏥 Ward Selector</h2></div>
    <div class="card-body">
        <form method="GET" class="search-bar">
            <select name="ward" onchange="this.form.submit()">
                <option value="">— Select a Ward —</option>
                <?php foreach ($wards as $w): ?>
                    <option value="<?= h($w['WardName']) ?>" <?= $wardName === $w['WardName'] ? 'selected' : '' ?>>
                        <?= h($w['WardName']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php if ($wardData): ?>
<div class="card">
    <div class="card-header"><h2>Ward Details: <?= h($wardName) ?></h2></div>
    <div class="card-body">
        <div class="form-grid">
            <div class="form-group"><label>Specialty</label><input readonly value="<?= h($wardData['Specialty']) ?>"></div>
            <div class="form-group"><label>Day Sister</label><input readonly value="<?= h($wardData['DaySister'] ?? 'Not Assigned') ?>"></div>
            <div class="form-group"><label>Night Sister</label><input readonly value="<?= h($wardData['NightSister'] ?? 'Not Assigned') ?>"></div>
            <div class="form-group"><label>Staff Nurses</label><input readonly value="<?= count(array_filter($nurses, fn($n) => $n['NurseType'] === 'Staff Nurse')) ?>"></div>
            <div class="form-group"><label>Non-registered Nurses</label><input readonly value="<?= count(array_filter($nurses, fn($n) => $n['NurseType'] === 'Non-Registered Nurse')) ?>"></div>
        </div>

        <p class="section-title">Care Units &amp; Staff Nurses In-Charge</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Care Unit</th><th>Staff Nurse In Charge</th><th>Staff No</th></tr></thead>
                <tbody>
                <?php if (empty($careUnits)): ?>
                    <tr><td colspan="3" style="padding:12px;color:var(--text-muted)">No care units in this ward.</td></tr>
                <?php else: foreach ($careUnits as $cu): ?>
                <tr>
                    <td>Unit <?= h($cu['CareUnitNo']) ?></td>
                    <td><strong><?= h($cu['InChargeNurse'] ?? 'Not Assigned') ?></strong></td>
                    <td><?= h($cu['InChargeStaffNo']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <p class="section-title">Nurses Working on this Ward</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Staff No</th><th>Name</th><th>Nurse Type</th><th>Assigned Care Unit</th></tr></thead>
                <tbody>
                <?php foreach ($nurses as $n): ?>
                <tr>
                    <td><?= h($n['StaffNo']) ?></td>
                    <td><strong><?= h($n['Name']) ?></strong></td>
                    <td><span class="pill pill-navy"><?= h($n['NurseType']) ?></span></td>
                    <td><?= $n['CareUnitNo'] ? 'Unit ' . h($n['CareUnitNo']) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <p class="section-title">Currently Admitted Patients</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Patient No</th><th>Name</th><th>Unit</th><th>Bed</th><th>Doctor</th><th>Consultant</th><th>Admitted</th></tr></thead>
                <tbody>
                <?php if (empty($patients)): ?>
                    <tr><td colspan="7" style="padding:12px;color:var(--text-muted)">No patients currently admitted.</td></tr>
                <?php else: foreach ($patients as $p): ?>
                <tr>
                    <td><?= h($p['PatientNo']) ?></td>
                    <td><strong><?= h($p['Name']) ?></strong></td>
                    <td>Unit <?= h($p['CareUnitNo']) ?></td>
                    <td>Bed <?= h($p['BedNo']) ?></td>
                    <td><?= h($p['Doctor']) ?> <small style="color:var(--text-muted)">(<?= h($p['Position']) ?>)</small></td>
                    <td><?= h($p['Consultant'] ?? 'N/A') ?></td>
                    <td><?= h($p['DateAdmitted']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php render_footer(); ?>