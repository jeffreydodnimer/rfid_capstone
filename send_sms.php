<?php
$fp = fopen("COM9", "w+");
fwrite($fp, "AT\r");
usleep(2000);

fwrite($fp, "AT+CMGF=1\r");
usleep(2000);

fwrite($fp, "AT+CMGS=\"" . "+639481820290" . "\"\r");
usleep(2000);

fwrite($fp, "RNCTLCI Attendance Alert: Hello, this is to inform you that your child ". chr(26));
usleep(2000);   

fclose($fp);
?>