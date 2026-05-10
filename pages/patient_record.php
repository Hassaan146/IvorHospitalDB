<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';

$action  = $_GET['action']  ?? 'list';
$pid     = (int)($_GET['id'] ?? 0);
$message = '';
$msgType = 'success';

// HANDLE FORM SUBMISSIONS 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $a = $_POST['form_action'] ?? '';

    // ADMIT NEW PATIENT
    if ($a === 'admit') {
        $sql = "INSERT INTO PATIENT
                    (PatientNo, Name, DateOfBirth, DateAdmitted, CareUnitNo, BedNo, DoctorInCharge)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $params = [
            [(int)$_POST['PatientNo']],
            [$_POST['Name']],
            [$_POST['DateOfBirth']],
            [$_POST['DateAdmitted']],
            [(int)$_POST['CareUnitNo']],
            [(int)$_POST['BedNo']],
            [(int)$_POST['DoctorInCharge']],
        ];
        // sqlsrv params are flat arrays
        $flatParams = array_map(fn($p) => $p[0], $params);
        $ok = dbExecute($sql, $flatParams);
        $message = $ok ? '✅ Patient admitted successfully.' : '❌ Error admitting patient. PatientNo may already exist.';
        $msgType  = $ok ? 'success' : 'danger';
        $action   = $ok ? 'list' : 'add';
    }

    // DISCHARGE PATIENT
    if ($a === 'discharge') {
        $sql = "UPDATE PATIENT SET DateDischarged = ? WHERE PatientNo = ?";
        $ok  = dbExecute($sql, [$_POST['DateDischarged'], (int)$_POST['PatientNo']]);
        $message = $ok ? '✅ Patient discharged successfully.' : '❌ Error discharging patient.';
        $msgType  = $ok ? 'success' : 'danger';
        $action   = 'list';
    }

    // ADD MEDICAL HISTORY
    if ($a === 'add_history') {
        $sql = "INSERT INTO MEDICAL_HISTORY
                    (PatientNo, ComplaintCode, TreatmentCode, DoctorNo, DateStarted)
                VALUES (?, ?, ?, ?, ?)";
        $ok  = dbExecute($sql, [
            (int)$_POST['PatientNo'],
            (int)$_POST['ComplaintCode'],
            (int)$_POST['TreatmentCode'],
            (int)$_POST['DoctorNo'],
            $_POST['DateStarted'],
        ]);
        $message = $ok ? '✅ Medical history entry added.' : '❌ Error adding history.';
        $msgType  = $ok ? 'success' : 'danger';
        $pid      = (int)$_POST['PatientNo'];
        $action   = 'view';
    }
}

// DROPDOWN DATA 
$careUnits  = dbQuery("SELECT cu.CareUnitNo, cu.WardName FROM CARE_UNIT cu ORDER BY cu.WardName, cu.CareUnitNo");
$beds       = dbQuery("SELECT BedNo, WardName FROM BED ORDER BY WardName, BedNo");
$doctors    = dbQuery("SELECT d.StaffNo, s.Name, d.Position FROM DOCTOR d JOIN STAFF s ON s.StaffNo=d.StaffNo ORDER BY s.Name");
$complaints = dbQuery("SELECT ComplaintCode, Description FROM COMPLAINT ORDER BY Description");
$treatments = dbQuery("SELECT TreatmentCode, Description FROM TREATMENT ORDER BY Description");

// LIST: all patients 
$search  = trim($_GET['search'] ?? '');
$whereSql = $search ? "WHERE p.Name LIKE ? OR CAST(p.PatientNo AS VARCHAR) LIKE ?" : '';
$searchParam = $search ? ["%$search%", "%$search%"] : [];

$patients = dbQuery("
    SELECT p.PatientNo, p.Name, p.DateOfBirth, p.DateAdmitted, p.DateDischarged,
           w.WardName, cu.CareUnitNo, p.BedNo,
           s.Name AS Doctor, d.Position
    FROM PATIENT p
    JOIN CARE_UNIT cu ON cu.CareUnitNo = p.CareUnitNo
    JOIN WARD w ON w.WardName = cu.WardName
    JOIN DOCTOR d ON d.StaffNo = p.DoctorInCharge
    JOIN STAFF s ON s.StaffNo = d.StaffNo
    $whereSql
    ORDER BY p.DateAdmitted DESC
", $searchParam);

// VIEW SINGLE PATIENT 
$patient = null;
$medHistory = [];
if ($action === 'view' && $pid) {
$rows = dbQuery("
    SELECT
        p.PatientNo      AS PatientNo,
        p.Name           AS Name,
        p.DateOfBirth    AS DateOfBirth,
        p.DateAdmitted   AS DateAdmitted,
        p.DateDischarged AS DateDischarged,
        p.DoctorInCharge AS DoctorInCharge,
        p.BedNo          AS BedNo,
        w.WardName       AS WardName,
        cu.CareUnitNo    AS CareUnitNo,
        s.Name           AS DoctorName,
        d.Position       AS DoctorPos,
        sc.Name          AS ConsultantName,
        con.Specialty    AS ConsultantSpec
    FROM PATIENT p
    JOIN CARE_UNIT cu  ON cu.CareUnitNo = p.CareUnitNo
    JOIN WARD w        ON w.WardName    = cu.WardName
    JOIN DOCTOR d      ON d.StaffNo     = p.DoctorInCharge
    JOIN STAFF s       ON s.StaffNo     = d.StaffNo
    LEFT JOIN CONSULTANT con ON con.StaffNo = d.ConsultantNo
    LEFT JOIN STAFF sc       ON sc.StaffNo  = d.ConsultantNo
    WHERE p.PatientNo = ?
", [$pid]);
    $patient = $rows[0] ?? null;

    $medHistory = dbQuery("
        SELECT mh.HistoryID, c.Description AS Complaint, t.Description AS Treatment,
               s.Name AS Doctor, mh.DateStarted, mh.DateEnded
        FROM MEDICAL_HISTORY mh
        JOIN COMPLAINT c ON c.ComplaintCode = mh.ComplaintCode
        JOIN TREATMENT t ON t.TreatmentCode = mh.TreatmentCode
        JOIN STAFF s ON s.StaffNo = mh.DoctorNo
        WHERE mh.PatientNo = ?
        ORDER BY mh.DateStarted DESC
    ", [$pid]);
}

render_header('Patient Record', 'patient');
?>

<?php if ($message): ?>
<div class="alert alert-<?= $msgType ?>"><?= $message ?></div>
<?php endif; ?>

<?php if ($action === 'list'): ?>

<!-- LIST VIEW -->
<div class="card">
    <div class="card-header">
        <h2>🧑‍⚕️ All Patients</h2>
        <a href="?action=add" class="btn btn-teal btn-sm" style="margin-left:auto">➕ Admit Patient</a>
    </div>
    <div class="card-body">
        <form method="GET" class="search-bar">
            <input type="hidden" name="action" value="list">
            <input type="text" name="search" placeholder="Search by name or ID…" value="<?= h($search) ?>">
            <button type="submit" class="btn btn-primary">🔍 Search</button>
            <?php if ($search): ?>
                <a href="?" class="btn btn-outline">✕ Clear</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="table-wrap">
    <?php if (empty($patients)): ?>
        <div class="empty-state"><div class="es-icon">🏥</div><p>No patients found.</p></div>
    <?php else: ?>
    <table>
        <thead><tr>
            <th>ID</th><th>Name</th><th>DOB</th><th>Admitted</th>
            <th>Ward</th><th>Bed</th><th>Doctor</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($patients as $p): ?>
        <tr>
            <td><?= h($p['PatientNo']) ?></td>
            <td><strong><?= h($p['Name']) ?></strong></td>
            <td><?= h($p['DateOfBirth']) ?></td>
            <td><?= h($p['DateAdmitted']) ?></td>
            <td><?= h($p['WardName']) ?></td>
            <td><?= h($p['BedNo']) ?></td>
            <td><?= h($p['Doctor']) ?></td>
            <td>
                <?php if (!$p['DateDischarged']): ?>
                    <span class="pill pill-teal">Admitted</span>
                <?php else: ?>
                    <span class="pill pill-green">Discharged <?= h($p['DateDischarged']) ?></span>
                <?php endif; ?>
            </td>
            <td>
                <a href="?action=view&id=<?= $p['PatientNo'] ?>" class="btn btn-outline btn-sm">👁 View</a>
                <?php if (!$p['DateDischarged']): ?>
                    <a href="?action=discharge&id=<?= $p['PatientNo'] ?>" class="btn btn-sm" style="background:#fff3cd;color:#856404">🏠 Discharge</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
</div>

<?php elseif ($action === 'add'): ?>

<!-- ADMIT FORM -->
<div class="card">
    <div class="card-header"><h2>➕ Admit New Patient</h2></div>
    <div class="card-body">
    <form method="POST">
        <input type="hidden" name="form_action" value="admit">
        <div class="form-grid">
            <div class="form-group">
                <label>Patient No *</label>
                <input type="number" name="PatientNo" required placeholder="e.g. 1009">
            </div>
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="Name" required placeholder="Full name">
            </div>
            <div class="form-group">
                <label>Date of Birth *</label>
                <input type="date" name="DateOfBirth" required>
            </div>
            <div class="form-group">
                <label>Date Admitted *</label>
                <input type="date" name="DateAdmitted" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label>Care Unit *</label>
                <select name="CareUnitNo" required>
                    <option value="">— Select Care Unit —</option>
                    <?php foreach ($careUnits as $cu): ?>
                        <option value="<?= $cu['CareUnitNo'] ?>">Unit <?= h($cu['CareUnitNo']) ?> (<?= h($cu['WardName']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Bed No *</label>
                <select name="BedNo" required>
                    <option value="">— Select Bed —</option>
                    <?php foreach ($beds as $b): ?>
                        <option value="<?= $b['BedNo'] ?>">Bed <?= h($b['BedNo']) ?> (<?= h($b['WardName']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Doctor In Charge *</label>
                <select name="DoctorInCharge" required>
                    <option value="">— Select Doctor —</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?= $doc['StaffNo'] ?>"><?= h($doc['Name']) ?> — <?= h($doc['Position']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-teal">✅ Admit Patient</button>
            <a href="?" class="btn btn-outline">Cancel</a>
        </div>
    </form>
    </div>
</div>

<?php elseif ($action === 'discharge' && $pid): ?>

<!-- DISCHARGE FORM  -->
<?php 
$pRows = dbQuery("
    SELECT PatientNo AS PatientNo, Name AS Name 
    FROM PATIENT 
    WHERE PatientNo = ?
", [$pid]); 
$pRow = $pRows[0] ?? null; 
?>
<div class="card">
    <div class="card-header"><h2>🏠 Discharge Patient</h2></div>
    <div class="card-body">
    <?php if ($pRow): ?>
    <p style="margin-bottom:18px">Discharging: <strong><?= h($pRow['Name'] ?? 'Unknown') ?></strong> (ID: <?= $pid ?>)</p>
    <form method="POST">
        <input type="hidden" name="form_action" value="discharge">
        <input type="hidden" name="PatientNo" value="<?= $pid ?>">
        <div class="form-grid">
            <div class="form-group">
                <label>Discharge Date *</label>
                <input type="date" name="DateDischarged" required value="<?= date('Y-m-d') ?>">
            </div>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Confirm Discharge</button>
            <a href="?" class="btn btn-outline">Cancel</a>
        </div>
    </form>
    <?php else: ?>
        <div class="alert alert-danger">Patient not found.</div>
    <?php endif; ?>
    </div>
</div>

<?php elseif ($action === 'view' && $patient): ?>

<!-- VIEW PATIENT  -->
<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <a href="?" class="btn btn-outline btn-sm">← Back to List</a>
    <?php if (!$patient['DateDischarged']): ?>
        <a href="?action=discharge&id=<?= $pid ?>" class="btn btn-sm" style="background:#fff3cd;color:#856404">🏠 Discharge</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h2>🧑‍⚕️ <?= h($patient['Name']) ?></h2>
        <span class="badge">Patient #<?= $pid ?></span>
    </div>
    <div class="card-body">
        <div class="form-grid">
            <div class="form-group"><label>Full Name</label><input readonly value="<?= h($patient['Name']) ?>"></div>
            <div class="form-group"><label>Date of Birth</label><input readonly value="<?= h($patient['DateOfBirth']) ?>"></div>
            <div class="form-group"><label>Date Admitted</label><input readonly value="<?= h($patient['DateAdmitted']) ?>"></div>
            <div class="form-group"><label>Date Discharged</label><input readonly value="<?= $patient['DateDischarged'] ? h($patient['DateDischarged']) : 'Currently Admitted' ?>"></div>
            <div class="form-group"><label>Ward</label><input readonly value="<?= h($patient['WardName']) ?>"></div>
            <div class="form-group"><label>Care Unit</label><input readonly value="Unit <?= h($patient['CareUnitNo']) ?>"></div>
            <div class="form-group"><label>Bed No</label><input readonly value="Bed <?= h($patient['BedNo']) ?>"></div>
            <div class="form-group"><label>Doctor No</label><input readonly value="<?= h($patient['DoctorInCharge'] ?? 'N/A') ?>"></div>
            <div class="form-group"><label>Doctor Name</label><input readonly value="<?= h($patient['DoctorName']) ?>"></div>
            <?php if ($patient['ConsultantName']): ?>
            <div class="form-group"><label>Consultant</label><input readonly value="<?= h($patient['ConsultantName']) ?> — <?= h($patient['ConsultantSpec']) ?>"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Medical History -->
<div class="card">
    <div class="card-header"><h2>📁 Medical History</h2></div>
    <div class="card-body">
        <!-- Add entry form -->
        <p class="section-title">Add New Entry</p>
        <form method="POST">
            <input type="hidden" name="form_action" value="add_history">
            <input type="hidden" name="PatientNo" value="<?= $pid ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Complaint *</label>
                    <select name="ComplaintCode" required>
                        <option value="">— Select —</option>
                        <?php foreach ($complaints as $c): ?>
                            <option value="<?= $c['ComplaintCode'] ?>"><?= h($c['Description']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Treatment *</label>
                    <select name="TreatmentCode" required>
                        <option value="">— Select —</option>
                        <?php foreach ($treatments as $t): ?>
                            <option value="<?= $t['TreatmentCode'] ?>"><?= h($t['Description']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Treating Doctor *</label>
                    <select name="DoctorNo" required>
                        <option value="">— Select —</option>
                        <?php foreach ($doctors as $doc): ?>
                            <option value="<?= $doc['StaffNo'] ?>"><?= h($doc['Name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date Started *</label>
                    <input type="date" name="DateStarted" required value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-teal btn-sm">➕ Add Entry</button>
            </div>
        </form>

        <!-- History table -->
        <?php if ($medHistory): ?>
        <p class="section-title" style="margin-top:24px">History</p>
        <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Complaint</th><th>Treatment</th><th>Doctor</th><th>Started</th><th>Ended</th></tr></thead>
            <tbody>
            <?php foreach ($medHistory as $mh): ?>
            <tr>
                <td><?= h($mh['HistoryID']) ?></td>
                <td><?= h($mh['Complaint']) ?></td>
                <td><?= h($mh['Treatment']) ?></td>
                <td><?= h($mh['Doctor']) ?></td>
                <td><?= h($mh['DateStarted']) ?></td>
                <td><?= $mh['DateEnded'] ? h($mh['DateEnded']) : '<span class="pill pill-teal">Ongoing</span>' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

<?php render_footer(); ?>