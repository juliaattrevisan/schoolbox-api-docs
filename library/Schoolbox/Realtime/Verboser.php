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

			$c = '';

			switch($char) {
				case '!': $c = '1;31'; break;
				case '?': $c = '0;33'; break;
				case '+': $c = '0;32'; break;
				case '-': $c = '0;34'; break;
				case '*': $c = '0;31'; break;
				case '>':
				case '<': $c = '1;37'; break;
			}

			fputs($this->stream, sprintf(
				"%s[$char] [%6.2f / %6.2f] $message%s\n",
				$c ? "\033[{$c}m" : '',
				(int)(memory_get_usage(true))/(1024*1024),
				(int)(memory_get_peak_usage(true))/(1024*1024),
				$c ? "\033[0m" : ''));
		}
	}

} 