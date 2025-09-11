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

// Check if the time_settings table exists, create if not
try {
    $pdo->query("SELECT 1 FROM time_settings LIMIT 1");
} catch (PDOException $e) {
    // Table doesn't exist, create it
    $pdo->exec("
        CREATE TABLE time_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            morning_start TIME NOT NULL,
            morning_end TIME NOT NULL,
            morning_late_threshold TIME NOT NULL,
            afternoon_start TIME NOT NULL,
            afternoon_end TIME NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // Insert default values
    $stmt = $pdo->prepare("
        INSERT INTO time_settings
        (morning_start, morning_end, morning_late_threshold, afternoon_start, afternoon_end)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute(['06:00:00', '09:00:00', '08:30:00', '16:00:00', '16:30:00']);
}

// Get current settings
function getTimeSettings($pdo) {
    $stmt = $pdo->query("SELECT * FROM time_settings WHERE id = 1");
    return $stmt->fetch();
}

$time_settings = getTimeSettings($pdo);

// Handle form submission to update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    // Validate time inputs
    $morning_start = filter_input(INPUT_POST, 'morning_start', FILTER_SANITIZE_STRING);
    $morning_end = filter_input(INPUT_POST, 'morning_end', FILTER_SANITIZE_STRING);
    $morning_late_threshold = filter_input(INPUT_POST, 'morning_late_threshold', FILTER_SANITIZE_STRING);
    $afternoon_start = filter_input(INPUT_POST, 'afternoon_start', FILTER_SANITIZE_STRING);
    $afternoon_end = filter_input(INPUT_POST, 'afternoon_end', FILTER_SANITIZE_STRING);

    // Basic validation
    $is_valid = true;
    $error_fields = [];

    // Validate time format and logic
    if (!validateTimeFormat($morning_start)) {
        $is_valid = false;
        $error_fields[] = 'morning_start';
    }
    if (!validateTimeFormat($morning_end)) {
        $is_valid = false;
        $error_fields[] = 'morning_end';
    }
    if (!validateTimeFormat($morning_late_threshold)) {
        $is_valid = false;
        $error_fields[] = 'morning_late_threshold';
    }
    if (!validateTimeFormat($afternoon_start)) {
        $is_valid = false;
        $error_fields[] = 'afternoon_start';
    }
    if (!validateTimeFormat($afternoon_end)) {
        $is_valid = false;
        $error_fields[] = 'afternoon_end';
    }

    // If there are format errors, show the message
    if (count($error_fields) > 0) {
        $is_valid = false;
        $message = '<div class="alert error"><i class="icon">⚠️</i> Please enter valid time values in 24-hour format (HH:MM) for: ' . implode(', ', $error_fields) . '</div>';
    }

    // Check logical time sequence
    if ($is_valid) {
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

    // If valid, update the database
    if ($is_valid) {
        // Add seconds to time values
        $morning_start .= ':00';
        $morning_end .= ':00';
        $morning_late_threshold .= ':00';
        $afternoon_start .= ':00';
        $afternoon_end .= ':00';

        $stmt = $pdo->prepare("
            UPDATE time_settings
            SET morning_start = :morning_start,
                morning_end = :morning_end,
                morning_late_threshold = :morning_late_threshold,
                afternoon_start = :afternoon_start,
                afternoon_end = :afternoon_end
            WHERE id = 1
        ");

        if ($stmt->execute([
            'morning_start' => $morning_start,
            'morning_end' => $morning_end,
            'morning_late_threshold' => $morning_late_threshold,
            'afternoon_start' => $afternoon_start,
            'afternoon_end' => $afternoon_end
        ])) {
            $message = '<div class="alert success"><i class="icon">✅</i> Time settings updated successfully!</div>';
            // Reload settings after update
            $time_settings = getTimeSettings($pdo);
        } else {
            $message = '<div class="alert error"><i class="icon">❌</i> Error updating time settings.</div>';
        }
    }
}

/**
 * Validates a time string in HH:MM format
 */
function validateTimeFormat($time) {
    // Check if the time matches the pattern HH:MM
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
        return false;
    }

    // Split into hours and minutes
    list($hours, $minutes) = explode(':', $time);

    // Validate hours and minutes
    return (is_numeric($hours) && is_numeric($minutes) &&
            $hours >= 0 && $hours <= 23 &&
            $minutes >= 0 && $minutes <= 59);
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
        :root {
            --primary-blue: #3b82f6;
            --light-blue: #dbeafe;
            --dark-blue: #1d4ed8;
            --text-dark: #1f2937;
            --text-medium: #6b7280;
            --text-light: #9ca3af;
            --success-green: #10b981;
            --error-red: #ef4444;
            --warning-orange: #f59e0b;
            --border-color: #e5e7eb;
            --box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --container-bg: #ffffff;
            --background: #f9fafb;
            --input-bg: #ffffff;
            --card-bg: #f8fafc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--background);
            color: var(--text-medium);
            line-height: 1.6;
            min-height: 100vh;
            padding: 15px;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            background-color: var(--container-bg);
            border-radius: 20px;
            box-shadow: var(--box-shadow);
            overflow: hidden;
            padding: 0;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            position: relative;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.1;
        }

        .header h1 {
            font-size: 2.2em;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .header .subtitle {
            font-size: 1em;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .header .icon {
            font-size: 2.5em;
            margin-bottom: 15px;
            display: block;
            position: relative;
            z-index: 1;
        }

        .content {
            padding: 20px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-weight: 500;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            animation: slideIn 0.3s ease-out;
        }

        .alert .icon {
            font-size: 1.1em;
            margin-right: 10px;
        }

        .alert.success {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            color: var(--success-green);
            border-left: 4px solid var(--success-green);
        }

        .alert.error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: var(--error-red);
            border-left: 4px solid var(--error-red);
        }

        .current-settings {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 25px;
            border: 2px solid var(--border-color);
        }

        .current-settings h3 {
            color: var(--text-dark);
            font-size: 1.3em;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }

        .current-settings h3::before {
            content: "⚙️";
            margin-right: 12px;
            font-size: 1.1em;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }

        .setting-card {
            background: white;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            text-align: center;
        }

        .setting-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px -4px rgba(0, 0, 0, 0.1);
        }

        .setting-icon {
            font-size: 1.8em;
            margin-bottom: 8px;
            color: var(--primary-blue);
        }

        .setting-label {
            font-size: 0.8em;
            color: var(--text-light);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .setting-value {
            font-size: 1.2em;
            color: var(--text-dark);
            font-weight: 700;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }

        .section-icon {
            font-size: 1.4em;
            margin-right: 12px;
            color: var(--primary-blue);
        }

        .section-title {
            color: var(--text-dark);
            font-size: 1.2em;
            font-weight: 600;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9em;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper::before {
            content: "🕐";
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            z-index: 1;
        }

        .form-group input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            background: var(--input-bg);
            font-size: 0.95em;
            font-weight: 500;
            transition: all 0.3s ease;
            color: var(--text-dark);
        }

        .form-group input:focus {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
            transform: translateY(-1px);
        }

        .form-group input.error {
            border-color: var(--error-red);
            background-color: #fef2f2;
        }

        .help-text {
            color: var(--text-light);
            font-size: 0.75em;
            margin-top: 6px;
            font-style: italic;
        }

        .actions {
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
        }

        .btn {
            padding: 12px 25px;
            border-radius: 10px;
            font-size: 0.95em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 130px;
        }

        .btn i {
            margin-right: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--dark-blue) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(59, 130, 246, 0.5);
        }

        .btn-secondary {
            background: white;
            color: var(--text-medium);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--card-bg);
            border-color: var(--primary-blue);
            color: var(--primary-blue);
            transform: translateY(-1px);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .container {
                border-radius: 12px;
                margin: 5px;
            }

            .header {
                padding: 25px 15px;
            }

            .header h1 {
                font-size: 1.8em;
            }

            .header .subtitle {
                font-size: 0.9em;
            }

            .content {
                padding: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .settings-grid {
                grid-template-columns: 1fr;
            }

            .actions {
                flex-direction: column-reverse;
                gap: 12px;
            }

            .btn {
                width: 100%;
            }

            .btn i {
                margin-right: 6px;
            }
        }

        @media (max-width: 480px) {
            .header h1 {
                font-size: 1.5em;
            }

            .header .icon {
                font-size: 2em;
            }

            .section-title {
                font-size: 1.1em;
            }

            .form-group input {
                padding: 10px 10px 10px 35px;
            }

            .input-wrapper::before {
                left: 10px;
            }
        }

        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .loading .btn-primary {
            background: var(--text-light);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="icon">⏰</div>
            <h1>Attendance Time Settings</h1>
            <div class="subtitle">Configure time windows for the RFID attendance monitoring system</div>
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

        // Add real-time validation
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

        // Format input to ensure only numbers and colons are entered
        timeInputs.forEach(input => {
            input.addEventListener('keydown', function(e) {
                // Allow backspace, delete, tab, escape, and enter
                if ([8, 9, 13, 27, 37, 38, 39, 40].includes(e.keyCode)) {
                    return;
                }

                // Allow only numbers and colons
                if (!/[0-9:]/i.test(e.key)) {
                    e.preventDefault();
                }

                // Auto-format the time when colon is entered
                if (this.value.length === 2 && e.key === ':') {
                    e.preventDefault();
                    this.value += ':';
                }
            });

            input.addEventListener('blur', function() {
                // Ensure the time is in the format HH:MM
                if (this.value.length === 5 && this.value.indexOf(':') === 2) {
                    return;
                }

                // If empty or invalid, reset to empty
                if (!/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/.test(this.value)) {
                    this.value = '';
                } else if (this.value.length === 1) {
                    // If only one character, assume hours
                    this.value = this.value + ':00';
                } else if (this.value.length === 2) {
                    // If two characters, assume hours
                    this.value = this.value + ':00';
                } else if (this.value.length === 3) {
                    // If three characters, assume hours and minutes
                    this.value = this.value[0] + this.value[1] + ':' + this.value[2] + '0';
                }
            });
        });
    </script>
</body>
</html>