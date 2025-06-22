<?php
session_start();

if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

include 'conn.php';
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8");

/**
 * Available grade levels (static)
 */
$available_grade_levels = [
    'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'
];

/**
 * Fetch students for LRN dropdown
 */
$students_result_for_dropdown = $conn->query(
    "SELECT lrn, lastname, firstname FROM students ORDER BY lastname, firstname"
);
if (!$students_result_for_dropdown) {
    error_log("Error fetching students for dropdown: " . $conn->error);
    $students_result_for_dropdown = (object)[
        'num_rows'   => 0,
        'data_seek'  => function($o){},
        'fetch_assoc'=> function(){ return null; }
    ];
}

/**
 * Fetch advisers for dropdown
 * FIX: Select individual name components and construct the full name.
 */
$advisers = [];
// Select all necessary name parts
$adv_rs = $conn->query("SELECT adviser_id, lastname, firstname, middlename, suffix FROM advisers ORDER BY lastname, firstname");
if ($adv_rs) {
    while ($adv = $adv_rs->fetch_assoc()) {
        $fullName = htmlspecialchars($adv['lastname'] . ', ' . $adv['firstname']);
        // Add middle initial if available
        if (!empty($adv['middlename'])) {
            $fullName .= ' ' . htmlspecialchars(substr($adv['middlename'], 0, 1)) . '.';
        }
        // Add suffix if available
        if (!empty($adv['suffix'])) {
            $fullName .= ' ' . htmlspecialchars($adv['suffix']);
        }
        $advisers[] = [
            'adviser_id' => $adv['adviser_id'],
            'name'       => $fullName // Store the constructed full name under the 'name' key
        ];
    }
} else {
    error_log("Error fetching advisers: " . $conn->error);
}

/**
 * Handle Add Enrollment
 */
if (isset($_POST['add_enrollment'])) {
    // Sanitize inputs
    $lrn          = htmlspecialchars(trim($_POST['lrn']), ENT_QUOTES, 'UTF-8');
    $section_name = htmlspecialchars(trim($_POST['section_name']), ENT_QUOTES, 'UTF-8');
    $grade_level  = htmlspecialchars(trim($_POST['grade_level']), ENT_QUOTES, 'UTF-8');
    $school_year  = htmlspecialchars(trim($_POST['school_year']), ENT_QUOTES, 'UTF-8');

    // Validate adviser_id
    $adviser_id = filter_var($_POST['adviser_id'], FILTER_VALIDATE_INT);
    if ($adviser_id === false || $adviser_id === null) {
        echo "<script>alert('Error: Please select a valid Adviser.'); window.location.href='enrollment.php';</script>";
        exit();
    }

    // Basic required‐field check
    if (empty($lrn) || empty($section_name) || empty($grade_level) || empty($school_year)) {
        echo "<script>alert('Error: All required fields must be filled.'); window.location.href='enrollment.php';</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // 1) LRN exists?
        $stmt = $conn->prepare("SELECT 1 FROM students WHERE lrn = ?");
        $stmt->bind_param("s", $lrn);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception("LRN not found. Please add the student first.");
        }
        $stmt->close();

        // 2) Adviser exists?
        $stmt = $conn->prepare("SELECT 1 FROM advisers WHERE adviser_id = ?");
        $stmt->bind_param("i", $adviser_id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception("Selected Adviser not found.");
        }
        $stmt->close();

        // 3) Prevent duplicate
        $stmt = $conn->prepare("
            SELECT enrollment_id
            FROM enrollments
            WHERE lrn = ? AND section_name = ? AND grade_level = ?
              AND adviser_id = ? AND school_year = ?
        ");
        $stmt->bind_param("sssis", $lrn, $section_name, $grade_level, $adviser_id, $school_year);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            throw new Exception("This enrollment already exists.");
        }
        $stmt->close();

        // 4) Insert
        $stmt = $conn->prepare("
            INSERT INTO enrollments
            (lrn, section_name, grade_level, adviser_id, school_year)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssis", $lrn, $section_name, $grade_level, $adviser_id, $school_year);
        if (!$stmt->execute()) {
            throw new Exception("Failed to add enrollment: " . $stmt->error);
        }
        $stmt->close();

        $conn->commit();
        echo "<script>alert('Enrollment added successfully!'); window.location.href='enrollment.php?status=added';</script>";
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error adding enrollment: " . $e->getMessage() . "'); window.location.href='enrollment.php';</script>";
        exit();
    }
}

/**
 * Handle Delete Enrollment
 */
if (isset($_POST['delete_enrollment'])) {
    $enrollment_id = filter_var($_POST['enrollment_id'], FILTER_VALIDATE_INT);
    if ($enrollment_id === false || $enrollment_id === null) {
        echo "<script>alert('Invalid Enrollment ID.'); window.location.href='enrollment.php';</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM enrollments WHERE enrollment_id = ?");
        $stmt->bind_param("i", $enrollment_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete: " . $stmt->error);
        }
        $stmt->close();

        $conn->commit();
        echo "<script>alert('Enrollment deleted successfully!'); window.location.href='enrollment.php?status=deleted';</script>";
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error deleting enrollment: " . $e->getMessage() . "'); window.location.href='enrollment.php';</script>";
        exit();
    }
}

/**
 * Handle Edit Enrollment
 */
if (isset($_POST['edit_enrollment'])) {
    $enrollment_id = filter_var($_POST['edit_enrollment_id'], FILTER_VALIDATE_INT);
    $lrn           = htmlspecialchars(trim($_POST['edit_lrn']), ENT_QUOTES, 'UTF-8');
    $section_name  = htmlspecialchars(trim($_POST['edit_section_name']), ENT_QUOTES, 'UTF-8');
    $grade_level   = htmlspecialchars(trim($_POST['edit_grade_level']), ENT_QUOTES, 'UTF-8');
    $school_year   = htmlspecialchars(trim($_POST['edit_school_year']), ENT_QUOTES, 'UTF-8');
    $adviser_id    = filter_var($_POST['edit_adviser_id'], FILTER_VALIDATE_INT);

    // Validate inputs
    if (
        $enrollment_id === false || $enrollment_id === null ||
        empty($lrn) || empty($section_name) ||
        empty($grade_level) || empty($school_year) ||
        $adviser_id === false || $adviser_id === null
    ) {
        echo "<script>alert('Invalid ID or missing fields.'); window.location.href='enrollment.php';</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // 1) LRN exists?
        $stmt = $conn->prepare("SELECT 1 FROM students WHERE lrn = ?");
        $stmt->bind_param("s", $lrn);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception("LRN not found.");
        }
        $stmt->close();

        // 2) Adviser exists?
        $stmt = $conn->prepare("SELECT 1 FROM advisers WHERE adviser_id = ?");
        $stmt->bind_param("i", $adviser_id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception("Selected Adviser not found.");
        }
        $stmt->close();

        // 3) Prevent duplicate (excluding current)
        $stmt = $conn->prepare("
            SELECT enrollment_id
            FROM enrollments
            WHERE lrn = ? AND section_name = ? AND grade_level = ?
              AND adviser_id = ? AND school_year = ?
              AND enrollment_id != ?
        ");
        $stmt->bind_param("sssisi", $lrn, $section_name, $grade_level, $adviser_id, $school_year, $enrollment_id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            throw new Exception("Another identical enrollment exists.");
        }
        $stmt->close();

        // 4) Update
        $stmt = $conn->prepare("
            UPDATE enrollments
            SET lrn = ?, section_name = ?, grade_level = ?, adviser_id = ?, school_year = ?
            WHERE enrollment_id = ?
        ");
        $stmt->bind_param("sssisi", $lrn, $section_name, $grade_level, $adviser_id, $school_year, $enrollment_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update: " . $stmt->error);
        }
        $stmt->close();

        $conn->commit();
        echo "<script>alert('Enrollment updated successfully!'); window.location.href='enrollment.php?status=updated';</script>";
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error updating enrollment: " . $e->getMessage() . "'); window.location.href='enrollment.php';</script>";
        exit();
    }
}

/**
 * Fetch enrollments for display in the table
 * FIX: Construct adviser_name from individual name columns using CONCAT_WS.
 */
$enrollments_query = "
    SELECT
        e.enrollment_id, e.lrn, e.section_name, e.grade_level,
        e.adviser_id, e.school_year,
        COALESCE(s.firstname, 'N/A') AS student_firstname,
        COALESCE(s.middlename, '')   AS student_middlename,
        COALESCE(s.lastname, 'N/A')  AS student_lastname,
        -- Construct adviser_name from individual components in the advisers table
        COALESCE(
            CONCAT_WS(' ',
                a.lastname, ',', a.firstname,
                CASE WHEN a.middlename IS NOT NULL AND a.middlename != '' THEN CONCAT(LEFT(a.middlename, 1), '.') ELSE NULL END,
                CASE WHEN a.suffix IS NOT NULL AND a.suffix != '' THEN a.suffix ELSE NULL END
            ),
            'N/A'
        ) AS adviser_name
    FROM enrollments e
    LEFT JOIN students s ON e.lrn = s.lrn
    LEFT JOIN advisers a ON e.adviser_id = a.adviser_id
    ORDER BY
      e.school_year DESC,
      e.grade_level ASC,
      e.section_name ASC,
      s.lastname, s.firstname
";
$enrollments_result = $conn->query($enrollments_query);
if (!$enrollments_result) {
    error_log("Error fetching enrollments: " . $conn->error);
    $enrollments_result = (object)[
        'num_rows'   => 0,
        'fetch_assoc'=> function(){ return null; }
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Student Enrollment</title>
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
  <link href="css/sb-admin-2.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body id="page-top">
  <?php include 'nav.php'; ?>

  <div id="wrapper">
    <div id="content-wrapper" class="d-flex flex-column">
      <div id="content">
        <div class="container-fluid">

          <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h3 mb-0 text-gray-800">ENROLL STUDENT</h2>
            <button
              class="btn btn-primary"
              data-bs-toggle="modal"
              data-bs-target="#enrollStudentModal"
            >
              Add New Enrollment
            </button>
          </div>

          <!-- Status Alerts -->
          <?php if (isset($_GET['status'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
              <?php
                switch ($_GET['status']) {
                  case 'added':   echo 'Student enrolled successfully!'; break;
                  case 'updated': echo 'Enrollment updated successfully!'; break;
                  case 'deleted': echo 'Enrollment deleted successfully!'; break;
                }
              ?>
              <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
          <?php endif; ?>

          <!-- Enrollment Table -->
          <div class="bg-gray-100 rounded-xl p-5 shadow-md mb-5">
            <h4 class="mb-4 text-gray-700">Enrolled Students</h4>
            <div class="overflow-x-auto">
              <table class="w-full table-auto border border-gray-300 text-sm text-center">
                <thead class="bg-gray-200 font-semibold">
                  <tr>
                    <th class="border px-3 py-2">No.</th>
                    <th class="border px-3 py-2">LRN</th>
                    <th class="border px-3 py-2">Student Name</th>
                    <th class="border px-3 py-2">Section</th>
                    <th class="border px-3 py-2">Grade Level</th>
                    <th class="border px-3 py-2">Adviser</th>
                    <th class="border px-3 py-2">School Year</th>
                    <th class="border px-3 py-2">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                    $counter = 1;
                    if ($enrollments_result->num_rows > 0):
                      while ($row = $enrollments_result->fetch_assoc()):
                        $fn = $row['student_firstname'];
                        $mn = $row['student_middlename'];
                        $ln = $row['student_lastname'];
                        if ($fn === 'N/A' && $ln === 'N/A') {
                          $fullname = 'Student Not Found (LRN: ' . htmlspecialchars($row['lrn']) . ')';
                        } else {
                          $fullname = htmlspecialchars(
                            $ln . ', ' . $fn . ($mn ? ' ' . $mn . '.' : '')
                          );
                        }
                  ?>
                  <tr class="hover:bg-gray-50">
                    <td class="border px-3 py-2"><?= $counter++ ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['lrn']) ?></td>
                    <td class="border px-3 py-2"><?= $fullname ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['section_name']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['grade_level']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['adviser_name']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['school_year']) ?></td>
                    <td class="border px-3 py-2 space-x-2">
                      <button
                        onclick='openEditEnrollmentModal(<?= json_encode([
                          'enrollment_id' => $row['enrollment_id'],
                          'lrn'           => $row['lrn'],
                          'section_name'  => $row['section_name'],
                          'grade_level'   => $row['grade_level'],
                          'school_year'   => $row['school_year'],
                          'adviser_id'    => $row['adviser_id']
                        ]) ?>)'
                        class="text-blue-600 hover:text-blue-800 p-1 rounded"
                      >
                        <i data-lucide="pencil" class="w-5 h-5 inline"></i>
                      </button>
                      <form
                        method="POST"
                        class="inline"
                        onsubmit="return confirm('Remove this enrollment for <?= $fullname ?>?');"
                      >
                        <input type="hidden" name="enrollment_id" value="<?= $row['enrollment_id'] ?>">
                        <button
                          type="submit"
                          name="delete_enrollment"
                          class="text-red-600 hover:text-red-800 p-1 rounded"
                        >
                          <i data-lucide="trash-2" class="w-5 h-5 inline"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                  <?php
                      endwhile;
                    else:
                  ?>
                  <tr>
                    <td colspan="8" class="border px-3 py-2 text-gray-500">
                      No enrollment records found.
                    </td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Add New Enrollment Modal -->
          <div class="modal fade" id="enrollStudentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog"><div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Add New Enrollment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <form method="post" id="enrollmentForm">
                  <div class="mb-3">
                    <label for="lrn" class="form-label">LRN</label>
                    <select id="lrn" name="lrn" class="form-control" required>
                      <option value="">Select Student LRN</option>
                      <?php
                        if ($students_result_for_dropdown->num_rows > 0) {
                          $students_result_for_dropdown->data_seek(0);
                          while ($s = $students_result_for_dropdown->fetch_assoc()):
                      ?>
                        <option value="<?= htmlspecialchars($s['lrn']) ?>">
                          <?= htmlspecialchars($s['lastname'] . ', ' . $s['firstname'] . ' (' . $s['lrn'] . ')') ?>
                        </option>
                      <?php
                          endwhile;
                        }
                      ?>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label for="section_name" class="form-label">Section Name</label>
                    <input
                      type="text"
                      id="section_name"
                      name="section_name"
                      class="form-control"
                      maxlength="50"
                      required
                    >
                  </div>

                  <div class="mb-3">
                    <label for="grade_level" class="form-label">Grade Level</label>
                    <select id="grade_level" name="grade_level" class="form-control" required>
                      <option value="">Select Grade Level</option>
                      <?php foreach ($available_grade_levels as $lvl): ?>
                        <option value="<?= htmlspecialchars($lvl) ?>">
                          <?= htmlspecialchars($lvl) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label for="adviser_id" class="form-label">Adviser</label>
                    <select id="adviser_id" name="adviser_id" class="form-control" required>
                      <option value="">Select Adviser</option>
                      <?php foreach ($advisers as $adv): ?>
                        <option value="<?= htmlspecialchars($adv['adviser_id']) ?>">
                          <?= htmlspecialchars($adv['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label for="school_year_add" class="form-label">School Year</label>
                    <input
                      type="text"
                      id="school_year_add"
                      name="school_year"
                      class="form-control"
                      maxlength="9"
                      required
                      placeholder="e.g., 2024-2025"
                    >
                  </div>

                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                      Close
                    </button>
                    <button type="submit" name="add_enrollment" class="btn btn-primary">
                      Enroll Student
                    </button>
                  </div>
                </form>
              </div>
            </div></div>
          </div>

          <!-- Edit Enrollment Modal -->
          <div class="modal fade" id="editEnrollmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog"><div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title">Edit Enrollment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
              </div>
              <div class="modal-body">
                <form method="post">
                  <input type="hidden" id="edit_enrollment_id" name="edit_enrollment_id">

                  <div class="mb-3">
                    <label for="edit_lrn" class="form-label">LRN</label>
                    <select id="edit_lrn" name="edit_lrn" class="form-control" required>
                      <option value="">Select Student LRN</option>
                      <?php
                        if ($students_result_for_dropdown->num_rows > 0) {
                          $students_result_for_dropdown->data_seek(0);
                          while ($s = $students_result_for_dropdown->fetch_assoc()):
                      ?>
                        <option value="<?= htmlspecialchars($s['lrn']) ?>">
                          <?= htmlspecialchars($s['lastname'] . ', ' . $s['firstname'] . ' (' . $s['lrn'] . ')') ?>
                        </option>
                      <?php
                          endwhile;
                        }
                      ?>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label for="edit_section_name" class="form-label">Section Name</label>
                    <input
                      type="text"
                      id="edit_section_name"
                      name="edit_section_name"
                      class="form-control"
                      maxlength="50"
                      required
                    >
                  </div>

                  <div class="mb-3">
                    <label for="edit_grade_level" class="form-label">Grade Level</label>
                    <select id="edit_grade_level" name="edit_grade_level" class="form-control" required>
                      <option value="">Select Grade Level</option>
                      <?php foreach ($available_grade_levels as $lvl): ?>
                        <option value="<?= htmlspecialchars($lvl) ?>">
                          <?= htmlspecialchars($lvl) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label for="edit_adviser_id" class="form-label">Adviser</label>
                    <select id="edit_adviser_id" name="edit_adviser_id" class="form-control" required>
                      <option value="">Select Adviser</option>
                      <?php foreach ($advisers as $adv): ?>
                        <option value="<?= htmlspecialchars($adv['adviser_id']) ?>">
                          <?= htmlspecialchars($adv['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="mb-3">
                    <label for="edit_school_year" class="form-label">School Year</label>
                    <input
                      type="text"
                      id="edit_school_year"
                      name="edit_school_year"
                      class="form-control"
                      maxlength="9"
                      required
                      placeholder="e.g., 2024-2025"
                    >
                  </div>

                  <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                      Close
                    </button>
                    <button type="submit" name="edit_enrollment" class="btn btn-primary">
                      Save Changes
                    </button>
                  </div>
                </form>
              </div>
            </div></div>
          </div>

        </div>
      </div>

      <footer class="sticky-footer bg-white">
        <div class="container my-auto">
          <div class="text-center my-auto">
            <span>Copyright &copy; School Management System 2025</span>
          </div>
        </div>
      </footer>
    </div>
  </div>

  <a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
  </a>

  <script>
    lucide.createIcons();

    function openEditEnrollmentModal(data) {
      $('#editEnrollmentModal').modal('show');
      $('#edit_enrollment_id').val(data.enrollment_id);
      $('#edit_lrn').val(data.lrn);
      $('#edit_section_name').val(data.section_name);
      $('#edit_grade_level').val(data.grade_level);
      // Set the selected adviser in the edit modal dropdown
      $('#edit_adviser_id').val(data.adviser_id);
      $('#edit_school_year').val(data.school_year);
    }

    // Reset Add form on close
    document
      .getElementById('enrollStudentModal')
      .addEventListener('hidden.bs.modal', function () {
        document.getElementById('enrollmentForm').reset();
      });

    // Auto‐dismiss alerts and clean URL
    $(document).ready(function() {
      setTimeout(function() {
        $(".alert").alert('close');
        if (window.history.replaceState) {
          const url = new URL(window.location.href);
          url.searchParams.delete('status');
          window.history.replaceState({path: url.href}, '', url.href);
        }
      }, 5000);
    });
  </script>
</body>
</html>