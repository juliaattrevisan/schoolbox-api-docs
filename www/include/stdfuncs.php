<?php

/************************************************************
*                                                           *
*         Copyright (c) 2003-2004 Lektec Consulting         *
*                                                           *
*   A collection of standard common functions               *
*   $Id: stdfuncs.php,v 1.5 2004/04/27 05:16:48 jleckie Exp $
*                                                           *
************************************************************/

use Schoolbox\Error\Mapper as ErrorMapper;

/**
 * Function for handling error conditions
 * @deprecated 17.5
 * @see Schoolbox\Error\Mapper::generateException()
 *
 * @param mixed  $ec      int:    retrieves error text from code from database
 *                        null|0: outputs whatever's in $message as error
 * @param string $message Replaces '%MESSAGE%' in $ec
 * @param int    $code    HTTP response code, defaults to 400 (Bad Request)
 * @throws Exception
 * @return never-return
 */
function error($ec, $message = "", $code = 400)
{
    throw ErrorMapper::getInstance()->generateException($ec, null, $message, $code);
}

/**
 * @param $camel
 * @param string $delim
 * @return string
 */
function camel2score($camel, $delim = '_')
{
    $xpl = preg_split('/([[:upper:]][[:lower:]]+)/', $camel, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    return strtolower(implode($delim, $xpl));
}


/**
 * @param $score
 * @param string $delim
 * @return string
 */
function score2camel($score, $delim = '_')
{
    return lcfirst(score2pascal($score, $delim));
}


/**
 * @param $score
 * @param string $delim
 * @return string
 */
function score2pascal($score, $delim = '_')
{
    $xpl = explode($delim, $score);
    foreach ($xpl as &$v) {
        $v = ucfirst($v);
    }
    return implode('', $xpl);
}

/**
 * Convenience wrapper for consistent temp file naming
 *
 * @deprecated just use tempnam instead
 *
 * @param string $prefix
 * @param string $dir
 * @return string
 */
function sb_tempnam($prefix = '', $dir = '')
{
    return tempnam($dir ?: sys_get_temp_dir(), 'sb-' . INSTANCE_ID . ($prefix ? "-$prefix" : ''));
}
