<?php
/**
 * Composite authorization method - checks whether any of the rules passed at
 * instantiation pass.
 *
 * If none do, then an exception is thrown only from the last rule.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Alaress\Authorizer\Rule;
use Schoolbox\User;
use Alaress\Authorizer\Failure\Base;
use InvalidArgumentException;

class Any implements IAuthorizationRule {

    protected $subRuleList = array();

    public function __construct() {
        foreach (func_get_args() as $subRule) {
            if (!($subRule instanceof IAuthorizationRule)) {
                throw new InvalidArgumentException(
                    'Sub-rules passed to Alaress\\Authorizer\\Rule\\Any must implement Alaress\\Authorizer\\Rule\\IAuthorizationRule'
                );
            }
            $this->subRuleList[] = $subRule;
        }
    }

    public function authorize(User $user = null) {
        $lastException = null;
        foreach ($this->subRuleList as $subRule) {
            try {
                $subRule->authorize($user);
                return true;
            }
            catch (Base $e) {
                $lastException = $e;
            }
        }

        // Did any exceptions fall through? Throw them if they did.
        if ($lastException instanceof \Exception) {
            throw $lastException;
        }
    }

}
