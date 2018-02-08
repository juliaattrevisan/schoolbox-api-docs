<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\InvalidPath;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\User;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use InvalidArgumentException;

class IsImplementation implements IAuthorizationRule
{

    /**
     * Check assessment is not a specification
     *
     * @inheritdoc
     * @throws InvalidArgumentException If unable to locate required params in route args
     */
    public function authorize(array $params = [], User $user = null)
    {
        if (!isset($params['assessment']) && !isset($params['participant'])) {
            throw new InvalidArgumentException('No assessment or participant preloaded');
        }
        /** @var IAssessable $assessment */
        $assessment = $params['assessment'] ?: $params['participant']->getAssessable();
        if ($assessment->getBaseContext() instanceof UnitSpecification\Model) {
            throw new InvalidPath('Assessment is a Specification');
        }
    }
}
