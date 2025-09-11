<?php
// Database Configuration
$host       = 'localhost';
$db_name    = 'rfid_capstone';
$username   = 'root';
$password   = '';

// Establish Database Connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

date_default_timezone_set('Asia/Manila');

$message            = '';
$student_info       = null;
$attendance_recorded = false;
$last_action_time   = '';
$last_action_type   = '';
$last_status        = '';

// Get current time details for display and logic
$current_datetime_str = date('Y-m-d H:i:s');
$current_time_str     = date('H:i:s');
$current_date_str     = date('Y-m-d');
$current_day_name     = date('l'); // Full day name (e.g., Monday)
$formatted_date       = date('F j, Y'); // e.g., June 23, 2025

// Define current school year (you may want to make this configurable)
$current_school_year = '2024-2025'; // Adjust this as needed

// Get time settings from database
function getTimeSettings($pdo) {
    $stmt = $pdo->query("SELECT * FROM time_settings WHERE id = 1");
    $settings = $stmt->fetch();
    
    if (!$settings) {
        // Default settings if not found in database
        return [
            'morning_start' => '06:00:00',
            'morning_end' => '09:00:00',
            'morning_late_threshold' => '08:30:00',
            'afternoon_start' => '16:00:00',
            'afternoon_end' => '16:30:00'
        ];
    }
    
    return $settings;
}

$time_settings = getTimeSettings($pdo);

// Parse database time settings
$morning_start = $time_settings['morning_start'];
$morning_end = $time_settings['morning_end'];
$morning_late_threshold = $time_settings['morning_late_threshold'];
$afternoon_start = $time_settings['afternoon_start'];
$afternoon_end = $time_settings['afternoon_end'];

// Format times for display
$morning_start_display = date('h:i A', strtotime($morning_start));
$morning_end_display = date('h:i A', strtotime($morning_end));
$afternoon_start_display = date('h:i A', strtotime($afternoon_start));
$afternoon_end_display = date('h:i A', strtotime($afternoon_end));

// Check if system is currently active
$is_morning_session = ($current_time_str >= $morning_start && $current_time_str <= $morning_end);
$is_afternoon_session = ($current_time_str >= $afternoon_start && $current_time_str <= $afternoon_end);
$system_active = $is_morning_session || $is_afternoon_session;

// Handle RFID Scan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rfid_uid'])) {
    $rfid_uid = trim($_POST['rfid_uid']);

    if (empty($rfid_uid)) {
        $message = '<div class="alert error">Please scan an RFID card.</div>';
    } else {
        // Look up the student by RFID number and get their current enrollment
        $stmt = $pdo->prepare("
            SELECT s.*, r.rfid_number, e.enrollment_id, e.grade_level, e.section_id, e.school_year,
                   sec.section_name, 
                   CONCAT_WS(' ', adv.firstname, adv.lastname) AS adviser_name
            FROM students s 
            INNER JOIN rfid r ON s.lrn = r.lrn 
            INNER JOIN enrollments e ON s.lrn = e.lrn
            LEFT JOIN sections sec ON e.section_id = sec.section_id
            LEFT JOIN advisers adv ON sec.adviser_id = adv.adviser_id
            WHERE r.rfid_number = :rfid_number 
              AND e.school_year = :school_year
            ORDER BY e.enrollment_id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'rfid_number' => $rfid_uid,
            'school_year' => $current_school_year
        ]);
        $student = $stmt->fetch();

        if ($student) {
            $student_info = $student;
            $student_lrn = $student['lrn'];
            $enrollment_id = $student['enrollment_id']; // Get enrollment ID

            // Validate enrollment
            if (!$enrollment_id) {
                $message = '<div class="alert error">Student is not enrolled for the current school year (' . $current_school_year . ').</div>';
            } else {
                // Check for today's attendance record using both LRN and enrollment_id
                $stmt_check = $pdo->prepare("
                    SELECT * FROM attendance
                    WHERE lrn = :lrn
                      AND enrollment_id = :enrollment_id
                      AND date = :current_date
                ");
                $stmt_check->execute([
                    'lrn' => $student_lrn, 
                    'enrollment_id' => $enrollment_id,
                    'current_date' => $current_date_str
                ]);
                $todays_record = $stmt_check->fetch();

                if ($is_morning_session) {
                    // --- TIME IN LOGIC ---
                    if ($todays_record) {
                        $message = '<div class="alert info">' . htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) . ' has already timed in today.</div>';
                    } else {
                        // Present if scanned before the late threshold; Late if after
                        $status = ($current_time_str < $morning_late_threshold) ? 'present' : 'late';

                        $stmt_insert = $pdo->prepare("
                            INSERT INTO attendance (lrn, enrollment_id, date, time_in, status)
                            VALUES (:lrn, :enrollment_id, :date, :time_in, :status)
                        ");
                        if ($stmt_insert->execute([
                            'lrn' => $student_lrn,
                            'enrollment_id' => $enrollment_id,
                            'date' => $current_date_str,
                            'time_in' => $current_datetime_str,
                            'status' => $status
                        ])) {
                            $message = '<div class="alert success">Time In recorded for ' . htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) . '. Status: ' . ucfirst($status) . '.</div>';
                            $attendance_recorded = true;
                            $last_action_time = $current_datetime_str;
                            $last_action_type = "Time In";
                            $last_status = $status;
                        } else {
                            $message = '<div class="alert error">Error recording Time In.</div>';
                        }
                    }
                } elseif ($is_afternoon_session) {
                    // --- TIME OUT LOGIC ---
                    if (!$todays_record) {
                        $message = '<div class="alert error">Cannot Time Out. ' . htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) . ' did not time in this morning.</div>';
                    } elseif ($todays_record['time_out'] !== null) {
                        $message = '<div class="alert info">' . htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) . ' has already timed out today.</div>';
                    } else {
                        $stmt_update = $pdo->prepare("
                            UPDATE attendance
                            SET time_out = :time_out
                            WHERE attendance_id = :attendance_id
                        ");
                        if ($stmt_update->execute([
                            'time_out' => $current_datetime_str,
                            'attendance_id' => $todays_record['attendance_id']
                        ])) {
                            $message = '<div class="alert success">Time Out recorded successfully for ' . htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) . '.</div>';
                            $attendance_recorded = true;
                            $last_action_time = $current_datetime_str;
                            $last_action_type = "Time Out";
                        } else {
                            $message = '<div class="alert error">Error recording Time Out.</div>';
                        }
                    }
                } else {
                    // --- SYSTEM INACTIVE ---
                    $message = '<div class="alert error">The attendance system is currently inactive. Please try again during the active hours ('. $morning_start_display .' - '. $morning_end_display .' for Time In or '. $afternoon_start_display .' - '. $afternoon_end_display .' for Time Out).</div>';
                }
            }
        } else {
            $message = '<div class="alert error">RFID card not registered or student not enrolled for current school year.</div>';
        }
    }
}

// --- Automated Task to Mark Absences ---
$current_time = date('H:i:s');
if ($current_time > $afternoon_end) {
    $stmt_absent = $pdo->prepare("
        UPDATE attendance
        SET status = 'absent'
        WHERE date = :current_date
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-blue: #2c7be5;
            --light-blue: #e8f0fe;
            --dark-blue: #1c57b9;
            --text-dark: #344767;
            --text-medium: #67748e;
            --text-light: #9ba8b9;
            --success-green: #28a745;
            --error-red: #dc3545;
            --info-blue: #17a2b8;
            --warning-orange: #ffc107;
            --border-color: #e2e8f0;
            --box-shadow: 0 4px 6px rgba(0,0,0,0.1), 0 1px 3px rgba(0,0,0,0.08);
            --container-bg: rgba(255, 255, 255, 0.80);
            --background-overlay: rgba(0, 0, 0, 0.45);
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            color: var(--text-medium);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: linear-gradient(var(--background-overlay), var(--background-overlay)), url('img/cover.jpg');
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            background-position: center center;
        }

        .container {
            max-width: 650px;
            width: 95%;
            background-color: var(--container-bg);
            padding: 40px;
            border-radius: 16px;
            box-shadow: var(--box-shadow);
            box-sizing: border-box;
            text-align: center;
            position: relative;
            z-index: 10;
        }

        .header-container {
            margin-bottom: 35px;
        }
        .header-logo {
            max-height: 90px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 50%;
            background-color: #fff;
            padding: 5px;
        }
        h1 {
            color: var(--primary-blue);
            font-size: 1.5em; 
            font-weight: 700;
            margin: 0;
            line-height: 0.1; 
        }
        h1 .sub-heading {
            display: block;
            font-size: 0.6em; 
            font-weight: 500;
            color: var(--text-dark);
            margin-top: 20px;
        }

        .live-datetime {
            font-size: 1.0em; 
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 17px; 
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        .live-datetime .date-display {
            font-size: 0.9em;
            color: var(--text-medium);
        }
        .live-datetime .time-display {
            font-size: 1.5em; 
            font-weight: 700;
            color: var(--primary-blue);
        }

        .system-status {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px; 
            font-weight: 600;
            font-size: 0.9em;
        }
        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            display: inline-block;
            box-shadow: 0 0 0 2px rgba(0,0,0,0.1);
        }
        .status-dot.active {
            background-color: var(--success-green);
        }
        .status-dot.inactive {
            background-color: var(--error-red);
        }
        
        .active-hours {
            font-size: 0.9em;
            color: var(--text-light);
            margin-bottom: 10px;
        }

        .form-group {
            margin-bottom: 30px;
        }
        .inline-form {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        .inline-form input[type="text"] {
            flex-grow: 1;
            max-width: 300px;
            padding: 15px 10px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            font-size: 0.8em;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
        }
        .inline-form input[type="text"]:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 4px var(--light-blue);
            outline: none;
        }

        .form-group button {
            padding: 15px 25px;
            background-color: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.8em;
            font-weight: 600;
            transition: background-color 0.2s ease, transform 0.1s ease, box-shadow 0.2s ease;
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
            min-width: 90px;
        }
        .form-group button:hover {
            background-color: var(--dark-blue);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }
        .form-group button:active {
            transform: translateY(2px);
            box-shadow: 0 3px 6px rgba(0,0,0,0.1);
        }

        .alert {
            padding: 18px;
            margin-bottom: 25px;
            border-radius: 10px;
            font-size: 0.8em;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            line-height: 1.4;
            border-left: 5px solid;
            text-align: left;
        }
        .alert.success {
            background-color: #e6ffed;
            color: var(--success-green);
            border-color: var(--success-green);
        }
        .alert.error {
            background-color: #ffe6e6;
            color: var(--error-red);
            border-color: var(--error-red);
        }
        .alert.info {
            background-color: #e6f7ff;
            color: var(--info-blue);
            border-color: var(--info-blue);
        }

        .status-present { color: var(--success-green); font-weight: 700; }
        .status-late { color: var(--warning-orange); font-weight: 700; }
        .status-absent { color: var(--error-red); font-weight: 700; }

        .scan-modal {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .scan-modal-content {
            background: #fff;
            padding: 40px 50px;
            border-radius: 20px;
            min-width: 380px;
            max-width: 90%;
            text-align: left;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: appear 0.3s ease-out;
        }
        @keyframes appear {
            from { opacity: 0; transform: scale(0.9) translateY(-20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .scan-modal-content h3 {
            margin-top: 0;
            color: var(--primary-blue);
            font-size: 2em;
            margin-bottom: 25px;
            border-bottom: 2px solid var(--light-blue);
            padding-bottom: 15px;
        }
        .scan-modal-content p {
            font-size: 1.2em;
            margin: 12px 0;
            color: var(--text-dark);
            display: flex;
            justify-content: space-between;
        }
        .scan-modal-content p strong {
            color: var(--text-dark);
            font-weight: 600;
            flex-shrink: 0;
            margin-right: 15px;
        }
        .scan-modal-content p span {
            text-align: right;
            flex-grow: 1;
        }

        @media (max-width: 768px) {
            .container {
                margin: 30px auto;
                padding: 25px;
            }
            .header-logo {
                max-height: 90px;
            }
            h1 {
                font-size: 2em;
            }
            h1 .sub-heading {
                font-size: 0.7em;
            }
            .live-datetime {
                font-size: 1em;
            }
            .live-datetime .time-display {
                font-size: 1.3em;
            }
            .inline-form {
                flex-direction: column;
                gap: 10px;
            }
            .inline-form input[type="text"],
            .form-group button {
                width: 100%;
                max-width: unset;
            }
            .alert {
                font-size: 0.95em;
                padding: 15px;
            }
            .scan-modal-content {
                padding: 30px 40px;
                min-width: unset;
            }
            .scan-modal-content h3 {
                font-size: 1.5em;
                margin-bottom: 15px;
            }
            .scan-modal-content p {
                font-size: 1.1em;
                flex-direction: column;
                align-items: flex-start;
            }
            .scan-modal-content p span {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-container">
            <img src="img/logo.jpg" alt="School Logo" class="header-logo">
            <h1>
                San Isidro National High School
                <span class="sub-heading">RFID Attendance Monitoring System</span>
            </h1>
        </div>

        <div class="live-datetime">
            <div class="date-display">
                <?php echo $current_day_name . ', ' . $formatted_date; ?>
            </div>
            <div class="time-display" id="currentTime">
                <?php echo date('h:i:s A'); ?>
            </div>
        </div>

        <div class="system-status">
            <span class="status-dot <?php echo $system_active ? 'active' : 'inactive'; ?>"></span>
            System Status:
            <span class="<?php echo $system_active ? 'status-present' : 'status-absent'; ?>">
                <?php echo $system_active ? ' Active' : ' Inactive'; ?>
            </span>
        </div>
        
        <div class="active-hours">
            (Time In: <?php echo $morning_start_display . ' - ' . $morning_end_display; ?> | Time Out: <?php echo $afternoon_start_display . ' - ' . $afternoon_end_display; ?>)
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
                <p><strong>Name:</strong> <span><?php echo htmlspecialchars($student_info['firstname'] . ' ' . $student_info['lastname']); ?></span></p>
                <p><strong>LRN:</strong> <span><?php echo htmlspecialchars($student_info['lrn']); ?></span></p>
                <p><strong>Grade & Section:</strong> <span><?php echo htmlspecialchars($student_info['grade_level'] . ' - ' . ($student_info['section_name'] ?? 'N/A')); ?></span></p>
                <p><strong>School Year:</strong> <span><?php echo htmlspecialchars($student_info['school_year']); ?></span></p>
                <p><strong>Action:</strong> <span><?php echo htmlspecialchars($last_action_type); ?></span></p>
                <p><strong>Time:</strong> <span><?php echo htmlspecialchars(date("g:i:s A", strtotime($last_action_time))); ?></span></p>
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
        function updateClock() {
            const now = new Date();
            const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
            const timeString = now.toLocaleTimeString('en-US', timeOptions);
            document.getElementById('currentTime').textContent = timeString;
        }

        setInterval(updateClock, 1000);
        updateClock();

        function closeModal() {
            var modal = document.getElementById('scanModal');
            if(modal) modal.style.display = 'none';
            var input = document.getElementById('rfid_uid');
            if(input) { input.focus(); input.value = ''; }
        }
        <?php if ($student_info && $attendance_recorded): ?>
        setTimeout(closeModal, 2500);
        <?php endif; ?>
        window.onload = function() {
            var rfidInput = document.getElementById('rfid_uid');
            if (rfidInput) {
                rfidInput.focus();
                rfidInput.value = '';
            }
        };
    </script>
</body>
</html>