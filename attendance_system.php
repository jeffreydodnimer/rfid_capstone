<?php
// --- CONFIGURATION AND HELPERS (USED BY BOTH PAGE LOAD AND AJAX) ---

// Database Configuration
$host = 'localhost';
$db_name = 'rfid_capstone';
$username = 'root';
$password = '';

// Establish Database Connection (reusable function)
function getDbConnection($h, $db, $u, $p)
{
    try {
        $pdo = new PDO("mysql:host=$h;dbname=$db;charset=utf8", $u, $p);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        return null; // Return null on failure for safe checking
    }
}

// Function to send SMS notification (now accepts phone number as parameter)
function sendSMS($studentName, $actionType, $time, $phone)
{
    // Skip SMS if no phone provided
    if (empty($phone)) {
        error_log("SMS skipped: No guardian phone number found for student.");
        return false;
    }

    $ch = curl_init();
    
    // Customize message based on action type
    if ($actionType === 'Time In') {
        $message = "Your child $studentName timed in today at $time.";
    } else {
        $message = "Your child $studentName timed out today at $time.";
    }
    
    $parameters = array(
        'apikey' => '', // Your API KEY
        'number' => $phone, // Use dynamic guardian phone number
        'message' => $message,
        'sendername' => 'RNCTLCI'
    );
    
    curl_setopt($ch, CURLOPT_URL, 'https://semaphore.co/api/v4/messages');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($parameters));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $output = curl_exec($ch);
    
    // Log for debugging (optional)
    if (curl_error($ch)) {
        error_log("SMS Curl error: " . curl_error($ch));
    } else {
        error_log("SMS sent successfully to $phone: " . $output);
    }
    
    curl_close($ch);
    
    return $output;
}

// Get time settings from database (with defaults)
function getTimeSettings(PDO $pdo)
{
    // Prefer id=1, fallback to the first row if not present
    $stmt = $pdo->query("SELECT * FROM time_settings WHERE id = 1");
    $row = $stmt->fetch();
    if (!$row) {
        $stmt = $pdo->query("SELECT * FROM time_settings ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch();
    }
    // Define default day settings (Mon-Fri allowed, Sat/Sun disabled)
    $default_settings = [
        'morning_start' => '06:00:00',
        'morning_end' => '09:00:00',
        'morning_late_threshold' => '08:30:00',
        'afternoon_start' => '16:00:00',
        'afternoon_end' => '16:30:00',
        'allow_mon' => 1,
        'allow_tue' => 1,
        'allow_wed' => 1,
        'allow_thu' => 1,
        'allow_fri' => 1,
        'allow_sat' => 0,
        'allow_sun' => 0
    ];

    if ($row) {
        // Merge with defaults to ensure all keys exist
        return array_merge($default_settings, $row);
    }
    // If no settings row at all, return the full default set
    return $default_settings;
}

// Check if attendance is allowed for the current day based on settings
function isAttendanceAllowedToday(array $settings): bool
{
    // PHP's date('N') returns 1 (for Monday) through 7 (for Sunday)
    $dow = (int) date('N');
    $map = [1 => 'allow_mon', 2 => 'allow_tue', 3 => 'allow_wed', 4 => 'allow_thu', 5 => 'allow_fri', 6 => 'allow_sat', 7 => 'allow_sun'];
    $col = $map[$dow];
    return !empty($settings[$col]);
}

// --- AJAX ENDPOINT FOR REAL-TIME STATUS POLLING ---
if (isset($_GET['action']) && $_GET['action'] === 'get_status') {
    header('Content-Type: application/json');
    date_default_timezone_set('Asia/Manila');

    $pdo = getDbConnection($host, $db_name, $username, $password);
    if (!$pdo) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed.']);
        exit;
    }

    $time_settings = getTimeSettings($pdo);
    $is_day_allowed = isAttendanceAllowedToday($time_settings);
    $current_time_str = date('H:i:s');

    $is_morning_session = ($current_time_str >= $time_settings['morning_start'] && $current_time_str <= $time_settings['morning_end']);
    $is_afternoon_session = ($current_time_str >= $time_settings['afternoon_start'] && $current_time_str <= $time_settings['afternoon_end']);
    $system_active = ($is_morning_session || $is_afternoon_session) && $is_day_allowed;

    $response = [
        'system_active' => $system_active,
        'is_day_allowed' => $is_day_allowed,
        'current_day_name' => date('l'),
        'settings' => [
            'morningStart' => $time_settings['morning_start'],
            'morningEnd' => $time_settings['morning_end'],
            'afternoonStart' => $time_settings['afternoon_start'],
            'afternoonEnd' => $time_settings['afternoon_end'],
        ],
        'display' => [
            'morning_start_display' => date('h:i A', strtotime($time_settings['morning_start'])),
            'morning_end_display' => date('h:i A', strtotime($time_settings['morning_end'])),
            'afternoon_start_display' => date('h:i A', strtotime($time_settings['afternoon_start'])),
            'afternoon_end_display' => date('h:i A', strtotime($time_settings['afternoon_end'])),
        ]
    ];

    echo json_encode($response);
    exit;
}

// --- MAIN PAGE LOGIC (IF NOT an 'action=get_status' request) ---

$pdo = getDbConnection($host, $db_name, $username, $password);
if (!$pdo) {
    die("Database connection failed. Please check your configuration and ensure the database server is running.");
}

date_default_timezone_set('Asia/Manila');

// Initialize variables for the page
$message = '';
$student_info = null;
$attendance_recorded = false;
$last_action_time = '';
$last_action_type = '';
$last_status = '';

// Get current datetime details for display and logic
$current_datetime_str = date('Y-m-d H:i:s');
$current_time_str = date('H:i:s');
$current_date_str = date('Y-m-d');
$current_day_name = date('l');
$formatted_date = date('F j, Y');
$current_school_year = '2024-2025';

// Fetch all settings for initial page load
$time_settings = getTimeSettings($pdo);

// Parse and format time settings for display
$morning_start = $time_settings['morning_start'];
$morning_end = $time_settings['morning_end'];
$morning_late_threshold = $time_settings['morning_late_threshold'];
$afternoon_start = $time_settings['afternoon_start'];
$afternoon_end = $time_settings['afternoon_end'];

$morning_start_display = date('h:i A', strtotime($morning_start));
$morning_end_display = date('h:i A', strtotime($morning_end));
$afternoon_start_display = date('h:i A', strtotime($afternoon_start));
$afternoon_end_display = date('h:i A', strtotime($afternoon_end));

// Check initial system status for page load
$is_day_allowed = isAttendanceAllowedToday($time_settings);
$is_morning_session = ($current_time_str >= $morning_start && $current_time_str <= $morning_end);
$is_afternoon_session = ($current_time_str >= $afternoon_start && $current_time_str <= $afternoon_end);
$system_active = ($is_morning_session || $is_afternoon_session) && $is_day_allowed;

// Handle RFID Scan (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rfid_uid'])) {
    $rfid_uid = trim($_POST['rfid_uid']);

    // Server-side validation is crucial
    if (!$is_day_allowed) {
        $message = '<div class="alert error">Attendance is not allowed on ' . $current_day_name . '.</div>';
    } elseif (!$system_active) {
        $message = '<div class="alert error">The attendance system is currently inactive. Please scan during active hours.</div>';
    } elseif (empty($rfid_uid)) {
        $message = '<div class="alert error">Please scan an RFID card.</div>';
    } else {
        // Look up student by RFID
        $stmt = $pdo->prepare("
            SELECT s.*, r.rfid_number, e.enrollment_id, e.grade_level, e.section_id, e.school_year,
                   sec.section_name, 
                   CONCAT_WS(' ', adv.firstname, adv.lastname) AS adviser_name
            FROM students s 
            INNER JOIN rfid r ON s.lrn = r.lrn 
            INNER JOIN enrollments e ON s.lrn = e.lrn
            LEFT JOIN sections sec ON e.section_id = sec.section_id
            LEFT JOIN advisers adv ON sec.adviser_id = adv.adviser_id
            WHERE r.rfid_number = :rfid_number AND e.school_year = :school_year
            ORDER BY e.enrollment_id DESC LIMIT 1
        ");
        $stmt->execute(['rfid_number' => $rfid_uid, 'school_year' => $current_school_year]);
        $student = $stmt->fetch();

        if ($student) {
            $student_info = $student;
            $student_lrn = $student['lrn'];
            $enrollment_id = $student['enrollment_id'];

            if (!$enrollment_id) {
                $message = '<div class="alert error">Student is not enrolled for the current school year (' . $current_school_year . ').</div>';
            } else {
                // Fetch guardian's contact number (assuming one guardian per student)
                $stmt_guardian = $pdo->prepare("SELECT contact_number FROM guardians WHERE lrn = :lrn LIMIT 1");
                $stmt_guardian->execute(['lrn' => $student_lrn]);
                $guardian = $stmt_guardian->fetch();
                $parent_phone = $guardian ? $guardian['contact_number'] : null;

                // Check for today's attendance record
                $stmt_check = $pdo->prepare("SELECT * FROM attendance WHERE lrn = :lrn AND enrollment_id = :enrollment_id AND date = :current_date");
                $stmt_check->execute(['lrn' => $student_lrn, 'enrollment_id' => $enrollment_id, 'current_date' => $current_date_str]);
                $todays_record = $stmt_check->fetch();

                if ($is_morning_session) { // --- TIME IN ---
                    if ($todays_record) {
                        $message = '<div class="alert info">' . htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) . ' has already timed in today.</div>';
                    } else {
                        $status = ($current_time_str < $morning_late_threshold) ? 'present' : 'late';
                        $stmt_insert = $pdo->prepare("INSERT INTO attendance (lrn, enrollment_id, date, time_in, status) VALUES (:lrn, :enrollment_id, :date, :time_in, :status)");
                        
                        if ($stmt_insert->execute(['lrn' => $student_lrn, 'enrollment_id' => $enrollment_id, 'date' => $current_date_str, 'time_in' => $current_datetime_str, 'status' => $status])) {
                            // Send SMS for Time In using guardian's phone
                            $studentFullName = $student['firstname'] . ' ' . $student['lastname'];
                            $formattedTime = date('h:i A', strtotime($current_datetime_str));
                            sendSMS($studentFullName, 'Time In', $formattedTime, $parent_phone);
                            
                            $message = '<div class="alert success">Time In: ' . htmlspecialchars($student['firstname']) . '. Status: ' . ucfirst($status) . '.</div>';
                            $attendance_recorded = true;
                            $last_action_time = $current_datetime_str;
                            $last_action_type = "Time In";
                            $last_status = $status;
                        } else {
                            $message = '<div class="alert error">Error recording Time In.</div>';
                        }
                    }
                } elseif ($is_afternoon_session) { // --- TIME OUT ---
                    if (!$todays_record) {
                        $message = '<div class="alert error">Cannot Time Out. ' . htmlspecialchars($student['firstname']) . ' did not time in this morning.</div>';
                    } elseif ($todays_record['time_out'] !== null) {
                        $message = '<div class="alert info">' . htmlspecialchars($student['firstname']) . ' has already timed out today.</div>';
                    } else {
                        $stmt_update = $pdo->prepare("UPDATE attendance SET time_out = :time_out WHERE attendance_id = :attendance_id");
                        if ($stmt_update->execute(['time_out' => $current_datetime_str, 'attendance_id' => $todays_record['attendance_id']])) {
                            // Send SMS for Time Out using guardian's phone
                            $studentFullName = $student['firstname'] . ' ' . $student['lastname'];
                            $formattedTime = date('h:i A', strtotime($current_datetime_str));
                            sendSMS($studentFullName, 'Time Out', $formattedTime, $parent_phone);
                            
                            $message = '<div class="alert success">Time Out recorded for ' . htmlspecialchars($student['firstname']) . '.</div>';
                            $attendance_recorded = true;
                            $last_action_time = $current_datetime_str;
                            $last_action_type = "Time Out";
                        } else {
                            $message = '<div class="alert error">Error recording Time Out.</div>';
                        }
                    }
                }
            }
        } else {
            $message = '<div class="alert error">RFID card not registered or student not enrolled.</div>';
        }
    }

    // This is a form submission, so it must return JSON
    if (isset($_POST['ajax'])) {
        $response = [
            'message' => $message,
            'attendance_recorded' => $attendance_recorded,
            'student_info' => $student_info,
            'last_action_type' => $last_action_type,
            'last_action_time' => $last_action_time ? date("g:i:s A", strtotime($last_action_time)) : '',
            'last_status' => $last_status,
        ];
        header('Content-Type: application/json'); // Ensure JSON header for AJAX responses
        echo json_encode($response);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Attendance Monitoring System</title>
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
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.08);
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
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
            box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.1);
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
            transition: all 0.3s ease;
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
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
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
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            min-width: 90px;
        }

        .form-group button:hover {
            background-color: var(--dark-blue);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .form-group button:active {
            transform: translateY(2px);
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.1);
        }

        .alert {
            padding: 18px;
            margin-bottom: 25px;
            border-radius: 10px;
            font-size: 0.8em;
            font-weight: 500;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
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

        .status-present {
            color: var(--success-green);
            font-weight: 700;
        }

        .status-late {
            color: var(--warning-orange);
            font-weight: 700;
        }

        .status-absent {
            color: var(--error-red);
            font-weight: 700;
        }

        .scan-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
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
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: appear 0.3s ease-out;
        }

        @keyframes appear {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
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
    </style>
</head>

<body>
    <div class="container">
        <div class="header-container">
            <img src="img/logo.jpg" alt="School Logo" class="header-logo">
            <h1>San Isidro National High School <span class="sub-heading">RFID Attendance Monitoring System</span></h1>
        </div>

        <div class="live-datetime">
            <div class="date-display"><?php echo $current_day_name . ', ' . $formatted_date; ?></div>
            <div class="time-display" id="currentTime"><?php echo date('h:i:s A'); ?></div>
        </div>

        <div class="system-status" id="systemStatus">
            <span class="status-dot <?php echo $system_active ? 'active' : 'inactive'; ?>"></span>
            System Status: <span
                class="<?php echo $system_active ? 'status-present' : 'status-absent'; ?>"><?php echo $system_active ? ' Active' : ' Inactive'; ?></span>
        </div>

        <div class="active-hours">
            (Time In: <?php echo $morning_start_display . ' - ' . $morning_end_display; ?> | Time Out:
            <?php echo $afternoon_start_display . ' - ' . $afternoon_end_display; ?>)
            <?php if (!$is_day_allowed): ?>
                <br><span style="color:var(--error-red); font-weight:bold;">(Attendance is disabled on
                    <?php echo $current_day_name; ?>)</span>
            <?php endif; ?>
        </div>

        <div id="messageArea"><?php echo $message; ?></div>

        <form id="rfidForm" class="form-group inline-form">
            <input type="text" id="rfid_uid" name="rfid_uid" placeholder="Scan RFID Card..." required autofocus
                autocomplete="off">
            <input type="hidden" name="ajax" value="1">
            <button type="submit">Submit</button>
        </form>
    </div>

    <div class="scan-modal" id="scanModal" style="display: none;">
        <div class="scan-modal-content" id="scanModalContent"></div>
    </div>

    <script>
        // Store initial time settings from PHP to a JavaScript object
        const timeSettings = {
            morningStart: '<?php echo $morning_start; ?>',
            morningEnd: '<?php echo $morning_end; ?>',
            afternoonStart: '<?php echo $afternoon_start; ?>',
            afternoonEnd: '<?php echo $afternoon_end; ?>',
            isDayAllowed: <?php echo json_encode($is_day_allowed); ?>,
            morningStartDisplay: '<?php echo $morning_start_display; ?>',
            morningEndDisplay: '<?php echo $morning_end_display; ?>',
            afternoonStartDisplay: '<?php echo $afternoon_start_display; ?>',
            afternoonEndDisplay: '<?php echo $afternoon_end_display; ?>',
            currentDayName: '<?php echo $current_day_name; ?>'
        };

        // Get current time as HH:mm:ss for easy comparison
        function getCurrentTimeStr() { return new Date().toTimeString().split(' ')[0]; }

        // Check if the system is active based on the client-side timeSettings object
        function isSystemActive() {
            if (!timeSettings.isDayAllowed) return false;
            const currentTime = getCurrentTimeStr();
            const isMorning = currentTime >= timeSettings.morningStart && currentTime <= timeSettings.morningEnd;
            const isAfternoon = currentTime >= timeSettings.afternoonStart && currentTime <= timeSettings.afternoonEnd;
            return isMorning || isAfternoon;
        }

        // Main function to update all dynamic UI elements, runs every second
        function updateDynamicElements() {
            // 1. Update Live Clock
            document.getElementById('currentTime').textContent = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });

            // 2. Update System Status (Active/Inactive)
            const active = isSystemActive();
            const statusDot = document.querySelector('#systemStatus .status-dot');
            const statusText = document.querySelector('#systemStatus span:last-of-type');
            if (statusDot.classList.contains(active ? 'inactive' : 'active')) {
                statusDot.className = `status-dot ${active ? 'active' : 'inactive'}`;
                statusText.className = active ? 'status-present' : 'status-absent';
                statusText.textContent = active ? ' Active' : ' Inactive';
            }

            // 3. Update the displayed time ranges and day-of-week warning
            const activeHoursDiv = document.querySelector('.active-hours');
            let hoursHTML = `(Time In: ${timeSettings.morningStartDisplay} - ${timeSettings.morningEndDisplay} | Time Out: ${timeSettings.afternoonStartDisplay} - ${timeSettings.afternoonEndDisplay})`;
            if (!timeSettings.isDayAllowed) {
                hoursHTML += `<br><span style="color:var(--error-red); font-weight:bold;">(Attendance is disabled on ${timeSettings.currentDayName})</span>`;
            }
            if (activeHoursDiv.innerHTML !== hoursHTML) {
                activeHoursDiv.innerHTML = hoursHTML;
            }
        }

        // Function to poll server for latest settings, runs periodically
        async function pollForSettings() {
            try {
                const response = await fetch('?action=get_status'); // Use GET request to trigger our AJAX endpoint
                if (!response.ok) return;
                const data = await response.json();

                // Update the global JS settings object with fresh data
                Object.assign(timeSettings, {
                    ...data.settings,
                    isDayAllowed: data.is_day_allowed,
                    currentDayName: data.current_day_name,
                    morningStartDisplay: data.display.morning_start_display,
                    morningEndDisplay: data.display.morning_end_display,
                    afternoonStartDisplay: data.display.afternoon_start_display,
                    afternoonEndDisplay: data.display.afternoon_end_display
                });
            } catch (e) { console.error("Polling error:", e); }
        }

        // --- Event Listeners and Timers ---

        document.getElementById('rfidForm').addEventListener('submit', function (e) {
            e.preventDefault();
            const rfidInput = document.getElementById('rfid_uid');

            // Client-side check provides instant feedback if system is inactive
            if (!isSystemActive()) {
                let msg = !timeSettings.isDayAllowed
                    ? `Attendance is not allowed on ${timeSettings.currentDayName}.`
                    : `System is inactive. Please scan during active hours.`;
                document.getElementById('messageArea').innerHTML = `<div class="alert error">${msg}</div>`;
                rfidInput.value = '';
                rfidInput.focus();
                return;
            }

            fetch('', { method: 'POST', body: new FormData(this) })
                .then(res => res.json())
                .then(data => {
                    document.getElementById('messageArea').innerHTML = data.message;
                    if (data.attendance_recorded) {
                        let modalContent = `<h3>Student Details</h3>` +
                            `<p><strong>Name:</strong> <span>${data.student_info.firstname} ${data.student_info.lastname}</span></p>` +
                            `<p><strong>LRN:</strong> <span>${data.student_info.lrn}</span></p>` +
                            `<p><strong>Grade & Section:</strong> <span>${data.student_info.grade_level} - ${data.student_info.section_name || 'N/A'}</span></p>` +
                            `<p><strong>Action:</strong> <span>${data.last_action_type}</span></p>` +
                            `<p><strong>Time:</strong> <span>${data.last_action_time}</span></p>` +
                            (data.last_action_type === 'Time In' ? `<p><strong>Status:</strong> <span class="status-${data.last_status.toLowerCase()}">${data.last_status.charAt(0).toUpperCase() + data.last_status.slice(1)}</span></p>` : '');

                        document.getElementById('scanModalContent').innerHTML = modalContent;
                        document.getElementById('scanModal').style.display = 'flex';
                        setTimeout(() => { document.getElementById('scanModal').style.display = 'none'; }, 2500);
                    }
                    rfidInput.value = '';
                    rfidInput.focus();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('messageArea').innerHTML = '<div class="alert error">A server error occurred. Please try again.</div>';
                    // Do not clear input on catch to allow retry without re-scanning, but refocus
                    rfidInput.focus();
                });
        });

        // On page load, set up timers and initial state
        window.addEventListener('load', function () {
            document.getElementById('rfid_uid').focus();
            updateDynamicElements(); // Run once for initial display
            pollForSettings();       // Fetch latest settings on load

            setInterval(updateDynamicElements, 1000);   // Update UI every second
            setInterval(pollForSettings, 5000); // Poll for new settings every 5 seconds
        });
    </script>
</body>

</html>