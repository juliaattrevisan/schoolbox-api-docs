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
     * Take a UI-formatted mark and normalise it
     *
     * @param $mark
     * @param $subbox
     * @return mixed
     */
    public function normaliseMark($rubric, $subbox) {
        $mark = 0;
        foreach($rubric as $i) {
            $mark += $i;
        }

        $max = $subbox['max_mark'];
        $normalised = (($mark / $max) * 100);

        // validation: must be inside bounds
        if($normalised < 0) {
            $normalised = 0;
            $mark = 0;
        } else if($normalised > 100) {
            $normalised = 100;
            $mark = $max;
        }

        return array(
            'norm_mark' => $normalised,
            'mark'      => $this->clean_num($mark, 4, false) . " / " . $max
        );
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
        // this was copied from Raw (Total Mark), since that's the aggregate display format
        $subReturn = $this->getSubmissionReturnHandler()->getOneByUser($user_id, $box_id);
        $subbox = $this->getSubmissionHandler()->getByID($box_id);
        $mark = $this->clean_num(($subReturn['norm_mark'] / 100) * $subbox['max_mark'], 4, $blankZero);
        return array(
            'mark'     => $asText ? "$mark / {$subbox['max_mark']}" : $mark,
            'max_mark' => $subbox['max_mark'],
            'comment'  => $subReturn['comment']
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

        $page->setBlock('mark_type_rubric', 'rubric_marked', 'rubric_marked_block');
        $page->setBlock('mark_type_rubric', 'rubric_unmarked', 'rubric_unmarked_block');

        $output = array(
            'MARK'     => $mark['mark'],
            'MAX_MARK' => $mark['max_mark'],
            'COMMENT'  => $mark['comment']
        );
        $page->setVar($output);

        if($mark['mark'] || $mark['mark'] === 0) {
            $page->parse('rubric_marked_block', 'rubric_marked');
            $page->setVar('rubric_unmarked_block', '');
        }
        else {
            $page->parse('rubric_unmarked_block', 'rubric_unmarked');
            $page->setVar('rubric_marked_block', '');

        }
    }

}
