<?php

/**
 * Runtime exception with a HTTP status code.
 *
 * A generic exception that encapsulates the rudest of all Exceptions
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Application\Exception;

use Throwable;

class Exception extends \RuntimeException
{
    /**
     * The HTTP status code associated with this exception.
     *
     * @var int
     */
    protected $httpStatusCode = 400;

    /**
     * Extra HTTP information related to the exception.
     */
    protected string $information = '';


    /**
     * Constructor
     *
     * @param string         $message // the title of the error (inside the < h1 >)
     * @param int|null       $code
     * @param Throwable|null $previous
     * @param int|null       $httpStatusCode
     * @param string         $information // Extra information to be displayed on the error page (inside a < p >)
     */
    public function __construct(string $message = "", ?int $code = 0, ?Throwable $previous = null, ?int $httpStatusCode = null, string $information = '')
    {
        parent::__construct($message, $code, $previous);
        if ($httpStatusCode !== null) {
            $this->httpStatusCode = $httpStatusCode;
        }
        if ('' !== $information) {
            $this->information = $information;
        }
    }


    /**
     * @return int
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * @return string
     */
    public function getInformation(): string
    {
        return $this->information;
    }
}
