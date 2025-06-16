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
    <a href="admin_dashboard.php" class="inline-block bg-red-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
      ← Back
    </a>
  </div>

  <h1 class="text-3xl font-bold text-gray-800 mb-10 text-center">Students Reports</h1>

  <!-- Junior High School Section -->
  <section class="mb-12">
    <h2 class="text-2xl font-semibold text-gray-700 mb-6">Junior High School</h2>

    <!-- Grade A Sections -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6 mb-6">
      <a href="sf2_template7A.php" class="block bg-yellow-200 hover:bg-yellow-300 p-5 rounded-lg shadow hover:shadow-md transition">
        <div class="text-lg font-bold text-gray-800">Grade 7 - Section A</div>
        <div class="text-sm text-gray-700 mt-1">View Students</div>
      </a>
      <a href="students/section.php?level=8&section=A" class="block bg-green-300 hover:bg-green-400 p-5 rounded-lg shadow hover:shadow-md transition">
        <div class="text-lg font-bold text-gray-800">Grade 8 - Section A</div>
        <div class="text-sm text-gray-700 mt-1">View Students</div>
      </a>
      <a href="students/section.php?level=9&section=A" class="block bg-blue-300 hover:bg-blue-400 p-5 rounded-lg shadow hover:shadow-md transition">
        <div class="text-lg font-bold text-gray-800">Grade 9 - Section A</div>
        <div class="text-sm text-gray-700 mt-1">View Students</div>
      </a>
      <a href="students/section.php?level=10&section=A" class="block bg-purple-300 hover:bg-purple-400 p-5 rounded-lg shadow hover:shadow-md transition">
        <div class="text-lg font-bold text-gray-800">Grade 10 - Section A</div>
        <div class="text-sm text-gray-700 mt-1">View Students</div>
      </a>
    </div>

    <!-- Grade B Sections -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-6">
      <a href="students/section.php?level=7&section=B" class="block bg-yellow-200 hover:bg-yellow-300 p-5 rounded-lg shadow hover:shadow-md transition">
        <div class="text-lg font-bold text-gray-800">Grade 7 - Section B</div>
        <div class="text-sm text-gray-700 mt-1">View Students</div>
      </a>
      <a href="students/section.php?level=8&section=B" class="block bg-green-300 hover:bg-green-400 p-5 rounded-lg shadow hover:shadow-md transition">
        <div class="text-lg font-bold text-gray-800">Grade 8 - Section B</div>
        <div class="text-sm text-gray-700 mt-1">View Students</div>
      </a>
      <a href="students/section.php?level=9&section=B" class="block bg-blue-300 hover:bg-blue-400 p-5 rounded-lg shadow hover:shadow-md transition">
        <div class="text-lg font-bold text-gray-800">Grade 9 - Section B</div>
        <div class="text-sm text-gray-700 mt-1">View Students</div>
      </a>
      <a href="students/section.php?level=10&section=B" class="block bg-purple-300 hover:bg-purple-400 p-5 rounded-lg shadow hover:shadow-md transition">
        <div class="text-lg font-bold text-gray-800">Grade 10 - Section B</div>
        <div class="text-sm text-gray-700 mt-1">View Students</div>
      </a>
    </div>
  </section>

  <!-- Senior High School Section -->
  <section>
    <h2 class="text-2xl font-semibold text-gray-700 mb-6">Senior High School</h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
      <a href="students/strand.php?strand=HUMMS" class="block bg-pink-200 hover:bg-pink-300 p-5 rounded-lg shadow hover:shadow-md transition">
        <div class="text-lg font-bold text-gray-800">HUMMS</div>
        <div class="text-sm text-gray-700 mt-1">View Students</div>
      </a>
      <a href="students/strand.php?strand=TVL" class="block bg-red-300 hover:bg-red-400 p-5 rounded-lg shadow hover:shadow-md transition">
        <div class="text-lg font-bold text-gray-800">TVL</div>
        <div class="text-sm text-gray-700 mt-1">View Students</div>
      </a>
      <a href="students/strand.php?strand=GAS" class="block bg-indigo-300 hover:bg-indigo-400 p-5 rounded-lg shadow hover:shadow-md transition">
        <div class="text-lg font-bold text-gray-800">GAS</div>
        <div class="text-sm text-gray-700 mt-1">View Students</div>
      </a>
    </div>
  </section>

</body>
</html>
