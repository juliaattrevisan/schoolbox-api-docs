<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Alaress\Web\Controller;


interface IExceptionGenerator
{
    /**
     * Generates an exception of some sort.
     *
     * @param int             $errorCode         The error code used to generate the exception
     * @param \Exception|null $previousException The exception that got us here, if any
     * @param string          $additionalInfo    An error message. Probably interspersed with the
     *                                           exception's message somehow.
     * @param int             $httpStatusCode    The HTTP status code that should be raised by the
     *                                           request that made this exception happen.
     * @return \Exception
     */
    public function generateException(
        int $errorCode,
        \Exception $previousException = null,
        string $additionalInfo = '',
        int $httpStatusCode = 400
    ): \Exception;
}
