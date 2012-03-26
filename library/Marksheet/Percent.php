<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 *
 */
class Marksheet_Percent extends Marksheet_Abstract {

    public function normaliseMark($mark, $subbox) {

        // validation: must be within 0 - 100
        if($mark < 0)
            $mark = 0;
        else if ($mark > 100)
            $mark = 100;

        return array(
            'norm_mark' => $mark,
            'mark'      => "$mark%"
        );
    }

    public function retrieveMark($user_id, $box_id, $asText = false) {
        $subReturn = $this->getSubmissionReturnHandler()->getOneByUser($user_id, $box_id);
        $mark = $this->clean_num($subReturn['norm_mark'], 0);

        return array(
            'mark'     => $asText ? "$mark%" : $mark,
            'comment'  => $subReturn['comment']
        );
    }

    public function convertRawMark($rawMark, $maxMark = 0, $asText = false) {
        $mark = $this->clean_num($rawMark, 0);
        return $asText ? "$mark%" : $mark;
    }

    public function retrieveMarkBySubmissionID($submissionID, $asText = false) {

    }
}
