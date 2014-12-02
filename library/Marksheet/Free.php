<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 *
 */
class Marksheet_Free extends Marksheet_Abstract {

    public function normaliseMark($mark) {
        return array(
            'norm_mark' => $mark,
            'mark'      => $mark
        );
    }

    public function retrieveMark($user_id, $asText = false, $blankZero = true) {
        $subReturn = $this->getSubmissionReturnHandler()->getOneByUser(
            $user_id,
            $this->getSubmissionBox()->getId()
        );
        return array(
            'mark'     => $subReturn['mark'],
            'comment'  => $subReturn['comment']
        );
    }

    public function convertRawMark($rawMark, $maxMark = 0, $asText = false, $blankZero = true) {
        return $rawMark;
    }

    public function outputUI(SitePage $page, $mark) {
        $output = array(
            'MARK' => Scrub::text($mark['mark']),
            'COMMENT'  => Scrub::text($mark['comment'])
        );
        $page->setVar($output);
    }
}
