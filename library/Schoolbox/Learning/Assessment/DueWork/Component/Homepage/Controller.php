<?php
/**
 * Due work homepage component controller
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use SitePage;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentDataMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;

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
        $this->setTermMapper(TermMapper::getInstance());
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $assessables = $this->getAssessmentDataMapper()
            ->findByColumn('d.category', $this->getSource()->getId())
            ->limit($this->getConfig('size'));

        return $this->renderTwigTemplate(
            $templateHandle,
            '@learningAssessment/dueWork/component.twig',
            array(
                'session'               => $_SESSION,
                'component_id'          => $this->getInstance()->getId(),
                'compact'               => $this->getConfig('compactMode'),
                'user'                  => $this->getUser(),
                'folder'                => $this->getSource(),
                'assessables'           => $assessables,
                'termCode'              => $this->getTermMapper()->findForToday()->getCode()
            ),
            $this->hasWriteAccess() || !$this->getConfig('compactMode')
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
