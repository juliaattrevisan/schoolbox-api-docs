<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Marksheet_Raw extends Marksheet_Abstract
{
    public function convertRawMark($rawMark, $maxMark = 0, $asText = false, $blankZero = true)
    {
        // do we trust what's stored in the database?
        $mark = $this->cleanNumber(($rawMark / 100) * $maxMark, $blankZero);
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
