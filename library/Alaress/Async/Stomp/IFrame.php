<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Async\Stomp;

/**
 * Standard interface for Stomp message frames.
 */
interface IFrame
{
    /**
     * Retrieves the body out of the frame.
     *
     * @return string
     */
    public function getBody(): string;

    /**
     * Retrieves a named header out of the frame.
     *
     * @param string $headerName
     * @return mixed
     */
    public function getHeader(string $headerName);
}
