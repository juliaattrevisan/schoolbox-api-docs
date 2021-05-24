<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Specification;

/**
 * Represents a single atomic assertion.
 *
 * May be framework-supplied classes, or custom domain-specific
 *
 * Framework examples:
 *  * colour is blue
 *  * height > 130kg
 *  * instanceof Cat
 *
 * Custom domain examples:
 * * Owner is a member of STAFF role
 */
abstract class Leaf extends Base
{

}
