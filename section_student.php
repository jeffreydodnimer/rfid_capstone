<?php
session_start(); // Must be at the VERY TOP
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

include 'conn.php';
if (!$conn) {
    die("Database connection failed.");
}

/**
 * Handle Add Section
 */
if (isset($_POST['add_section'])) {
    $section_name = htmlspecialchars(trim($_POST['section_name']), ENT_QUOTES, 'UTF-8');
    $grade_level = htmlspecialchars($_POST['grade_level'], ENT_QUOTES, 'UTF-8');
    $adviser_id = filter_var($_POST['adviser_id'], FILTER_VALIDATE_INT);

    if (empty($section_name) || empty($grade_level) || $adviser_id === false) {
        echo "<script>alert('All fields are required.'); window.location.href='sections.php';</script>";
        exit();
    }

    // Check for duplicate section name within the same grade level
    $dup_stmt = $conn->prepare("SELECT section_id FROM sections WHERE section_name = ? AND grade_level = ?");
    $dup_stmt->bind_param("ss", $section_name, $grade_level);
    $dup_stmt->execute();
    if ($dup_stmt->get_result()->num_rows > 0) {
        echo "<script>alert('Error: A section with this name already exists for this grade level.'); window.location.href='sections.php';</script>";
        exit();
    }
    $dup_stmt->close();

    $stmt = $conn->prepare("INSERT INTO sections (section_name, grade_level, adviser_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $section_name, $grade_level, $adviser_id);
    if ($stmt->execute()) {
        header("Location: sections.php?status=added");
    } else {
        echo "<script>alert('Error adding section.'); window.location.href='sections.php';</script>";
    }
    exit();
}

/**
 * Handle Edit Section
 */
if (isset($_POST['edit_section'])) {
    $section_id = filter_var($_POST['edit_section_id'], FILTER_VALIDATE_INT);
    $section_name = htmlspecialchars(trim($_POST['edit_section_name']), ENT_QUOTES, 'UTF-8');
    $grade_level = htmlspecialchars($_POST['edit_grade_level'], ENT_QUOTES, 'UTF-8');
    $adviser_id = filter_var($_POST['edit_adviser_id'], FILTER_VALIDATE_INT);

    if (!$section_id || empty($section_name) || empty($grade_level) || $adviser_id === false) {
        echo "<script>alert('All fields are required for editing.'); window.location.href='sections.php';</script>";
        exit();
    }
    
    // Check for duplicate, excluding the current section being edited
    $dup_stmt = $conn->prepare("SELECT section_id FROM sections WHERE section_name = ? AND grade_level = ? AND section_id != ?");
    $dup_stmt->bind_param("ssi", $section_name, $grade_level, $section_id);
    $dup_stmt->execute();
    if ($dup_stmt->get_result()->num_rows > 0) {
        echo "<script>alert('Error: Another section with this name already exists for this grade level.'); window.location.href='sections.php';</script>";
        exit();
    }
    $dup_stmt->close();

    $stmt = $conn->prepare("UPDATE sections SET section_name=?, grade_level=?, adviser_id=? WHERE section_id=?");
    $stmt->bind_param("ssii", $section_name, $grade_level, $adviser_id, $section_id);
    if ($stmt->execute()) {
        header("Location: sections.php?status=updated");
    } else {
        echo "<script>alert('Error updating section.'); window.location.href='sections.php';</script>";
    }
    exit();
}

/**
 * Handle Delete Section
 */
if (isset($_POST['delete_section'])) {
    $section_id = filter_var($_POST['section_id'], FILTER_VALIDATE_INT);
    if ($section_id) {
        // Optional: Check if any students are enrolled in this section before deleting
        // $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM enrollments WHERE section_id = ?"); ...
        
        $stmt = $conn->prepare("DELETE FROM sections WHERE section_id = ?");
        $stmt->bind_param("i", $section_id);
        if ($stmt->execute()) {
            header("Location: sections.php?status=deleted");
        } else {
            echo "<script>alert('Error: Could not delete section. It might be in use by enrolled students.'); window.location.href='sections.php';</script>";
        }
        exit();
    }
}

// Fetch data for the page
$available_grade_levels = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];

$advisers_result = $conn->query("SELECT adviser_id, CONCAT_WS(' ', firstname, lastname) AS adviser_fullname FROM advisers ORDER BY lastname, firstname");
$all_advisers = $advisers_result ? $advisers_result->fetch_all(MYSQLI_ASSOC) : [];

$sections_query = "
    SELECT s.section_id, s.section_name, s.grade_level, s.adviser_id, CONCAT_WS(' ', adv.firstname, adv.lastname) AS adviser_fullname
    FROM sections s
    LEFT JOIN advisers adv ON s.adviser_id = adv.adviser_id
    ORDER BY s.grade_level, s.section_name";
$sections_result = $conn->query($sections_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Manage Sections</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
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
                <div class="container-fluid mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="h3 mb-0 text-gray-800">MANAGE SECTIONS</h2>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">Add New Section</button>
                    </div>

                    <?php if (isset($_GET['status'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Section <?= htmlspecialchars($_GET['status']) ?> successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <div class="bg-gray-100 rounded-xl p-5 shadow-md">
                        <div class="overflow-x-auto">
                            <table class="w-full table-auto border border-gray-300 text-sm text-center">
                                <thead class="bg-gray-200 font-semibold">
                                    <tr>
                                        <th>No.</th><th>Section Name</th><th>Grade Level</th><th>Adviser</th><th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($sections_result && $sections_result->num_rows > 0): ?>
                                        <?php $counter = 1; while ($row = $sections_result->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="border px-3 py-2"><?= $counter++ ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($row['section_name']) ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($row['grade_level']) ?></td>
                                            <td class="border px-3 py-2"><?= htmlspecialchars($row['adviser_fullname'] ?? 'N/A') ?></td>
                                            <td class="border px-3 py-2 space-x-2">
                                                <button onclick='openEditModal(<?= json_encode($row) ?>)' class="text-blue-600 hover:text-blue-800 p-1 rounded">
                                                    <i data-lucide="pencil" class="w-5 h-5 inline"></i>
                                                </button>
                                                <form method="POST" class="inline" onsubmit="return confirm('Delete section <?= htmlspecialchars($row['section_name']) ?>? This cannot be undone.');">
                                                    <input type="hidden" name="section_id" value="<?= $row['section_id'] ?>" />
                                                    <button type="submit" name="delete_section" class="text-red-600 hover:text-red-800 p-1 rounded">
                                                        <i data-lucide="trash-2" class="w-5 h-5 inline"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="5" class="border px-3 py-2 text-center text-gray-500">No sections found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'footer.php'; ?>
        </div>
    </div>

    <!-- Add Section Modal -->
    <div class="modal fade" id="addSectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header"><h5 class="modal-title">Add New Section</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Section Name</label>
                            <input type="text" class="form-control" name="section_name" placeholder="e.g., Courage, Honesty" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Grade Level</label>
                            <select class="form-control" name="grade_level" required>
                                <option value="">Select Grade Level</option>
                                <?php foreach ($available_grade_levels as $grade): ?>
                                    <option value="<?= htmlspecialchars($grade) ?>"><?= htmlspecialchars($grade) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Adviser</label>
                            <select class="form-control" name="adviser_id" required>
                                <option value="">Select an Adviser</option>
                                <?php foreach ($all_advisers as $adviser): ?>
                                    <option value="<?= $adviser['adviser_id'] ?>"><?= htmlspecialchars($adviser['adviser_fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="add_section" class="btn btn-primary">Add Section</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Section Modal -->
    <div class="modal fade" id="editSectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" id="edit_section_id" name="edit_section_id">
                    <div class="modal-header"><h5 class="modal-title">Edit Section</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Section Name</label>
                            <input type="text" class="form-control" id="edit_section_name" name="edit_section_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Grade Level</label>
                            <select class="form-control" id="edit_grade_level" name="edit_grade_level" required>
                                <?php foreach ($available_grade_levels as $grade): ?>
                                    <option value="<?= htmlspecialchars($grade) ?>"><?= htmlspecialchars($grade) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Adviser</label>
                            <select class="form-control" id="edit_adviser_id" name="edit_adviser_id" required>
                                <?php foreach ($all_advisers as $adviser): ?>
                                    <option value="<?= $adviser['adviser_id'] ?>"><?= htmlspecialchars($adviser['adviser_fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="edit_section" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function openEditModal(data) {
            document.getElementById('edit_section_id').value = data.section_id;
            document.getElementById('edit_section_name').value = data.section_name;
            document.getElementById('edit_grade_level').value = data.grade_level;
            document.getElementById('edit_adviser_id').value = data.adviser_id;

            var editModal = new bootstrap.Modal(document.getElementById('editSectionModal'));
            editModal.show();
        }
    </script>
</body>
</html>