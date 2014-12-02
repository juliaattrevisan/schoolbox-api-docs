<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 *
 */
class Marksheet_None extends Marksheet_Abstract
{

    public function normaliseMark($mark)
    {
        return array(
            'norm_mark' => 'NULL',
            'mark'      => null
        );
    }

    public function retrieveMark($user_id, $asText = false, $blankZero = true)
    {
        $subReturn = $this->getSubmissionReturnHandler()->getOneByUser(
            $user_id,
            $this->getSubmissionBox()->getId()
        );
        return array(
            'mark'      => null,
            'norm_mark' => null,
            'comment'   => $subReturn['comment']
        );
    }

    public function convertRawMark($rawMark, $maxMark = 0, $asText = false, $blankZero = true)
    {
        return null;
    }

    public function outputUI(SitePage $page, $mark)
    {
        $output = array(
            'COMMENT'  => Scrub::text($mark['comment'])
        );
        $page->setVar($output);
    }
}
