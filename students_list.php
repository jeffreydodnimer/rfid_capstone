<?php
session_start();
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

include 'conn.php';

define('STUDENT_UPLOAD_DIR', __DIR__ . '/uploads/');
if (!file_exists(STUDENT_UPLOAD_DIR)) {
    mkdir(STUDENT_UPLOAD_DIR, 0755, true);
}

/**
 * Accepts: MM-DD-YYYY or MM/DD/YYYY
 * Stores/returns (for DB): YYYY-MM-DD
 */
function convertDateFormat($dateString) {
    if (empty($dateString)) return null;
    if (!is_string($dateString)) return null;

    if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $dateString, $m)) {
        $month = (int)$m[1];
        $day   = (int)$m[2];
        $year  = (int)$m[3];

        if (checkdate($month, $day, $year)) {
            return sprintf("%04d-%02d-%02d", $year, $month, $day);
        }
    }
    return null;
}

function safeTrim($value) {
    if (is_null($value) || is_array($value) || is_bool($value) || is_object($value)) {
        return '';
    }
    return trim((string)$value);
}

function handleProfileImageUpload($inputName, $existing = null) {
    $filename = $existing;
    $errors = [];

    if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === UPLOAD_ERR_OK) {
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif'];

        $tmpPath = $_FILES[$inputName]['tmp_name'];
        $ext = strtolower(pathinfo($_FILES[$inputName]['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($tmpPath);

        if (!in_array($ext, $allowedExts) || !in_array($mime, $allowedMimes)) {
            $errors[] = 'Only JPG, PNG or GIF files are allowed.';
        }

        if ($_FILES[$inputName]['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Maximum file size is 2MB.';
        }

        if (empty($errors)) {
            if ($existing && file_exists(STUDENT_UPLOAD_DIR . $existing)) {
                unlink(STUDENT_UPLOAD_DIR . $existing);
            }
            $filename = uniqid('student_', true) . '.' . $ext;
            if (!move_uploaded_file($tmpPath, STUDENT_UPLOAD_DIR . $filename)) {
                $errors[] = 'Unable to save uploaded image.';
            }
        }
    }

    return [$filename, $errors];
}

function alertAndBack($msg) {
    $escaped = json_encode($msg);
    echo "<script>alert({$escaped}); window.location.href='students_list.php';</script>";
    exit();
}

//
// ------------- CSV IMPORT -------------
//
if (isset($_POST['import_csv']) && isset($_FILES['csvfile']) && $_FILES['csvfile']['error'] === UPLOAD_ERR_OK) {

    $tmpPath = $_FILES['csvfile']['tmp_name'];
    $fileName = $_FILES['csvfile']['name'];

    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($fileExt !== 'csv') {
        alertAndBack('❌ Please upload a CSV file.');
    }

    if ($_FILES['csvfile']['size'] > 5 * 1024 * 1024) {
        alertAndBack('❌ File size exceeds 5MB limit.');
    }

    if (($handle = fopen($tmpPath, 'r')) !== false) {

        ini_set('auto_detect_line_endings', 1);
        $header = fgetcsv($handle, 2000, ",");

        if (!is_array($header) || count($header) < 8) {
            fclose($handle);
            alertAndBack('❌ CSV must have at least 8 columns: LRN, Lastname, Firstname, Middlename, Suffix, Age, Birthdate, Sex');
        }

        $stmt = $conn->prepare("INSERT INTO students (lrn, lastname, firstname, middlename, suffix, age, birthdate, sex)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            fclose($handle);
            alertAndBack('❌ Prepare failed: ' . addslashes($conn->error));
        }

        $rowCount = 0;
        $errors = [];
        $row_num = 1;

        while (($data = fgetcsv($handle, 2000, ",")) !== false) {
            $row_num++;
            if (!is_array($data) || count(array_filter($data)) === 0) {
                continue;
            }

            if (count($data) < 8) {
                $errors[] = "Row {$row_num}: Expected 8 columns, found " . count($data);
                continue;
            }

            $lrn        = safeTrim($data[0] ?? '');
            $lastname   = safeTrim($data[1] ?? '');
            $firstname  = safeTrim($data[2] ?? '');
            $middlename = safeTrim($data[3] ?? '');
            $suffix     = safeTrim($data[4] ?? '');
            $age_raw    = safeTrim($data[5] ?? '');
            $birth_raw  = safeTrim($data[6] ?? '');
            $sex_raw    = safeTrim($data[7] ?? '');

            if (empty($lrn) || strlen($lrn) !== 12 || !ctype_digit($lrn)) {
                $errors[] = "Row {$row_num}: Invalid LRN '{$lrn}'";
                continue;
            }

            if (empty($age_raw) || !ctype_digit($age_raw) || intval($age_raw) <= 0 || intval($age_raw) > 120) {
                $errors[] = "Row {$row_num}: Invalid age '{$age_raw}'";
                continue;
            }
            $age = (int)$age_raw;

            // Birthdate input: MM-DD-YYYY or MM/DD/YYYY
            $birthdate = convertDateFormat($birth_raw);
            if ($birthdate === null) {
                $errors[] = "Row {$row_num}: Invalid date '{$birth_raw}' (use MM-DD-YYYY or MM/DD/YYYY)";
                continue;
            }

            $sex = ucfirst(strtolower($sex_raw));
            if (!in_array($sex, ['Male', 'Female'])) {
                $errors[] = "Row {$row_num}: Invalid sex '{$sex_raw}' (use Male or Female)";
                continue;
            }

            $check = $conn->prepare("SELECT lrn FROM students WHERE lrn = ?");
            if (!$check) {
                $errors[] = "Row {$row_num}: Database error.";
                continue;
            }
            $check->bind_param("s", $lrn);
            $check->execute();
            $check_result = $check->get_result();

            if ($check_result->num_rows > 0) {
                $errors[] = "Row {$row_num}: Duplicate LRN '{$lrn}'";
                $check->close();
                continue;
            }
            $check->close();

            $stmt->bind_param("sssssiss", $lrn, $lastname, $firstname, $middlename, $suffix, $age, $birthdate, $sex);

            if (!$stmt->execute()) {
                $errors[] = "Row {$row_num}: Database error.";
                continue;
            }

            $rowCount++;
        }

        fclose($handle);
        $stmt->close();

        $message = "✓ CSV Import Complete\n\n";
        $message .= "✓ Successfully imported: {$rowCount} students\n";

        if (!empty($errors)) {
            $message .= "\n❌ Errors (" . count($errors) . " rows failed):\n";
            $message .= implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= "\n... and " . (count($errors) - 5) . " more errors.";
            }
        }

        $escapedMessage = json_encode($message);
        echo "<script>alert({$escapedMessage}); window.location.href='students_list.php?status=imported';</script>";
        exit();
    } else {
        alertAndBack('❌ Failed to open CSV file.');
    }
}

//
// ------------- ADD STUDENT -------------
//
if (isset($_POST['add_student'])) {

    $lrn = htmlspecialchars(safeTrim($_POST['lrn'] ?? ''));
    $lastname = htmlspecialchars(safeTrim($_POST['lastname'] ?? ''));
    $firstname = htmlspecialchars(safeTrim($_POST['firstname'] ?? ''));
    $middlename = htmlspecialchars(safeTrim($_POST['middlename'] ?? ''));
    $suffix = htmlspecialchars(safeTrim($_POST['suffix'] ?? ''));
    $age = !empty($_POST['age']) ? (int)$_POST['age'] : 0;
    $birth_raw = htmlspecialchars(safeTrim($_POST['birthdate'] ?? ''));
    $sex = htmlspecialchars(safeTrim($_POST['sex'] ?? ''));

    $birthdate = convertDateFormat($birth_raw);
    if ($birthdate === null) {
        alertAndBack('❌ Invalid birthdate format (use MM-DD-YYYY or MM/DD/YYYY)');
    }

    if (!in_array($sex, ['Male', 'Female'])) {
        alertAndBack('❌ Please select a valid sex.');
    }

    if (strlen($lrn) !== 12 || !ctype_digit($lrn)) {
        alertAndBack('❌ LRN must be exactly 12 digits');
    }

    list($profileImage, $imgErrors) = handleProfileImageUpload('profile_image');
    if ($imgErrors) {
        alertAndBack(implode("\\n", $imgErrors));
    }

    $stmt = $conn->prepare("INSERT INTO students (lrn, lastname, firstname, middlename, suffix, age, birthdate, sex, profile_image)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssisss", $lrn, $lastname, $firstname, $middlename, $suffix, $age, $birthdate, $sex, $profileImage);

    if ($stmt->execute()) {
        header("Location: students_list.php?status=added");
        exit();
    } else {
        alertAndBack('❌ Error adding student: ' . addslashes($stmt->error));
    }
    $stmt->close();
}

//
// ------------- DELETE STUDENT -------------
//
if (isset($_POST['delete_student'])) {

    $lrn = htmlspecialchars(safeTrim($_POST['student_lrn'] ?? ''));

    if (empty($lrn)) {
        alertAndBack('❌ Invalid student');
    }

    $select = $conn->prepare("SELECT profile_image FROM students WHERE lrn = ?");
    $select->bind_param("s", $lrn);
    $select->execute();
    $result = $select->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        if ($row['profile_image'] && file_exists(STUDENT_UPLOAD_DIR . $row['profile_image'])) {
            unlink(STUDENT_UPLOAD_DIR . $row['profile_image']);
        }
    }
    $select->close();

    $stmt = $conn->prepare("DELETE FROM students WHERE lrn = ?");
    $stmt->bind_param("s", $lrn);

    if ($stmt->execute()) {
        header("Location: students_list.php?status=deleted");
        exit();
    } else {
        alertAndBack('❌ Error deleting student: ' . addslashes($stmt->error));
    }
    $stmt->close();
}

//
// ------------- EDIT STUDENT -------------
//
if (isset($_POST['edit_student'])) {

    $original_lrn = htmlspecialchars(safeTrim($_POST['edit_id'] ?? ''));
    $lrn = htmlspecialchars(safeTrim($_POST['edit_lrn'] ?? ''));
    $lastname = htmlspecialchars(safeTrim($_POST['edit_lastname'] ?? ''));
    $firstname = htmlspecialchars(safeTrim($_POST['edit_firstname'] ?? ''));
    $middlename = htmlspecialchars(safeTrim($_POST['edit_middlename'] ?? ''));
    $suffix = htmlspecialchars(safeTrim($_POST['edit_suffix'] ?? ''));
    $age = !empty($_POST['edit_age']) ? (int)$_POST['edit_age'] : 0;
    $birth_raw = htmlspecialchars(safeTrim($_POST['edit_birthdate'] ?? ''));
    $sex = htmlspecialchars(safeTrim($_POST['edit_sex'] ?? ''));
    $existingProfile = htmlspecialchars(safeTrim($_POST['existing_profile'] ?? ''));

    $birthdate = convertDateFormat($birth_raw);
    if ($birthdate === null) {
        alertAndBack('❌ Invalid birthdate format (use MM-DD-YYYY or MM/DD/YYYY)');
    }

    if (!in_array($sex, ['Male', 'Female'])) {
        alertAndBack('❌ Please select a valid sex.');
    }

    if (strlen($lrn) !== 12 || !ctype_digit($lrn)) {
        alertAndBack('❌ LRN must be exactly 12 digits');
    }

    list($profileImage, $imgErrors) = handleProfileImageUpload('edit_profile_image', $existingProfile);
    if ($imgErrors) {
        alertAndBack(implode("\\n", $imgErrors));
    }

    $stmt = $conn->prepare("UPDATE students
                            SET lrn=?, lastname=?, firstname=?, middlename=?, suffix=?, age=?, birthdate=?, sex=?, profile_image=?
                            WHERE lrn=?");

    $stmt->bind_param("sssssissss", $lrn, $lastname, $firstname, $middlename, $suffix, $age, $birthdate, $sex, $profileImage, $original_lrn);

    if ($stmt->execute()) {
        header("Location: students_list.php?status=updated");
        exit();
    } else {
        alertAndBack('❌ Error updating student: ' . addslashes($stmt->error));
    }
    $stmt->close();
}

//
// ------------- FETCH STUDENTS -------------
//
$students_result = $conn->query("SELECT * FROM students ORDER BY lastname ASC, firstname ASC");
if (!$students_result) {
    die("Error fetching students: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Student List - Management System</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@400;500&display=swap">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<style>
.container-fluid { width: 100%; padding: 0; margin: 0; }

body {
    margin: 0;
    padding: 0;
    font-family: 'Inter', 'Segoe UI', sans-serif;
    background: #f6f8ff;
    color: #1a1a1a;
}

.header-section {
    width: 100%;
    padding: 1rem 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
    position: relative;
}

.search-container {
    display: flex;
    gap: 10px;
    align-items: center;
}

.table-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    width: min(100%, 1200px);
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
    margin: 0 auto;
}

.table-card h2 {
    margin: 0 0 1.5rem;
    font-size: 1.5rem;
    color: #111827;
    text-align: center;
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
    border: 1px solid #e5e7eb;
    border-bottom: 1px solid #e5e7eb;
    background-color: #f9fafb;
}

.custom-table tbody td {
    padding: 0.85rem 1rem;
    border: 1px solid #f1f5f9;
    vertical-align: middle;
}

.custom-table tbody tr:last-child td { border-bottom: 1px solid #f1f5f9; }
.custom-table tbody tr:hover { background: rgba(59, 130, 246, 0.06); }

.lrn-cell { font-weight: 600; font-size: 0.85rem; }

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
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
}

.action-icon-btn .material-symbols-outlined {
    font-size: 1.1em;
    font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
}

.action-icon-btn.edit-icon .material-symbols-outlined { color: #1c74e4; }
.action-icon-btn.delete-icon .material-symbols-outlined { color: #dc3545; }
.action-icon-btn:hover .material-symbols-outlined { opacity: 0.7; }

.action-icon-btn:hover {
    background-color: #f1f5f9;
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.action-icon-btn:focus { outline: 2px solid #1c74e4; outline-offset: 2px; }

.modal-content input.form-control,
.modal-content select.form-select {
    border: 1px solid #ccc;
    box-shadow: 2px 4px 8px rgba(0,0,0,0.05);
    border-radius: 8px;
    padding: 0.5rem 0.75rem;
    text-align: left;
}

.modal-content label { font-weight: 500; margin-bottom: 0.25rem; }

.profile-img-thumb {
    width: 36px;
    height: 36px;
    object-fit: cover;
    border-radius: 50%;
    border: 1px solid #ddd;
}

.img-preview {
    width: 120px;
    height: 120px;
    border-radius: 10px;
    object-fit: cover;
    border: 1px solid #d1d5db;
    margin-top: 10px;
}

.clear-search {
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    font-size: 1.2rem;
    padding: 0 5px;
}
.clear-search:hover { color: #000; }
</style>

<script>
function validateLRN(input) {
    input.value = input.value.replace(/\D/g, '');
    if (input.value.length !== 12) {
        input.setCustomValidity('LRN must be exactly 12 digits');
    } else {
        input.setCustomValidity('');
    }
}

function showImagePreview(input, targetId) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById(targetId);
        img.src = e.target.result;
        img.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

function openEditModal(student) {
    if (!student || !student.lrn) {
        alert('❌ Invalid student data');
        return;
    }

    $('#editModal').modal('show');
    $('#edit_id').val(student.lrn);
    $('#edit_lrn').val(student.lrn);
    $('#edit_lastname').val(student.lastname || '');
    $('#edit_firstname').val(student.firstname || '');
    $('#edit_middlename').val(student.middlename || '');
    $('#edit_suffix').val(student.suffix || '');
    $('#edit_age').val(student.age || '');
    $('#edit_sex').val(student.sex || '');

    // DB is expected: YYYY-MM-DD
    // Display in input: MM-DD-YYYY
    if (student.birthdate) {
        const parts = student.birthdate.split("-");
        if (parts.length === 3) {
            $('#edit_birthdate').val(parts[1] + '-' + parts[2] + '-' + parts[0]);
        } else {
            $('#edit_birthdate').val(student.birthdate);
        }
    } else {
        $('#edit_birthdate').val('');
    }

    if (student.profile_image) {
        $('#existing_profile').val(student.profile_image);
        $('#currentImage').attr('src', 'uploads/' + student.profile_image).show();
        $('#removeImageBtn').show();
    } else {
        $('#existing_profile').val('');
        $('#currentImage').hide();
        $('#removeImageBtn').hide();
    }
    $('#edit_profile_image').val('');
    $('#editImagePreview').hide();
}

$(document).ready(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');

    if (status) {
        const messages = {
            'added': '✓ Student added successfully!',
            'deleted': '✓ Student deleted successfully!',
            'updated': '✓ Student updated successfully!',
            'imported': '✓ Students imported successfully!'
        };

        if (messages[status]) {
            alert(messages[status]);
            window.history.replaceState({}, document.title, 'students_list.php');
        }
    }

    $('#csvfile').on('change', function() {
        const fileName = $(this).val().split('\\').pop();
        $('#csvFileName').text(fileName || 'No file chosen');
    });

    $('#profile_image').on('change', function() {
        showImagePreview(this, 'imagePreview');
    });

    $('#edit_profile_image').on('change', function() {
        showImagePreview(this, 'editImagePreview');
        $('#removeImageBtn').show();
    });

    $('#removeImageBtn').on('click', function() {
        $('#existing_profile').val('');
        $('#currentImage').hide();
        $('#editImagePreview').hide();
        $('#edit_profile_image').val('');
        $(this).hide();
    });

    $('#searchInput').on('keyup', function() {
        const value = $(this).val().toLowerCase();
        $('.custom-table tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    $('#clearSearch').on('click', function() {
        $('#searchInput').val('').trigger('keyup');
        $(this).hide();
    });

    $('#searchInput').on('input', function() {
        $('#clearSearch').toggle($(this).val().length > 0);
    });
});
</script>
</head>

<body>
<?php include 'nav.php'; ?>

<div class="container-fluid mt-4 mb-5">
    <div class="header-section">
        <div style="display: flex; align-items: center;">
            <img src="img/depedlogo.jpg" alt="Dashboard Logo" style="width: 50px; height: 50px; margin-right: 10px;">
            <h2 class="h3 mb-0 text-gray-800">Student Dashboard</h2>
        </div>

        <div class="search-container">
            <div class="input-group" style="width: 250px; position: relative;">
                <input type="text" id="searchInput" class="form-control" placeholder="Search Student">
                <span class="search-icon" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #6c757d; pointer-events: none; z-index: 2;">
                    <span class="material-symbols-outlined" style="font-size: 1.2em;">search</span>
                </span>
                <button class="clear-search" id="clearSearch" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #6c757d; cursor: pointer; display:none; z-index: 3;">×</button>
            </div>

            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-radius: 8px;">
                <span class="material-symbols-outlined" style="font-size: 1.2em; vertical-align: middle;">upload_file</span> Import CSV
            </button>

            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-radius: 8px;">
                <span class="material-symbols-outlined" style="font-size: 1.2em; vertical-align: middle;">person_add</span> Add Student
            </button>
        </div>
    </div>

    <div class="table-card">
        <div class="table-responsive-custom">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 5%;">#</th>
                        <th style="width: 12%;">LRN</th>
                        <th style="width: 5%;">Photo</th>
                        <th style="width: 12%;">Lastname</th>
                        <th style="width: 12%;">Firstname</th>
                        <th style="width: 10%;">Middlename</th>
                        <th style="width: 5%;">Suffix</th>
                        <th style="width: 5%;">Age</th>
                        <th style="width: 10%;">Birthdate</th>
                        <th style="width: 8%;">Sex</th>
                        <th style="width: 8%; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $count = 1;
                if ($students_result->num_rows > 0):
                    while ($row = $students_result->fetch_assoc()):
                        // DB: YYYY-MM-DD -> Display: MM-DD-YYYY
                        $display_date = '';
                        if (!empty($row['birthdate'])) {
                            $parts = explode("-", $row['birthdate']);
                            if (count($parts) === 3) {
                                $display_date = "{$parts[1]}-{$parts[2]}-{$parts[0]}";
                            } else {
                                $display_date = $row['birthdate'];
                            }
                        }
                ?>
                    <tr>
                        <td><?= $count++ ?></td>
                        <td class="lrn-cell"><?= htmlspecialchars($row['lrn']) ?></td>
                        <td>
                            <?php if (!empty($row['profile_image']) && file_exists(STUDENT_UPLOAD_DIR . $row['profile_image'])): ?>
                                <img src="uploads/<?= htmlspecialchars($row['profile_image']) ?>" class="profile-img-thumb" alt="Profile">
                            <?php else: ?>
                                <span class="material-symbols-outlined" style="font-size: 1.4rem; color: #9ca3af;">account_circle</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['lastname']) ?></td>
                        <td><?= htmlspecialchars($row['firstname']) ?></td>
                        <td><?= htmlspecialchars($row['middlename'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['suffix'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['age']) ?></td>
                        <td><?= htmlspecialchars($display_date) ?></td>
                        <td><?= htmlspecialchars($row['sex'] ?? '-') ?></td>
                        <td class="actions-cell">
                            <button type="button" class="action-icon-btn edit-icon"
                                onclick='openEditModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)'
                                title="Edit">
                                <span class="material-symbols-outlined">edit</span>
                            </button>

                            <form method="POST" class="d-inline"
                                  onsubmit="return confirm('Are you sure you want to delete student <?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?>?');">
                                <input type="hidden" name="student_lrn" value="<?= htmlspecialchars($row['lrn']) ?>">
                                <button type="submit" name="delete_student" class="action-icon-btn delete-icon" title="Delete">
                                    <span class="material-symbols-outlined">delete</span>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php
                    endwhile;
                else:
                    echo '<tr><td colspan="11" class="text-center text-muted py-4">No students found.</td></tr>';
                endif;
                ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- IMPORT MODAL -->
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">📥 Import Students from CSV</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>Select CSV File</strong></label>
                        <input type="file" name="csvfile" class="form-control" accept=".csv" required>
                        <small class="text-muted">Maximum 5MB | Format: CSV</small>
                    </div>

                    <div class="alert alert-info">
                        <strong>📋 CSV Format Required:</strong>
                        <pre style="font-size: 12px; margin-top: 10px;">LRN,Lastname,Firstname,Middlename,Suffix,Age,Birthdate,Sex</pre>
                        <small class="text-muted">Birthdate must be MM-DD-YYYY or MM/DD/YYYY</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="import_csv" class="btn btn-success">
                        <span class="material-symbols-outlined" style="font-size: 1.2em; vertical-align: middle;">cloud_upload</span> Upload & Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ADD STUDENT MODAL -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">➕ Add New Student</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">LRN (12 digits) *</label>
                        <input type="text" name="lrn" class="form-control" maxlength="12" oninput="validateLRN(this)" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Firstname *</label>
                        <input type="text" name="firstname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lastname *</label>
                        <input type="text" name="lastname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Middlename</label>
                        <input type="text" name="middlename" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Suffix</label>
                        <input type="text" name="suffix" class="form-control" placeholder="Jr., Sr.">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Age *</label>
                        <input type="number" name="age" class="form-control" min="0" max="120" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Birthdate (MM-DD-YYYY or MM/DD/YYYY) *</label>
                        <input type="text" name="birthdate" class="form-control" placeholder="MM-DD-YYYY" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sex *</label>
                        <select name="sex" class="form-select" required>
                            <option value="" disabled selected>Select sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Profile Image</label>
                        <input type="file" name="profile_image" id="profile_image" class="form-control" accept="image/jpeg,image/png,image/gif">
                        <small class="text-muted">Max 2MB | JPG / PNG / GIF</small>
                        <img id="imagePreview" class="img-preview" style="display:none;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title">✎ Edit Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" id="edit_id" name="edit_id">
                    <input type="hidden" id="existing_profile" name="existing_profile">

                    <div class="mb-3">
                        <label class="form-label">LRN *</label>
                        <input type="text" id="edit_lrn" name="edit_lrn" class="form-control" maxlength="12" oninput="validateLRN(this)" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Firstname *</label>
                        <input type="text" id="edit_firstname" name="edit_firstname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lastname *</label>
                        <input type="text" id="edit_lastname" name="edit_lastname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Middlename</label>
                        <input type="text" id="edit_middlename" name="edit_middlename" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Suffix</label>
                        <input type="text" id="edit_suffix" name="edit_suffix" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Age *</label>
                        <input type="number" id="edit_age" name="edit_age" class="form-control" min="0" max="120" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Birthdate (MM-DD-YYYY or MM/DD/YYYY) *</label>
                        <input type="text" id="edit_birthdate" name="edit_birthdate" class="form-control" placeholder="MM-DD-YYYY" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sex *</label>
                        <select id="edit_sex" name="edit_sex" class="form-select" required>
                            <option value="" disabled>Select sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Profile Image</label>
                        <input type="file" name="edit_profile_image" id="edit_profile_image" class="form-control" accept="image/jpeg,image/png,image/gif">
                        <small class="text-muted">Upload to replace current photo.</small>
                        <img id="currentImage" class="img-preview" style="display:none;">
                        <img id="editImagePreview" class="img-preview" style="display:none;">
                        <button type="button" id="removeImageBtn" class="btn btn-sm btn-danger mt-2" style="display:none;">Remove Image</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="edit_student" class="btn btn-primary">Update Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>