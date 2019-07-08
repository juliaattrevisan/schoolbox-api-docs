<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 *
 */
class Marksheet_Free extends Marksheet_Abstract
{



    public function convertRawMark($rawMark, $maxMark = 0, $asText = false, $blankZero = true)
    {
        return $rawMark;
    }

    public function outputUI(SitePage $page, $mark)
    {
        $output = array(
            'MARK' => Scrub::text($mark['mark']),
            'COMMENT'  => Scrub::text($mark['comment'])
        );
        $page->setVar($output);
    }
}
