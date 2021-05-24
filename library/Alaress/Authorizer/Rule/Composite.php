<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Alaress\Authorizer\Rule;

/**
 * Abstract composite authorization method, the base class for the All and Any
 * rules.
 */
abstract class Composite implements IAuthorizationRule
{
    /**
     * @var array|IAuthorizationRule[]
     */
    protected $subRuleList = [];

    /**
     * Constructor
     *
     * @param IAuthorizationRule ...$subRules
     */
    public function __construct(IAuthorizationRule ...$subRules)
    {
        foreach ($subRules as $subRule) {
            $this->addRule($subRule);
        }
    }

    /**
     * Adds a rule to the end of the chain.
     *
     * @param IAuthorizationRule $subRule
     * @return $this
     */
    public function addRule(IAuthorizationRule $subRule)
    {
        $this->subRuleList[] = $subRule;
        return $this;
    }

    /**
     * Adds a rule to the front of the chain.
     *
     * If you have a rule you want to check before any others, you should use
     * this method.
     *
     * @param IAuthorizationRule $subRule
     * @return $this
     */
    public function prependRule(IAuthorizationRule $subRule)
    {
        array_unshift($this->subRuleList, $subRule);
        return $this;
    }
}
