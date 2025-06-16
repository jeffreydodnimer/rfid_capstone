<?php
include 'conn.php';


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
        exit(); // Stop execution
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
    $student_id = (int)$_POST['student_id']; // Cast to integer for security

    if (!$conn) {
        echo "<script>alert('Database connection failed for deleting student.'); window.location.href='students_list.php';</script>";
        exit();
    }

    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");

    // Check if prepare() was successful
    if ($stmt === false) {
        echo "<script>alert('Error preparing delete statement: " . $conn->error . "'); window.location.href='students_list.php';</script>";
        exit(); // Stop execution
    }

    $stmt->bind_param("i", $student_id);

    if ($stmt->execute()) {
        header("Location: students_list.php?status=deleted");
        exit();
    } else {
        echo "<script>alert('Error deleting student: " . $stmt->error . "'); window.location.href='students_list.php';</script>";
    }
    $stmt->close();
}

// Handle Edit Student
if (isset($_POST['edit_student'])) {
    $id = (int)$_POST['id'];
    $lrn = htmlspecialchars($_POST['edit_lrn']);
    $lastname = htmlspecialchars($_POST['edit_lastname']);
    $firstname = htmlspecialchars($_POST['edit_firstname']);
    $middlename = htmlspecialchars($_POST['edit_middlename']);
    $birthdate = htmlspecialchars($_POST['edit_birthdate']);
    $contact = htmlspecialchars($_POST['edit_contact']);
    $guardian = htmlspecialchars($_POST['edit_guardian']);

    // Check if connection is valid
    if (!$conn) {
        echo "<script>alert('Database connection failed for editing student.'); window.location.href='students_list.php';</script>";
        exit();
    }

    // Fetch the current student data from the database
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    if ($stmt === false) {
        echo "<script>alert('Error preparing SELECT statement: " . $conn->error . "'); window.location.href='students_list.php';</script>";
        exit();
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $currentStudent = $result->fetch_assoc();
    $stmt->close();

    // Check if any changes were made
    if (
        $currentStudent['lrn'] === $lrn &&
        $currentStudent['lastname'] === $lastname &&
        $currentStudent['firstname'] === $firstname &&
        $currentStudent['middlename'] === $middlename &&
        $currentStudent['birthdate'] === $birthdate &&
        $currentStudent['contact'] === $contact &&
        $currentStudent['guardian'] === $guardian
    ) {
        // No changes were made
        echo "<script>alert('No changes were made.'); window.location.href='students_list.php';</script>";
        exit();
    }

    // Prepare the UPDATE statement
    $stmt = $conn->prepare("UPDATE students SET lrn=?, lastname=?, firstname=?, middlename=?, birthdate=?, contact=?, guardian=? WHERE id=?");

    // Check if prepare() was successful
    if ($stmt === false) {
        echo "<script>alert('Error preparing UPDATE statement: " . $conn->error . "'); window.location.href='students_list.php';</script>";
        exit();
    }

    $stmt->bind_param("sssssssi", $lrn, $lastname, $firstname, $middlename, $birthdate, $contact, $guardian, $id);

    if ($stmt->execute()) {
        header("Location: students_list.php?status=updated");
        exit();
    } else {
        echo "<script>alert('Error updating student: " . $stmt->error . "'); window.location.href='students_list.php';</script>";
    }
    $stmt->close();
}

// --- Fetch Students for Display (runs every time the page loads) ---
if (!$conn) {
    die("Database connection failed during student list retrieval.");
}
$students_result = $conn->query("SELECT * FROM students"); 
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
    <meta name="description" content="Student Management System">
    <meta name="author" content="Your Name">

    <title>Student List</title>

    <!-- Custom fonts for this template-->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <!-- Custom styles for this template-->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- jQuery and Bootstrap JS - IMPORTANT: Load before your custom JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function() {
            lucide.createIcons();

            window.openEditModal = function(student) {
                $('#editModal').modal('show'); // Show the Bootstrap modal

                // Fill form fields with the student's current data
                $('#edit_id').val(student.id);
                $('#edit_lrn').val(student.lrn);
                $('#edit_lastname').val(student.lastname);
                $('#edit_firstname').val(student.firstname);
                $('#edit_middlename').val(student.middlename);
                $('#edit_birthdate').val(student.birthdate);
                $('#edit_contact').val(student.contact);
                $('#edit_guardian').val(student.guardian);
            }

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
        <!-- Sidebar and other wrapper content from nav.php -->
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar from nav.php -->
                <!-- Begin Page Content -->
                <div class="container-fluid">
                    <!-- Page Heading -->
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
                                        // Use $students_result which was fetched above
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
                                                <!-- Edit Button - Calls JS function to open modal and populate data -->
                                                <button onclick='openEditModal(<?= json_encode($row) ?>)'
                                                    class="text-blue-600 hover:text-blue-800 p-1 rounded">
                                                    <i data-lucide="pencil" class="w-5 h-5 inline"></i>
                                                </button>

                                                <!-- Delete Form - Submits to PHP for deletion -->
                                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?>?');">
                                                    <input type="hidden" name="student_id" value="<?= htmlspecialchars($row['id'] ?? '') ?>" />
                                                    <button type="submit" name="delete_student"
                                                        class="text-red-600 hover:text-red-800 p-1 rounded">
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
    <!-- End of Page Wrapper -->


    <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="position: relative;">
                <!-- Modal Header -->
                <div class="modal-header">
                    <h5 class="modal-title" id="addStudentModalLabel">Add Student Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <div class="container mt-3">
                        <div class="row justify-content-center">
                            <div class="col-md-10">
                                <div class="card p-4 shadow-sm" style="border-radius: 20px; background-color: rgba(255, 255, 255, 0.9);">
                                    <img src="images/you.png" width="100" height="100" alt="" class="rounded-circle mx-auto d-block"><br>
                                    <h3 class="text-center">ADD STUDENT</h3><br>
                                    
                                    <!-- Form for Adding Student -->
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
                                    <!-- End Form -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Student Modal (Bootstrap 5) -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="position: relative;">
                <!-- Modal Header -->
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Student Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <div class="container mt-3">
                        <div class="row justify-content-center">
                            <div class="col-md-10">
                                <div class="card p-4 shadow-sm" style="border-radius: 20px; background-color: rgba(255, 255, 255, 0.9);">
                                    <img src="images/you.png" width="100" height="100" alt="" class="rounded-circle mx-auto d-block"><br>
                                    <h3 class="text-center">EDIT STUDENT</h3><br>
                                    
                                    <!-- Form for Editing Student -->
                                    <form method="post">
                                        <input type="hidden" id="edit_id" name="id"> <!-- Hidden input for student ID -->
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
                                    <!-- End Form -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <br>
    <!-- Footer -->
    <footer class="sticky-footer bg-white">
        <div class="container my-auto">
            <div class="copyright text-center my-auto">
                <span>Copyright &copy; Your Website 06/2025</span>
            </div>
        </div>
    </footer>
    <!-- End of Footer -->

    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>


</body>
</html>