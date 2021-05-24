<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Forbidden;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\User;
use InvalidArgumentException;
use UnexpectedValueException;

class ValidAssessmentType extends AbstractAssessableRule
{

    /** @var string[] */
    private $validTypes;


    /**
     * @param string[] $validTypes
     */
    public function __construct(array $validTypes)
    {
        $this->validTypes = $validTypes;
    }


    /**
     * Ensures an assessment is of the correct type
     *
     * @param array     $params
     * @param User|null $user
     * @return bool
     * @throws Forbidden If assessment is not a valid type
     * @throws InvalidArgumentException If unable to locate an Assessment instance in $params
     * @throws UnexpectedValueException If passed assessment is not recognised
     */
    public function authorize(array $params = [], User $user = null)
    {
        $assessable     = $this->findAssessable($params);
        $assessmentType = AssessmentType::objectToString($assessable);
        if (\in_array($assessmentType, $this->validTypes, true)) {
            return true;
        }
        throw new Forbidden("Invalid assessment type. Found: {$assessmentType}");
    }
}
