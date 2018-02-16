<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\InvalidPath;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use InvalidArgumentException;

class IsImplementation extends AbstractAssessableRule
{

    /**
     * Check assessment is not a specification
     *
     * @inheritdoc
     * @throws InvalidArgumentException If unable to locate required params in route args
     */
    public function authorize(array $params = [], User $user = null)
    {
        if ($this->findAssessable($params)->getBaseContext() instanceof UnitSpecification\Model) {
            throw new InvalidPath('Assessment is a Specification');
        }
    }
}
