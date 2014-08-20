<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

//$num    $index
//5       e
//25      y
//26      z
//26+4    ad
//26+9    ai
//26+24   ax
//26+26   az
//26+26+1 = ba
//26+26+2 = bb
//26+26+26 = bz
//26+26+26+1 = ca
//26+26+26+26 = cz
//26+26+26+26+1 = da

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

for ($num = 1; $num < 702; $num++) {
    echo "{$num}: ". findLetterIndex($num);
    echo "<BR>";
}

exit();