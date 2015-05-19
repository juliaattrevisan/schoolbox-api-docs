<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Marksheet_Raw extends Marksheet_Abstract
{

    public function normaliseMark($mark)
    {

        $max = $this->getSubmissionBox()->getMaxMark();
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

    public function retrieveMark($user_id, $asText = false, $blankZero = true)
    {
        $box_id = $this->getSubmissionBox()->getId();
        $subReturn = $this->getSubmissionReturnHandler()->getOneByUser($user_id, $box_id);
        $subbox = $this->getSubmissionHandler()->getByID($box_id);

        $dem = DueWork_ErrorMapper::getInstance();
        $errs = $dem->listDisplayValues();

        if (in_array($subReturn['mark'], $errs)) {
            $rawMark = $subReturn['mark'];
        } elseif (isset($subReturn['mark'])) {
            // Raw marks get stored as "$mark / $totalMark"
            // Conveniently floatval() strips out everything after $mark
            $rawMark = $asText ? $subReturn['mark'] : floatval($subReturn['mark']);
        } else {
            $rawMark = null;
        }

        return array(
            'mark'      => $rawMark,
            'max_mark'  => $subbox['max_mark'],
            'norm_mark' => $subReturn['norm_mark'],
            'comment'   => $subReturn['comment']
        );
    }

    public function convertRawMark($rawMark, $maxMark = 0, $asText = false, $blankZero = true)
    {
        // do we trust what's stored in the database?
        $mark = $this->cleanNumber(($rawMark / 100) * $maxMark, 4, $blankZero);
        return $asText ? "$mark / $maxMark" : $mark;
    }

    public function outputUI(SitePage $page, $mark)
    {
        $output = array(
            'MAX_MARK' => Scrub::text($mark['max_mark']),
            'MARK'     => Scrub::text($mark['mark']),
            'COMMENT'  => Scrub::text($mark['comment'])
        );
        $page->setVar($output);
    }
}
