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
