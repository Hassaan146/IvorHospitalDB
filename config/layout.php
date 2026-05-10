<?php
// layout.php — call render_header() and render_footer() from every page
function render_header(string $page_title, string $active = ''): void { ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?> — IVOR Hospital</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
    
    <!-- FIXED CSS PATH -->
    <link rel="stylesheet" href="/project/assets/style.css">
</head>
<body>

<nav class="sidebar">
    <div class="sidebar-logo">
        <span class="logo-cross">✚</span>
        <div>
            <strong>IVOR HOSPITAL</strong>
            <small>Management System</small>
        </div>
    </div>
    <ul class="nav-links">
        <!-- FIXED PAGE PATHS -->
        <li><a href="/project/index.php"                   class="<?= $active==='home'        ?'active':'' ?>">🏠 Dashboard</a></li>
        <li class="nav-section">RECORDS</li>
        <li><a href="/project/pages/patient_record.php"    class="<?= $active==='patient'     ?'active':'' ?>">🧑‍⚕️ Patient Record</a></li>
        <li><a href="/project/pages/ward_record.php"       class="<?= $active==='ward'        ?'active':'' ?>">🏥 Ward Record</a></li>
        <li><a href="/project/pages/consultant_team.php"   class="<?= $active==='consultant'  ?'active':'' ?>">🏅 Consultant Team</a></li>
        <li class="nav-section">REPORTS</li>
        <li><a href="/project/pages/reports.php?q=1"       class="<?= $active==='report1'     ?'active':'' ?>">📋 Team Members</a></li>
        <li><a href="/project/pages/reports.php?q=2"       class="<?= $active==='report2'     ?'active':'' ?>">📋 Ward Overview</a></li>
        <li><a href="/project/pages/reports.php?q=3"       class="<?= $active==='report3'     ?'active':'' ?>">📋 Patient Treatments</a></li>
        <li><a href="/project/pages/reports.php?q=7"       class="<?= $active==='report7'     ?'active':'' ?>">📋 Multi-Complaint</a></li>
        <li><a href="/project/pages/reports.php?q=12"      class="<?= $active==='report12'    ?'active':'' ?>">📋 Staff Count</a></li>
        <li><a href="/project/pages/reports.php"           class="<?= $active==='reports'     ?'active':'' ?>">📊 All Reports</a></li>
    </ul>
    <div class="sidebar-footer">CS204 — Lab Project</div>
</nav>

<main class="main-content">
    <header class="topbar">
        <h1 class="page-title"><?= h($page_title) ?></h1>
        <span class="topbar-date"><?= date('l, d F Y') ?></span>
    </header>
    <div class="content-area">
<?php } ?>

<?php
function render_footer(): void { ?>
    </div><!-- .content-area -->
</main><!-- .main-content -->
</body>
</html>
<?php } ?>