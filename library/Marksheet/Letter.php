<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Marksheet_Letter extends Marksheet_Abstract {

    public function normaliseMark($mark, $subbox) {
        $grade = $this->getSubmissionHandler()->getLetterGradeByID($mark);
        return array(
            'norm_mark' => $grade['lower_bound'],
            'mark'      => $grade['caption']
        );
    }

    public function retrieveMark($user_id, $box_id, $asText = false) {
        $subReturn = $this->getSubmissionReturnHandler()->getLetterGradeByUser($user_id, $box_id);
        return array(
            'grade_selected' => $subReturn['grade_id']
        );
    }

    public function convertRawMark($rawMark, $maxMark = 0, $asText = false) {
        $grade = $this->getSubmissionReturnHandler()->getLetterGradeByValue($rawMark);
        return $grade['caption'];
    }

    public function retrieveMarkBySubmissionID($submissionID, $asText = false) {

    }
}