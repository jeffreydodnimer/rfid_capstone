<?php
$conn = new mysqli('localhost', 'root', '', 'capstone');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function isValidName($name) {
    return preg_match("/^[A-Z][a-zA-Z\-']+, [A-Z][a-zA-Z\-']+( [A-Z][a-zA-Z\-']+)?$/", $name);
}

function isValidLRN($lrn) {
    return preg_match("/^\d{10}$/", $lrn);
}

if (isset($_POST['add_student'])) {
    $name = trim($_POST['student_name']);
    $lrn = trim($_POST['lrn']);

    if (!isValidName($name)) die("Invalid name format. Use: Lastname, Firstname Middlename");
    if (!isValidLRN($lrn)) die("LRN must be exactly 10 digits.");

    $stmt = $conn->prepare("INSERT INTO grade_10A (student_name, birthday, age, parent_contact, lrn) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiss", $name, $_POST['birthday'], $_POST['age'], $_POST['parent_contact'], $lrn);
    $stmt->execute();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['edit_student'])) {
    $name = trim($_POST['student_name']);
    $lrn = trim($_POST['lrn']);

    if (!isValidName($name)) die("Invalid name format. Use: Lastname, Firstname Middlename");
    if (!isValidLRN($lrn)) die("LRN must be exactly 10 digits.");

    $stmt = $conn->prepare("UPDATE grade_10A SET student_name=?, birthday=?, age=?, parent_contact=?, lrn=? WHERE id=?");
    $stmt->bind_param("ssissi", $name, $_POST['birthday'], $_POST['age'], $_POST['parent_contact'], $lrn, $_POST['student_id']);
    $stmt->execute();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['delete_student'])) {
    $stmt = $conn->prepare("DELETE FROM grade_10A WHERE id=?");
    $stmt->bind_param("i", $_POST['student_id']);
    $stmt->execute();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

$students = $conn->query("SELECT * FROM grade_10A ORDER BY student_name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Grade 10 - Section A</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
    function openAddModal() {
        document.getElementById("addModal").classList.remove("hidden");
        document.querySelectorAll("#addModal input").forEach(input => input.value = "");
    }
    function closeAddModal() {
        document.getElementById("addModal").classList.add("hidden");
    }
    function openEditModal(student) {
        document.getElementById("editModal").classList.remove("hidden");
        document.getElementById("edit_id").value = student.id;
        document.getElementById("edit_name").value = student.name;
        document.getElementById("edit_birthday").value = student.birthday;
        document.getElementById("edit_age").value = student.age;
        document.getElementById("edit_contact").value = student.contact;
        document.getElementById("edit_lrn").value = student.lrn;
    }
    function closeEditModal() {
        document.getElementById("editModal").classList.add("hidden");
    }
    function calculateAge(input, outputId) {
        const birthday = new Date(input.value);
        const today = new Date();
        let age = today.getFullYear() - birthday.getFullYear();
        const m = today.getMonth() - birthday.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthday.getDate())) {
            age--;
        }
        document.getElementById(outputId).value = age;
    }
    </script>
</head>
<body class="bg-gray-100 p-6">

<h1 class="text-4xl font-bold text-center mb-6">Grade 10 - Section A</h1>

<div class="max-w-6xl mx-auto bg-white p-4 rounded shadow">
    <div class="flex justify-between items-center mb-4 px-2">
        <a href="admin_dashboard.php" class="bg-blue-600 text-white px-4 py-2 rounded inline-flex items-center gap-2">
            ← Back
        </a>
        <button onclick="openAddModal()" class="bg-green-600 text-white px-4 py-2 rounded inline-flex items-center gap-2">
            <i data-lucide="plus-circle" class="w-5 h-5"></i>Student
        </button>
    </div>

    <table class="w-full table-auto border border-gray-300 text-sm text-center">
        <thead class="bg-gray-200 font-semibold">
            <tr>
                <th class="border px-3 py-2">No.</th>
                <th class="border px-3 py-2">Name</th>
                <th class="border px-3 py-2">Birthday</th>
                <th class="border px-3 py-2">Age</th>
                <th class="border px-3 py-2">Parent Contact</th>
                <th class="border px-3 py-2">LRN</th>
                <th class="border px-3 py-2">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $counter = 1;
            while ($row = $students->fetch_assoc()): ?>
            <tr>
                <td class="border px-3 py-2"><?= $counter++ ?></td>
                <td class="border px-3 py-2"><?= htmlspecialchars($row['student_name']) ?></td>
                <td class="border px-3 py-2"><?= $row['birthday'] ?></td>
                <td class="border px-3 py-2"><?= $row['age'] ?></td>
                <td class="border px-3 py-2"><?= htmlspecialchars($row['parent_contact']) ?></td>
                <td class="border px-3 py-2"><?= htmlspecialchars($row['lrn']) ?></td>
                <td class="border px-3 py-2 space-x-2">
                    <button onclick='openEditModal(<?= json_encode([
                        'id' => $row['id'],
                        'name' => $row['student_name'],
                        'birthday' => $row['birthday'],
                        'age' => $row['age'],
                        'contact' => $row['parent_contact'],
                        'lrn' => $row['lrn']
                    ]) ?>)' class="text-blue-600 hover:text-blue-800">
                        <i data-lucide="pencil" class="w-5 h-5 inline"></i>
                    </button>
                    <form method="POST" class="inline">
                        <input type="hidden" name="student_id" value="<?= $row['id'] ?>" />
                        <button type="submit" name="delete_student" onclick="return confirm('Delete this student?');" class="text-red-600 hover:text-red-800">
                            <i data-lucide="trash" class="w-5 h-5 inline"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- Add Modal -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded shadow-lg w-full max-w-lg">
        <h2 class="text-xl font-semibold mb-4">Add Student</h2>
        <form method="POST" autocomplete="off" class="grid grid-cols-1 gap-4">
            <input name="student_name" placeholder="Lastname, Firstname Middlename" pattern="^[A-Z][a-zA-Z\-']+, [A-Z][a-zA-Z\-']+( [A-Z][a-zA-Z\-']+)?$" class="border p-2 rounded" required />
            <input type="date" name="birthday" class="border p-2 rounded" onchange="calculateAge(this, 'add_age')" required />
            <input type="number" name="age" id="add_age" placeholder="Age" class="border p-2 rounded" required readonly />
            <input name="parent_contact" placeholder="09XXXXXXXXX" pattern="^09\d{9}$" maxlength="11" class="border p-2 rounded" required />
            <input name="lrn" placeholder="10-digit LRN" pattern="^\d{10}$" maxlength="10" class="border p-2 rounded" required />
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeAddModal()" class="px-4 py-2 border rounded">Cancel</button>
                <button type="submit" name="add_student" class="bg-green-600 text-white px-4 py-2 rounded">Add</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded shadow-lg w-full max-w-lg">
        <h2 class="text-xl font-semibold mb-4">Edit Student</h2>
        <form method="POST" class="grid grid-cols-1 gap-4">
            <input type="hidden" name="student_id" id="edit_id" />
            <input name="student_name" id="edit_name" pattern="^[A-Z][a-zA-Z\-']+, [A-Z][a-zA-Z\-']+( [A-Z][a-zA-Z\-']+)?$" class="border p-2 rounded" required />
            <input type="date" name="birthday" id="edit_birthday" class="border p-2 rounded" onchange="calculateAge(this, 'edit_age')" required />
            <input type="number" name="age" id="edit_age" class="border p-2 rounded" required readonly />
            <input name="parent_contact" id="edit_contact" pattern="^09\d{9}$" maxlength="11" class="border p-2 rounded" required />
            <input name="lrn" id="edit_lrn" pattern="^\d{10}$" maxlength="10" class="border p-2 rounded" required />
            <div class="flex justify-end gap-2">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 border rounded">Cancel</button>
                <button type="submit" name="edit_student" class="bg-blue-600 text-white px-4 py-2 rounded">Update</button>
            </div>
        </form>
    </div>
</div>

<script>lucide.createIcons();</script>
</body>
</html>

<?php $conn->close(); ?>
