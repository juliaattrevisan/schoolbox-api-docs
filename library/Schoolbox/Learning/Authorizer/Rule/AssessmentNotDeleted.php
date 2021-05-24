<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Schoolbox\Application\Exception\NotFound;
use Schoolbox\User;
use InvalidArgumentException;

/**
 * Class AssessmentNotDeleted
 *
 * @package Schoolbox\Learning\Authorizer\Rule
 */
class AssessmentNotDeleted extends AbstractAssessableRule
{

    /**
     * Ensures the assessment is not soft-deleted
     *
     * @inheritdoc
     * @throws NotFound If an IAssessable was unable to be found in route params
     * @throws InvalidArgumentException
     */
    public function authorize(array $params = [], User $user = null)
    {
        $assessable = $this->findAssessable($params);

        if ($assessable->isDeleted()) {
            throw new NotFound("Assessment (id: {$assessable->getId()}) is deleted");
        }
    }
}
