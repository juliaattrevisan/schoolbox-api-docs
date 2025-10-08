<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use Schoolbox\User;
use InvalidArgumentException;

/**
 * Similarity Check only exists on the Due Work assessment type
 * It does not apply to any other types; using this rule on something
 * other than a Due Work always results in Unauthorized
 */
class IsSimilarityCheckEnabled extends AbstractAssessableRule
{
    /**
     * Determine whether Similarity Check was activated for the given $assessment
     *
     * @inheritdoc
     * @throws InvalidArgumentException If unable to locate required params in route args
     */
    public function authorize(array $params = [], ?User $user = null)
    {
        $assessment = $this->findAssessable($params);

        if ($assessment instanceof DueWork) {
            if ($assessment->getSimilarityCheckEnabled()) {
                return;
            }

            throw new Unauthorized('Due Work has not enabled Similarity Check');
        }

        throw new Unauthorized('Only Due Works have Similarity Check');
    }
}
