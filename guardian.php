<?php
session_start();
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
include 'conn.php'; // Assuming this file correctly connects to your database
if ($conn->connect_error) {
    if (!is_dir('logs')) mkdir('logs', 0755, true);
    error_log(date('c') . " DB Conn Error: " . $conn->connect_error . "\n", 3, 'logs/error_log.txt');
    die("DB connection failed. See logs/error_log.txt");
}

// --- Fetch Students for LRN Datalist/Dropdown (Feature 1) ---
$students_for_datalist = [];
$students_query_dl = $conn->query("
    SELECT lrn, firstname, lastname, middlename
    FROM students
    ORDER BY lastname, firstname
");
if ($students_query_dl) {
    while ($s_dl = $students_query_dl->fetch_assoc()) {
        $fullName = htmlspecialchars($s_dl['lastname'] . ', ' . $s_dl['firstname']);
        if (!empty($s_dl['middlename'])) {
            $fullName .= ' ' . htmlspecialchars(substr($s_dl['middlename'], 0, 1)) . '.';
        }
        $students_for_datalist[] = [
            'lrn' => $s_dl['lrn'],
            'display_name' => $fullName // This will be the displayed part of the option
        ];
    }
}


// CSRF Token Validation
function validate_csrf_token() {
    if (isset($_POST['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo "<script>alert('Invalid CSRF');location='guardian.php'</script>";
        exit;
    }
}

// --- Fetch Guardians for the JavaScript ---
$existing_relationships = $conn->query("
    SELECT lrn, relationship_to_student
    FROM guardians
    ORDER BY lrn, relationship_to_student
") or die("Error fetching existing relationships: " . $conn->error);

$student_relationships = [];
while ($rel = $existing_relationships->fetch_assoc()) {
    $lrn = $rel['lrn'];
    $relationship = strtolower($rel['relationship_to_student']); // Convert to lowercase for comparison
    if (!isset($student_relationships[$lrn])) {
        $student_relationships[$lrn] = [];
    }
    $student_relationships[$lrn][] = $relationship;
}

// --- Add Guardian ---
if (isset($_POST['add_guardian'])) {
    validate_csrf_token();

    $lrn = filter_var($_POST['lrn'], FILTER_SANITIZE_NUMBER_INT);
    $lrn_val = (int)$lrn;
    $lastname = htmlspecialchars($_POST['lastname'], ENT_QUOTES);
    $firstname = htmlspecialchars($_POST['firstname'], ENT_QUOTES);
    $middlename = htmlspecialchars($_POST['middlename'] ?? '', ENT_QUOTES);
    $suffix = htmlspecialchars($_POST['suffix'] ?? '', ENT_QUOTES);
    $contact = htmlspecialchars($_POST['contact'], ENT_QUOTES);
    $rel = htmlspecialchars($_POST['relationship_to_student'], ENT_QUOTES);

    // Basic validation
    if (!$lrn_val || !$lastname || !$firstname || !$contact || !$rel) {
        echo "<script>alert('Fill all required fields.');location='guardian.php'</script>";
        exit;
    }

    // --- Server-side: Validate Contact Number Length and Digits for Add (Feature 3) ---
    if (strlen($contact) !== 11 || !ctype_digit($contact)) {
        echo "<script>alert('Contact number must be exactly 11 digits long and contain only numbers.');location='guardian.php'</script>";
        exit;
    }

    // --- Server-side: Check if LRN exists in students table (Feature 2) ---
    $checkLrnExists = $conn->prepare("SELECT COUNT(*) AS count FROM students WHERE lrn = ?");
    if (!$checkLrnExists) {
        error_log(date('c') . " PREPARE ERR (checkLrnExists): " . $conn->error . "\n", 3, 'logs/guardian_errors.txt');
        echo "<script>alert('DB Error: Could not prepare LRN existence check.');location='guardian.php'</script>";
        exit;
    }
    $checkLrnExists->bind_param("i", $lrn_val); // Assuming LRN in students table is integer
    $checkLrnExists->execute();
    $lrnResult = $checkLrnExists->get_result()->fetch_assoc();
    if ($lrnResult['count'] == 0) {
        echo "<script>alert('LRN did not register yet.');location='guardian.php'</script>";
        exit;
    }
    $checkLrnExists->close();


    // --- Check if a guardian already exists for this LRN ---
    $checkDuplicate = $conn->prepare("SELECT COUNT(*) AS count FROM guardians WHERE lrn = ?");
    if (!$checkDuplicate) {
        error_log(date('c') . " PREPARE ERR (checkDuplicate): " . $conn->error . "\n", 3, 'logs/guardian_errors.txt');
        echo "<script>alert('DB Error: Could not prepare duplicate check.');location='guardian.php'</script>";
        exit;
    }
    $checkDuplicate->bind_param("i", $lrn_val);
    $checkDuplicate->execute();
    $duplicateResult = $checkDuplicate->get_result()->fetch_assoc();
    if ($duplicateResult['count'] > 0) {
        echo "<script>alert('A guardian is already assigned to student with LRN " . $lrn_val . ".');location='guardian.php'</script>";
        exit;
    }
    $checkDuplicate->close();

    // Add Guardian
    $guardianInsert = $conn->prepare("INSERT INTO guardians (lrn, lastname, firstname, middlename, suffix, contact_number, relationship_to_student) VALUES(?,?,?,?,?,?,?)");
    if (!$guardianInsert) {
        error_log(date('c') . " PREPARE ERR (guardianInsert): " . $conn->error . "\n", 3, 'logs/guardian_errors.txt');
        echo "<script>alert('DB Error: Could not prepare guardian insert.');location='guardian.php'</script>";
        exit;
    }
    $contact_int = (int)$contact; // Ensure contact is an integer if DB column is INT
    $guardianInsert->bind_param("issssis", $lrn_val, $lastname, $firstname, $middlename, $suffix, $contact_int, $rel);
    if ($guardianInsert->execute()) {
        header("Location: guardian.php?status=added");
        exit;
    } else {
        file_put_contents('logs/guardian_errors.txt', date('c') . " INSERT ERR " . $guardianInsert->error . "\n", FILE_APPEND);
        echo "<script>alert('DB Error during guardian insert: " . $guardianInsert->error . "');location='guardian.php'</script>";
        exit;
    }
}

// --- Delete Guardian ---
if (isset($_POST['delete_guardian'])) {
    validate_csrf_token();
    $id = (int)$_POST['guardian_id'];

    $del = $conn->prepare("DELETE FROM guardians WHERE guardian_id=?");
    $del->bind_param("i", $id);
    if ($del->execute()) {
        if ($del->affected_rows > 0) {
            header("Location: guardian.php?status=deleted");
        } else {
            echo "<script>alert('Guardian ID not found.');location='guardian.php'</script>";
        }
        exit;
    } else {
        echo "<script>alert('Delete failed: " . $del->error . "');location='guardian.php'</script>";
        exit;
    }
}

// --- Edit Guardian ---
if (isset($_POST['edit_guardian'])) {
    validate_csrf_token();
    $id = (int)$_POST['edit_guardian_id'];
    $lrn = filter_var($_POST['lrn'], FILTER_SANITIZE_NUMBER_INT);
    $lrn_val = (int)$lrn;
    $lastname = htmlspecialchars($_POST['lastname'], ENT_QUOTES);
    $firstname = htmlspecialchars($_POST['firstname'], ENT_QUOTES);
    $middlename = htmlspecialchars($_POST['middlename'] ?? '', ENT_QUOTES);
    $suffix = htmlspecialchars($_POST['suffix'] ?? '', ENT_QUOTES);
    $contact = htmlspecialchars($_POST['contact'], ENT_QUOTES); // Kept as string for validation first
    $rel = htmlspecialchars($_POST['relationship_to_student'], ENT_QUOTES);

    if (!$id || !$lrn_val || !$lastname || !$firstname || !$contact || !$rel) {
        echo "<script>alert('Fill all required fields');location='guardian.php'</script>";
        exit;
    }

    // --- Server-side: Validate Contact Number Length and Digits for Edit (Feature 3) ---
    if (strlen($contact) !== 11 || !ctype_digit($contact)) {
        echo "<script>alert('Contact number must be exactly 11 digits long and contain only numbers.');location='guardian.php'</script>";
        exit;
    }

    // --- Server-side: Check if LRN exists in students table for edit (Feature 2) ---
    $checkLrnExistsEdit = $conn->prepare("SELECT COUNT(*) AS count FROM students WHERE lrn = ?");
    if (!$checkLrnExistsEdit) {
        error_log(date('c') . " PREPARE ERR (checkLrnExistsEdit): " . $conn->error . "\n", 3, 'logs/guardian_errors.txt');
        echo "<script>alert('DB Error: Could not prepare LRN existence check during edit.');location='guardian.php'</script>";
        exit;
    }
    $checkLrnExistsEdit->bind_param("i", $lrn_val); // Assuming LRN in students table is integer
    $checkLrnExistsEdit->execute();
    $lrnResultEdit = $checkLrnExistsEdit->get_result()->fetch_assoc();
    if ($lrnResultEdit['count'] == 0) {
        echo "<script>alert('LRN did not register yet.');location='guardian.php'</script>";
        exit;
    }
    $checkLrnExistsEdit->close();

    // --- Check if the new LRN is already taken by another guardian (if a student can only have one guardian) ---
    $checkDuplicateEdit = $conn->prepare(
        "SELECT COUNT(*) AS count
         FROM guardians
        WHERE lrn = ? AND guardian_id != ?"
    );
    $checkDuplicateEdit->bind_param("ii", $lrn_val, $id);
    $checkDuplicateEdit->execute();
    $duplicateEditResult = $checkDuplicateEdit->get_result()->fetch_assoc();
    if ($duplicateEditResult['count'] > 0) {
        echo "<script>alert('The student with LRN " . $lrn_val . " is already assigned to a different guardian.');location='guardian.php'</script>";
        exit;
    }
    $checkDuplicateEdit->close();

    // Update Guardian
    $updateGuardian = $conn->prepare(
        "UPDATE guardians
         SET
             lrn = ?,
             lastname = ?,
             firstname = ?,
             middlename = ?,
             suffix = ?,
             contact_number = ?,
             relationship_to_student = ?
         WHERE guardian_id = ?"
    );

    $contact_int_val = (int)$contact; // Cast contact to integer if DB column is INT AFTER validation
    $updateGuardian->bind_param(
        "issssssi", // 'i' for integer, 's' for string
        $lrn_val,
        $lastname,
        $firstname,
        $middlename,
        $suffix,
        $contact_int_val, // Use the integer-casted contact value
        $rel,
        $id
    );

    if ($updateGuardian->execute()) {
        header("Location: guardian.php?status=updated");
        exit;
    } else {
        file_put_contents('logs/guardian_errors.txt', date('c') . " UPDATE ERR " . $updateGuardian->error . "\n", FILE_APPEND);
        echo "<script>alert('Update failed: " . $updateGuardian->error . "');location='guardian.php'</script>";
        exit;
    }
}
// --- Fetch Guardians for Table ---
$guardians = $conn->query("
    SELECT
    g.guardian_id,
    g.lrn,
    g.lastname,
    g.firstname,
    g.middlename,
    g.suffix,
    g.contact_number AS contact,
    g.relationship_to_student,
    CONCAT(s.firstname, ' ', s.lastname) as student_name
FROM guardians g
LEFT JOIN students s ON g.lrn = s.lrn
ORDER BY g.lastname ASC, g.firstname ASC
") or die("Error fetching guardians: " . $conn->error);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Guardians Dashboard</title>

    <!-- Custom fonts and styles -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        @keyframes hl {
            0% {
                background-color: #c8e6c9;
            }
            100% {
                background-color: transparent;
            }
        }

        .highlight {
            animation: hl 2s;
        }

        .text-danger {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body id="page-top">
<?php include 'nav.php'; ?>

<div id="wrapper">
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="h3 mb-0 text-gray-800">Guardians Dashboard</h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"
                            style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                        Add Guardian
                    </button>
                </div>
                <div class="bg-gray-50 rounded-xl p-4 shadow-md">
                    <div class="bg-gray-100 rounded-xl p-5 shadow-md">
                        <div class="overflow-x-auto">
                            <table class="w-full table-auto border border-gray-300 text-sm text-center">
                                <thead class="bg-gray-200 font-semibold">
                                <tr>
                                    <th class="border px-3 py-2">#</th>
                                    <th class="border px-3 py-2">LRN</th>
                                    <th class="border px-3 py-2">Lastname</th>
                                    <th class="border px-3 py-2">Firstname</th>
                                    <th class="border px-3 py-2">Middlename</th>
                                    <th class="border px-3 py-2">Suffix</th>
                                    <th class="border px-3 py-2">Contact</th>
                                    <th class="border px-3 py-2">Relationship</th>
                                    <th class="border px-3 py-2">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php
                                $counter = 1;
                                while ($g = $guardians->fetch_assoc()):
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="border px-3 py-2"><?= $counter++ ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($g['lrn'] ?? '') ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($g['lastname'] ?? '') ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($g['firstname'] ?? '') ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($g['middlename'] ?? '') ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($g['suffix'] ?? '') ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($g['contact'] ?? '') ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($g['relationship_to_student'] ?? '') ?></td>
                                        <td class="border px-3 py-2 space-x-2">
                                            <!-- Edit Button -->
                                            <button onclick='openEditModal(<?= json_encode($g) ?>)'
                                                    class="text-blue-600 hover:text-blue-800 p-1 rounded">
                                                <i data-lucide="pencil" class="w-5 h-5 inline"></i>
                                            </button>
                                            <!-- Delete Form -->
                                            <form method="POST" class="inline"
                                                  onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars($g['firstname'] . ' ' . $g['lastname']) ?>?');">
                                                <input type="hidden" name="csrf_token"
                                                       value="<?= $_SESSION['csrf_token'] ?>"/>
                                                <input type="hidden" name="guardian_id"
                                                       value="<?= htmlspecialchars($g['guardian_id'] ?? '') ?>"/>
                                                <button type="submit" name="delete_guardian"
                                                        class="text-red-600 hover:text-red-800 p-1 rounded">
                                                    <i data-lucide="trash" class="w-5 h-5 inline"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <?php if ($guardians->num_rows === 0): ?>
                                    <tr>
                                        <td colspan="9" class="border px-3 py-2 text-center text-gray-500">No
                                            guardians found.
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
</div>

<!-- Add Guardian Modal -->
<div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="position: relative;">
            <div class="modal-header">
                <h5 class="modal-title" id="addModalLabel">Add Guardian Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="container mt-3">
                    <div class="row justify-content-center">
                        <div class="col-md-10">
                            <div class="card p-4 shadow-sm" style="border-radius: 20px; background-color: rgba(255,255,255,0.9);">
                                <img src="images/you.png" width="100" height="100" alt=""
                                     class="rounded-circle mx-auto d-block"><br>
                                <h3 class="text-center">ADD GUARDIAN</h3><br>
                                <form method="post" name="add_guardian_form">  <!-- Added form name for JavaScript -->
                                    <input type="hidden" name="csrf_token"
                                           value="<?= $_SESSION['csrf_token'] ?>">
                                    <div class="mb-3">
                                        <label for="add_lrn" class="form-label">Student LRN</label>
                                        <!-- Feature 1: Changed from <select> to <input type="text"> with <datalist> -->
                                        <input type="text" id="add_lrn" name="lrn" class="form-control" list="student_lrns"
                                               placeholder="Type LRN or select from list" required
                                               style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        <datalist id="student_lrns">
                                            <?php foreach ($students_for_datalist as $student): ?>
                                                <option value="<?= htmlspecialchars($student['lrn']) ?>"><?= $student['display_name'] ?></option>
                                            <?php endforeach; ?>
                                        </datalist>
                                    </div>
                                    <div class="mb-3">
                                        <label for="add_lastname" class="form-label">Last Name</label>
                                        <input id="add_lastname" type="text" class="form-control" name="lastname"
                                               placeholder="Enter Last Name" required
                                               style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                    </div>
                                    <div class="mb-3">
                                        <label for="add_firstname" class="form-label">First Name</label>
                                        <input id="add_firstname" type="text" class="form-control" name="firstname"
                                               placeholder="Enter First Name" required
                                               style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                    </div>
                                    <div class="mb-3">
                                        <label for="add_middlename" class="form-label">Middle Name</label>
                                        <input id="add_middlename" type="text" class="form-control" name="middlename"
                                               placeholder="Enter Middle Name"
                                               style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                    </div>
                                    <div class="mb-3">
                                        <label for="add_suffix" class="form-label">Suffix</label>
                                        <input id="add_suffix" type="text" class="form-control" name="suffix"
                                               placeholder="Enter Suffix (Jr., III, etc.)"
                                               style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                    </div>
                                    <div class="mb-3">
                                        <label for="add_contact" class="form-label">Contact Number</label>
                                        <!-- Feature 3: Client-side validation for 11 digits -->
                                        <input id="add_contact" type="text" class="form-control" name="contact"
                                               placeholder="Enter Contact Number" required
                                               pattern="[0-9]{11}" maxlength="11"
                                               title="Contact number must be exactly 11 digits long and contain only numbers."
                                               style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                    </div>
                                    <div class="mb-3">
                                        <label for="add_relationship" class="form-label">Relationship to Student</label>
                                        <input id="add_relationship" type="text" class="form-control" name="relationship_to_student"
                                               placeholder="Enter Relationship to Student" required
                                               style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        <div id="relationship-warning" class="text-danger mt-2" style="display:none;"></div>
                                    </div>
                                    <div class="text-center">
                                        <button type="submit" name="add_guardian" id="add_guardian_btn"
                                                class="btn btn-primary px-4">Register Guardian
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Guardian Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="position: relative;">
            <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel">Edit Guardian Information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="container mt-3">
                    <div class="row justify-content-center">
                        <div class="col-md-10">
                            <div class="card p-4 shadow-sm" style="border-radius: 20px; background-color: rgba(255,255,255,0.9);">
                                <img src="images/you.png" width="100" height="100" alt=""
                                     class="rounded-circle mx-auto d-block"><br>
                                <h3 class="text-center">EDIT GUARDIAN</h3><br>
                                <form method="post">
                                    <!-- Hidden field to store the original ID -->
                                    <input type="hidden" name="csrf_token"
                                           value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" id="edit_id" name="edit_guardian_id">
                                    <div class="mb-3">
                                        <label for="edit_lrn" class="form-label">Student LRN</label>
                                        <!-- Feature 1: Added list="student_lrns" to the existing input -->
                                        <input id="edit_lrn" type="text" class="form-control" name="lrn"
                                               placeholder="Enter LRN" required list="student_lrns"
                                               style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        <!-- The datalist 'student_lrns' is defined once above for the addModal -->
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_lastname" class="form-label">Last Name</label>
                                        <input id="edit_lastname" type="text" class="form-control" name="lastname"
                                               placeholder="Enter Last Name" required
                                               style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_firstname" class="form-label">First Name</label>
                                        <input id="edit_firstname" type="text" class="form-control" name="firstname"
                                               placeholder="Enter First Name" required
                                               style="border: 1px solid #ccc; box-shadow: 2px 44px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_middlename" class="form-label">Middle Name</label>
                                        <input id="edit_middlename" type="text" class="form-control" name="middlename"
                                               placeholder="Enter Middle Name"
                                               style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_suffix" class="form-label">Suffix</label>
                                        <input id="edit_suffix" type="text" class="form-control" name="suffix"
                                               placeholder="Enter Suffix (Jr., III, etc.)"
                                               style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_contact" class="form-label">Contact Number</label>
                                        <!-- Feature 3: Client-side validation for 11 digits -->
                                        <input id="edit_contact" type="text" class="form-control" name="contact"
                                               placeholder="Enter Contact Number" required
                                               pattern="[0-9]{11}" maxlength="11"
                                               title="Contact number must be exactly 11 digits long and contain only numbers."
                                               style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                    </div>
                                    <div class="mb-3">
                                        <label for="edit_rel" class="form-label">Relationship to Student</label>
                                        <input id="edit_rel" type="text" class="form-control" name="relationship_to_student"
                                               placeholder="Enter Relationship to Student" required
                                               style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                    </div>
                                    <div class="text-center">
                                        <button type="submit" name="edit_guardian"
                                                class="btn btn-primary px-4">Update Guardian
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function () {
        lucide.createIcons();

        // Open the Edit Modal and fill in the fields with the selected guardian info.
        window.openEditModal = function (g) {
            $('#editModal').modal('show');
            // The hidden field "edit_id" stores the original ID.
            $('#edit_id').val(g.guardian_id);
            // New values, so the user can update the details if desired.
            $('#edit_lrn').val(g.lrn);
            $('#edit_lastname').val(g.lastname);
            $('#edit_firstname').val(g.firstname);
            $('#edit_middlename').val(g.middlename);
            $('#edit_suffix').val(g.suffix);
            $('#edit_contact').val(g.contact); // This now correctly uses the 'contact' alias from the SELECT query
            $('#edit_rel').val(g.relationship_to_student);
        }

        // Show status messages based on URL parameters.
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        if (status) {
            let message = "";
            if (status === "added") {
                message = "Guardian added successfully!";
            } else if (status === "deleted") {
                message = "Guardian deleted successfully!";
            } else if (status === "updated") {
                message = "Guardian updated successfully!";
            }
            if (message) {
                alert(message);
                // Clear the status parameter from the URL after displaying the message
                window.history.replaceState({}, document.title, "guardian.php");
            }
        }
    });
</script>
</body>
</html>