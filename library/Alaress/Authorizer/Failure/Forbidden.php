<?php
/**
 * Base authorization method - contains a session object.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Failure;

class Forbidden extends Base {
    /**
     * Default error code - corresponds to message "you do not have permission..."
     *
     * @var int
     */
    protected $code = 19;
}
