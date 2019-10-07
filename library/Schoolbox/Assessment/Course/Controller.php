<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Assessment\Course;

use Alaress\Web\Controller as Base;
use Aura\Di\Exception\ServiceNotFound;
use Component_DefinitionMapper;
use Component_Instance;
use Component_InstanceMapper;
use InvalidArgumentException;
use PDOException;
use RangeException;
use UnexpectedValueException;
use Schoolbox\Application;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Assessment\Course\Config\Model as CourseConfigModel;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node;
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\Component\Homepage\Builder\Controller as CourseBuilderController;
use Schoolbox\Learning\Assessment\DataModel\Mapper as AssessmentMapper;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitImplementation;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitImplementationMapper;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Folder\Code as FolderCode;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Controller as FolderController;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\YearLevel;


/**
 * Class Controller
 *
 * @package Schoolbox\Assessment\Course
 */
class Controller extends Base\Base
{
    /** @var FolderMapper */
    private $folderMapper;

    /**  @var CourseSpecificationMapper */
    private $courseSpecificationMapper;

    /** @var AssessmentMapperProxy */
    private $assessmentMapperProxy;

    /** @var UnitSpecificationMapper */
    private $unitSpecificationMapper;

    /** @var UnitImplementationMapper */
    private $unitImplementationMapper;

    /** @var Component_DefinitionMapper */
    private $componentDefinitionMapper;

    /** @var Component_InstanceMapper */
    private $componentInstanceMapper;

    /** @var HomepageConfigMapper */
    private $homepageConfigMapper;

    /** @var  HomepageTemplateMapper */
    private $homepageTemplateMapper;

    /** @var CourseConfigMapper */
    private $courseConfigMapper;

    /** @var UserAccessMapper */
    private $userAccessMapper;

    /** @var Access */
    private $access;

    /** @var AssessmentMapper */
    private $assessmentMapper;

    /** @var CurriculumNodeMapper */
    private $curriculumNodeMapper;


    /**
     * @param AssessmentMapperProxy      $assessmentMapperProxy
     * @param CourseSpecificationMapper  $courseSpecificationMapper
     * @param UnitSpecificationMapper    $unitSpecificationMapper
     * @param UnitImplementationMapper   $unitImplementationMapper
     * @param FolderMapper               $folderMapper
     * @param Component_DefinitionMapper $componentDefinitionMapper
     * @param Component_InstanceMapper   $componentInstanceMapper
     * @param HomepageConfigMapper       $homepageConfigMapper
     * @param HomepageTemplateMapper     $homepageTemplateMapper
     * @param Access                     $access
     * @param CourseConfigMapper         $courseConfigMapper
     * @param UserAccessMapper           $userAccessMapper
     * @param AssessmentMapper           $assessmentMapper
     * @param CurriculumNodeMapper       $curriculumNodeMapper
     */
    public function __construct(
        AssessmentMapperProxy $assessmentMapperProxy,
        CourseSpecificationMapper $courseSpecificationMapper,
        UnitSpecificationMapper $unitSpecificationMapper,
        UnitImplementationMapper $unitImplementationMapper,
        FolderMapper $folderMapper,
        Component_DefinitionMapper $componentDefinitionMapper,
        Component_InstanceMapper $componentInstanceMapper,
        HomepageConfigMapper $homepageConfigMapper,
        HomepageTemplateMapper $homepageTemplateMapper,
        Access $access,
        CourseConfigMapper $courseConfigMapper,
        UserAccessMapper $userAccessMapper,
        AssessmentMapper $assessmentMapper,
        CurriculumNodeMapper $curriculumNodeMapper
    ) {
        $this->assessmentMapperProxy      = $assessmentMapperProxy;
        $this->courseSpecificationMapper  = $courseSpecificationMapper;
        $this->unitSpecificationMapper    = $unitSpecificationMapper;
        $this->unitImplementationMapper   = $unitImplementationMapper;
        $this->folderMapper               = $folderMapper;
        $this->componentDefinitionMapper  = $componentDefinitionMapper;
        $this->componentInstanceMapper    = $componentInstanceMapper;
        $this->homepageConfigMapper       = $homepageConfigMapper;
        $this->homepageTemplateMapper     = $homepageTemplateMapper;
        $this->access                     = $access;
        $this->courseConfigMapper         = $courseConfigMapper;
        $this->userAccessMapper           = $userAccessMapper;
        $this->assessmentMapper           = $assessmentMapper;
        $this->curriculumNodeMapper       = $curriculumNodeMapper;
    }


    /**
     * Generates a Unit and Course for a pair of folders.
     *
     * A single call to this method may create a Unit, a Course or both. Note that we DO enforce a
     * parent-child relationship between a Unit's folder and the Course's folder.
     *
     * While to the User it appears like folders have been converted, under the hood this is achieved
     * by associating the specified folders with a Unit and/or Course respectively.
     *
     * Does not permit moving an existing Unit to a new Course.
     *
     * @api POST /course/convert/:courseFolderId/:unitFolderId
     * @since 165
     *
     * @param Folder $courseFolder
     * @param Folder $unitFolder
     * @throws PDOException
     * @throws MapperException
     * @throws UnexpectedValueException
     */
    public function convertFolders(Folder $courseFolder, Folder $unitFolder)
    {
        $errors = [];

        // Is the course folder not the parent of the unit folder?
        if ($unitFolder->getParent()->getId() != $courseFolder->getId()) {
            $errors[] = "Course folder (id: {$courseFolder->getId()}) is not the parent of Unit folder (id: {$unitFolder->getId()})";
            $this->returnJson($errors, 400);
            return;
        }

        $course = $courseFolder->getCourseSpecification();
        $unit = $unitFolder->getUnitSpecification();

        $courseExists = $course instanceof CourseSpecification && $course->getId();
        $unitExists = $unit instanceof UnitSpecification && $unit->getId();

        // Do the folders already have a Unit and Course attached ?
        if ($courseExists && $unitExists) {
            $errors[] = "Course (id: {$course->getId()}) and Unit (id: {$unit->getId()}) already linked.";
            $this->returnJson($errors, 400);
            return;
        }

        // Does Unit already exist and belong to different Course?
        if ($unitExists && !$courseExists) {
            $errors[] = "Unit (id: {$unit->getId()}) already exists in Course (id: {$unit->getCourse()->getId()})";
            $this->returnJson($errors, 400);
            return;
        }

        // Is the proposed unit folder the same as the course folder?
        if ($unitFolder->getId() == $courseFolder->getId()) {
            $errors[] = 'Cannot convert the same folder into both a Unit and a Course. FolderId: ' . $courseFolder->getId();
        }

        // Check proposed Course
        if (!$courseExists) {
            // Does user have WRITE+ access to Course Folder?
            $courseFolderAccess = $this->access->getFolderAccess($this->getUser(), $courseFolder->getId());

            if ($courseFolderAccess < UserAccess::ACCESS_WRITE) {
                $errors[] = 'Course Folder permission error: '.$courseFolder->getId();
            }

            // Does the proposed Course Folder already have a Unit
            $courseFolderUnit = $courseFolder->getUnitSpecification();

            if ($courseFolderUnit instanceof UnitSpecification && $courseFolderUnit->getId()){
                $errors[] = 'Proposed Course folder already has a Unit: ' . $courseFolderUnit->getId();
            }
        }

        // Check the proposed Unit
        if (!$unitExists) {
            // Does user have WRITE+ access to the Unit folder?
            $unitFolderAccess = $this->access->getFolderAccess($this->getUser(), $unitFolder->getId());

            if ($unitFolderAccess < UserAccess::ACCESS_WRITE) {
                $errors[] = 'Unit Folder permission error: '.$unitFolder->getId();
            }

            // Does the proposed Unit Folder already have a Course ?
            $unitFolderCourse = $unitFolder->getCourseSpecification();

            if ($unitFolderCourse instanceof CourseSpecification && $unitFolderCourse->getId()) {
                $errors[] = 'Proposed Unit folder already has a Course: ' . $unitFolderCourse->getId();
            }
        }

        if (\count($errors)) {
            $this->returnJson($errors, 400);
            return;
        }

        // No errors. Proceed.

        // Create Course if required
        if (!$courseExists) {
            $course = $this->courseSpecificationMapper->createModel();
            $course->setFolder($courseFolder);
        }

        // Create Unit if required
        if (!$unitExists) {
            $unit = $this->unitSpecificationMapper->createModel();
            $unit->setFolder($unitFolder);
        }

        // Link Course and Unit
        $unit->setCourse($course);
        $course->getUnits()->add($unit);

        // And save
        $response = [];

        if (!$courseExists) {
            $this->courseSpecificationMapper->save($course);
            $response['course'] = $course->getId();
        }

        // Create Unit if required
        if (!$unitExists) {
            $this->unitSpecificationMapper->save($unit);
            $response['unit'] = $unit->getId();
        }

        $this->getResponse()->content->set($this->buildJsonOutput($response));
    }


    /**
     * Convenience method for returning JSON output
     *
     * @param string|array $message
     * @param int $code
     */
    protected function returnJson($message, $code = 200)
    {
        $this->getResponse()->status->set($code);

        if ($this->getRequest()->isXhr()){
            $this->getResponse()->content->set($this->buildJsonOutput($message));
        } else {
            die(json_encode($message));
        }
    }


    /**
     * Saves the sequence of Assessments in specified UnitSpecification
     *
     * @api /course/unitspecs/{id}/sequence
     * @since 165
     *
     * @var int[] $_POST['sequence'] AssessmentIds, indexed by sequence
     */
    public function saveAssessmentSequence()
    {
        $assessmentSequence = $this->getRequest()->post->get('sequence');
        $this->returnJson($this->assessmentMapper->sequenceAssessments($assessmentSequence));
    }


    /**
     * Just a raw template render
     *
     * @param string $template
     * @param array $data
     */
    protected function courseTpl($template, array $data = [])
    {
        $this->tpl('course', $template, $data, 'course');
    }


    /**
     * Retrieve the Course Builder component instance. If it doesn't exist, create it, then return that
     *
     * @param Folder $unitFolder
     * @return Component_Instance
     * @throws Deleted
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    protected function getCourseBuilderInstance(Folder $unitFolder): \Component_Instance
    {
        // confirm that there's a Course Builder component
        $folderId = $unitFolder->getId();
        $componentClass = CourseBuilderController::class;
        $instances = $this->componentInstanceMapper->findByContextAndClass('homepage', $folderId, $componentClass);

        if (\count($instances) == 0) {
            // no instance found - give me a new one
            return $this->injectNewComponent($unitFolder);
        }

        // instance found - use that
        return $instances->first();
    }


    /**
     * @param Folder $folder
     * @param string $controllerClass
     * @return Component_Instance
     * @throws Deleted
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     */
    protected function injectNewComponent(Folder $folder, $controllerClass = CourseBuilderController::class): \Component_Instance
    {
        // Doesn't exist. We need that to exist. Make it exist.
        $definition = $this->componentDefinitionMapper->findOneByColumn('class', $controllerClass);

        // invent a new component
        $newInstance = $this->componentInstanceMapper
            ->createModel()
            ->setModule('homepage')
            ->setIdentifier($folder->getId())
            ->setDefinitionId($definition->getId())
            ->setSequence(0)
            ->setPosition('top');

        // save it
        $this->componentInstanceMapper->save($newInstance);

        // reapply the sequencing so the homepage doesn't break
        $this->componentInstanceMapper->cascadeSequence($folder->getId(), 'top');

        return $newInstance;
    }


    /**
     * Form to create/modify a course specification
     *
     * Course.id will be null if it is a create.
     *
     * @api   GET /course/modify/{id}
     * @since 160
     *
     * @param CourseSpecification $courseSpec The course being created/modified
     * @throws BaseMapper\DefinitionException
     * @throws Deleted
     * @throws MapperException
     * @throws NotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \LogicException
     */
    public function modifyCourse(CourseSpecification $courseSpec)
    {
        // Is course deleted ?
        // TODO move this to module's route auth rule
        if ($courseSpec->isDeleted()) {
            throw new NotFound();
        }

        $courseSpec->setUnits($this->unitSpecificationMapper->findAllByCourseId($courseSpec->getId()));

        // Is this an UPDATE ?
        if ($courseSpec->getId()) {
            // Rehydrate course folder so that curriculum codes exist
            $courseSpec->setFolder($this->folderMapper->findById($courseSpec->getFolder()->getId()));

            // Need to hydrate parent to populate the 'Faculty/Subject' selector
            $courseSpec->getFolder()->setParent($this->folderMapper->findById($courseSpec->getFolder()->getParent()->getId()));
        }

        /**
         * Folder permissions. Indexed by folder.id in all cases.
         *
         * @var int[] $folderAccess
         */
        $folderAccess = [];

        /**
         * Encoded return URL to send to create/update assessment page to enable return to
         * specific unitSpec in specific courseSpec. Indexed by unitSpec.id
         *
         * @var string[] $encodedReturnUrls
         */
        $encodedReturnUrls = [];

        $folderAccess[$courseSpec->getFolder()->getId()] = $this->access->getFolderAccess($this->getUser(), $courseSpec->getFolder()->getId());

        foreach ($courseSpec->getUnits() as $unitSpec) {
            $unitSpecId = $unitSpec->getId();
            $folderId = $unitSpec->getFolder()->getId();
            $options = ['order' => ['sequence' => 'ASC']];
            $assessments = $this->assessmentMapperProxy->findAllByUnitSpecificationId($unitSpecId, $options);
            $unitSpec->setAssessables($assessments);
            $folderAccess[$folderId] = $this->access->getFolderAccess($this->getUser(), $folderId);
            $encodedReturnUrls[$unitSpecId] = $this->getReturnUrl($courseSpec->getId(), $unitSpec->getId());
        }

        $this->courseTpl('course', [
            'courseSpec'        => $courseSpec,
            'curriculumEnabled' => $this->getConfigLoader()->get('curriculum_tagging_enabled'),
            'folderAccess'      => $folderAccess,
            'encodedReturnUrls' => $encodedReturnUrls,
            'isValidFacultyRoot'=> $this->courseSpecificationMapper->isConfiguredFacultyRootValid(),
        ]);
    }


    /**
     * Form to create a new course
     *
     * @api   GET /course/new{/:name}
     * @since 160
     *
     * @param string|null $name
     * @throws BaseMapper\DefinitionException
     * @throws Deleted
     * @throws MapperException
     * @throws NotFound
     * @throws \Alaress\Database\Predicate\InvalidColumnException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \LogicException
     */
    public function newCourse(string $name = null)
    {
        $course = $this->courseSpecificationMapper->createModel();
        $folder = $this->folderMapper->createModel();
        $folder->setCourseSpecification($course);
        $course->setFolder($folder);
        $folder->setName($name);
        $this->modifyCourse($course);
    }


    /**
     * Accept the create formdata
     *
     * @api   POST /course/new
     * @since 160
     *
     * @var string   $_POST['name']             name to give to new course's folder
     * @var int      $_POST['parent']           id of the folder to create the new course's folder under
     * @var string[] $_POST['curriculumCodes']  1st item is comma-separated integers. Saves to 'curriculum_node_for_category' table
     *
     * @throws Application\Exception\Exception
     * @throws BaseMapper\DefinitionException
     * @throws Base\Exception
     * @throws Deleted
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws PDOException
     * @throws RangeException
     * @throws ServiceNotFound
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function createCourse()
    {
        $post = $this->getRequest()->post;

        // Get the default permissions to give to Course Folders
        $rolePermissions = [];
        foreach ($this->courseConfigMapper->findByColumn('page', 'course') as $courseConfig) {
            /** @var CourseConfigModel $courseConfig */
            $rolePermissions[$courseConfig->getRoleId()] = $courseConfig->getPermission();
        }

        try {
            $this->validateCourseData(trim($post['name']), trim($post['parent']), null);

            $templateID = $this->getConfigLoader()->get('course_builder_default_course_page_template');
            if ($this->homepageTemplateMapper->countByColumn('id', $templateID) === 0) {
                $templateID = HomepageTemplate::HOMEPAGE_NONE;
            } else {
                $templateID = (int)$templateID;
            }

            // Create new CourseFolder
            $courseFolder = $this->persistFolder(
                $post['name'],
                (int)trim($post['parent']),
                array_filter(explode(',', $post['curriculumCodes'][0])),
                $this->getConfigLoader()->get('course_builder_default_course_page_inherit'),
                $templateID,
                $rolePermissions,
                false,
                []
            );

            $courseSpec = $this->courseSpecificationMapper->createModel();
            $courseSpec->setFolder($courseFolder);
            $courseFolder->setCourseSpecification($courseSpec);

            $this->courseSpecificationMapper->save($courseSpec);
        } catch (Base\Exception $e) {
            $this->sendError($e);
            return;
        }


        if ($this->getRequest()->isXhr()) {
            // respond to XHR with JSON
            $this->getResponse()->content->set($this->buildJsonOutput([
                'id'              => $courseSpec->getId(),
                'urlModifyCourse' => $this->getRouter()->generate('course.updateCourse', ['id' => $courseSpec->getId()]),
                'urlNewUnit'      => $this->getRouter()->generate('course.newUnit', ['id' => $courseSpec->getId()]),
            ]));

        } else {
            // respond to synchronous POST with redirect
            $this->getResponse()->redirect->afterPost($this->getRouteIfAuthorized('course.courseList'));
        }
    }

    /**
     * Checks:
     * * Course Name not empty
     * * user has write access to course's folder's parent
     * * user selected a folder parent
     * * course not being created, or moved to, outside faculty root
     * * no loops in folder ancestry
     *
     * @param int|null $folderId
     * @param string   $folderName
     * @param int      $parentFolderId
     * @throws Base\Exception
     * @todo move route perm logic from controller to module (eg course parent folder access)
     */
    protected function validateCourseData($folderName, $parentFolderId, $folderId = null)
    {
        $errors = [];
        $parentFolder = null;

        if (!$folderName) {
            $errors[] = 'You need to give the course a name';
            $folderName = 'this course';
        }

        // Was no parent indicated? (as opposed to explicitly setting to root)
        if (!$parentFolderId && '0' !== $parentFolderId){
            $errors[] = "You need to select a faculty or class for {$folderName} to live in";
        } else {
            $access = $this->access->getFolderAccess($user = $this->getUser(), $parentFolderId);

            if ($access < UserAccess::ACCESS_WRITE) {
                $errors[] = 'You need to select a Faculty / Subject for which you have write access';
            } else {
                try {
                    $parentFolder = $this->folderMapper->findCheaplyById($parentFolderId);
                } catch (MapperException $e){
                    $errors[] = "You need to select an existing faculty or class for {$folderName} to live in";
                }
            }
        }

        // if there were any validation problems, throw that back
        if ($errors || !$parentFolder instanceof Folder) {
            throw new Base\Exception('Could not save', $errors);
        }

        // Explicitly set to root (as opposed to not set)
        if ('0' === $parentFolderId && 0 !== $this->courseSpecificationMapper->getFacultyRootFolder()->getId()) {
            throw new Base\Exception('Courses may not be created in the root folder');
        }


        // Deeper logic tests
        try {
            // Does the folder already exist? If so, retrieve it.
            $folder = null;

            if ($folderId){
                $folder = $this->folderMapper->findCheaplyById($folderId);
            }

            // Is this a create, or are we moving an existing course?
            if (!$folderId || $folder->getParent()->getId() != $parentFolderId ) {
                if (!$this->courseSpecificationMapper->isFolderValid($parentFolder)) {
                    if ($folderId) {
                        throw new Base\Exception('Courses may not be moved outside the faculty folder');
                    } else {
                        throw new Base\Exception('Courses may not be created outside the faculty folder');
                    }
                }
            }

            // Check we are not moving a folder to be its own parent
            if ($folder) {
                $folder->setParent($parentFolder);

                if (!$this->folderMapper->checkParentage($folder)) {
                    throw new Base\Exception('Courses may not be moved inside themselves');
                }
            }
        } catch (BaseMapper\Exception $e) {
            throw new Base\Exception("Could not save because {$e->getMessage()}");
        }
    }


    /**
     * @api   POST /course/modify/{id}
     * @since 160
     *
     * @param CourseSpecification $courseSpec
     * @var string   $_POST['name'] indexed by unitSpec.id
     * @var int      $_POST['parent'] indexed by unitSpec.id
     * @var string[] $_POST['curriculumCodes']  1st item is comma-separated integers. Saves to 'curriculum_node_for_category' table
     * @var array[]  $_POST['unit'] indexed by unitSpec.id
     * @var string   $_POST[unitSpecId]['name']
     * @throws BaseMapper\DefinitionException
     * @throws Base\Exception
     * @throws Deleted
     * @throws MapperException
     * @throws PDOException
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     */
    public function updateCourse(CourseSpecification $courseSpec)
    {
        $post = $this->getRequest()->post;
        $courseSpec->getFolder()->setName(trim($post['name']));
        $parentFolderId = trim($post['parent']);
        $curriculumCodes = $post['curriculumCodes'];

        try {
            $this->validateCourseData($courseSpec->getFolder()->getName(), $parentFolderId, $courseSpec->getFolder()->getId());

            if ($courseSpec->getFolder()->getParent()->getId() != $parentFolderId) {
                $parentFolder = $this->folderMapper->findCheaplyById($parentFolderId);
                $courseSpec->getFolder()->setParent($parentFolder);
            }
        } catch (Base\Exception $e){
            $this->sendError($e);
            return;
        }

        // Save each Unit's name
        if (\is_array($post['unit'])) {
            $courseSpec->setUnits($this->unitSpecificationMapper->findAllByCourseId($courseSpec->getId()));

            // save unit name changes
            foreach ($courseSpec->getUnits() as $unitSpec) {
                $unitName = $post['unit'][$unitSpec->getId()]['name'];

                if (isset($unitName)) {
                    // Re-hydrate unit folder to ensure curriculum codes etc are hydrated.
                    $unitFolder = $this->folderMapper->findById($unitSpec->getFolder()->getId());
                    $this->folderMapper->save($unitFolder->setName($unitName));
                }
            }
        } else {
            $this->sendError(new Base\Exception('Cannot create a Course without Units.', ['You need to create at least 1 unit']));
            return;
        }

        // Save the Course
        $this->courseSpecificationMapper->save($courseSpec);

        // Save the folder
        $folder = $courseSpec->getFolder();

        if (\count($curriculumCodes)){
            $folder->setCurriculumNodes($this->curriculumNodeMapper->findByColumn('id', $curriculumCodes));
        }

        $this->folderMapper->save($folder);

        if ($this->getRequest()->isXhr()) {
            // respond to XHR with JSON
            $this->getResponse()->content->set($this->buildJsonOutput([
                'id' => $courseSpec->getId(),
            ]));
        } else {
            // respond to synchronous POST with redirect
            $this->getResponse()->redirect->afterPost($this->getRouteIfAuthorized('course.courseList'));
        }
    }


    /**
     * Give the encoded return URL for the given course ID
     *
     * @param int $courseId
     * @param int $unitId
     * @return string
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    protected function getReturnUrl($courseId, $unitId = null): string
    {
        $path = $this->getRouteIfAuthorized('course.modifyCourse', ['id' => $courseId]);

        if ($unitId) {
            $path .= "#{$unitId}";
        }

        return Redirect::encode($path);
    }


    /**
     * Post request to spawn a new unit
     *
     * Creates:
     * * UnitSpec
     * * Folder
     * * CourseBuilder Component on the Folder
     *
     * @api   POST /course/{courseSpecId}/units/new
     * @since 160
     *
     * @param CourseSpecification $courseSpec
     * @var string $_POST['name'] What to name the new unit
     * @throws Base\Exception
     */
    public function newUnit(CourseSpecification $courseSpec)
    {
        if (!$courseSpec->getId()) {
            $this->sendError('Could not save. Course not found.');
            return;
        }

        $post = $this->getRequest()->post;
        $name = trim($post->get('name'));

        if (!$name) {
            $this->sendError('Could not create unit.', ['You need to give the unit a name']);
            return;
        }

        try {
            // Need:
            // base config
            // course config
            $rolePermissions = [];

            foreach ($this->courseConfigMapper->findByColumn('page', 'unit') as $courseConfig) {
                $rolePermissions[$courseConfig->getRoleId()] = $courseConfig->getPermission();
            }

            $templateID = $this->getConfigLoader()->get('course_builder_default_unit_page_template');

            if ($this->homepageTemplateMapper->countByColumn('id', $templateID) === 0) {
                $templateID = HomepageTemplate::HOMEPAGE_NONE;
            } else {
                $templateID = (int)$templateID;
            }

            $folder = $this->persistFolder(
                $name,
                $courseSpec->getFolder()->getId(),
                [],
                $this->getConfigLoader()->get('course_builder_default_unit_page_inherit'),
                $templateID,
                $rolePermissions,
                false,
                []
            );

            // Persist the unit too
            $unitSpec = new UnitSpecification();
            $unitSpec->setFolder($folder);
            $unitSpec->setCourse($courseSpec);
            $this->unitSpecificationMapper->save($unitSpec);

            $encodedReturnUrl = $this->getReturnUrl($courseSpec->getId(), $unitSpec->getId());

            $data = [
                'unitSpec'         => $unitSpec,
                'encodedReturnUrl' => $encodedReturnUrl,
                '_writable'        => true,
                '_expanded'        => true,
            ];
            $this->courseTpl('unit', $data);

        } catch (ServiceNotFound $e) {
            // quite simple not able to find things we need. bye bye.
            $this->sendError('Could not save because of an internal error.', [], 500);

        } catch (\Exception $e) {
            // whatever the exception, recover from it as best you can
            $this->sendError("Could not save because {$e->getMessage()}");
        }
    }


    /**
     * Wrapper around Folder\Controller::persistFolder() and does some extra perms checking
     *
     * @param string   $name
     * @param int      $parentId
     * @param array    $curriculumCodeIds
     * @param bool     $inherit            Whether to inherit permissions
     * @param int|null $templateId
     * @param array    $rolePermissions    Maps role to permission level
     * @param bool     $recursePermissions If true && user is SU, applies 'inherit' to entire subtree
     * @param array    $classCodes
     * @return Folder
     * @throws Deleted
     * @throws MapperException
     * @throws RangeException
     * @throws ServiceNotFound
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws Application\Exception\Exception
     * @throws UnexpectedValueException
     * @throws BaseMapper\DefinitionException
     */
    private function persistFolder(
        string $name,
        int $parentId,
        array $curriculumCodeIds,
        bool $inherit,
        int $templateId,
        array $rolePermissions,
        bool $recursePermissions,
        array $classCodes
    ): Folder {
        /** @var FolderController $folderController */
        $folderController = $this->getController('folder');

        $folder = (new Folder())
            ->setParent($this->folderMapper->findById($parentId))
            ->setCodes(new GenericCollection(FolderCode\Model::class))
            ->setYearLevels(new GenericCollection(YearLevel\Model::class))
            ->setCurriculumNodes(new GenericCollection(Node\Model::class));

        $folder = $folderController->persistFolder(
            $folder,
            $name,
            $inherit,
            $templateId,
            null,
            null,
            $curriculumCodeIds,
            $rolePermissions,
            $recursePermissions,
            $classCodes,
            [],
            false,
            $this->getUser()
        );

        // Check that the initiating user has write access to the newly-created folder, and if not,
        // explicitly give it to them. This covers the case where the template allows you to create a folder
        // to which you don't have appropriate access
        if ($this->access->getFolderAccess($this->getUser(), $folder->getId()) < UserAccess::ACCESS_WRITE) {
            $ua = $this->userAccessMapper->createModel();
            $ua->setUser($this->getUser())
                ->setAccessLevel(UserAccess::ACCESS_WRITE)
                ->setService(UserAccess::SERVICE_HOMEPAGE)
                ->setServiceIdentifier($folder->getId());

            $this->userAccessMapper->save($ua);
        }

        return $folder;
    }


    /**
     * Generate a list of CourseSpecs
     *
     * @api   GET /course/list
     * @since 160
     *
     * @var string[] $_GET                       AJAX GET params
     * @var string   $_GET['encodedReturnUrl']   For URL generation
     * @var int      $_GET['folder']             Folder.id for URL generation
     * @var int      $_GET['component']          ComponentInstance.id for URL generation
     *
     * @throws BaseMapper\DefinitionException
     * @throws Deleted
     * @throws MapperException
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \DomainException
     * @throws \OverflowException
     */
    public function courseList(): void
    {
        $query = $this->getRequest()->query;
        $data = $this->getCourseData();
        $data['returnUrlKey'] = Redirect::RETURN_URL;

        if ($query->get('folder')) {
            $folderModel = $this->folderMapper->findById($query->get('folder'));
            $data['suggestions'] = $this->getLinkSuggestions($folderModel);
        }

        if ($this->getRequest()->isXhr()) {
            $options = [
                'hasClose'         => true,
                'hasAdd'           => false,
                'hasTot'           => false,
                'linkToUnits'      => false,
                'hasBreadcrumb'    => false,
                'encodedReturnUrl' => $query->get('encodedReturnUrl'),
                'folder'           => [ 'id' => $query->get('folder') ],
                'component'        => [ 'id' => $query->get('component') ],
            ];
        } else {
            $options = [
                'hasClose'         => false,
                'hasAdd'           => true,
                'hasTot'           => true,
                'linkToUnits'      => true,
                'hasBreadcrumb'    => true,
                'encodedReturnUrl' => Redirect::encode($this->getRouter()->generate('course.courseList')),
            ];
        }

        $this->courseTpl('courseList', array_merge($data, $options));
    }


    /**
     * Generated when user types in CourseSpec search field
     * Performs a search by CourseSpec's Folder name
     *
     * @api   GET /course/list/ajax
     * @since 160
     *
     * @var string[] $_GET                       AJAX GET params
     * @var string   $_GET['search']             Search string submitted by user
     * @var string   $_GET['encodedReturnUrl']   For URL generation
     * @var bool     $_GET['hasAdd']
     * @var bool     $_GET['hasTot']
     * @var bool     $_GET['linkToUnits']
     * @var int      $_GET['folder']
     * @var int      $_GET['component']
     * @throws BaseMapper\DefinitionException
     * @throws Deleted
     * @throws MapperException
     * @throws \DomainException
     * @throws \OverflowException
     */
    public function courseListAjax()
    {
        $query = $this->getRequest()->query;

        $search = $query->get('search');
        $folder = $query->get('folder');

        $data = $this->getCourseData($search);

        // only offer suggestions if you're not searching
        if ($folder && !$search) {
            $folderModel = $this->folderMapper->findById($folder);
            $data['suggestions'] = $this->getLinkSuggestions($folderModel);
        }

        $options = [
            'search'           => $search,
            'folder'           => ['id' => $folder],
            'component'        => ['id' => $query->get('component')],
            'hasAdd'           => $query->get('hasAdd', 0),
            'hasTot'           => $query->get('hasTot', 0),
            'linkToUnits'      => $query->get('linkToUnits', 0),
            'returnUrlKey'     => Redirect::RETURN_URL,
            'encodedReturnUrl' => $query->get('encodedReturnUrl'),
        ];

        $this->courseTpl('courseListResults', array_merge($data, $options));
    }


    /**
     * @param int $folderId
     * @param int $min
     * @return bool
     */
    protected function confirmAccess($folderId, $min = UserAccess::ACCESS_READ): bool
    {
        return $this->access->getFolderAccess($this->getUser(), $folderId) >= $min;
    }


    /**
     * List the courses. Returns a combined array of Folder instances
     * and information about any found Units and their task counts
     *
     * @param string|null $searchParam  If null, filter out courses residing outside Faculty root
     * @return array
     * @var Folder[] $data['lineage']
     * @var CourseSpecification[] $data['courses']
     * @throws \DomainException
     * @throws \OverflowException
     */
    protected function getCourseData(string $searchParam = null): array
    {
        // Use the new mapper to get the list of courses
        $allCourses = $this->courseSpecificationMapper->findAllByName($searchParam, ['order' => ['f.name' => 'asc']]);

        $data = [];

        foreach ($allCourses as $currentCourse) {

            // general search ignores Courses outside Faculty
            if (null === $searchParam) {
                try {
                    if (!$this->folderMapper->isDescendant($currentCourse->getFolder(), $this->courseSpecificationMapper->getFacultyRootFolder())) {
                        continue;
                    }
                } catch (MapperException $e) {
                    // can't detect if it's in faculty area because an ancestor is deleted. For our purposes, consider this deleted / irrelevant
                    continue;
                }
            }

            // confirm you at least have read access...
            if (!$this->confirmAccess($currentCourse->getFolder()->getId())) {
                continue;
            }

            /* Also store the path to this Course
             */
            try {
                $data['lineage'][$currentCourse->getId()] = $this->getLineage($currentCourse->getFolder());
            } catch (MapperException $e) {
                // somehow, against all odds, one of my ancestors has been deleted: show nothing
                continue;
            }

            // Get units for a course
            $units = $this->unitSpecificationMapper->findAllByCourseId($currentCourse->getId());

            // Remove units whose folders aren't accessible by this user
            $currentCourse->setUnits($units->filter(function ($unit) {
                /** @var UnitSpecification $unit */
                return $this->confirmAccess($unit->getFolder()->getId());
            }));

            // Add course directly into $data
            $data['courses'][] = $currentCourse;
        }

        return $data;
    }


    /**
     * Convenience method
     *
     * @param Folder $course
     * @throws MapperException if one of the ancestors is deleted or missing somehow
     * @throws \OverflowException
     * @return Collection
     */
    protected function getLineage(Folder $course): Collection
    {
        return $this->folderMapper->getAncestry()->findParentsCheaply($course, $this->courseSpecificationMapper->getFacultyRootFolder());
    }


    /**
     * Gets the suggested course links to A FULLY HYDRATED folder
     *
     * @param Folder $folder
     * @return Collection
     * @throws MapperException
     */
    protected function getLinkSuggestions(Folder $folder): Collection
    {
        // obtain curriculum codes...
        $codes = $folder->getCurriculumNodes()->getColumnValues('code');

        // find all the folders that match this code, then filter for coarseness
        $courseFolders = $this->folderMapper->findByCurriculumCode($codes)->filter(function ($result) {
            /** @var Folder $result */
            // TODO: test this with folders and courses which have curriculum codes
            try {
                $this->courseSpecificationMapper->findByFolderId($result->getId());
                return true;
            } catch (MapperException $e) {
                return false;
            }
        });

        $courseFolderIds = $courseFolders->getPropertyValues('id');

        $courseSpecs = $this->courseSpecificationMapper->findByColumn('folder_id', $courseFolderIds);

        return $courseSpecs;
    }


    /**
     * List the folders that are allowed to be course parents
     *
     * @param $folderId
     * @api   GET /course/parent
     * @since 160
     */
    public function parentList($folderId)
    {
        if (empty($folderId)) {
            $folderId = $this->courseSpecificationMapper->getFacultyRootFolder()->getId();
        }

        $treeBuilder = new TreeBuilder(
            FolderMapper::getInstance(),
            $_SESSION['user'],
            $folderId
        );

        $facultyRootFolder = $this->courseSpecificationMapper->getFacultyRootFolder();
        $treeData = $treeBuilder->buildPartialTree($folderId, $facultyRootFolder->getId() ?: '#');

        $data = [
            'treedata' => json_encode($treeData),
            'folderId' => $folderId
        ];

        $this->courseTpl('picker', $data);
    }


    /**
     * Delete a course specification
     *
     * @api   POST /course/delete/{id}
     * @since 160
     *
     * @param CourseSpecification $courseSpec The course to delete
     * @param bool                $deleteFolder
     * @throws Application\Exception\Exception
     * @throws MapperException
     * @throws ServiceNotFound
     */
    public function deleteCourseSpecification(CourseSpecification $courseSpec, $deleteFolder = false)
    {
        // TODO: make delete of folder and course atomic

        if ($courseSpec->isDeleted()){
            $this->getResponse()->status->set(410);
        }

        // Call delete(), even if already deleted, to enable mapper hooks to fire
        // (this also cascades the deletions to constituent units and assessments)
        $this->courseSpecificationMapper->delete($courseSpec);

        if ($deleteFolder) {
            /** @var FolderController $folderController */
            $folderController = $this->getController('folder');
            $folderController->recursiveDelete($courseSpec->getFolder()->getId(), $this->getUser());
        }

        $this->getResponse()->content->set($this->buildJsonOutput([
            'id'     => $courseSpec->getId(),
            'result' => 'success',
        ]));
    }


    /**
     * Delete a unit specification.
     *
     * Does not delete the folder the UnitSpec is linked to.
     * (Essentially we are converting a Unit back to a Folder)
     *
     * @api   POST /course/unitspecs/{id}/delete
     * @since 160
     *
     * @param UnitSpecification $unitSpec The unit spec to delete
     * @throws Base\Exception
     * @throws MapperException
     */
    public function deleteUnitSpecification(UnitSpecification $unitSpec)
    {
        // Does this Course exist ?
        if (!$unitSpec->getId()){
            $this->sendError('Unit not found');
            return;
        }

        if ($unitSpec->isDeleted()){
            $this->getResponse()->status->set(410);
        }

        // Call delete(), even if already deleted, to enable mapper hooks to fire
        $this->unitSpecificationMapper->delete($unitSpec);

        $this->getResponse()->content->set($this->buildJsonOutput([
            'id' => $unitSpec->getId(),
        ]));
    }


    /**
     * Delete a unit implementation.
     *
     * Does not delete the folder the UnitImpl is linked to.
     * (Essentially we are converting a Unit back to a Folder)
     *
     * @api   POST /course/units/{id}/delete
     * @since 160
     *
     * @param UnitImplementation $unitImpl The unit implementation to delete
     * @throws Base\Exception
     * @throws MapperException
     */
    public function deleteUnitImplementation(UnitImplementation $unitImpl)
    {
        // Does this Course exist ?
        if (!$unitImpl->getId()){
            $this->sendError('Unit not found');
            return;
        }

        if ($unitImpl->isDeleted()){
            $this->getResponse()->status->set(410);
        }

        // Call delete(), even if already deleted, to enable mapper hooks to fire
        $this->unitImplementationMapper->delete($unitImpl);

        $this->getResponse()->content->set($this->buildJsonOutput([
            'id' => $unitImpl->getId(),
        ]));
    }


    /**
     * @api   GET /course/:folderId/nodes
     * @since 155
     *
     * @param int $folderId
     * @throws BaseMapper\DefinitionException
     * @throws Deleted
     * @throws MapperException
     */
    public function listNodes($folderId)
    {
        $mime = $this->getRequest()->query['mime'];

        if (empty($mime)) {
            $mime = '*/*';
        }

        // hydrate so children can detect parent folder type
        $folder = $this->folderMapper->findById($folderId);

        $treeBuilder = new TreeBuilder(
            $this->folderMapper,
            $this->getUser(),
            $folder->getId()
        );

        // Don't show children if this is an invalid location
        $showChildren = $treeBuilder->isValidCourseLocation($folderId);

        $this->getResponse()->content->set([
            'format' => 'json',
            'data'   => $showChildren ? $treeBuilder->buildChildNodes($folderId, [], $mime) : [],
        ]);
    }
}
