<?php
/**
 * Homepage component controller
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentDataMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;

class Controller
{
    /** @var Component_Homepage_ControllerAbstract  */
    private $component;

    /**
     * Controller constructor.
     * @param $component
     * @param $templateHandle
     */
    public function __construct($component) {
        $this->component = $component;
        $this->setAssessmentDataMapper(AssessmentDataMapper::getInstance());
        $this->setTermMapper(TermMapper::getInstance());
    }

    /**
     * @return array
     */
    public function render()
    {
        $assessables = [];

        $template = '';

        switch (get_class($this->component)) {
            case 'Schoolbox\Learning\Assessment\DueWork\Component\Homepage\Controller':

                $template = '@learningAssessment/dueWork/component.twig';

                $allAssessable = $this->getAssessmentDataMapper()
                    ->findByColumn('d.category', $this->component->getSource()->getId())
                    ->limit($this->component->getConfig('size'));

                /** @var \Schoolbox\Learning\Assessment\DataModel\Model $assessable */
                foreach ($allAssessable as $assessable) {
                    if (true || $assessable->getCloseDate()->isFuture()) {
                        $assessables[] = $assessable;
                    }
                }
                break;

            case 'Schoolbox\Learning\Assessment\Quiz\Component\Homepage\Controller':

                $template = '@learningAssessment/quiz/component.twig';

                $allAssessable = $this->getAssessmentDataMapper()
                    ->findByColumn('q.category_id', $this->component->getSource()->getId())
                    ->limit($this->component->getConfig('size'));

                /** @var \Schoolbox\Learning\Assessment\DataModel\Model $assessable */
                foreach ($allAssessable as $assessable) {
                    if (true || $assessable->getCloseDate()->isFuture()) {
                        $assessables[] = $assessable;
                    }
                }

                break;

            case 'Schoolbox\Learning\Assessment\Task\Component\Homepage\Controller':

                $template = '@learningAssessment/task/component.twig';

                $assessables = $this->getAssessmentDataMapper()->findByColumn('t.category_id', $this->component->getSource()->getId());

                break;
        }

        return [
            'template' => $template,
            'data' => [
                'component_id'          => $this->component->getInstance()->getId(),
                'compact'               => $this->component->getConfig('compactMode'),
                'folder'                => $this->component->getSource(),
                'assessables'           => $assessables,
                'termCode'              => $this->getTermMapper()->findForToday()->getCode()
                ]
        ];
    }

    /** @var AssessmentDataMapper $assessmentDataMapper */
    private $assessmentDataMapper;

    /**
     * @param $assessmentDataMapper
     * @return $this
     */
    public function setAssessmentDataMapper($assessmentDataMapper) {
        $this->assessmentDataMapper = $assessmentDataMapper;
        return $this;
    }

    /**
     * @return AssessmentDataMapper
     */
    public function getAssessmentDataMapper() {
        return $this->assessmentDataMapper;
    }

    /** @var TermMapper $termMapper */
    private $termMapper;

    /**
     * @param $termMapper
     * @return $this
     */
    public function setTermMapper($termMapper) {
        $this->termMapper = $termMapper;
        return $this;
    }

    /**
     * @return TermMapper
     */
    public function getTermMapper() {
        return $this->termMapper;
    }
}
