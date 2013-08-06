<?php
/**
 * Exception for resource and upload related exceptions.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\File;

class Exception extends \Exception {
	const CODE_UNKNOWN = 0;

	//
	// User error codes
	const CODE_MIMETYPE_NOT_FOUND = 1;
	const CODE_FILE_TOO_LARGE     = 2;
}