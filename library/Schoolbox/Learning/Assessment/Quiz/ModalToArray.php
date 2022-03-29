<?php

namespace Schoolbox\Learning\Assessment\Quiz;

use Schoolbox\Learning\Assessment\IAssessable;

class ModalToArray extends \Schoolbox\Learning\Assessment\ModelToArray
{
    /**
     * @param Model|IAssessable $assessment
     * @return array
     */
    public function __invoke($assessment): array
    {
        $tmp = parent::__invoke($assessment);
        $tmp['type'] = 'Quiz';
        $tmp['completionMessage'] = $assessment->getCompletionMessage();
        $tmp['duration'] = $assessment->getDuration();
        $tmp['maxAttempts'] = $assessment->getMaxAttempts();
        $tmp['questionSection'] = [];
        return $tmp;
    }
}
