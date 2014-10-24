<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Marksheet_Letter extends Marksheet_Abstract
{

    public function normaliseMark($mark, \Schoolbox\Assessment\Component\SubmissionBox $subbox)
    {
        $grade = $this->getSubmissionHandler()->getLetterGradeByID($mark);
        return array(
            'norm_mark' => $grade['lower_bound'],
            'mark'      => $grade['caption']
        );
    }

    public function retrieveMark($user_id, $box_id, $asText = false, $blankZero = true)
    {
        $return = $this->getSubmissionReturnHandler()->getOneByUser($user_id, $box_id);
        $grade = $this->getSubmissionReturnHandler()->getLetterGradeByUser($user_id, $box_id);

        return array(
            'grade_selected' => $grade['grade_id'],
            'norm_mark' => $return['norm_mark'],
            'comment' => $return['comment']
        );
    }

    public function convertRawMark($rawMark, $maxMark = 0, $asText = false, $blankZero = true)
    {
        if ($rawMark === null) {
            $rawMark = -1;
        }
        $grade = $this->getSubmissionReturnHandler()->getLetterGradeByValue($rawMark);
        return $grade['caption'];
    }

    public function outputUI(SitePage $page, $mark)
    {
        $page->setVar('grade_drop_block', '');

        // Only allow letter grades for the selected submission box
        // TODO allow the submission to be passed in here
        $factory = new Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\ListFactory();
        $submission = new \Schoolbox\Assessment\Component\SubmissionBox();
        $submission->getMarkType()->setId(
            \Schoolbox\Assessment\Component\SubmissionBox\MarkType::TYPE_LETTER
        );
        $grades = array();
        foreach ($factory->buildGradeList($submission) as $grade) {
            $grades[] = array(
                'id'      => $grade->getId(),
                'caption' => $grade->getCaption(),
                'group'   => $grade->getGroup()->getId(),
            );
        }
        $page->dropdown(
            'grade_drop',
            $grades,
            $mark['grade_selected'],
            'mark_type_letter',
            array(
                'id'      => 'GRADE_VALUE',
                'caption' => 'GRADE_TEXT',
                'group'   => 'GRADE_GROUP_ID',
            )
        );
        $page->setVar('COMMENT', Scrub::text($mark['comment']));
    }
}
