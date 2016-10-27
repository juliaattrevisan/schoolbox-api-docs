<?php
/**
 * Quiz homepage component controller
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Quiz\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use SitePage;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentDataMapper;

class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage) {
        parent::__construct($instance, $user, $sitepage);
        $this->setAssessmentDataMapper(AssessmentDataMapper::getInstance());
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $allAssessable = $this->getAssessmentDataMapper()
            ->findByColumn('q.category_id', $this->getSource()->getId())
            ->limit($this->getConfig('size'));

        $futureAssessables = [];

        /** @var \Schoolbox\Learning\Assessment\DataModel\Model $assessable */
        foreach ($allAssessable as $assessable) {
            if (true || $assessable->getCloseDate()->isFuture()) {
                $futureAssessables[] = $assessable;
            }
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            '@learningAssessment/quiz/component.twig',
            array(
                'component_id'          => $this->getInstance()->getId(),
                'compact'               => $this->getConfig('compactMode'),
                'user'                  => $this->getUser(),
                'folder'                => $this->getSource(),
                'assessables'           => $futureAssessables
            ),
            true
        );
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
}
