<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\InvalidPath;
use Schoolbox\User;
use InvalidArgumentException;

class AssessmentNotDeleted extends AbstractAssessableRule
{

    /**
     * Ensures the assessment is not soft-deleted
     *
     * @inheritdoc
     * @throws InvalidArgumentException If an IAssessable was unable to be found in route params
     */
    public function authorize(array $params = [], User $user = null)
    {
        $assessable = $this->findAssessable($params);
        if ($assessable->isDeleted()) {
            throw new InvalidPath("Assessment (id: {$assessable->getId()}) is deleted");
        }
    }
}
