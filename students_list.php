<?php
session_start();
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

include 'conn.php';

// Handle Add Student
if (isset($_POST['add_student'])) {
    $lrn = htmlspecialchars($_POST['lrn']);
    $lastname = htmlspecialchars($_POST['lastname']);
    $firstname = htmlspecialchars($_POST['firstname']);
    $middlename = htmlspecialchars($_POST['middlename']);
    $suffix = htmlspecialchars($_POST['suffix']);
    $age = (int) htmlspecialchars($_POST['age']);
    $birthdate = htmlspecialchars($_POST['birthdate']);

    // Validate LRN length on server side
    if (strlen($lrn) !== 12 || !ctype_digit($lrn)) {
        echo "<script>alert('LRN must be exactly 12 digits.'); window.location.href='students_list.php';</script>";
        exit();
    }

    if (!$conn) {
        echo "<script>alert('Database connection failed for adding student.'); window.location.href='students_list.php';</script>";
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO students (lrn, lastname, firstname, middlename, suffix, age, birthdate) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        echo "<script>alert('Error preparing add statement: " . $conn->error . "'); window.location.href='students_list.php';</script>";
        exit();
    }

    // Changed from "issssis" to "ssssiis" - treating LRN as string
    $stmt->bind_param("ssssiis", $lrn, $lastname, $firstname, $middlename, $suffix, $age, $birthdate);

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
    $lrn = htmlspecialchars($_POST['student_lrn']); // Changed from (int) cast to string

    if (!$conn) {
        echo "<script>alert('Database connection failed for deleting student.'); window.location.href='students_list.php';</script>";
        exit();
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("DELETE FROM students WHERE lrn = ?");
        $stmt->bind_param("s", $lrn); // Changed from "i" to "s"
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        $conn->commit();
        header("Location: students_list.php?status=deleted");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error deleting student: " . $e->getMessage() . "'); window.location.href='students_list.php';</script>";
        exit();
    }
}

// Handle Edit Student
if (isset($_POST['edit_student'])) {
    $original_lrn = htmlspecialchars($_POST['edit_id']);
    $lrn = htmlspecialchars($_POST['edit_lrn']);
    $lastname = htmlspecialchars($_POST['edit_lastname']);
    $firstname = htmlspecialchars($_POST['edit_firstname']);
    $middlename = htmlspecialchars($_POST['edit_middlename']);
    $suffix = htmlspecialchars($_POST['edit_suffix']);
    $age = (int) htmlspecialchars($_POST['edit_age']);
    $birthdate = htmlspecialchars($_POST['edit_birthdate']);

    // Validate LRN length on server side
    if (strlen($lrn) !== 12 || !ctype_digit($lrn)) {
        echo "<script>alert('LRN must be exactly 12 digits.'); window.location.href='students_list.php';</script>";
        exit();
    }

    if (!$conn) {
        echo "<script>alert('Database connection failed for editing student.'); window.location.href='students_list.php';</script>";
        exit();
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("UPDATE students SET lrn=?, lastname=?, firstname=?, middlename=?, suffix=?, age=?, birthdate=? WHERE lrn=?");
        if ($stmt === false) {
            throw new Exception($conn->error);
        }

        // Changed from "issssisi" to "ssssiiss" - treating LRN as string
        $stmt->bind_param("ssssiiss", $lrn, $lastname, $firstname, $middlename, $suffix, $age, $birthdate, $original_lrn);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        $conn->commit();
        header("Location: students_list.php?status=updated");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error updating student: " . $e->getMessage() . "'); window.location.href='students_list.php';</script>";
        exit();
    }
}

// Fetch Students for Display
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
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            lucide.createIcons();
            window.openEditModal = function(student) {
                $('#editModal').modal('show');
                $('#edit_id').val(student.lrn);
                $('#edit_lrn').val(student.lrn);
                $('#edit_lastname').val(student.lastname);
                $('#edit_firstname').val(student.firstname);
                $('#edit_middlename').val(student.middlename);
                $('#edit_suffix').val(student.suffix);
                $('#edit_age').val(student.age);
                $('#edit_birthdate').val(student.birthdate);
            }
            $('#birthdate').change(function(){
                var dob = new Date($(this).val());
                var diff = Date.now() - dob.getTime();
                var ageDate = new Date(diff);
                var age = Math.abs(ageDate.getUTCFullYear() - 1970);
                $('#age').val(age);
            });
            $('#edit_birthdate').change(function(){
                var dob = new Date($(this).val());
                var diff = Date.now() - dob.getTime();
                var ageDate = new Date(diff);
                var age = Math.abs(ageDate.getUTCFullYear() - 1970);
                $('#edit_age').val(age);
            });
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

        // Enhanced LRN validation function
        function validateLRN(input) {
            // Remove non-digit characters
            input.value = input.value.replace(/\D/g, '');
            
            // Limit to 12 digits
            if (input.value.length > 12) {
                input.value = input.value.substring(0, 12);
            }
            
            const errorElement = input.id === 'add_lrn' 
                ? document.getElementById('lrn-error') 
                : document.getElementById('edit-lrn-error');
            
            // Check if LRN is exactly 12 digits
            if (input.value.length !== 12) {
                input.setCustomValidity('LRN must be exactly 12 digits');
                errorElement.style.display = 'block';
                errorElement.textContent = `LRN must be exactly 12 digits (currently ${input.value.length} digits)`;
            } else {
                input.setCustomValidity('');
                errorElement.style.display = 'none';
            }
        }

        // Enhanced form validation
        document.addEventListener('DOMContentLoaded', function() {
            const addForm = document.querySelector('#addStudentModal form');
            const editForm = document.querySelector('#editModal form');

            if (addForm) {
                addForm.addEventListener('submit', function(event) {
                    const lrnInput = document.getElementById('add_lrn');
                    if (lrnInput.value.length !== 12 || !/^\d{12}$/.test(lrnInput.value)) {
                        event.preventDefault();
                        lrnInput.focus();
                        document.getElementById('lrn-error').style.display = 'block';
                        document.getElementById('lrn-error').textContent = 'LRN must be exactly 12 digits';
                        return false;
                    }
                });
            }

            if (editForm) {
                editForm.addEventListener('submit', function(event) {
                    const lrnInput = document.getElementById('edit_lrn');
                    if (lrnInput.value.length !== 12 || !/^\d{12}$/.test(lrnInput.value)) {
                        event.preventDefault();
                        lrnInput.focus();
                        document.getElementById('edit-lrn-error').style.display = 'block';
                        document.getElementById('edit-lrn-error').textContent = 'LRN must be exactly 12 digits';
                        return false;
                    }
                });
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
                        <h2 class="h3 mb-0 text-gray-800">STUDENTS DASHBOARD</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                            Add Student
                        </button>
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
                                            <th class="border px-3 py-2">Suffix</th>
                                            <th class="border px-3 py-2">Age</th>
                                            <th class="border px-3 py-2">Birthdate</th>
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
                                                <td class="border px-3 py-2"><?= htmlspecialchars($row['suffix'] ?? '') ?></td>
                                                <td class="border px-3 py-2"><?= htmlspecialchars($row['age'] ?? '') ?></td>
                                                <td class="border px-3 py-2"><?= htmlspecialchars($row['birthdate'] ?? '') ?></td>
                                                <td class="border px-3 py-2 space-x-2">
                                                    <button onclick='openEditModal(<?= json_encode($row) ?>)' class="text-blue-600 hover:text-blue-800 p-1 rounded">
                                                        <i data-lucide="pencil" class="w-5 h-5 inline"></i>
                                                    </button>
                                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars($row['firstname'] .' '. $row['lastname']) ?>?');">
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
                                <div class="card p-4 shadow-sm" style="border-radius: 20px; background-color: rgba(255,255,255,0.9);">
                                    <img src="images/you.png" width="100" height="100" alt="" class="rounded-circle mx-auto d-block"><br>
                                    <h3 class="text-center">ADD STUDENT</h3><br>
                                    <form method="post">
                                        <div class="mb-3">
                                            <input type="text" 
                                                   class="form-control" 
                                                   name="lrn" 
                                                   id="add_lrn" 
                                                   placeholder="Enter LRN (12 digits)" 
                                                   required 
                                                   maxlength="12" 
                                                   pattern="\d{12}" 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center; font-family: 'Courier New', monospace; font-size: 16px; letter-spacing: 1px;"
                                                   oninput="validateLRN(this)"
                                                   title="LRN must be exactly 12 digits"
                                                   autocomplete="off"
                                            >
                                            <small class="text-danger" id="lrn-error" style="display:none;">LRN must be exactly 12 digits</small>
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="lastname" placeholder="Enter Last Name" required style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="firstname" placeholder="Enter First Name" required style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="middlename" placeholder="Enter Middle Name" style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="suffix" placeholder="Enter Suffix (Jr., III, etc.)" style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="number" class="form-control" id="age" name="age" placeholder="Student Age" required style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="date" class="form-control" id="birthdate" name="birthdate" placeholder="Enter Birthdate" required style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
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
                                <div class="card p-4 shadow-sm" style="border-radius: 20px; background-color: rgba(255,255,255,0.9);">
                                    <img src="images/you.png" width="100" height="100" alt="" class="rounded-circle mx-auto d-block"><br>
                                    <h3 class="text-center">EDIT STUDENT</h3><br>
                                    <form method="post">
                                        <input type="hidden" id="edit_id" name="edit_id">
                                        <div class="mb-3">
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="edit_lrn" 
                                                   name="edit_lrn" 
                                                   placeholder="Enter LRN (12 digits)" 
                                                   required 
                                                   maxlength="12" 
                                                   pattern="\d{12}" 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center; font-family: 'Courier New', monospace; font-size: 16px; letter-spacing: 1px;"
                                                   oninput="validateLRN(this)"
                                                   title="LRN must be exactly 12 digits"
                                                   autocomplete="off"
                                            >
                                            <small class="text-danger" id="edit-lrn-error" style="display:none;">LRN must be exactly 12 digits</small>
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="edit_lastname" name="edit_lastname" placeholder="Enter Last Name" required style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="edit_firstname" name="edit_firstname" placeholder="Enter First Name" required style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="edit_middlename" name="edit_middlename" placeholder="Enter Middle Name" style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="edit_suffix" name="edit_suffix" placeholder="Enter Suffix (Jr., III, etc.)" style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="number" class="form-control" id="edit_age" name="edit_age" placeholder="Enter Age" required style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <input type="date" class="form-control" id="edit_birthdate" name="edit_birthdate" placeholder="Enter Birthdate" required style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
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
                <span>&copy; Your Website 07/2025</span>
            </div>
        </div>
    </footer>
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>
</body>
</html>