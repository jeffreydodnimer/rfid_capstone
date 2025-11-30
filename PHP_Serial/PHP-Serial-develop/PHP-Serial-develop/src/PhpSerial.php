<?php
define ("SERIAL_DEVICE_NOTSET", 0);
define ("SERIAL_DEVICE_SET", 1);
define ("SERIAL_DEVICE_OPENED", 2);

class PhpSerial
{
    public $_device = null;
    public $_winDevice = null;
    public $_dHandle = null;
    public $_dState = SERIAL_DEVICE_NOTSET;
    public $_buffer = "";
    public $_os = "";

    public $autoFlush = true;

    /**
     * Constructor. Perform some checks about the OS and setserial
     */
    public function __construct()
    {
        // keep locale consistent
        setlocale(LC_ALL, "en_US");

        $sysName = php_uname();

        if (substr($sysName, 0, 5) === "Linux") {
            $this->_os = "linux";

            if ($this->_exec("stty") === 0) {
                register_shutdown_function(array($this, "deviceClose"));
            } else {
                trigger_error(
                    "No stty availible, unable to run.",
                    E_USER_ERROR
                );
            }
        } elseif (substr($sysName, 0, 6) === "Darwin") {
            $this->_os = "osx";
            register_shutdown_function(array($this, "deviceClose"));
        } elseif (substr($sysName, 0, 7) === "Windows") {
            $this->_os = "windows";
            register_shutdown_function(array($this, "deviceClose"));
        } else {
            trigger_error("Host OS is neither osx, linux nor windows, unable " .
                          "to run.", E_USER_ERROR);
            exit();
        }
    }

    // Backwards compatibility (if someone calls old-style constructor)
    public function PhpSerial()
    {
        $this->__construct();
    }

    //
    // OPEN/CLOSE DEVICE SECTION -- {START}
    //

    public function deviceSet($device)
    {
        if ($this->_dState !== SERIAL_DEVICE_OPENED) {
            if ($this->_os === "linux") {
                if (preg_match("@^COM(\\d+):?$@i", $device, $matches)) {
                    $device = "/dev/ttyS" . ($matches[1] - 1);
                }

                if ($this->_exec("stty -F " . $device) === 0) {
                    $this->_device = $device;
                    $this->_dState = SERIAL_DEVICE_SET;

                    return true;
                }
            } elseif ($this->_os === "osx") {
                if ($this->_exec("stty -f " . $device) === 0) {
                    $this->_device = $device;
                    $this->_dState = SERIAL_DEVICE_SET;

                    return true;
                }
            } elseif ($this->_os === "windows") {
                if (preg_match("@^COM(\\d+):?$@i", $device, $matches)) {
                    // call mode via _exec (not exec), and check its result
                    $modeCmd = "mode " . "COM" . $matches[1] . " xon=on BAUD=9600";
                    if ($this->_exec($modeCmd) === 0) {
                        $this->_winDevice = "COM" . $matches[1];
                        // use the Windows device path form \\.\COMx
                        $this->_device = "\\\\.\\COM" . $matches[1];
                        $this->_dState = SERIAL_DEVICE_SET;

                        return true;
                    }
                }
            }

            trigger_error("Specified serial port is not valid", E_USER_WARNING);

            return false;
        } else {
            trigger_error("You must close your device before to set an other " .
                          "one", E_USER_WARNING);

            return false;
        }
    }

    public function deviceOpen($mode = "r+b")
    {
        if ($this->_dState === SERIAL_DEVICE_OPENED) {
            trigger_error("The device is already opened", E_USER_NOTICE);

            return true;
        }

        if ($this->_dState === SERIAL_DEVICE_NOTSET) {
            trigger_error(
                "The device must be set before to be open",
                E_USER_WARNING
            );

            return false;
        }

        if (!preg_match("@^[raw]\\+?b?$@", $mode)) {
            trigger_error(
                "Invalid opening mode : ".$mode.". Use fopen() modes.",
                E_USER_WARNING
            );

            return false;
        }

        $this->_dHandle = @fopen($this->_device, $mode);

        if ($this->_dHandle !== false) {
            stream_set_blocking($this->_dHandle, 0);
            $this->_dState = SERIAL_DEVICE_OPENED;

            return true;
        }

        $this->_dHandle = null;
        trigger_error("Unable to open the device", E_USER_WARNING);

        return false;
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

        trigger_error("Unable to close the device", E_USER_ERROR);

        return false;
    }

    //
    // CONFIGURE SECTION -- {START}
    //

    public function confBaudRate($rate)
    {
        if ($this->_dState !== SERIAL_DEVICE_SET) {
            trigger_error("Unable to set the baud rate : the device is " .
                          "either not set or opened", E_USER_WARNING);

            return false;
        }

        $validBauds = array (
            110    => 11,
            150    => 15,
            300    => 30,
            600    => 60,
            1200   => 12,
            2400   => 24,
            4800   => 48,
            9600   => 96,
            19200  => 19,
            38400  => 38400,
            57600  => 57600,
            115200 => 115200
        );

        if (isset($validBauds[$rate])) {
            if ($this->_os === "linux") {
                $ret = $this->_exec(
                    "stty -F " . $this->_device . " " . (int) $rate,
                    $out
                );
            } elseif ($this->_os === "osx") {
                $ret = $this->_exec(
                    "stty -f " . $this->_device . " " . (int) $rate,
                    $out
                );
            } elseif ($this->_os === "windows") {
                $ret = $this->_exec(
                    "mode " . $this->_winDevice . " BAUD=" . $validBauds[$rate],
                    $out
                );
            } else {
                return false;
            }

            if ($ret !== 0) {
                trigger_error(
                    "Unable to set baud rate: " . (isset($out[1]) ? $out[1] : ""),
                    E_USER_WARNING
                );

                return false;
            }

            return true;
        } else {
            return false;
        }
    }

    public function confParity($parity)
    {
        if ($this->_dState !== SERIAL_DEVICE_SET) {
            trigger_error(
                "Unable to set parity : the device is either not set or opened",
                E_USER_WARNING
            );

            return false;
        }

        $args = array(
            "none" => "-parenb",
            "odd"  => "parenb parodd",
            "even" => "parenb -parodd",
        );

        if (!isset($args[$parity])) {
            trigger_error("Parity mode not supported", E_USER_WARNING);

            return false;
        }

        if ($this->_os === "linux") {
            $ret = $this->_exec(
                "stty -F " . $this->_device . " " . $args[$parity],
                $out
            );
        } elseif ($this->_os === "osx") {
            $ret = $this->_exec(
                "stty -f " . $this->_device . " " . $args[$parity],
                $out
            );
        } else {
            // use bracket syntax for string access
            $firstChar = isset($parity[0]) ? $parity[0] : '';
            $ret = $this->_exec(
                "mode " . $this->_winDevice . " PARITY=" . $firstChar,
                $out
            );
        }

        if ($ret === 0) {
            return true;
        }

        trigger_error("Unable to set parity : " . (isset($out[1]) ? $out[1] : ""), E_USER_WARNING);

        return false;
    }

    public function confCharacterLength($int)
    {
        if ($this->_dState !== SERIAL_DEVICE_SET) {
            trigger_error("Unable to set length of a character : the device " .
                          "is either not set or opened", E_USER_WARNING);

            return false;
        }

        $int = (int) $int;
        if ($int < 5) {
            $int = 5;
        } elseif ($int > 8) {
            $int = 8;
        }

        if ($this->_os === "linux") {
            $ret = $this->_exec(
                "stty -F " . $this->_device . " cs" . $int,
                $out
            );
        } elseif ($this->_os === "osx") {
            $ret = $this->_exec(
                "stty -f " . $this->_device . " cs" . $int,
                $out
            );
        } else {
            $ret = $this->_exec(
                "mode " . $this->_winDevice . " DATA=" . $int,
                $out
            );
        }

        if ($ret === 0) {
            return true;
        }

        trigger_error(
            "Unable to set character length : " . (isset($out[1]) ? $out[1] : ""),
            E_USER_WARNING
        );

        return false;
    }

    public function confStopBits($length)
    {
        if ($this->_dState !== SERIAL_DEVICE_SET) {
            trigger_error("Unable to set the length of a stop bit : the " .
                          "device is either not set or opened", E_USER_WARNING);

            return false;
        }

        if ($length != 1
                and $length != 2
                and $length != 1.5
                and !($length == 1.5 and $this->_os === "linux")
        ) {
            trigger_error(
                "Specified stop bit length is invalid",
                E_USER_WARNING
            );

            return false;
        }

        if ($this->_os === "linux") {
            $ret = $this->_exec(
                "stty -F " . $this->_device . " " .
                    (($length == 1) ? "-" : "") . "cstopb",
                $out
            );
        } elseif ($this->_os === "osx") {
            $ret = $this->_exec(
                "stty -f " . $this->_device . " " .
                    (($length == 1) ? "-" : "") . "cstopb",
                $out
            );
        } else {
            $ret = $this->_exec(
                "mode " . $this->_winDevice . " STOP=" . $length,
                $out
            );
        }

        if ($ret === 0) {
            return true;
        }

        trigger_error(
            "Unable to set stop bit length : " . (isset($out[1]) ? $out[1] : ""),
            E_USER_WARNING
        );

        return false;
    }

    public function confFlowControl($mode)
    {
        if ($this->_dState !== SERIAL_DEVICE_SET) {
            trigger_error("Unable to set flow control mode : the device is " .
                          "either not set or opened", E_USER_WARNING);

            return false;
        }

        $linuxModes = array(
            "none"     => "clocal -crtscts -ixon -ixoff",
            "rts/cts"  => "-clocal crtscts -ixon -ixoff",
            "xon/xoff" => "-clocal -crtscts ixon ixoff"
        );
        $windowsModes = array(
            "none"     => "xon=off octs=off rts=on",
            "rts/cts"  => "xon=off octs=on rts=hs",
            "xon/xoff" => "xon=on octs=off rts=on",
        );

        if ($mode !== "none" and $mode !== "rts/cts" and $mode !== "xon/xoff") {
            trigger_error("Invalid flow control mode specified", E_USER_ERROR);

            return false;
        }

        if ($this->_os === "linux") {
            $ret = $this->_exec(
                "stty -F " . $this->_device . " " . $linuxModes[$mode],
                $out
            );
        } elseif ($this->_os === "osx") {
            $ret = $this->_exec(
                "stty -f " . $this->_device . " " . $linuxModes[$mode],
                $out
            );
        } else {
            $ret = $this->_exec(
                "mode " . $this->_winDevice . " " . $windowsModes[$mode],
                $out
            );
        }

        if ($ret === 0) {
            return true;
        } else {
            trigger_error(
                "Unable to set flow control : " . (isset($out[1]) ? $out[1] : ""),
                E_USER_ERROR
            );

            return false;
        }
    }

    public function setSetserialFlag($param, $arg = "")
    {
        if (!$this->_ckOpened()) {
            return false;
        }

        $return = exec(
            "setserial " . $this->_device . " " . $param . " " . $arg . " 2>&1"
        );

        // guard against empty strings
        $first = isset($return[0]) ? $return[0] : null;

        if ($first === "I") {
            trigger_error("setserial: Invalid flag", E_USER_WARNING);

            return false;
        } elseif ($first === "/") {
            trigger_error("setserial: Error with device file", E_USER_WARNING);

            return false;
        } else {
            return true;
        }
    }

    //
    // I/O SECTION -- {START}
    //

    public function sendMessage($str, $waitForReply = 0.1)
    {
        $this->_buffer .= $str;

        if ($this->autoFlush === true) {
            $this->serialflush();
        }

        usleep((int) ($waitForReply * 1000000));
    }

    public function readPort($count = 0)
    {
        if ($this->_dState !== SERIAL_DEVICE_OPENED) {
            trigger_error("Device must be opened to read it", E_USER_WARNING);

            return false;
        }

        if ($this->_os === "linux" || $this->_os === "osx") {
            $content = ""; $i = 0;

            if ($count !== 0) {
                do {
                    if ($i > $count) {
                        $content .= fread($this->_dHandle, ($count - $i));
                    } else {
                        $content .= fread($this->_dHandle, 128);
                    }
                } while (($i += 128) === strlen($content));
            } else {
                do {
                    $content .= fread($this->_dHandle, 128);
                } while (($i += 128) === strlen($content));
            }

            return $content;
        } elseif ($this->_os === "windows") {
            $content = ""; $i = 0;

            if ($count !== 0) {
                do {
                    if ($i > $count) {
                        $content .= fread($this->_dHandle, ($count - $i));
                    } else {
                        $content .= fread($this->_dHandle, 128);
                    }
                } while (($i += 128) === strlen($content));
            } else {
                do {
                    $content .= fread($this->_dHandle, 128);
                } while (($i += 128) === strlen($content));
            }

            return $content;
        }

        return false;
    }

    public function serialflush()
    {
        if (!$this->_ckOpened()) {
            return false;
        }

        if (fwrite($this->_dHandle, $this->_buffer) !== false) {
            $this->_buffer = "";

            return true;
        } else {
            $this->_buffer = "";
            trigger_error("Error while sending message", E_USER_WARNING);

            return false;
        }
    }

    //
    // INTERNAL TOOLKIT -- {START}
    //

    public function _ckOpened()
    {
        if ($this->_dState !== SERIAL_DEVICE_OPENED) {
            trigger_error("Device must be opened", E_USER_WARNING);

            return false;
        }

        return true;
    }

    public function _ckClosed()
    {
        if ($this->_dState === SERIAL_DEVICE_OPENED) {
            trigger_error("Device must be closed", E_USER_WARNING);

            return false;
        }

        return true;
    }

    public function _exec($cmd, &$out = null)
    {
        $desc = array(
            1 => array("pipe", "w"),
            2 => array("pipe", "w")
        );

        $proc = proc_open($cmd, $desc, $pipes);

        $ret = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $retVal = proc_close($proc);

        if (func_num_args() == 2) $out = array($ret, $err);
        return $retVal;
    }

    //
    // INTERNAL TOOLKIT -- {STOP}
    //
}
