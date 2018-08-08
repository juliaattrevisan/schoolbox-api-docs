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


    public function convertRawMark($rawMark, $maxMark = 0, $asText = false, $blankZero = true)
    {
        $mark = $this->cleanNumber($rawMark, 0, $blankZero);
        return $asText ? "$mark %" : $mark;
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
