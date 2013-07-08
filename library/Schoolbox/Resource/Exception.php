<?php
/**
 * Exception for resource and upload related exceptions.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
 
namespace Schoolbox\Resource;

class Exception extends \Exception {
	const CODE_UNKNOWN = 0;

	//
	// User error codes
	const CODE_ACCESS_DENIED           = 1;
	const CODE_NO_ONLINE_SUBMISSION    = 2;
	const CODE_NO_MULTIPLE_SUBMISSION  = 3;
	const CODE_SUBMISSION_CLOSED       = 4;
	const CODE_SUBMISSION_NOT_YET_OPEN = 5;

	//
	// Internal error codes
	const CODE_COPY_TO_STORAGE_FAILED = 100;
}