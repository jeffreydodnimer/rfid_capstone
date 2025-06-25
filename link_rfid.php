<?php
session_start();  // Must be at the VERY TOP
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

include 'conn.php';

// Handle Link RFID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_rfid'])) {
    $rfid_number = htmlspecialchars($_POST['rfid_number'], ENT_QUOTES, 'UTF-8');
    $lrn = htmlspecialchars($_POST['lrn'], ENT_QUOTES, 'UTF-8');

    // Check if connection is valid
    if (!$conn) {
        echo "<script>alert('Database connection failed for linking RFID.'); window.location.href='link_rfid.php';</script>";
        exit();
    }

    // Start a transaction to ensure data consistency
    $conn->begin_transaction();

    try {
        // First check if the LRN exists in the students table
        $check_stmt = $conn->prepare("SELECT lrn FROM students WHERE lrn = ?");
        if ($check_stmt === false) {
            throw new Exception("Error preparing check statement: " . $conn->error);
        }
        $check_stmt->bind_param("s", $lrn);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows === 0) {
            throw new Exception("Student with LRN $lrn does not exist.");
        }
        $check_stmt->close();
        
        // Check if RFID already exists
        $check_rfid_stmt = $conn->prepare("SELECT rfid_number FROM rfid WHERE rfid_number = ?");
        if ($check_rfid_stmt === false) {
            throw new Exception("Error preparing RFID check statement: " . $conn->error);
        } 
        $check_rfid_stmt->bind_param("s", $rfid_number);
        $check_rfid_stmt->execute();
        $check_rfid_result = $check_rfid_stmt->get_result();
        if ($check_rfid_result->num_rows > 0) {
            throw new Exception("RFID number already in use.");
        }
        $check_rfid_stmt->close();

        // NEW: Check if LRN is already used in the RFID table
        $check_lrn_stmt = $conn->prepare("SELECT lrn FROM rfid WHERE lrn = ?");
        if ($check_lrn_stmt === false) {
            throw new Exception("Error preparing LRN check statement: " . $conn->error);
        }
        $check_lrn_stmt->bind_param("s", $lrn);
        $check_lrn_stmt->execute();
        $check_lrn_result = $check_lrn_stmt->get_result();
        if ($check_lrn_result->num_rows > 0) {
            throw new Exception("LRN already use.");
        }
        $check_lrn_stmt->close();

        // Insert the RFID record
        $stmt = $conn->prepare("INSERT INTO rfid (rfid_number, lrn) VALUES (?, ?)");
        if ($stmt === false) {
            throw new Exception("Error preparing insert statement: " . $conn->error);
        }
        $stmt->bind_param("ss", $rfid_number, $lrn);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();

        // Commit the transaction
        $conn->commit();
        header("Location: link_rfid.php?status=linked");
        exit();
    } catch (Exception $e) {
        // Roll back the transaction if an error occurs
        $conn->rollback();
        echo "<script>alert('Error linking RFID: " . $e->getMessage() . "'); window.location.href='link_rfid.php';</script>";
        exit();
    }
}

// Handle Unlink/Delete RFID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlink_rfid'])) {
    $rfid_number = $_POST['rfid_number'];
    
    if (!$conn) {
        echo "<script>alert('Database connection failed for unlinking RFID.'); window.location.href='link_rfid.php';</script>";
        exit();
    }

    // Start a transaction
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM rfid WHERE rfid_number = ?");
        if ($stmt === false) {
            throw new Exception("Error preparing delete statement: " . $conn->error);
        }
        $stmt->bind_param("s", $rfid_number);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        // Commit the transaction
        $conn->commit();
        header("Location: link_rfid.php?status=unlinked");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error unlinking RFID: " . $e->getMessage() . "'); window.location.href='link_rfid.php';</script>";
        exit();
    }
}

// Handle Update RFID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rfid'])) {
    $original_rfid = $_POST['original_rfid'];
    $rfid_number = htmlspecialchars($_POST['edit_rfid_number'], ENT_QUOTES, 'UTF-8');
    $lrn = htmlspecialchars($_POST['edit_lrn'], ENT_QUOTES, 'UTF-8');

    if (!$conn) {
        echo "<script>alert('Database connection failed for updating RFID.'); window.location.href='link_rfid.php';</script>";
        exit();
    }
    
    // Start a transaction
    $conn->begin_transaction();
    try {
        // Check if the LRN exists in students table
        $check_stmt = $conn->prepare("SELECT lrn FROM students WHERE lrn = ?");
        if ($check_stmt === false) {
            throw new Exception("Error preparing check statement: " . $conn->error);
        }
        $check_stmt->bind_param("s", $lrn);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows === 0) {
            throw new Exception("Student with LRN $lrn does not exist.");
        }
        $check_stmt->close();
        
        // Check if the new RFID already exists (if it's different from the original)
        if ($original_rfid != $rfid_number) {
            $check_rfid_stmt = $conn->prepare("SELECT rfid_number FROM rfid WHERE rfid_number = ?");
            if ($check_rfid_stmt === false) {
                throw new Exception("Error preparing RFID check statement: " . $conn->error);
            }
            $check_rfid_stmt->bind_param("s", $rfid_number);
            $check_rfid_stmt->execute();
            $check_rfid_result = $check_rfid_stmt->get_result();
            if ($check_rfid_result->num_rows > 0) {
                throw new Exception("New RFID number already in use.");
            }
            $check_rfid_stmt->close();
        }

        // NEW for update: If the LRN is changed (i.e. different from what is currently linked to the original RFID)
        // Check if that new LRN is already used.
        if ($original_rfid != $rfid_number) {  // You can adjust this condition as needed.
            $check_lrn_stmt = $conn->prepare("SELECT lrn FROM rfid WHERE lrn = ?");
            if ($check_lrn_stmt === false) {
                throw new Exception("Error preparing LRN check statement: " . $conn->error);
            }
            $check_lrn_stmt->bind_param("s", $lrn);
            $check_lrn_stmt->execute();
            $check_lrn_result = $check_lrn_stmt->get_result();
            if ($check_lrn_result->num_rows > 0) {
                throw new Exception("LRN already use.");
            }
            $check_lrn_stmt->close();
        }

        // Update the RFID record
        $stmt = $conn->prepare("UPDATE rfid SET rfid_number=?, lrn=? WHERE rfid_number=?");
        if ($stmt === false) {
            throw new Exception("Error preparing update statement: " . $conn->error);
        }
        $stmt->bind_param("sss", $rfid_number, $lrn, $original_rfid);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        // Commit the transaction
        $conn->commit();
        header("Location: link_rfid.php?status=updated");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error updating RFID: " . $e->getMessage() . "'); window.location.href='link_rfid.php';</script>";
        exit();
    }
}

// --- Fetch RFID records for Display (runs every time the page loads) ---
if (!$conn) {
    die("Database connection failed during RFID list retrieval.");
}

$rfid_result = $conn->query("
    SELECT r.rfid_number, r.lrn, s.lastname, s.firstname, s.middlename 
    FROM rfid r
    LEFT JOIN students s ON r.lrn = s.lrn
    ORDER BY s.lastname ASC
"); 

if (!$rfid_result) {
    die("Error fetching RFID records: " . $conn->error);
}

$students_result = $conn->query("SELECT lrn, lastname, firstname, middlename FROM students ORDER BY lastname ASC");
if (!$students_result) {
    die("Error fetching students: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>RFID Management</title>
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

            // Opens the Edit Modal and fills the fields with RFID data
            window.openEditModal = function(rfid) {
                $('#editModal').modal('show');
                $('#original_rfid').val(rfid.rfid_number);
                $('#edit_rfid_number').val(rfid.rfid_number);
                $('#edit_lrn').val(rfid.lrn);
            }

            // Check for status messages in URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            if (status) {
                let message = "";
                if (status === "linked") {
                    message = "RFID linked successfully!";
                } else if (status === "unlinked") {
                    message = "RFID unlinked successfully!";
                } else if (status === "updated") {
                    message = "RFID updated successfully!";
                }
                if (message) {
                    alert(message);
                    window.history.replaceState({}, document.title, "link_rfid.php");
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
                        <h2 class="h3 mb-0 text-gray-800">RFID MANAGEMENT</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRfidModal"
                            style="box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);">Link New RFID</button>
                    </div>

                    <div class="bg-gray-50 rounded-xl p-4 shadow-md">
                        <div class="bg-gray-100 rounded-xl p-5 shadow-md">
                            <div class="overflow-x-auto">
                                <table class="w-full table-auto border border-gray-300 text-sm text-center">
                                    <thead class="bg-gray-200 font-semibold">
                                        <tr>
                                            <th class="border px-3 py-2">No.</th>
                                            <th class="border px-3 py-2">RFID Number</th>
                                            <th class="border px-3 py-2">LRN</th>
                                            <th class="border px-3 py-2">Student Name</th>
                                            <th class="border px-3 py-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $counter = 1;
                                        while ($row = $rfid_result->fetch_assoc()): 
                                            $fullname = $row['lastname'] . ', ' . $row['firstname'] . ' ' . $row['middlename'];
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="border px-3 py-2"><?= $counter++ ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($row['rfid_number'] ?? '') ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($row['lrn'] ?? '') ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($fullname ?? '') ?></td>
                                            <td class="border px-3 py-2 space-x-2">
                                                <button onclick='openEditModal(<?= json_encode(['rfid_number' => $row['rfid_number'], 'lrn' => $row['lrn']]) ?>)'
                                                    class="text-blue-600 hover:text-blue-800 p-1 rounded">
                                                    <i data-lucide="pencil" class="w-5 h-5 inline"></i>
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to unlink this RFID from <?= htmlspecialchars($fullname) ?>?');">
                                                    <input type="hidden" name="rfid_number" value="<?= htmlspecialchars($row['rfid_number'] ?? '') ?>" />
                                                    <button type="submit" name="unlink_rfid" class="text-red-600 hover:text-red-800 p-1 rounded">
                                                        <i data-lucide="unlink" class="w-5 h-5 inline"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php if ($rfid_result->num_rows === 0): ?>
                                            <tr>
                                                <td colspan="5" class="border px-3 py-2 text-center text-gray-500">No RFID records found.</td>
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

    <!-- Add RFID Modal -->
    <div class="modal fade" id="addRfidModal" tabindex="-1" aria-labelledby="addRfidModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="position: relative;">
                <div class="modal-header">
                    <h5 class="modal-title" id="addRfidModalLabel">Link RFID to Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container mt-3">
                        <div class="row justify-content-center">
                            <div class="col-md-10">
                                <div class="card p-4 shadow-sm" style="border-radius: 20px; background-color: rgba(255, 255, 255, 0.9);">
                                    <img src="images/rfid.png" width="100" height="100" alt="" class="rounded-circle mx-auto d-block"><br>
                                    <h3 class="text-center">LINK RFID</h3><br>
                                    <form method="post">
                                        <div class="mb-3">
                                            <input type="text" class="form-control" name="rfid_number" placeholder="Enter RFID Number" required
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <select class="form-control" name="lrn" required
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                                <option value="">Select Student (LRN)</option>
                                                <?php 
                                                // Reset pointer to beginning of result set
                                                $students_result->data_seek(0);
                                                while ($student = $students_result->fetch_assoc()): 
                                                    $student_name = $student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename'];
                                                ?>
                                                <option value="<?= htmlspecialchars($student['lrn']) ?>">
                                                    <?= htmlspecialchars($student['lrn'] . ' - ' . $student_name) ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="text-center">
                                            <button type="submit" name="link_rfid" class="btn btn-primary px-4">Link RFID</button>
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

    <!-- Edit RFID Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="position: relative;">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit RFID Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="container mt-3">
                        <div class="row justify-content-center">
                            <div class="col-md-10">
                                <div class="card p-4 shadow-sm" style="border-radius: 20px; background-color: rgba(255, 255, 255, 0.9);">
                                    <img src="images/rfid.png" width="100" height="100" alt="" class="rounded-circle mx-auto d-block"><br>
                                    <h3 class="text-center">EDIT RFID</h3><br>
                                    <form method="post">
                                        <input type="hidden" id="original_rfid" name="original_rfid">
                                        <div class="mb-3">
                                            <input type="text" class="form-control" id="edit_rfid_number" name="edit_rfid_number" placeholder="Enter RFID Number" required
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                        </div>
                                        <div class="mb-3">
                                            <select class="form-control" id="edit_lrn" name="edit_lrn" required
                                                style="border: 1px solid #ccc; box-shadow: 2px 4px 8px rgba(0,0,0,0.1); border-radius: 15px; text-align: center;">
                                                <option value="">Select Student (LRN)</option>
                                                <?php 
                                                // Reset pointer to beginning of result set
                                                $students_result->data_seek(0);
                                                while ($student = $students_result->fetch_assoc()): 
                                                    $student_name = $student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename'];
                                                ?>
                                                <option value="<?= htmlspecialchars($student['lrn']) ?>">
                                                    <?= htmlspecialchars($student['lrn'] . ' - ' . $student_name) ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="text-center">
                                            <button type="submit" name="update_rfid" class="btn btn-primary px-4">Update RFID</button>
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