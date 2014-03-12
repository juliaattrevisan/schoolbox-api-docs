<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

$inputFile = fopen('./quotes.csv', 'r');

while ($row = fgetcsv($inputFile, 4096)) {
	print_r($row);
}

