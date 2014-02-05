<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Notif\Formatter;


use Monolog\Formatter\FormatterInterface;

class CompressedFormatter implements FormatterInterface {

	const COMPRESSION_LEVEL = 9; // 9 is max, 6 is default, -1 is none

	/**
	 * Formats a log record.
	 *
	 * @param  array $record A record to format
	 * @return mixed The formatted record
	 */
	public function format(array $record) {
		// serialize the message contents
		$uncompressed = serialize($record);

		// compress the resultant string
		$compressed = gzcompress($uncompressed, self::COMPRESSION_LEVEL);

		// base64 encode so it survives transport
		$encoded = base64_encode($compressed);

		// return the result
		return $encoded;

	}

	/**
	 * Formats a set of log records.
	 *
	 * @param  array $records A set of records to format
	 * @return mixed The formatted set of records
	 */
	public function formatBatch(array $records) {
		return $this->format($records);
	}


} 