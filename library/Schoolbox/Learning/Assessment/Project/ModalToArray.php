<?php

namespace Schoolbox\Learning\Assessment\Project;

use Schoolbox\Learning\Assessment\AssessmentType;
use Schoolbox\Learning\Assessment\IAssessable;

class ModalToArray extends \Schoolbox\Learning\Assessment\ModelToArray
{
    /**
     * @param IAssessable|Model $assessment
     * @return array
     */
    public function __invoke($assessment): array
    {
        $tmp = parent::__invoke($assessment);
        $tmp['type'] = 'Project';
        $tmp['assessments'] = [];
        foreach ($assessment->getAssessables() as $subAssessment) {
            switch (AssessmentType::objectToString($subAssessment)) {
                case 'quiz':
                    $tmp['assessments'][] = (new \Schoolbox\Learning\Assessment\Quiz\ModalToArray())($subAssessment);
                    break;
                case 'dueWork':
                    $tmp['assessments'][] = (new \Schoolbox\Learning\Assessment\DueWork\ModalToArray())($subAssessment);
                    break;
                case 'lti':
                    $tmp['assessments'][] = (new \Schoolbox\Learning\Assessment\Lti\ModalToArray())($subAssessment);
                    break;
                case 'project':
                    $tmp['assessments'][] = (new \Schoolbox\Learning\Assessment\Project\ModalToArray())($subAssessment);
                    break;
                case 'task':
                    $tmp['assessments'][] = (new \Schoolbox\Learning\Assessment\Task\ModalToArray())($subAssessment);
                    break;
                default:
                    $tmp['assessments'][] = (new \Schoolbox\Learning\Assessment\ModelToArray())($subAssessment);
            }
        }

        return $tmp;
    }
}
