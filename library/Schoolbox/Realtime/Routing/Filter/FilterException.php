<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Realtime\Routing\Filter;

use DomainException;
use Exception;

class FilterException extends DomainException
{
    /**
     * @param string       $message
     * @param string|array $expected
     * @param string|array $actual
     * @param int          $code
     * @param Exception    $previous
     */
    public function __construct($message = "", $expected = null, $actual = null, $code = 0, Exception $previous = null)
    {
        if (isset($expected)) {
            $message = sprintf(
                "%s; expected %s%s, got %s.",
                $message,
                is_array($expected) ? ' one of ' : '',
                json_encode($expected),
                json_encode($actual)
            );
        }

        parent::__construct($message, $code, $previous);
    }
}
