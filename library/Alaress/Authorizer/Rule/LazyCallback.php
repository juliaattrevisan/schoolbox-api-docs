<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;

/**
 * A lazy callback auth rule.
 *
 * Devised to get around the performance issues with creating rules with
 * expensive dependencies (e.g. database connections); the rule will only be
 * created if an authorization attempt occurs.
 */
class LazyCallback implements IAuthorizationRule
{
    /**
     * @var callable
     */
    private $createRuleCallback;

    /**
     * @var IAuthorizationRule
     */
    private $instantiatedRule;

    /**
     * Constructor
     *
     * @param callable $createRuleCallback A callback for creating a rule.
     *                                     No parameters are passed, and it
     *                                     must return a `IAuthorizationRule`.
     */
    public function __construct(callable $createRuleCallback)
    {
        $this->createRuleCallback = $createRuleCallback;
    }

    /**
     * Instantiates rule if it isn't yet instantiated, then authorizes against
     * that rule.
     *
     * @inheritdoc
     */
    public function authorize(array $params = array(), User $user = null): void
    {
        if (!$this->instantiatedRule) {
            $this->instantiatedRule = \call_user_func($this->createRuleCallback);
        }
        if (!$this->instantiatedRule instanceof IAuthorizationRule) {
            // Something's wrong
            throw new \InvalidArgumentException("Callback's return value must implement IAuthorizationRule");
        }
        $this->instantiatedRule->authorize($params, $user);
    }
}
