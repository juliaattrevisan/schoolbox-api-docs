<?php
/**
 * Synchronise_Synergetic_Genazzano_Verbose
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>, <matt.day@alaress.com.au>
 */
class Synchronise_Synergetic_Kings_Verbose extends Synchronise_Base_Verbose {

    public function outputMessage($message) {
        $this->allResults[] = $message;
    }
}
