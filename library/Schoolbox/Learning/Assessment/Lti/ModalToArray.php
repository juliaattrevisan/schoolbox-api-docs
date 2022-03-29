<?php

namespace Schoolbox\Learning\Assessment\Lti;

use Schoolbox\Learning\Assessment\IAssessable;

class ModalToArray extends \Schoolbox\Learning\Assessment\ModelToArray
{
    /**
     * @param IAssessable $assessment
     * @return array
     */
    public function __invoke(IAssessable $assessment): array
    {
        $tmp = parent::__invoke($assessment);
        $tmp['type'] = 'LTI';
        return $tmp;
    }
}
