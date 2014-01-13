<?php
/**
 * 
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Realtime;


class Verboser {

	protected $stream;

	public function __construct($stream) {
		$this->stream = $stream;
	}

	public function v($message, $char = ' ') {
		if(!is_null($this->stream)) {
			fputs($this->stream, sprintf("[$char] $message\n"));
		}
	}

} 