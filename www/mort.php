<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

/*

1               a
2               b
3               c
4               d
5               e
5+1             aa
5+5             ae
5+5+1           ba
5+5+5           be
5+5+5+1         ca
5+5+5+5         ce
5+5+5+5+1       da
5+5+5+5+3       dc
5+5+5+5+5       de
5+5+5+5+5+1     ea
5+5+5+5+5+5     ee
5+5+5+5+5+5+1   aaa
5+5+5+5+5+5+5   aae
*/

$kernel = require_once __DIR__ . '/../include/Bootstrap.php';

/**
 * Translate an integer to letters
 *
 * @param $num
 * @return string
 */
function findLetterIndex($num) {
    $firstLetter = floor($num / 26);
    $lastLetter = $num % 26;

    /* Special case for z, az, bz, etc.
     */
    if (!$lastLetter) {
        $firstLetter--;
        $lastLetter = 26;
    }

    /* Convert from numeric to alpha
     */
    if ($firstLetter) {
        $string = chr($firstLetter+64) . chr($lastLetter+64);
    }
    else {
        $string = chr($lastLetter+64);
    }

    return $string;
}

for ($num = 1; $num < 703; $num++) {
    echo "{$num}: ". findLetterIndex($num);
    echo "<BR>";
}

exit();