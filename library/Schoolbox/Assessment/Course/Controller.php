<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Alaress\Authorizer\Failure;
use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Tree\TreeNode;
use Alaress\Web\Controller as Base;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Web\Request\Values;
use Component_DefinitionMapper;
use Component_Instance;
use Component_InstanceMapper;
use RangeException;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Assessment\Course\Config\Model as CourseConfigModel;
use Schoolbox\Assessment\Course\DeprecatedModel as CourseModel;
use Schoolbox\Assessment\Course\Unit\DeprecatedMapper as CourseUnitMapper;
use Schoolbox\Assessment\Course\Task\InstanceMapper as TaskInstanceMapper;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Translator as CourseSpecificationTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Translator as UnitSpecificationTranslator;
use Schoolbox\Learning\Course\SpecificationMapperProxy as CourseSpecificationMapperProxy;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper as CourseImplementationMapper;
use Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecificationMapperProxy;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted as MapperExceptionDeleted;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Browser\AbstractTreeBuilder;
use Schoolbox\Resource\Browser\Resources\TreeBuilder;
use Schoolbox\Resource\Folder\Ancestry;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Controller as FolderController;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Config;
use Schoolbox\Resource\File\IFile as FileInterface;
use Schoolbox\Resource\Folder\IFolder as FolderInterface;
use Schoolbox\Resource;
use Schoolbox\Resource\Folder\IFolder;
use Schoolbox\Learning\Course;
use \Schoolbox\Learning\Assessment\Component\Homepage\Builder\Controller as CourseBuilderController;

class Controller extends Base\Base
{

    /** @var FolderMapper */
    protected $folderMapper;

    /** @var SubmissionBoxMapper */
    protected $submissionBoxMapper;

    /** @var TaskMapper */
    protected $taskMapper;

    /**
     * @var CourseSpecificationMapper
     */
    protected $courseSpecificationMapper;

    /**
     * @var AssessmentMapperProxy
     */
    protected $assessmentMapperProxy;

    /**
     * @var CourseSpecificationMapperProxy
     */
    protected $courseSpecificationMapperProxy;

    /**
     * @var UnitSpecificationMapper
     */
    protected $unitSpecificationMapper;

    /**
     * @var UnitSpecificationMapperProxy
     */
    protected $unitSpecificationMapperProxy;

    /**
     * Translates between course specification data and domain models
     *
     * TODO: figure out if this should be a part of the course spec mapper
     *
     * @var CourseSpecificationTranslator
     */
    protected $courseSpecificationTranslator;

    /**
     * Translates between unit specification data and domain models
     *
     * TODO: figure out if this should be a part of the unit spec mapper
     *
     * @var UnitSpecificationTranslator
     */
    protected $unitSpecificationTranslator;

    /**
     * @var CourseImplementationMapper
     */
    protected $courseImplementationMapper;

    /**
     * @var CourseImplementationMapperProxy
     */
    protected $courseImplementationMapperProxy;

    /** @var Component_DefinitionMapper */
    protected $componentDefinitionMapper;

    /** @var Component_InstanceMapper */
    protected $componentInstanceMapper;

    /** @var UnitInstanceMapper */
    protected $unitInstanceMapper;

    /** @var HomepageConfigMapper */
    protected $homepageConfigMapper;

    /** @var  HomepageTemplateMapper */
    protected $homepageTemplateMapper;

    /** @var CourseConfigMapper */
    protected $courseConfigMapper;

    /** @var Error\Mapper */
    protected $errorMapper;

    /** @var UserAccessMapper */
    protected $userAccessMapper;

    /** @var UserMapper */
    protected $userMapper;

    /** @var Access */
    protected $access;

    /**
     * Set dependencies
     *
     * @param CourseSpecificationMapperProxy  $courseSpecificationDataSource
     * @param UnitSpecificationMapperProxy    $unitSpecificationDataSource
     * @param AssessmentMapperProxy           $assessmentDataSource
     * @param CourseImplementationMapper $courseImplementationMapper
     */
    public function __construct(
        CourseSpecificationMapperProxy $courseSpecificationDataSource,
        UnitSpecificationMapperProxy $unitSpecificationDataSource,
        AssessmentMapperProxy $assessmentDataSource,
        CourseImplementationMapper $courseImplementationMapper
    ) {
        // Deprecated course/unit specification mappers - all gone!

        // Proxy for course/unit specification mappers
        $this->setUnitSpecificationMapperProxy($unitSpecificationDataSource);
        $this->setCourseSpecificationMapperProxy($courseSpecificationDataSource);
        $this->setCourseImplementationMapper($courseImplementationMapper);

        // Proxy for assessment mapper
        $this->setAssessmentMapperProxy($assessmentDataSource);

        // TODO DI everything below this line?

        // Modern course/unit specification mappers
        $this->setCourseSpecificationMapper(CourseSpecificationMapper::getInstance());
        $this->setUnitSpecificationMapper(UnitSpecificationMapper::getInstance());

        // Mapper/domain translation layer
        $this->setUnitSpecificationTranslator(new UnitSpecificationTranslator());
        $this->setCourseSpecificationTranslator(new CourseSpecificationTranslator($this->getUnitSpecificationTranslator()));


        $this->setSubmissionBoxMapper(SubmissionBoxMapper::getInstance());
        $this->setFolderMapper(FolderMapper::getInstance());
        $this->setComponentDefinitionMapper(Component_DefinitionMapper::getInstance());
        $this->setComponentInstanceMapper(Component_InstanceMapper::getInstance());
        $this->setUnitInstanceMapper(UnitInstanceMapper::getInstance());
        $this->setTaskMapper(TaskMapper::getInstance());
        $this->setHomepageConfigMapper(HomepageConfigMapper::getInstance());
        $this->setHomepageTemplateMapper(HomepageTemplateMapper::getInstance());
        $this->setAccess(Access::getInstance());
        $this->setCourseConfigMapper(CourseConfigMapper::getInstance());
        $this->setUserAccessMapper(UserAccessMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setErrorMapper(Error\Mapper::getInstance());
    }

    /**
     * Generates a Unit and Course for a pair of folders.
     *
     * A single call to this method may create a Unit, a Course or both. Note that we do NOT enforce a
     * parent-child relationship between a Unit's folder and the Course's folder.
     *
     * While to the User it appears like folders have been converted, under the hood this is achieved
     * by associating the specified folders with a Unit and/or Course respectively.
     *
     * Does not permit moving an existing Unit to a new Course.
     *
     * @api POST /course/convert/:courseFolderId/:unitFolderId
     * @level 165
     *
     * @param Folder $courseFolder
     * @param Folder $unitFolder
     */
    public function convertFolders(Folder $courseFolder, Folder $unitFolder)
    {
        $errors = [];

        $course = $courseFolder->getCourseSpecification();
        $unit = $unitFolder->getUnitSpecification();

        $courseExists = $course instanceof CourseSpecification && $course->getId();
        $unitExists = $unit instanceof UnitSpecification && $unit->getId();

        // Do the folders already have a Unit and Course attached ?
        if($courseExists && $unitExists){
            $errors[] = "Course (id: {$course->getId()}) and Unit (id: {$unit->getId()}) already linked.";
            $this->returnJson($errors, 400);
        }

        // Does Unit already exist and belong to different Course?
        if($unitExists && !$courseExists){
            $errors[] = "Unit (id: {$unit->getId()}) already exists in Course (id: {$unit->getCourse()->getId()})";
            $this->returnJson($errors, 400);
        }

        // Is the proposed unit folder the same as the course folder?
        if($unitFolder->getId() == $courseFolder->getId()){
            $errors[] = 'Cannot convert the same folder into both a Unit and a Course. FolderId: ' . $courseFolder->getId();
        }

        // Check proposed Course
        if(!$courseExists){
            // Does user have WRITE+ access to Course Folder?
            $courseFolderAccess = $this->getAccess()->getFolderAccess($this->getUser(), [$courseFolder->getId()]);
            if($courseFolderAccess < UserAccess::ACCESS_WRITE){
                $errors[] = 'Course Folder permission error: '.$courseFolder->getId();
            }

            // Does the proposed Course Folder already have a Unit
            $courseFolderUnit = $courseFolder->getUnitSpecification();
            if($courseFolderUnit instanceof UnitSpecification && $courseFolderUnit->getId()){
                $errors[] = 'Proposed Course folder already has a Unit: ' . $courseFolderUnit->getId();
            }
        }

        // Check the proposed Unit
        if(!$unitExists){
            // Does user have WRITE+ access to the Unit folder?
            $unitFolderAccess = $this->getAccess()->getFolderAccess($this->getUser(), [$unitFolder->getId()]);
            if($unitFolderAccess < UserAccess::ACCESS_WRITE){
                $errors[] = 'Unit Folder permission error: '.$unitFolder->getId();
            }

            // Does the proposed Unit Folder already have a Course ?
            $unitFolderCourse = $unitFolder->getCourseSpecification();
            if($unitFolderCourse instanceof CourseSpecification && $unitFolderCourse->getId()){
                $errors[] = 'Proposed Unit folder already has a Course: ' . $unitFolderCourse->getId();
            }
        }

        if(count($errors)){
            $this->returnJson($errors, 400);
        }

        // No errors. Proceed.

        // Create Course if required
        if(!$courseExists){
            $course = $this->getCourseSpecificationMapper()->createModel();
            $course->setFolder($courseFolder);
        }

        // Create Unit if required
        if(!$unitExists){
            $unit = $this->getUnitSpecificationMapper()->createModel();
            $unit->setFolder($unitFolder);
        }

        // Link Course and Unit
        $unit->setCourse($course);
        $course->getUnits()->add($unit);

        // And save
        $response = [];
        if(!$courseExists){
            $this->getCourseSpecificationMapper()->save($course);
            $response['course'] = $course->getId();
        }

        // Create Unit if required
        if(!$unitExists){
            $this->getUnitSpecificationMapper()->save($unit);
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
        if($this->getRequest()->isXhr()){
            $this->getResponse()->content->set($this->buildJsonOutput($message));
        } else {
            die(json_encode($message));
        }
    }


    /**
     * Just a raw template render
     *
     * @param string $template
     * @param array $data
     */
    protected function tpl($template, $data = [])
    {
        parent::tpl('course', $template, $data);
    }


    /**
     * Retrieve the Course Builder component instance. If it doesn't exist, create it, then return that
     *
     * @param Folder $unit
     * @return Component_Instance
     */
    protected function getCourseBuilderInstance(Folder $unit)
    {
        // confirm that there's a Course Builder component
        $instances = $this->getComponentInstanceMapper()->findByContextAndClass('homepage', $unit->getId(), CourseBuilderController::class);

        if (count($instances) == 0) {
            // no instance found - give me a new one
            return $this->injectNewComponent($unit);
        }

        // instance found - use that
        return reset($instances);
    }


    /**
     * @param Folder $unit
     * @param string $controller
     * @return Component_Instance
     */
    protected function injectNewComponent(Folder $unit, $controller = CourseBuilderController::class)
    {
        $definitionMapper = $this->getComponentDefinitionMapper();
        $instanceMapper = $this->getComponentInstanceMapper();

        // Doesn't exist. We need that to exist. Make it exist.
        $definition = $definitionMapper->findOneByColumn('class', $controller);

        // invent a new component
        $newInstance = $this->getComponentInstanceMapper()
            ->createModel()
            ->setModule('homepage')
            ->setIdentifier($unit->getId())
            ->setDefinitionId($definition->getId())
            ->setSequence(0)
            ->setPosition('top');

        // save it
        $instanceMapper->save($newInstance);

        // reapply the sequencing so the homepage doesn't break
        $instanceMapper->cascadeSequence($unit->getId(), 'top');

        return $newInstance;
    }


    /**
     * Form to modify a course specification
     *
     * @api   GET /course/modify/{id}
     * @level 160
     *
     * @param int    $id   The ID of the course specification that is being modified
     * @param string $name
     */
    public function modifyCourse($id = null, $name = null)
    {
        $data = [];

        if ($id) {

            try {
                // First, check whether or not this is already a course. We don't want to barr access to courses that
                // are already courses.
                $course = $this->getCourseSpecificationMapperProxy()->findById($id);

                // Rehydrate course folder so that curriculum codes exist
                $course->setFolder($this->getFolderMapper()->findById($course->getFolder()->getId()));
                $folder = $course->getFolder();

            } catch (MapperException $e) {
                // It should no longer be possible for a folder to exist without a course spec.
                throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_COURSE_FOLDER_INVALID);
            }

            $folder->setParent($this->getFolderMapper()->findById($folder->getParent()->getId()));

            $instances = [];

            $data['access'][$folder->getId()] = $this->getAccess()->getFolderAccess($this->getUser(), [ $folder->getId() ]);
            $data['course'] = $course;

            foreach ($course->getUnits() as $unit) {
                /** @var UnitSpecification $unit */
                $unitFolder = $unit->getFolder();
                $data['access'][$unit->getId()] = $this->getAccess()->getFolderAccess($this->getUser(), [ $unitFolder->getId() ]);
                $data['tasks'][$unit->getId()] = $this->getAssessmentMapperProxy()->findAllByUnitSpecificationId($unit->getId());
                $data['returnUrl'][$unit->getId()] = $this->getReturnUrl($id, $unit->getId());
                $data['usages'][$unit->getId()] = $this->getUnitInstanceMapper()->findAllFoldersUsingCourse($folder)->getColumnValues('id');

//                // obtain the Course Builder Component Instance from the homepage.
//                $instances[$unit->getId()] = $this->getCourseBuilderInstance($unitFolder);
//
//                foreach ($data['tasks'][$unit->getId()] as $task) {
//                    /** @var Task $task */
//                    $data['taskReveals'][$task->getId()] = new TaskBuilder($instances[$unit->getId()], $task);
//                }
//
//                // set reference to this reveal instance
//                $data['reveals'][$unit->getId()] = new TaskBuilder($instances[$unit->getId()]);
            }
        }

        /* Optionally display Curriculum Tagging
         */
        $data['curriculumEnabled'] = $this->getConfigLoader()->get('curriculum_tagging_enabled');

        /* Search query
         * Optional name passed in url
         */
        $data['query'] = $name;

        $this->tpl('course', $data);
    }


    /**
     * Form to create a new course
     *
     * @api   GET /course/new
     * @level 160
     */
    public function newCourse()
    {
        $this->modifyCourse(null, null);
    }

    /**
     * Form to create a new course, but also include search param
     *
     * @api   GET /course/new/{name}
     * @level 160
     *
     * @param string $name
     */
    public function newCourseFromSearch($name)
    {
        $this->modifyCourse(null, $name);
    }

    /**
     * Accept the create formdata
     *
     * @api   POST /course/new
     * @level 160
     */
    public function createCourse()
    {
        $rq = $this->getRequest();

        $this->saveCourse($rq->post, $rq->isXhr(), 'course.modify');
    }


    /**
     * @api   POST /course/modify/{id}
     * @level 160
     *
     * @param int $id
     */
    public function updateCourse($id)
    {
        $rq = $this->getRequest();

        $post = $rq->post;

        // if something got passed, don't care: overriding it
        $post['id'] = $id;

        // Load course
        $course = $this->getCourseSpecificationMapperProxy()->findById($post['id']);

        // Do not permit Courses to be moved to, or created in, Root
        if (0 == $post['parent']) {

            // ...unless already in Root
            if(0 != $course->getFolder()->getParent()->getId()) {
                $this->sendError(
                    new Base\Exception("Course cannot reside directly in root",
                        ["move this Course out of root"]));
                return;
            }
        }

        if (is_array($post['unitname'])) {
            // save unit name changes
            foreach ($course->getUnits() as $unit) {
                $unitName = $post['unitname'][$unit->getId()];
                if (isset($unitName)) {
                    $this->getFolderMapper()->save($unit->getFolder()->setName($unitName));
                }
            }
        } else {
            $this->sendError(new Base\Exception("Cannot create a Course without Units", ["create at least 1 unit"]));
            return;
        }

        // Robustly handle either int, CSV string, or any level of nested arrays

        if (is_array($post['curriculumCodes'])) {
            $tmpArr = [];
            $itr = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($post['curriculumCodes']));
            foreach ($itr as $codes) {
                $tmpArr = array_merge($tmpArr, explode(',', $codes));
            }
            $post['curriculumCodes'] = $tmpArr;
        } else {
            if (isset($post['curriculumCodes'])) {
                $post['curriculumCodes'] = explode(',', $post['curriculumCodes']);
            }
        }

        $this->saveCourse($post, $rq->isXhr(), 'course.courseList');
    }


    /**
     * Common logic to create and modify a course
     *
     * @param Values $post
     * @param bool $xhr
     * @param string $redir
     */
    protected function saveCourse(Values $post, $xhr, $redir)
    {
        $rs = $this->getResponse();

        try {
            // attempt to persist this course based on the given POST data
            $courseSpec = $this->persistCourse($post);

            if ($xhr) {
                // respond to XHR with JSON
                $rs->content->set([
                    'format' => 'json',
                    'data'   => [ 'id' => $courseSpec->getId() ]
                ]);

            } else {
                // respond to synchronous POST with redirect
                $rs->redirect->afterPost($this->getRouteIfAuthorized($redir, [ $courseSpec->getId() ]));
            }

        } catch (Base\Exception $e) {
            // sometimes bad things happen, and there's nothing you can do about it
            $this->sendError($e);
        }
    }


    /**
     * Save the course based on the given POST data
     *
     * @param Values $post
     * @throws Base\Exception
     * @return CourseSpecification
     */
    protected function persistCourse($post)
    {
        $mode = BaseMapper::METHOD_CREATE;
        $parent = null;
        $errors = [];
        $name = trim($post->get('name'));

        if (!$name) {
            $errors[] = 'give the course a name';
            $name = 'this course';
        }

        $parentId = $post->get('parent');
        try {
            if ($parentId === "0" || $parentId > 0) {
                $access = $this->getAccess()->getFolderAccess($user = $this->getUser(), [$parentId]);
                if ($access < UserAccess::ACCESS_WRITE) {
                    $errors[] = "select a Faculty / Subject for which you have write access";
                } else {
                    $parent = $this->getFolderMapper()->findById($parentId);
                }
            } else {
                // cause a snap if no (or invalid) parent was supplied
                throw new BaseMapper\Exception("Invalid parent nominated");
            }


        } catch (BaseMapper\Exception $e) {

            $errors[] = "select a faculty or class for $name to live in";
        }

        // if there were any validation problems, throw that back
        if ($errors || !$parent instanceof Folder) {
            throw new Base\Exception("Could not save", $errors);
        }

        // basic validation passed: prepare to save!

        // was an ID sent through?
        $id = trim($post->get('id'));

        // extract codes
        $codes = $post->get('curriculumCodes', []);

        $folderId = null;
        if ($id) {
            try {
                // Retrieve the course specification
                $courseSpecification = $this->getCourseSpecificationMapperProxy()->findById($id);

                // do checks to make sure it exists
                $folder = $courseSpecification->getFolder();
                $folderId = $folder->getId();
                $mode = BaseMapper::METHOD_UPDATE;

                // Sanity check for existing courses:
                // * Don't allow moving outside of faculty root
                //   (Keeping a course in a folder outside fac root is okay)
                if ($folder->getParent()->getId() != $parentId
                    && !$this->getCourseSpecificationMapper()->isFolderValid($parent)
                ) {
                    throw new Base\Exception("Courses may not be moved outside the faculty folder");
                }

                // * Don't allow a folder to be its own parent
                $folder->setParent($parent);
                if (!$this->getFolderMapper()->checkParentage($folder)) {
                    throw new Base\Exception("Courses may not be moved inside themselves");
                }

            } catch (BaseMapper\Exception $e) {
                throw new Base\Exception("Could not save because " . $e->getMessage());
            }
        } else {
            // Sanity checks for new courses:
            // * Don't allow a course to be created in root folder
            // * Don't allow outside of faculty root
            if ($parentId == 0) {
                throw new Base\Exception("Courses may not be created in the root folder");
            }
            if (!$this->getCourseSpecificationMapper()->isFolderValid($parent)) {
                throw new Base\Exception("Courses may not be created outside the faculty folder");
            }

            // Create the course specification (no units yet)
            $courseSpecification = new CourseSpecification();
        }

        try {
            $datums = [
                // the basics
                'categoryID'      => $folderId,
                'name'            => $name,
                'parent'          => $parent->getId(),
                'curriculumCodes' => $codes,
                'inherit' => $this->getConfigLoader()->get('course_builder_default_course_page_inherit'),
            ];

            if ($mode == BaseMapper::METHOD_CREATE) {
                $rolePermissions = [];

                foreach ($this->getCourseConfigMapper()->findByColumn('page', 'course') as $courseConfig) {
                    /** @var CourseConfigModel $courseConfig */
                    $rolePermissions[$courseConfig->getRoleId()] = $courseConfig->getPermission();
                }

                $datums = array_merge($datums, [


                    // configurable things
                    'inherit'   => $this->getConfigLoader()->get('course_builder_default_course_page_inherit'),
                    'template'  => $this->getConfigLoader()->get('course_builder_default_course_page_template'),
                    'group'     => $rolePermissions,

                    // leave these always off
                    'recursive' => false, // LEAVE THIS
                    'code' => null,  // LEAVE THIS

                ]);
            } else {

                /** @var Folder $folder */
                $datums = array_merge($datums, [
                    // configurable things
                    'inherit' => $folder->getInherit()
                ]);
            }

            $folder = $this->persistFolder($datums);

            // Persist the course spec as well
            $courseSpecification->setFolder($folder);
            $this->getCourseSpecificationMapperProxy()->save($courseSpecification);
            return $courseSpecification;

        } catch (ServiceNotFound $e) {
            // quite simple not able to find things we need. bye bye.
            throw new Base\Exception("Could not save because of an internal error.", [], 500);

        } catch (\Exception $e) {
            // whatever the exception, recover from it as best you can
            throw new Base\Exception("Could not save because " . $e->getMessage());
        }

    }

    /**
     * Give the encoded return URL for the given course ID
     *
     * @param int $courseId
     * @param int $unitId
     * @return string
     */
    protected function getReturnUrl($courseId, $unitId = null)
    {
        $path = $this->getRouteIfAuthorized('course.modifyCourse', ['id' => $courseId]);

        if ($unitId) {
            $path .= "#$unitId";
        }

        return urlencode(base64_encode($path));
    }


    /**
     * Post request to spawn a new unit
     *
     * @api   POST /course/unit/new
     * @level 160
     */
    public function newUnit()
    {
        $post = $this->getRequest()->post;

        $name = trim($post->get('name'));

        if (!$name) {
            $this->sendError('Could not create unit', ['give the unit a name']);
            return;
        }

        try {
            // Need:
            // base config
            // course config
            $rolePermissions = [];
            foreach ($this->getCourseConfigMapper()->findByColumn('page', 'unit') as $courseConfig) {
                /** @var CourseConfigModel $courseConfig */
                $rolePermissions[$courseConfig->getRoleId()] = $courseConfig->getPermission();
            }

            // Get the course I'm saving into
            $courseSpec = $this->getCourseSpecificationMapperProxy()->findById($post['id']);

            $folder = $this->persistFolder([
                // the basics (note lack of ID and Codes)
                'name'            => $name,
                'parent'          => $courseSpec->getFolder()->getId(),

                // configurable things
                'inherit' => $this->getConfigLoader()->get('course_builder_default_unit_page_inherit'),
                'template' => $this->getConfigLoader()->get('course_builder_default_unit_page_template'),
                'group' => $rolePermissions,

                // leave these always off
                'recursive' => false, // LEAVE THIS
                'code' => null,  // LEAVE THIS
            ]);

            // Persist the unit too
            $unitSpec = new UnitSpecification();
            $unitSpec->setFolder($folder);
            $this->getUnitSpecificationMapperProxy()->save($unitSpec, $courseSpec);

            $instance  = $this->getCourseBuilderInstance($folder);
            $folderId  = $folder->getId();
            $returnUrl = $this->getReturnUrl($post['id'], $folderId);

            $data = [
                'unit'      => $unitSpec,
                'returnUrl' => [
                    $folderId => $returnUrl
                ],
                'reveals' => [$folderId => new TaskBuilder($instance, null, $returnUrl)],
                '_writable' => true,
                '_expanded' => true
            ];
            $this->tpl('unit', $data);

        } catch (ServiceNotFound $e) {
            // quite simple not able to find things we need. bye bye.
            $this->sendError("Could not save because of an internal error.", [], 500);

        } catch (\Exception $e) {
            // whatever the exception, recover from it as best you can
            $this->sendError("Could not save because " . $e->getMessage());
        }
    }


    /**
     * Wrapper around Folder\Controller::persistFolder() and does some extra perms checking
     *
     * @param array $data
     * @return Folder
     */
    protected function persistFolder(array $data)
    {
        /** @var FolderController $folderController */
        $folderController = $this->getController('folder');

        $folder = $folderController->persistFolder($data, $this->getUser());

        // Check that the initiating user has write access to the newly-created folder, and if not,
        // explicitly give it to them. This covers the case where the template allows you to create a folder
        // to which you don't have appropriate access
        if ($this->getAccess()->getFolderAccess($this->getUser(), [$folder->getId()]) < UserAccess::ACCESS_WRITE) {
            $ua = $this->getUserAccessMapper()->createModel();
            $ua->setUser($this->getUser())
                ->setAccessLevel(UserAccess::ACCESS_WRITE)
                ->setService(UserAccess::SERVICE_HOMEPAGE)
                ->setServiceIdentifier($folder->getId());

            $this->getUserAccessMapper()->save($ua);
        }

        return $folder;
    }

    /**
     * Dump out a list of courses
     * The Filter field allows you to search Course names
     *
     * @api   GET /course/list
     * @level 160
     */
    public function courseList()
    {
        $this->displayCourseList('courseList');
    }


    /**
     * Dump out AJAX'd search results
     * The Filter field allows you to search Course names
     *
     * @api   GET /course/list/ajax
     * @level 160
     */
    public function courseListAjax()
    {
        $this->displayCourseList('courseListResults');
    }


    /**
     * Sub-method for course list renderings
     *
     * @param string $template
     */
    protected function displayCourseList($template)
    {
        $query = $this->getRequest()->query;

        $componentId = $query->get('component');
        $folder = $query->get('folder');
        $search = $query->get('query');

        $data = $this->getCourseData($search);

        // only offer suggestions if you're not searching
        if ($folder && !$search) {
            $folderModel = $this->getFolderMapper()->findById($folder);
            $data['suggestions'] = $this->getLinkSuggestions($folderModel);
        }

        /* Remember what the user searched for
         */
        $data['query'] = $query->get('query');

        if ($this->getRequest()->isXhr()) {
            $options = [
                'hasBreadcrumb' => false,
                'hasAdd' => false,
                'hasTot' => false,
                'linkToUnits' => false,
                'hasClose' => true,
                'componentId' => $componentId,
            ];
        } else {
            $options = [
                'hasBreadcrumb' => true,
                'hasAdd' => true,
                'hasTot' => true,
                'linkToUnits' => true,
                'hasClose' => false,
            ];
        }

        if ($folder) {
            $options['folder']['id'] = $folder;
        }
        $this->tpl($template, array_merge($data, $options));
    }

    /**
     * @param int $folderId
     * @param int $min
     * @return bool
     */
    protected function confirmAccess($folderId, $min = UserAccess::ACCESS_READ)
    {
        return $this->getAccess()->getFolderAccess($this->getUser(), [$folderId]) >= $min;
    }

    /**
     * List the courses. Returns a combined array of Folder instances
     * and information about any found Units and their task counts
     *
     * @param string $searchParam
     * @return array
     */
    protected function getCourseData($searchParam)
    {
        // Use the new mapper to get the list of courses
        $allCourses = $this->getCourseSpecificationMapperProxy()->findAllByName($searchParam);

        $data = [];

        foreach ($allCourses as $currentCourse) {

            // general search ignores Courses in Faculty
            if (null === $searchParam) {
                try {
                    if (!($this->getFolderMapper()->isDescendant($currentCourse->getFolder(), $this->getCourseSpecificationMapper()->getFacultyRootFolder()))) {
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

            // Remove units whose folders aren't accessible by this user
            $currentCourse->getUnits()->filter(function($unit) {
                /** @var UnitSpecification $unit */
                return $this->confirmAccess($unit->getFolder()->getId());
            });

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
     * @return Collection
     */
    protected function getLineage(Folder $course)
    {
        return $this->getAncestry()->findParentsCheaply($course, $this->getCourseSpecificationMapper()->getFacultyRootFolder());
    }


    /**
     * Gets the suggested course links to A FULLY HYDRATED folder
     *
     * @param Folder $folder
     * @return Collection
     */
    protected function getLinkSuggestions(Folder $folder)
    {
        // obtain curriculum codes...
        $codes = $folder->getCurriculumNodes()->getColumnValues('code');

        // find all the folders that match this code, then filter for coarseness
        return $this->getFolderMapper()->findByCurriculumCode($codes)->filter(function ($result) {
            /** @var Folder $result */
            // TODO: test this with folders and courses which have curriculum codes
            try {
                $this->getCourseSpecificationMapperProxy()->findByFolderId($result->getId());
                return true;
            } catch (MapperException $e) {
                return false;
            }
        });
    }

    /**
     * Gets a permission string out of a permission code returned from
     * $this->handler->getPermission().
     *
     * @param  $code
     *
     * @return string
     */
    protected function getPermissionString($code)
    {
        switch ($code) {
            case UserAccess::ACCESS_FULL:
                return 'full';
                break;
            case UserAccess::ACCESS_WRITE:
                return 'write';
                break;
            case UserAccess::ACCESS_READ:
                return 'read';
                break;
            case UserAccess::ACCESS_DENY:
            default:
                return '';
        }
    }


    /**
     * Recursively convert TreeNode tree to nested array
     * @param TreeNode $node
     * @param array $courseIds Maps $courseId => $courseId
     * @param array $permissions Maps $folderId => permissionLevel
     * @return array
     */
    private function recursiveTreeBuild(TreeNode $node, array $courseIds, array $permissions)
    {
        $array = array();

        // Special Case for SB Root
        if (0 === $node->getId()) {
            $array['id'] = 0;
            $array['text'] = "Home";
        } else {

            /** @var Folder $folder */
            $folder = $node->getItem();

            $array['id'] = $folder->getId();
            $array['text'] = $folder->getName();
            $array['type'] = $folder->getFolderType();
            $array['li_attr'] = array(
                'data-permission' => $this->getPermissionString($permissions[$folder->getId()]),
                'data-type' => $folder->getFolderType(),
                'data-id' => $folder->getId()
            );
            $array['a_attr'] = array(
                'href' => '/homepage/' . $folder->getId()
            );
        }

        $array['children'] = array();

        foreach ($node->getChildren() as $childNode) {
            // Permission Business Rules
            // not set => prune subtree
            // 0 => prune subtree
            // 1 => show node, but don't permit selection (enforced on save attempt)
            // 2 => show node, permit selection
            // 3 => show node, permit selection

            // Folder Type Business Rules
            // isCourse => prune subtree

            $childId = $childNode->getId();
            $isSuper = $this->getUser()->isSuperuser();
            $isDenied = !isset($permissions[$childId]) || UserAccess::ACCESS_DENY === $permissions[$childId];
            $isCourse = array_key_exists($childId, $courseIds);

            if (($isSuper || !$isDenied) && !$isCourse) {
                $array['children'][] = $this->recursiveTreeBuild($childNode, $courseIds, $permissions);
            }
        }

        //sort children alphabetically
        usort($array['children'], function ($a, $b) {
            return strcasecmp($a['text'], $b['text']);
        });

        return $array;
    }

    /**
     * List the folders that are allowed to be course parents
     *
     * @param $folderId
     * @api   GET /course/parent
     * @level 160
     */
    public function parentList($folderId)
    {
        if (empty($folderId)) {
            $folderId = $this->getCourseSpecificationMapper()->getFacultyRootFolder()->getId();
        }

        $treeBuilder = new TreeBuilder(
            FolderMapper::getInstance(),
            $_SESSION['user'],
            'Courses'
        );

        $treeData = $treeBuilder->buildPartialTree($folderId, $this->getCourseSpecificationMapper()->getFacultyRootFolder()->getId());

        $data = [
            'treedata' => json_encode($treeData),
            'folderId' => $folderId
        ];

        $this->tpl('picker', $data);
    }


    /**
     * Save the given template for the given folder
     *
     * @param Folder $model
     * @param int $template
     */
    protected function saveHomepageConfig(Folder $model, $template)
    {
        $confMapper = $this->getHomepageConfigMapper();
        $tplMapper = $this->getHomepageTemplateMapper();

        $homepageConfig = $confMapper->createModel()->setCategory($model);

        // if homepage is to be removed
        try {
            if ($template == HomepageTemplate::HOMEPAGE_REMOVE) {
                $confMapper->emptyHomepage($homepageConfig);
                $confMapper->delete($homepageConfig);

            } elseif ($template == HomepageTemplate::HOMEPAGE_KEEP_EXISTING) {
                // we're done. get out.
                return;

            } elseif ($template > 0) {
                // if template defined
                $homepageConfig->setTemplate($tplMapper->findById($template));

                // Save homepage config
                $confMapper->save($homepageConfig);
                $confMapper->resetToTemplate($homepageConfig);

            } else {
                throw new RangeException("Invalid template ID specified");
            }
        } catch (BaseMapper\Exception $e) {
            throw new RangeException("Invalid template ID specified", 0, $e);
        }

        // Ram it back into its parent object where it belongs
        $model->setHomepage($homepageConfig);

    }

    /**
     * Delete unit INSTANCE. This will delete the instance and all its content.
     *
     * @api   POST /course/unit/delete
     * @level 160
     */
    public function deleteUnit()
    {
        $unitInstanceMapper = UnitInstanceMapper::getInstance();

        try {
            // Locate the unit instance we want to delete

            /** @var UnitInstance $unit */
            $unit = $unitInstanceMapper->findById($this->getRequest()->post["id"]);

            /** @var Access $access */
            $access = $this->getAccess();

            if ($access->getFolderAccess($this->getUser(), [$unit->getCategory()->getId()]) >= WRITE_ACCESS) {
                $unitInstanceMapper->delete($unit);
            } else {
                throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_PERM_CATEGORY_WRITE);
            }
        } catch (\Exception $e) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_RESOURCE_NOT_FOUND);
        }

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => ['result' => 'success']
        ]);
    }


    /**
     * Delete a course specification
     *
     * @api   POST /course/delete/{id}
     * @level 160
     *
     * @param int $id The course to delete
     */
    public function deleteCourseSpecification($id)
    {
        // TODO: make delete of folder and course atomic

        $courseSpec = $this->getCourseSpecificationMapperProxy()->findById($id);

        // Delete course specs (this also deletes constituent units)
        $this->getCourseSpecificationMapperProxy()->delete($courseSpec);

        /** @var FolderController $folderController */
        $folderController = $this->getController('folder');
        $folderController->recursiveDelete($courseSpec->getFolder()->getId(), $this->getUser());
    }

    /**
     * Delete a unit specification
     *
     * @api   POST /course/unit/delete/{id}
     * @level 160
     *
     * @param int $id The unit spec to delete
     */
    public function deleteUnitSpecification($id)
    {
        // TODO: make delete of folder and unit atomic

        $unitSpec = $this->getUnitSpecificationMapperProxy()->findById($id);

        // Delete unit spec
        $this->getUnitSpecificationMapperProxy()->delete($unitSpec);

        /** @var FolderController $folderController */
        $folderController = $this->getController('folder');
        $folderController->recursiveDelete($unitSpec->getFolder()->getId(), $this->getUser());
    }

    /**
     * Returns a HTTP 500 error with a specific text message
     *
     * @param $message
     */
    protected function throwError($message)
    {

        $this->getResponse()->content->set([
            'format' => 'file',
            'data' => $message
        ]);
    }


    /* Getters/Setters
     */

    /**
     * Get folder mapper
     *
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * Set folder mapper
     *
     * @param FolderMapper $folderMapper
     */
    public function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }

    /**
     * Get task mapper
     *
     * @return TaskMapper
     */
    protected function getTaskMapper()
    {
        return $this->taskMapper;
    }

    /**
     * Set task mapper
     *
     * @param TaskMapper $taskMapper
     */
    public function setTaskMapper($taskMapper)
    {
        $this->taskMapper = $taskMapper;
    }


    /**
     * Get component instance mapper
     *
     * @return Component_InstanceMapper
     */
    protected function getComponentInstanceMapper()
    {
        return $this->componentInstanceMapper;
    }

    /**
     * Set component instance mapper
     *
     * @param Component_InstanceMapper $componentInstanceMapper
     */
    public function setComponentInstanceMapper($componentInstanceMapper)
    {
        $this->componentInstanceMapper = $componentInstanceMapper;
    }

    /**
     * Get component definition mapper
     *
     * @return Component_DefinitionMapper
     */
    protected function getComponentDefinitionMapper()
    {
        return $this->componentDefinitionMapper;
    }

    /**
     * Set component definition mapper
     *
     * @param Component_DefinitionMapper $componentDefinitionMapper
     */
    public function setComponentDefinitionMapper($componentDefinitionMapper)
    {
        $this->componentDefinitionMapper = $componentDefinitionMapper;
    }

    /**
     * Get unit instance mapper
     *
     * @return UnitInstanceMapper
     */
    protected function getUnitInstanceMapper()
    {
        return $this->unitInstanceMapper;
    }

    /**
     * Set unit instance mapper
     *
     * @param UnitInstanceMapper $unitInstanceMapper
     */
    public function setUnitInstanceMapper($unitInstanceMapper)
    {
        $this->unitInstanceMapper = $unitInstanceMapper;
    }

    /**
     * Set task mapper
     *
     * @param SubmissionBoxMapper $submissionBoxMapper
     */
    public function setSubmissionBoxMapper($submissionBoxMapper)
    {
        $this->submissionBoxMapper = $submissionBoxMapper;
    }

    /**
     * Get submission box mapper
     *
     * @return SubmissionBoxMapper
     */
    protected function getSubmissionBoxMapper()
    {
        return $this->submissionBoxMapper;
    }

    /**
     * @return HomepageConfigMapper
     */
    protected function getHomepageConfigMapper()
    {
        return $this->homepageConfigMapper;
    }


    /**
     * @param HomepageConfigMapper $homepageConfigMapper
     * @return static
     */
    public function setHomepageConfigMapper($homepageConfigMapper)
    {
        $this->homepageConfigMapper = $homepageConfigMapper;
        return $this;
    }


    /**
     * @return HomepageTemplateMapper
     */
    protected function getHomepageTemplateMapper()
    {
        return $this->homepageTemplateMapper;
    }


    /**
     * @param HomepageTemplateMapper $homepageTemplateMapper
     * @return static
     */
    public function setHomepageTemplateMapper($homepageTemplateMapper)
    {
        $this->homepageTemplateMapper = $homepageTemplateMapper;
        return $this;
    }

    /**
     * @return CourseConfigMapper
     */
    protected function getCourseConfigMapper()
    {
        return $this->courseConfigMapper;
    }

    /**
     * @param  CourseConfigMapper $courseConfigMapper
     * @return static
     */
    public function setCourseConfigMapper($courseConfigMapper)
    {
        $this->courseConfigMapper = $courseConfigMapper;
        return $this;
    }


    /**
     * @return Error\Mapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }


    /**
     * @param Error\Mapper $errorMapper
     * @return static
     */
    public function setErrorMapper($errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }


    /**
     * @return UserAccessMapper
     */
    protected function getUserAccessMapper()
    {
        return $this->userAccessMapper;
    }


    /**
     * @param UserAccessMapper $userAccessMapper
     * @return static
     */
    public function setUserAccessMapper($userAccessMapper)
    {
        $this->userAccessMapper = $userAccessMapper;
        return $this;
    }


    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }


    /**
     * @param UserMapper $userMapper
     * @return static
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }


    /**
     * @return Access
     */
    protected function getAccess()
    {
        return $this->access;
    }


    /**
     * @param Access $access
     * @return static
     */
    public function setAccess($access)
    {
        $this->access = $access;
        return $this;
    }

    /**
     * @return CourseSpecificationMapper
     */
    public function getCourseSpecificationMapper()
    {
        return $this->courseSpecificationMapper;
    }

    /**
     * @param CourseSpecificationMapper $courseSpecificationMapper
     * @return static
     */
    public function setCourseSpecificationMapper($courseSpecificationMapper)
    {
        $this->courseSpecificationMapper = $courseSpecificationMapper;
        return $this;
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
    public function setUnitSpecificationMapper($unitSpecificationMapper)
    {
        $this->unitSpecificationMapper = $unitSpecificationMapper;
        return $this;
    }

    /**
     * @return CourseSpecificationTranslator
     */
    public function getCourseSpecificationTranslator()
    {
        return $this->courseSpecificationTranslator;
    }

    /**
     * @param CourseSpecificationTranslator $courseSpecificationTranslator
     * @return static
     */
    public function setCourseSpecificationTranslator($courseSpecificationTranslator)
    {
        $this->courseSpecificationTranslator = $courseSpecificationTranslator;
        return $this;
    }

    /**
     * @return UnitSpecificationTranslator
     */
    public function getUnitSpecificationTranslator()
    {
        return $this->unitSpecificationTranslator;
    }

    /**
     * @param UnitSpecificationTranslator $unitSpecificationTranslator
     * @return static
     */
    public function setUnitSpecificationTranslator($unitSpecificationTranslator)
    {
        $this->unitSpecificationTranslator = $unitSpecificationTranslator;
        return $this;
    }

    /**
     * @return CourseSpecificationMapperProxy
     */
    public function getCourseSpecificationMapperProxy()
    {
        return $this->courseSpecificationMapperProxy;
    }

    /**
     * @param CourseSpecificationMapperProxy $courseSpecificationMapperProxy
     * @return static
     */
    public function setCourseSpecificationMapperProxy($courseSpecificationMapperProxy)
    {
        $this->courseSpecificationMapperProxy = $courseSpecificationMapperProxy;
        return $this;
    }

    /**
     * @return UnitSpecificationMapperProxy
     */
    public function getUnitSpecificationMapperProxy()
    {
        return $this->unitSpecificationMapperProxy;
    }

    /**
     * @param UnitSpecificationMapperProxy $unitSpecificationMapperProxy
     * @return static
     */
    public function setUnitSpecificationMapperProxy($unitSpecificationMapperProxy)
    {
        $this->unitSpecificationMapperProxy = $unitSpecificationMapperProxy;
        return $this;
    }

    /**
     * @return CourseImplementationMapper
     */
    public function getCourseImplementationMapper()
    {
        return $this->courseImplementationMapper;
    }

    /**
     * @param CourseImplementationMapper $courseImplementationMapper
     */
    public function setCourseImplementationMapper(CourseImplementationMapper $courseImplementationMapper)
    {
        $this->courseImplementationMapper = $courseImplementationMapper;
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
    public function setAssessmentMapperProxy($assessmentMapperProxy)
    {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        return $this;
    }

    /**
     * @return Ancestry
     */
    public function getAncestry()
    {
        return $this->getFolderMapper()->getAncestry();
    }

    /** @var AbstractTreeBuilder */
    protected $treeBuilder;

    /** @var \Schoolbox\Mapper|Resource\File\ILocator */
    protected $fileMapper;

    /** @var Config\Loader */
    protected $conf;



    /** @var string */
    protected $namespace;


    /** @var string */
    protected $nodeType;

    /**
     * Which tab gets highlighted?
     *
     * @var string
     */
    protected $tab = 'resources';


    /**
     * @param AbstractTreeBuilder $treeBuilder
     * @return self
     */
    public function setTreeBuilder($treeBuilder)
    {
        $this->treeBuilder = $treeBuilder;
        return $this;
    }


    /**
     * @param Config\Loader $conf
     * @return self
     */
    public function setConfig($conf)
    {
        $this->conf = $conf;
        return $this;
    }


    /**
     * @param \Schoolbox\Mapper|Resource\File\ILocator $fileMapper
     * @return self
     */
    public function setFileMapper($fileMapper)
    {
        $this->fileMapper = $fileMapper;
        return $this;
    }


    /**
     * @param string $namespace
     * @return self
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }


    /**
     * @param string $nodeType
     * @return self
     */
    public function setNodeType($nodeType)
    {
        $this->nodeType = $nodeType;
        return $this;
    }

    /**
     * @param int $fileId
     * @return FileInterface
     * @throws MapperException
     */
    protected function getFile($fileId)
    {
        /** @var FileInterface $file */
        $file = $this->fileMapper->findById($fileId);

        // urgh...
        if (method_exists($file, 'setOwner')) {
            try {
                $file->setOwner($this->userMapper->findById($file->getOwner()->getId()));
            } catch (MapperException $e) {
                $file->setOwner($this->userMapper->createModel());
            }
        }

        return $file;
    }

    /**
     * @param int $folderId
     * @return FolderInterface
     */
    protected function findFolder($folderId)
    {
        try {
            return $this->getFolder($folderId);

        } catch (MapperExceptionDeleted $e) {
            // Deleted: load the parent (recursively)
            /** @var Folder $folder */
            $folder = $e->getDeletedObject();
            return $this->findFolder($folder->getId());

        } catch (MapperException $e) {
            return $this->folderMapper->createModel()->setId(0);

        }
    }


    /**
     * @param int $folderId
     * @return FolderInterface
     * @throws MapperException
     */
    protected function getFolder($folderId)
    {
        return $this->folderMapper->findById($folderId);
    }

    /**
     * @param  string $tab
     * @return $this
     */
    public function setTab($tab)
    {
        $this->tab = $tab;
        return $this;
    }

}
