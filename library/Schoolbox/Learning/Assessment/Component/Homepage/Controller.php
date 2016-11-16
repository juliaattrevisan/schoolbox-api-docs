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
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\AssessmentContextType;
use \Schoolbox\Learning\Assessment\Task\Model as Task;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\User;
use SitePage;

abstract class Controller extends Component_Homepage_ControllerAbstract
{
    /** @var IAssessable[]|Collection */
    protected $assessments;

    /** @var AssessmentMapperProxy $assessmentMapper */
    private $assessmentMapper;

    /** @var TermMapper $termMapper */
    private $termMapper;

    /** @var  Participant\DataSource */
    private $participantMapper;

    /**
     * Constructor
     *
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);

        /** @var AssessmentMapperProxy $assMap */
        $assMap = $this->getSitePage()->getDi()->get('assessmentDataSource');

        $this->setAssessmentMapper($assMap);
        $this->setTermMapper(TermMapper::getInstance());
        $this->participantMapper = $this->getSitePage()->getDi()->get('participantDataSource');
    }

    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     */
    public function execute()
    {
        if (!parent::execute()) {
            return false;
        }

        // TODO - this effectively hydrates all Assessment types in this Folder, then filters out
        // and discards 2/3 of results. Need to add a method, findAllForContextAndAssessmentType()
        try {
            $this->assessments = $this->getAssessmentMapper()
                ->findAllForFolderId($this->getSource()->getId())
                ->filter(
                    function (IAssessable $assessment)
                    {
                        return $this->filterAssessment($assessment) && $assessment->getCloseAt()->isFuture();
                    }
                )
                ->limit($this->getConfig('size'));

        } catch (\DomainException $e) {
            // if something goes wrong, this component will not be displayed
            return false;
        }

        /* Only show participation for tasks, as those can be altered inline
         * The current user's participation determines state of the task checkbox
         *
         * Don't need to load data for any other assessment type (due work, quiz, etc)
         */
        foreach($this->assessments as $assessment){
            if ($assessment instanceof Task) {
                try {
                    $participant = $this->participantMapper->findForAssessmentAndUser($assessment, $this->getUser());
                    $assessment->getAllParticipants()->add($participant);
                } catch (\OutOfBoundsException $e) {
                    // noop
                }
            }
        }

        return true;
    }

    /**
     * @return array
     */
    private function getTwigContext()
    {
        return [
            'user'         => $this->getUser(),
            'component_id' => $this->getInstance()->getId(),
            'compact'      => $this->getConfig('compactMode'),
            'context'      => $this->getSource(),
            'contextType'  => AssessmentContextType::TYPE_FOLDER.'s',
            'assessables'  => $this->assessments,
            'termCode'     => $this->getTermMapper()->findForToday()->getCode(),
        ];
    }

    /**
     * Renders view using Component_AbstractController::$templateObject.
     *
     * Responsible just for rendering objects already retrieved during execute()
     *
     * Should never be called if some sort of rendering is not to take place.
     *
     * @param String $templateHandle
     * @return boolean
     */
    public function renderTemplate($templateHandle)
    {
        return $this->renderTwigTemplate(
            $templateHandle,
            $this->getTwigTemplate(),
            $this->getTwigContext(),
            true
        );
    }

    /**
     * @return string
     */
    protected abstract function getTwigTemplate();

    /**
     * Return whether or not this assessment is of the relevant type for the current controller
     *
     * @param IAssessable $assessment
     * @return bool
     */
    protected abstract function filterAssessment(IAssessable $assessment);


    /**
     * @param AssessmentMapperProxy $assessmentDataMapper
     * @return $this
     */
    public function setAssessmentMapper(AssessmentMapperProxy $assessmentDataMapper) {
        $this->assessmentMapper = $assessmentDataMapper;
        return $this;
    }

    /**
     * @return AssessmentMapperProxy
     */
    public function getAssessmentMapper() {
        return $this->assessmentMapper;
    }

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
