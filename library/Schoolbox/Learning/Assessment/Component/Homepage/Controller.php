<?php
/**
 * Homepage component controller
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Component\Homepage;

use Alaress_Date_Format;
use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\AssessmentContextType;
use Schoolbox\Learning\Assessment\DueDateSortStrategy;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use SitePage;

abstract class Controller extends Component_Homepage_ControllerAbstract
{
    /** @var IAssessable[]|Collection */
    private $assessments;

    /** @var AssessmentMapperProxy $assessmentMapper */
    private $assessmentMapper;

    /** @var TermMapper $termMapper */
    private $termMapper;

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
            $visibility = new Visibility($this->getAccess());

            // Note: findAllByFolderIdForReview retrieves response+feedback for all
            // assessment types, not just tasks (as it did previously).
            $options = [
                'type'  => $this->getAssessmentType(),
                'order' => ['due_date' => 'asc'],
            ];

            if(!$this->getConfig('showClosed')){
                $options['closed_before'] = Alaress_Date_Format::now();
            }

            $this->assessments = $this->getAssessmentMapper()
                ->findAllByFolderIdForReview(
                    $this->getSource()->getId(),
                    $this->getUser(),
                    $options
                )
                ->filter(
                    function (IAssessable $assessment) use ($visibility)
                    {
                        return $visibility->isVisibleToUser($assessment, $this->getUser(), $this->getConfig('showClosed'));
                    }
                )
                ->sortByCallback(new DueDateSortStrategy($this->getUser()))
                ->limit($this->getConfig('size') ?: null);

        } catch (\DomainException $e) {
            // if something goes wrong, this component will not be displayed
            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    private function getTwigContext()
    {
        return [
            'component_id' => $this->getInstance()->getId(),
            'compact'      => $this->getConfig('compactMode'),
            'context'      => $this->getSource(),
            'contextType'  => AssessmentContextType::TYPE_FOLDER.'s',
            'assessables'  => $this->assessments,
            'termCode'     => $this->getTermMapper()->findForToday()->getCode(),
            'readOnly'     => ($this->getContentPermission() <= UserAccess::ACCESS_READ),
            'canWrite'     => ($this->getContentPermission() >= UserAccess::ACCESS_WRITE),
        ];
    }

    /**
     * Retrieves date for sorting out of an assessment.
     *
     * @param IAssessable $assessment
     * @return Alaress_Date_Format|null
     */
    private function retrieveDateForSort(IAssessable $assessment): ?Alaress_Date_Format
    {
        if ($assessment->isParticipant($this->getUser())) {
            $participant = $assessment->getParticipantIfExtant($this->getUser());
            if ($participant instanceof Participant) {
                return $participant->getDueDate();
            }
        }
        return $assessment->getDueAt();
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
     * Return the type of assessment to retrieve
     *
     * @return string
     */
    protected abstract function getAssessmentType();

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
