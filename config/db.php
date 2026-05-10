<?php
// ============================================================
//  IVOR HOSPITAL — SQL Server Connection (Updated)
// ============================================================

// 1. Update these to match your "DESKTOP-Q0RIFGH" environment
define('DB_SERVER',   'DESKTOP-Q0RIFGH'); 
define('DB_NAME',     'IvorHospitalDB');   // The name we just created in SQL

// 2. If you are using Windows Authentication (like your old file), 
// leave these empty or null. SQL Server will use your Windows login.
define('DB_USER', 'ivoruser');
define('DB_PASSWORD', 'Ivor123');
function getConnection(): mixed {
    $serverName = DB_SERVER;
    
    // 3. We use the logic from your old file here
    $connInfo = [
        "Database" => DB_NAME,
        "CharacterSet" => "UTF-8",
        "TrustServerCertificate" => true
    ];

    // 4. Add UID/PWD only if they are actually set
    if (DB_USER !== null) {
        $connInfo["UID"] = DB_USER;
        $connInfo["PWD"] = DB_PASSWORD;
    }

    $conn = sqlsrv_connect($serverName, $connInfo);

    if ($conn === false) {
        $errors = sqlsrv_errors();
        die(json_encode([
            'error'   => 'Database connection failed.',
            'details' => $errors
        ]));
    }

    return $conn;
}

function dbQuery(string $sql, array $params = []): array {
    $conn   = getConnection();
    $stmt   = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $err = sqlsrv_errors();
        sqlsrv_close($conn);
        return ['__error' => $err];
    }
    $rows = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        // Convert DateTime objects to strings
        foreach ($row as $k => $v) {
            if ($v instanceof DateTime) {
                $row[$k] = $v->format('Y-m-d');
            }
        }
        $rows[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    sqlsrv_close($conn);
    return $rows;
}
 
// Helper: run INSERT / UPDATE / DELETE, return true/false
function dbExecute(string $sql, array $params = []): bool {
    $conn = getConnection();
    $stmt = sqlsrv_query($conn, $sql, $params);
    $ok   = ($stmt !== false);
    if (!$ok) {
        error_log(print_r(sqlsrv_errors(), true));
    }
    sqlsrv_close($conn);
    return $ok;
}
 
// Helper: escape for safe display (not for SQL — use parameterised queries)
function h(mixed $v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
