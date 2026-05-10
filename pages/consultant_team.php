<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';

$consultantNo    = (int)($_GET['id']     ?? 0);
$selectedDoctorId = (int)($_GET['doctor'] ?? 0);

$consultants = dbQuery("SELECT c.StaffNo, s.Name FROM CONSULTANT c JOIN STAFF s ON c.StaffNo = s.StaffNo");

$team = [];
if ($consultantNo) {
    $team = dbQuery("
        SELECT d.StaffNo, s.Name, d.Position, d.DateJoinedTeam
        FROM DOCTOR d
        JOIN STAFF s ON d.StaffNo = s.StaffNo
        WHERE d.ConsultantNo = ?
    ", [$consultantNo]);
}

$experience  = [];
$performance = [];
if ($selectedDoctorId) {
    $experience = dbQuery("
        SELECT FromDate, ToDate, Position, Establishment
        FROM EXPERIENCE_RECORD
        WHERE DoctorNo = ?
        ORDER BY FromDate
    ", [$selectedDoctorId]);

    $performance = dbQuery("
        SELECT GradeDate, Grade
        FROM PERFORMANCE_RECORD
        WHERE DoctorNo = ?
        ORDER BY GradeDate
    ", [$selectedDoctorId]);
}

render_header('Consultant Teams', 'consultant');
?>

<div class="card">
    <div class="card-header"><h2>🏅 Team Selector</h2></div>
    <div class="card-body">
        <form method="GET" class="search-bar">
            <select name="id" onchange="this.form.submit()">
                <option value="">— Select Consultant —</option>
                <?php foreach ($consultants as $c): ?>
                    <option value="<?= $c['StaffNo'] ?>" <?= $consultantNo == $c['StaffNo'] ? 'selected' : '' ?>>
                        <?= h($c['Name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
</div>

<?php if ($consultantNo): ?>
<div class="card">
    <div class="card-header"><h2>Team Members</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Staff No</th><th>Name</th><th>Position</th><th>Date Joined</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($team as $tm): ?>
            <tr>
                <td><?= h($tm['StaffNo']) ?></td>
                <td><strong><?= h($tm['Name']) ?></strong></td>
                <td><?= h($tm['Position']) ?></td>
                <td><?= h($tm['DateJoinedTeam']) ?></td>
                <td>
                    <a href="?id=<?= $consultantNo ?>&doctor=<?= $tm['StaffNo'] ?>" class="btn btn-outline btn-sm">📈 View Record</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($selectedDoctorId): ?>
<div class="card">
    <div class="card-header"><h2>📋 Doctor Record — Staff No: <?= $selectedDoctorId ?></h2></div>
    <div class="card-body">

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">

            <!-- Previous Experience -->
            <div>
                <p class="section-title">Previous Experience</p>
                <table>
                    <thead><tr><th>From</th><th>To</th><th>Position</th><th>Establishment</th></tr></thead>
                    <tbody>
                    <?php if (empty($experience)): ?>
                        <tr><td colspan="4" style="padding:10px;color:var(--text-muted)">No experience records.</td></tr>
                    <?php else: foreach ($experience as $e): ?>
                        <tr>
                            <td><?= h($e['FromDate']) ?></td>
                            <td><?= $e['ToDate'] ? h($e['ToDate']) : '<span class="pill pill-teal">Current</span>' ?></td>
                            <td><?= h($e['Position']) ?></td>
                            <td><?= h($e['Establishment']) ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Performance / Progress -->
            <div>
                <p class="section-title">Progress</p>
                <table>
                    <thead><tr><th>Date</th><th>Performance Grade</th></tr></thead>
                    <tbody>
                    <?php if (empty($performance)): ?>
                        <tr><td colspan="2" style="padding:10px;color:var(--text-muted)">No performance records.</td></tr>
                    <?php else: foreach ($performance as $pr): ?>
                        <tr>
                            <td><?= h($pr['GradeDate']) ?></td>
                            <td><strong><?= h($pr['Grade']) ?></strong></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
<?php endif; ?>

<?php render_footer(); ?>