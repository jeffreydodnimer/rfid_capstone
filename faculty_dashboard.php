<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #3B82F6; /* A clear blue for primary actions/active states */
            --primary-color-hover: #2563EB;
            --sidebar-bg-start: rgb(23, 79, 190); /* Dark blue from your original */
            --sidebar-bg-end: rgb(15, 60, 150); /* Slightly darker blue for gradient effect */
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f2f5; /* Lighter gray for background */
        }
        
        /* Custom styles for the sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, var(--sidebar-bg-start) 0%, var(--sidebar-bg-end) 100%);
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.25); /* Stronger, softer shadow */
            padding-top: 0;
            position: relative;
            z-index: 10; /* Ensure sidebar is above main content for responsive overlay */
            transition: width 0.3s ease-in-out, transform 0.3s ease-in-out;
            border-radius: 0 15px 15px 0; /* Rounded right edge */
            display: flex;
            flex-direction: column;
        }

        /* Collapsed state for sidebar */
        .sidebar.collapsed {
            width: 80px;
            transform: translateX(0); /* Ensure it's visible on large screens */
        }
        .sidebar.collapsed .sidebar-logo .logo-text,
        .sidebar.collapsed .sidebar-section-title,
        .sidebar.collapsed .nav-link span {
            display: none;
        }
        .sidebar.collapsed .sidebar-logo img {
            margin-right: 0;
        }
        .sidebar.collapsed .nav-link {
            justify-content: center;
        }
        .sidebar.collapsed .collapse-button svg {
            transform: rotate(180deg);
        }


        .sidebar-logo {
            background-color: rgba(0, 0, 0, 0.1); /* Slight overlay for the logo section */
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column; /* Stack logo and text */
            align-items: center;
            justify-content: center;
            margin-bottom: 2rem;
            height: 180px; /* Increased height for profile image */
            position: relative; /* For profile picture positioning */
        }

        .sidebar-logo img {
            width: 90px; 
            height: 90px; 
            object-fit: cover; 
            border-radius: 50%; 
            border: 4px solid rgba(255, 255, 255, 0.3); /* Lighter border for profile pic */
            margin-bottom: 0.75rem; /* Space between img and text */
            transition: all 0.3s ease;
        }
        .sidebar-logo .logo-text {
            color: #ffffff;
            font-size: 1.5rem;
            font-weight: 700; /* Bold */
            letter-spacing: 0.05em;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }

        .sidebar nav {
            padding: 0 1rem;
            flex-grow: 1; /* Allow nav to take available available space */
            display: flex; /* Use flexbox for nav */
            flex-direction: column; /* Stack items vertically */
        }

        .sidebar-section-title {
            color: #bfdbfe; /* Lighter blue for section titles */
            font-size: 0.75rem;
            font-weight: 700; /* Bolder */
            text-transform: uppercase;
            padding: 0.75rem 1rem 0.5rem;
            letter-spacing: 0.08em; /* More pronounced spacing */
            margin-top: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1); /* Subtle separator */
            padding-bottom: 0.5rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .nav-link {
            padding: 0.9rem 1rem; /* Increased padding */
            display: flex;
            align-items: center;
            color: #e0f2fe; /* Light blue text for inactive links */
            transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
            font-weight: 500;
            border-radius: 0.5rem; /* Slightly larger border-radius */
            margin-bottom: 0.5rem; /* More space between links */
            text-decoration: none; /* Remove underline */
        }
        .nav-link i {
            margin-right: 1rem; /* Space for icon */
            font-size: 1.2rem;
            width: 24px; /* Fixed width for icons */
            text-align: center;
        }
        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.15); /* Lighter hover background */
            color: #ffffff;
            transform: translateX(5px); /* Subtle slide effect on hover */
        }
        .nav-link.active {
            background-color: #ffffff; /* White background for active */
            color: var(--primary-color); /* Primary color text for active */
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2); /* Stronger shadow for active link */
            transform: translateX(5px);
        }
        .nav-link.active i {
            color: var(--primary-color-hover); /* Icon color matches text */
        }
        /* Style for the logout link */
        .nav-link.logout-link {
            margin-top: auto; /* Pushes the logout link to the bottom of the flex container (nav) */
            background-color: #EF4444; /* Tailwind red-500: A clear, distinct red */
            color: #ffffff; /* White text for contrast */
            font-weight: 600; /* Make text bolder */
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3); /* Soft shadow with red tint */
            padding: 1rem 1rem; /* Slightly more padding */
            border-radius: 0.75rem; /* More rounded corners */
            margin-top: 1.5rem; /* Add some space above it */
            margin-bottom: 1rem; /* Add some space below it, for overall balance in sidebar */
        }
        .nav-link.logout-link i {
            font-size: 1.3rem; /* Slightly larger icon */
        }
        .nav-link.logout-link:hover {
            background-color: #DC2626; /* Tailwind red-600: Darker red on hover */
            color: #ffffff;
            transform: translateY(-2px); /* Lift effect on hover */
            box-shadow: 0 6px 12px rgba(239, 68, 68, 0.4); /* More pronounced shadow on hover */
        }
        .nav-link.logout-link:active {
            transform: translateY(0); /* Press down effect on click */
            box-shadow: 0 2px 5px rgba(239, 68, 68, 0.2); /* Smaller shadow on active */
        }

        /* Adjust the collapse button's margin to separate it from the logout button */
        .collapse-button {
            /* ... existing styles ... */
            margin-top: 1rem; /* Reduced margin if needed, or adjust based on overall layout */
            margin-bottom: 1rem; /* Space below it */
        }

        /* Sidebar Collapse Button */
        .collapse-button {
            position: absolute; /* Changed from absolute to relative */
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%);
            padding: 0.75rem;
            background-color: rgba(0, 0, 0, 0.3);
            color: white;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease, transform 0.3s ease;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            margin-top: 2rem; /* Add some space above it */
            margin-bottom: 1.5rem; /* Add some space below it, to keep it off the edge */
        }
        .collapse-button:hover {
            background-color: rgba(0, 0, 0, 0.5);
            transform: translateX(-50%) scale(1.05);
        }
        .collapse-button svg {
            transition: transform 0.3s ease;
        }


        /* Main Content Area */
        .content-area {
            flex-grow: 1;
            padding: 2rem;
            background-color: #ffffff;
            border-radius: 0.75rem;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08); /* Softer, larger shadow */
            margin-left: 2rem;
            display: flex;
            flex-direction: column;
        }
        .header-bar {
            background-color: #ffffff;
            padding: 1.5rem 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08); /* Enhanced header shadow */
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header-bar h1 {
            display: flex;
            align-items: center;
        }
        .header-bar h1 i {
            margin-right: 0.75rem;
            color: var(--primary-color);
            font-size: 2rem;
        }

        /* Card-like containers for content */
        .content-card {
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }

        /* Table styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1.5rem;
            border-radius: 0.5rem;
            overflow: hidden;
            border: 1px solid #e2e8f0; /* Outer border for the table */
        }
        th, td {
            border: 1px solid #e2e8f0;
            padding: 0.85rem 1rem;
            text-align: left;
            vertical-align: middle;
        }
        th {
            background-color: #edf2f7;
            font-weight: 600;
            color: #2d3748;
            position: sticky;
            top: 0; /* Sticky header for tables */
            z-index: 1;
        }
        tbody tr:nth-child(even) {
            background-color: #f7fafc;
        }
        tbody tr:hover {
            background-color: #e2f0ff; /* Light blue on row hover */
        }

        /* SF2 Table specific styles */
        .sf2-table th, .sf2-table td {
            font-size: 0.75rem; /* Smaller font for SF2 */
            padding: 0.4rem 0.5rem;
            white-space: nowrap;
        }
        .sf2-table {
            min-width: 1500px; /* Wider for more columns */
        }
        .printable-area {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
        }

        /* Form elements for SF2 report */
        input[type="month"] {
            border: 1px solid #cbd5e0;
            padding: 0.6rem 0.8rem;
            border-radius: 0.375rem;
            font-size: 1rem;
            color: #4a5568;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        input[type="month"]:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.5); /* Blue shadow on focus */
        }
        .action-button {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .action-button:hover {
            background-color: var(--primary-color-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        .action-button:active {
            transform: translateY(0);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        /* Print styles */
        @media print {
            body {
                background-color: #fff;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            .dashboard-container, .sidebar, .header-bar, .print-button, .mb-6.flex.items-center.space-x-4, .text-gray-500.text-center.py-10 {
                display: none !important;
            }
            .printable-area {
                display: block !important;
                width: 100% !important;
                margin: 0 !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
            .sf2-table {
                font-size: 8pt;
                border: 0.5px solid #000;
            }
            .sf2-table th, .sf2-table td {
                border: 0.5px solid #000;
            }
            .printable-content {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
            }
        }

        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .sidebar {
                position: fixed;
                left: -280px; /* Hide by default */
                height: 100vh;
                transform: translateX(0); /* Reset transform */
                border-radius: 0 15px 15px 0;
            }
            .sidebar.active {
                transform: translateX(280px); /* Show when active */
            }
            .content-area {
                margin-left: 0;
            }
            .header-bar {
                padding: 1rem 1.5rem;
            }
            .hamburger-menu {
                display: block;
            }
            .collapse-button {
                display: none; /* Hide collapse button on small screens */
            }
        }
    </style>
</head>
<body class="flex min-h-screen">
    <aside class="sidebar flex flex-col items-center" id="sidebar">
        <div class="sidebar-logo">
            <img src="img/logo.jpg" alt="Faculty Profile Picture"> <span class="logo-text mt-2">Faculty Panel</span>
            <span class="text-white text-sm opacity-80">John Doe</span>
        </div>
        <nav class="w-full">
            <div class="sidebar-section-title">Main Menu</div>
            <a href="#" class="nav-link active" data-view="students">
                <i class="fas fa-users"></i>
                <span>My Students</span>
            </a>

            <div class="sidebar-section-title">Reports & Tools</div>
            <a href="#" class="nav-link" data-view="attendance">
                <i class="fas fa-calendar-check"></i>
                <span>View Attendance</span>
            </a>
            <a href="#" class="nav-link" data-view="sf2">
                <i class="fas fa-file-alt"></i>
                <span>Generate SF2 Report</span>
            </a>
            <a href="#" class="nav-link logout-link" data-view="logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
            <div class="flex-grow"></div> </nav>
        <button id="collapseButton" class="collapse-button">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"></path></svg>
        </button>
    </aside>

    <main class="flex-1 flex flex-col p-8">
        <button id="hamburgerButton" class="lg:hidden absolute top-4 left-4 z-20 p-3 bg-blue-600 text-white rounded-full shadow-lg">
            <i class="fas fa-bars"></i>
        </button>

        <div class="header-bar">
            <h1 id="dashboard-title" class="text-3xl font-bold text-gray-800">
                <i class="fas fa-chalkboard-teacher"></i> My Students
            </h1>
            <div class="text-gray-600 font-medium">Welcome, John Doe (Faculty)</div>
        </div>

        <div id="content" class="content-area">
            </div>
    </main>

    <div id="printableSf2Area" class="printable-area hidden"></div>

    <script>
        // Simulated Data (This data would typically come from a PHP backend fetching from a database)
        // PHP Outline: This data would be fetched from your database tables (e.g., 'students', 'sections', 'enrollments')
        // The admin module would manage the assignment of students to sections.
        // Teachers cannot directly add students; this is enforced by the backend database schema and PHP logic.
        const facultyId = 'F001'; // Simulated current faculty ID

        const simulatedStudents = [
            { id: 'S001', name: 'Alice Smith', sectionId: 'SEC001', sectionName: 'Grade 7 - A', gender: 'F' },
            { id: 'S002', name: 'Bob Johnson', sectionId: 'SEC001', sectionName: 'Grade 7 - A', gender: 'M' },
            { id: 'S003', name: 'Charlie Brown', sectionId: 'SEC001', sectionName: 'Grade 7 - A', gender: 'M' },
            { id: 'S004', name: 'Diana Prince', sectionId: 'SEC001', sectionName: 'Grade 7 - A', gender: 'F' },
            { id: 'S005', name: 'Eve Adams', sectionId: 'SEC002', sectionName: 'Grade 7 - B', gender: 'F' }, // Different section, will not be shown to F001
            { id: 'S006', name: 'Frank White', sectionId: 'SEC001', sectionName: 'Grade 7 - A', gender: 'M' },
            { id: 'S007', name: 'Grace Lee', sectionId: 'SEC001', sectionName: 'Grade 7 - A', gender: 'F' },
            { id: 'S008', name: 'Henry Ford', sectionId: 'SEC001', sectionName: 'Grade 7 - A', gender: 'M' },
        ];

        // Simulated attendance data for a specific month (e.g., June 2025)
        // PHP Outline: This would be fetched from an 'attendance' table.
        // The table would typically store student_id, date, status (Present, Absent, Late, Excuse), and faculty_id.
        const simulatedAttendance = [
            // Student S001 (Alice Smith) - Grade 7 - A
            { studentId: 'S001', date: '2025-06-03', status: 'Present' },
            { studentId: 'S001', date: '2025-06-04', status: 'Present' },
            { studentId: 'S001', date: '2025-06-05', status: 'Absent' },
            { studentId: 'S001', date: '2025-06-06', status: 'Present' },
            { studentId: 'S001', date: '2025-06-07', status: 'Present' },
            { studentId: 'S001', date: '2025-06-10', status: 'Present' },
            { studentId: 'S001', date: '2025-06-11', status: 'Late' },

            // Student S002 (Bob Johnson) - Grade 7 - A
            { studentId: 'S002', date: '2025-06-03', status: 'Present' },
            { studentId: 'S002', date: '2025-06-04', status: 'Late' },
            { studentId: 'S002', date: '2025-06-05', status: 'Present' },
            { studentId: 'S002', date: '2025-06-06', status: 'Present' },
            { studentId: 'S002', date: '2025-06-07', status: 'Absent' },
            { studentId: 'S002', date: '2025-06-10', status: 'Present' },
            { studentId: 'S002', date: '2025-06-11', status: 'Present' },

            // Student S003 (Charlie Brown) - Grade 7 - A
            { studentId: 'S003', date: '2025-06-03', status: 'Present' },
            { studentId: 'S003', date: '2025-06-04', status: 'Present' },
            { studentId: 'S003', date: '2025-06-05', status: 'Present' },
            { studentId: 'S003', date: '2025-06-06', status: 'Present' },
            { studentId: 'S003', date: '2025-06-07', status: 'Present' },
            { studentId: 'S003', date: '2025-06-10', status: 'Present' },
            { studentId: 'S003', date: '2025-06-11', status: 'Present' },


            // Student S004 (Diana Prince) - Grade 7 - A
            { studentId: 'S004', date: '2025-06-03', status: 'Present' },
            { studentId: 'S004', date: '2025-06-04', status: 'Present' },
            { studentId: 'S004', date: '2025-06-05', status: 'Present' },
            { studentId: 'S004', date: '2025-06-06', status: 'Absent' },
            { studentId: 'S004', date: '2025-06-07', status: 'Absent' },
            { studentId: 'S004', date: '2025-06-10', status: 'Present' },
            { studentId: 'S004', date: '2025-06-11', status: 'Absent' },


            // Student S006 (Frank White) - Grade 7 - A
            { studentId: 'S006', date: '2025-06-03', status: 'Present' },
            { studentId: 'S006', date: '2025-06-04', status: 'Present' },
            { studentId: 'S006', date: '2025-06-05', status: 'Present' },
            { studentId: 'S006', date: '2025-06-06', status: 'Present' },
            { studentId: 'S006', date: '2025-06-07', status: 'Present' },
            { studentId: 'S006', date: '2025-06-10', status: 'Present' },
            { studentId: 'S006', date: '2025-06-11', status: 'Present' },

            // Student S007 (Grace Lee) - Grade 7 - A
            { studentId: 'S007', date: '2025-06-03', status: 'Present' },
            { studentId: 'S007', date: '2025-06-04', status: 'Present' },
            { studentId: 'S007', date: '2025-06-05', status: 'Present' },
            { studentId: 'S007', date: '2025-06-06', status: 'Present' },
            { studentId: 'S007', date: '2025-06-07', status: 'Present' },
            { studentId: 'S007', date: '2025-06-10', status: 'Present' },
            { studentId: 'S007', date: '2025-06-11', status: 'Present' },

            // Student S008 (Henry Ford) - Grade 7 - A
            { studentId: 'S008', date: '2025-06-03', status: 'Present' },
            { studentId: 'S008', date: '2025-06-04', status: 'Present' },
            { studentId: 'S008', date: '2025-06-05', status: 'Present' },
            { studentId: 'S008', date: '2025-06-06', status: 'Present' },
            { studentId: 'S008', date: '2025-06-07', status: 'Present' },
            { studentId: 'S008', date: '2025-06-10', status: 'Absent' },
            { studentId: 'S008', date: '2025-06-11', status: 'Present' },
        ];


        const contentDiv = document.getElementById('content');
        const dashboardTitle = document.getElementById('dashboard-title');
        const navLinks = document.querySelectorAll('.nav-link');
        const sidebar = document.getElementById('sidebar');
        const collapseButton = document.getElementById('collapseButton');
        const hamburgerButton = document.getElementById('hamburgerButton');

        // Get the current assigned section for the simulated faculty
        // PHP Outline: In a real PHP app, this would query a 'faculty_sections' table
        // or join 'faculty' with 'sections' to get the assigned section for the logged-in faculty.
        const assignedSectionId = 'SEC001';
        const assignedSectionName = 'Grade 7 - A';

        // Filter students assigned to this faculty's section
        const facultyStudents = simulatedStudents.filter(student => student.sectionId === assignedSectionId);

        // --- Sidebar Collapse/Expand Logic ---
        collapseButton.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });

        hamburgerButton.addEventListener('click', () => {
            sidebar.classList.toggle('active'); // Use 'active' class for mobile overlay
        });


        // --- View Functions ---

        function showMyStudents() {
            dashboardTitle.innerHTML = `<i class="fas fa-chalkboard-teacher"></i> My Students`;
            let studentsHtml = `
                <div class="content-card">
                    <h2 class="text-2xl font-semibold mb-6 text-gray-700">Students in ${assignedSectionName}</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr>
                                    <th class="py-3 px-4 border-b">Student ID</th>
                                    <th class="py-3 px-4 border-b">Name</th>
                                    <th class="py-3 px-4 border-b">Gender</th>
                                    <th class="py-3 px-4 border-b">Section</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            if (facultyStudents.length === 0) {
                studentsHtml += `
                                    <tr>
                                        <td colspan="4" class="py-6 px-4 text-center text-gray-500">No students assigned to your section yet.</td>
                                    </tr>
                `;
            } else {
                facultyStudents.forEach(student => {
                    studentsHtml += `
                                    <tr>
                                        <td class="py-2.5 px-4 border-b">${student.id}</td>
                                        <td class="py-2.5 px-4 border-b">${student.name}</td>
                                        <td class="py-2.5 px-4 border-b">${student.gender}</td>
                                        <td class="py-2.5 px-4 border-b">${student.sectionName}</td>
                                    </tr>
                    `;
                });
            }

            studentsHtml += `
                            </tbody>
                        </table>
                    </div>
                </div>
            `;
            contentDiv.innerHTML = studentsHtml;
        }

        function showViewAttendance() {
            dashboardTitle.innerHTML = `<i class="fas fa-calendar-check"></i> View Attendance`;

            const today = new Date();
            const currentMonth = today.getMonth() + 1; // getMonth() is 0-indexed
            const currentYear = today.getFullYear();
            const daysInMonth = new Date(currentYear, currentMonth, 0).getDate(); // Get last day of month

            const attendanceDates = [];
            // In a real app, you'd generate all working days of the month or fetch distinct dates from DB.
            // For this demo, let's just generate first 15 days of the month to keep table size manageable for display
            for (let i = 1; i <= Math.min(daysInMonth, 15); i++) { // Limit to 15 days for demo table width
                const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                // Only add dates for which we have simulated data or relevant business days
                attendanceDates.push(dateStr);
            }
            attendanceDates.sort();

            let attendanceHtml = `
                <div class="content-card">
                    <h2 class="text-2xl font-semibold mb-6 text-gray-700">Attendance for ${assignedSectionName} - ${new Date(currentYear, currentMonth - 1).toLocaleString('default', { month: 'long', year: 'numeric' })}</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr>
                                    <th class="py-3 px-4 border-b sticky left-0 bg-white min-w-[150px]">Student Name</th>
            `;
            attendanceDates.forEach(date => {
                attendanceHtml += `<th class="py-3 px-2 border-b text-center min-w-[40px]">${new Date(date).getDate()}</th>`;
            });
            attendanceHtml += `
                                </tr>
                            </thead>
                            <tbody>
            `;

            if (facultyStudents.length === 0) {
                attendanceHtml += `
                                <tr>
                                    <td colspan="${attendanceDates.length + 1}" class="py-6 px-4 text-center text-gray-500">No students found for attendance.</td>
                                </tr>
                `;
            } else {
                facultyStudents.forEach(student => {
                    attendanceHtml += `
                                <tr>
                                    <td class="py-2.5 px-4 border-b sticky left-0 bg-white font-medium">${student.name}</td>
                    `;
                    attendanceDates.forEach(date => {
                        const record = simulatedAttendance.find(att => att.studentId === student.id && att.date === date);
                        let statusAbbr = '';
                        let statusClass = '';
                        switch (record ? record.status : '') { // Default to empty if no record
                            case 'Present': statusAbbr = 'P'; statusClass = 'text-green-600'; break;
                            case 'Absent': statusAbbr = 'A'; statusClass = 'text-red-600'; break;
                            case 'Late': statusAbbr = 'L'; statusClass = 'text-yellow-600'; break;
                            case 'Excuse': statusAbbr = 'E'; statusClass = 'text-blue-600'; break;
                            default: statusAbbr = '-'; statusClass = 'text-gray-400'; break;
                        }
                        attendanceHtml += `<td class="py-2.5 px-2 border-b text-center font-bold ${statusClass}">${statusAbbr}</td>`;
                    });
                    attendanceHtml += `
                                </tr>
                    `;
                });
            }

            attendanceHtml += `
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-6 text-sm text-gray-600">
                        <p><strong>P</strong> = Present, <strong>A</strong> = Absent, <strong>L</strong> = Late, <strong>E</strong> = Excuse</p>
                    </div>
                </div>
            `;
            contentDiv.innerHTML = attendanceHtml;
        }

        function showGenerateSf2Report() {
            dashboardTitle.innerHTML = `<i class="fas fa-file-alt"></i> Generate SF2 Report`;
            let sf2Controls = `
                <div class="content-card">
                    <h2 class="text-2xl font-semibold mb-6 text-gray-700">SF2 Report Generation</h2>
                    <div class="mb-6 flex flex-wrap items-center space-y-4 sm:space-y-0 sm:space-x-4 bg-gray-50 p-5 rounded-lg shadow-inner">
                        <label for="sf2Month" class="font-medium text-gray-700 w-full sm:w-auto">Select Month:</label>
                        <input type="month" id="sf2Month" class="p-2.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 flex-grow max-w-xs">
                        <button id="generateSf2Btn" class="action-button flex-grow sm:flex-grow-0">Generate Report</button>
                        <button id="printSf2Btn" class="action-button bg-gray-600 hover:bg-gray-700 ml-auto hidden flex-grow sm:flex-grow-0">Print Report</button>
                    </div>
                    <div id="sf2ReportOutput" class="mt-6 overflow-x-auto min-h-[200px] flex items-center justify-center">
                        <p class="text-gray-500 text-center text-lg">Select a month and click "Generate Report" to see the SF2.</p>
                    </div>
                </div>
            `;
            contentDiv.innerHTML = sf2Controls;

            document.getElementById('generateSf2Btn').addEventListener('click', generateSf2Report);
            document.getElementById('printSf2Btn').addEventListener('click', printSf2Report);
            // Set default month to current month
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            document.getElementById('sf2Month').value = `${year}-${month}`;
        }

        function generateSf2Report() {
            const selectedMonth = document.getElementById('sf2Month').value; // e.g., "2025-06"
            const sf2ReportOutputDiv = document.getElementById('sf2ReportOutput');

            if (!selectedMonth) {
                sf2ReportOutputDiv.innerHTML = `<p class="text-red-500 text-center py-10">Please select a month.</p>`;
                return;
            }

            const [year, month] = selectedMonth.split('-').map(Number);
            const monthName = new Date(year, month - 1).toLocaleString('default', { month: 'long' });
            const daysInMonth = new Date(year, month, 0).getDate();

            // PHP Outline:
            // 1. Fetch attendance records for all students in the assigned section for the selected month.
            // 2. Aggregate the attendance data: count P, A, L, E for each student for the entire month.
            // 3. This is the most complex part of the PHP logic, requiring careful database queries
            //    and aggregation.

            const sf2Data = facultyStudents.map(student => {
                let present = 0;
                let absent = 0;
                let late = 0;
                let excuse = 0;
                const dailyStatuses = {}; // To store 'P'/'A' etc for each day

                for (let day = 1; day <= daysInMonth; day++) {
                    const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const record = simulatedAttendance.find(att => att.studentId === student.id && att.date === dateStr);
                    const status = record ? record.status : 'Absent'; // Assume absent if no record
                    dailyStatuses[day] = status.charAt(0); // P, A, L, E

                    switch (status) {
                        case 'Present': present++; break;
                        case 'Absent': absent++; break;
                        case 'Late': late++; break;
                        case 'Excuse': excuse++; break;
                    }
                }
                return {
                    ...student,
                    present,
                    absent,
                    late,
                    excuse,
                    dailyStatuses
                };
            });

            let sf2ReportHtml = `
                <div class="printable-content p-8 border border-gray-200 rounded-lg shadow-xl bg-white">
                    <h3 class="text-2xl font-bold text-center mb-3 text-gray-800">SF2 - Daily Attendance Report</h3>
                    <p class="text-center mb-6 text-lg text-gray-600"><strong>School Year:</strong> ${year}-${year + 1} | <strong>Month:</strong> ${monthName} | <strong>Section:</strong> ${assignedSectionName}</p>
                    <div class="overflow-x-auto">
                        <table class="sf2-table min-w-full border border-collapse">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th rowspan="2" class="border py-2 px-1 text-center font-bold">#</th>
                                    <th rowspan="2" class="border py-2 px-1 text-center font-bold">LRN</th>
                                    <th rowspan="2" class="border py-2 px-1 text-left font-bold">NAME OF LEARNER</th>
                                    <th colspan="${daysInMonth}" class="border py-2 px-1 text-center font-bold">DAILY ATTENDANCE (${monthName})</th>
                                    <th colspan="4" class="border py-2 px-1 text-center font-bold">TOTAL</th>
                                </tr>
                                <tr class="bg-gray-100">
                                    ${Array.from({ length: daysInMonth }, (_, i) => `<th class="border py-2 px-1 text-center font-bold">${i + 1}</th>`).join('')}
                                    <th class="border py-2 px-1 text-center font-bold">P</th>
                                    <th class="border py-2 px-1 text-center font-bold">A</th>
                                    <th class="border py-2 px-1 text-center font-bold">L</th>
                                    <th class="border py-2 px-1 text-center font-bold">E</th>
                                </tr>
                            </thead>
                            <tbody>
            `;

            sf2Data.forEach((student, index) => {
                sf2ReportHtml += `
                                <tr>
                                    <td class="border py-1 px-1 text-center">${index + 1}</td>
                                    <td class="border py-1 px-1 text-center">9${student.id.substring(1)}</td> 
                                    <td class="border py-1 px-1">${student.name}</td>
                `;
                for (let day = 1; day <= daysInMonth; day++) {
                    sf2ReportHtml += `<td class="border py-1 px-1 text-center">${student.dailyStatuses[day] || '-'}</td>`;
                }
                sf2ReportHtml += `
                                    <td class="border py-1 px-1 text-center">${student.present}</td>
                                    <td class="border py-1 px-1 text-center">${student.absent}</td>
                                    <td class="border py-1 px-1 text-center">${student.late}</td>
                                    <td class="border py-1 px-1 text-center">${student.excuse}</td>
                                </tr>
                `;
            });

            sf2ReportHtml += `
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-10 text-base flex justify-end">
                        <div>
                            <p class="mb-2 text-gray-700"><strong>Note:</strong> P = Present, A = Absent, L = Late, E = Excuse</p>
                            <p class="mt-4 text-right"><strong>Prepared by:</strong> <span class="border-b border-gray-400 px-10 pb-1 inline-block text-center">${'John Doe'}</span></p>
                            <p class="text-gray-600 text-sm italic text-right">(Faculty Name)</p>
                        </div>
                    </div>
                </div>
            `;
            sf2ReportOutputDiv.innerHTML = sf2ReportHtml;
            document.getElementById('printableSf2Area').innerHTML = sf2ReportHtml; // For printing
            document.getElementById('printSf2Btn').classList.remove('hidden'); // Show print button
            sf2ReportOutputDiv.scrollIntoView({ behavior: 'smooth', block: 'start' }); // Scroll to report
        }

        function printSf2Report() {
            const printContent = document.getElementById('printableSf2Area');
            const originalBodyContent = document.body.innerHTML;
            
            document.body.innerHTML = printContent.innerHTML;
            window.print();
            
            // Restore original body content
            document.body.innerHTML = originalBodyContent;
            location.reload(); // Reload to restore all event listeners and state
        }

        function handleLogout() {
            // In a real application, this would involve:
            // 1. Sending an AJAX request to a PHP logout script (e.g., 'logout.php').
            // 2. The PHP script would destroy the session (session_destroy()).
            // 3. The PHP script would then redirect to the login page.
            alert('Logging out... (This is a simulated logout)');
            // For a real logout, you would typically redirect:
            // window.location.href = 'logout.php'; 
        }


        // --- Navigation Logic ---
        navLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const view = link.dataset.view;

                if (view === 'logout') {
                    handleLogout();
                    return; // Prevent further processing for logout
                }

                navLinks.forEach(nav => nav.classList.remove('active'));
                link.classList.add('active');
                document.getElementById('printSf2Btn').classList.add('hidden'); // Hide print button on view change
                
                // Hide sidebar on mobile after clicking a link
                if (window.innerWidth <= 1024) {
                    sidebar.classList.remove('active');
                }

                if (view === 'students') {
                    showMyStudents();
                } else if (view === 'attendance') {
                    showViewAttendance();
                } else if (view === 'sf2') {
                    showGenerateSf2Report();
                }
            });
        });

        // Initial load: show My Students view
        document.addEventListener('DOMContentLoaded', () => {
            showMyStudents();
            // Check if it's a large screen on load to show collapse button
            if (window.innerWidth > 1024) {
                collapseButton.classList.remove('hidden');
            } else {
                collapseButton.classList.add('hidden');
            }
        });

        // Re-evaluate collapse button visibility on resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 1024) {
                collapseButton.classList.remove('hidden');
                sidebar.classList.remove('active'); // Ensure sidebar isn't stuck "active" in desktop mode
            } else {
                collapseButton.classList.add('hidden');
            }
        });

    </script>
</body>
</html>