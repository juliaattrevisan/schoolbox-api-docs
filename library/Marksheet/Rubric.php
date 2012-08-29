<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Marksheet_Rubric extends Marksheet_Abstract {

    /**
     * Take a UI-formatted mark, normalise it, and insert it into the database
     *
     * @param $mark
     * @param $subbox
     * @return mixed
     */
    public function normaliseMark($mark, $subbox) {
        // TODO: Implement normaliseMark() method.
    }

    /**
     * Extract a normalised mark from the database, and format it for the UI
     *
     * @param $user_id
     * @param $box_id
     * @param bool $asText
     * @param bool $blankZero
     * @return mixed
     */
    public function retrieveMark($user_id, $box_id, $asText = false, $blankZero = true) {
        // TODO: Implement retrieveMark() method.
    }

    /**
     * Format a pre-extracted value for the UI
     *
     * @param $rawMark
     * @param int $maxMark
     * @param bool $asText
     * @param bool $blankZero
     */
    public function convertRawMark($rawMark, $maxMark = 0, $asText = false, $blankZero = true) {
        // TODO: Implement convertRawMark() method.
    }

    /**
     * Use the sitepage instance passed to output the user interface elements for this marktype.
     * I think I decided against doing this in the first place (and using a switch instead) to aid Separation of Concerns.
     *
     * @param SitePage $page
     * @param array $mark
     */
    public function outputUI(SitePage $page, $mark) {
        // TODO: Implement outputUI() method.
    }

}
