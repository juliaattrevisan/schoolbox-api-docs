<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Authorizer\Rule;

use Alaress\Authorizer\Failure\Forbidden;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\User;
use InvalidArgumentException;
use UnexpectedValueException;

class ValidAssessmentType implements IAuthorizationRule
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
        $assessable     = $this->findAssessment($params);
        $assessmentType = AssessmentType::objectToString($assessable);
        if (\in_array($assessmentType, $this->validTypes, true)) {
            return true;
        }
        throw new Forbidden("Invalid assessment type. Found: {$assessmentType}");
    }


    /**
     * @param array $params
     * @return IAssessable
     * @throws InvalidArgumentException If unable to locate an Assessment instance in $params
     * @todo Move this method to an assessment rule abstract
     */
    private function findAssessment(array $params): IAssessable
    {
        if (isset($params['assessment']) && $params['assessment'] instanceof IAssessable) {
            return $params['assessment'];
        }
        if (isset($params['participant']) && $params['participant'] instanceof Participant) {
            /** @var Participant $participant */
            $participant = $params['participant'];
            return $participant->getAssessable();
        }
        throw new \InvalidArgumentException('Unable to locate an assessment instance in $params');
    }
}
