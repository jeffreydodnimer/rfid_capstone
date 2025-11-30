<?php
define("SERIAL_DEVICE_NOTSET", 0);
define("SERIAL_DEVICE_SET", 1);
define("SERIAL_DEVICE_OPENED", 2);

/**
 * Serial port control class for Windows
 *
 * THIS PROGRAM COMES WITH ABSOLUTELY NO WARRANTIES!
 * USE IT AT YOUR OWN RISK!
 */
class PhpSerial
{
    private $_device = null;
    private $_winDevice = null;
    private $_dHandle = null;
    private $_dState = SERIAL_DEVICE_NOTSET;
    private $_buffer = "";
    private $_os = "windows";

    public $autoFlush = true;

    public function __construct()
    {
        // Register shutdown function to close device automatically
        register_shutdown_function([$this, "deviceClose"]);
    }

    // -----------------------------
    // Device setup
    // -----------------------------
    public function deviceSet($device)
    {
        if ($this->_dState === SERIAL_DEVICE_OPENED) {
            trigger_error("Close the device before setting a new one.", E_USER_WARNING);
            return false;
        }

        if (preg_match("/^COM(\d+):?$/i", $device, $matches)) {
            $this->_winDevice = "COM" . $matches[1];
            $this->_device = "\\\\.\\COM" . $matches[1];
            $this->_dState = SERIAL_DEVICE_SET;
            return true;
        }

        trigger_error("Invalid COM port specified.", E_USER_WARNING);
        return false;
    }

    public function deviceOpen($mode = "r+b")
    {
        if ($this->_dState === SERIAL_DEVICE_OPENED) {
            trigger_error("Device already opened.", E_USER_NOTICE);
            return true;
        }

        if ($this->_dState !== SERIAL_DEVICE_SET) {
            trigger_error("Device must be set before opening.", E_USER_WARNING);
            return false;
        }

        $this->_dHandle = @fopen($this->_device, $mode);
        if ($this->_dHandle === false) {
            trigger_error("Unable to open device.", E_USER_WARNING);
            return false;
        }

        stream_set_blocking($this->_dHandle, 0);
        $this->_dState = SERIAL_DEVICE_OPENED;
        return true;
    }

    public function deviceClose()
    {
        if ($this->_dState !== SERIAL_DEVICE_OPENED) {
            return true;
        }

        if (fclose($this->_dHandle)) {
            $this->_dHandle = null;
            $this->_dState = SERIAL_DEVICE_SET;
            return true;
        }

        trigger_error("Unable to close the device.", E_USER_ERROR);
        return false;
    }

    // -----------------------------
    // Configuration
    // -----------------------------
    public function confBaudRate($rate)
    {
        if ($this->_dState !== SERIAL_DEVICE_SET) {
            trigger_error("Device not set.", E_USER_WARNING);
            return false;
        }

        $validBauds = [110, 150, 300, 600, 1200, 2400, 4800, 9600, 19200, 38400, 57600, 115200];

        if (!in_array($rate, $validBauds)) {
            trigger_error("Invalid baud rate.", E_USER_WARNING);
            return false;
        }

        return $this->_exec("mode " . $this->_winDevice . " BAUD=" . $rate) === 0;
    }

    public function confParity($parity)
    {
        if ($this->_dState !== SERIAL_DEVICE_SET) {
            trigger_error("Device not set.", E_USER_WARNING);
            return false;
        }

        $parityMap = ['none' => 'N', 'odd' => 'O', 'even' => 'E'];
        if (!isset($parityMap[$parity])) {
            trigger_error("Invalid parity.", E_USER_WARNING);
            return false;
        }

        return $this->_exec("mode " . $this->_winDevice . " PARITY=" . $parityMap[$parity]) === 0;
    }

    public function confCharacterLength($length)
    {
        if ($this->_dState !== SERIAL_DEVICE_SET) {
            trigger_error("Device not set.", E_USER_WARNING);
            return false;
        }

        $length = max(5, min(8, (int)$length));
        return $this->_exec("mode " . $this->_winDevice . " DATA=" . $length) === 0;
    }

    public function confStopBits($length)
    {
        if ($this->_dState !== SERIAL_DEVICE_SET) {
            trigger_error("Device not set.", E_USER_WARNING);
            return false;
        }

        if (!in_array($length, [1, 2])) {
            trigger_error("Invalid stop bits.", E_USER_WARNING);
            return false;
        }

        return $this->_exec("mode " . $this->_winDevice . " STOP=" . $length) === 0;
    }

    public function confFlowControl($mode)
    {
        if ($this->_dState !== SERIAL_DEVICE_SET) {
            trigger_error("Device not set.", E_USER_WARNING);
            return false;
        }

        $map = [
            'none' => "xon=off octs=off rts=on",
            'rts/cts' => "xon=off octs=on rts=hs",
            'xon/xoff' => "xon=on octs=off rts=on"
        ];

        if (!isset($map[$mode])) {
            trigger_error("Invalid flow control mode.", E_USER_WARNING);
            return false;
        }

        return $this->_exec("mode " . $this->_winDevice . " " . $map[$mode]) === 0;
    }

    // -----------------------------
    // I/O
    // -----------------------------
    public function sendMessage($str, $waitForReply = 0.1)
    {
        $this->_buffer .= $str;

        if ($this->autoFlush) {
            $this->serialflush();
        }

        usleep((int)($waitForReply * 1_000_000));
    }

    public function readPort($count = 0)
    {
        if ($this->_dState !== SERIAL_DEVICE_OPENED) {
            trigger_error("Device must be opened.", E_USER_WARNING);
            return false;
        }

        $content = '';
        $i = 0;

        if ($count !== 0) {
            while ($i < $count) {
                $chunk = fread($this->_dHandle, $count - $i);
                if ($chunk === false || $chunk === '') break;
                $content .= $chunk;
                $i += strlen($chunk);
            }
        } else {
            while (!feof($this->_dHandle)) {
                $chunk = fread($this->_dHandle, 128);
                if ($chunk === false || $chunk === '') break;
                $content .= $chunk;
            }
        }

        return $content;
    }

    public function serialflush()
    {
        if ($this->_dState !== SERIAL_DEVICE_OPENED) return false;

        if (fwrite($this->_dHandle, $this->_buffer) !== false) {
            $this->_buffer = '';
            return true;
        } else {
            $this->_buffer = '';
            trigger_error("Error writing to serial port.", E_USER_WARNING);
            return false;
        }
    }

    // -----------------------------
    // Internal exec
    // -----------------------------
    private function _exec($cmd, &$out = null)
    {
        $desc = [1 => ["pipe", "w"], 2 => ["pipe", "w"]];
        $proc = proc_open($cmd, $desc, $pipes);
        if (!is_resource($proc)) return 1;

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $retVal = proc_close($proc);

        if (func_num_args() == 2) $out = [$stdout, $stderr];
        return $retVal;
    }
}
