<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\WorkerCrew\Pooling;

class ConfigurationReader
{
    /** @var string */
    private $filename;

    /**
     * @param string $filename
     */
    public function __construct($filename)
    {
        $this->filename = $filename;
    }

    /**
     * @return array
     */
    public function readConfig()
    {
        $stratConfig = [];

        if (!(file_exists($this->filename) && is_readable($this->filename))) {
            return $stratConfig;
        }

        $fp = fopen($this->filename, 'r');
        while ($line = fgets($fp)) {
            $xpl = explode('=', $line);
            if (count($xpl) == 2) {
                $stratConfig[$xpl[0]] = $xpl[1];
            }
        }
        fclose($fp);

        return $stratConfig;
    }
}
