<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';

$q     = (int)($_GET['q'] ?? 0);
$data  = [];
$title = "Select a Report";
$extra = '';      // any contextual HTML to print above the results table
$paramForm = '';  // optional form for parameterised queries

switch ($q) {

    // Q1. Consultants and doctors in their team
    case 1:
        $title = "Q1 . Consultants and Doctors in their Team";
        $data = dbQuery("
            SELECT con.StaffNo AS ConsultantNo, sc.Name AS ConsultantName, con.Specialty,
                   d.StaffNo AS DoctorNo, sd.Name AS DoctorName, d.Position, d.DateJoinedTeam
            FROM CONSULTANT con
            JOIN STAFF sc ON sc.StaffNo = con.StaffNo
            JOIN DOCTOR d ON d.ConsultantNo = con.StaffNo
            JOIN STAFF sd ON sd.StaffNo = d.StaffNo
            ORDER BY con.StaffNo, d.DateJoinedTeam
        ");
        break;

    // Q2. Wards with respective sisters, care units and staff nurses in-charge
    case 2:
        $title = "Q2 . Wards, Sisters, Care Units and Staff Nurses In-Charge";
        $data = dbQuery("
            SELECT w.WardName, w.Specialty,
                   sday.Name   AS DaySister,
                   snight.Name AS NightSister,
                   cu.CareUnitNo,
                   sn.Name AS StaffNurseInCharge
            FROM WARD w
            LEFT JOIN STAFF sday   ON sday.StaffNo   = w.DaySisterStaffNo
            LEFT JOIN STAFF snight ON snight.StaffNo = w.NightSisterStaffNo
            LEFT JOIN CARE_UNIT cu ON cu.WardName    = w.WardName
            LEFT JOIN STAFF sn     ON sn.StaffNo     = cu.InChargeStaffNo
            ORDER BY w.WardName, cu.CareUnitNo
        ");
        break;

    // Q3. Patients with complaints, treatments and dates
    case 3:
        $title = "Q3 . Patients, Complaints, Treatments and Dates";
        $data = dbQuery("
            SELECT p.PatientNo, p.Name AS PatientName,
                   c.Description AS Complaint,
                   t.Description AS Treatment,
                   sd.Name AS TreatingDoctor,
                   mh.DateStarted,
                   ISNULL(CONVERT(VARCHAR(10), mh.DateEnded, 120), 'Ongoing') AS DateEnded
            FROM PATIENT p
            JOIN MEDICAL_HISTORY mh ON mh.PatientNo = p.PatientNo
            JOIN COMPLAINT c ON c.ComplaintCode = mh.ComplaintCode
            JOIN TREATMENT t ON t.TreatmentCode = mh.TreatmentCode
            JOIN STAFF sd ON sd.StaffNo = mh.DoctorNo
            ORDER BY p.PatientNo, mh.DateStarted
        ");
        break;

    // Q4. Junior housemen, their patients, and staff nurse for the care-unit
    case 4:
        $title = "Q4 . Junior Housemen, their Patients and Care-Unit Staff Nurse";
        $data = dbQuery("
            SELECT d.StaffNo AS DoctorNo, sd.Name AS JuniorHouseman,
                   p.PatientNo, p.Name AS PatientName,
                   p.CareUnitNo, sn.Name AS StaffNurseInCharge
            FROM DOCTOR d
            JOIN STAFF sd ON sd.StaffNo = d.StaffNo
            JOIN PATIENT p ON p.DoctorInCharge = d.StaffNo
            JOIN CARE_UNIT cu ON cu.CareUnitNo = p.CareUnitNo
            JOIN STAFF sn ON sn.StaffNo = cu.InChargeStaffNo
            WHERE d.Position = 'Junior Houseman'
            ORDER BY d.StaffNo, p.PatientNo
        ");
        break;

    // Q5. Consultants with a unique specialty
    case 5:
        $title = "Q5 . Consultants with a Unique Specialty";
        $data = dbQuery("
            SELECT con.StaffNo AS ConsultantNo, s.Name AS ConsultantName, con.Specialty
            FROM CONSULTANT con
            JOIN STAFF s ON s.StaffNo = con.StaffNo
            WHERE con.Specialty IN (
                SELECT Specialty
                FROM CONSULTANT
                GROUP BY Specialty
                HAVING COUNT(*) = 1
            )
            ORDER BY con.Specialty
        ");
        break;

    // Q6. Complaints, treatments and experience history of the treating doctor
    case 6:
        $title = "Q6 . Complaints, Treatments and Doctor Experience History";
        $data = dbQuery("
            SELECT c.Description AS Complaint,
                   t.Description AS Treatment,
                   sd.Name AS Doctor,
                   er.FromDate, er.ToDate,
                   er.Position AS PreviousPosition,
                   er.Establishment
            FROM MEDICAL_HISTORY mh
            JOIN COMPLAINT c ON c.ComplaintCode = mh.ComplaintCode
            JOIN TREATMENT t ON t.TreatmentCode = mh.TreatmentCode
            JOIN STAFF sd ON sd.StaffNo = mh.DoctorNo
            JOIN EXPERIENCE_RECORD er ON er.DoctorNo = mh.DoctorNo
            ORDER BY c.Description, sd.Name, er.FromDate
        ");
        break;

    // Q7. Patients with more than one complaint and their treatments
    case 7:
        $title = "Q7 . Patients with More than One Complaint and their Treatments";
        $data = dbQuery("
            SELECT p.PatientNo, p.Name AS PatientName,
                   c.Description AS Complaint,
                   t.Description AS Treatment,
                   mh.DateStarted,
                   ISNULL(CONVERT(VARCHAR(10), mh.DateEnded, 120), 'Ongoing') AS DateEnded
            FROM PATIENT p
            JOIN MEDICAL_HISTORY mh ON mh.PatientNo = p.PatientNo
            JOIN COMPLAINT c ON c.ComplaintCode = mh.ComplaintCode
            JOIN TREATMENT t ON t.TreatmentCode = mh.TreatmentCode
            WHERE p.PatientNo IN (
                SELECT PatientNo
                FROM MEDICAL_HISTORY
                GROUP BY PatientNo
                HAVING COUNT(DISTINCT ComplaintCode) > 1
            )
            ORDER BY p.PatientNo, c.Description, mh.DateStarted
        ");
        break;

    // Q8. Patients grouped by treatment within complaint
    case 8:
        $title = "Q8 . Patients Grouped by Treatment within Complaint";
        $data = dbQuery("
            SELECT c.Description AS Complaint,
                   t.Description AS Treatment,
                   p.PatientNo, p.Name AS PatientName,
                   mh.DateStarted,
                   ISNULL(CONVERT(VARCHAR(10), mh.DateEnded, 120), 'Ongoing') AS DateEnded
            FROM MEDICAL_HISTORY mh
            JOIN COMPLAINT c ON c.ComplaintCode = mh.ComplaintCode
            JOIN TREATMENT t ON t.TreatmentCode = mh.TreatmentCode
            JOIN PATIENT p   ON p.PatientNo = mh.PatientNo
            ORDER BY c.Description, t.Description, p.PatientNo
        ");
        break;

    // Q9. Performance history for a particular doctor
    case 9:
        $title = "Q9 . Performance History for a Particular Doctor";
        $doctorId = (int)($_GET['id'] ?? 0);
        $doctorList = dbQuery("
            SELECT d.StaffNo, s.Name, d.Position
            FROM DOCTOR d JOIN STAFF s ON s.StaffNo = d.StaffNo
            WHERE d.ConsultantNo IS NOT NULL
            ORDER BY s.Name
        ");
        $paramForm = '<form method="GET" class="search-bar">'
                   . '<input type="hidden" name="q" value="9">'
                   . '<select name="id" onchange="this.form.submit()">'
                   . '<option value="">— Select a Doctor —</option>';
        foreach ($doctorList as $d) {
            $sel = $doctorId == $d['StaffNo'] ? 'selected' : '';
            $paramForm .= '<option value="' . $d['StaffNo'] . '" ' . $sel . '>'
                       . h($d['Name']) . ' (' . h($d['Position']) . ')</option>';
        }
        $paramForm .= '</select></form>';

        if ($doctorId) {
            $data = dbQuery("
                SELECT sd.Name AS DoctorName, d.Position,
                       sc.Name AS ConsultantName,
                       pr.GradeDate, pr.Grade
                FROM PERFORMANCE_RECORD pr
                JOIN DOCTOR d ON d.StaffNo = pr.DoctorNo
                JOIN STAFF sd ON sd.StaffNo = d.StaffNo
                JOIN CONSULTANT con ON con.StaffNo = pr.ConsultantNo
                JOIN STAFF sc ON sc.StaffNo = con.StaffNo
                WHERE pr.DoctorNo = ?
                ORDER BY pr.GradeDate
            ", [$doctorId]);
        }
        break;

    // Q10. Full medical details for a particular patient
    case 10:
        $title = "Q10 . Full Medical Details for a Particular Patient";
        $patientId = (int)($_GET['id'] ?? 0);
        $patientList = dbQuery("SELECT PatientNo, Name FROM PATIENT ORDER BY Name");
        $paramForm = '<form method="GET" class="search-bar">'
                   . '<input type="hidden" name="q" value="10">'
                   . '<select name="id" onchange="this.form.submit()">'
                   . '<option value="">— Select a Patient —</option>';
        foreach ($patientList as $p) {
            $sel = $patientId == $p['PatientNo'] ? 'selected' : '';
            $paramForm .= '<option value="' . $p['PatientNo'] . '" ' . $sel . '>'
                       . h($p['Name']) . ' (#' . $p['PatientNo'] . ')</option>';
        }
        $paramForm .= '</select></form>';

        if ($patientId) {
            $header = dbQuery("
                SELECT p.PatientNo, p.Name AS PatientName, p.DateOfBirth, p.DateAdmitted,
                       ISNULL(CONVERT(VARCHAR(10), p.DateDischarged, 120), 'Currently Admitted') AS Status,
                       w.WardName, w.Specialty, cu.CareUnitNo, p.BedNo,
                       sd.Name AS DoctorInCharge, doc.Position,
                       sc.Name AS ConsultantName, con.Specialty AS ConsultantSpecialty
                FROM PATIENT p
                JOIN CARE_UNIT cu ON cu.CareUnitNo = p.CareUnitNo
                JOIN WARD w ON w.WardName    = cu.WardName
                JOIN DOCTOR doc ON doc.StaffNo   = p.DoctorInCharge
                JOIN STAFF sd ON sd.StaffNo    = doc.StaffNo
                LEFT JOIN CONSULTANT con ON con.StaffNo = doc.ConsultantNo
                LEFT JOIN STAFF sc       ON sc.StaffNo  = doc.ConsultantNo
                WHERE p.PatientNo = ?
            ", [$patientId]);

            if (!empty($header)) {
                $h1 = $header[0];
                $extra = '<div class="card" style="margin-bottom:18px"><div class="card-header"><h2>👤 ' . h($h1['PatientName']) . '</h2></div><div class="card-body"><div class="form-grid">'
                      . '<div class="form-group"><label>Patient No</label><input readonly value="' . h($h1['PatientNo']) . '"></div>'
                      . '<div class="form-group"><label>DOB</label><input readonly value="' . h($h1['DateOfBirth']) . '"></div>'
                      . '<div class="form-group"><label>Admitted</label><input readonly value="' . h($h1['DateAdmitted']) . '"></div>'
                      . '<div class="form-group"><label>Status</label><input readonly value="' . h($h1['Status']) . '"></div>'
                      . '<div class="form-group"><label>Ward</label><input readonly value="' . h($h1['WardName']) . ' — ' . h($h1['Specialty']) . '"></div>'
                      . '<div class="form-group"><label>Care Unit / Bed</label><input readonly value="Unit ' . h($h1['CareUnitNo']) . ' / Bed ' . h($h1['BedNo']) . '"></div>'
                      . '<div class="form-group"><label>Doctor In Charge</label><input readonly value="' . h($h1['DoctorInCharge']) . ' (' . h($h1['Position']) . ')"></div>'
                      . '<div class="form-group"><label>Consultant</label><input readonly value="' . h(($h1['ConsultantName'] ?? 'N/A') . ($h1['ConsultantSpecialty'] ? ' — ' . $h1['ConsultantSpecialty'] : '')) . '"></div>'
                      . '</div></div></div>';
            }

            $data = dbQuery("
                SELECT c.Description AS Complaint,
                       t.Description AS Treatment,
                       s.Name AS TreatingDoctor,
                       mh.DateStarted,
                       ISNULL(CONVERT(VARCHAR(10), mh.DateEnded, 120), 'Ongoing') AS DateEnded
                FROM MEDICAL_HISTORY mh
                JOIN COMPLAINT c ON c.ComplaintCode = mh.ComplaintCode
                JOIN TREATMENT t ON t.TreatmentCode = mh.TreatmentCode
                JOIN STAFF s  ON s.StaffNo = mh.DoctorNo
                WHERE mh.PatientNo = ?
                ORDER BY mh.DateStarted
            ", [$patientId]);
        }
        break;

    // Q11. Treatments for a particular complaint between two given dates
    case 11:
        $title = "Q11 . Treatments for a Complaint between Two Dates (ordered by Treatment)";
        $cCode    = (int)($_GET['complaint'] ?? 0);
        $fromDate = $_GET['from'] ?? '2026-01-01';
        $toDate   = $_GET['to']   ?? '2026-12-31';
        $complaintList = dbQuery("SELECT ComplaintCode, Description FROM COMPLAINT ORDER BY Description");

        $paramForm  = '<form method="GET" class="search-bar" style="flex-wrap:wrap">'
                    . '<input type="hidden" name="q" value="11">'
                    . '<select name="complaint" required><option value="">— Select a Complaint —</option>';
        foreach ($complaintList as $cl) {
            $sel = $cCode == $cl['ComplaintCode'] ? 'selected' : '';
            $paramForm .= '<option value="' . $cl['ComplaintCode'] . '" ' . $sel . '>' . h($cl['Description']) . '</option>';
        }
        $paramForm .= '</select>'
                    . '<input type="date" name="from" value="' . h($fromDate) . '">'
                    . '<input type="date" name="to"   value="' . h($toDate) . '">'
                    . '<button type="submit" class="btn btn-primary btn-sm">Run Query</button>'
                    . '</form>';

        if ($cCode) {
            $data = dbQuery("
                SELECT c.Description AS Complaint,
                       t.Description AS Treatment,
                       p.Name AS PatientName,
                       s.Name AS TreatingDoctor,
                       mh.DateStarted,
                       ISNULL(CONVERT(VARCHAR(10), mh.DateEnded, 120), 'Ongoing') AS DateEnded
                FROM MEDICAL_HISTORY mh
                JOIN COMPLAINT c ON c.ComplaintCode = mh.ComplaintCode
                JOIN TREATMENT t ON t.TreatmentCode = mh.TreatmentCode
                JOIN PATIENT p   ON p.PatientNo = mh.PatientNo
                JOIN STAFF s     ON s.StaffNo = mh.DoctorNo
                WHERE mh.ComplaintCode = ?
                  AND mh.DateStarted BETWEEN ? AND ?
                ORDER BY t.Description, mh.DateStarted
            ", [$cCode, $fromDate, $toDate]);
        }
        break;

    // Q12. Positions held by staff and count in each position
    case 12:
        $title = "Q12 . Staff Positions and Counts";
        $data = dbQuery("
            SELECT Position AS StaffPosition, COUNT(*) AS NumberOfStaff, 'Doctor' AS StaffCategory
            FROM DOCTOR
            GROUP BY Position
            UNION ALL
            SELECT NurseType, COUNT(*), 'Nurse'
            FROM NURSE
            GROUP BY NurseType
            ORDER BY StaffCategory, NumberOfStaff DESC
        ");
        break;
}

render_header($title, 'reports');
?>

<?php if ($q === 0): ?>
    <p style="margin-bottom:18px;color:var(--text-muted)">
        Select one of the 12 required reports below.
    </p>
    <div class="report-grid">
        <a href="?q=1"  class="report-card"><span class="r-icon">📋</span><div><span class="r-num">Query 01</span><div class="r-title">Consultants &amp; their Teams</div></div></a>
        <a href="?q=2"  class="report-card"><span class="r-icon">🏥</span><div><span class="r-num">Query 02</span><div class="r-title">Ward · Sisters · Care Units</div></div></a>
        <a href="?q=3"  class="report-card"><span class="r-icon">🧑‍⚕️</span><div><span class="r-num">Query 03</span><div class="r-title">Patient Complaints &amp; Treatments</div></div></a>
        <a href="?q=4"  class="report-card"><span class="r-icon">👨‍⚕️</span><div><span class="r-num">Query 04</span><div class="r-title">Junior Housemen · Patients · Nurses</div></div></a>
        <a href="?q=5"  class="report-card"><span class="r-icon">🏅</span><div><span class="r-num">Query 05</span><div class="r-title">Consultants with Unique Specialty</div></div></a>
        <a href="?q=6"  class="report-card"><span class="r-icon">📜</span><div><span class="r-num">Query 06</span><div class="r-title">Treatments &amp; Doctor Experience</div></div></a>
        <a href="?q=7"  class="report-card"><span class="r-icon">📋</span><div><span class="r-num">Query 07</span><div class="r-title">Multi-Complaint Patients</div></div></a>
        <a href="?q=8"  class="report-card"><span class="r-icon">📊</span><div><span class="r-num">Query 08</span><div class="r-title">Patients Grouped by Treatment</div></div></a>
        <a href="?q=9"  class="report-card"><span class="r-icon">📈</span><div><span class="r-num">Query 09</span><div class="r-title">Doctor Performance History</div></div></a>
        <a href="?q=10" class="report-card"><span class="r-icon">📁</span><div><span class="r-num">Query 10</span><div class="r-title">Full Patient Medical Details</div></div></a>
        <a href="?q=11" class="report-card"><span class="r-icon">📅</span><div><span class="r-num">Query 11</span><div class="r-title">Treatments by Date Range</div></div></a>
        <a href="?q=12" class="report-card"><span class="r-icon">📊</span><div><span class="r-num">Query 12</span><div class="r-title">Staff Position Counts</div></div></a>
    </div>
<?php else: ?>
    <?php if ($paramForm): ?>
        <div class="card" style="margin-bottom:18px">
            <div class="card-header"><h2>🔎 Query Parameters</h2></div>
            <div class="card-body"><?= $paramForm ?></div>
        </div>
    <?php endif; ?>

    <?= $extra ?>

    <div class="card">
        <div class="card-header">
            <h2>Report Results</h2>
            <a href="reports.php" class="badge" style="text-decoration:none">← All Reports</a>
        </div>
        <div class="table-wrap">
            <?php if (!empty($data)): ?>
            <table>
                <thead><tr>
                    <?php foreach (array_keys($data[0]) as $k): ?>
                        <th><?= h($k) ?></th>
                    <?php endforeach; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                    <?php foreach ($row as $v): ?>
                        <td><?= h($v) ?></td>
                    <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-state">
                    <div class="es-icon">📭</div>
                    <p><?php
                        if (in_array($q, [9, 10, 11], true)) {
                            echo 'Please select the parameters above to run this query.';
                        } else {
                            echo 'No data found for this report.';
                        }
                    ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php render_footer(); ?>