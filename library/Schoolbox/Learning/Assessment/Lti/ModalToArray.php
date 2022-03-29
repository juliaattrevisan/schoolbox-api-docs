<?php

namespace Schoolbox\Learning\Assessment\Lti;

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
        $tmp['type'] = 'LTI';
        $tmp['provider'] = $assessment->getLtiTool()->getName();
        return $tmp;
    }
}
