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
 * Function to get the current school year based on month.
 * Assumes school year starts in August.
 */
function getCurrentSchoolYear() {
    $currentMonth = (int)date('m');
    $currentYear = (int)date('Y');

    if ($currentMonth >= 8) { // August (month 8) to December
        return $currentYear . '-' . ($currentYear + 1);
    } else { // January to July
        return ($currentYear - 1) . '-' . $currentYear;
    }
}

/**
 * Available grade levels (static)
 */
$available_grade_levels = [
    'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'
];

/**
 * Fetch students for LRN datalist suggestions
 */
$students_result_for_dropdown = $conn->query(
    "SELECT lrn, lastname, firstname, middlename FROM students ORDER BY lastname, firstname"
);
$all_students_for_dropdown = [];
if ($students_result_for_dropdown) {
    while ($row = $students_result_for_dropdown->fetch_assoc()) {
        $fullName = htmlspecialchars($row['lastname'] . ', ' . $row['firstname']);
        if (!empty($row['middlename'])) {
            $fullName .= ' ' . htmlspecialchars(substr($row['middlename'], 0, 1)) . '.';
        }
        $all_students_for_dropdown[] = [
            'lrn' => $row['lrn'],
            'name' => $fullName
        ];
    }
} else {
    error_log("Error fetching students for dropdown: " . $conn->error);
}


/**
 * Fetch all sections with their grade levels and adviser names for JavaScript filtering
 */
$sections_query = "
    SELECT
        s.section_id,
        s.section_name,
        s.grade_level,
        s.adviser_id,
        CONCAT_WS(' ', 
            adv.firstname, 
            CASE WHEN adv.middlename IS NOT NULL AND adv.middlename != '' THEN CONCAT(LEFT(adv.middlename, 1), '.') ELSE NULL END,
            adv.lastname,
            CASE WHEN adv.suffix IS NOT NULL AND adv.suffix != '' THEN adv.suffix ELSE NULL END
        ) AS adviser_fullname -- Improved name combining
    FROM sections s
    LEFT JOIN advisers adv ON s.adviser_id = adv.adviser_id
    ORDER BY s.grade_level ASC, s.section_name ASC";

$sections_result_all = $conn->query($sections_query);
$all_sections_for_js = []; // Store all sections for JS filtering
if ($sections_result_all) {
    while ($row = $sections_result_all->fetch_assoc()) {
        $all_sections_for_js[] = $row;
    }
} else {
    error_log("Warning: Could not load sections for dropdowns. " . $conn->error);
}
// Pass all sections data to JavaScript as a JSON object
$all_sections_json = json_encode($all_sections_for_js);


/**
 * Handle Add Enrollment
 */
if (isset($_POST['add_enrollment'])) {
    // Sanitize inputs
    $lrn          = htmlspecialchars(trim($_POST['lrn']), ENT_QUOTES, 'UTF-8');
    $grade_level  = htmlspecialchars(trim($_POST['grade_level']), ENT_QUOTES, 'UTF-8');
    $section_id  = filter_var($_POST['section_id'], FILTER_VALIDATE_INT); // New: Get section_id
    $school_year  = htmlspecialchars(trim($_POST['school_year']), ENT_QUOTES, 'UTF-8');

    // Basic required‐field check
    if (empty($lrn) || empty($grade_level) || $section_id === false || $section_id === null || empty($school_year)) {
        echo "<script>alert('All required fields must be filled (LRN, Grade Level, Section, School Year).'); window.location.href='enrollment.php';</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // 1) LRN exists? (using 's' for LRN as it's often alphanumeric)
        $stmt = $conn->prepare("SELECT 1 FROM students WHERE lrn = ?");
        $stmt->bind_param("s", $lrn);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception("LRN not found. Please add the student first.");
        }
        $stmt->close();

        // 2) Section exists?
        $stmt = $conn->prepare("SELECT 1 FROM sections WHERE section_id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception("Selected Section not found.");
        }
        $stmt->close();

        // 3) Prevent duplicate enrollment for this student in this school year (ANY section)
        $stmt = $conn->prepare("
            SELECT enrollment_id
            FROM enrollments
            WHERE lrn = ? AND school_year = ?
        ");
        $stmt->bind_param("ss", $lrn, $school_year); // s for LRN, s for school_year
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            throw new Exception("This student is already enrolled in a section for the specified school year.");
        }
        $stmt->close();

        // 4) Insert into enrollments (lrn, grade_level, section_id, school_year)
        $stmt = $conn->prepare("
            INSERT INTO enrollments
            (lrn, grade_level, section_id, school_year)
            VALUES (?, ?, ?, ?)
        ");
        // s for LRN, s for grade_level, i for section_id, s for school_year
        $stmt->bind_param("ssis", $lrn, $grade_level, $section_id, $school_year);
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
    $lrn           = htmlspecialchars(trim($_POST['edit_lrn_input']), ENT_QUOTES, 'UTF-8'); // Changed to edit_lrn_input
    $grade_level   = htmlspecialchars(trim($_POST['edit_grade_level']), ENT_QUOTES, 'UTF-8');
    $section_id    = filter_var($_POST['edit_section_id'], FILTER_VALIDATE_INT); // New: Get section_id
    $school_year   = htmlspecialchars(trim($_POST['edit_school_year']), ENT_QUOTES, 'UTF-8');


    // Validate inputs
    if (
        $enrollment_id === false || $enrollment_id === null ||
        empty($lrn) || empty($grade_level) ||
        $section_id === false || $section_id === null || empty($school_year)
    ) {
        echo "<script>alert('Invalid ID or missing fields (LRN, Grade Level, Section, School Year).'); window.location.href='enrollment.php';</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // 1) LRN exists? (using 's' for LRN)
        $stmt = $conn->prepare("SELECT 1 FROM students WHERE lrn = ?");
        $stmt->bind_param("s", $lrn);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception("LRN not found.");
        }
        $stmt->close();

        // 2) Section exists?
        $stmt = $conn->prepare("SELECT 1 FROM sections WHERE section_id = ?");
        $stmt->bind_param("i", $section_id);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows === 0) {
            throw new Exception("Selected Section not found.");
        }
        $stmt->close();

        // 3) Prevent duplicate (lrn, school_year) for other enrollments (ANY section)
        $stmt = $conn->prepare("
            SELECT enrollment_id
            FROM enrollments
            WHERE lrn = ? AND school_year = ? AND enrollment_id != ?
        ");
        $stmt->bind_param("ssi", $lrn, $school_year, $enrollment_id); // s for LRN, s for school_year, i for enrollment_id
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            throw new Exception("Another enrollment for this student exists for this school year.");
        }
        $stmt->close();

        // 4) Update enrollments (lrn, grade_level, section_id, school_year)
        $stmt = $conn->prepare("
            UPDATE enrollments
            SET lrn = ?, grade_level = ?, section_id = ?, school_year = ?
            WHERE enrollment_id = ?
        ");
        // s for LRN, s for grade_level, i for section_id, s for school_year, i for enrollment_id
        $stmt->bind_param("ssisi", $lrn, $grade_level, $section_id, $school_year, $enrollment_id);
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
 * Fetch enrollments for display in the table (Updated for new schema)
 */
$enrollments_query = "
    SELECT
        e.enrollment_id, e.lrn, e.grade_level, e.school_year, e.section_id,
        COALESCE(st.firstname, 'N/A') AS student_firstname,
        COALESCE(st.middlename, '')   AS student_middlename,
        COALESCE(st.lastname, 'N/A')  AS student_lastname,
        COALESCE(sec.section_name, 'N/A') AS section_name,
        COALESCE(
            CONCAT_WS(' ',
                adv.firstname, 
                CASE WHEN adv.middlename IS NOT NULL AND adv.middlename != '' THEN CONCAT(LEFT(adv.middlename, 1), '.') ELSE NULL END,
                adv.lastname,
                CASE WHEN adv.suffix IS NOT NULL AND adv.suffix != '' THEN adv.suffix ELSE NULL END
            ),
            'N/A'
        ) AS adviser_name
    FROM enrollments e
    LEFT JOIN students st ON e.lrn = st.lrn
    LEFT JOIN sections sec ON e.section_id = sec.section_id -- Join to sections table
    LEFT JOIN advisers adv ON sec.adviser_id = adv.adviser_id -- Join sections to advisers
    ORDER BY
      e.school_year DESC,
      e.grade_level ASC,
      sec.section_name ASC, -- Use section_name from the joined table
      st.lastname, st.firstname
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
            <h2 class="h3 mb-0 text-gray-800">ENROLLMENT DASHBOARD</h2>
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
                        // Fix student name display:
                        $student_fullname_display = htmlspecialchars($ln);
                        if (!empty($fn) && $fn !== 'N/A') {
                            $student_fullname_display .= ', ' . htmlspecialchars($fn);
                        }
                        if (!empty($mn) && $mn !== 'N/A') {
                            $student_fullname_display .= ' ' . htmlspecialchars(substr($mn, 0, 1)) . '.';
                        }
                  ?>
                  <tr class="hover:bg-gray-50">
                    <td class="border px-3 py-2"><?= $counter++ ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['lrn']) ?></td>
                    <td class="border px-3 py-2"><?= $student_fullname_display ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['section_name']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['grade_level']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['adviser_name']) ?></td>
                    <td class="border px-3 py-2"><?= htmlspecialchars($row['school_year']) ?></td>
                    <td class="border px-3 py-2 space-x-2">
                      <button
                        onclick='openEditEnrollmentModal(<?= json_encode([
                          'enrollment_id' => $row['enrollment_id'],
                          'lrn'           => $row['lrn'],
                          'grade_level'   => $row['grade_level'],
                          'section_id'    => $row['section_id'], // Pass section_id
                          'school_year'   => $row['school_year']
                        ]) ?>)'
                        class="text-blue-600 hover:text-blue-800 p-1 rounded"
                      >
                        <i data-lucide="pencil" class="w-5 h-5 inline"></i>
                      </button>
                      <form
                        method="POST"
                        class="inline"
                        onsubmit="return confirm('Remove this enrollment for <?= $student_fullname_display ?>?');"
                      >
                        <input type="hidden" name="enrollment_id" value="<?= $row['enrollment_id'] ?>">
                        <button
                          type="submit"
                          name="delete_enrollment"
                          class="text-red-600 hover:text-red-800 p-1 rounded"
                        >
                          <i data-lucide="trash" class="w-5 h-5 inline"></i>
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
                    <label for="lrn_input" class="form-label">Student LRN</label>
                    <input type="text" id="lrn_input" name="lrn" class="form-control" list="student_lrns" placeholder="Type LRN or select from list" required>
                    <datalist id="student_lrns">
                      <?php foreach ($all_students_for_dropdown as $student): ?>
                        <option value="<?= htmlspecialchars($student['lrn']) ?>"><?= htmlspecialchars($student['name']) ?></option>
                      <?php endforeach; ?>
                    </datalist>
                  </div>

                  <div class="mb-3">
                    <label for="add_grade_level" class="form-label">Grade Level</label>
                    <select id="add_grade_level" name="grade_level" class="form-control" required onchange="populateSections('add_grade_level', 'add_section_id')">
                      <option value="">Select Grade Level</option>
                      <?php foreach ($available_grade_levels as $lvl): ?>
                        <option value="<?= htmlspecialchars($lvl) ?>">
                          <?= htmlspecialchars($lvl) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  
                  <div class="mb-3">
                    <label for="add_section_id" class="form-label">Section</label>
                    <select id="add_section_id" name="section_id" class="form-control" required>
                      <option value="">Select Grade Level first</option>
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
                      value="<?= getCurrentSchoolYear() ?>"
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
                    <label for="edit_lrn_input" class="form-label">Student LRN</label>
                    <input type="text" id="edit_lrn_input" name="edit_lrn_input" class="form-control" list="student_lrns" placeholder="Type LRN or select from list" required>
                    <!-- Re-use the same datalist -> student_lrns -->
                  </div>

                  <div class="mb-3">
                    <label for="edit_grade_level" class="form-label">Grade Level</label>
                    <select id="edit_grade_level" name="edit_grade_level" class="form-control" required onchange="populateSections('edit_grade_level', 'edit_section_id')">
                      <?php foreach ($available_grade_levels as $lvl): ?>
                        <option value="<?= htmlspecialchars($lvl) ?>">
                          <?= htmlspecialchars($lvl) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  
                  <div class="mb-3">
                    <label for="edit_section_id" class="form-label">Section</label>
                    <select id="edit_section_id" name="edit_section_id" class="form-control" required>
                      <option value="">Select Grade Level first</option>
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

      <?php 
        include 'footer.php';
      ?>
    </div>
  </div>

  <a class="scroll-to-top rounded" href="#page-top">
    <i class="fas fa-angle-up"></i>
  </a>

  <script>
    lucide.createIcons();

    // Pass all sections data to JavaScript
    const allSectionsData = <?= $all_sections_json ?>;

    /**
     * Populates the section dropdown based on the selected grade level.
     * @param {string} gradeLevelSelectId ID of the grade level select element.
     * @param {string} sectionSelectId ID of the section select element.
     * @param {number|null} selectedSectionId Optional: The section_id to pre-select.
     */
    function populateSections(gradeLevelSelectId, sectionSelectId, selectedSectionId = null) {
        const gradeLevel = document.getElementById(gradeLevelSelectId).value;
        const sectionSelect = document.getElementById(sectionSelectId);
        
        // Clear existing options
        sectionSelect.innerHTML = '<option value="">Select Section</option>';

        if (gradeLevel) {
            const filteredSections = allSectionsData.filter(section => section.grade_level === gradeLevel); // Strict comparison
            
            // Sort sections by name for better UX
            filteredSections.sort((a, b) => a.section_name.localeCompare(b.section_name));

            filteredSections.forEach(section => {
                const option = document.createElement('option');
                option.value = section.section_id;
                
                // Display section name and adviser name (if available)
                const adviserDisplay = section.adviser_fullname && section.adviser_fullname !== 'N/A' 
                                       ? ` (Adviser: ${section.adviser_fullname})` 
                                       : '';
                option.textContent = `${section.section_name}${adviserDisplay}`;
                
                if (selectedSectionId !== null && section.section_id == selectedSectionId) {
                    option.selected = true;
                }
                sectionSelect.appendChild(option);
            });
        }
    }

    /**
     * Opens the Edit Enrollment Modal and populates its fields.
     */
    function openEditEnrollmentModal(data) {
        // Set basic fields
        document.getElementById('edit_enrollment_id').value = data.enrollment_id;
        document.getElementById('edit_lrn_input').value = data.lrn; // Corrected ID for input textbox
        document.getElementById('edit_school_year').value = data.school_year;
        
        // Set grade level and then populate sections for it, pre-selecting the correct section
        document.getElementById('edit_grade_level').value = data.grade_level;
        populateSections('edit_grade_level', 'edit_section_id', data.section_id);

        // Show the modal
        var editModal = new bootstrap.Modal(document.getElementById('editEnrollmentModal'));
        editModal.show();
    }

    // Reset Add form on close
    document
      .getElementById('enrollStudentModal')
      .addEventListener('hidden.bs.modal', function () {
        document.getElementById('enrollmentForm').reset();
        // Reset section dropdown when modal is closed
        const sectionSelect = document.getElementById('add_section_id');
        sectionSelect.innerHTML = '<option value="">Select Grade Level first</option>';
      });

    // Auto-dismiss alerts and clean URL
    $(document).ready(function() {
      // Set initial school year for add modal
      document.getElementById('school_year_add').value = '<?= getCurrentSchoolYear() ?>';

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