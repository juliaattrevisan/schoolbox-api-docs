<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Sentry\Tracing\Operation;

use Sentry\Breadcrumb as SentryBreadcrumb;

/**
 * A representation of an addition of a breadcrumb onto the transaction stack.
 */
class Breadcrumb
{
    public function __construct(public SentryBreadcrumb $breadcrumb)
    {
    }
}
