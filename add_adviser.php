<?php
session_start();
// Ensure the user is logged in
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

include 'conn.php'; // Database connection

// Create logs directory if needed
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}
$adviser_error_log = 'logs/adviser_errors.txt';
$general_error_log = 'logs/error_log.txt';

// --- Database Connection Check ---
if ($conn->connect_error) {
    error_log(date('c') . " DB Conn Error: " . $conn->connect_error . "\n", 3, $general_error_log);
    die("Database connection failed. Please check the logs.");
}
$conn->set_charset("utf8");

// --- CSRF Token Generation ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validate_csrf_token($log_file_path) {
    if (isset($_POST['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        error_log(date('c') . " CSRF Attack Detected: " . $_SERVER['REMOTE_ADDR'] . "\n", 3, $log_file_path);
        echo "<script>alert('Invalid security token. Please try again.');location='add_adviser.php'</script>";
        exit();
    }
}

/*****************
ADVISER MANAGEMENT
*******************/

// --- Handle Add Adviser ---
if (isset($_POST['add_adviser'])) {
    validate_csrf_token($adviser_error_log);
    $employee_id = htmlspecialchars(trim($_POST['employee_id']), ENT_QUOTES, 'UTF-8');
    $lastname    = htmlspecialchars(trim($_POST['lastname']), ENT_QUOTES, 'UTF-8');
    $firstname   = htmlspecialchars(trim($_POST['firstname']), ENT_QUOTES, 'UTF-8');
    $middlename  = htmlspecialchars(trim($_POST['middlename']), ENT_QUOTES, 'UTF-8');
    $suffix      = htmlspecialchars(trim($_POST['suffix']), ENT_QUOTES, 'UTF-8');
    $gender      = ($_POST['gender'] === 'male') ? 'male' : 'female';
    $pass        = $_POST['pass']; // store as plain text

    if (empty($employee_id) || empty($lastname) || empty($firstname) || empty($pass)) {
        echo "<script>alert('Employee ID, Last Name, First Name, and Password are required.'); location='add_adviser.php'</script>";
        exit();
    }

    $plain_pass = $pass;

    $conn->begin_transaction();
    try {
        // Check by employee_id (primary key)
        $dup_employee = $conn->prepare("SELECT employee_id FROM advisers WHERE employee_id = ?");
        if (!$dup_employee) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $dup_employee->bind_param("s", $employee_id);
        $dup_employee->execute();
        $dup_employee->store_result();
        if ($dup_employee->num_rows > 0) {
            throw new Exception("Employee ID '$employee_id' already exists.");
        }
        $dup_employee->close();

        $stmt = $conn->prepare("INSERT INTO advisers (employee_id, lastname, firstname, middlename, suffix, gender, pass) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("sssssss", $employee_id, $lastname, $firstname, $middlename, $suffix, $gender, $plain_pass);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        $conn->commit();

        header("Location: add_adviser.php?status=added");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log(date('c') . " Add Adviser Error: " . $e->getMessage() . "\n", 3, $adviser_error_log);
        echo "<script>alert('Error adding adviser: " . addslashes($e->getMessage()) . "'); location='add_adviser.php'</script>";
        exit();
    }
}

// --- Handle Delete Adviser ---
if (isset($_POST['delete_adviser'])) {
    validate_csrf_token($adviser_error_log);
    $employee_id = htmlspecialchars(trim($_POST['employee_id']), ENT_QUOTES, 'UTF-8');
    
    if (empty($employee_id)) {
        echo "<script>alert('Invalid Employee ID.'); location='add_adviser.php'</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // Check sections using employee_id
        $check_sections = $conn->prepare("SELECT COUNT(*) FROM sections WHERE employee_id = ?");
        if (!$check_sections) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $check_sections->bind_param("s", $employee_id);
        $check_sections->execute();
        $check_sections->bind_result($section_count);
        $check_sections->fetch();
        $check_sections->close();

        if ($section_count > 0) {
            throw new Exception("Cannot delete adviser. They are assigned to $section_count section(s). Please reassign or delete sections first.");
        }

        // Delete using employee_id
        $stmt = $conn->prepare("DELETE FROM advisers WHERE employee_id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $employee_id);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();

        $conn->commit();
        header("Location: add_adviser.php?status=deleted");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log(date('c') . " Delete Adviser Error: " . $e->getMessage() . "\n", 3, $adviser_error_log);
        echo "<script>alert('Delete failed: " . addslashes($e->getMessage()) . "'); location='add_adviser.php'</script>";
        exit();
    }
}

// --- Handle Edit Adviser ---
if (isset($_POST['edit_adviser'])) {
    validate_csrf_token($adviser_error_log);
    $old_employee_id = htmlspecialchars(trim($_POST['old_employee_id']), ENT_QUOTES, 'UTF-8');
    $employee_id = htmlspecialchars(trim($_POST['edit_employee_id']), ENT_QUOTES, 'UTF-8');
    $lastname    = htmlspecialchars(trim($_POST['edit_lastname']), ENT_QUOTES, 'UTF-8');
    $firstname   = htmlspecialchars(trim($_POST['edit_firstname']), ENT_QUOTES, 'UTF-8');
    $middlename  = htmlspecialchars(trim($_POST['edit_middlename']), ENT_QUOTES, 'UTF-8');
    $suffix      = htmlspecialchars(trim($_POST['edit_suffix']), ENT_QUOTES, 'UTF-8');
    $gender      = ($_POST['edit_gender'] === 'female') ? 'female' : 'male';
    $pass_new    = trim($_POST['edit_pass']);

    if (empty($old_employee_id) || empty($employee_id) || empty($lastname) || empty($firstname)) {
        echo "<script>alert('Employee ID, Last Name, and First Name are required.'); location='add_adviser.php'</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // Check if new employee_id conflicts (if changed)
        $dup_employee = $conn->prepare("SELECT employee_id FROM advisers WHERE employee_id = ? AND employee_id != ?");
        if (!$dup_employee) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $dup_employee->bind_param("ss", $employee_id, $old_employee_id);
        $dup_employee->execute();
        $dup_employee->store_result();
        if ($dup_employee->num_rows > 0) {
            throw new Exception("Employee ID '$employee_id' already exists for another adviser.");
        }
        $dup_employee->close();

        $sql = "UPDATE advisers SET employee_id = ?, lastname = ?, firstname = ?, middlename = ?, suffix = ?, gender = ?";
        $param_types = "ssssss";
        $param_values = [$employee_id, $lastname, $firstname, $middlename, $suffix, $gender];

        if (!empty($pass_new)) {
            $sql .= ", pass = ?";
            $param_types .= "s";
            $param_values[] = $pass_new;
        }

        $sql .= " WHERE employee_id = ?";
        $param_types .= "s";
        $param_values[] = $old_employee_id;
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $bind_params = [];
        $bind_params[] = $param_types;
        foreach ($param_values as $key => $value) {
            $bind_params[] = &$param_values[$key];
        }

        if (!call_user_func_array([$stmt, 'bind_param'], $bind_params)) {
            throw new Exception("Bind_param failed: " . $stmt->error);
        }

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        $conn->commit();
        header("Location: add_adviser.php?status=updated");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log(date('c') . " Edit Adviser Error: " . $e->getMessage() . "\n", 3, $adviser_error_log);
        echo "<script>alert('Error updating adviser: " . addslashes($e->getMessage()) . "'); location='add_adviser.php'</script>";
        exit();
    }
}

/***********************************
CSV IMPORT FOR ADVISERS - PLAIN PASSWORD
***********************************/
function safeTrimCSV($value) {
    if (is_null($value) || is_array($value) || is_bool($value) || is_object($value)) {
        return '';
    }
    return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$value));
}

if (isset($_POST['import_advisers_csv']) && isset($_FILES['adviser_csvfile']) && $_FILES['adviser_csvfile']['error'] === UPLOAD_ERR_OK) {
    validate_csrf_token($adviser_error_log);
    $tmpPath = $_FILES['adviser_csvfile']['tmp_name'];
    $fileExt = strtolower(pathinfo($_FILES['adviser_csvfile']['name'], PATHINFO_EXTENSION));

    if ($fileExt !== 'csv') {
        echo "<script>alert('❌ Please upload a CSV file.'); location='add_adviser.php'</script>";
        exit();
    }
    if ($_FILES['adviser_csvfile']['size'] > 5 * 1024 * 1024) {
        echo "<script>alert('❌ File size exceeds 5MB limit.'); location='add_adviser.php'</script>";
        exit();
    }

    if (($handle = fopen($tmpPath, 'r')) !== false) {
        ini_set('auto_detect_line_endings', 1);
        $header = fgetcsv($handle, 2000, ",");

        $expected_fields = [
            'employee_id' => ['employee_id'],
            'lastname'    => ['lastname', 'last_name'],
            'firstname'   => ['firstname', 'first_name'],
            'middlename'  => ['middlename', 'middle_name'],
            'suffix'      => ['suffix'],
            'gender'      => ['gender'],
            'pass'        => ['pass', 'password']
        ];

        if (!is_array($header) || count(array_filter($header)) < 7) { 
            fclose($handle);
            echo "<script>alert('❌ CSV must have at least 7 columns.'); location='add_adviser.php'</script>";
            exit();
        }

        $col_map = [];
        foreach ($expected_fields as $field => $possible_headers) {
            $found = false;
            foreach ($possible_headers as $possible_header) {
                foreach ($header as $idx => $h_val) {
                    if (strtolower(safeTrimCSV($h_val)) === strtolower($possible_header)) {
                        $col_map[$field] = $idx;
                        $found = true;
                        break 2;
                    }
                }
            }
            if (!$found) {
                fclose($handle);
                echo "<script>alert('❌ Missing required column: " . ucfirst(str_replace('_', ' ', $field)) . " (expected: " . implode(', ', $possible_headers) . ")'); location='add_adviser.php'</script>";
                exit();
            }
        }

        $conn->begin_transaction();
        try {
            if (!$conn->query("DELETE FROM sections")) {
                throw new Exception("SQL Error: Could not clear sections table: " . $conn->error);
            }

            if (!$conn->query("SET FOREIGN_KEY_CHECKS = 0")) {
                throw new Exception("SQL Error: Could not disable foreign key checks: " . $conn->error);
            }

            if (!$conn->query("TRUNCATE TABLE advisers")) {
                $conn->query("SET FOREIGN_KEY_CHECKS = 1"); 
                throw new Exception("SQL Error: Could not truncate advisers table: " . $conn->error);
            }

            if (!$conn->query("SET FOREIGN_KEY_CHECKS = 1")) {
                throw new Exception("SQL Error: Could not re-enable foreign key checks: " . $conn->error);
            }

            $stmt = $conn->prepare("INSERT INTO advisers (employee_id, lastname, firstname, middlename, suffix, gender, pass) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Database error preparing import statement: " . $conn->error);
            }

            $rowCount = 0;
            $errors = [];
            $row_num = 1;

            while (($data = fgetcsv($handle, 2000, ",")) !== false) {
                $row_num++;
                if (!is_array($data) || count(array_filter($data)) === 0 || count($data) < 7) {
                    continue;
                }

                $csv_employee_id = safeTrimCSV($data[$col_map['employee_id']] ?? '');
                $csv_lastname    = htmlspecialchars(safeTrimCSV($data[$col_map['lastname']] ?? ''), ENT_QUOTES, 'UTF-8');
                $csv_firstname   = htmlspecialchars(safeTrimCSV($data[$col_map['firstname']] ?? ''), ENT_QUOTES, 'UTF-8');
                $csv_middlename  = htmlspecialchars(safeTrimCSV($data[$col_map['middlename']] ?? ''), ENT_QUOTES, 'UTF-8');
                $csv_suffix      = htmlspecialchars(safeTrimCSV($data[$col_map['suffix']] ?? ''), ENT_QUOTES, 'UTF-8');
                $csv_gender      = strtolower(safeTrimCSV($data[$col_map['gender']] ?? '')) === 'female' ? 'female' : 'male';
                $csv_pass        = safeTrimCSV($data[$col_map['pass']] ?? '');

                if (empty($csv_employee_id) || empty($csv_lastname) || empty($csv_firstname) || empty($csv_pass)) {
                    $errors[] = "Row $row_num: Missing required fields.";
                    continue;
                }

                $stmt->bind_param(
                    "sssssss",
                    $csv_employee_id,
                    $csv_lastname,
                    $csv_firstname,
                    $csv_middlename,
                    $csv_suffix,
                    $csv_gender,
                    $csv_pass
                );
                
                if ($stmt->execute()) {
                    $rowCount++;
                } else {
                    $errors[] = "Row $row_num: Insert failed (" . $stmt->error . ").";
                    error_log(date('c') . " CSV Import Row Error: " . $stmt->error . " for row " . $row_num . "\n", 3, $adviser_error_log);
                }
            }
            fclose($handle);
            $stmt->close();
            $conn->commit();

            $message = "✓ CSV Import Complete!\nSuccessfully imported: $rowCount advisers.";
            if (!empty($errors)) {
                $message .= "\n\n❌ " . count($errors) . " rows failed. Check logs for details.";
            }
            echo "<script>alert(" . json_encode($message) . "); location='add_adviser.php';</script>";
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $conn->query("SET FOREIGN_KEY_CHECKS = 1"); 
            error_log(date('c') . " CSV Import Fatal Error: " . $e->getMessage() . "\n", 3, $adviser_error_log);
            echo "<script>alert('❌ CSV Import failed: " . addslashes($e->getMessage()) . "'); location='add_adviser.php'</script>";
            exit();
        }
    } else {
        echo "<script>alert('❌ Failed to read CSV file. Check file permissions or integrity.'); location='add_adviser.php'</script>";
        exit();
    }
}

// --- Fetch advisers for display ---
$advisers_result = $conn->query("SELECT employee_id, lastname, firstname, middlename, suffix, gender, pass FROM advisers ORDER BY lastname, firstname");
if (!$advisers_result) {
    error_log(date('c') . " Fetch Advisers Error: " . $conn->error . "\n", 3, $general_error_log);
    die("Error fetching advisers.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Advisers Dashboard</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        @keyframes hl { 0% { background-color: #c8e6c9; } 100% { background-color: transparent; } }
        .highlight { animation: hl 2s forwards; }
        .table-card {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            width: 100%;
            box-shadow: 0 12px 30px rgba(15,23,42,0.08);
        }
        .table-responsive-custom { overflow-x: auto; }
        .custom-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        .custom-table thead th {
            text-align: left;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            color: #4b5563;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e5e7eb;
            background-color: #f9fafb;
        }
        .custom-table tbody td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .custom-table tbody tr:hover { background: rgba(59,130,246,0.06); }
        .actions-cell {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
            min-width: 80px;
        }
        .action-icon-btn {
            border: none;
            background: none;
            padding: 0;
            margin: 0 2px;
            cursor: pointer;
            transition: color 0.2s ease, opacity 0.2s ease;
        }
        .action-icon-btn .material-symbols-outlined {
            font-size: 1.1em;
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .action-icon-btn.edit-icon .material-symbols-outlined { color: #1c74e4; }
        .action-icon-btn.delete-icon .material-symbols-outlined { color: #dc3545; }
        .action-icon-btn:hover .material-symbols-outlined { opacity: 0.7; }
        .search-box {
            position: relative;
            display: inline-block;
            margin-right: 10px;
        }
        .search-box input {
            padding: 0.5rem 2.5rem 0.5rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            width: 300px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        .search-box input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        .search-box .search-icon {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            pointer-events: none;
        }
        .clear-search {
            position: absolute;
            right: 2.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            display: none;
        }
        .clear-search.show { display: block; }
        .page-title-with-logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-logo {
            width: 45px;
            height: 45px;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .modal-header {
            background-color: #007bff;
            color: white;
        }
        .modal-header .btn-close-white {
            filter: invert(1);
        }
        .btn-primary {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 15px;
        }
    </style>
    <script>
    $(document).ready(function(){
        const searchInput = $('#searchAdviser');
        const clearBtn = $('#clearSearch');
        const tableRows = $('.custom-table tbody tr');
        
        searchInput.on('input', function() {
            const searchTerm = $(this).val().toLowerCase().trim();
            if(searchTerm.length > 0) { 
                clearBtn.addClass('show'); 
            } else { 
                clearBtn.removeClass('show'); 
            }
            let visibleCount = 0;
            tableRows.each(function(){
                const row = $(this);
                if (row.attr('id') === 'noResults' && searchTerm.length > 0) {
                    row.remove();
                    return true;
                }
                if(row.text().toLowerCase().includes(searchTerm)){
                    row.show(); 
                    visibleCount++;
                } else {
                    row.hide();
                }
            });
            if(visibleCount === 0 && searchTerm.length > 0 && $('#noResults').length === 0) {
                $('.custom-table tbody').append('<tr id="noResults"><td colspan="9" class="border px-3 py-2 text-center text-gray-500 py-8"><span class="material-symbols-outlined" style="font-size:1.5rem;">search_off</span><br>No advisers found matching "' + searchTerm + '"</td></tr>');
            } else if (searchTerm.length === 0) {
                $('#noResults').remove();
            }
        });
        
        clearBtn.on('click', function(){
            searchInput.val('');
            clearBtn.removeClass('show');
            tableRows.show();
            $('#noResults').remove();
            searchInput.focus();
        });
        
        searchInput.on('keydown', function(e) { 
            if(e.key === 'Escape') clearBtn.click(); 
        });
    });

    // Now passes no username (removed)
    function openEditAdviserModal(employee_id, lastname, firstname, middlename, suffix, gender) {
        const editModal = new bootstrap.Modal(document.getElementById('editAdviserModal'));
        document.getElementById('old_employee_id').value = employee_id;
        document.getElementById('edit_employee_id').value = employee_id;
        document.getElementById('edit_lastname').value = lastname;
        document.getElementById('edit_firstname').value = firstname;
        document.getElementById('edit_middlename').value = middlename;
        document.getElementById('edit_suffix').value = suffix;
        document.getElementById('edit_gender').value = gender;
        document.getElementById('edit_pass').value = '';
        editModal.show();
    }
    </script>
</head>
<body id="page-top">
    <?php include 'nav.php'; ?>
    <div id="wrapper">
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
                        <div class="page-title-with-logo">
                            <img src="img/depedlogo.jpg" alt="Adviser Logo" class="page-logo">
                            <h2 class="h3 mb-0 text-gray-800">Advisers Dashboard</h2>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="search-box">
                                <input type="text" id="searchAdviser" placeholder="Search advisers..." autocomplete="off">
                                <button type="button" class="clear-search" id="clearSearch" title="Clear search">
                                    <span class="material-symbols-outlined">close</span>
                                </button>
                                <span class="search-icon">
                                    <span class="material-symbols-outlined">search</span>
                                </span>
                            </div>
                            <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                                <span class="material-symbols-outlined">upload_file</span> Import CSV
                            </button>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdviserModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                                <span class="material-symbols-outlined">person_add</span> Add Adviser
                            </button>
                        </div>
                    </div>

                    <?php if (isset($_GET['status'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Adviser <?= htmlspecialchars($_GET['status']) ?> successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <div class="table-card">
                        <div class="table-responsive-custom">
                            <table class="custom-table">
                                <thead class="bg-gray-200 font-semibold">
                                    <tr>
                                        <th class="border px-3 py-2">#</th>
                                        <th class="border px-3 py-2">Employee ID</th>
                                        <th class="border px-3 py-2">Last Name</th>
                                        <th class="border px-3 py-2">First Name</th>
                                        <th class="border px-3 py-2">Middle Name</th>
                                        <th class="border px-3 py-2">Suffix</th>
                                        <th class="border px-3 py-2">Gender</th>
                                        <th class="border px-3 py-2">Password</th>
                                        <th class="border px-3 py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $counter = 1;
                                    if ($advisers_result && $advisers_result->num_rows > 0):
                                        while ($row = $advisers_result->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td class="border px-3 py-2 font-medium"><?= $counter++ ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($row['employee_id']) ?></td>
                                        <td class="border px-3 py-2 font-medium"><?= htmlspecialchars($row['lastname']) ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($row['firstname']) ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($row['middlename']) ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($row['suffix']) ?></td>
                                        <td class="border px-3 py-2">
                                            <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full"><?= ucfirst($row['gender']) ?></span>
                                        </td>
                                        <td class="border px-3 py-2 font-mono text-sm">
                                            <?= htmlspecialchars($row['pass']) ?>
                                        </td>
                                        <td class="border px-3 py-2 actions-cell">
                                            <button onclick="openEditAdviserModal(
                                                '<?= htmlspecialchars($row['employee_id'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($row['lastname'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($row['firstname'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($row['middlename'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($row['suffix'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($row['gender'], ENT_QUOTES) ?>'
                                            )" class="action-icon-btn edit-icon" title="Edit Adviser">
                                                <span class="material-symbols-outlined">edit</span>
                                            </button>
                                            <form method="POST" class="d-inline" style="display:inline-block;" onsubmit="return confirm('Delete <?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?>? This is permanent.');">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="employee_id" value="<?= htmlspecialchars($row['employee_id']) ?>">
                                                <button type="submit" name="delete_adviser" class="action-icon-btn delete-icon" title="Delete Adviser">
                                                    <span class="material-symbols-outlined">delete</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="border px-3 py-12 text-center text-gray-500">
                                            <span class="material-symbols-outlined" style="font-size:3rem; opacity:0.5;">group_off</span>
                                            <div class="mt-2">No advisers found.</div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Adviser Modal -->
    <div class="modal fade" id="addAdviserModal" tabindex="-1" aria-labelledby="addAdviserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addAdviserModalLabel">
                            <span class="material-symbols-outlined me-2">person_add</span>Add New Adviser
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Employee ID <span class="text-danger">*</span></label>
                            <input type="text" name="employee_id" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="lastname" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="firstname" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" name="middlename" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Suffix</label>
                                <input type="text" name="suffix" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Gender <span class="text-danger">*</span></label>
                                <select name="gender" class="form-select" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="pass" class="form-control" required minlength="6">
                            <div class="form-text">Minimum 6 characters (stored as plain text)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_adviser" class="btn btn-primary">
                            <span class="material-symbols-outlined me-1">save</span>Add Adviser
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Adviser Modal -->
    <div class="modal fade" id="editAdviserModal" tabindex="-1" aria-labelledby="editAdviserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" id="old_employee_id" name="old_employee_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editAdviserModalLabel">
                            <span class="material-symbols-outlined me-2">edit</span>Edit Adviser
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Employee ID <span class="text-danger">*</span></label>
                            <input type="text" id="edit_employee_id" name="edit_employee_id" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                            <input type="text" id="edit_lastname" name="edit_lastname" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                            <input type="text" id="edit_firstname" name="edit_firstname" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" id="edit_middlename" name="edit_middlename" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Suffix</label>
                                <input type="text" id="edit_suffix" name="edit_suffix" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Gender <span class="text-danger">*</span></label>
                                <select id="edit_gender" name="edit_gender" class="form-select" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" id="edit_pass" name="edit_pass" class="form-control" minlength="6">
                            <div class="form-text">Stored as plain text.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_adviser" class="btn btn-primary">
                            <span class="material-symbols-outlined me-1">save</span>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import CSV Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Advisers from CSV</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="adviser_csvfile" class="form-label">Select CSV File *</label>
                            <input type="file" name="adviser_csvfile" id="adviser_csvfile" class="form-control" accept=".csv" required>
                            <small class="text-muted">
                                Max 5MB | Columns: <code>employee_id, lastname, firstname, middlename, suffix, gender, pass OR password</code>
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="import_advisers_csv" class="btn btn-primary">Upload & Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>