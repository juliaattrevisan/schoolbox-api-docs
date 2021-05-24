<?php

/**
 * Course builder homepage component controller
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\Component\Homepage\Builder;

use Alaress\Database\Predicate\InvalidColumnException;
use Component_Homepage_ControllerAbstract;
use Component_Instance;
use LogicException;
use RuntimeException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use Schoolbox\Permission\Access\UserAccess;
use SitePage;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use TypeError;
use UnexpectedValueException;

class Controller extends Component_Homepage_ControllerAbstract
{
    /** @var UnitSpecificationMapper */
    private $unitSpecificationMapper;

    /** @var AssessmentMapperProxy */
    private $assessmentMapperProxy;

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
        $this->assessmentMapperProxy = $assessmentProxy = $this->getSitePage()->getDi()->get('assessmentDataSource');
    }

    /**
     * @inheritdoc
     */
    public function execute()
    {
        if (!parent::execute()) {
            return false;
        }
        return $this->getContentPermission() >= UserAccess::ACCESS_WRITE;
    }


    /**
     * @param String $templateHandle
     * @return bool
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws InvalidColumnException If a PredicateBuilder method is called with an invalid column name
     * @throws LogicException         If cyclic graph detected during translation
     * @throws TypeError              If a PredicateBuilder method is called with wrong data type
     * @throws LoaderError            When the template cannot be found
     * @throws SyntaxError            When an error occurred during compilation
     * @throws RuntimeError           When an error occurred during rendering
     * @throws RuntimeException       When Twig renderer not found in this Sitepage object's dependency injector.
     * @throws UnexpectedValueException If source not set. TODO source should be runtime arg
     */
    public function renderTemplate($templateHandle)
    {
        // Get the current folder and hydrate its parent
        $folder = $this->getSource();
        if (!$folder) {
            throw new UnexpectedValueException('Source has not been set');
        }

        // Ensure this CourseBuilder component is being placed on a Folder which has a UnitSpec
        $unitSpec = $folder->getUnitSpecification();
        if (!$unitSpec) {
            return $this->renderNotAUnitSpecification($templateHandle);
        }

        // Re-hydrate to get the unit's details (eg unit.course.name)
        $unitSpec = $this->unitSpecificationMapper->findByFolderId($folder->getId());

        $options = ['order' => ['sequence' => 'ASC']];
        $assessables = $this->assessmentMapperProxy->findAllByUnitSpecificationId($unitSpec->getId(), $options);
        $unitSpec->setAssessables($assessables);

        $data = [
            'unitSpec'         => $unitSpec,
            'returnUrlName'    => Assessment\Redirect::RETURN_URL,
            'encodedReturnUrl' => Assessment\Redirect::encode('/homepage/' . $folder->getId()),
        ];

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
            $data
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
            'folderHasParent'   => $folder->getParent()->getId(),
            'parentHasCourse'   => $parentHasCourse,
            'parentHasUnit'     => $parentHasUnit,
            'parentWritable'    => $this->getAccess()->getFolderAccess($this->getUser(), $parent->getId()) >= UserAccess::ACCESS_WRITE,
        ];

        return $this->renderTwigTemplate(
            $templateHandle,
            '@learningAssessment/builder/emptyStateNoUnitSpec.twig',
            $data
        );
    }
}
