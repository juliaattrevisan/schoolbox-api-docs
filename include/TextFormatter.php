<?php
/**
 * Formats text strings into more meaningful stuff
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
	 * Converts a size integer to human readable size
	 *
	 * @param  $size
	 * @return string
	 */
	public function bytesToString($size) {
		$a = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');

		for ($i = 0; $size >= 1024; $i++) {
			$size /= 1024;
		}

		return round($size,2) . ' ' . $a[$i];
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
	public function cutSentence($content, $chars) {
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

}

