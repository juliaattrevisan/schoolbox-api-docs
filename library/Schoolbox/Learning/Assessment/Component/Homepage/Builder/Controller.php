<?php
/**
 * Course builder homepage component controller
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\Component\Homepage\Builder;

use Component_Homepage_ControllerAbstract;
use Component_Instance;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;
use SitePage;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;

class Controller extends Component_Homepage_ControllerAbstract
{
    /** @var UnitImplementationMapper */
    protected $unitImplementationMapper;

    /** @var UnitSpecificationMapper */
    protected $unitSpecificationMapper;

    /** @var AssessmentMapperProxy */
    protected $assessmentMapperProxy;

    /**
     * Injectable constructor
     *
     * @param Component_Instance $instance
     * @param User $user
     * @param SitePage $sitepage
     */
    public function __construct(Component_Instance $instance, User $user, SitePage $sitepage)
    {
        parent::__construct($instance, $user, $sitepage);

        $this->unitSpecificationMapper = $this->getMapperFactory()->get(UnitSpecificationMapper::class);
        $this->unitImplementationMapper = $this->getMapperFactory()->get(UnitImplementationMapper::class);
        $this->assessmentMapperProxy = $assessmentProxy = $this->getSitePage()->getDi()->get('assessmentDataSource');
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        if(!parent::execute()){
            return false;
        }
        return $this->getContentPermission() >= UserAccess::ACCESS_WRITE;
    }

    /**
     * @param String $templateHandle
     * @return bool
     */
    public function renderTemplate($templateHandle)
    {
        $unitSpecMapper = $this->getUnitSpecificationMapper();
        $unitImplMapper = $this->getUnitImplementationMapper();
        $assessmentMapperProxy = $this->getAssessmentMapperProxy();

        // Get the current folder and hydrate its parent
        $folder = $this->getSource();

        // Ensure this CourseBuilder component is being placed on a Folder which has a UnitSpec
        if(!$folder->getUnitSpecification() instanceof UnitSpecification || !$folder->getUnitSpecification()->getId()){
            return $this->renderNotAUnitSpecification($templateHandle);
        }

        // Get the unit's details
        try {
            $unitSpec = $unitSpecMapper->findByFolderId($folder->getId());
            $options = ['order' => ['sequence' => 'ASC']];
            $assessables = $assessmentMapperProxy->findAllByUnitSpecificationId($unitSpec->getId(), $options);
            $unitSpec->setAssessables($assessables);


            $data = [
                'unitSpec'         => $unitSpec,
                'returnUrlName'    => Assessment\Redirect::RETURN_URL,
                'encodedReturnUrl' => Assessment\Redirect::encode('/homepage/'.$folder->getId()),
            ];
        } catch (MapperException $e) {
            // noop
        }
        $data['componentInstance'] = $this->getInstance();

        // Retrieve all of the parents for use as breadcrumbs
        if ($this->getContext() instanceof Folder) {
            foreach (FolderMapper::getInstance()->findParents($folder, null, true) as $parent) {
                $data['breadcrumbs'][] = $parent;
            }
        }

        return $this->renderTwigTemplate(
            $templateHandle,
            '@learningAssessment/builder/component.twig',
            $data,
            $this->hasWriteAccess() || !$this->getConfig('compactMode')
        );

    }

    /**
     * Renders a component-specific empty state to indicate that the folder on which
     * this CourseBuilder component is placed is not linked to a UnitSpecification.
     *
     * Prompts the user to manage Courses and Units via the CourseBuilder workflow
     *
     * @param string $templateHandle
     * @return bool
     */
    protected function renderNotAUnitSpecification($templateHandle)
    {
        $folder = $this->getSource();
        $parent = $this->getFolderMapper()->findById($folder->getParent()->getId());

        $folderCourse = $folder->getCourseSpecification();
        $parentCourse = $parent->getCourseSpecification();
        $parentUnit = $parent->getUnitSpecification();

        $folderHasClass = $folder->getCodes()->count() > 0;
        $folderHasCourse = $folderCourse instanceof CourseSpecification && $folderCourse->getId();
        $parentHasCourse = $parentCourse instanceof CourseSpecification && $parentCourse->getId();
        $parentHasUnit = $parentUnit instanceof UnitSpecification && $parentUnit->getId();

        $data = [
            'componentInstance' => $this->getInstance(),
            'folder'            => $folder,
            'folderHasClass'    => $folderHasClass,
            'folderHasCourse'   => $folderHasCourse,
            'parentHasCourse'   => $parentHasCourse,
            'parentHasUnit'     => $parentHasUnit,
        ];

        return $this->renderTwigTemplate(
            $templateHandle,
            '@learningAssessment/builder/emptyStateNoUnitSpec.twig',
            $data,
            $this->hasWriteAccess() || !$this->getConfig('compactMode')
        );
    }



    /* Getters/Setters
     */

    /**
     * @return UnitImplementationMapper
     */
    protected function getUnitImplementationMapper()
    {
        return $this->unitImplementationMapper;
    }

    /**
     * @param UnitImplementationMapper $unitImplementationMapper
     */
    public function setUnitImplementationMapper(UnitImplementationMapper $unitImplementationMapper)
    {
        $this->unitImplementationMapper = $unitImplementationMapper;
    }


    /**
     * @return UnitSpecificationMapper
     */
    public function getUnitSpecificationMapper()
    {
        return $this->unitSpecificationMapper;
    }

    /**
     * @param UnitSpecificationMapper $unitSpecificationMapper
     * @return static
     */
    public function setUnitSpecificationMapper(UnitSpecificationMapper $unitSpecificationMapper)
    {
        $this->unitSpecificationMapper = $unitSpecificationMapper;
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
}
