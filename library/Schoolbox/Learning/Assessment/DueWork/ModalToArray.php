<?php

namespace Schoolbox\Learning\Assessment\DueWork;

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
        $tmp['type'] = 'DueWork';
        $tmp['markScheme'] = $assessment->getMarkScheme()->getTitle();
        $tmp['acceptsSubmission'] = $this->formatAcceptSubmission($assessment->getAcceptOnline());
        $tmp['rubric'] = [
//            'capabilityMarkScheme' => $assessment->getRubricGrid()->getCapabilityMarkScheme(),
            'grid' => $assessment->getRubricGrid(),
        ];
        $tmp['files'] = $assessment->getAttachedFiles();
        return $tmp;
    }

    /**
     * format acceptSubmission
     */
    private function formatAcceptSubmission(string $value)
    {
        switch ($value) {
            case Model::ACCEPT_ONLINE_NONE:
                return 'none';
            case Model::ACCEPT_ONLINE_SINGLE:
                return 'single';
            case Model::ACCEPT_ONLINE_MULTI:
                return 'multi';
        }
    }
}
