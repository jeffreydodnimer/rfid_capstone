<?php
include 'conn.php';

// Handle Add Student
if (isset($_POST['add_student'])) {
    $lrn = htmlspecialchars($_POST['lrn']);
    $lastname = htmlspecialchars($_POST['lastname']);
    $firstname = htmlspecialchars($_POST['firstname']);
    $middlename = htmlspecialchars($_POST['middlename']);
    $birthdate = htmlspecialchars($_POST['birthdate']);
    $contact = htmlspecialchars($_POST['contact']);
    $guardian = htmlspecialchars($_POST['guardian']);

    // Check if connection is valid
    if (!$conn) {
        echo "<script>alert('Database connection failed for adding student.'); window.location.href='students_list.php';</script>";
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO students (lrn, lastname, firstname, middlename, birthdate, contact, guardian) VALUES (?, ?, ?, ?, ?, ?, ?)");

    // Check if prepare() was successful
    if ($stmt === false) {
        echo "<script>alert('Error preparing add statement: " . $conn->error . "'); window.location.href='students_list.php';</script>";
        exit();
    }

    $stmt->bind_param("sssssss", $lrn, $lastname, $firstname, $middlename, $birthdate, $contact, $guardian);

    if ($stmt->execute()) {
        header("Location: students_list.php?status=added");
        exit();
    } else {
        echo "<script>alert('Error adding student: " . $stmt->error . "'); window.location.href='students_list.php';</script>";
    }
    $stmt->close();
}

// Handle Delete Student
if (isset($_POST['delete_student'])) {
    // Get the LRN from the form
    $lrn = $_POST['student_lrn']; // Changed from student_id to student_lrn
    
    if (!$conn) {
        echo "<script>alert('Database connection failed for deleting student.'); window.location.href='students_list.php';</script>";
        exit();
    }

    // Start a transaction to ensure data consistency
    $conn->begin_transaction();

    try {
        // Delete related records from other tables (if any)
        // For example, if you have a table called 'grades' with a foreign key to 'students'
        // $stmt = $conn->prepare("DELETE FROM grades WHERE lrn = ?");
        // $stmt->bind_param("s", $lrn);
        // $stmt->execute();
        // $stmt->close();

        // Delete the student record using LRN
        $stmt = $conn->prepare("DELETE FROM students WHERE lrn = ?");
        $stmt->bind_param("s", $lrn); // Using string parameter since LRN might not be an integer
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();

        // Commit the transaction
        $conn->commit();

        header("Location: students_list.php?status=deleted");
        exit();
    } catch (Exception $e) {
        // Roll back the transaction if an error occurs
        $conn->rollback();
        echo "<script>alert('Error deleting student: " . $e->getMessage() . "'); window.location.href='students_list.php';</script>";
        exit();
    }
}

// Handle Edit Student
if (isset($_POST['edit_student'])) {
    $original_lrn = $_POST['edit_id']; // Changed from id to edit_id to match the form field
    $lrn = htmlspecialchars($_POST['edit_lrn'], ENT_QUOTES, 'UTF-8');
    $lastname = htmlspecialchars($_POST['edit_lastname'], ENT_QUOTES, 'UTF-8');
    $firstname = htmlspecialchars($_POST['edit_firstname'], ENT_QUOTES, 'UTF-8');
    $middlename = htmlspecialchars($_POST['edit_middlename'], ENT_QUOTES, 'UTF-8');
    $birthdate = htmlspecialchars($_POST['edit_birthdate'], ENT_QUOTES, 'UTF-8');
    $contact = htmlspecialchars($_POST['edit_contact'], ENT_QUOTES, 'UTF-8');
    $guardian = htmlspecialchars($_POST['edit_guardian'], ENT_QUOTES, 'UTF-8');

    // Check if connection is valid
    if (!$conn) {
        echo "<script>alert('Database connection failed for editing student.'); window.location.href='students_list.php';</script>";
        exit();
    }

    // Start a transaction for the update
    $conn->begin_transaction();

    try {
        // Prepare the UPDATE statement - update record using the original LRN
        $stmt = $conn->prepare("UPDATE students SET lrn=?, lastname=?, firstname=?, middlename=?, birthdate=?, contact=?, guardian=? WHERE lrn=?");

        // Check if prepare() was successful
        if ($stmt === false) {
            throw new Exception($conn->error);
        }

        $stmt->bind_param("ssssssss", $lrn, $lastname, $firstname, $middlename, $birthdate, $contact, $guardian, $original_lrn);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        
        $stmt->close();
        
        // Commit the transaction
        $conn->commit();
        
        header("Location: students_list.php?status=updated");
        exit();
    } catch (Exception $e) {
        // Roll back the transaction if an error occurs
        $conn->rollback();
        echo "<script>alert('Error updating student: " . $e->getMessage() . "'); window.location.href='students_list.php';</script>";
        exit();
    }
}

// --- Fetch Students for Display (runs every time the page loads) ---
if (!$conn) {
    die("Database connection failed during student list retrieval.");
}
$students_result = $conn->query("SELECT * FROM students ORDER BY lastname ASC"); 
if (!$students_result) {
    die("Error fetching students: " . $conn->error); 
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Student List</title>

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

    <script>
        $(document).ready(function() {
            lucide.createIcons();

            // Opens the Edit Modal and fills the fields with student data
            window.openEditModal = function(student) {
                $('#editModal').modal('show');
                // Use lrn as the identifier for the edit operation
                $('#edit_id').val(student.lrn);
                $('#edit_lrn').val(student.lrn);
                $('#edit_lastname').val(student.lastname);
                $('#edit_firstname').val(student.firstname);
                $('#edit_middlename').val(student.middlename);
                $('#edit_birthdate').val(student.birthdate);
                $('#edit_contact').val(student.contact);
                $('#edit_guardian').val(student.guardian);
            }

            // Check for status messages in URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            if (status) {
                let message = "";
                if (status === "added") {
                    message = "Student added successfully!";
                } else if (status === "deleted") {
                    message = "Student deleted successfully!";
                } else if (status === "updated") {
                    message = "Student updated successfully!";
                }

                if (message) {
                    alert(message);
                    window.history.replaceState({}, document.title, "students_list.php");
                }
            }
        });
    </script>
</head>

<body id="page-top">

    <?php include 'nav.php'; ?>

    <div id="wrapper">
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h3 mb-0 text-gray-800">STUDENTS LIST</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal"
                            style="box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);">Add Student</button>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-4 shadow-md">
                        <div class="bg-gray-100 rounded-xl p-5 shadow-md">
                            <div class="overflow-x-auto">
                                <table class="w-full table-auto border border-gray-300 text-sm text-center">
                                    <thead class="bg-gray-200 font-semibold">
                                        <tr>
                                            <th class="border px-3 py-2">No.</th>
                                            <th class="border px-3 py-2">LRN</th>
                                            <th class="border px-3 py-2">Lastname</th>
                                            <th class="border px-3 py-2">Firstname</th>
                                            <th class="border px-3 py-2">Middlename</th>
                                            <th class="border px-3 py-2">Birthdate</th>
                                            <th class="border px-3 py-2">Contact</th>
                                            <th class="border px-3 py-2">Guardian</th>
                                            <th class="border px-3 py-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $counter = 1;
                                        while ($row = $students_result->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="border px-3 py-2"><?= $counter++ ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($row['lrn'] ?? '') ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($row['lastname'] ?? '') ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($row['firstname'] ?? '') ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($row['middlename'] ?? '') ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($row['birthdate'] ?? '') ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($row['contact'] ?? '') ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($row['guardian'] ?? '') ?></td>
                                            <td class="border px-3 py-2 space-x-2">
                                                <button onclick='openEditModal(<?= json_encode($row) ?>)'
                                                    class="text-blue-600 hover:text-blue-800 p-1 rounded">
                                                    <i data-lucide="pencil" class="w-5 h-5 inline"></i>
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?>?');">
                                                    <input type="hidden" name="student_lrn" value="<?= htmlspecialchars($row['lrn'] ?? '') ?>" />
                                                    <button type="submit" name="delete_student" class="text-red-600 hover:text-red-800 p-1 rounded">
                                                        <i data-lucide="trash" class="w-5 h-5 inline"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php if ($students_result->num_rows === 0): ?>
                                            <tr>
                                                <td colspan="9" class="border px-3 py-2 text-center text-gray-500">No students found.</td>
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

    <!-- Add Student Modal -->
    <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="position: relative;">
                <div class="modal-header">
                    <h5 class="modal-title" id="addStudentModalLabel">Add Student Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container mt-3">
                        <div class="row justify-content-center">
                            <div class="col-md-10">
                                <div class="card p-4 shadow-sm" style="border-radius: 20px; background-color: rgba(255, 255, 255, 0.9);">
                                    <img src="images/you.png" width="100" height="100" alt="" class="rounded-circle mx-auto d-block"><br>
                                    <h3 class="text-center">ADD STUDENT</h3><br>
                                    <form method="post">
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="lrn" placeholder="Enter LRN" required
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="lastname" placeholder="Enter Last Name" required
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="firstname" placeholder="Enter First Name" required
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="middlename" placeholder="Enter Middle Name"
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="date" class="form-control" name="birthdate" placeholder="Enter Birthdate"
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="contact" placeholder="Enter Contact Number"
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="guardian" placeholder="Enter Guardian's Name"
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="text-center">
                                            <button type="submit" name="add_student" class="btn btn-primary px-4">Register Student</button>
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

    <!-- Edit Student Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="position: relative;">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Student Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container mt-3">
                        <div class="row justify-content-center">
                            <div class="col-md-10">
                                <div class="card p-4 shadow-sm" style="border-radius: 20px; background-color: rgba(255, 255, 255, 0.9);">
                                    <img src="images/you.png" width="100" height="100" alt="" class="rounded-circle mx-auto d-block"><br>
                                    <h3 class="text-center">EDIT STUDENT</h3><br>
                                    <form method="post">
                                        <input type="hidden" id="edit_id" name="edit_id">
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="edit_lrn" name="edit_lrn" placeholder="Enter LRN" required
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="edit_lastname" name="edit_lastname" placeholder="Enter Last Name" required
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="edit_firstname" name="edit_firstname" placeholder="Enter First Name" required
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="edit_middlename" name="edit_middlename" placeholder="Enter Middle Name"
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="date" class="form-control" id="edit_birthdate" name="edit_birthdate" placeholder="Enter Birthdate"
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="edit_contact" name="edit_contact" placeholder="Enter Contact Number"
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="edit_guardian" name="edit_guardian" placeholder="Enter Guardian's Name"
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="text-center">
                                            <button type="submit" name="edit_student" class="btn btn-primary px-4">Update Student</button>
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
</body>
</html>