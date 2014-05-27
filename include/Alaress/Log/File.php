<?php
/**
 * Alaress_Log_File
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Alaress_Log_File {

	/**
	 * Constructor
	 *
	 * Save $data to $path with a $prefix
	 *
	 * @param $path
	 * @param $prefix
	 * @param $data
	 */
	public function __construct($path, $prefix, $data) {
		$filepointer = fopen($path, 'a+');

		ob_start();
		print_r($data);
		$x = ob_get_contents();
		fputs($filepointer, date('y-m-d h:i:s') . '    ' . $prefix . ': ' . $x . "\n\n", 8192);
		ob_end_clean();

		fclose($filepointer);
	}
}
