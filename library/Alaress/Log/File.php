<?php

/**
 * Alaress_Log_File
 *
 * Log data to a file. Used when debugging sync information when you
 * don't have a debugger, or it's just too hard to keep track of the
 * code flow you're trying to investigate.
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Alaress_Log_File
{
    /**
     * Constructor
     *
     * @param string $path  The place to log data; e.g. /tmp/myfile.log
     */
    public function __construct(private string $path)
    {
    }


    /**
     * Save $data to $path with a $prefix
     *
     * @param string $prefix
     * @param mixed $contents
     * @return $this
     */
    public function log(string $prefix, mixed $contents):self
    {
        $filepointer = fopen($this->path, 'a+');

        ob_start();

        /* Also accommodate bools/nulls
         */
        if (is_bool($contents) || null === $contents) {
            var_dump($contents);
        } else {
            print_r($contents);
        }

        $x = ob_get_contents();
        fputs($filepointer, "\n---------------------------------------------------------------\n\n", 8192);
        fputs($filepointer, date('Y-m-d H:i:s') . '    ' . $prefix . ': ' . $x . "\n\n", 16003840);
        ob_end_clean();

        fclose($filepointer);

        return $this;
    }
}
