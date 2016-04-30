#!/usr/bin/php
<?php


/*
 * Read a file backwards.
 * See http://stackoverflow.com/a/10494801/1999581
 */

class ReverseFile implements Iterator
{
    const BUFFER_SIZE = 4096;
    const SEPARATOR = "\n";

    public function __construct($filename)
    {
        $this->_fh = fopen($filename, 'r');
        $this->_filesize = filesize($filename);
        $this->_pos = -1;
        $this->_buffer = null;
        $this->_key = -1;
        $this->_value = null;
    }

    public function _read($size)
    {
        $this->_pos -= $size;
        fseek($this->_fh, $this->_pos);
        return fread($this->_fh, $size);
    }

    public function _readline()
    {
        $buffer =& $this->_buffer;
        while (true) {
            if ($this->_pos == 0) {
                return array_pop($buffer);
            }
            if (count($buffer) > 1) {
                return array_pop($buffer);
            }
            $buffer = explode(self::SEPARATOR, $this->_read(self::BUFFER_SIZE) . $buffer[0]);
        }
    }

    public function next()
    {
        ++$this->_key;
        $this->_value = $this->_readline();
    }

    public function rewind()
    {
        if ($this->_filesize > 0) {
            $this->_pos = $this->_filesize;
            $this->_value = null;
            $this->_key = -1;
            $this->_buffer = explode(self::SEPARATOR, $this->_read($this->_filesize % self::BUFFER_SIZE ?: self::BUFFER_SIZE));
            $this->next();
        }
    }

    public function key() { return $this->_key; }
    public function current() { return $this->_value; }
    public function valid() { return ! is_null($this->_value); }
}


class AuroraInverterMunin {

    const GRAPH_CATEGORY = 'Aurora Inverter';
    protected $aurora_output_file_path;
    const RUN_MODE_CONFIG = 1;
    const RUN_MODE_DATA = 2;

    public function __construct() {
        $this->aurora_output_file_path = getenv('aurora_output_file_path');
        if ($this->aurora_output_file_path == FALSE) {
            $this->aurora_output_file_path = '/home/ermanno/aurora/output.txt';
        }
    }

    public function getRunMode() {
        global $argv;
        if (count($argv) == 2 && $argv[1] == 'config') {
            return self::RUN_MODE_CONFIG;
        } else {
            return self::RUN_MODE_DATA;
        }
    }

    public function outputConfig() {
        $output = "multigraph aurora_solar_input_voltage
graph_title Input Voltage
graph_vlabel V
graph_category " . self::GRAPH_CATEGORY . "
graph_args --base 1000
input_voltage_first_string.label Input Voltage String 1
input_voltage_first_string.min 0
input_voltage_second_string.label Input Voltage String 2
input_voltage_second_string.min 0

multigraph aurora_solar_input_current
graph_title Input Current
graph_vlabel A
graph_category " . self::GRAPH_CATEGORY . "
graph_args --base 1000
input_current_first_string.label Input Current String 1
input_current_first_string.min 0
input_current_second_string.label Input Current String 2
input_current_second_string.min 0

multigraph aurora_solar_input_power
graph_title Input Power
graph_vlabel W
graph_category " . self::GRAPH_CATEGORY . "
graph_args --base 1000
input_power_first_string.label Input Power String 1
input_power_second_string.label Input Power String 2

multigraph aurora_solar_grid_voltage
graph_title Grid Voltage
graph_vlabel V
graph_category " . self::GRAPH_CATEGORY . "
graph_args --base 1000
grid_voltage.label Grid Voltage

multigraph aurora_solar_grid_current
graph_title Grid Current
graph_vlabel A
graph_category " . self::GRAPH_CATEGORY . "
graph_args --base 1000
grid_current.label Grid Current

multigraph aurora_solar_grid_power
graph_title Grid Power
graph_vlabel W
graph_category " . self::GRAPH_CATEGORY . "
graph_args --base 1000
grid_power.label Grid Power

multigraph aurora_solar_grid_frequency
graph_title Grid Frequency
graph_vlabel Hz
graph_category " . self::GRAPH_CATEGORY . "
graph_args --base 1000
grid_frequency.label Grid Frequency

multigraph aurora_solar_conversion_efficiency
graph_title DC/AC Conversion Efficiency
graph_vlabel %
graph_category " . self::GRAPH_CATEGORY . "
graph_args --base 1000
conversion_efficiency.label Efficiency

multigraph aurora_solar_temperature
graph_title Inverter Temperature
graph_vlabel °C
graph_category " . self::GRAPH_CATEGORY . "
graph_args --base 1000
inverter_temperature.label Inverter Temperature
booster_temperature.label Booster Temperature

multigraph aurora_solar_production
graph_title Energy Production
graph_vlabel Wh
graph_category " . self::GRAPH_CATEGORY . "
graph_args --base 1000
solar_production.label Energy production
";
        return $output;
    }


    public function outputData() {
        if (!file_exists($this->aurora_output_file_path)) {
            return array('success' => FALSE, 'data' => 'Output file of Aurora not found');
        }
        if (!is_readable($this->aurora_output_file_path)) {
            return array('success' => FALSE, 'data' => 'Output file of Aurora is not readable');
        }

        $file_reader = new \ReverseFile($this->aurora_output_file_path);
        if ($file_reader == FALSE) {
            return array('success' => FALSE, 'data' => 'Output file of Aurora is not readable');
        }
        $latest_reading = NULL;
        foreach ($file_reader as $line) {
            if (!$this->isLineValid($line)) {
                continue;
            }
            try {
                $latest_reading = $this->parseAuroraLine($line);
            } catch (Exception $e) {
                return array('success' => FALSE, 'data' => $e->getMessage());
            }
            break;
        }

        return array('success' => TRUE, 'data' => $latest_reading);
    }

    protected function isLineValid($line) {
        if ($line == '') {
            return FALSE;
        }
        if (substr($line, -2) == 'OK') {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /**
     * @param $line string
     *
     * Example: 20160430-15:43:01  346.715271     1.986610   688.787903   346.146210     1.955997   677.060913   232.546310     5.660111  1312.832275    49.992001    96.118416    44.492020    40.557159       13.368       88.958        0.000      373.171      967.855     2888.323     2888.280    OK
     * @throws Exception
     */
    public function parseAuroraLine($line) {
        $timestamp = \DateTime::createFromFormat('Ymd-H:i:s', substr($line, 0, 17));
        if (!$timestamp) {
            throw new Exception('Got no timestamp');
        }
        if (($timestamp->format('U') + 300) < time()) {
            throw new Exception('Latest reading age is older than 5 minutes');
        }
        $output = '';
        $output .= "multigraph aurora_solar_input_voltage\n";
        $output .= 'input_voltage_first_string.value ' . trim(substr($line, 17, 12)) . "\n";
        $sub_line = substr($line, 29); //cut away timestamp and first data, which are not 13 chars long as everything else
        $data = str_split($sub_line, 13);
        $output .= 'input_voltage_second_string.value ' . trim($data[2]) . "\n";

        $output .= "multigraph aurora_solar_input_current\n";
        $output .= 'input_current_first_string.value ' . trim($data[0]) . "\n";
        $output .= 'input_current_second_string.value ' . trim($data[3]) . "\n";

        $output .= "multigraph aurora_solar_input_power\n";
        $output .= 'input_power_first_string.value ' . trim($data[1]) . "\n";
        $output .= 'input_power_second_string.value ' . trim($data[4]) . "\n";

        $output .= "multigraph aurora_solar_grid_voltage\n";
        $output .= 'grid_voltage.value ' . trim($data[5]) . "\n";

        $output .= "multigraph aurora_solar_grid_current\n";
        $output .= 'grid_current.value ' . trim($data[6]) . "\n";

        $output .= "multigraph aurora_solar_grid_power\n";
        $output .= 'grid_power.value ' . trim($data[7]) . "\n";

        $output .= "multigraph aurora_solar_grid_frequency\n";
        $output .= 'grid_frequency.value ' . trim($data[8]) . "\n";

        $output .= "multigraph aurora_solar_conversion_efficiency\n";
        $output .= 'conversion_efficiency.value ' . trim($data[9]) . "\n";

        $output .= "multigraph aurora_solar_temperature\n";
        $output .= 'booster_temperature.value ' . trim($data[10]) . "\n";
        $output .= 'inverter_temperature.value ' . trim($data[11]) . "\n";

        $output .= "multigraph aurora_solar_production\n";
	$production_value = trim($data[12]) * 1000;
        $output .= 'solar_production.value ' . round($production_value) . "\n";
        return $output;
    }
}


$plugin = new AuroraInverterMunin();
if ($plugin->getRunMode() == $plugin::RUN_MODE_CONFIG) {
    echo $plugin->outputConfig();
} else {
    $output = $plugin->outputData();
    if ($output['success'] == FALSE) {
        echo $output['data'];
        exit(1);
    } else {
        echo $output['data'];
    }
}