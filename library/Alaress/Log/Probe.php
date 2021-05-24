<?php

/**
 * Alaress_Probe
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Alaress_Log_Probe
{

    /**
     * Constructor
     *
     * @param mixed $dataStructure
     */
    public function __construct($dataStructure)
    {
        echo "<pre>";
        print_r($dataStructure);
        echo "</pre>";
    }
}
