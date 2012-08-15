<?php
/**
 * Synchronise_Synergetic_Moriah_Verbose
 *
 * Contains methods to output verbose messages
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Synchronise_Synergetic_Moriah_Verbose extends Synchronise_Synergetic_Verbose_Abstract {

    public function intro() {
        parent::intro();
        $header = sprintf("%22s%-24s%-32s%-48s%-8s%-6s%-4s\n", '', 'username', 'name', 'alt_email', 'campus', 'group', 'year');

        echo $header;
        for($i = 0; $i < strlen($header); $i++) { echo "-"; }
        echo "\n";
    }

    protected function verboseUserSummary($u) {
        $name = implode(' ', array($u['title'], $u['firstname'], $u['lastname']));
        $campus = implode(',', $u['campus']);
        $year = array_key_exists('year', $u) ? $u['year'] : '';

        return sprintf("%-24s%-32s%-48s%-8s%-6s%-4s", "'".$u['username']."'", $name, $u['alt_email'], $campus, $u['group_id'], $year);
    }

    public function skip() {
        if ($this->getVerbose() === true) {
            echo "o  Skipping create of " . $this->verboseUserSummary($this->getNewUser()) . " (no username)\n";
        }
    }

}
