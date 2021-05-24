<?php

/**
 * Composite authorization method - checks whether all of the rules passed at
 * instantiation pass.
 *
 * If any doesn't, then an exception is thrown from the first failing rule.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;

class All extends Composite
{

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        $lastException = null;
        foreach ($this->subRuleList as $subRule) {
            $subRule->authorize($params, $user);
        }
        return true;
    }
}
