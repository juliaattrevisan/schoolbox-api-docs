<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Web\Response;

use Psr\Http\Message\ResponseInterface;

/**
 * Common interface for all response factories.
 */
interface IFactory
{
    /**
     * Builds a PSR-7 response.
     *
     * @return ResponseInterface
     */
    public function build(): ResponseInterface;
}
