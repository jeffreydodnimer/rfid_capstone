<?php
session_start();
if (!isset($_SESSION['email'])) {
    // Redirect unauthorized users to login or students_dashboard (adjust as needed)
    header("Location: students_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Students Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-sans min-h-screen p-6">

  <!-- Back Button -->
  <div class="mb-6">
    <a href="dashboard.php" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
      ← Back to Admin Dashboard
    </a>
  </div>

  <h1 class="text-3xl font-bold text-gray-800 mb-10 text-center">Students Dashboard</h1>

  <!-- Junior High School Section -->
  <section class="mb-12">
    <h2 class="text-2xl font-semibold text-gray-700 mb-6">Junior High School</h2>

    <?php
    // Color classes for each grade
    $gradeColors = [
        7 => 'bg-yellow-100 hover:bg-yellow-200',
        8 => 'bg-green-100 hover:bg-green-200',
        9 => 'bg-blue-100 hover:bg-blue-200',
        10 => 'bg-purple-100 hover:bg-purple-200'
    ];
    ?>

    <!-- Grade A Sections -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 mb-6">
      <?php
      for ($grade = 7; $grade <= 10; $grade++) {
          $section = 'A';
          $color = $gradeColors[$grade];
          echo "
          <a href='students/section.php?level=$grade&section=$section' class='block $color p-5 rounded-lg shadow hover:shadow-md transition'>
              <div class='text-lg font-bold text-gray-800'>Grade $grade - Section $section</div>
              <div class='text-sm text-gray-600 mt-1'>View Students</div>
          </a>";
      }
      ?>
    </div>

    <!-- Grade B Sections -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
      <?php
      for ($grade = 7; $grade <= 10; $grade++) {
          $section = 'B';
          $color = $gradeColors[$grade];
          echo "
          <a href='students/section.php?level=$grade&section=$section' class='block $color p-5 rounded-lg shadow hover:shadow-md transition'>
              <div class='text-lg font-bold text-gray-800'>Grade $grade - Section $section</div>
              <div class='text-sm text-gray-600 mt-1'>View Students</div>
          </a>";
      }
      ?>
    </div>
  </section>

  <!-- Senior High School Section -->
  <section>
    <h2 class="text-2xl font-semibold text-gray-700 mb-6">Senior High School</h2>

    <?php
    // Color classes for each strand
    $strandColors = [
        'HUMMS' => 'bg-pink-100 hover:bg-pink-200',
        'TVL'   => 'bg-red-100 hover:bg-red-200',
        'GAS'   => 'bg-indigo-100 hover:bg-indigo-200'
    ];
    $strands = ['HUMMS', 'TVL', 'GAS'];
    ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
      <?php
      foreach ($strands as $strand) {
          $color = $strandColors[$strand];
          echo "
          <a href='students/strand.php?strand=$strand' class='block $color p-5 rounded-lg shadow hover:shadow-md transition'>
              <div class='text-lg font-bold text-gray-800'>$strand</div>
              <div class='text-sm text-gray-600 mt-1'>View Students</div>
          </a>";
      }
      ?>
    </div>
  </section>

</body>
</html>
