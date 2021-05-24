<?php

/**
 * Exception for resource and upload related exceptions.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\File;

class Exception extends \Exception
{
    const CODE_UNKNOWN = 0;

    //
    // User error codes
    const CODE_MIMETYPE_NOT_FOUND   = 1;
    const CODE_MIMETYPE_NOT_ALLOWED = 2;
    const CODE_FILE_TOO_LARGE       = 3;

    // System error codes
    const CODE_CANNOT_WRITE = 4;
    const CODE_CANNOT_READ  = 5;
}
