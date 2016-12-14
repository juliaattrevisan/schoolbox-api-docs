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
use Schoolbox\Assessment\Course\Task\Completion as TaskCompletion;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Collection;
use \Schoolbox\Permission\Access\UserAccess;
use \Schoolbox\Learning\Assessment\Redirect;
use Component_Homepage_ControllerAbstract;
use Schoolbox\User;
use SitePage;
use \Schoolbox\Permission\Access\UserAccessMapper;

class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * The CourseImport currently associated with this Component
     *
     * @var CourseImplementation|null
     */
    private $courseImplementation;

    /** @var int[] */
    private $accessList = [];

    /**
     *
     * @var Collection|User[]
     */
    private $userCollection;

    /**
     * A list of students who have completed the assessments within the courseImpl.
     *
     * @var Collection|TaskCompletion[]
     */
    private $completionList;

    /**
     * A list of students for this category.
     *
     * @var User[]
     */
    private $studentList = array();

    /**
     * @var bool
     */
    private $readOnly;

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
     */
    public function execute()
    {
        $this->readOnly = $this->getContentPermission() < UserAccess::ACCESS_WRITE;

        // Get the list of units for this course, as well as who has actually
        // completed each unit.

        $courseImplId = $this->getConfig('importedCourse');

        $this->courseImplementation = null;
        try {
            $this->courseImplementation = $this->courseImplementationMapper->findById($courseImplId);
            $this->courseImplementation->setUnits($this->unitImplementationMapper->findAllByCourseId($courseImplId));

            foreach ($this->courseImplementation->getUnits() as $unitImpl) {
                if ($this->readOnly) {
                    // I might be a student, but can't be anything more.
                    // In any case, load only my participation - if I'm not a participant, there will be nothing.
                    $assessments = $this->assessmentMapperProxy->findAllByUnitImplementationIdForCourseOutline(
                        $unitImpl->getId(),
                        $this->getUser()
                    );
                } else {
                    // I'm a teacher - load all participants.
                    $assessments = $this->assessmentMapperProxy->findAllByUnitImplementationIdForCourseOutline(
                        $unitImpl->getId()
                    );
                }
                $unitImpl->setAssessables($assessments);

//                $unitId = $unitImpl->getSpecification()->getFolder()->getId();
//                $this->accessList[$unitId] = Access::getInstance()->getFolderAccess($this->getUser(), [$unitId]);
            }

            // TODO get permissions working, I guess
//            $this_student_id = null;
//            if ($this->readOnly) {
//                $this_student_id = $this->getUser()->getId();
//            }
            /*$this->completion_list = Task\CompletionMapper::getInstance()->findLatestForList(
              $task_list, $this_student_id
            );*/

        } catch (MapperException $e) {
            // noop
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
        if($hasUnitSpec || $hasCourseSpec){
            return $this->renderContextInvalidEmptyState($templateHandle, $hasUnitSpec, $hasCourseSpec);
        }

        $data = [
            'currentUser'          => $this->getUser(),
            'readOnly'             => $this->readOnly,
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
