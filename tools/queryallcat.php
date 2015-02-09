<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$rdi = new RecursiveDirectoryIterator("/vagrant/data");
$rri = new RecursiveIteratorIterator($rdi, RecursiveIteratorIterator::SELF_FIRST);
$ri  = new RegexIterator($rri, '`msg.[a-z_]+.conf`i', RecursiveRegexIterator::GET_MATCH);

$agg = array();

foreach ($ri as $filename => $matches) {
    $fp = fopen($filename, 'r');
    while ($line = fgets($fp)) {
        if (!strlen(trim($line))) {
            break;
        }

        $foo = explode("\t", $line, 5);

        if (count($foo) != 5) {
            echo $line;
            break;
        }

        list($type, $min, $max, $default, $count) = $foo;

        if (empty($agg[$min][$max][$default])) {
            $agg[$min][$max][$default] = 0;
        }
        $agg[$min][$max][$default] += $count;
    }
}

$rev = array();

foreach ($agg as $min => $one) {
    foreach ($one as $max => $two) {
        foreach ($two as $def => $count) {
            $rev[$count][] = array($min, $max, $def);
        }
    }
}

krsort($rev);

print_r($rev);