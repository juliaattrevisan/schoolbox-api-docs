<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\InvalidPath;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use InvalidArgumentException;

class IsSpecification extends AbstractAssessableRule
{

    /**
     * Check assessment is a specification
     *
     * @inheritdoc
     * @throws InvalidArgumentException If unable to locate required params in route args
     */
    public function authorize(array $params = [], User $user = null)
    {
        if (!$this->findAssessable($params)->getBaseContext() instanceof UnitSpecification\Model) {
            throw new InvalidPath('Assessment is not a Specification');
        }
    }
}
