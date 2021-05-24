<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Database\Predicate\Test;

use Alaress\Database\Predicate\Builder;
use Alaress\Database\Quoter\IQuoter;

/**
 * Exists purely for testing purposes. Primarily to expose otherwise private properties.
 */
class TestableBuilder extends Builder
{
    /**
     * Exposes the otherwise-private quoter
     *
     * @return IQuoter
     */
    public function getQuoter(): IQuoter
    {
        return $this->quoter;
    }
}
