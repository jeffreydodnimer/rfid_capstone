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

// --- Database Connection Check ---
if ($conn->connect_error) {
    error_log(date('c') . " DB Conn Error: " . $conn->connect_error . "\n", 3, 'logs/error_log.txt');
    die("Database connection failed. Please check the logs.");
}
$conn->set_charset("utf8");

// --- CSRF Token Generation ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function validate_csrf_token() {
    if (isset($_POST['csrf_token']) && !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        error_log(date('c') . " CSRF Attack Detected: " . $_SERVER['REMOTE_ADDR'] . "\n", 3, 'logs/adviser_errors.txt');
        echo "<script>alert('Invalid security token. Please try again.');location='adviser.php'</script>";
        exit();
    }
}

/*****************
ADVISER MANAGEMENT
*******************/

// --- Handle Add Adviser ---
if (isset($_POST['add_adviser'])) {
    validate_csrf_token();
    $employee_id = htmlspecialchars(trim($_POST['employee_id']), ENT_QUOTES, 'UTF-8');
    $lastname    = htmlspecialchars(trim($_POST['lastname']), ENT_QUOTES, 'UTF-8');
    $firstname   = htmlspecialchars(trim($_POST['firstname']), ENT_QUOTES, 'UTF-8');
    $middlename  = htmlspecialchars(trim($_POST['middlename']), ENT_QUOTES, 'UTF-8');
    $suffix      = htmlspecialchars(trim($_POST['suffix']), ENT_QUOTES, 'UTF-8');
    $gender      = ($_POST['gender'] === 'male') ? 'male' : 'female';

    if (empty($employee_id) || empty($lastname) || empty($firstname)) {
        echo "<script>alert('Employee ID, Last Name and First Name are required.'); location='adviser.php'</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // Check duplicate employee_id
        $dup = $conn->prepare("SELECT adviser_id FROM advisers WHERE employee_id = ?");
        $dup->bind_param("s", $employee_id);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            throw new Exception("Employee ID '$employee_id' already exists.");
        }
        $dup->close();

        $stmt = $conn->prepare("INSERT INTO advisers (employee_id, lastname, firstname, middlename, suffix, gender) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $employee_id, $lastname, $firstname, $middlename, $suffix, $gender);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        $conn->commit();

        header("Location: adviser.php?status=added");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error adding adviser: " . $e->getMessage() . "'); location='adviser.php'</script>";
        exit();
    }
}

// --- Handle Delete Adviser ---
if (isset($_POST['delete_adviser'])) {
    validate_csrf_token();
    $adviser_id = filter_var($_POST['adviser_id'], FILTER_VALIDATE_INT);
    if ($adviser_id === false) {
        echo "<script>alert('Invalid Adviser ID.'); location='adviser.php'</script>";
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM advisers WHERE adviser_id = ?");
    $stmt->bind_param("i", $adviser_id);
    if ($stmt->execute()) {
        header("Location: adviser.php?status=deleted");
    } else {
        echo "<script>alert('Delete failed: " . $stmt->error . "'); location='adviser.php'</script>";
    }
    exit();
}

// --- Handle Edit Adviser ---
if (isset($_POST['edit_adviser'])) {
    validate_csrf_token();
    $adviser_id  = filter_var($_POST['edit_adviser_id'], FILTER_VALIDATE_INT);
    $employee_id = htmlspecialchars(trim($_POST['edit_employee_id']), ENT_QUOTES, 'UTF-8');
    $lastname    = htmlspecialchars(trim($_POST['edit_lastname']), ENT_QUOTES, 'UTF-8');
    $firstname   = htmlspecialchars(trim($_POST['edit_firstname']), ENT_QUOTES, 'UTF-8');
    $middlename  = htmlspecialchars(trim($_POST['edit_middlename']), ENT_QUOTES, 'UTF-8');
    $suffix      = htmlspecialchars(trim($_POST['edit_suffix']), ENT_QUOTES, 'UTF-8');
    $gender      = ($_POST['edit_gender'] === 'female') ? 'female' : 'male';

    if ($adviser_id === false || empty($employee_id) || empty($lastname) || empty($firstname)) {
        echo "<script>alert('Invalid input. Please fill out all required fields.'); location='adviser.php'</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // Check duplicate employee_id from another record
        $dup = $conn->prepare("SELECT adviser_id FROM advisers WHERE employee_id = ? AND adviser_id != ?");
        $dup->bind_param("si", $employee_id, $adviser_id);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            throw new Exception("Employee ID '$employee_id' already exists.");
        }
        $dup->close();

        $stmt = $conn->prepare("UPDATE advisers SET employee_id = ?, lastname = ?, firstname = ?, middlename = ?, suffix = ?, gender = ? WHERE adviser_id = ?");
        $stmt->bind_param("ssssssi", $employee_id, $lastname, $firstname, $middlename, $suffix, $gender, $adviser_id);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        $conn->commit();
        header("Location: adviser.php?status=updated");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error updating adviser: " . $e->getMessage() . "'); location='adviser.php'</script>";
        exit();
    }
}

/***********************************
CSV IMPORT FOR ADVISERS (OPTIONAL)
***********************************/
function safeTrimCSV($value) {
    if (is_null($value) || is_array($value) || is_bool($value) || is_object($value)) {
        return '';
    }
    return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$value));
}

if (isset($_POST['import_advisers_csv']) && isset($_FILES['adviser_csvfile']) && $_FILES['adviser_csvfile']['error'] === UPLOAD_ERR_OK) {
    validate_csrf_token();
    $tmpPath  = $_FILES['adviser_csvfile']['tmp_name'];
    $fileName = $_FILES['adviser_csvfile']['name'];
    $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($fileExt !== 'csv') {
        echo "<script>alert('❌ Please upload a CSV file.'); location='adviser.php'</script>";
        exit();
    }
    if ($_FILES['adviser_csvfile']['size'] > 5 * 1024 * 1024) { // 5MB max
        echo "<script>alert('❌ File size exceeds 5MB limit.'); location='adviser.php'</script>";
        exit();
    }
    if ($conn->connect_error) {
        error_log(date('c') . " DB Conn Error during CSV import: " . $conn->connect_error . "\n", 3, $adviser_error_log);
        echo "<script>alert('Database connection lost. Cannot import CSV.'); location='adviser.php'</script>";
        exit();
    }
    
    if (($handle = fopen($tmpPath, 'r')) !== false) {
        ini_set('auto_detect_line_endings', 1);
        $header = fgetcsv($handle, 2000, ",");
        // Expected CSV header columns:
        // employee_id, lastname, firstname, middlename, suffix, gender
        $expected_headers = ['employee_id', 'lastname', 'firstname', 'middlename', 'suffix', 'gender'];
        if (!is_array($header) || count($header) < count($expected_headers)) {
            fclose($handle);
            echo "<script>alert('❌ CSV must have these columns: employee_id, lastname, firstname, middlename, suffix, gender'); location='adviser.php'</script>";
            exit();
        }
        // Map CSV columns to indices
        $col_map = [];
        foreach ($expected_headers as $expected_header) {
            $found = false;
            foreach ($header as $h_idx => $h_val) {
                if (strtolower(trim($h_val)) === $expected_header) {
                    $col_map[$expected_header] = $h_idx;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                fclose($handle);
                echo "<script>alert('❌ Missing column: {$expected_header}'); location='adviser.php'</script>";
                exit();
            }
        }
        $stmt = $conn->prepare("INSERT INTO advisers (employee_id, lastname, firstname, middlename, suffix, gender) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            fclose($handle);
            error_log(date('c') . " PREPARE ERR (CSV Import): " . $conn->error . "\n", 3, $adviser_error_log);
            echo "<script>alert('❌ DB error preparing CSV import.'); location='adviser.php'</script>";
            exit();
        }
        $rowCount = 0;
        $errors   = [];
        $row_num  = 1;
        while (($data = fgetcsv($handle, 2000, ",")) !== false) {
            $row_num++;
            if (!is_array($data) || count(array_filter($data)) === 0 || count($data) < count($expected_headers)) {
                continue;
            }
            $csv_employee_id = safeTrimCSV($data[$col_map['employee_id']] ?? '');
            $csv_lastname    = htmlspecialchars(trim(safeTrimCSV($data[$col_map['lastname']] ?? '')), ENT_QUOTES);
            $csv_firstname   = htmlspecialchars(trim(safeTrimCSV($data[$col_map['firstname']] ?? '')), ENT_QUOTES);
            $csv_middlename  = htmlspecialchars(trim(safeTrimCSV($data[$col_map['middlename']] ?? '')), ENT_QUOTES);
            $csv_suffix      = htmlspecialchars(trim(safeTrimCSV($data[$col_map['suffix']] ?? '')), ENT_QUOTES);
            $csv_gender      = strtolower(safeTrimCSV($data[$col_map['gender']] ?? '')) === 'female' ? 'female' : 'male';

            if (empty($csv_employee_id) || empty($csv_lastname) || empty($csv_firstname)) {
                $errors[] = "Row {$row_num}: Missing required fields.";
                continue;
            }
            // Check for duplicate adviser using employee_id
            $dup = $conn->prepare("SELECT adviser_id FROM advisers WHERE employee_id = ?");
            $dup->bind_param("s", $csv_employee_id);
            $dup->execute();
            $dup->store_result();
            if ($dup->num_rows > 0) {
                $errors[] = "Row {$row_num}: Employee ID '$csv_employee_id' already exists.";
                $dup->close();
                continue;
            }
            $dup->close();

            $stmt->bind_param("ssssss", $csv_employee_id, $csv_lastname, $csv_firstname, $csv_middlename, $csv_suffix, $csv_gender);
            if (!$stmt->execute()) {
                error_log(date('c') . " CSV Import Exec Error: " . $stmt->error . "\n", 3, $adviser_error_log);
                $errors[] = "Row {$row_num}: DB error during insert.";
            } else {
                $rowCount++;
            }
        }
        fclose($handle);
        $stmt->close();
        
        $message = "✓ CSV Import Complete\nSuccessfully imported: {$rowCount} advisers.\n";
        if (!empty($errors)) {
            $message .= "\n❌ Errors (" . count($errors) . " rows failed):\n" . implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= "\n... and " . (count($errors) - 5) . " more errors.";
            }
        }
        echo "<script>alert(" . json_encode($message) . ");location='adviser.php'</script>";
        exit();
    } else {
        echo "<script>alert('❌ Failed to open CSV file. Check file permissions or integrity.'); location='adviser.php'</script>";
        exit();
    }
}

// --- Fetch all advisers for display ---
$advisers_result = $conn->query("SELECT * FROM advisers ORDER BY lastname, firstname");
if (!$advisers_result) {
    die("Error fetching advisers: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Advisers Dashboard</title>
    <!-- Custom fonts and styles -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <!-- Material Symbols -->
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
    </style>
    <script>
      $(document).ready(function(){
        // Initialize search functionality
        const searchInput = $('#searchGuardian');
        const clearBtn = $('#clearSearch');
        const tableRows = $('.custom-table tbody tr');
        searchInput.on('input', function() {
            const searchTerm = $(this).val().toLowerCase().trim();
            if(searchTerm.length > 0) { clearBtn.addClass('show'); } else { clearBtn.removeClass('show'); }
            let visibleCount = 0;
            tableRows.each(function(){
                const row = $(this);
                if(row.text().toLowerCase().includes(searchTerm)){
                    row.show(); visibleCount++;
                }else{
                    row.hide();
                }
            });
            if(visibleCount === 0 && searchTerm.length > 0 && $('#noResults').length === 0) {
                $('.custom-table tbody').append('<tr id="noResults"><td colspan="9" class="border px-3 py-2 text-center text-gray-500"><span class="material-symbols-outlined" style="font-size:1.2rem;">search_off</span> No advisers found matching "' + searchTerm + '"</td></tr>');
            } else { $('#noResults').remove(); }
        });
        clearBtn.on('click', function(){
            searchInput.val('');
            clearBtn.removeClass('show');
            tableRows.show();
            $('#noResults').remove();
            searchInput.focus();
        });
        searchInput.on('keydown', function(e) { if(e.key === 'Escape') clearBtn.click(); });
      });
      
      // Populate Edit Modal with adviser data
      function openEditAdviserModal(data) {
          const editModal = new bootstrap.Modal(document.getElementById('editAdviserModal'));
          document.getElementById('edit_adviser_id').value   = data.adviser_id;
          document.getElementById('edit_employee_id').value  = data.employee_id;
          document.getElementById('edit_lastname').value     = data.lastname;
          document.getElementById('edit_firstname').value    = data.firstname;
          document.getElementById('edit_middlename').value   = data.middlename;
          document.getElementById('edit_suffix').value       = data.suffix;
          document.getElementById('edit_gender').value       = data.gender;
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
                <!-- Logo/Photo - update with your actual logo path -->
                <img src="img/depedlogo.jpg" alt="Adviser Logo" class="page-logo">
                <h2 class="h3 mb-0 text-gray-800">Advisers Dashboard</h2>
              </div>
              <div class="d-flex align-items-center">
                <div class="search-box">
                  <input type="text" id="searchGuardian" placeholder="Search Adviser" autocomplete="off">
                  <button type="button" class="clear-search" id="clearSearch" title="Clear search">
                    <span class="material-symbols-outlined">close</span>
                  </button>
                  <span class="search-icon">
                    <span class="material-symbols-outlined">search</span>
                  </span>
                </div>
                <!-- Import CSV Button -->
                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span class="material-symbols-outlined">upload_file</span> Import CSV
                </button>
                <!-- Add Adviser Button -->
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdviserModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                    <span class="material-symbols-outlined">person_add</span> Add Adviser
                </button>
              </div>
            </div>
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
                      <th class="border px-3 py-2">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      $counter = 1;
                      while ($row = $advisers_result->fetch_assoc()):
                    ?>
                    <tr class="hover:bg-gray-50">
                      <td class="border px-3 py-2"><?= $counter++ ?></td>
                      <td class="border px-3 py-2"><?= htmlspecialchars($row['employee_id']) ?></td>
                      <td class="border px-3 py-2"><?= htmlspecialchars($row['lastname']) ?></td>
                      <td class="border px-3 py-2"><?= htmlspecialchars($row['firstname']) ?></td>
                      <td class="border px-3 py-2"><?= htmlspecialchars($row['middlename']) ?></td>
                      <td class="border px-3 py-2"><?= htmlspecialchars($row['suffix']) ?></td>
                      <td class="border px-3 py-2"><?= htmlspecialchars($row['gender']) ?></td>
                      <td class="border px-3 py-2 actions-cell">
                        <button onclick='openEditAdviserModal(<?= json_encode($row) ?>)' class="action-icon-btn edit-icon" title="Edit Adviser">
                          <span class="material-symbols-outlined">edit</span>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete adviser <?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?>?');">
                          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                          <input type="hidden" name="adviser_id" value="<?= $row['adviser_id'] ?>">
                          <button type="submit" name="delete_adviser" class="action-icon-btn delete-icon" title="Delete Adviser">
                            <span class="material-symbols-outlined">delete</span>
                          </button>
                        </form>
                      </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($counter === 1): ?>
                      <tr>
                        <td colspan="8" class="border px-3 py-2 text-center text-gray-500">No advisers found.</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div><!-- container-fluid -->
        </div><!-- content -->
      </div><!-- content-wrapper -->
    </div><!-- wrapper -->

    <!-- Add Adviser Modal -->
    <div class="modal fade" id="addAdviserModal" tabindex="-1" aria-labelledby="addAdviserModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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

    <!-- Import Adviser CSV Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="modal-header">
              <h5 class="modal-title" id="importModalLabel">Import Advisers from CSV</h5>
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label for="adviser_csvfile" class="form-label">Select CSV File *</label>
                <input type="file" name="adviser_csvfile" id="adviser_csvfile" class="form-control" accept=".csv" required>
                <small class="text-muted">Max 5MB | Columns: employee_id, lastname, firstname, middlename, suffix, gender</small>
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