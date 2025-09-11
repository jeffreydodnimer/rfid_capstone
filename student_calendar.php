<?php
// Set timezone to Philippine Time
date_default_timezone_set('Asia/Manila');

// Get current date information
$month = date('n');
$year  = date('Y');
$today = date('j');

// First day of the month and total days in month
$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$totalDays = date('t', $firstDayOfMonth);

// Month and weekday names
$monthName = date('F', $firstDayOfMonth);
$weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// What day of the week the month starts on (0=Sun, 6=Sat)
$startDay = date('w', $firstDayOfMonth);

// Prepare current Philippine date/time for JS (Y-m-d H:i:s)
$currentDateTimeJS = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Calendar - Philippine Time</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%; /* Ensure html and body take full height */
        }
        body {
            font-family: Arial, sans-serif;
            /* To use a local image:
               1. Place your image file (e.g., 'my_background.jpg') in the same directory as this PHP file.
               2. Or, specify the correct relative path if it's in a subdirectory (e.g., 'images/my_background.png').
            */
            background: url('img/pic6.jpg') no-repeat center center fixed; /* <--- CHANGED HERE */
            background-size: cover;
            min-height: 100vh; /* Ensure body covers full viewport height */
        }
        .content-wrap {
            background: rgba(255,255,255,0.8);
            /* Make it cover the full viewport */
            width: 100%;
            min-height: 100vh; /* This makes it fill the screen height */
            padding: 16px 0; /* Add top/bottom padding for content spacing */
            box-sizing: border-box; /* Include padding in the height calculation */
            
            /* Optional: Center content vertically if desired */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center; /* Center horizontally for block elements */
        }
        table.calendar {
            border-collapse: collapse;
            width: 100%;
            max-width: 400px;
            margin: 20px auto; /* Add some vertical margin to separate from time */
        }
        .calendar th, .calendar td {
            border: 1px solid #888;
            text-align: center;
            height: 40px;
            width: 14.2%;
        }
        .calendar th {
            background: #1976d2;
            color: #fff;
        }
        .today {
            background: #ffeb3b;
            font-weight: bold;
            color: #333;
        }
        caption {
            font-size: 1.4em;
            margin-bottom: 8px;
        }
        .datetime {
            text-align: center;
            margin-bottom: 10px;
            color: #222;
            font-size: 1.1em;
        }
        .clock {
            font-size: 1.3em;
            font-weight: bold;
            letter-spacing: 2px;
        }
        /* Back button styling */
        .back-button {
            background-color: #1976d2;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 20px;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        .back-button:hover {
            background-color: #1565c0;
        }
    </style>
</head>
<body>
<div class="content-wrap">
    <div class="datetime">
        Current Philippine Date and Time:<br>
        <span class="clock" id="clock"></span>
    </div>

    <table class="calendar">
        <caption><?php echo "$monthName $year"; ?></caption>
        <tr>
            <?php
            foreach ($weekDays as $weekDay) {
                echo "<th>$weekDay</th>";
            }
            ?>
        </tr>
        <tr>
            <?php
            // Add empty cells before the first day
            for ($i = 0; $i < $startDay; $i++) {
                echo "<td></td>";
            }
            
            // Print all days of the month
            $dayOfWeek = $startDay;
            for ($day = 1; $day <= $totalDays; $day++, $dayOfWeek++) {
                // Highlight today's date
                if ($day == $today) {
                    echo "<td class='today'>$day</td>";
                } else {
                    echo "<td>$day</td>";
                }

                // Start new row after Saturday
                if ($dayOfWeek == 6 && $day != $totalDays) {
                    echo "</tr><tr>";
                    $dayOfWeek = -1;
                }
            }

            // Add empty cells after the last day
            if ($dayOfWeek != 7) {
                for ($i = $dayOfWeek; $i < 7; $i++) {
                    echo "<td></td>";
                }
            }
            ?>
        </tr>
    </table>
    
    <!-- Back Button -->
    <button class="back-button" onclick="window.history.back();">← Back</button>
</div>

<script>
    // Get PHP date/time as start point
    let phTime = new Date('<?php echo $currentDateTimeJS; ?>');

    function updateClock() {
        // Format options
        const options = {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
            hour: '2-digit', minute: '2-digit', second: '2-digit',
            hour12: true
        };

        // Format time, replace comma with just a pipe for neatness
        document.getElementById('clock').textContent = phTime.toLocaleString('en-US', options).replace(",", " |");

        // Add 1 second for the next update
        phTime.setSeconds(phTime.getSeconds() + 1);
    }

    // Initial call
    updateClock();
    // Then every second
    setInterval(updateClock, 1000);
</script>
</body>
</html>