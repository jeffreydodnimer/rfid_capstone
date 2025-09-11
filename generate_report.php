<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Attendance Report (SF2)</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            background-color: #f4f4f4; 
            margin: 0; 
            padding: 20px; 
            color: #333; 
        }
        .container {
            max-width: 600px; 
            margin: 20px auto; 
            background-color: #fff; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            text-align: center;
        }
        h1 {
            color: #0056b3;
            margin-bottom: 20px;
        }
        form {
            margin-top: 20px;
            max-width: 400px;
            margin: 0 auto;
            text-align: left;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            margin-top: 10px;
        }
        input[type="text"],
        input[type="month"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            margin-bottom: 20px;
            display: block;
            margin: 0 auto 20px auto;
        }
        button {
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            border: none; 
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            margin-bottom: 20px;
        }
        button:hover { 
            background-color: #0056b3; 
        }
        a {
            display: inline-block;
            margin-top: 20px;
            text-decoration: none;
            color: #007bff;
            width: 100%;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Generate SF2 Attendance Report</h1>

        <!-- Form to generate PDF, points to generate_pdf.php -->
        <form action="generate_pdf.php" method="get" target="_blank">
            <label for="school_name">School Name:</label>
            <input type="text" 
                   name="school_name" 
                   id="school_name" 
                   placeholder="e.g., SAMPLE INTEGRATED SCHOOL" 
                   required
            />

            <label for="school_id">School ID:</label>
            <input type="text" 
                   name="school_id" 
                   id="school_id" 
                   placeholder="e.g., 987654" 
                   required
            />

            <label for="grade_level">Grade Level:</label>
            <input type="text" 
                   name="grade_level" 
                   id="grade_level" 
                   placeholder="e.g., Grade 7" 
                   required
            />

            <label for="section">Section:</label>
            <input type="text" 
                   name="section" 
                   id="section" 
                   placeholder="e.g., Grade7-A" 
                   required
            />

            <label for="advisor_name">Adviser:</label>
            <input type="text" 
                   name="advisor_name" 
                   id="advisor_name" 
                   placeholder="e.g., LOVELY BUAG CHIANG" 
                   required
            />

            <label for="month">Month:</label>
            <input type="month" 
                   name="month" 
                   id="month" 
                   required
            />

            <button href="generate_pdf.php" type="submit" >Generate PDF</button>
        </form>

    </div>
</body>
</html>