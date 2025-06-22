<?php
session_start(); // Must be at the VERY TOP of every PHP page that uses sessions

// Authentication check
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

include 'conn.php'; // Database connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8");

/**
 * Handle Add Adviser
 */
if (isset($_POST['add_adviser'])) {
    $employee_id = htmlspecialchars(trim($_POST['employee_id']), ENT_QUOTES, 'UTF-8');
    $lastname    = htmlspecialchars(trim($_POST['lastname']),    ENT_QUOTES, 'UTF-8');
    $firstname   = htmlspecialchars(trim($_POST['firstname']),   ENT_QUOTES, 'UTF-8');
    $middlename  = htmlspecialchars(trim($_POST['middlename']),  ENT_QUOTES, 'UTF-8');
    $suffix      = htmlspecialchars(trim($_POST['suffix']),      ENT_QUOTES, 'UTF-8');
    $gender      = $_POST['gender'] === 'male' ? 'male' : 'female';

    // Validate required fields
    if (empty($employee_id) || empty($lastname) || empty($firstname)) {
        echo "<script>alert('Error: Employee ID, Lastname, and Firstname are required.'); window.location.href='advisers.php';</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // Prevent duplicate employee_id
        $dup = $conn->prepare("SELECT adviser_id FROM advisers WHERE employee_id = ?");
        $dup->bind_param("s", $employee_id);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            throw new Exception("Employee ID '$employee_id' already exists.");
        }
        $dup->close();

        // Insert
        $stmt = $conn->prepare(
            "INSERT INTO advisers (employee_id, lastname, firstname, middlename, suffix, gender)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("ssssss",
            $employee_id, $lastname, $firstname, $middlename, $suffix, $gender
        );
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();

        $conn->commit();
        header("Location: advisers.php?status=added");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error adding adviser: " . $e->getMessage() . "'); window.location.href='advisers.php';</script>";
        exit();
    }
}

/**
 * Handle Delete Adviser
 */
if (isset($_POST['delete_adviser'])) {
    $adviser_id = filter_var($_POST['adviser_id'], FILTER_VALIDATE_INT);
    if ($adviser_id === false || $adviser_id === null) {
        echo "<script>alert('Invalid Adviser ID.'); window.location.href='advisers.php';</script>";
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM advisers WHERE adviser_id = ?");
    $stmt->bind_param("i", $adviser_id);
    if ($stmt->execute()) {
        header("Location: advisers.php?status=deleted");
        exit();
    } else {
        echo "<script>alert('Delete failed: " . $stmt->error . "'); window.location.href='advisers.php';</script>";
        exit();
    }
}

/**
 * Handle Edit Adviser
 */
if (isset($_POST['edit_adviser'])) {
    $adviser_id  = filter_var($_POST['edit_adviser_id'], FILTER_VALIDATE_INT);
    $employee_id = htmlspecialchars(trim($_POST['edit_employee_id']), ENT_QUOTES, 'UTF-8');
    $lastname    = htmlspecialchars(trim($_POST['edit_lastname']),    ENT_QUOTES, 'UTF-8');
    $firstname   = htmlspecialchars(trim($_POST['edit_firstname']),   ENT_QUOTES, 'UTF-8');
    $middlename  = htmlspecialchars(trim($_POST['edit_middlename']),  ENT_QUOTES, 'UTF-8');
    $suffix      = htmlspecialchars(trim($_POST['edit_suffix']),      ENT_QUOTES, 'UTF-8');
    $gender      = $_POST['edit_gender'] === 'female' ? 'female' : 'male';

    if ($adviser_id === false || empty($employee_id) || empty($lastname) || empty($firstname)) {
        echo "<script>alert('Invalid input. Please fill out all required fields.'); window.location.href='advisers.php';</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // Prevent duplicate employee_id (excluding current)
        $dup = $conn->prepare(
            "SELECT adviser_id FROM advisers 
             WHERE employee_id = ? AND adviser_id != ?"
        );
        $dup->bind_param("si", $employee_id, $adviser_id);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            throw new Exception("Employee ID '$employee_id' already exists.");
        }
        $dup->close();

        $stmt = $conn->prepare(
            "UPDATE advisers
             SET employee_id=?, lastname=?, firstname=?, middlename=?, suffix=?, gender=?
             WHERE adviser_id=?"
        );
        $stmt->bind_param("ssssssi",
            $employee_id, $lastname, $firstname, $middlename, $suffix, $gender, $adviser_id
        );
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();

        $conn->commit();
        header("Location: advisers.php?status=updated");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error updating adviser: " . $e->getMessage() . "'); window.location.href='advisers.php';</script>";
        exit();
    }
}

// Fetch all advisers for display
$advisers_result = $conn->query("SELECT * FROM advisers ORDER BY lastname, firstname");
if (!$advisers_result) {
    die("Error fetching advisers: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Manage Advisers</title>
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
  <link href="css/sb-admin-2.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body id="page-top">
  <?php include 'nav.php'; ?>
  <div class="container-fluid mt-4">
    <div class="d-flex justify-content-between mb-3">
      <h2>Advisers</h2>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdviserModal">
        <i class="fas fa-plus"></i> Add Adviser
      </button>
    </div>
    <?php if (isset($_GET['status'])): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php
          if ($_GET['status']==='added')   echo 'Adviser added.';
          if ($_GET['status']==='updated') echo 'Adviser updated.';
          if ($_GET['status']==='deleted') echo 'Adviser deleted.';
        ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <table class="table table-bordered text-center">
      <thead class="table-light">
        <tr>
          <th>No.</th>
          <th>Employee ID</th>
          <th>Last Name</th>
          <th>First Name</th>
          <th>Middle Name</th>
          <th>Suffix</th>
          <th>Gender</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php $i=1; while($row=$advisers_result->fetch_assoc()): ?>
          <tr>
            <td><?= $i++ ?></td>
            <td><?= htmlspecialchars($row['employee_id']) ?></td>
            <td><?= htmlspecialchars($row['lastname']) ?></td>
            <td><?= htmlspecialchars($row['firstname']) ?></td>
            <td><?= htmlspecialchars($row['middlename']) ?></td>
            <td><?= htmlspecialchars($row['suffix']) ?></td>
            <td><?= htmlspecialchars($row['gender']) ?></td>
            <td>
              <button class="btn btn-sm btn-info"
                onclick='openEditAdviserModal(<?= json_encode([
                  'adviser_id'  => $row['adviser_id'],
                  'employee_id' => $row['employee_id'],
                  'lastname'    => $row['lastname'],
                  'firstname'   => $row['firstname'],
                  'middlename'  => $row['middlename'],
                  'suffix'      => $row['suffix'],
                  'gender'      => $row['gender']
                ]) ?>)'>
                <i class="fas fa-edit"></i>
              </button>
              <form method="post" class="d-inline"
                onsubmit="return confirm('Delete adviser <?= htmlspecialchars($row['lastname']) ?>?');">
                <input type="hidden" name="adviser_id" value="<?= $row['adviser_id'] ?>">
                <button name="delete_adviser" class="btn btn-sm btn-danger">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </td>
          </tr>
        <?php endwhile; ?>
        <?php if ($i===1): ?>
          <tr><td colspan="8" class="text-muted">No advisers found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Add Adviser Modal -->
  <div class="modal fade" id="addAdviserModal" tabindex="-1" aria-labelledby="addAdviserModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post">
          <div class="modal-header">
            <h5 class="modal-title" id="addAdviserModalLabel">Add Adviser</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Employee ID</label>
              <input type="text" name="employee_id" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Last Name</label>
              <input type="text" name="lastname" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">First Name</label>
              <input type="text" name="firstname" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Middle Name</label>
              <input type="text" name="middlename" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Suffix</label>
              <input type="text" name="suffix" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Gender</label>
              <select name="gender" class="form-control" required>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" name="add_adviser" class="btn btn-primary">Add Adviser</button>
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
          <input type="hidden" id="edit_adviser_id" name="edit_adviser_id">
          <div class="modal-header">
            <h5 class="modal-title" id="editAdviserModalLabel">Edit Adviser</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Employee ID</label>
              <input type="text" id="edit_employee_id" name="edit_employee_id" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Last Name</label>
              <input type="text" id="edit_lastname" name="edit_lastname" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">First Name</label>
              <input type="text" id="edit_firstname" name="edit_firstname" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Middle Name</label>
              <input type="text" id="edit_middlename" name="edit_middlename" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Suffix</label>
              <input type="text" id="edit_suffix" name="edit_suffix" class="form-control">
            </div>
            <div class="mb-3">
              <label class="form-label">Gender</label>
              <select id="edit_gender" name="edit_gender" class="form-control" required>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" name="edit_adviser" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    lucide.createIcons();
    function openEditAdviserModal(data) {
      const m = new bootstrap.Modal(document.getElementById('editAdviserModal'));
      document.getElementById('edit_adviser_id').value  = data.adviser_id;
      document.getElementById('edit_employee_id').value = data.employee_id;
      document.getElementById('edit_lastname').value    = data.lastname;
      document.getElementById('edit_firstname').value   = data.firstname;
      document.getElementById('edit_middlename').value  = data.middlename;
      document.getElementById('edit_suffix').value      = data.suffix;
      document.getElementById('edit_gender').value      = data.gender;
      m.show();
    }
  </script>
</body>
</html>