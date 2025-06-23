<?php
session_start();  // Must be at the VERY TOP

// Redirect if user is not logged in
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include database connection
include 'conn.php'; 

// Check database connection immediately
if ($conn->connect_error) { // Use connect_error for initial connection failure
    die("Database connection failed: " . $conn->connect_error);
}

// --- PHP Logic for CRUD Operations ---

// Handle Add Guardian
if (isset($_POST['add_guardian'])) {
    // Verify CSRF token for security
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo "<script>alert('Security validation failed. Please try again.'); window.location.href='guardians_list.php';</script>";
        exit();
    }

    // Sanitize and get values from the form
    $lrn = (int) htmlspecialchars($_POST['lrn'], ENT_QUOTES, 'UTF-8');
    $lastname = htmlspecialchars($_POST['lastname'], ENT_QUOTES, 'UTF-8');
    $firstname = htmlspecialchars($_POST['firstname'], ENT_QUOTES, 'UTF-8');
    $middlename = htmlspecialchars($_POST['middlename'], ENT_QUOTES, 'UTF-8');
    $suffix = htmlspecialchars($_POST['suffix'], ENT_QUOTES, 'UTF-8');
    $contact = htmlspecialchars($_POST['contact'], ENT_QUOTES, 'UTF-8'); // Store as string (VARCHAR)
    $relationship_to_student = htmlspecialchars($_POST['relationship_to_student'], ENT_QUOTES, 'UTF-8');

    // Server-side validation for required fields
    if (empty($lrn) || empty($lastname) || empty($firstname) || empty($contact) || empty($relationship_to_student)) {
        echo "<script>alert('All required fields must be filled out.'); window.location.href='guardians_list.php';</script>";
        exit();
    }
    
    // Validate contact number format (optional, but good practice for VARCHAR)
    if (!preg_match("/^[0-9+\-\(\) ]{7,20}$/", $contact)) { // Basic phone number regex
        echo "<script>alert('Invalid phone number format. Please use a valid contact number (e.g., numbers, +, -, (, )).'); window.location.href='guardians_list.php';</script>";
        exit();
    }

    // Optimized query to check student existence and guardian duplicates in one go
    // This assumes students.contact is VARCHAR as recommended or compatible
    $checkStmt = $conn->prepare("
        SELECT s.contact, 
               (SELECT COUNT(*) FROM guardians WHERE lrn = ? AND relationship_to_student = ?) AS duplicate_count
        FROM students s
        WHERE s.lrn = ?
    ");
    
    if ($checkStmt === false) {
        echo "<script>alert('Error preparing validation statement: " . htmlspecialchars($conn->error) . "'); window.location.href='guardians_list.php';</script>";
        exit();
    }
    
    $checkStmt->bind_param("isi", $lrn, $relationship_to_student, $lrn);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        // Error: Student with the given LRN was not found.
        echo "<script>alert('Error: No student found with the provided LRN (" . $lrn . "). Please check the LRN and try again.'); window.location.href='guardians_list.php';</script>";
        $checkStmt->close();
        exit();
    } else {
        $data = $result->fetch_assoc();
        
        // Check for duplicate guardian
        if ($data['duplicate_count'] > 0) {
            echo "<script>alert('A guardian with this relationship already exists for this student.'); window.location.href='guardians_list.php';</script>";
            $checkStmt->close();
            exit();
        }
        
        // Check if contact matches student record (string comparison)
        if ($contact !== $data['contact']) {
            echo "<script>alert('Error: The contact number provided for the guardian does not match the registered contact for the student with LRN (" . $lrn . ").'); window.location.href='guardians_list.php';</script>";
            $checkStmt->close();
            exit();
        }
    }
    $checkStmt->close();

    // If all validations pass, proceed with inserting the guardian
    $stmt = $conn->prepare("INSERT INTO guardians (lrn, lastname, firstname, middlename, suffix, contact, relationship_to_student) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt === false) {
        echo "<script>alert('Error preparing add statement: " . htmlspecialchars($conn->error) . "'); window.location.href='guardians_list.php';</script>";
        exit();
    }

    // Bind parameters: issssss (all strings except LRN)
    $stmt->bind_param("issssss", $lrn, $lastname, $firstname, $middlename, $suffix, $contact, $relationship_to_student);

    if ($stmt->execute()) {
        // Create logs directory if it doesn't exist
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
        }
        
        // Log the successful addition
        $log_message = date('Y-m-d H:i:s') . " - Guardian added: {$firstname} {$lastname} for student LRN: {$lrn}\n";
        @file_put_contents('logs/guardian_logs.txt', $log_message, FILE_APPEND); // @ suppresses errors
        
        header("Location: guardians_list.php?status=added");
        exit();
    } else {
        echo "<script>alert('Error adding guardian: " . htmlspecialchars($stmt->error) . "'); window.location.href='guardians_list.php';</script>";
    }
    $stmt->close();
}

// Handle Delete Guardian
if (isset($_POST['delete_guardian'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo "<script>alert('Security validation failed. Please try again.'); window.location.href='guardians_list.php';</script>";
        exit();
    }

    $guardian_id = (int) $_POST['guardian_id'];

    $conn->begin_transaction(); // Start transaction
    try {
        $stmt = $conn->prepare("DELETE FROM guardians WHERE guardian_id = ?");
        if ($stmt === false) {
             throw new Exception($conn->error); // Ensure error is caught by catch
        }
        $stmt->bind_param("i", $guardian_id);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error); // Ensure error is caught by catch
        }
        $stmt->close();
        $conn->commit(); // Commit transaction
        
        // Log the deletion
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
        }
        $log_message = date('Y-m-d H:i:s') . " - Guardian ID {$guardian_id} deleted\n";
        @file_put_contents('logs/guardian_logs.txt', $log_message, FILE_APPEND);
        
        header("Location: guardians_list.php?status=deleted");
        exit();
    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        echo "<script>alert('Error deleting guardian: " . htmlspecialchars($e->getMessage()) . "'); window.location.href='guardians_list.php';</script>";
        exit();
    }
}

// Handle Edit Guardian
if (isset($_POST['edit_guardian'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo "<script>alert('Security validation failed. Please try again.'); window.location.href='guardians_list.php';</script>";
        exit();
    }

    $guardian_id = (int) $_POST['edit_guardian_id'];
    $lrn = (int) htmlspecialchars($_POST['lrn'], ENT_QUOTES, 'UTF-8');
    $lastname = htmlspecialchars($_POST['lastname'], ENT_QUOTES, 'UTF-8');
    $firstname = htmlspecialchars($_POST['firstname'], ENT_QUOTES, 'UTF-8');
    $middlename = htmlspecialchars($_POST['middlename'], ENT_QUOTES, 'UTF-8');
    $suffix = htmlspecialchars($_POST['suffix'], ENT_QUOTES, 'UTF-8');
    $contact = htmlspecialchars($_POST['contact'], ENT_QUOTES, 'UTF-8'); // Store as string (VARCHAR)
    $relationship_to_student = htmlspecialchars($_POST['relationship_to_student'], ENT_QUOTES, 'UTF-8');
    
    // Server-side validation for required fields
    if (empty($lrn) || empty($lastname) || empty($firstname) || empty($contact) || empty($relationship_to_student)) {
        echo "<script>alert('All required fields must be filled out.'); window.location.href='guardians_list.php';</script>";
        exit();
    }

    // Validate contact number format (optional, but good practice for VARCHAR)
    if (!preg_match("/^[0-9+\-\(\) ]{7,20}$/", $contact)) { // Basic phone number regex
        echo "<script>alert('Invalid phone number format. Please use a valid contact number (e.g., numbers, +, -, (, )).'); window.location.href='guardians_list.php';</script>";
        exit();
    }
    
    // Optimized query to check student existence and duplicates (excluding current guardian)
    $checkStmt = $conn->prepare("
        SELECT s.contact, 
               (SELECT COUNT(*) FROM guardians WHERE lrn = ? AND relationship_to_student = ? AND guardian_id != ?) AS duplicate_count
        FROM students s
        WHERE s.lrn = ?
    ");
    
    if ($checkStmt === false) {
        echo "<script>alert('Error preparing validation statement: " . htmlspecialchars($conn->error) . "'); window.location.href='guardians_list.php';</script>";
        exit();
    }
    
    $checkStmt->bind_param("isii", $lrn, $relationship_to_student, $guardian_id, $lrn);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        echo "<script>alert('Error: No student found with the provided LRN (" . $lrn . "). Please check the LRN and try again.'); window.location.href='guardians_list.php';</script>";
        $checkStmt->close();
        exit();
    } else {
        $data = $result->fetch_assoc();
        
        // Check for duplicate guardian
        if ($data['duplicate_count'] > 0) {
            echo "<script>alert('Another guardian with this relationship already exists for this student.'); window.location.href='guardians_list.php';</script>";
            $checkStmt->close();
            exit();
        }
        
        // Check if contact matches student record
        if ($contact !== $data['contact']) {
            echo "<script>alert('Error: The contact number provided for the guardian does not match the registered contact for the student with LRN (" . $lrn . ").'); window.location.href='guardians_list.php';</script>";
            $checkStmt->close();
            exit();
        }
    }
    $checkStmt->close();

    $conn->begin_transaction(); // Start transaction
    try {
        $stmt = $conn->prepare("UPDATE guardians SET lrn=?, lastname=?, firstname=?, middlename=?, suffix=?, contact=?, relationship_to_student=? WHERE guardian_id=?");
        if ($stmt === false) {
            throw new Exception($conn->error); // Ensure error is caught by catch
        }
        // Bind parameters: isssssisi (all strings except LRN and guardian_id)
        $stmt->bind_param("isssssisi", $lrn, $lastname, $firstname, $middlename, $suffix, $contact, $relationship_to_student, $guardian_id);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error); // Ensure error is caught by catch
        }
        $stmt->close();
        $conn->commit(); // Commit transaction
        
        // Log the update
        if (!is_dir('logs')) {
            mkdir('logs', 0755, true);
        }
        $log_message = date('Y-m-d H:i:s') . " - Guardian ID {$guardian_id} updated\n";
        @file_put_contents('logs/guardian_logs.txt', $log_message, FILE_APPEND);
        
        header("Location: guardians_list.php?status=updated");
        exit();
    } catch (Exception $e) {
        $conn->rollback(); // Rollback on error
        echo "<script>alert('Error updating guardian: " . htmlspecialchars($e->getMessage()) . "'); window.location.href='guardians_list.php';</script>";
        exit();
    }
}

// --- Data Fetching for Display ---
$guardians_result = $conn->query("SELECT * FROM guardians ORDER BY lastname ASC"); 
if (!$guardians_result) {
    die("Error fetching guardians: " . $conn->error);
}

// Get the latest added guardian ID for highlighting (used in JS)
$latest_id = 0;
if (isset($_GET['status']) && $_GET['status'] === 'added') {
    // This SELECT MAX is only reliable if guardian_id is auto-incrementing
    $latest_result = $conn->query("SELECT MAX(guardian_id) as latest_id FROM guardians");
    if ($latest_result && $latest_row = $latest_result->fetch_assoc()) {
        $latest_id = (int)$latest_row['latest_id'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Guardian List</title>

    <!-- Custom Fonts and Styles (SB Admin 2) -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    
    <!-- Tailwind CSS (for some utility classes) and Lucide Icons -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- jQuery and Bootstrap JS Bundle (popper.js included) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom Styles -->
    <style>
        /* Highlight animation for new or updated rows */
        @keyframes highlightRow {
            0% { background-color: #c8e6c9; } /* Light green */
            100% { background-color: transparent; }
        }
        
        .highlight-row {
            animation: highlightRow 2s ease-in-out;
        }
        
        /* Form styling: highlight focus states */
        .form-control:focus {
            border-color: #4e73df; /* Bootstrap primary blue */
            box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
        }
        
        /* Responsive table adjustments */
        @media (max-width: 768px) {
            .table-responsive {
                overflow-x: auto;
            }
        }
        
        /* Additional responsive styles for smaller screens (e.g., phones) */
        @media (max-width: 576px) {
            .modal-dialog {
                margin: 0.5rem; /* Reduce margin to make modal larger */
            }
            table th, table td {
                padding: 0.3rem;
                font-size: 0.8rem;
            }
            .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }
        }
        
        /* Visual cue for required form fields */
        .required-field::after {
            content: " *";
            color: red;
        }
        
        /* Styling for client-side validation error messages */
        .error-message {
            color: red;
            font-size: 0.8rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body id="page-top">
    <!-- JavaScript fallback warning: Displayed if JS is disabled -->
    <noscript>
        <div class="alert alert-warning text-center">
            Please enable JavaScript for the best experience on this page. Some features may not work correctly.
        </div>
    </noscript>

    <?php include 'nav.php'; // Include your navigation bar component ?>

    <div id="wrapper">
        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">
            <!-- Main Content Area -->
            <div id="content">
                <div class="container-fluid">
                    <!-- Page Heading and Add Guardian Button -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h3 mb-0 text-gray-800">GUARDIANS DASHBOARD</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGuardianModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                            Add Guardian
                        </button>
                    </div>

                    <!-- Status Messages (Success/Error from PHP redirects) -->
                    <?php if (isset($_GET['status'])): ?>
                        <?php if ($_GET['status'] === 'added'): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                Guardian added successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php elseif ($_GET['status'] === 'updated'): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                Guardian updated successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php elseif ($_GET['status'] === 'deleted'): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                Guardian deleted successfully!
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                        <?php 
                        // Remove status from URL to prevent re-showing alert on refresh
                        echo "<script>
                                if(window.history.replaceState) {
                                    const url = window.location.href.split('?')[0];
                                    window.history.replaceState({path: url}, '', url);
                                }
                              </script>";
                        ?>
                    <?php endif; ?>

                    <!-- Guardian List Table -->
                    <div class="bg-gray-50 rounded-xl p-4 shadow-md">
                        <div class="bg-gray-100 rounded-xl p-5 shadow-md">
                            <div class="overflow-x-auto">
                                <table class="w-full table-auto border border-gray-300 text-sm text-center">
                                    <thead class="bg-gray-200 font-semibold">
                                        <tr>
                                            <th class="border px-3 py-2">No.</th>
                                            <th class="border px-3 py-2">Student LRN</th>
                                            <th class="border px-3 py-2">Lastname</th>
                                            <th class="border px-3 py-2">Firstname</th>
                                            <th class="border px-3 py-2">Middlename</th>
                                            <th class="border px-3 py-2">Suffix</th>
                                            <th class="border px-3 py-2">Contact</th>
                                            <th class="border px-3 py-2">Relationship</th>
                                            <th class="border px-3 py-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $counter = 1;
                                        // Loop through guardians fetched from database
                                        while ($guardian = $guardians_result->fetch_assoc()): 
                                            // Apply highlight class if guardian was just added/updated
                                            $rowClass = (($guardian['guardian_id'] == $latest_id) && ($_GET['status'] == 'added' || $_GET['status'] == 'updated')) ? 'highlight-row' : '';
                                        ?>
                                        <tr class="hover:bg-gray-50 <?= $rowClass ?>">
                                            <td class="border px-3 py-2"><?= $counter++ ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($guardian['lrn'] ?? '') ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($guardian['lastname'] ?? '') ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($guardian['firstname'] ?? '') ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($guardian['middlename'] ?? '') ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($guardian['suffix'] ?? '') ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($guardian['contact'] ?? '') ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($guardian['relationship_to_student'] ?? '') ?></td>
                                            <td class="border px-3 py-2 space-x-2">
                                                <!-- Edit button (opens modal and populates data) -->
                                                <button onclick='openEditGuardianModal(<?= json_encode($guardian) ?>)' class="text-blue-600 hover:text-blue-800 p-1 rounded">
                                                    <i data-lucide="pencil" class="w-5 h-5 inline"></i>
                                                </button>
                                                <!-- Delete form (submits with POST) -->
                                                <form method="POST" class="inline delete-form" onsubmit="return confirm('Are you sure you want to delete <?= htmlspecialchars($guardian['firstname'].' '.$guardian['lastname']) ?>?');">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <input type="hidden" name="guardian_id" value="<?= htmlspecialchars($guardian['guardian_id'] ?? '') ?>">
                                                    <button type="submit" name="delete_guardian" class="text-red-600 hover:text-red-800 p-1 rounded">
                                                        <i data-lucide="trash" class="w-5 h-5 inline"></i>
                                                    </button>
                                                </form>                                                
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php if ($guardians_result->num_rows === 0): ?>
                                            <tr>
                                                <td colspan="9" class="border px-3 py-2 text-center text-gray-500">No guardians found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- End Guardian List Table -->
                </div>
            </div>
        </div>
    </div>

    <!-- ----------------------------
         Add Guardian Modal
         ---------------------------- -->
    <div class="modal fade" id="addGuardianModal" tabindex="-1" aria-labelledby="addGuardianModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="position: relative;">
                <div class="modal-header">
                    <h5 class="modal-title" id="addGuardianModalLabel">Add Guardian Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container mt-3">
                        <div class="row justify-content-center">
                            <div class="col-md-10">
                                <div class="card p-4 shadow-sm" style="border-radius: 20px; background-color: rgba(255,255,255,0.9);">
                                    <img src="images/you.png" width="100" height="100" alt="" class="rounded-circle mx-auto d-block"><br>
                                    <h3 class="text-center">ADD GUARDIAN</h3><br>
                                    <form id="addGuardianForm" method="post" action="guardians_list.php" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        
                                        <!-- Student LRN -->
                                        <div class="mb-3">
                                            <label for="guardian_lrn" class="form-label required-field">Student LRN</label>
                                            <input type="text" class="form-control" id="guardian_lrn" name="lrn" placeholder="Enter LRN" required 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                            <div class="error-message" id="lrn-error"></div>
                                        </div>
                                        
                                        <!-- Guardian Last Name -->
                                        <div class="mb-3">
                                            <label for="guardian_lastname" class="form-label required-field">Last Name</label>
                                            <input type="text" class="form-control" id="guardian_lastname" name="lastname" placeholder="Enter Last Name" required 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                            <div class="error-message" id="lastname-error"></div>
                                        </div>
                                        
                                        <!-- Guardian First Name -->
                                        <div class="mb-3">
                                            <label for="guardian_firstname" class="form-label required-field">First Name</label>
                                            <input type="text" class="form-control" id="guardian_firstname" name="firstname" placeholder="Enter First Name" required 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                            <div class="error-message" id="firstname-error"></div>
                                        </div>
                                        
                                        <!-- Guardian Middle Name -->
                                        <div class="mb-3">
                                            <label for="guardian_middlename" class="form-label">Middle Name</label>
                                            <input type="text" class="form-control" id="guardian_middlename" name="middlename" placeholder="Enter Middle Name" 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        
                                        <!-- Guardian Suffix -->
                                        <div class="mb-3">
                                            <label for="guardian_suffix" class="form-label">Suffix</label>
                                            <input type="text" class="form-control" id="guardian_suffix" name="suffix" placeholder="Enter Suffix (Jr., III, etc.)" 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        
                                        <!-- Guardian Contact -->
                                        <div class="mb-3">
                                            <label for="guardian_contact" class="form-label required-field">Contact Number</label>
                                            <input type="tel" class="form-control" id="guardian_contact" name="contact" placeholder="Enter Contact Number" required 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                            <div class="error-message" id="contact-error"></div>
                                        </div>
                                        
                                        <!-- Relationship to Student -->
                                        <div class="mb-3">
                                            <label for="relationship" class="form-label required-field">Relationship to Student</label>
                                            <input type="text" class="form-control" id="relationship" name="relationship_to_student" placeholder="e.g., Mother, Father, Aunt, Uncle" required 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                            <div class="error-message" id="relationship-error"></div>
                                        </div>
                                        
                                        <div class="text-center">
                                            <button type="submit" id="addGuardianBtn" name="add_guardian" class="btn btn-primary">Register Guardian</button>
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

    <!-- ----------------------------
         Edit Guardian Modal
         ---------------------------- -->
    <div class="modal fade" id="editGuardianModal" tabindex="-1" aria-labelledby="editGuardianModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="position: relative;">
                <div class="modal-header">
                    <h5 class="modal-title" id="editGuardianModalLabel">Edit Guardian Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container mt-3">
                        <div class="row justify-content-center">
                            <div class="col-md-10">
                                <div class="card p-4 shadow-sm" style="border-radius: 20px; background-color: rgba(255,255,255,0.9);">
                                    <img src="images/you.png" width="100" height="100" alt="" class="rounded-circle mx-auto d-block"><br>
                                    <h3 class="text-center">EDIT GUARDIAN</h3><br>
                                    <form id="editGuardianForm" method="post" action="guardians_list.php" novalidate>
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <!-- Hidden field to store guardian_id -->
                                        <input type="hidden" id="edit_guardian_id" name="edit_guardian_id">
                                        
                                        <!-- Student LRN -->
                                        <div class="mb-3">
                                            <label for="edit_guardian_lrn" class="form-label required-field">Student LRN</label>
                                            <input type="text" class="form-control" id="edit_guardian_lrn" name="lrn" placeholder="Enter LRN" required 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                            <div class="error-message" id="edit-lrn-error"></div>
                                        </div>
                                        
                                        <!-- Guardian Last Name -->
                                        <div class="mb-3">
                                            <label for="edit_guardian_lastname" class="form-label required-field">Last Name</label>
                                            <input type="text" class="form-control" id="edit_guardian_lastname" name="lastname" placeholder="Enter Last Name" required 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                            <div class="error-message" id="edit-lastname-error"></div>
                                        </div>
                                        
                                        <!-- Guardian First Name -->
                                        <div class="mb-3">
                                            <label for="edit_guardian_firstname" class="form-label required-field">First Name</label>
                                            <input type="text" class="form-control" id="edit_guardian_firstname" name="firstname" placeholder="Enter First Name" required 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                            <div class="error-message" id="edit-firstname-error"></div>
                                        </div>
                                        
                                        <!-- Guardian Middle Name -->
                                        <div class="mb-3">
                                            <label for="edit_guardian_middlename" class="form-label">Middle Name</label>
                                            <input type="text" class="form-control" id="edit_guardian_middlename" name="middlename" placeholder="Enter Middle Name" 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        
                                        <!-- Guardian Suffix -->
                                        <div class="mb-3">
                                            <label for="edit_guardian_suffix" class="form-label">Suffix</label>
                                            <input type="text" class="form-control" id="edit_guardian_suffix" name="suffix" placeholder="Enter Suffix (Jr., III, etc.)" 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        
                                        <!-- Guardian Contact -->
                                        <div class="mb-3">
                                            <label for="edit_guardian_contact" class="form-label required-field">Contact Number</label>
                                            <input type="tel" class="form-control" id="edit_guardian_contact" name="contact" placeholder="Enter Contact Number" required 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                            <div class="error-message" id="edit-contact-error"></div>
                                        </div>
                                        
                                        <!-- Relationship to Student -->
                                        <div class="mb-3">
                                            <label for="edit_relationship" class="form-label required-field">Relationship to Student</label>
                                            <input type="text" class="form-control" id="edit_relationship" name="relationship_to_student" placeholder="e.g., Mother, Father, Aunt, Uncle" required 
                                                   style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                            <div class="error-message" id="edit-relationship-error"></div>
                                        </div>
                                        
                                        <div class="text-center">
                                            <button type="submit" id="editGuardianBtn" name="edit_guardian" class="btn btn-primary">Update Guardian</button>
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
                <span>&copy; Your Website <?= date('Y') ?></span>
            </div>
        </div>
    </footer>
    <a class="scroll-to-top rounded" href="#page-top">
         <i class="fas fa-angle-up"></i>
    </a>
</body>
</html>