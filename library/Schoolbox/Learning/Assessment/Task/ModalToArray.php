<?php

namespace Schoolbox\Learning\Assessment\Task;

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
        $tmp['type'] = 'Task';
        $tmp['link'] = $assessment->getUrl();
        $tmp['teacherConfirmation'] = $assessment->getIsSelfAssessable();
        return $tmp;
    }
}
