<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;

/**
 * A lazy wrapper around an auth rule.
 *
 * Devised to get around the performance issues with creating rules with
 * expensive dependencies (e.g. database connections); these will only be
 * created if an authorization attempt occurs.
 */
abstract class LazyWrapper implements IAuthorizationRule
{
    /**
     * An authorization rule.
     *
     * Won't be created until `authorize` is called for the first time.
     *
     * @var IAuthorizationRule
     */
    private $instantiatedRule;

    /**
     * Instantiates rule if it isn't yet instantiated, then
     *
     * @inheritdoc
     */
    public function authorize(array $params = [], User $user = null): void
    {
        if (!$this->instantiatedRule) {
            $this->instantiatedRule = $this->instantiateRule();
        }
        $this->instantiatedRule->authorize($params, $user);
    }

    /**
     * Instantiates the rule.
     *
     * @return IAuthorizationRule
     */
    abstract protected function instantiateRule(): IAuthorizationRule;
}
