<?php
/**
 * Course component for a class.
 * Before a course is selected, it contains a selector for the course - after
 * this, it contains a list of units and assessments within the course.
 *
 * This is the Course Outline component.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2013, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Component\Homepage\Outline;

use Component_Instance;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;
use \Schoolbox\Permission\Access\UserAccess;
use \Schoolbox\Learning\Assessment\Redirect;
use Component_Homepage_ControllerAbstract;
use Schoolbox\User;
use SitePage;
use \Schoolbox\Permission\Access\UserAccessMapper;
use Alaress\Database\Predicate\InvalidColumnException;
use DomainException;
use LogicException;
use Schoolbox\Mapper\DefinitionException;
use TypeError;
use UnexpectedValueException;
use Alaress_Date_Format;

class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * The CourseImport currently associated with this Component
     *
     * @var CourseImplementation|null
     */
    private $courseImplementation;

    /**
     * @var bool
     */
    private $readOnly;

    /**
     * @var bool
     */
    private $canWrite;

    /**
     * @var CourseImplementationMapper
     */
    private $courseImplementationMapper;

    /**
     * @var UnitImplementationMapper
     */
    private $unitImplementationMapper;

    /**
     * @var AssessmentMapperProxy
     */
    private $assessmentMapperProxy;

    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * Controller constructor.
     *
     * @param Component_Instance $instance
     * @param User $user
     * @param SitePage $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);
        $this
            ->setCourseImplementationMapper($this->getMapperFactory()->get(CourseImplementationMapper::class))
            ->setUnitImplementationMapper($this->getMapperFactory()->get(UnitImplementationMapper::class))
            ->setAssessmentMapperProxy($this->getSitePage()->getDi()->get('assessmentDataSource'))
            ->setUserAccessMapper($this->getMapperFactory()->get(UserAccessMapper::class));
    }


    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     * @throws MapperException
     * @throws InvalidColumnException
     * @throws DomainException
     * @throws LogicException
     * @throws DefinitionException
     * @throws TypeError
     * @throws UnexpectedValueException
     */
    public function execute()
    {
        $this->readOnly = $this->getContentPermission() < UserAccess::ACCESS_WRITE;
        $this->canWrite = $this->getContentPermission() >= UserAccess::ACCESS_WRITE;
        // Get the list of units for this course, as well as who has actually
        // completed each unit.

        $courseImplId = $this->getConfig('importedCourse');

        $this->courseImplementation = null;
        try {
            $this->courseImplementation = $this->courseImplementationMapper->findById($courseImplId);
            $this->courseImplementation->setUnits($this->unitImplementationMapper->findAllByCourseId($courseImplId));

            // Load all assessments in a single query
            $uiIds = $this->courseImplementation->getUnits()->getPropertyValues('id');

            if ($this->readOnly) {
                // I might be a learner (or parent of learner), but can't be anything more.
                // In any case, load only my interactions - if I'm not a participant, there will be nothing.
                // also omit anything that is not yet published (there's no easy way to apply this filter to the query)

                $visibility  = new Visibility($this->getAccess());
                $isParent    = $this->getUser()->getRole()->isParent();
                $now         = Alaress_Date_Format::now();
                $assessments = $this->assessmentMapperProxy->findAllByUnitImplementationIdsForCourseOutline(
                    $uiIds,
                    $this->getUser()
                )->filter(function (IAssessable $activity) use ($visibility, $isParent, $now) {
                    // Check user's own participation
                    if ($visibility->isVisibleToUser($activity, $this->getUser(), true)) {
                        return true;
                    }
                    // If a parent, check their children's participations, un-setting children who do not have visibility
                    $isVisibleViaChild = false;
                    if ($isParent) {
                        foreach ($this->getUser()->getChildren() as $child) {
                            if ($visibility->isVisibleToLearner($activity, $child, true, $now)) {
                                $isVisibleViaChild = true;
                            } elseif ($activity->isParticipant($child)) {
                                $activity->unsetUserParticipation($child);
                            }
                        }
                    }
                    return $isVisibleViaChild;
                });
            } else {
                // I'm an instructor - load all participants.
                $assessments = $this->assessmentMapperProxy->findAllByUnitImplementationIdsForCourseOutline($uiIds);
            }

            // And then add them to the displayed course impl
            $assessments = $assessments
                ->groupByCallback(function (IAssessable $assessment) {
                    return $assessment->getContext()->getId();
                });

            foreach ($this->courseImplementation->getUnits() as $unitImpl) {
                if (isset($assessments[$unitImpl->getId()])) {
                    $unitImpl->setAssessables($assessments[$unitImpl->getId()]);
                }
            }
        } catch (MapperException $e) {
            if (ConfigMapper::getInstance()->get('debug_mode')) {
                throw $e;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function renderTemplate($templateHandle)
    {
        $folder = $this->getSource();

        // Ensure this CourseBuilder component is being placed on a Folder which has a UnitSpec
        $hasUnitSpec = $folder->getUnitSpecification() instanceof UnitSpecification && $folder->getUnitSpecification()->getId();
        $hasCourseSpec = $folder->getCourseSpecification() instanceof CourseSpecification && $folder->getCourseSpecification()->getId();
        if (($hasUnitSpec || $hasCourseSpec) && !$this->readOnly){
            return $this->renderContextInvalidEmptyState($templateHandle, $hasUnitSpec, $hasCourseSpec);
        }

        $data = [
            'readOnly'             => $this->readOnly,
            'canWrite'             => $this->canWrite,
            'context'              => $this->getContext(),
            'courseSpecification'  => $this->courseImplementation ? $this->courseImplementation->getSpecification() : null,
            'courseImplementation' => $this->courseImplementation,
            'componentInstance'    => $this->getInstance(),
            'returnUrlName'        => Redirect::RETURN_URL,
            'encodedReturnUrl'     => Redirect::encode('/homepage/' . $this->getContext()->getId() . '#component' . $this->getInstance()->getId())
        ];
        
        return $this->renderTwigTemplate(
            $templateHandle,
            '@learningAssessment/outline/component.twig',
            $data,
            $this->hasWriteAccess() || !$this->getConfig('compactMode')
        );
    }

    /**
     * Renders a component-specific empty state to indicate that the folder on which
     * this CourseOutline component is placed is not a valid context.
     *
     * Not valid if this folder is:
     * * linked to a CourseSpec
     * * linked to a UnitSpec
     *
     * @param string $templateHandle
     * @param bool $hasUnitSpec
     * @param bool $hasCourseSpec
     * @return bool
     */
    protected function renderContextInvalidEmptyState($templateHandle, $hasUnitSpec, $hasCourseSpec)
    {
        $data = [
            'componentInstance' => $this->getInstance(),
            'hasUnitSpec'       => $hasUnitSpec,
            'hasCourseSpec'     => $hasCourseSpec,
        ];

        return $this->renderTwigTemplate(
            $templateHandle,
            '@learningAssessment/outline/emptyStateContextInvalid.twig',
            $data,
            $this->hasWriteAccess() || !$this->getConfig('compactMode')
        );
    }

    /* Getters / Setters
     */

    /**
     * @return CourseImplementationMapper
     */
    public function getCourseImplementationMapper()
    {
        return $this->courseImplementationMapper;
    }

    /**
     * @param CourseImplementationMapper $courseImplementationMapper
     * @return static
     */
    public function setCourseImplementationMapper(CourseImplementationMapper $courseImplementationMapper)
    {
        $this->courseImplementationMapper = $courseImplementationMapper;
        return $this;
    }

    /**
     * @return UnitImplementationMapper
     */
    public function getUnitImplementationMapper()
    {
        return $this->unitImplementationMapper;
    }

    /**
     * @param UnitImplementationMapper $unitImplementationMapper
     * @return static
     */
    public function setUnitImplementationMapper(UnitImplementationMapper $unitImplementationMapper)
    {
        $this->unitImplementationMapper = $unitImplementationMapper;
        return $this;
    }

    /**
     * @return AssessmentMapperProxy
     */
    public function getAssessmentMapperProxy()
    {
        return $this->assessmentMapperProxy;
    }

    /**
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @return static
     */
    public function setAssessmentMapperProxy(AssessmentMapperProxy $assessmentMapperProxy)
    {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        return $this;
    }

    /**
     * @return UserAccessMapper
     */
    public function getUserAccessMapper()
    {
        return $this->userAccessMapper;
    }

    /**
     * @param UserAccessMapper $userAccessMapper
     * @return static
     */
    public function setUserAccessMapper(UserAccessMapper $userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
        return $this;
    }
}
