<?php

/**
 * Composite authorization method - checks whether any of the rules passed at
 * instantiation pass.
 *
 * If none do, then an exception is thrown only from the last rule.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use Schoolbox\User;
use Alaress\Authorizer\Failure\Base;

class Any extends Composite
{

    /**
     * {@inheritdoc}
     */
    public function authorize(array $params = array(), User $user = null)
    {
        $lastException = null;
        foreach ($this->subRuleList as $subRule) {
            try {
                $subRule->authorize($params, $user);
                return true;
            } catch (Base $e) {
                $lastException = $e;
            }
        }

        // Did any exceptions fall through? Throw them if they did.
        if ($lastException instanceof \Exception) {
            throw $lastException;
        }
    }
}
