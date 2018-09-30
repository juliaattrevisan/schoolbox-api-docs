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
	 * @param  int|float|string $size
	 * @param  int              $precision
	 * @return string
	 */
	public static function bytesToString($size, int $precision = 2): string
    {
		$a = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');

		for ($i = 0; $size >= 1024; $i++) {
			$size /= 1024;
		}

		return round($size,$precision) . ' ' . $a[$i];
	}

    /**
	 * Converts human readable size into an integer
	 *
	 * @param  string $size
	 * @return int
	 */
	public static function stringToBytes(string $size): int
    {
        $trimmed = \trim($size);

	    // All numeric
	    if(preg_match('/^\d+$/', $trimmed, $match)){
            $bytes = $match[0];
        }
        // Numeric with 'B'
        elseif(preg_match('/(\d+)\s?B/i', $trimmed, $match)){
            $bytes = $match[1];
        }

		elseif (preg_match('/([0-9\.]+)\s?((KB)|(K))/i', $trimmed, $match)) {
			$bytes = $match[1] * 1024;
		}

		elseif (preg_match('/([0-9\.]+)\s?((MB)|(M))/i', $trimmed, $match)) {
			$bytes = $match[1] * 1024 * 1024;
		}

        elseif (preg_match('/([0-9\.]+)\s?((GB)|(G))/i', $trimmed, $match)) {
			$bytes = $match[1] * 1024 * 1024 * 1024;
		}

        elseif (preg_match('/([0-9\.]+)\s?((TB)|(T))/i', $trimmed, $match)) {
			$bytes = $match[1] * 1024 * 1024 * 1024 * 1024;
		}

        return (int)round($bytes ?? 0, 0);
	}
}
