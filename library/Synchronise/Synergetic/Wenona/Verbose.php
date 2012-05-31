<?php
/**
 * Synchronise_Synergetic_Wenona_Verbose
 *
 * Commandline verbose output for synchronisation of Wenona Synergetic database.
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 * @shamelessly_pilfered_from <steve.chan@alaress.com.au>
 *
 */
class Synchronise_Synergetic_Wenona_Verbose extends Synchronise_Synergetic_Verbose_Abstract {

    /**
     * Create a verbose summary string containing data about the
     * current user being examined
     *
     * @param  $newUser
     * @return string
     */
    protected function verboseUserSummary($newUser) {
        $data = array();
        $data[] = sprintf("un: %-16s",        $newUser['username']);
        $data[] = sprintf("name: %-32s",      implode(' ', array($newUser['title'], $newUser['firstname'], $newUser['lastname'])));
        $data[] = sprintf("alt_email: %-32s", $newUser['alt_email']);
        $data[] = sprintf("campus: [%-8s]",   implode(',', $newUser['campus']));
        $data[] = sprintf("groupIDs: %-4s",   $newUser['group_id']);

        if (array_key_exists('year', $newUser)) {
            $data[] = sprintf('year: %-2s', $newUser['year']);
        }

        $output = implode(' ', $data);

        return "( $output )";
    }
}
