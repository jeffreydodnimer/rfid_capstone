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
    $grade_level = htmlspecialchars($_POST['grade_level'], ENT_QUOTES, 'UTF-8');
    // Using filter_var to specifically validate section_id as an integer
    $section_id = filter_var($_POST['section_id'], FILTER_VALIDATE_INT);
    $school_year = htmlspecialchars($_POST['school_year'], ENT_QUOTES, 'UTF-8');

    // Basic validation for required fields and integer type
    if (empty($lrn) || empty($grade_level) || empty($school_year) || $section_id === false || $section_id === null) {
        echo "<script>alert('Error: All fields are required and Section ID must be a number.'); window.location.href='enrollments_list.php';</script>";
        exit();
    }

    $conn->begin_transaction(); // Start a transaction for atomicity (either all succeed or all fail)

    try {
        // Prepare the SQL INSERT statement with placeholders for security
        $stmt = $conn->prepare("INSERT INTO enrollments (lrn, grade_level, section_id, school_year) VALUES (?, ?, ?, ?)");

        // Check if the prepare statement failed
        if ($stmt === false) {
            throw new Exception("Error preparing add statement: " . $conn->error);
        }

        // Bind parameters to the placeholders (ssis: string, string, integer, string)
        $stmt->bind_param("ssis", $lrn, $grade_level, $section_id, $school_year);

        // Execute the prepared statement
        if (!$stmt->execute()) {
            // If execution fails, throw an exception with the error message
            throw new Exception("Error adding enrollment: " . $stmt->error);
        }

        $stmt->close(); // Close the prepared statement
        $conn->commit(); // Commit the transaction if all operations were successful

        // Redirect with a success status message
        header("Location: enrollments_list.php?status=added");
        exit();
    } catch (Exception $e) {
        $conn->rollback(); // Rollback the transaction on any error
        // Display an alert and redirect back to the page with the error message
        echo "<script>alert('Error: " . $e->getMessage() . "'); window.location.href='enrollments_list.php';</script>";
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
        echo "<script>alert('Error: Invalid Enrollment ID for deletion.'); window.location.href='enrollments_list.php';</script>";
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

        header("Location: enrollments_list.php?status=deleted");
        exit();
    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        echo "<script>alert('Error: " . $e->getMessage() . "'); window.location.href='enrollments_list.php';</script>";
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
    $grade_level = htmlspecialchars($_POST['edit_grade_level'], ENT_QUOTES, 'UTF-8');
    $section_id = filter_var($_POST['edit_section_id'], FILTER_VALIDATE_INT);
    $school_year = htmlspecialchars($_POST['edit_school_year'], ENT_QUOTES, 'UTF-8');

    // Validate inputs
    if ($original_enrollment_id === false || $original_enrollment_id === null ||
        empty($lrn) || empty($grade_level) || empty($school_year) ||
        $section_id === false || $section_id === null) {
        echo "<script>alert('Error: Invalid ID or missing required fields for editing.'); window.location.href='enrollments_list.php';</script>";
        exit();
    }

    $conn->begin_transaction(); // Start transaction

    try {
        // Prepare the SQL UPDATE statement
        $stmt = $conn->prepare("UPDATE enrollments SET lrn=?, grade_level=?, section_id=?, school_year=? WHERE enrollment_id=?");

        if ($stmt === false) {
            throw new Exception("Error preparing update statement: " . $conn->error);
        }

        // Bind parameters (ssisi: string, string, integer, string, integer)
        $stmt->bind_param("ssisi", $lrn, $grade_level, $section_id, $school_year, $original_enrollment_id);

        if (!$stmt->execute()) {
            throw new Exception("Error updating enrollment: " . $stmt->error);
        }

        $stmt->close();
        $conn->commit(); // Commit transaction on success

        header("Location: enrollments_list.php?status=updated");
        exit();
    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        echo "<script>alert('Error: " . $e->getMessage() . "'); window.location.href='enrollments_list.php';</script>";
        exit();
    }
}

/**
 * Fetch Enrollments for Display
 * This part runs every time the page loads (after processing any POST requests)
 * to retrieve the latest list of enrollments from the database.
 */
$enrollments_result = $conn->query("SELECT * FROM enrollments ORDER BY school_year DESC, lrn ASC");
if (!$enrollments_result) {
    die("Error fetching enrollments: " . $conn->error); // Display error if fetching fails
}

// Close the database connection (optional, PHP handles this at script end)
// $conn->close();
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

    <?php include 'nav.php'; ?>

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
                                        <th class="border px-3 py-2">Grade Level</th>
                                        <th class="border px-3 py-2">Section</th>
                                        <th class="border px-3 py-2">School Year</th>
                                        <th class="border px-3 py-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Assume you have a query like this:
                                    // $enrollment_query = "SELECT e.*, s.firstname, s.middlename, s.lastname, sec.section_name 
                                    //                      FROM enrollments e 
                                    //                      JOIN students s ON e.lrn = s.lrn 
                                    //                      JOIN sections sec ON e.section_id = sec.section_id
                                    //                      ORDER BY s.lastname, s.firstname";
                                    // $enrollment_result = $conn->query($enrollment_query);
                                        
                                    $counter = 1;
                                    if(isset($enrollment_result) && $enrollment_result->num_rows > 0):
                                    while ($row = $enrollment_result->fetch_assoc()): 
                                        $fullname = $row['lastname'] . ', ' . $row['firstname'] . ' ' . $row['middlename'];
                                    ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="border px-3 py-2"><?= $counter++ ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($row['lrn']) ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($fullname) ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($row['grade_level']) ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($row['section_name']) ?></td>
                                        <td class="border px-3 py-2"><?= htmlspecialchars($row['school_year']) ?></td>
                                        <td class="border px-3 py-2 space-x-2">
                                            <button onclick='openEditEnrollmentModal(<?= json_encode(['enrollment_id' => $row['enrollment_id'], 'lrn' => $row['lrn'], 'grade_level' => $row['grade_level'], 'section_id' => $row['section_id'], 'school_year' => $row['school_year']]) ?>)'
                                                class="text-blue-600 hover:text-blue-800 p-1 rounded">
                                                <i data-lucide="pencil" class="w-5 h-5 inline"></i>
                                            </button>
                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this enrollment for <?= htmlspecialchars($fullname) ?>?');">
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
                    
                    <!-- First, remove the form section from the main content and modify the modal structure -->

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
                                            <input type="text" class="form-control" id="lrn" name="lrn" required placeholder="Enter LRN">
                                        </div>

                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="grade_level" name="grade_level" required placeholder="Enter Grade Level">
                                        </div>

                                        <div class="mb-3">
                                            <input type="number" class="form-control" id="section_id" name="section_id" required placeholder="Enter Section ID">
                                        </div>

                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="school_year" name="school_year" required placeholder="Enter School Year">
                                        </div>

                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <button type="submit" name="enroll_student" class="btn btn-primary">Enroll Student</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

    <!-- Footer -->
    <br>
    <footer class="sticky-footer bg-white">
        <div class="container my-auto">
            <div class="copyright text-center my-auto">
                <span>Copyright &copy; Your Website 06/2025</span>
            </div>
        </div>
    </footer>

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <script>
        // Initialize Lucide icons
        lucide.createIcons();
        
        // Function to open edit enrollment modal
        function openEditEnrollmentModal(data) {
            document.getElementById('edit_enrollment_id').value = data.enrollment_id;
            document.getElementById('edit_lrn').value = data.lrn;
            document.getElementById('edit_grade_level').value = data.grade_level;
            document.getElementById('edit_section_id').value = data.section_id;
            document.getElementById('edit_school_year').value = data.school_year;
            
            var editModal = new bootstrap.Modal(document.getElementById('editEnrollmentModal'));
            editModal.show();
        }
    </script>

</body>
</html>
