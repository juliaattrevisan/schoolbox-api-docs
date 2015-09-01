<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 *
 */
class Marksheet_Percent extends Marksheet_Abstract
{

    public function normaliseMark($mark)
    {

        // validation: must be within 0 - 100
        if ($mark < 0) {
            $mark = 0;
        } elseif ($mark > 100) {
            $mark = 100;
        }

        return array(
            'norm_mark' => $mark,
            'mark'      => "$mark%"
        );
    }

    public function retrieveMark($user_id, $asText = false, $blankZero = true)
    {
        $subReturn = $this->getSubmissionReturnHandler()->getOneByUser(
            $user_id,
            $this->getSubmissionBox()->getId()
        );

        $dem = DueWork_ErrorMapper::getInstance();
        $errs = $dem->listDisplayValues();

        if (in_array($subReturn['mark'], $errs)) {
            $rawMark = $subReturn['mark'];
        } else {
            $mark = $this->cleanNumber($subReturn['norm_mark'], 0, $blankZero);
            $rawMark = $asText ? "$mark%" : $mark;
        }

        return array(
            'mark'      => $rawMark,
            'norm_mark' => $subReturn['norm_mark'],
            'comment'   => $subReturn['comment']
        );
    }

    public function convertRawMark($rawMark, $maxMark = 0, $asText = false, $blankZero = true)
    {
        $mark = $this->cleanNumber($rawMark, 0, $blankZero);
        return $asText ? "$mark%" : $mark;
    }

    public function outputUI(SitePage $page, $mark)
    {
        $output = array(
            'MARK'        => Scrub::text($mark['mark']),
            'COMMENT'     => Scrub::text($mark['comment']),
            'MARK_SYMBOL' => (is_numeric($mark['mark']) ? '%' : ''),
        );
        $page->setVar($output);
    }
}
