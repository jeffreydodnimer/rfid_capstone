<!DOCTYPE html>
<html>
<head>
    <title>SF2 Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 30px;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #1a73e8;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #125abc;
        }
    </style>
</head>
<body>

    <h2>Download DepEd SF2 Attendance Report</h2>

    <form action="generate_sf2_pdf.php" method="post" target="_blank">
        <button type="submit">Download PDF</button>
    </form>

</body>
</html>
