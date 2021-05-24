<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Web\Controller;

interface IExceptionGenerator
{
    /**
     * Generates an exception of some sort.
     *
     * @param int|null        $errorCode         The error code used to generate the exception
     * @param \Throwable|null $previous          The throwable that got us here, if any
     * @param string          $additionalInfo    An error message. Probably interspersed with the
     *                                           exception's message somehow.
     * @param int             $httpStatusCode    The HTTP status code that should be raised by the
     *                                           request that made this exception happen.
     * @return \Exception
     */
    public function generateException(
        ?int $errorCode,
        \Throwable $previous = null,
        string $additionalInfo = '',
        int $httpStatusCode = 400
    ): \Exception;
}
