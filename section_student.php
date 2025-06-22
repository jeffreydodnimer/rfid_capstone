<?php
session_start(); // Must be at the VERY TOP of every PHP page that uses sessions

// Basic authentication check: if the user is not logged in, redirect them to the login page.
// This assumes 'admin_login.php' handles the login process and sets $_SESSION['email'].
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php'); // Redirect to your login page
    exit();
}

include 'conn.php'; // Include your database connection file

// Ensure the database connection is valid before proceeding with any operations
if (!$conn) {
    die("Database connection failed during script initialization.");
}

/**
 * Handle Add Enrollment
 * This block processes the form submission for adding a new enrollment record.
 */
if (isset($_POST['add_enrollment'])) {
    // Sanitize and validate input data
    $lrn = htmlspecialchars($_POST['lrn'], ENT_QUOTES, 'UTF-8');
    $grade_level = htmlspecialchars($_POST['add_grade_level'], ENT_QUOTES, 'UTF-8'); // Now grade_level is submitted
    $school_year = htmlspecialchars($_POST['school_year'], ENT_QUOTES, 'UTF-8');

    // Basic validation for required fields
    if (empty($lrn) || empty($grade_level) || empty($school_year)) {
        echo "<script>alert('Error: All fields (LRN, Grade Level, School Year) are required.'); window.location.href='enroll_student.php';</script>";
        exit();
    }

    $conn->begin_transaction(); // Start a transaction for atomicity (either all succeed or all fail)

    try {
        // Step 1: Check if the LRN exists in the students table
        $lrn_check_stmt = $conn->prepare("SELECT lrn FROM students WHERE lrn = ?");
        if ($lrn_check_stmt === false) {
            throw new Exception("Error preparing LRN validation statement: " . $conn->error);
        }

        $lrn_check_stmt->bind_param("s", $lrn);
        $lrn_check_stmt->execute();
        $lrn_result = $lrn_check_stmt->get_result();

        if ($lrn_result->num_rows === 0) {
            $lrn_check_stmt->close();
            $conn->rollback();
            echo "<script>alert('Error: LRN didn\\'t register. Please register the student first.'); window.location.href='enroll_student.php';</script>";
            exit();
        }
        $lrn_check_stmt->close();

        // Step 2: Automatically determine section_id based on grade_level
        // We will assign the first section found for the selected grade level.
        // You might want more sophisticated logic here (e.g., assign to least full section).
        $get_section_stmt = $conn->prepare("SELECT section_id FROM sections WHERE grade_level = ? ORDER BY section_name ASC LIMIT 1");
        if ($get_section_stmt === false) {
            throw new Exception("Error preparing section retrieval statement: " . $conn->error);
        }
        $get_section_stmt->bind_param("s", $grade_level);
        $get_section_stmt->execute();
        $section_result = $get_section_stmt->get_result();
        $section_data = $section_result->fetch_assoc();
        $get_section_stmt->close();

        if (!$section_data) {
            $conn->rollback();
            echo "<script>alert('Error: No sections found for the selected Grade Level (" . htmlspecialchars($grade_level) . ").'); window.location.href='enroll_student.php';</script>";
            exit();
        }
        $section_id = $section_data['section_id'];

        // Step 3: Check for duplicate enrollment (same LRN, section_id, school_year)
        // Note: With automatic section assignment, a student might be assigned to the same section if they try to re-enroll in the same grade/year.
        $duplicate_check_stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE lrn = ? AND section_id = ? AND school_year = ?");
        if ($duplicate_check_stmt === false) {
            throw new Exception("Error preparing duplicate enrollment check: " . $conn->error);
        }

        $duplicate_check_stmt->bind_param("sis", $lrn, $section_id, $school_year);
        $duplicate_check_stmt->execute();
        $duplicate_result = $duplicate_check_stmt->get_result();

        if ($duplicate_result->num_rows > 0) {
            $duplicate_check_stmt->close();
            $conn->rollback();
            echo "<script>alert('Error: Student is already enrolled in a section for the specified Grade Level and School Year.'); window.location.href='enroll_student.php';</script>";
            exit();
        }
        $duplicate_check_stmt->close();


        // Step 4: Insert the new enrollment record
        $stmt = $conn->prepare("INSERT INTO enrollments (lrn, section_id, school_year) VALUES (?, ?, ?)");

        if ($stmt === false) {
            throw new Exception("Error preparing add statement: " . $conn->error);
        }

        // Bind parameters (sis: string, integer, string)
        $stmt->bind_param("sis", $lrn, $section_id, $school_year);

        if (!$stmt->execute()) {
            throw new Exception("Error adding enrollment: " . $stmt->error);
        }

        $stmt->close(); // Close the prepared statement
        $conn->commit(); // Commit the transaction if all operations were successful

        // Redirect with a success status message
        header("Location: enroll_student.php?status=added");
        exit();
    } catch (Exception $e) {
        $conn->rollback(); // Rollback the transaction on any error
        // Display an alert and redirect back to the page with the error message
        echo "<script>alert('Error: " . $e->getMessage() . "'); window.location.href='enroll_student.php';</script>";
        exit();
    }
}

/**
 * Handle Delete Enrollment
 * This block processes the form submission for deleting an enrollment record.
 */
if (isset($_POST['delete_enrollment'])) {
    // Get the enrollment_id from the form and validate it as an integer
    $enrollment_id = filter_var($_POST['enrollment_id'], FILTER_VALIDATE_INT);

    if ($enrollment_id === false || $enrollment_id === null) {
        echo "<script>alert('Error: Invalid Enrollment ID for deletion.'); window.location.href='enroll_student.php';</script>";
        exit();
    }

    $conn->begin_transaction(); // Start transaction

    try {
        // Prepare the SQL DELETE statement
        $stmt = $conn->prepare("DELETE FROM enrollments WHERE enrollment_id = ?");

        if ($stmt === false) {
            throw new Exception("Error preparing delete statement: " . $conn->error);
        }

        // Bind the enrollment_id parameter (i: integer)
        $stmt->bind_param("i", $enrollment_id);

        // Execute the statement
        if (!$stmt->execute()) {
            throw new Exception("Error deleting enrollment: " . $stmt->error);
        }

        $stmt->close();
        $conn->commit(); // Commit transaction on success

        header("Location: enroll_student.php?status=deleted");
        exit();
    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        echo "<script>alert('Error: " . $e->getMessage() . "'); window.location.href='enroll_student.php';</script>";
        exit();
    }
}

/**
 * Handle Edit Enrollment
 * This block processes the form submission for updating an existing enrollment record.
 */
if (isset($_POST['edit_enrollment'])) {
    // Get original ID and new values, sanitizing inputs
    $original_enrollment_id = filter_var($_POST['edit_enrollment_id'], FILTER_VALIDATE_INT);
    $lrn = htmlspecialchars($_POST['edit_lrn'], ENT_QUOTES, 'UTF-8');
    $section_id = filter_var($_POST['edit_section_id'], FILTER_VALIDATE_INT); // section_id is still what's stored
    $school_year = htmlspecialchars($_POST['edit_school_year'], ENT_QUOTES, 'UTF-8');

    // Validate inputs
    if ($original_enrollment_id === false || $original_enrollment_id === null ||
        empty($lrn) || empty($school_year) ||
        $section_id === false || $section_id === null) {
        echo "<script>alert('Error: Invalid ID or missing required fields for editing.'); window.location.href='enroll_student.php';</script>";
        exit();
    }

    $conn->begin_transaction(); // Start transaction

    try {
        // First, check if the LRN exists in the students table
        $lrn_check_stmt = $conn->prepare("SELECT lrn FROM students WHERE lrn = ?");
        if ($lrn_check_stmt === false) {
            throw new Exception("Error preparing LRN validation statement: " . $conn->error);
        }

        $lrn_check_stmt->bind_param("s", $lrn);
        $lrn_check_stmt->execute();
        $lrn_result = $lrn_check_stmt->get_result();

        if ($lrn_result->num_rows === 0) {
            $lrn_check_stmt->close();
            $conn->rollback();
            echo "<script>alert('Error: LRN didn\\'t register. Please register the student first.'); window.location.href='enroll_student.php';</script>";
            exit();
        }
        $lrn_check_stmt->close();

        // Check if another enrollment record already exists with the same LRN, section, and school year (excluding current record)
        $duplicate_check_stmt = $conn->prepare("SELECT enrollment_id FROM enrollments WHERE lrn = ? AND section_id = ? AND school_year = ? AND enrollment_id != ?");
        if ($duplicate_check_stmt === false) {
            throw new Exception("Error preparing duplicate enrollment check: " . $conn->error);
        }

        $duplicate_check_stmt->bind_param("sisi", $lrn, $section_id, $school_year, $original_enrollment_id);
        $duplicate_check_stmt->execute();
        $duplicate_result = $duplicate_check_stmt->get_result();

        if ($duplicate_result->num_rows > 0) {
            $duplicate_check_stmt->close();
            $conn->rollback();
            echo "<script>alert('Error: Student is already enrolled in this section for the specified school year.'); window.location.href='enroll_student.php';</script>";
            exit();
        }
        $duplicate_check_stmt->close();

        // Prepare the SQL UPDATE statement
        $stmt = $conn->prepare("UPDATE enrollments SET lrn=?, section_id=?, school_year=? WHERE enrollment_id=?");

        if ($stmt === false) {
            throw new Exception("Error preparing update statement: " . $conn->error);
        }

        // Bind parameters (sisi: string, integer, string, integer)
        $stmt->bind_param("sisi", $lrn, $section_id, $school_year, $original_enrollment_id);

        if (!$stmt->execute()) {
            throw new Exception("Error updating enrollment: " . $stmt->error);
        }

        $stmt->close();
        $conn->commit(); // Commit transaction on success

        header("Location: enroll_student.php?status=updated");
        exit();
    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        echo "<script>alert('Error: " . $e->getMessage() . "'); window.location.href='enroll_student.php';</script>";
        exit();
    }
}

/**
 * Fetch Data for Display and Dropdowns
 */

// Hardcode available grade levels for the dropdown
$available_grade_levels = [
    'Grade 7',
    'Grade 8',
    'Grade 9',
    'Grade 10',
    'Grade 11',
    'Grade 12'
];

// Fetch all sections with their grade levels for JavaScript filtering (needed for EDIT modal)
$sections_query = "SELECT section_id, section_name, grade_level FROM sections ORDER BY grade_level ASC, section_name ASC";
$sections_result_all = $conn->query($sections_query);
$all_sections_for_js = []; // Store all sections for JS filtering
if ($sections_result_all) {
    while ($row = $sections_result_all->fetch_assoc()) {
        $all_sections_for_js[] = $row;
    }
} else {
    echo "<script>alert('Warning: Could not load sections for dropdowns. " . $conn->error . "');</script>";
}

// Pass all sections data to JavaScript as a JSON object
$all_sections_json = json_encode($all_sections_for_js);

// Fetch Enrollments for Display in the table
$enrollments_query = "
    SELECT
        e.enrollment_id, e.lrn, e.section_id, e.school_year,
        s.firstname, s.middlename, s.lastname,
        sec.section_name, sec.grade_level
    FROM enrollments e
    JOIN students s ON e.lrn = s.lrn
    JOIN sections sec ON e.section_id = sec.section_id
    ORDER BY sec.grade_level, s.lastname, s.firstname";

$enrollments_result = $conn->query($enrollments_query);
if (!$enrollments_result) {
    die("Error fetching enrollments for display: " . $conn->error); // Display error if fetching fails
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Student Enrollment</title>

    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

</head>

<body id="page-top">

    <?php include 'nav.php'; // Include your navigation bar ?>

    <div id="wrapper">
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h3 mb-0 text-gray-800">ENROLL STUDENT</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#enrollStudentModal"
                            style="box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);">Add New Enrollment</button>
                    </div>

                    <div class="bg-gray-100 rounded-xl p-5 shadow-md">
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
                                        <th class="border px-3 py-2">School Year</th>
                                        <th class="border px-3 py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $counter = 1;
                                    if(isset($enrollments_result) && $enrollments_result->num_rows > 0):
                                    while ($row = $enrollments_result->fetch_assoc()):
                                        $firstname = isset($row['firstname']) ? $row['firstname'] : '';
                                        $middlename = isset($row['middlename']) ? $row['middlename'] : '';
                                        $lastname = isset($row['lastname']) ? $row['lastname'] : '';
                                        $fullname = htmlspecialchars($lastname . ', ' . $firstname . ' ' . $middlename);
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="border px-3 py-2"><?= $counter++ ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($row['lrn']) ?></td>
                                        <td class="border px-3 py-2"><?= $fullname ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($row['section_name']) ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($row['grade_level']) ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($row['school_year']) ?></td>
                                        <td class="border px-3 py-2 space-x-2">
                                            <button onclick='openEditEnrollmentModal(<?= json_encode(['enrollment_id' => $row['enrollment_id'], 'lrn' => $row['lrn'], 'section_id' => $row['section_id'], 'school_year' => $row['school_year'], 'grade_level' => $row['grade_level']]) ?>)'
                                                class="text-blue-600 hover:text-blue-800 p-1 rounded">
                                                <i data-lucide="pencil" class="w-5 h-5 inline"></i>
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this enrollment for <?= $fullname ?>?');">
                                                <input type="hidden" name="enrollment_id" value="<?= htmlspecialchars($row['enrollment_id']) ?>" />
                                                <button type="submit" name="delete_enrollment" class="text-red-600 hover:text-red-800 p-1 rounded">
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
                                    <td colspan="7" class="border px-3 py-2 text-center text-gray-500">No enrollment records found.</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Add New Enrollment Modal -->
                    <div class="modal fade" id="enrollStudentModal" tabindex="-1" aria-labelledby="enrollStudentModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="enrollStudentModalLabel">Add New Enrollment</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="post">
                                        <div class="mb-3">
                                            <label for="lrn" class="form-label">LRN</label>
                                            <input type="text" class="form-control" id="lrn" name="lrn" required placeholder="Enter LRN">
                                        </div>

                                        <div class="mb-3">
                                            <label for="add_grade_level" class="form-label">Grade Level</label>
                                            <!-- IMPORTANT: Added name="add_grade_level" so its value is submitted -->
                                            <select class="form-control" id="add_grade_level" name="add_grade_level" required>
                                                <option value="">Select Grade Level</option>
                                                <?php foreach ($available_grade_levels as $grade_level): ?>
                                                    <option value="<?= htmlspecialchars($grade_level) ?>">
                                                        <?= htmlspecialchars($grade_level) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <!-- REMOVED THE SECTION INPUT FIELD -->
                                        <!--
                                        <div class="mb-3">
                                            <label for="add_section_id" class="form-label">Section</label>
                                            <select class="form-control" id="add_section_id" name="section_id" required>
                                                <option value="">Select a Section</option>
                                            </select>
                                        </div>
                                        -->

                                        <div class="mb-3">
                                            <label for="school_year" class="form-label">School Year</label>
                                            <input type="text" class="form-control" id="school_year" name="school_year" required placeholder="e.g., 2024-2025">
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <button type="submit" name="add_enrollment" class="btn btn-primary">Enroll Student</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Enrollment Modal (remains unchanged as per request) -->
                    <div class="modal fade" id="editEnrollmentModal" tabindex="-1" aria-labelledby="editEnrollmentModalLabel" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="editEnrollmentModalLabel">Edit Enrollment</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <form method="post">
                                        <input type="hidden" id="edit_enrollment_id" name="edit_enrollment_id">
                                        <div class="mb-3">
                                            <label for="edit_lrn" class="form-label">LRN</label>
                                            <input type="text" class="form-control" id="edit_lrn" name="edit_lrn" required placeholder="Enter LRN">
                                        </div>

                                        <div class="mb-3">
                                            <label for="edit_grade_level" class="form-label">Grade Level</label>
                                            <select class="form-control" id="edit_grade_level" required>
                                                <option value="">Select Grade Level</option>
                                                <?php foreach ($available_grade_levels as $grade_level): ?>
                                                    <option value="<?= htmlspecialchars($grade_level) ?>">
                                                        <?= htmlspecialchars($grade_level) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="edit_section_id" class="form-label">Section</label>
                                            <!-- The actual section_id input, populated dynamically -->
                                            <select class="form-control" id="edit_section_id" name="edit_section_id" required>
                                                <option value="">Select a Section</option>
                                                <!-- Options will be populated by JavaScript -->
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="edit_school_year" class="form-label">School Year</label>
                                            <input type="text" class="form-control" id="edit_school_year" name="edit_school_year" required placeholder="e.g., 2024-2025">
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <button type="submit" name="edit_enrollment" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /.container-fluid -->
            </div><!-- End of Main Content -->

            <!-- Footer -->
            <br>
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; Your Website 06/2025</span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->

        </div><!-- End of Content Wrapper -->
    </div><!-- End of Page Wrapper -->

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();

        // Pass PHP sections data to JavaScript
        const allSections = <?= $all_sections_json ?>;

        // Function to populate sections dropdown based on selected grade level
        // This function is now primarily used for the EDIT modal
        function populateSections(gradeLevelSelectId, sectionSelectId, selectedSectionId = null) {
            const gradeLevel = document.getElementById(gradeLevelSelectId).value;
            const sectionSelect = document.getElementById(sectionSelectId);

            // Clear existing options
            sectionSelect.innerHTML = '<option value="">Select a Section</option>';

            if (gradeLevel) {
                const filteredSections = allSections.filter(section => section.grade_level == gradeLevel);
                filteredSections.forEach(section => {
                    const option = document.createElement('option');
                    option.value = section.section_id;
                    option.textContent = `${section.section_name} (ID: ${section.section_id})`; // Display section name and ID
                    if (selectedSectionId && section.section_id == selectedSectionId) {
                        option.selected = true;
                    }
                    sectionSelect.appendChild(option);
                });
            }
        }

        // Removed the event listener for the Add Enrollment modal's grade level dropdown
        // The grade level is now submitted directly with the form.
        document.addEventListener('DOMContentLoaded', function() {
            // No specific event listeners are needed here for the removed section dropdown logic on the add modal.
            // The add_grade_level select now just needs a `name` attribute to be submitted.
        });


        // Function to open edit enrollment modal (unchanged)
        function openEditEnrollmentModal(data) {
            document.getElementById('edit_enrollment_id').value = data.enrollment_id;
            document.getElementById('edit_lrn').value = data.lrn;
            document.getElementById('edit_school_year').value = data.school_year;

            // Set the grade level in the edit modal first
            document.getElementById('edit_grade_level').value = data.grade_level;

            // Then populate the sections dropdown based on the set grade level
            // and select the correct section_id
            populateSections('edit_grade_level', 'edit_section_id', data.section_id);

            var editModal = new bootstrap.Modal(document.getElementById('editEnrollmentModal'));
            editModal.show();
        }
    </script>

</body>
</html>