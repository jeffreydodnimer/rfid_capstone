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

// Initialize message variable
$message = '';

// Ensure time_settings table exists (with days columns)
try {
    $pdo->query("SELECT 1 FROM time_settings LIMIT 1");
} catch (PDOException $e) {
    // Table doesn't exist, create it with day flags
    $pdo->exec("
        CREATE TABLE time_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            morning_start TIME NOT NULL,
            morning_end TIME NOT NULL,
            morning_late_threshold TIME NOT NULL,
            afternoon_start TIME NOT NULL,
            afternoon_end TIME NOT NULL,
            allow_mon TINYINT(1) NOT NULL DEFAULT 1,
            allow_tue TINYINT(1) NOT NULL DEFAULT 1,
            allow_wed TINYINT(1) NOT NULL DEFAULT 1,
            allow_thu TINYINT(1) NOT NULL DEFAULT 1,
            allow_fri TINYINT(1) NOT NULL DEFAULT 1,
            allow_sat TINYINT(1) NOT NULL DEFAULT 0,
            allow_sun TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Insert default values (Mon–Fri allowed, Sat/Sun disabled)
    $stmt = $pdo->prepare("
        INSERT INTO time_settings
        (morning_start, morning_end, morning_late_threshold, afternoon_start, afternoon_end,
         allow_mon, allow_tue, allow_wed, allow_thu, allow_fri, allow_sat, allow_sun)
        VALUES (?, ?, ?, ?, ?, 1, 1, 1, 1, 1, 0, 0)
    ");
    $stmt->execute(['06:00:00', '09:00:00', '08:30:00', '16:00:00', '16:30:00']);
}

// Ensure days columns exist if table is older
function ensureDayColumns(PDO $pdo) {
    $cols = [
        'allow_mon' => 1, 'allow_tue' => 1, 'allow_wed' => 1,
        'allow_thu' => 1, 'allow_fri' => 1, 'allow_sat' => 0, 'allow_sun' => 0
    ];
    foreach ($cols as $col => $default) {
        $check = $pdo->prepare("SHOW COLUMNS FROM time_settings LIKE :col");
        $check->execute([':col' => $col]);
        if (!$check->fetch()) {
            $pdo->exec("ALTER TABLE time_settings ADD COLUMN $col TINYINT(1) NOT NULL DEFAULT $default");
        }
    }
}
ensureDayColumns($pdo);

// Get current settings
function getTimeSettings(PDO $pdo) {
    // Prefer id=1, fallback to the first row if not present
    $stmt = $pdo->query("SELECT * FROM time_settings WHERE id = 1");
    $row = $stmt->fetch();
    if (!$row) {
        $stmt = $pdo->query("SELECT * FROM time_settings ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch();
    }
    return $row;
}

$time_settings = getTimeSettings($pdo);

// Helper: validate HH:MM
function validateTimeFormat($time) {
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) return false;
    list($h,$m) = explode(':', $time);
    return is_numeric($h) && is_numeric($m) && $h >= 0 && $h <= 23 && $m >= 0 && $m <= 59;
}

// Helper: is attendance allowed today based on settings
function isAttendanceAllowedToday(array $settings): bool {
    // PHP: 1 (for Monday) through 7 (for Sunday)
    $dow = (int)date('N');
    $map = [
        1 => 'allow_mon', 2 => 'allow_tue', 3 => 'allow_wed', 4 => 'allow_thu',
        5 => 'allow_fri', 6 => 'allow_sat', 7 => 'allow_sun'
    ];
    $col = $map[$dow];
    return !empty($settings[$col]);
}

// Handle form submission to update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    // Validate time inputs
    $morning_start = trim((string)filter_input(INPUT_POST, 'morning_start', FILTER_UNSAFE_RAW));
    $morning_end = trim((string)filter_input(INPUT_POST, 'morning_end', FILTER_UNSAFE_RAW));
    $morning_late_threshold = trim((string)filter_input(INPUT_POST, 'morning_late_threshold', FILTER_UNSAFE_RAW));
    $afternoon_start = trim((string)filter_input(INPUT_POST, 'afternoon_start', FILTER_UNSAFE_RAW));
    $afternoon_end = trim((string)filter_input(INPUT_POST, 'afternoon_end', FILTER_UNSAFE_RAW));

    $is_valid = true;
    $error_fields = [];

    foreach ([
        'morning_start' => $morning_start,
        'morning_end' => $morning_end,
        'morning_late_threshold' => $morning_late_threshold,
        'afternoon_start' => $afternoon_start,
        'afternoon_end' => $afternoon_end
    ] as $field => $value) {
        if (!validateTimeFormat($value)) {
            $is_valid = false;
            $error_fields[] = $field;
        }
    }

    if (!$is_valid) {
        $message = '<div class="alert error"><i class="icon">⚠️</i> Please enter valid time values in 24-hour format (HH:MM) for: ' . implode(', ', $error_fields) . '</div>';
    } else {
        // Check logical time sequence
        $ms = strtotime($morning_start . ':00');
        $mt = strtotime($morning_late_threshold . ':00');
        $me = strtotime($morning_end . ':00');
        $as = strtotime($afternoon_start . ':00');
        $ae = strtotime($afternoon_end . ':00');

        if ($ms >= $me) {
            $is_valid = false;
            $message = '<div class="alert error"><i class="icon">❌</i> Morning start time must be before morning end time.</div>';
        } elseif ($mt >= $me) {
            $is_valid = false;
            $message = '<div class="alert error"><i class="icon">❌</i> Late threshold must be before morning end time.</div>';
        } elseif ($ms >= $mt) {
            $is_valid = false;
            $message = '<div class="alert error"><i class="icon">❌</i> Morning start time must be before late threshold.</div>';
        } elseif ($as >= $ae) {
            $is_valid = false;
            $message = '<div class="alert error"><i class="icon">❌</i> Afternoon start time must be before afternoon end time.</div>';
        }
    }

    // Gather allowed days (Mon–Sun). Default is unchecked=0.
    $allow_days = [
        'allow_mon' => isset($_POST['allow_mon']) ? 1 : 0,
        'allow_tue' => isset($_POST['allow_tue']) ? 1 : 0,
        'allow_wed' => isset($_POST['allow_wed']) ? 1 : 0,
        'allow_thu' => isset($_POST['allow_thu']) ? 1 : 0,
        'allow_fri' => isset($_POST['allow_fri']) ? 1 : 0,
        'allow_sat' => isset($_POST['allow_sat']) ? 1 : 0,
        'allow_sun' => isset($_POST['allow_sun']) ? 1 : 0,
    ];

    if ($is_valid) {
        // Add seconds to time values
        $morning_start .= ':00';
        $morning_end .= ':00';
        $morning_late_threshold .= ':00';
        $afternoon_start .= ':00';
        $afternoon_end .= ':00';

        // Ensure row with id=1 exists
        $exists = $pdo->query("SELECT COUNT(*) c FROM time_settings WHERE id=1")->fetch()['c'] ?? 0;
        if (!$exists) {
            $pdo->exec("INSERT INTO time_settings (id, morning_start, morning_end, morning_late_threshold, afternoon_start, afternoon_end,
                allow_mon, allow_tue, allow_wed, allow_thu, allow_fri, allow_sat, allow_sun)
                VALUES (1, '06:00:00','09:00:00','08:30:00','16:00:00','16:30:00',1,1,1,1,1,0,0)");
        }

        $stmt = $pdo->prepare("
            UPDATE time_settings
               SET morning_start = :morning_start,
                   morning_end = :morning_end,
                   morning_late_threshold = :morning_late_threshold,
                   afternoon_start = :afternoon_start,
                   afternoon_end = :afternoon_end,
                   allow_mon = :allow_mon,
                   allow_tue = :allow_tue,
                   allow_wed = :allow_wed,
                   allow_thu = :allow_thu,
                   allow_fri = :allow_fri,
                   allow_sat = :allow_sat,
                   allow_sun = :allow_sun
             WHERE id = 1
        ");

        $ok = $stmt->execute([
            'morning_start' => $morning_start,
            'morning_end' => $morning_end,
            'morning_late_threshold' => $morning_late_threshold,
            'afternoon_start' => $afternoon_start,
            'afternoon_end' => $afternoon_end,
            'allow_mon' => $allow_days['allow_mon'],
            'allow_tue' => $allow_days['allow_tue'],
            'allow_wed' => $allow_days['allow_wed'],
            'allow_thu' => $allow_days['allow_thu'],
            'allow_fri' => $allow_days['allow_fri'],
            'allow_sat' => $allow_days['allow_sat'],
            'allow_sun' => $allow_days['allow_sun'],
        ]);

        if ($ok) {
            $message = '<div class="alert success"><i class="icon">✅</i> Time settings updated successfully!</div>';
            $time_settings = getTimeSettings($pdo);
        } else {
            $message = '<div class="alert error"><i class="icon">❌</i> Error updating time settings.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Attendance Time Settings - San Isidro National High School</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
/* Styles trimmed for brevity; same as your version, unchanged except minor fit-and-finish */
:root {
    --primary-blue:#3b82f6; --light-blue:#dbeafe; --dark-blue:#1d4ed8; --text-dark:#1f2937;
    --text-medium:#6b7280; --text-light:#9ca3af; --success-green:#10b981; --error-red:#ef4444;
    --warning-orange:#f59e0b; --border-color:#e5e7eb; --box-shadow:0 10px 15px -3px rgba(0,0,0,.1),0 4px 6px -2px rgba(0,0,0,.05);
    --container-bg:#fff; --background:#f9fafb; --input-bg:#fff; --card-bg:#f8fafc;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',system-ui,Arial,sans-serif;background:var(--background);color:var(--text-medium);line-height:1.6;min-height:100vh;padding:15px}
.container{max-width:1200px;margin:0 auto;background:var(--container-bg);border-radius:20px;box-shadow:var(--box-shadow);overflow:hidden}
.header{background:linear-gradient(135deg,var(--primary-blue),var(--dark-blue));color:#fff;padding:30px 20px;text-align:center;position:relative}
.header h1{font-size:2.2em;font-weight:700;margin-bottom:10px;position:relative;z-index:1}
.header .subtitle{font-size:1em;opacity:.9;position:relative;z-index:1}
.header .icon{font-size:2.5em;margin-bottom:15px;display:block;position:relative;z-index:1}
.content{padding:20px}
.alert{padding:15px;margin-bottom:20px;border-radius:10px;font-weight:500;display:flex;align-items:center;box-shadow:0 4px 6px -1px rgba(0,0,0,.1)}
.alert .icon{font-size:1.1em;margin-right:10px}
.alert.success{background:linear-gradient(135deg,#ecfdf5,#d1fae5);color:var(--success-green);border-left:4px solid var(--success-green)}
.alert.error{background:linear-gradient(135deg,#fef2f2,#fee2e2);color:var(--error-red);border-left:4px solid var(--error-red)}
.current-settings{background:var(--card-bg);border-radius:14px;padding:20px;margin-bottom:25px;border:2px solid var(--border-color)}
.current-settings h3{color:var(--text-dark);font-size:1.3em;font-weight:600;margin-bottom:20px;display:flex;align-items:center}
.current-settings h3::before{content:"⚙️";margin-right:12px;font-size:1.1em}
.settings-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px}
.setting-card{background:#fff;padding:15px;border-radius:10px;border:1px solid var(--border-color);text-align:center}
.setting-icon{font-size:1.8em;margin-bottom:8px;color:var(--primary-blue)}
.setting-label{font-size:.8em;color:var(--text-light);margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px;font-weight:600}
.setting-value{font-size:1.2em;color:var(--text-dark);font-weight:700}
.form-section{margin-bottom:30px}
.section-header{display:flex;align-items:center;margin-bottom:20px;padding-bottom:10px;border-bottom:2px solid var(--border-color)}
.section-icon{font-size:1.4em;margin-right:12px;color:var(--primary-blue)}
.section-title{color:var(--text-dark);font-size:1.2em;font-weight:600}
.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:20px}
.form-group{position:relative}
.form-group label{display:block;margin-bottom:6px;font-weight:600;color:var(--text-dark);font-size:.9em}
.input-wrapper{position:relative}
.input-wrapper::before{content:"🕐";position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--text-light);z-index:1}
.form-group input{width:100%;padding:12px 12px 12px 40px;border:2px solid var(--border-color);border-radius:10px;background:var(--input-bg);font-size:.95em;font-weight:500;color:var(--text-dark)}
.form-group input:focus{border-color:var(--primary-blue);box-shadow:0 0 0 3px rgba(59,130,246,.1);outline:none}
.help-text{color:var(--text-light);font-size:.75em;margin-top:6px;font-style:italic}
.actions{margin-top:25px;display:flex;justify-content:space-between;align-items:center;gap:15px;padding-top:20px;border-top:2px solid var(--border-color)}
.btn{padding:12px 25px;border-radius:10px;font-size:.95em;font-weight:600;cursor:pointer;transition:all .3s;border:none;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;min-width:130px}
.btn i{margin-right:8px}
.btn-primary{background:linear-gradient(135deg,var(--primary-blue),var(--dark-blue));color:#fff;box-shadow:0 4px 12px rgba(59,130,246,.4)}
.btn-secondary{background:#fff;color:var(--text-medium);border:2px solid var(--border-color)}
.days-grid{display:grid;grid-template-columns:repeat(7,minmax(90px,1fr));gap:10px}
.day-pill{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--border-color);border-radius:999px;padding:8px 12px;justify-content:center}
.status-chip{display:inline-block;padding:4px 10px;border-radius:999px;font-size:.8em;font-weight:700}
.status-on{background:#dcfce7;color:#166534;border:1px solid #86efac}
.status-off{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
@media (max-width:768px){.days-grid{grid-template-columns:repeat(3,1fr)}}
</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">⏰</div>
            <h1>Attendance Time Settings</h1>
            <div class="subtitle">Configure time windows and allowed school days for attendance</div>
            <div style="margin-top:10px;">
                <?php
                    $todayName = date('l');
                    $allowed = isAttendanceAllowedToday($time_settings);
                    $chipClass = $allowed ? 'status-on' : 'status-off';
                    $chipText = $allowed ? 'Attendance allowed today' : 'Attendance disabled today';
                    echo "<span class='status-chip $chipClass'>$chipText ($todayName)</span>";
                ?>
            </div>
        </div>

        <div class="content">
            <?php echo $message; ?>

            <div class="current-settings">
                <h3>Current Configuration</h3>
                <div class="settings-grid">
                    <div class="setting-card">
                        <div class="setting-icon">🌅</div>
                        <div class="setting-label">Morning Start</div>
                        <div class="setting-value"><?php echo date('h:i A', strtotime($time_settings['morning_start'])); ?></div>
                    </div>
                    <div class="setting-card">
                        <div class="setting-icon">⏰</div>
                        <div class="setting-label">Late Threshold</div>
                        <div class="setting-value"><?php echo date('h:i A', strtotime($time_settings['morning_late_threshold'])); ?></div>
                    </div>
                    <div class="setting-card">
                        <div class="setting-icon">🌄</div>
                        <div class="setting-label">Morning End</div>
                        <div class="setting-value"><?php echo date('h:i A', strtotime($time_settings['morning_end'])); ?></div>
                    </div>
                    <div class="setting-card">
                        <div class="setting-icon">🌇</div>
                        <div class="setting-label">Afternoon Start</div>
                        <div class="setting-value"><?php echo date('h:i A', strtotime($time_settings['afternoon_start'])); ?></div>
                    </div>
                    <div class="setting-card">
                        <div class="setting-icon">🌃</div>
                        <div class="setting-label">Afternoon End</div>
                        <div class="setting-value"><?php echo date('h:i A', strtotime($time_settings['afternoon_end'])); ?></div>
                    </div>
                    <div class="setting-card">
                        <div class="setting-icon">📅</div>
                        <div class="setting-label">Allowed Days</div>
                        <div class="setting-value">
                            <?php
                                $days = ['Mon'=>'allow_mon','Tue'=>'allow_tue','Wed'=>'allow_wed','Thu'=>'allow_thu','Fri'=>'allow_fri','Sat'=>'allow_sat','Sun'=>'allow_sun'];
                                $out = [];
                                foreach ($days as $label=>$col) {
                                    $out[] = $time_settings[$col] ? $label : "<span style='color:#aaa;text-decoration:line-through'>$label</span>";
                                }
                                echo implode(' · ', $out);
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <form action="" method="post" id="settingsForm">
                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon">🌅</div>
                        <div class="section-title">Morning Time In Settings</div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="morning_start">Start Time</label>
                            <div class="input-wrapper">
                                <input type="text" id="morning_start" name="morning_start" placeholder="06:00" value="<?php echo substr($time_settings['morning_start'], 0, 5); ?>" required>
                            </div>
                            <div class="help-text">When the system starts accepting morning time-ins</div>
                        </div>
                        <div class="form-group">
                            <label for="morning_late_threshold">Late Threshold</label>
                            <div class="input-wrapper">
                                <input type="text" id="morning_late_threshold" name="morning_late_threshold" placeholder="08:30" value="<?php echo substr($time_settings['morning_late_threshold'], 0, 5); ?>" required>
                            </div>
                            <div class="help-text">Students arriving after this time are marked as "late"</div>
                        </div>
                        <div class="form-group">
                            <label for="morning_end">End Time</label>
                            <div class="input-wrapper">
                                <input type="text" id="morning_end" name="morning_end" placeholder="09:00" value="<?php echo substr($time_settings['morning_end'], 0, 5); ?>" required>
                            </div>
                            <div class="help-text">When the system stops accepting morning time-ins</div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon">🌇</div>
                        <div class="section-title">Afternoon Time Out Settings</div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="afternoon_start">Start Time</label>
                            <div class="input-wrapper">
                                <input type="text" id="afternoon_start" name="afternoon_start" placeholder="16:00" value="<?php echo substr($time_settings['afternoon_start'], 0, 5); ?>" required>
                            </div>
                            <div class="help-text">When the system starts accepting afternoon time-outs</div>
                        </div>
                        <div class="form-group">
                            <label for="afternoon_end">End Time</label>
                            <div class="input-wrapper">
                                <input type="text" id="afternoon_end" name="afternoon_end" placeholder="16:30" value="<?php echo substr($time_settings['afternoon_end'], 0, 5); ?>" required>
                            </div>
                            <div class="help-text">When the system stops accepting afternoon time-outs</div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="section-header">
                        <div class="section-icon">📅</div>
                        <div class="section-title">School Days (attendance allowed)</div>
                    </div>
                    <div class="days-grid">
                        <?php
                        $dayFields = [
                            'Mon' => 'allow_mon', 'Tue' => 'allow_tue', 'Wed' => 'allow_wed',
                            'Thu' => 'allow_thu', 'Fri' => 'allow_fri', 'Sat' => 'allow_sat', 'Sun' => 'allow_sun'
                        ];
                        foreach ($dayFields as $label => $name): ?>
                            <label class="day-pill">
                                <input type="checkbox" name="<?= $name ?>" <?= !empty($time_settings[$name]) ? 'checked' : '' ?>>
                                <span><?= $label ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="help-text" style="margin-top:8px;">
                        Uncheck Saturday/Sunday to prevent attendance on weekends. Mon–Fri are typically checked.
                    </div>
                </div>

                <div class="actions">
                    <a href="admin_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <button type="submit" name="update_settings" class="btn btn-primary" id="saveBtn">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Add loading state to form submission
        document.getElementById('settingsForm').addEventListener('submit', function() {
            const saveBtn = document.getElementById('saveBtn');
            const form = document.getElementById('settingsForm');
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            form.classList.add('loading');
        });

        // Real-time validation for HH:MM
        const timeInputs = document.querySelectorAll('input[type="text"]');
        timeInputs.forEach(input => {
            input.addEventListener('input', function() {
                const timePattern = /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/;
                if (this.value && !timePattern.test(this.value)) {
                    this.classList.add('error');
                } else {
                    this.classList.remove('error');
                }
            });
        });
    </script>
</body>
</html>