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

    public function retrieveMark($user_id, $box_id, $asText = false, $blankZero = true) {
        $subReturn = $this->getSubmissionReturnHandler()->getLetterGradeByUser($user_id, $box_id);
        return array(
            'grade_selected' => $subReturn['grade_id'],
            'norm_mark'      => $subReturn['lower_bound'],
            'comment'        => $subReturn['comment'],
        );
    }

    public function convertRawMark($rawMark, $maxMark = 0, $asText = false, $blankZero = true) {
        $grade = $this->getSubmissionReturnHandler()->getLetterGradeByValue($rawMark);
        return $grade['caption'];
    }

    public function outputUI(SitePage $page, $mark) {
        $subHandler = new Submission();

        $page->setVar('grade_drop_block', '');
        $page->dropdown('grade_drop', $subHandler->getLetterGrades(), $mark['grade_selected'], 'mark_type_letter', array(
            'grade_id' => 'GRADE_VALUE',
            'caption'  => 'GRADE_TEXT',
        ));
        $page->setVar('COMMENT', $mark['comment']);
    }

}