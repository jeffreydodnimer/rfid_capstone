<?php
session_start();
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'conn.php';
if ($conn->connect_error) {
    if (!is_dir('logs')) mkdir('logs', 0755, true);
    error_log(date('Y-m-d H:i:s') . " DB Conn Error: " . $conn->connect_error . "\n", 3, 'logs/error_log.txt');
    die("DB connection failed. See logs/error_log.txt");
}

// --- Add Guardian ---
if (isset($_POST['add_guardian'])) {
    // CSRF validation
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo "<script>alert('Invalid CSRF token.');location='guardians_list.php'</script>"; exit;
    }

    // Data sanitization and validation
    $lrn = filter_var($_POST['lrn'], FILTER_SANITIZE_NUMBER_INT);
    $lrn_val = (int)$lrn;
    $lastname = htmlspecialchars($_POST['lastname'], ENT_QUOTES);
    $firstname = htmlspecialchars($_POST['firstname'], ENT_QUOTES);
    $middlename = htmlspecialchars($_POST['middlename'] ?? '', ENT_QUOTES);
    $suffix = htmlspecialchars($_POST['suffix'] ?? '', ENT_QUOTES);
    $contact = htmlspecialchars($_POST['contact'], ENT_QUOTES);
    $rel = htmlspecialchars($_POST['relationship_to_student'], ENT_QUOTES);

    // Basic validations
    if (empty($lrn) || empty($lastname) || empty($firstname) || empty($contact) || empty($rel)) {
        echo "<script>alert('Please fill all required fields.');location='guardians_list.php'</script>"; exit;
    }
    if (!preg_match("/^[0-9\+\-\(\) ]{7,20}$/", $contact)) {
        echo "<script>alert('Invalid phone number format.');location='guardians_list.php'</script>"; exit;
    }

    // Check for existing student and duplicates
    $chk = $conn->prepare("
        SELECT (SELECT COUNT(*) FROM guardians WHERE lrn = ? AND relationship_to_student = ?) AS dup
        FROM students WHERE lrn = ?
    ");
    $chk->bind_param("isi", $lrn_val, $rel, $lrn_val);
    $chk->execute();
    $r = $chk->get_result()->fetch_assoc();

    if (!$r) {
        echo "<script>alert('LRN not found.');location='guardians_list.php'</script>"; exit;
    }
    if ($r['dup'] > 0) {
        echo "<script>alert('A guardian with this relationship already exists.');location='guardians_list.php'</script>"; exit;
    }
    $chk->close();

    // Insert new guardian
    $ins = $conn->prepare("
        INSERT INTO guardians (lrn, lastname, firstname, middlename, suffix, contact, relationship_to_student)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $ins->bind_param("issssss", $lrn_val, $lastname, $firstname, $middlename, $suffix, $contact, $rel);
    
    if ($ins->execute()) {
        header("Location: guardians_list.php?status=added"); exit;
    } else {
        file_put_contents('logs/guardian_errors.txt', date('Y-m-d H:i:s') . " INSERT ERR " . $ins->error . "\n", FILE_APPEND);
        echo "<script>alert('Database error: " . htmlspecialchars($ins->error) . "');location='guardians_list.php'</script>";
        exit;
    }
}

// --- Delete Guardian ---
if (isset($_POST['delete_guardian'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo "<script>alert('Invalid CSRF token.');location='guardians_list.php'</script>"; exit;
    }
    $id = (int)$_POST['guardian_id'];
    $del = $conn->prepare("DELETE FROM guardians WHERE guardian_id = ?");
    $del->bind_param("i", $id);

    if ($del->execute()) {
        header("Location: guardians_list.php?status=deleted"); exit;
    } else {
        file_put_contents('logs/guardian_errors.txt', date('Y-m-d H:i:s') . " DELETE ERR " . $del->error . "\n", FILE_APPEND);
        echo "<script>alert('Delete failed: " . htmlspecialchars($del->error) . "');location='guardians_list.php'</script>"; exit;
    }
}

// --- Edit Guardian ---
if (isset($_POST['edit_guardian'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo "<script>alert('Invalid CSRF token.');location='guardians_list.php'</script>"; exit;
    }
    $id = (int)$_POST['edit_guardian_id'];
    $lrn = filter_var($_POST['lrn'], FILTER_SANITIZE_NUMBER_INT);
    $lrn_val = (int)$lrn;
    $lastname = htmlspecialchars($_POST['lastname'], ENT_QUOTES);
    $firstname = htmlspecialchars($_POST['firstname'], ENT_QUOTES);
    $middlename = htmlspecialchars($_POST['middlename'] ?? '', ENT_QUOTES);
    $suffix = htmlspecialchars($_POST['suffix'] ?? '', ENT_QUOTES);
    $contact = htmlspecialchars($_POST['contact'], ENT_QUOTES);
    $rel = htmlspecialchars($_POST['relationship_to_student'], ENT_QUOTES);

    if (empty($lrn) || empty($lastname) || empty($firstname) || empty($contact) || empty($rel)) {
        echo "<script>alert('Fill all required fields.');location='guardians_list.php'</script>"; exit;
    }
    if (!preg_match("/^[0-9\+\-\(\) ]{7,20}$/", $contact)) {
        echo "<script>alert('Invalid phone format.');location='guardians_list.php'</script>"; exit;
    }

    // Check for duplicates excluding the current guardian
    $chk = $conn->prepare("
        SELECT (SELECT COUNT(*) FROM guardians WHERE lrn = ? AND relationship_to_student = ? AND guardian_id != ?) AS dup
        FROM students WHERE lrn = ?
    ");
    $chk->bind_param("isii", $lrn_val, $rel, $id, $lrn_val);
    $chk->execute();
    $r = $chk->get_result()->fetch_assoc();
    
    if (!$r) {
        echo "<script>alert('LRN not found.');location='guardians_list.php'</script>"; exit;
    }
    if ($r['dup'] > 0) {
        echo "<script>alert('Duplicate guardian exists.');location='guardians_list.php'</script>"; exit;
    }
    $chk->close();

    $upd = $conn->prepare("
        UPDATE guardians SET lrn=?, lastname=?, firstname=?, middlename=?, suffix=?, contact=?, relationship_to_student=?
        WHERE guardian_id=?
    ");
    $upd->bind_param("issssssi", $lrn_val, $lastname, $firstname, $middlename, $suffix, $contact, $rel, $id);
    
    if ($upd->execute()) {
        header("Location: guardians_list.php?status=updated"); exit;
    } else {
        file_put_contents('logs/guardian_errors.txt', date('Y-m-d H:i:s') . " UPDATE ERR " . $upd->error . "\n", FILE_APPEND);
        echo "<script>alert('Update failed: " . htmlspecialchars($upd->error) . "');location='guardians_list.php'</script>"; exit;
    }
}

// --- Fetch ---
$guardians_result = $conn->query("
    SELECT guardian_id, lrn, lastname, firstname, middlename, suffix,
           contact, relationship_to_student
    FROM guardians
    ORDER BY lastname ASC
") or die("Error fetching guardians: " . htmlspecialchars($conn->error));

// Highlight latest added guardian
$highlight_id = 0;
if (isset($_GET['status']) && ($_GET['status'] === 'added' || $_GET['status'] === 'updated')) {
    $latest_result = $conn->query("SELECT MAX(guardian_id) AS latest_id FROM guardians");
    if ($latest_result && $latest_row = $latest_result->fetch_assoc()) {
        $highlight_id = (int)$latest_row['latest_id'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Guardian List</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        .highlight-row { animation: highlight 2s; }
        @keyframes highlight { from { background-color: #c8e6c9; } to { background-color: transparent; } }
    </style>
</head>
<body>
<div class="container">
    <h2 class="mt-4">Guardians Dashboard</h2>
    <?php if (isset($_GET['status'])): ?>
        <div class="alert alert-success">Guardian <?= htmlspecialchars($_GET['status']) ?>!</div>
    <?php endif; ?>

    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addGuardianModal">Add Guardian</button>

    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>LRN</th>
                <th>Lastname</th>
                <th>Firstname</th>
                <th>Middlename</th>
                <th>Suffix</th>
                <th>Contact</th>
                <th>Relationship</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php $counter = 1; ?>
            <?php while ($guardian = $guardians_result->fetch_assoc()): ?>
                <tr class="<?= ($guardian['guardian_id'] == $highlight_id) ? 'highlight-row' : '' ?>">
                    <td><?= $counter++ ?></td>
                    <td><?= htmlspecialchars($guardian['lrn']) ?></td>
                    <td><?= htmlspecialchars($guardian['lastname']) ?></td>
                    <td><?= htmlspecialchars($guardian['firstname']) ?></td>
                    <td><?= htmlspecialchars($guardian['middlename']) ?></td>
                    <td><?= htmlspecialchars($guardian['suffix']) ?></td>
                    <td><?= htmlspecialchars($guardian['contact']) ?></td>
                    <td><?= htmlspecialchars($guardian['relationship_to_student']) ?></td>
                    <td>
                        <button class="btn btn-warning btn-sm" onclick='openEditGuardianModal(<?= json_encode($guardian) ?>)'>Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars($guardian['firstname'] . ' ' . $guardian['lastname']) ?>?');">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="guardian_id" value="<?= htmlspecialchars($guardian['guardian_id']) ?>">
                            <button type="submit" name="delete_guardian" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Add Guardian Modal -->
    <div class="modal fade" id="addGuardianModal" tabindex="-1" aria-labelledby="addGuardianModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addGuardianModalLabel">Add Guardian Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="guardians_list.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="mb-3">
                            <label for="guardian_lrn" class="form-label">Student LRN</label>
                            <input type="text" class="form-control" id="guardian_lrn" name="lrn" required>
                        </div>
                        <div class="mb-3">
                            <label for="guardian_lastname" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="guardian_lastname" name="lastname" required>
                        </div>
                        <div class="mb-3">
                            <label for="guardian_firstname" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="guardian_firstname" name="firstname" required>
                        </div>
                        <div class="mb-3">
                            <label for="guardian_middlename" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="guardian_middlename" name="middlename">
                        </div>
                        <div class="mb-3">
                            <label for="guardian_suffix" class="form-label">Suffix</label>
                            <input type="text" class="form-control" id="guardian_suffix" name="suffix">
                        </div>
                        <div class="mb-3">
                            <label for="guardian_contact" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="guardian_contact" name="contact" required>
                        </div>
                        <div class="mb-3">
                            <label for="relationship" class="form-label">Relationship to Student</label>
                            <input type="text" class="form-control" id="relationship" name="relationship_to_student" required>
                        </div>
                        <button type="submit" name="add_guardian" class="btn btn-primary">Register Guardian</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Guardian Modal -->
    <div class="modal fade" id="editGuardianModal" tabindex="-1" aria-labelledby="editGuardianModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editGuardianModalLabel">Edit Guardian Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="guardians_list.php" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" id="edit_guardian_id" name="edit_guardian_id">
                        <div class="mb-3">
                            <label for="edit_guardian_lrn" class="form-label">Student LRN</label>
                            <input type="text" class="form-control" id="edit_guardian_lrn" name="lrn" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_guardian_lastname" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="edit_guardian_lastname" name="lastname" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_guardian_firstname" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="edit_guardian_firstname" name="firstname" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_guardian_middlename" class="form-label">Middle Name</label>
                            <input type="text" class="form-control" id="edit_guardian_middlename" name="middlename">
                        </div>
                        <div class="mb-3">
                            <label for="edit_guardian_suffix" class="form-label">Suffix</label>
                            <input type="text" class="form-control" id="edit_guardian_suffix" name="suffix">
                        </div>
                        <div class="mb-3">
                            <label for="edit_guardian_contact" class="form-label">Contact Number</label>
                            <input type="text" class="form-control" id="edit_guardian_contact" name="contact" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_relationship" class="form-label">Relationship to Student</label>
                            <input type="text" class="form-control" id="edit_relationship" name="relationship_to_student" required>
                        </div>
                        <button type="submit" name="edit_guardian" class="btn btn-primary">Update Guardian</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openEditGuardianModal(guardian) {
            document.getElementById('edit_guardian_id').value = guardian.guardian_id;
            document.getElementById('edit_guardian_lrn').value = guardian.lrn;
            document.getElementById('edit_guardian_lastname').value = guardian.lastname;
            document.getElementById('edit_guardian_firstname').value = guardian.firstname;
            document.getElementById('edit_guardian_middlename').value = guardian.middlename;
            document.getElementById('edit_guardian_suffix').value = guardian.suffix;
            document.getElementById('edit_guardian_contact').value = guardian.contact;
            document.getElementById('edit_relationship').value = guardian.relationship_to_student;

            var editModal = new bootstrap.Modal(document.getElementById('editGuardianModal'));
            editModal.show();
        }
    </script>
</div>
</body>
</html>