<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Marksheet_Rubric extends Marksheet_Abstract
{

    private $rubricHandler;

    protected $actionButtonCount = 1;

    public function __construct($mark_type, $submissionBox, $extraParams)
    {
        parent::__construct($mark_type, $submissionBox, $extraParams);
        $this->rubricHandler = new DueWork_Rubric_Handler();
    }

    /**
    * Take a UI-formatted mark and normalise it
    *
    * @param $rubric
    * @return array|mixed
    */
    public function normaliseMark($rubric)
    {
        $mark = 0;
        foreach ($rubric as $i) {
            $mark += $i;
        }

        $max = $this->getRubricHandler()->getRubricMaxScore($this->getSubmissionBox()->getId());

        $normalised = (($mark / $max) * 100);

        // validation: must be inside bounds
        if ($normalised < 0) {
            $normalised = 0;
            $mark = 0;
        } elseif ($normalised > 100) {
            $normalised = 100;
            $mark = $max;
        }

        return array(
            'norm_mark' => $normalised,
            'mark'      => $this->cleanNumber($mark, 4, false) . " / " . $max
        );
    }

    /**
     * Extract a normalised mark from the database, and format it for the UI
     *
     * @param $user_id
     * @param bool $asText
     * @param bool $blankZero
     * @return mixed
     */
    public function retrieveMark($user_id, $asText = false, $blankZero = true)
    {
        $box_id = $this->getSubmissionBox()->getId();
        // we can't trust the submission_box table, because it may have been set to null, so retrieve rubric data
        $marks = $this->getRubricHandler()->getStudentMarksTotal($user_id, $box_id);
        $subReturn = $this->getSubmissionReturnHandler()->getOneByUser($user_id, $box_id);

/*
        $subbox = $this->getSubmissionHandler()->getByID($box_id);
        $mark = $this->cleanNumber(($subReturn['norm_mark'] / 100) * $subbox['max_mark'], 4, $blankZero);
*/

        return array(
            'mark'      => $asText ? sprintf('%d / %d', $marks['mark'], $marks['max']) : $marks['mark'],
            'max_mark'  => $marks['max'],
//            'norm_mark' => $marks['max'] <= 0 ? 0 : ($marks['mark'] / $marks['max']) * 100,
            'norm_mark' => $subReturn['norm_mark'],
            'err'       => isset($marks['err']) ? $marks['err'] : -1,
            'comment'   => $subReturn['comment']
        );
    }

    /**
     * Format a pre-extracted value for the UI
     *
     * @param $rawMark
     * @param int $maxMark
     * @param bool $asText
     * @param bool $blankZero
     */
    public function convertRawMark($rawMark, $maxMark = 0, $asText = false, $blankZero = true)
    {
        $mark = $this->cleanNumber(($rawMark / 100) * $maxMark, 4, $blankZero);
        return $asText ? "$mark / $maxMark" : $mark;
    }

    /**
     * Use the sitepage instance passed to output the user interface elements for this marktype.
     * I think I decided against doing this in the first place (and using a switch instead) to aid Separation of Concerns.
     *
     * @param SitePage $page
     * @param array $mark
     */
    public function outputUI(SitePage $page, $mark)
    {

        $page->setBlock('mark_type_rubric', 'rubric_marked', 'rubric_marked_block');
        $page->setBlock('mark_type_rubric', 'rubric_unmarked', 'rubric_unmarked_block');

        $output = array(
            'MARK'     => $mark['mark'],
            'MAX_MARK' => $mark['max_mark'],
            'COMMENT'  => Scrub::text($mark['comment'])
        );
        $page->setVar($output);

        // no mark given yet
        if ($mark['max_mark'] == -1) {
            $page->parse('rubric_unmarked_block', 'rubric_unmarked');
            $page->setVar('rubric_marked_block', '');
        } else {
            $page->parse('rubric_marked_block', 'rubric_marked');
            $page->setVar('rubric_unmarked_block', '');
        }

        $grades = array();
        $dem = DueWork_ErrorMapper::getInstance();
        $errs = $dem->listDisplayValues();
        foreach ($errs as $e) {
            $grades[] = array(
                'grade_id' => $e,
                'caption' => $e,
            );
        }

        $page->setVar('rubric_grade_drop_block', '');
        $page->dropdown('rubric_grade_drop', $grades, $mark['err'], 'mark_type_rubric', array(
            'grade_id' => 'RUBRIC_GRADE_VALUE',
            'caption'  => 'RUBRIC_GRADE_TEXT',
        ));

    }

    /**
     * @return DueWork_Rubric_Handler
     */
    public function getRubricHandler()
    {
        return $this->rubricHandler;
    }
}
