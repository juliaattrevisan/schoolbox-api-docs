<?php
/**
 * Abstract composite authorization method, the base class for the All and Any
 * rules.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;
use InvalidArgumentException;

abstract class Composite implements IAuthorizationRule {

    /**
     * @var array|IAuthorizationRule[]
     */
    protected $subRuleList = array();

    /**
     * @throws InvalidArgumentException if any param is not a IAuthorizationRule
     */
    public function __construct() {

        /* An unlimited number of authorization rules.
         */
        foreach (func_get_args() as $subRule) {
            if (!($subRule instanceof IAuthorizationRule)) {
                throw new InvalidArgumentException(
                    'Sub-rules passed to Alaress\\Authorizer\\Rule\\Any must implement Alaress\\Authorizer\\Rule\\IAuthorizationRule'
                );
            }
            $this->addRule($subRule);
        }
    }

    /**
     * Adds a rule to the end of the chain.
     *
     * @param  IAuthorizationRule $subRule
     * @return $this
     */
    public function addRule(IAuthorizationRule $subRule) {
        $this->subRuleList[] = $subRule;
        return $this;
    }

    /**
     * Adds a rule to the front of the chain.
     *
     * If you have a rule you want to check before any others, you should use
     * this method.
     *
     * @param  IAuthorizationRule $subRule
     * @return $this
     */
    public function prependRule(IAuthorizationRule $subRule) {
        array_unshift($this->subRuleList, $subRule);
        return $this;
    }

}
