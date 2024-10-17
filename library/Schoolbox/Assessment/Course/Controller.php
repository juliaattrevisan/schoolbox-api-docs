<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Alaress\Web\Controller as Base;
use Component_Config;
use Component_ConfigMapper;
use Component_Instance;
use Component_InstanceMapper;
use InvalidArgumentException;
use PDOException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RangeException;
use Schoolbox\Assessment\Course\Api\Helper;
use Schoolbox\Assessment\Course\Template\Mapper as CourseTemplateMapper;
use Schoolbox\Assessment\Course\Template\Model as CourseTemplateModel;
use Schoolbox\Resource\Folder\Ancestry;
use Schoolbox\Resource\HomepageConfig;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplate;
use UnexpectedValueException;
use Schoolbox\Application;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Assessment\Course\Config\Model as CourseConfigModel;
use Schoolbox\Collection;
use Schoolbox\Curriculum\Node;
use Schoolbox\Curriculum\Node\Mapper as CurriculumNodeMapper;
use Schoolbox\GenericCollection;
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
use Schoolbox\YearLevel;

/**
 * Class Controller
 *
 * @package Schoolbox\Assessment\Course
 */
class Controller extends Base\Base
{
    /**
     * Selected tab for this controller.
     */
    private const TAB = 'course';

    /**
     * @param AssessmentMapperProxy     $assessmentMapperProxy
     * @param CourseSpecificationMapper $courseSpecificationMapper
     * @param UnitSpecificationMapper   $unitSpecificationMapper
     * @param UnitImplementationMapper  $unitImplementationMapper
     * @param FolderMapper              $folderMapper
     * @param Access                    $access
     * @param CourseConfigMapper        $courseConfigMapper
     * @param UserAccessMapper          $userAccessMapper
     * @param AssessmentMapper          $assessmentMapper
     * @param CurriculumNodeMapper      $curriculumNodeMapper
     * @param CourseTemplateMapper      $courseTemplateMapper
     */
    public function __construct(
        private AssessmentMapperProxy $assessmentMapperProxy,
        private CourseSpecificationMapper $courseSpecificationMapper,
        private UnitSpecificationMapper $unitSpecificationMapper,
        private UnitImplementationMapper $unitImplementationMapper,
        private FolderMapper $folderMapper,
        private Access $access,
        private CourseConfigMapper $courseConfigMapper,
        private UserAccessMapper $userAccessMapper,
        private AssessmentMapper $assessmentMapper,
        private CurriculumNodeMapper $curriculumNodeMapper,
        private CourseTemplateMapper $courseTemplateMapper,
        private HomepageConfigMapper $homepageConfigMapper,
        private Component_InstanceMapper $cim,
        private Component_ConfigMapper $ccm,
        private \Schoolbox\Tile\Layout\Mapper $layoutMapper,
        private \Schoolbox\Tile\Mapper $tileMapper,
        private Ancestry $ancestry
    ) {
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

        if ($unitExists) {
            if ($courseExists) { // Do the folders already have a Unit and Course attached ?
                $errors[] = "Course (id: {$course->getId()}) and Unit (id: {$unit->getId()}) already linked.";
                $this->returnJson($errors, 400);
                return;
            } else {   // Does Unit already exist and belong to different Course?
                $errors[] = "Unit (id: {$unit->getId()}) already exists in Course (id: {$unit->getCourse()->getId()})";
                $this->returnJson($errors, 400);
                return;
            }
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
                $errors[] = 'Course Folder permission error: ' . $courseFolder->getId();
            }

            // Does the proposed Course Folder already have a Unit
            $courseFolderUnit = $courseFolder->getUnitSpecification();

            if ($courseFolderUnit instanceof UnitSpecification && $courseFolderUnit->getId()) {
                $errors[] = 'Proposed Course folder already has a Unit: ' . $courseFolderUnit->getId();
            }
        }

        // Does user have WRITE+ access to the Unit folder?
        $unitFolderAccess = $this->access->getFolderAccess($this->getUser(), $unitFolder->getId());

        if ($unitFolderAccess < UserAccess::ACCESS_WRITE) {
            $errors[] = 'Unit Folder permission error: ' . $unitFolder->getId();
        }

        // Does the proposed Unit Folder already have a Course ?
        $unitFolderCourse = $unitFolder->getCourseSpecification();

        if ($unitFolderCourse instanceof CourseSpecification && $unitFolderCourse->getId()) {
            $errors[] = 'Proposed Unit folder already has a Course: ' . $unitFolderCourse->getId();
        }

        // Are children courses / units?
        $children = $this->folderMapper->findAllChildren($courseFolder->getId());
        foreach ($children as $childFolder) {
            // Does the proposed Unit Folder already have a Course ?
            $childFolderCourse = $childFolder->getCourseSpecification();
            if ($childFolderCourse instanceof CourseSpecification && $childFolderCourse->getId()) {
                $errors[] = 'The proposed folder already contains a course: ' . $childFolder->getTitle();
            }
        }

        // Is $courseFolder a child of a course?
        $courseParent = $this->folderMapper->findById($courseFolder->getParent()->getId());
        if ($courseParent->getCourseSpecification() instanceof CourseSpecification) {
            $errors[] = 'The proposed course folder is a child of course: ' . $courseParent->getFolder()->getTitle();
        }

        // Is $courseFolder a child of a unit?
        if ($courseParent->getUnitSpecification() instanceof UnitSpecification) {
            $errors[] = 'The proposed course folder is a child of unit: ' . $courseParent->getFolder()->getTitle();
        }

        if (\count($errors)) {
            $this->returnJson($errors, 400);
            return;
        }

        // No errors. Proceed.

        // Create Course if required
        if (!$courseExists) {
            /* Course might have been deleted at some point in the past
             * See if there's a deleted row we can recycle since foreign keys
             * will stop us from inserting a tuple that already exists
             */
            try {
                $course = $this->courseSpecificationMapper->findOneByColumn(
                    'folder_id',
                    $courseFolder->getId(),
                    [
                        'deleted' => true,
                    ]
                );
            } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
                /** @var CourseSpecification $deletedCourse */
                $deletedCourse = $e->getDeletedObject();

                $course = $deletedCourse->setDeletedAt(null);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $course = $this->courseSpecificationMapper->createModel();
                // give the course the default template of initial `1: Default` or if that doesn't exist, the first non deleted entry...
                try {
                    $template = $this->courseTemplateMapper->findById(CourseTemplateMapper::INITIAL_DEFAULT);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $template = $this->courseTemplateMapper->findAll()->first();
                }
                $course->setTemplate($template);
            }
            $course->setFolder($courseFolder);
        }

        /* Unit folder might have been deleted at some point in the past
         * See if there's a deleted row we can recycle since foreign keys
         * will stop us from inserting a tuple that already exists
         */
        try {
            $unit = $this->unitSpecificationMapper->findOneByColumn('folder_id', $unitFolder->getId());
        } catch (\Schoolbox\Mapper\Exception\Deleted $e) {
            /** @var UnitSpecification $deletedUnit */
            $deletedUnit = $e->getDeletedObject();

            $unit = $deletedUnit->setDeletedAt(null);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $unit = $this->unitSpecificationMapper->createModel();
        }

        $unit->setFolder($unitFolder);

        // Link Course and Unit
        $unit->setCourse($course);
        $course->getUnits()->add($unit);

        // And save
        $response = [];

        if (!$courseExists) {
            $this->courseSpecificationMapper->save($course);
            $response['course'] = $course->getId();
        }

        // Create Unit
        $this->unitSpecificationMapper->save($unit);
        $response['unit'] = $unit->getId();

        $this->response = $this->responseAsJson($response);
    }


    /**
     * Convenience method for returning JSON output
     *
     * @param string|array $message
     * @param int $code
     */
    protected function returnJson($message, $code = 200)
    {
        if ($this->isXhrRequest()) {
            $this->response = $this->responseAsJson($message)->withStatus($code);
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
        $assessmentSequence = $this->requestPost->get('sequence');
        $this->returnJson($this->assessmentMapper->sequenceAssessments($assessmentSequence));
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
    public function modifyCourse(CourseSpecification $courseSpec): void
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

        $this->response = $this->responseDependentOnRequest(
            '@course/course.twig',
            [
                'templates'         => $this->courseTemplateMapper->findAll(['deleted' => true]),
                'courseSpec'        => $courseSpec,
                'curriculumEnabled' => $this->getConfigLoader()->get('curriculum_tagging_enabled'),
                'folderAccess'      => $folderAccess,
                'encodedReturnUrls' => $encodedReturnUrls,
                'isValidFacultyRoot' => $this->courseSpecificationMapper->isConfiguredFacultyRootValid(),
                'curriculumCodes'   => $courseSpec->getFolder()->getViewableCurriculumNodes()->reindex(),
            ],
            self::TAB,
        );
    }


    /**
     * Form to create a new course
     *
     * @api   GET /course/new{/:name}
     * @since 160
     *
     * @param string|null $name  Provided when creating from empty course search result
     */
    public function newCourse(?string $name): void
    {
        $this->response = $this->responseDependentOnRequest(
            '@course/course.twig',
            [
                'templates'          => $this->courseTemplateMapper->findAll(),
                'courseSpec'         => null,
                'courseName'         => $name ? urldecode($name) : null,
                'curriculumEnabled'  => $this->getConfigLoader()->get('curriculum_tagging_enabled'),
                'isValidFacultyRoot' => $this->courseSpecificationMapper->isConfiguredFacultyRootValid(),
            ],
            self::TAB,
        );
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
     * @throws UnexpectedValueException
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function createCourse()
    {
        $post = $this->requestPost;

        // Get the default permissions to give to Course Folders
        $rolePermissions = [];
        foreach ($this->courseConfigMapper->findByColumn('page', 'course') as $courseConfig) {
            /** @var CourseConfigModel $courseConfig */
            $rolePermissions[$courseConfig->getRoleId()] = $courseConfig->getPermission();
        }

        try {
            $this->validateCourseData(trim($post['name']), (int)trim($post['parent']), null);

            // Template
            try {
                $template = $this->courseTemplateMapper->findById($post['template']);
            } catch (Deleted $e) {
                $this->sendError('It appears this template no longer exists. Please refresh your page to re-select.');
                return;
            }

            // Create new CourseFolder
            $courseFolder = $this->persistFolder(
                $post['name'],
                (int)trim($post['parent']),
                array_filter(explode(',', $post['curriculumCodes'][0] ?? '')),
                $this->getConfigLoader()->get('course_builder_default_course_page_inherit'),
                $template->getCourseTemplate()->getId(),
                $rolePermissions,
                false,
                []
            );

            $courseSpec = $this->courseSpecificationMapper->createModel()
                ->setFolder($courseFolder)
                ->setSubjectCode(trim($post->get('subjectCode', '')))
                ->setTemplate($template);

            $courseFolder->setCourseSpecification($courseSpec);

            $this->courseSpecificationMapper->save($courseSpec);
        } catch (Base\Exception $e) {
            $this->sendError($e);
            return;
        }


        if ($this->isXhrRequest()) {
            // respond to XHR with JSON
            $this->response = $this->responseAsJson([
                'id'              => $courseSpec->getId(),
                'urlModifyCourse' => $this->routeGenerator->generate('course.updateCourse', ['id' => $courseSpec->getId()]),
                'urlNewUnit'      => $this->routeGenerator->generate('course.newUnit', ['id' => $courseSpec->getId()]),
            ]);
        } else {
            // respond to synchronous POST with redirect
            $this->response = $this->redirectAfterPost($this->getRouteIfAuthorized('course.courseList'));
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
     * @param string      $folderName
     * @param int|null    $parentFolderId
     * @param Folder|null $folder
     * @throws Base\Exception
     * @throws MapperException
     * @todo move route perm logic from controller to module (eg course parent folder access)
     */
    protected function validateCourseData(string $folderName, ?int $parentFolderId, ?Folder $folder = null): void
    {
        $errors = [];
        $parentFolder = null;

        if (!$folderName) {
            $errors[] = 'You need to give the course a name';
            $folderName = 'this course';
        }

        // Was no parent indicated? (as opposed to explicitly setting to root)
        if (!$parentFolderId) {
            $errors[] = "You need to select a faculty or class for {$folderName} to live in";
        } else {
            $access = $this->access->getFolderAccess($this->getUser(), $parentFolderId);

            if ($access < UserAccess::ACCESS_WRITE) {
                $errors[] = 'You need to select a Faculty / Subject for which you have write access';
            } else {
                try {
                    $parentFolder = $this->folderMapper->findCheaplyById($parentFolderId);
                } catch (MapperException $e) {
                    $errors[] = "You need to select an existing faculty or class for {$folderName} to live in";
                }
            }
        }

        // if there were any validation problems, throw that back
        if ($errors || !$parentFolder instanceof Folder) {
            throw new Base\Exception('Could not save.', $errors);
        }

        // Explicitly set to root (as opposed to not set)
        if (0 === $parentFolderId && 0 !== $this->courseSpecificationMapper->getFacultyRootFolder()->getId()) {
            throw new Base\Exception('Courses may not be created in the root folder');
        }


        // Deeper logic tests
        try {
            // Is this a create, or are we moving an existing course?
            if (!$this->courseSpecificationMapper->isFolderValid($parentFolder)) {
                if ($folder) {
                    throw new Base\Exception('Courses may not be moved outside the faculty folder');
                } else {
                    throw new Base\Exception('Courses may not be created outside the faculty folder');
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
        $post          = $this->requestPost;
        $newCourseName = trim($post->get('name', ''));
        if ($newCourseName === '') {
            $this->sendError(new Base\Exception('You need to give the course a name. ', ['Courses must have names.']));
            return;
        }

        // Template
        try {
            $template = $this->courseTemplateMapper->findById($post['template']);
        } catch (Deleted $e) {
            /**
             * Catch deleted and use the deleted item, this is still valid here
             */
            /** @var CourseTemplateModel $template */
            $template = $e->getDeletedObject();
        }

        $parentFolderId = (int)trim($post->get('parent'));
        $originalParentFolderId = $courseSpec->getFolder()->getParent()->getId();

        // If course is moved under another folder, validate
        if ($originalParentFolderId !== $parentFolderId) {
            try {
                $this->validateCourseData($newCourseName, $parentFolderId, $courseSpec->getFolder());
                $parentFolder = $this->folderMapper->findCheaplyById($parentFolderId);
                $courseSpec->getFolder()->setParent($parentFolder);
            } catch (Base\Exception $e) {
                $this->sendError($e);
                return;
            }
        }

        // Save each Unit's name
        if (\is_array($post->get('unit'))) {
            $courseSpec->setUnits($this->unitSpecificationMapper->findAllByCourseId($courseSpec->getId()));

            /* Keep track of how many units were missing names
             */
            $emptyUnit     = 0;
            $readOnlyUnits = [];

            // save unit name changes
            foreach ($courseSpec->getUnits() as $unitSpec) {
                if (!isset($post->get('unit')[$unitSpec->getId()])) {
                    // no change required
                    continue;
                }
                $unitName = trim($post->get('unit')[$unitSpec->getId()]['name'] ?? '');
                if ($unitName === $unitSpec->getFolder()->getName()) {
                    // name is not changed.
                    continue;
                }
                if ($this->access->getFolderAccess($this->getUser(), $unitSpec->getFolder()->getId()) < UserAccess::ACCESS_WRITE) {
                    $readOnlyUnits[] = $unitSpec->getFolder()->getName();
                    continue;
                }

                /* Don't try to save empty units
                 */
                if ('' !== $unitName) {
                    // Re-hydrate unit folder to ensure curriculum codes etc are hydrated.
                    $unitFolder = $this->folderMapper->findById($unitSpec->getFolder()->getId());
                    $this->folderMapper->save($unitFolder->setName($unitName));
                } else {
                    $emptyUnit++;
                }
            }

            /* Report problems with units
             */
            if ($emptyUnit) {
                $this->sendError(new Base\Exception('At least one unit in this course has no name. ', ['Units must have names.']));
                return;
            }
            if (count($readOnlyUnits) > 0) {
                $this->sendError(new Base\Exception('Cannot update units. ', [sprintf("You don't have permission to update %s.", implode(',', $readOnlyUnits))]));
                return;
            }
        } else {
            $this->sendError(new Base\Exception('Cannot create a Course without Units. ', ['You need to create at least 1 unit.']));
            return;
        }

        $courseSpecFolder       = $this->folderMapper->findById($courseSpec->getFolder()->getId());
        $newCurriculumCodes     = array_filter(explode(',', $post['curriculumCodes'][0] ?? ''));
        $currentCurriculumCodes = $courseSpecFolder->getCurriculumNodes()->getPropertyValues('id');
        $subjectCode            = trim($post->get('subjectCode', ''));

        // If anything changed for course
        if (
            $courseSpecFolder->getName() !== $newCourseName
            || array_diff($currentCurriculumCodes, $newCurriculumCodes)
            || array_diff($newCurriculumCodes, $currentCurriculumCodes)
            || $originalParentFolderId !== $parentFolderId
            || $courseSpec->getSubjectCode() !== $subjectCode
            || $courseSpec->getTemplate()?->getId() !== $template->getId()
        ) {
            if ($this->access->getFolderAccess($this->getUser(), $courseSpecFolder->getId()) < UserAccess::ACCESS_WRITE) {
                $this->sendError(new Base\Exception('Cannot update course. ', ["You don't have permission to update {$courseSpecFolder->getName()}."]));
                return;
            }

            // curriculumCodes is not set when curriculum is disabled
            $courseSpecFolder->setName($newCourseName);
            $courseSpec->setFolder($courseSpecFolder);
            $courseSpec->setSubjectCode($subjectCode);

            // Only update existing course / unit folders, if we changed the template
            if ($courseSpec->getTemplate()?->getId() !== $template->getId()) {
                $courseSpec->setTemplate($template);
                // update existing units / course with the new templates
                $this->updateExistingFolders($courseSpec, $template);
            }

            // Save the Course
            $this->courseSpecificationMapper->save($courseSpec);

            // Save the folder
            $courseSpecFolder->setCurriculumNodes($this->curriculumNodeMapper->findByColumn('id', $newCurriculumCodes));

            $this->folderMapper->save($courseSpecFolder);
        }

        if ($this->isXhrRequest()) {
            // respond to XHR with JSON
            $this->response = $this->responseAsJson([
                'id' => $courseSpec->getId(),
            ]);
        } else {
            // respond to synchronous POST with redirect
            $returnUrlEncoded = $this->requestPost['returnUrl'] ?? '';

            if ($returnUrlEncoded !== '') {
                $returnUrl = Redirect::decode($returnUrlEncoded);
            } else {
                // If no return URL supplied, redirect to course list page
                $returnUrl = $this->getRouteIfAuthorized('course.courseList');
            }

            $this->response = $this->redirectAfterPost($returnUrl);
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

        $post = $this->requestPost;
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

            $folder = $this->persistFolder(
                $name,
                $courseSpec->getFolder()->getId(),
                [],
                $this->getConfigLoader()->get('course_builder_default_unit_page_inherit'),
                $courseSpec->getTemplate()->getUnitTemplate()->getId(),
                $rolePermissions,
                false,
                []
            );

            // Persist the unit too
            $unitSpec = $this->unitSpecificationMapper->createModel()
                ->setFolder($folder)
                ->setCourse($courseSpec);

            $this->unitSpecificationMapper->save($unitSpec);

            $encodedReturnUrl = $this->getReturnUrl($courseSpec->getId(), $unitSpec->getId());

            $data = [
                'unitSpec'         => $unitSpec,
                'encodedReturnUrl' => $encodedReturnUrl,
                '_writable'        => true,
                '_expanded'        => true,
                'toastSelector'    => '[data-relation="ajax-response"]',
            ];

            $this->response = $this->responseDependentOnRequest(
                '@course/unit.twig',
                $data,
                self::TAB,
            );
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            // quite simple not able to find things we need. bye bye.
            $this->sendError('Could not save because of an internal error.', [], 500);
            return;
        } catch (\Exception $e) {
            // whatever the exception, recover from it as best you can
            $this->sendError("Could not save because {$e->getMessage()}");
            return;
        }
    }


    /**
     * Wrapper around Folder\Controller::persistFolder() and does some extra perms checking
     *
     * @param string   $name
     * @param int      $parentId
     * @param array    $curriculumCodeIds
     * @param bool     $inherit            Whether to inherit permissions
     * @param int      $templateId
     * @param array    $rolePermissions    Maps role to permission level
     * @param bool     $recursePermissions If true && user is SU, applies 'inherit' to entire subtree
     * @param array    $classCodes
     * @return Folder
     * @throws Deleted
     * @throws MapperException
     * @throws RangeException
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws Application\Exception\Exception
     * @throws UnexpectedValueException
     * @throws BaseMapper\DefinitionException
     */
    public function persistFolder(
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
                ->setFavourite(false)
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
        $query = $this->requestQuery;
        $data = $this->getCourseData();
        $data['returnUrlKey'] = Redirect::RETURN_URL;

        if ($query->get('folder')) {
            $folderModel = $this->folderMapper->findById($query->get('folder'));
            /* The payload will be replaced by the course api later, but we can use the helper class first
             * to reduce the amount of code maintenance for the time being, the helper class should only be
             * instantiated when needed, so should be fine to be here instead of injecting it
             */
            $helper = new Helper(
                $this->courseSpecificationMapper,
                $this->folderMapper,
                $this->unitSpecificationMapper,
                $this->ancestry,
                $this->access,
                $this->getUser(),
                null,
                0,
                null
            );
            $data['suggestions'] = $helper->getLinkSuggestions($folderModel);
        }

        if ($this->isXhrRequest()) {
            $options = [
                'hasClose'         => true,
                'hasAdd'           => false,
                'hasTot'           => false,
                'linkToUnits'      => false,
                'hasBreadcrumb'    => false,
                'encodedReturnUrl' => $query->get('encodedReturnUrl'),
                'folder'           => ['id' => $query->get('folder')],
                'component'        => ['id' => $query->get('component')],
            ];
        } else {
            $options = [
                'hasClose'         => false,
                'hasAdd'           => true,
                'hasTot'           => true,
                'linkToUnits'      => true,
                'hasBreadcrumb'    => true,
                'encodedReturnUrl' => Redirect::encode($this->routeGenerator->generate('course.courseList')),
            ];
        }

        $this->response = $this->responseDependentOnRequest(
            '@course/courseList.twig',
            array_merge($data, $options),
            self::TAB,
        );
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
        $query = $this->requestQuery;

        $search = $query->get('search');
        $folder = $query->get('folder');

        $data = $this->getCourseData($search);

        // only offer suggestions if you're not searching
        if ($folder && !$search) {
            $folderModel = $this->folderMapper->findById($folder);
            /* The payload will be replaced by the course api later, but we can use the helper class first
             * to reduce the amount of code maintenance for the time being, the helper class should only be
             * instantiated when needed, so should be fine to be here instead of injecting it
             */
            $helper = new Helper(
                $this->courseSpecificationMapper,
                $this->folderMapper,
                $this->unitSpecificationMapper,
                $this->ancestry,
                $this->access,
                $this->getUser(),
                null,
                0,
                null
            );
            $data['suggestions'] = $helper->getLinkSuggestions($folderModel);
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

        $this->response = $this->responseDependentOnRequest(
            '@course/courseListResults.twig',
            array_merge($data, $options),
            self::TAB,
        );
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
        $findOptions = ['order' => ['f.name' => 'asc']];
        if (null === $searchParam) {
            $allCourses = $this->courseSpecificationMapper->findAll($findOptions);
        } else {
            $allCourses = $this->courseSpecificationMapper->findAllByName($searchParam, $findOptions);
        }

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

            /* For each of the courses found in $data['courses'], decide if it's a courseSpec
             * (it should already be, but we're specifically editing courseSpecs only
             * in this usage - if you pollute the data, don't get sick from it)
             *
             * Then look at its folder instance and find out if you have WRITE+ access to it
             * If you have write access to the courseSpec's folder context, you can modify
             * the course - otherwise, deny the link but show it in the index anyway I guess
             *
             * (Staff members who don't have permissions to edit might still find it useful
             * to see what's in here)
             */
            $courseFolderAccess = $this->access->getFolderAccess($this->getUser(), $currentCourse->getFolder()->getId());
            if ($courseFolderAccess > UserAccess::ACCESS_READ) {
                $data['folderAccess'][$currentCourse->getFolder()->getId()] = true;
            } elseif ($courseFolderAccess === UserAccess::ACCESS_READ) {
                $data['folderAccess'][$currentCourse->getFolder()->getId()] = false;
            } else {
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
        return Ancestry::getInstance($this->folderMapper)->findParentsCheaply($course, $this->courseSpecificationMapper->getFacultyRootFolder());
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
        $codes = $folder->getCurriculumNodes()->getPropertyValues('code');

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
     * @param int $folderId
     * @api   GET /course/parent
     * @since 160
     */
    public function parentList($folderId)
    {
        if (empty($folderId)) {
            $folderId = $this->courseSpecificationMapper->getFacultyRootFolder()->getId();
        }

        $treeBuilder = new TreeBuilder(
            $this->folderMapper,
            $_SESSION['user'],
            $folderId
        );

        $facultyRootFolder = $this->courseSpecificationMapper->getFacultyRootFolder();
        $treeData = $treeBuilder->buildPartialTree($folderId, $facultyRootFolder->getId() ?: '#');

        $data = [
            'treedata' => json_encode($treeData),
            'folderId' => $folderId,
        ];

        $this->response = $this->responseDependentOnRequest('@course/picker.twig', $data, self::TAB);
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
     */
    public function deleteCourseSpecification(CourseSpecification $courseSpec, $deleteFolder = false)
    {
        // TODO: make delete of folder and course atomic
        $status = 200;
        if ($courseSpec->isDeleted()) {
            $status = 410;
        }

        // Call delete(), even if already deleted, to enable mapper hooks to fire
        // (this also cascades the deletions to constituent units and assessments)
        $this->courseSpecificationMapper->delete($courseSpec);

        if ($deleteFolder) {
            /** @var FolderController $folderController */
            $folderController = $this->getController('folder');
            $folderController->recursiveDelete($courseSpec->getFolder()->getId(), $this->getUser());
        }

        $this->response = $this->responseAsJson([
            'id'     => $courseSpec->getId(),
            'result' => 'success',
        ])->withStatus($status);
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
        if (!$unitSpec->getId()) {
            $this->sendError('Unit not found');
            return;
        }

        $status = 200;
        if ($unitSpec->isDeleted()) {
            $status = 410;
        }

        // Call delete(), even if already deleted, to enable mapper hooks to fire
        $this->unitSpecificationMapper->delete($unitSpec);

        $this->response = $this->responseAsJson([
            'id' => $unitSpec->getId(),
        ])->withStatus($status);
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
        if (!$unitImpl->getId()) {
            $this->sendError('Unit not found');
            return;
        }

        $status = 200;
        if ($unitImpl->isDeleted()) {
            $status = 410;
        }

        // Call delete(), even if already deleted, to enable mapper hooks to fire
        $this->unitImplementationMapper->delete($unitImpl);

        $this->response = $this->responseAsJson([
            'id' => $unitImpl->getId(),
        ])->withStatus($status);
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
        $mime = $this->requestQuery['mime'];

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

        $this->response = $this->responseAsJson(
            $showChildren ? $treeBuilder->buildChildNodes($folderId, [], $mime) : [],
        );
    }


    /**
     * Re applies the course framework / template onto the specified course and its child units.
     *
     * @api POST /course/reapply/{id}
     * @since 24.0
     *
     * @param CourseSpecification $courseSpec
     * @return void
     */
    public function reApplyFramework(CourseSpecification $courseSpec)
    {
        try {
            // update existing units / course with the new templates
            $this->updateExistingFolders($courseSpec, $courseSpec->getTemplate());
        } catch (MapperException $e) {
            $this->response = $this->responseAsJson(['error' => $e->getMessage()])->withStatus(400);
        }
        $this->response = $this->responseAsJson(['message' => 'success'])->withStatus(200);
    }

    /**
     * Update Spec Course and Unit homepage templates
     * This will purge all components from the homepage and re create only the ones from the selected template
     * @see \Schoolbox\Resource\HomepageConfigMapper::resetToTemplate
     *
     * @param CourseSpecification $courseSpec
     * @param CourseTemplateModel $template
     * @return void
     * @throws MapperException
     */
    private function updateExistingFolders(CourseSpecification $courseSpec, CourseTemplateModel $template)
    {
        /** @var FolderController $folderController */
        $folderController = $this->getController('folder');

        // update the course homepage template
        $homepageTemplate = $folderController->buildHomepageTemplate($courseSpec->getFolder(), $template->getCourseTemplate()->getId());
        $this->updateHomepageTemplate($courseSpec->getFolder(), $homepageTemplate);

        // loop through existing units
        foreach ($courseSpec->getUnits() as $unit) {
            // update unit homepage templates
            $homepageTemplate = $folderController->buildHomepageTemplate($unit->getFolder(), $template->getUnitTemplate()->getId());
            $this->updateHomepageTemplate($unit->getFolder(), $homepageTemplate);
        }
    }

    /**
     * Will append all 'new' components from the destination component at the end of the columns
     * Based off:
     * @param Folder           $folder
     * @param HomepageTemplate $template
     * @return void
     * @throws MapperException
     *
     * @see \Schoolbox\Resource\Folder\Controller::updateHomepageTemplate
     * @see \Schoolbox\Resource\HomepageConfigMapper::resetToTemplate
     */
    private function updateHomepageTemplate(Folder $folder, HomepageTemplate $template)
    {
        // if we are changing to the No Hompage option, clear the homepage and exit.
        if (HomepageTemplate::HOMEPAGE_NONE === $template->getId()) {
            $folder->setHomepage(null);
            return;
        }
        // if existing template was No Homepage, we need to build more things...
        if (null === $folder->getHomepage()) {
            $homepageConfig = (new HomepageConfig())
                ->setCategory($folder)
                ->setTemplate($template);
            $folder->setHomepage($homepageConfig);
            $this->homepageConfigMapper->save($homepageConfig);
            // destructively purge and re build the folder / homepage
            // this assumes you don't have a homepage at current, and are changing to a homepage template.
            $this->homepageConfigMapper->resetToTemplate($homepageConfig);
        } else {
            // update the homepage config to the new template
            $homepageConfig = $this->homepageConfigMapper->findById($folder->getId())
                                                         ->setCategory($folder)
                                                         ->setTemplate($template);
            $folder->setHomepage($homepageConfig);
            $this->homepageConfigMapper->save($homepageConfig);
        }

        /**
         * now do the merge / append new components
         * this is a dupe of @see \Schoolbox\Resource\HomepageConfigMapper::resetToTemplate
         * This will NOT change folder colour, or change the column widths
         */

        // retrieve, rearrange and reinsert the componentry
        /** @var Component_Instance[] $templateInstances */
        $templateInstances = $this->cim->findByContext('homepage_template', $homepageConfig->getTemplate()->getId());
        $existingInstances = $this->cim->findByContext('homepage', $folder->getId());

        if (!count($templateInstances)) {
            return;
        }

        foreach ($templateInstances as $currentInstance) {
            /**
             * check if there is already a component with this definition ID
             * and if there is, and the definition says multiple false, then ignore this component and move on
             * there are some components that do not work when there are multiple instances on a page `Course Builder` among others
             *
             * If there is already a component by the same type and component name on the page then also continue and don't create a dupe.
             * This will cause issues if there are multiple in the template with the same heading, ie multiple textboxes with the default of 'Text Box',
             * or multiple blank Tiles components
             */
            if ($existingInstances->getBy('definitionId', $currentInstance->getDefinitionId())) {
                if (!$currentInstance->getDefinition()->getMultiple()) {
                    continue;
                }
                // if the component is going to be a direct dupe of what already exists, then ignore the new one
                foreach ($existingInstances->filterBy('definitionId', $currentInstance->getDefinitionId()) as $existing) {
                    if (
                        $existing->getConfigByKey('componentname')->getValue() === $currentInstance->getConfigByKey('componentname')->getValue()
                        && $existing->getPosition() === $currentInstance->getPosition()
                    ) {
                        // break out of the parent for loop (the dupe / spawn $templateInstances loop)
                        continue 2;
                    }
                }
            }
            // preserve the config
            /** @var \Component_ConfigDefinition[] $configs */
            $configs = $currentInstance->getConfig();

            /* Clone before saving, otherwise it does some magical caching stuff
             * and tries to re-assign config instances to the wrong component instance
             * in findByFields:247 (it tries to match the homepage template's configs
             * to the $inst, which is a misbehaviour that is seemingly rectified by clone)
             */
            $inst = clone($currentInstance);

            $inst
                ->setId(null)
                ->setConfig(new Collection())
                ->setModule('homepage')
                ->setIdentifier($homepageConfig->getCategory()->getId());

            $ids = $this->cim->save($inst);

            if (!count($configs)) {
                continue;
            }
            $id = $ids[0];
            foreach ($configs as $c) {
                if ($c->getId()) {
                    // Gross special case for tile component:
                    // copy the homepage template's tile layout
                    // TODO move this somewhere it actually belongs
                    $value = $c->getValue();
                    try {
                        $src = $currentInstance->getConfigByKey('src');
                    } catch (\DomainException $e) {
                        $src = null;
                    }
                    if ($c->getKey() === 'layoutId' && (!$src || empty($src->getValue()))) {
                        try {
                            $layout = $this->layoutMapper->findById($c->getValue());
                            $layout
                                ->setId(null)
                                ->setDashboardRole(null)
                                ->setHomepageTemplate(null)
                                ->setFolder($homepageConfig->getCategory());
                            $this->layoutMapper->save($layout);
                            $value = $layout->getId();
                            foreach ($layout->getTileList() as $tile) {
                                $tile->setId(null)->setTileLayout($layout);
                            }
                            $this->tileMapper->save($layout->getTileList());
                        } catch (MapperException $e) {
                            // Don't save anything
                            continue;
                        }
                    }

                    $conf = new Component_Config();
                    $conf
                        ->setInstanceId($id)
                        ->setValue($value)
                        ->setConfigDefinitionId($c->getId());
                    $this->ccm->save($conf);
                }
            }
        }
    }
}
