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
     * Format a pre-extracted value for the UI
     *
     * @param $rawMark
     * @param int $maxMark
     * @param bool $asText
     * @param bool $blankZero
     * @return string|float|int
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
