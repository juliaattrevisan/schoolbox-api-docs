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
