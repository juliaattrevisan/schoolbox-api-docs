<?php
/**
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\User;
use Schoolbox\Comms\News\Policy;

class IsModerationEnabled implements IAuthorizationRule
{

    /** @var Policy\Mapper */
    private $policyMapper;


    /**
     * @param Policy\Mapper $policyMapper
     */
    public function __construct(Policy\Mapper $policyMapper)
    {
        $this->policyMapper = $policyMapper;
    }


    /**
     * Checks that global moderation is currently enabled
     *
     * @inheritdoc
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     */
    public function authorize(array $params = [], User $user = null): void
    {
        $pb = $this->policyMapper->getPredicateBuilder()->equals('identifier', 'global_moderation');
        if (!$this->policyMapper->findScalarByPredicates('value', $pb)[0]) {
            throw new Unauthorized('Global moderation is disabled');
        }
    }
}
