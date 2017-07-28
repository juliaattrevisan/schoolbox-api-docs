<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Database;

use PDOException;

trait HandlesExceptionTrait
{
    /** @var string $errorInfo If the last statement execution returned an error- this is its message */
    protected $errorInfo;

    /** @var string $errorCode If the last statement execution returned an error- this is its code */
    protected $errorCode;

    /**
     * If we get a PDO exception running a query we DO want to terminate application
     * execution (or at least force something up the stack to handle this issue).
     *
     * @param PDOException $e
     * @param string $prefix Prefix the returned exception's message with this
     * @throws Exception
     */
    protected function handleException(PDOException $e, $prefix = '')
    {
        $this->errorInfo = $e->getMessage();
        $this->errorCode = $e->getCode();
        // Query exceptions are usually logic exceptions, not runtime exceptions.
        // However, PDOException extends RuntimeException, so...
        // (PHP5.5 refuses to accept a string as $code parameter, so pass 0)
        throw new Exception($prefix . $e->getMessage(), 0, $e);
    }
}
