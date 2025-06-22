<?php
// Database Configuration
$host      = 'localhost';
$db_name   = 'rfid_attendance_db';
$username  = 'root';
$password  = '';

// Establish Database Connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

date_default_timezone_set('Asia/Manila');

$message             = '';
$student_info        = null;
$attendance_recorded = false;
$last_action_time    = '';
$last_action_type    = '';
$last_status         = '';

// Handle RFID Scan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rfid_uid'])) {
    $rfid_uid = trim($_POST['rfid_uid']);

    if (empty($rfid_uid)) {
        $message = '<div class="alert error">Please scan an RFID card.</div>';
    } else {
        // Look up the student by RFID UID
        $stmt = $pdo->prepare("SELECT * FROM students WHERE rfid_uid = :rfid_uid");
        $stmt->execute(['rfid_uid' => $rfid_uid]);
        $student = $stmt->fetch();

        if ($student) {
            $student_info         = $student;
            $current_datetime_str = date('Y-m-d H:i:s');
            $current_time_str     = date('H:i:s');
            $current_date_str     = date('Y-m-d');

            // Define Active Time Windows
            $is_morning_session   = ($current_time_str >= '09:00:00' && $current_time_str <= '11:00:00');
            $is_afternoon_session = ($current_time_str >= '16:00:00' && $current_time_str <= '17:00:00');

            // Check for today's attendance record
            $stmt_check = $pdo->prepare("
                SELECT * FROM attendance 
                WHERE rfid_uid = :rfid_uid 
                  AND DATE(time_in) = :current_date
            ");
            $stmt_check->execute(['rfid_uid' => $rfid_uid, 'current_date' => $current_date_str]);
            $todays_record = $stmt_check->fetch();

            if ($is_morning_session) {
                // --- TIME IN LOGIC ---
                if ($todays_record) {
                    $message = '<div class="alert info">' . htmlspecialchars($student['name']) . ' has already timed in today.</div>';
                } else {
                    // Present if scanned before 10:30:00; Late if at or after 10:30:00
                    $status = ($current_time_str < '10:30:00') ? 'present' : 'late';
                    
                    $stmt_insert = $pdo->prepare("
                        INSERT INTO attendance (rfid_uid, time_in, status)
                        VALUES (:rfid_uid, :time_in, :status)
                    ");
                    if ($stmt_insert->execute([
                        'rfid_uid' => $rfid_uid,
                        'time_in'  => $current_datetime_str,
                        'status'   => $status
                    ])) {
                        $message             = '<div class="alert success">Time In recorded for ' . htmlspecialchars($student['name']) . '. Status: ' . ucfirst($status) . '.</div>';
                        $attendance_recorded = true;
                        $last_action_time    = $current_datetime_str;
                        $last_action_type    = "Time In";
                        $last_status         = $status;
                    } else {
                        $message = '<div class="alert error">Error recording Time In.</div>';
                    }
                }
            } elseif ($is_afternoon_session) {
                // --- TIME OUT LOGIC ---
                if (!$todays_record) {
                    $message = '<div class="alert error">Cannot Time Out. ' . htmlspecialchars($student['name']) . ' did not time in this morning.</div>';
                } elseif ($todays_record['time_out'] !== null) {
                    $message = '<div class="alert info">' . htmlspecialchars($student['name']) . ' has already timed out today.</div>';
                } else {
                    $stmt_update = $pdo->prepare("
                        UPDATE attendance 
                        SET time_out = :time_out 
                        WHERE id = :id
                    ");
                    if ($stmt_update->execute([
                        'time_out' => $current_datetime_str,
                        'id'       => $todays_record['id']
                    ])) {
                        $message             = '<div class="alert success">Time Out recorded successfully for ' . htmlspecialchars($student['name']) . '.</div>';
                        $attendance_recorded = true;
                        $last_action_time    = $current_datetime_str;
                        $last_action_type    = "Time Out";
                    } else {
                        $message = '<div class="alert error">Error recording Time Out.</div>';
                    }
                }
            } else {
                // --- SYSTEM INACTIVE ---
                $message = '<div class="alert error">The attendance system is currently inactive. Please try again during the active hours (9:00-11:00 AM for Time In or 4:00-5:00 PM for Time Out).</div>';
            }
        } else {
            $message = '<div class="alert error">RFID card not registered.</div>';
        }
    }
}

// --- Automated Task to Mark Absences ---
$current_time = date('H:i:s');
if ($current_time > '17:00:00') {
    $stmt_absent = $pdo->prepare("
        UPDATE attendance 
        SET status = 'absent'
        WHERE DATE(time_in) = :current_date 
          AND time_out IS NULL 
          AND status != 'absent'
    ");
    $stmt_absent->execute(['current_date' => date('Y-m-d')]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>San Isidro National High School RFID Attendance Monitoring System</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background-color: #f8f9fa; margin: 0; padding: 10px; color: #343a40; }
        .container { max-width: 1000px; margin: 20px auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .header-container {
            text-align: center;
            margin-bottom: 25px;
        }
        .header-logo {
            max-height: 90px; /* Adjust size as needed */
            margin-bottom: 15px;
        }
        h1 {
            color: #0056b3;
            text-align: center;
            margin: 0;
            line-height: 1.2;
            font-size: 1.9em;
        }
        h1 .sub-heading {
            display: block;
            font-size: 0.75em;
            font-weight: normal;
        }
        .form-group { margin-bottom: 25px; text-align: center; }
        .inline-form { display: flex; justify-content: center; align-items: center; }
        .inline-form input { width: 60%; padding: 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 16px; margin-right: 10px; }
        .inline-form input:focus { border-color: #80bdff; box-shadow: 0 0 0 .2rem rgba(0,123,255,.25); }
        .form-group button { padding: 12px 24px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; transition: background-color 0.2s; }
        .form-group button:hover { background-color: #0056b3; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; text-align: center; border: 1px solid transparent; }
        .alert.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
        .alert.info { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
        .status-present { color: #28a745; font-weight: bold; }
        .status-late { color: #fd7e14; font-weight: bold; }
        .status-absent { color: #dc3545; font-weight: bold; }
        .scan-modal {
            position: fixed;
            top:0; left:0; right:0; bottom:0;
            background: rgba(0,0,0,0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2222;
        }
        .scan-modal-content {
            background: #fff;
            padding: 28px 40px;
            border-radius: 14px;
            min-width: 320px;
            text-align: left;
            box-shadow: 0 12px 32px rgba(0,0,0,0.14);
            animation: appear 0.18s;
        }
        @keyframes appear { from {opacity:0;transform:scale(.96);} to {opacity:1;transform:scale(1);} }
        .scan-modal-content h3 { margin-top:0; color:#0056b3;}
        .scan-modal-content p {font-size:1.15em; margin:.8em 0;}
    </style>
</head>
<body>
    <div class="container">
        <div class="header-container">
            <!-- IMPORTANT: Change 'your_logo.png' to the path of your school's logo file -->
            <img src="img/logo.jpg" alt="School Logo" class="header-logo">
            <h1>
                San Isidro National High School
                <span class="sub-heading">RFID Attendance Monitoring System</span>
            </h1>
        </div>

        <?php echo $message; ?>
        
        <div class="form-group">
            <form action="" method="post" class="inline-form">
                <input type="text" id="rfid_uid" name="rfid_uid" placeholder="Scan RFID Card..." required autofocus autocomplete="off">
                <button type="submit">Submit</button>
            </form>
        </div>
    </div>

    <?php if ($student_info && $attendance_recorded): ?>
        <div class="scan-modal" id="scanModal">
            <div class="scan-modal-content">
                <h3>Student Details</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($student_info['name']); ?></p>
                <p><strong>Action:</strong> <?php echo htmlspecialchars($last_action_type); ?></p>
                <p><strong>Time:</strong> <?php echo htmlspecialchars(date("g:i:s A", strtotime($last_action_time))); ?></p>
                <?php if ($last_action_type === "Time In"): ?>
                    <p><strong>Status:</strong>
                        <span class="status-<?php echo htmlspecialchars($last_status); ?>">
                            <?php echo ucfirst(htmlspecialchars($last_status)); ?>
                        </span>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <script>
        function closeModal() {
            var modal = document.getElementById('scanModal');
            if(modal) modal.style.display = 'none';
            var input = document.getElementById('rfid_uid');
            if(input) { input.focus(); input.value = ''; }
        }
        <?php if ($student_info && $attendance_recorded): ?>
        setTimeout(closeModal, 2500); // Hide modal after 2.5 seconds
        <?php endif; ?>
        window.onload = function() {
            var rfidInput = document.getElementById('rfid_uid');
            if (rfidInput) {
                rfidInput.focus();
                rfidInput.value = ''; // clear for next scan
            }
        };
    </script>
</body>
</html>