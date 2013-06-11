<?php
/**
 * Formats text strings into more meaningful stuff
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class TextFormatter {

	/**
	 * Create a greeting string. If it's 5pm, change to Evening
	 *
	 * @return string
	 */
	public function greeting() {
		if (date('A') == 'AM') {
			return 'Good Morning';
		}
		else if (date('G') < 17) {
			return 'Good Afternoon';
		}

		return 'Good Evening';
	}

	/**
	 * Cut a string to the character limit and append ellipsis (...)
	 *
	 * @param  $content
	 * @param  $chars
	 * @return string
	 */
	public function cutString($content, $chars) {
		$summary = substr($content, 0, $chars);

		if (strlen($content) > strlen($summary)) {
			return $summary . '...';
		}

		return $summary;
	}

	/**
	 * Cut off a summary at the fullstop within the character limit
	 * Alternatively just at the word
	 *
	 * @param  $content
	 * @param  $chars
	 * @return string
	 */
	public static function cutSentence($content, $chars) {

        $content = strip_tags($content);

		if (strlen($content) < $chars) {
			return $content;
		}

		$summary = substr($content, 0, $chars);
		$position = strrpos($summary, '.') + 1;
		$positionQ = strrpos($summary, '?') + 1;
		$positionB = strrpos($summary, '!') + 1;

		if ($position > 1 && $position > $positionQ && $position > $positionB) {
			$output = substr($summary, 0, $position);
		}
		else if ($positionQ > 1 && $positionQ > $positionB) {
			$output = substr($summary, 0, $positionQ);
		}
		else if($positionB > 1) {
			$output = substr($summary, 0, $positionB);
		}
		else {
			$position = strrpos($summary, ' ');
			$output = substr($summary, 0, $position);

			if ($output) {
				$output .= '...';
			}
			else {
				$output = '';
			}
		}

		return $output;
	}

	/**
	 * Output a line in CSV format
	 *
	 * @param  $line
	 * @param string $delimiter
	 * @param string $enclosure
	 * @return string
	 */
	public function outputCSV($line, $delimiter=',', $enclosure='"') {
		// No leading delimiter
		$writeDelimiter = FALSE;
		$string = '';

		foreach($line as $dataElement){
		   // Replaces a double quote with two double quotes
		   $dataElement = str_replace("\"", "\"\"", $dataElement);

		   // Adds a delimiter before each field (except the first)
		   if ($writeDelimiter) {
			   $string .= $delimiter;
		   }

		   // Encloses each field with $enclosure and adds it to the string
		   $string .= $enclosure . $dataElement . $enclosure;

		   // Delimiters are used every time except the first.
		   $writeDelimiter = TRUE;
		}

		return $string;
	}
}

