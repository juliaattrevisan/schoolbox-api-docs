<?php
/**
 * Alaress library for working with computer storage sizes commonly called bytes.  Can be used for converting from and to bytes
 * User: jleckie
 * Date: 9/19/11
 * Time: 8:10 PM
 * To change this template use File | Settings | File Templates.
 */

class Alaress_Byte_Format {

	/**
	 * Converts a size in bytes to human readable size
	 *
	 * @param  $size
	 * @return string
	 */
	static public function bytesToString($size, $precision = 2) {
		$a = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');

		for ($i = 0; $size >= 1024; $i++) {
			$size /= 1024;
		}

		return round($size,$precision) . ' ' . $a[$i];
	}

    /**
	 * Converts human readable size into an integer
	 *
	 * @param  $size
	 * @return string
	 */
	static public function stringToBytes($size) {

		if (preg_match('/([0-9\.]+)\s?((KB)|(K))/i', $size, $match)) {
			$bytes = $match[1] * 1024;
		}

		if (preg_match('/([0-9\.]+)\s?((MB)|(M))/i', $size, $match)) {
			$bytes = $match[1] * 1024 * 1024;
		}

		if (preg_match('/([0-9\.]+)\s?((GB)|(G))/i', $size, $match)) {
			$bytes = $match[1] * 1024 * 1024 * 1024;
		}

		if (preg_match('/([0-9\.]+)\s?((TB)|(T))/i', $size, $match)) {
			$bytes = $match[1] * 1024 * 1024 * 1024 * 1024;
		}

        return round($bytes,0);
	}
}
