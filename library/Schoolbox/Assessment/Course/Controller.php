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
use Component_Homepage_CourseBuilderController;
use Component_Homepage_CourseOutlineController;
use Component_Instance;
use Component_InstanceMapper;
use RangeException;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Assessment\Course\Config\Model as CourseConfigModel;
use Schoolbox\Assessment\Course\Import\Import as CourseImport;
use Schoolbox\Assessment\Course\DeprecatedModel as CourseModel;
use Schoolbox\Assessment\Course\Unit\DeprecatedMapper as CourseUnitMapper;
use Schoolbox\Assessment\Course\Task\Instance as TaskInstance;
use Schoolbox\Assessment\Course\Task\InstanceMapper as TaskInstanceMapper;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper as CourseSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Translator as CourseSpecificationTranslator;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper as UnitSpecificationMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Translator as UnitSpecificationTranslator;
use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\SpecificationMapperProxy as CourseSpecificationMapperProxy;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecificationMapperProxy;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted as MapperExceptionDeleted;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Browser\AbstractTreeBuilder;
use Schoolbox\Resource\Browser\Resources\TreeBuilder;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Ancestry;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as FolderModel;
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

class Controller extends Base\Base
{

    /** @var FolderMapper */
    protected $folderMapper;

    /** @var SubmissionBoxMapper */
    protected $submissionBoxMapper;

    /** @var OnlineAssessmentMapper */
    protected $onlineAssessmentMapper;

    /** @var TaskMapper */
    protected $taskMapper;

    /** @var TaskInstanceMapper */
    protected $taskInstanceMapper = null;

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
     * @param CourseSpecificationMapperProxy $courseSpecificationDataSource
     * @param UnitSpecificationMapperProxy   $unitSpecificationDataSource
     * @param AssessmentMapperProxy          $assessmentDataSource
     */
    public function __construct(
        CourseSpecificationMapperProxy $courseSpecificationDataSource,
        UnitSpecificationMapperProxy $unitSpecificationDataSource,
        AssessmentMapperProxy $assessmentDataSource
    ) {
        // Deprecated course/unit specification mappers - all gone!

        // Proxy for course/unit specification mappers
        $this->setUnitSpecificationMapperProxy($unitSpecificationDataSource);
        $this->setCourseSpecificationMapperProxy($courseSpecificationDataSource);

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
        $this->setOnlineAssessmentMapper(OnlineAssessmentMapper::getInstance());
        $this->setComponentDefinitionMapper(Component_DefinitionMapper::getInstance());
        $this->setComponentInstanceMapper(Component_InstanceMapper::getInstance());
        $this->setUnitInstanceMapper(UnitInstanceMapper::getInstance());
        $this->setTaskMapper(TaskMapper::getInstance());
        $this->setTaskInstanceMapper(TaskInstanceMapper::getInstance());
        $this->setHomepageConfigMapper(HomepageConfigMapper::getInstance());
        $this->setHomepageTemplateMapper(HomepageTemplateMapper::getInstance());
        $this->setAccess(Access::getInstance());
        $this->setCourseConfigMapper(CourseConfigMapper::getInstance());
        $this->setUserAccessMapper(UserAccessMapper::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setErrorMapper(Error\Mapper::getInstance());
    }


    /**
     * Just a raw template render
     *
     * @param string $template
     * @param array $data
     */
    protected function tpl($template, $data = [])
    {
        $content = [
            'template' => "@course/$template.twig",
            'data' => array_merge($data, ['currentUser' => $this->getUser()]),
            'tab' => 'course',
        ];

        if ($this->getRequest()->isXhr()) {
            $content['format'] = 'nochrome';
        }

        $this->getResponse()->content->set($content);
    }


    /**
     * Retrieve the Course Builder component instance. If it doesn't exist, create it, then return that
     *
     * @param Folder\Model $unit
     * @return Component_Instance
     */
    protected function getCourseBuilderInstance(Folder\Model $unit)
    {
        // confirm that there's a Course Builder component
        $instances = $this->getComponentInstanceMapper()->findByContextAndClass('homepage', $unit->getId(), \Component_Homepage_CourseBuilderController::class);

        if (count($instances) == 0) {
            // no instance found - give me a new one
            return $this->injectNewComponent($unit);
        }

        // instance found - use that
        return reset($instances);
    }


    /**
     * @param Folder\Model $unit
     * @param string $controller
     * @return Component_Instance
     */
    protected function injectNewComponent(Folder\Model $unit, $controller = Component_Homepage_CourseBuilderController::class)
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
                $data['tasks'][$unit->getId()] = $this->getTaskMapper()->findByCategory($unitFolder);
                $data['returnUrl'][$unit->getId()] = $this->getReturnUrl($id, $unit->getId());
                $data['usages'][$unit->getId()] = $this->getUnitInstanceMapper()->findAllFoldersUsingCourse($folder)->getColumnValues('id');

                // obtain the Course Builder Component Instance from the homepage.
                $instances[$unit->getId()] = $this->getCourseBuilderInstance($unitFolder);

                foreach ($data['tasks'][$unit->getId()] as $task) {
                    /** @var Task $task */
                    $data['taskReveals'][$task->getId()] = new TaskBuilder($instances[$unit->getId()], $task);
                }

                // set reference to this reveal instance
                $data['reveals'][$unit->getId()] = new TaskBuilder($instances[$unit->getId()]);
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
        if ($errors || !$parent instanceof FolderModel) {
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

                /** @var Folder\Model $folder */
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
     * Send a neat and tidy error back to the client
     *
     * @param $problem
     * @param array $resolutions
     * @param int $statusCode
     * @throws SchoolboxException
     */
    protected function sendError($problem, array $resolutions = [], $statusCode = 400)
    {
        // cast exception to primitives
        if ($problem instanceof Base\Exception) {
            $resolutions = $problem->getReasons();
            $problem = $problem->getMessage();
        }

        if (count($resolutions)) {
            $problem .= ". You need to";
        }

        $this->getResponse()->status->set($statusCode);

        if (!$this->getRequest()->isXhr()) {
            throw $this->getErrorMapper()->generateException(105, null, $problem . implode(', ', $resolutions));
        }

        $this->getResponse()->content->set([
            'template' => '@course/mage-error.twig',
            'format' => 'nochrome',
            'data' => [
                'error' => [
                    'problem' => $problem,
                    'reasons' => $resolutions
                ]
            ]
        ]);

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
     * @return Folder\Model
     */
    protected function persistFolder(array $data)
    {
        /** @var Folder\Controller $folderController */
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

        $component = $query->get('component');
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
            $options['component']['id'] = $component ?: "0.0"; // TODO replace: this is a hack to make the old import page work without an explicit component instance ID
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
     * @param Folder\Model $course
     * @throws MapperException if one of the ancestors is deleted or missing somehow
     * @return Collection
     */
    protected function getLineage(Folder\Model $course)
    {
        return $this->getAncestry()->findParentsCheaply($course, $this->getCourseSpecificationMapper()->getFacultyRootFolder());
    }


    /**
     * Gets the suggested course links to A FULLY HYDRATED folder
     *
     * @param Folder\Model $folder
     * @return Collection
     */
    protected function getLinkSuggestions(Folder\Model $folder)
    {
        // obtain curriculum codes...
        $codes = $folder->getCurriculumNodes()->getColumnValues('code');

        // find all the folders that match this code, then filter for coarseness
        return $this->getFolderMapper()->findByCurriculumCode($codes)->filter(function ($result) {
            /** @var Folder\Model $result */
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
            $array['text'] = "Root";
        } else {

            /** @var Folder\Model $folder */
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
            Folder\Mapper::getInstance(),
            CourseUnitMapper::getInstance(),
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
     * This GET is the start of the import process. Import a Course into a set of Folders (classes)
     *
     * @api   GET /course/import/{courseId}/class/{classCode}{/componentId}
     * @level 160
     *
     * @param int $courseId
     * @param string $classCode
     * @param int $componentId (Optional) the ID of the component the course is to be imported into
     */
    public function import($courseId, $classCode, $componentId = null)
    {
        $importer = new CourseImport();

        $this->tpl('import', array_merge($importer->importSelectize($courseId, $classCode), ['componentId' => $componentId]));
    }


    /**
     * This POST is the result of import stage 2
     *
     * TODO: replace component ID parameter with course implementation ID?
     * TODO: figure out how this should work with course specification
     *
     * @api   POST /course/import/ownership
     * @level 160
     */
    public function importOwnership()
    {
        $ownerships = $this->getRequest()->post["ownership"];
        $courseHomepage = trim($this->getRequest()->post["importCourse"]);
        $componentId = trim($this->getRequest()->post["componentId"]);

        // process ownership assignments
        // respond with stage 3: unit selection
        $this->tpl('unitImportForm', [
            'homepage' => implode(',', array_keys($ownerships)),
            'courseHomepage' => $courseHomepage,
            'componentId'    => $componentId,
            'units'          => $this->getUnitSpecificationMapperProxy()->findAllByCourseFolderId($courseHomepage),
            'ownerships'     => implode(',', array_map(
                    function ($k, $v) { return "$k:$v"; }, array_keys($ownerships), array_values($ownerships))
            )
        ]);
    }


    /**
     * Search function. Retrieve a list of Folders that match the string in $_GET['query']
     * TODO: rename this, and everything in it; it confusingly uses "import" in its noun form, rather than verb
     *
     * @api   GET /course/import/classes
     * @level 160
     */
    public function importClasses()
    {
        $importer = new CourseImport();

        // TODO: despite its name, "importClasses" actually just performs a search based on the query string given
        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => $importer->importClasses($this->getRequest()->query->get('query'))
        ]);
    }

    /**
     * Get the Units and Tasks for a particular Course
     *
     * @api   GET /course/importResults/{courseId}
     * @level 160
     *
     * @param string $courseId
     */
    public function importResults($courseId)
    {
        $importer = new CourseImport();

        $this->tpl('import/results', $importer->findUnitsAndTasks($courseId));
    }

    /**
     * This POST is the result of import stage 1. Retrieve fragment with units in the selected course, and checkboxes/dates for selection
     *
     * @api   POST /course/importUnits
     * @level 160
     */
    public function importUnits()
    {
        $homepage = trim($this->getRequest()->post["importFolders"]);
        $courseHomepage = trim($this->getRequest()->post["importCourse"]);
        $componentId = trim($this->getRequest()->post["componentId"]);

        try {
            $this->getCourseSpecificationMapperProxy()->findByFolderId($courseHomepage);

        } catch (MapperException $e) {
            // the nominated "course" is not actually a course...
            $this->throwError('The selected source is not a course');
            return;
        }

        if ($homepage == "" && $courseHomepage == "") {
            // They didn't select anything

            $this->throwError('You must select at least one class and a course to import before you can proceed');
        } else {
            if ($homepage == "") {
                // They didn't select a class code

                $this->throwError('You must select at least one class before you can proceed');
            } else {
                if ($courseHomepage == "") {
                    // They didn't select a course to import

                    $this->throwError('You must select a course to import before you can proceed');
                } else {
                    $targetFolderIds = explode(',', $homepage);

                    $folders = $this->getFolderMapper()->findByColumn('id', $targetFolderIds);

                    $possibleOwnerships = [];

                    foreach ($folders as $folder) {
                        $possibleOwnerships[$folder->getId()] = $this->getUserAccessMapper()->findUsersInCategory(
                            $folder, Role::STAFF, UserAccess::ACCESS_WRITE
                        );

                        if (count($possibleOwnerships[$folder->getId()]) == 0) {
                            // if nobody else was returned, jam the session user in there.
                            $possibleOwnerships[$folder->getId()] = new Collection([$this->getUser()]);
                        }
                    }

                    // Everything kosher, proceed with the mage
                    // respond with stage 2: ownership
                    $this->tpl('import/ownership', [
                        'targetFolders' => $folders,
                        'importCourse' => $courseHomepage,
                        'ownershipCandidates' => $possibleOwnerships,
                        'componentId' => $componentId
                    ]);
                }
            }
        }
    }

    /**
     * Decide publish, open, due, late, start, finish dates for tasks
     *
     * Figger out when each task should open, due, late, etc
     * A superposition of Task Instances over Task Abstracts
     *
     * (a Task Abstract is one specified in the original Unit
     * but has not yet been imported)
     *
     * TODO: make this take a course implementation ID, not a course component ID
     *
     * @param $unitDates array User-selected dates for a Unit, (via POST, typically)
     * @param $courseComponentId int A componentId related to this course >.<
     * @return array
     */
    private function determineTaskDates($unitDates, $courseComponentId)
    {
        /** @var Folder\Model[] $unitCache */
        $unitCache = [];

        /* Make a local cache for unit information
         */
        // TODO: figure out how this should work with course implementation
        foreach ($unitDates as $unit) {
            if ($unit['import'] && !isset($unitCache[$unit['unit_id']])) {
                try {
                    $u = $this->getUnitSpecificationMapperProxy()->findByFolderId($unit['unit_id']);
                } catch (MapperException\Deleted $e) {
                    // ignore deletions here; the assumption is that any unit passed into here is legit. If you modify a class import and
                    // any source units have been deleted, their existence still needs to be honoured.
                    $u = $e->getDeletedObject();
                }
                $unitCache[$unit['unit_id']] = $u;
            }
        }

        // Find all tasks, due work, and quizzes associated with the selected units.

        $units = new Collection();

        foreach ($unitCache as $key => $unitCurrent) {
            $unit = [];
            $unit['id'] = $unitCurrent->getId();
            $unit['name'] = $unitCache[$unitCurrent->getId()]->getName();
            $unit['items'] = new Collection();

            foreach ($unitDates as $unitDate) {
                if ($unitDate["unit_id"] == $unitCurrent->getId()) {
                    $unit['open_date'] = $unitDate['open_date'];
                    $unit['due_date'] = $unitDate['due_date'];
                }
            }

            /* Get a list of all the abstract coursework that were part of
             * the original unit specification
             */
            $abstractCoursework = $this->getTaskMapper()->findAbstractTasksForUnit($key);

            /* Get a list of all the coursework instances that were created
             * as instances of the original abstracts
             */
            if ($courseComponentId) {
                $unitInstance = $this->getUnitInstanceMapper()->findByColumnList(
                    [
                        'component_id' => $courseComponentId,
                        'unit_category_id' => $key
                    ]
                )->first();

                if ($unitInstance instanceof UnitInstance) {
                    $instancesCoursework = $this->getTaskInstanceMapper()->findByColumn(
                        'course_unit_instance_id',
                        $unitInstance->getId()
                    );
                } else {

                    /* No instances to list
                     */
                    $instancesCoursework = new Collection();
                }
            } else {

                /* No instances to list
                 */
                $instancesCoursework = new Collection();
            }

            /* Unify all three of Tasks, Due Work and Quizzes
             * into one mega list
             */
            $temp = new Collection();

            /* If the coursework instances exist, use them; otherwise use
             * an instance of the abstract coursework
             */
            $found = [];

            foreach ($abstractCoursework as $currentAbstractCoursework) {
                foreach ($instancesCoursework as $currentInstanceCoursework) {

                    /* An instance already exists; use that and stop searching
                     */
                    if ($currentInstanceCoursework->getTask()->getId() == $currentAbstractCoursework->getId()) {

                        /* This abstract already has an instance; ignore it
                         * after inserting the instance
                         */
                        $found[] = $currentAbstractCoursework->getId();

                        /* Retain the dates for this coursework as specified by
                         * the original import; ram in the recently-found Unit Instance
                         */
                        $unitInstance = $this->getUnitInstanceMapper()->findById(
                            $currentInstanceCoursework->getUnitInstance()->getId()
                        );
                        $currentInstanceCoursework->setUnitInstance($unitInstance);

                        /* Stack it up
                         */
                        $temp->add($currentInstanceCoursework);

                        break;
                    }
                }

                /* If an instance of the abstract coursework wasn't found,
                 * use the abstract instead; otherwise ignore and continue
                 */
                if (!in_array($currentAbstractCoursework->getId(), $found)) {
                    $temp->add($currentAbstractCoursework);
                }
            }

            /* Find any customisations to the imported course
             *
             * Take advantage of the fact that:
             * $currentInstanceCoursework->getTask()->getId() = $currentAbstractCoursework->getId() = elements of $found
             *
             * So we can find all the $currentInstanceCoursework that weren't superpositioned
             * because there are no abstracts for them (ie. customisations)
             */
            foreach ($instancesCoursework as $currentInstanceCoursework) {
                if (!in_array($currentInstanceCoursework->getTask()->getId(), $found)) {
                    $temp->add($currentInstanceCoursework);
                }
            }

            /* Sort by Created At for now, until manual sequencing
             * is added to the ui so teachers can sort it themselves
             */
            $temp->sortBy('created', 'asc');

            foreach ($temp as $current) {
                $class = 'Task';
                $detail = null;
                try {
                    if ($current->getAssessment()->getId()) {
                        $class = 'OnlineAssessment';
                        $itemId = $current->getAssessment()->getId();
                        $detail = $this->getOnlineAssessmentMapper()->findById($itemId);
                    } elseif ($current->getSubmissionBox()->getId()) {
                        $class = 'SubmissionBox';
                        $itemId = $current->getSubmissionBox()->getId();
                        $detail = $this->getSubmissionBoxMapper()->findById($itemId);
                    }
                } catch (MapperException $e) {
                    // the underlying assessment entity does not exist. do not import.
                    continue;
                }

                /* Determine whether this was an abstract task, or a task instance
                 * that actually exists (the former will have to inherit unit dates
                 * and the latter will have to retain unit dates
                 */
                if ($current instanceof Task) {
                    $isAbstract = true;
                } else {
                    $isAbstract = false;
                }

                $unit['items']->add(
                    [
                        'class' => $class,
                        'isAbstract' => $isAbstract,
                        'item' => $current,
                        'detail' => $detail
                    ]
                );
            }

            $units->add($unit);
        }

        return [
            'units' => $units
        ];
    }

    /**
     * This POST is the result of import stage 3. Retrieve friend with a list of tasks, quizzes
     * and due work (and relevant date entry boxes) associated with the selected course.
     *
     * @api   POST /course/importTasks
     * @level 160
     *
     * @throws Unauthorized
     **/
    public function importTasks()
    {
        $post = $this->getRequest()->post;

        if (trim($post["homepage"]) == "") {
            // The homepage ID has gone missing

            $this->throwError('No class code was specified');
        } else {
            if (trim($post["course_homepage"]) == "") {
                // The course homepage ID has gone missing

                $this->throwError('No course was specified');
            } else {
                $homepages = explode(",", $post["homepage"]);

                // Test permissions. Must have write permission on the destination page and read permission
                // on the course we are trying to import.

                // Test course page read access

                if ($this->getAccess()->getFolderAccess($this->getUser(), [$post["course_homepage"]]) < READ_ACCESS) {
                    throw new Unauthorized();
                }

                // Test each homepage for write access

                foreach ($homepages as $homepage) {
                    if ($this->getAccess()->getFolderAccess($this->getUser(), [$homepage]) < WRITE_ACCESS) {
                        throw new Unauthorized();
                    }
                }

                // Check all required dates have been specified

                $errorDate = false;
                $foundUnit = false;

                foreach ($post["unit"] as $unit) {
                    if (!empty($unit['import'])) {
                        $foundUnit = true;

                        /* Add date validation
                         */
                        try {
                            $openDate = \Alaress_Date_Format::newLocalShort($unit['open_date']);
                            $dueDate = \Alaress_Date_Format::newLocalShort($unit['due_date']);
                            if ($openDate->gt($dueDate)) {
                                $errorDate = true;
                                $this->throwError('The open date must be before the close date');
                                break;
                            }
                        } catch (\Exception $e) {
                            $errorDate = true;
                            $this->throwError('You must specify a valid open and close date for each unit you are importing');
                            break;
                        }
                    }
                }

                // Make sure at least one unit was specified

                if (!$foundUnit) {
                    $this->throwError('You must select at least one unit to import');
                } elseif (!$errorDate) {
                    /* Figger out when each task should open, due, late, etc
                     * A superposition of Task Instances over Task Abstracts
                     *
                     * (a Task Abstract is one specified in the original Unit
                     * but has not yet been imported)
                     */
                    try {
                        $allTaskDates = $this->determineTaskDates(
                            $post["unit"],
                            $post->get('componentId')
                        );
                    } catch (MapperException $e) {
                        $this->sendError($e->getMessage());
                        return;
                    }

                    // respond with stage 4: coursework list
                    $this->tpl('unitImportResults', [
                        'ownerships' => $post['ownerships'],
                        'homepage' => $post["homepage"],
                        'componentId' => $post["componentId"],
                        'courseHomepage' => $post["course_homepage"],
                        'unitDates' => $post["unit"],
                        'units' => $allTaskDates['units'],
                        'submitValue' => empty($post['ownerships']) ? 'Modify' : 'Import'
                    ]);
                }
            }
        }
    }

    /**
     * This POST is the result of import stage 4 (final). Do the actual import
     * and create all relevant database entries.
     *
     * @api   POST /course/importSave
     * @level 160
     */
    public function importSave()
    {
        $post = $this->getRequest()->post;

        // Validate dates before we save (nothing to do for tasks)

        // Validate due work

        if (isset($post["work"])) {
            foreach ($post["work"] as $work) {
                try {
                    \Alaress_Date_Format::newLocalShort($work['publish_date']);
                } catch (\Exception $e) {
                    $this->throwError('You must enter a publish date for each due work item before you can proceed.');
                    return;
                }

                try {
                    \Alaress_Date_Format::newLocalShort($work['due_date']);
                } catch (\Exception $e) {
                    $this->throwError('You must enter a due date for each due work item before you can proceed.');
                    return;
                }

                // Accepting submissions, must have the other two dates

                if ($work['submissions'] == '1') {
                    try {
                        \Alaress_Date_Format::newLocalShort($work['open_date']);
                    } catch (\Exception $e) {
                        $this->throwError('You must enter an open date for each due work item that accepts submissions before you can proceed.');
                        return;
                    }

                    try {
                        \Alaress_Date_Format::newLocalShort($work['late_date']);
                    } catch (\Exception $e) {
                        $this->throwError('You must enter a late date for each due work item that accepts submissions before you can proceed.');
                        return;
                    }
                }
            }
        }

        // Validate quizzes

        if (isset($post["quiz"])) {
            foreach ($post["quiz"] as $quiz) {
                try {
                    \Alaress_Date_Format::newLocalShort($quiz['start_date']);
                } catch (\Exception $e) {
                    $this->throwError('You must enter a start date for each quiz before you can proceed.');
                    return;
                }

                try {
                    \Alaress_Date_Format::newLocalShort($quiz['finish_date']);
                } catch (\Exception $e) {
                    $this->throwError('You must enter a finish date for each quiz before you can proceed.');
                    return;
                }
            }
        }

        // Parse homepages out of CSV format into an array

        $homepages = explode(",", $post["homepage"]);

        // Test permissions for each homepage. Must have write permission on the destination page and read permission
        // on the course we are trying to import.

        // Test course page read access

        if ($this->getAccess()->getFolderAccess($this->getUser(), [$post["course_homepage"]]) < READ_ACCESS) {
            throw new Failure\Unauthorized();
        }

        // Test each homepage for write access

        foreach ($homepages as $homepage) {
            if ($this->getAccess()->getFolderAccess($this->getUser(), [$homepage]) < WRITE_ACCESS) {
                throw new Failure\Unauthorized();
            }
        }

        /** @var \Schoolbox\User[] $owners */
        $owners = [];

        if (isset($post['ownerships']) && strlen($post['ownerships']) && strpos($post['ownerships'], ':')) {
            // unsquash ownerships
            foreach (explode(',', $post['ownerships']) as $ownership) {
                if (preg_match('/^\\d+:\\d+/', $ownership)) {
                    list ($folderId, $userId) = explode(':', $ownership);
                    try {
                        $owners[$folderId] = $this->getUserMapper()->findById($userId);
                    } catch (MapperException $e) {
                        // Couldn't find user. That's sort of okay.
                        $owners[$folderId] = null;
                    }
                }
            }
        }


        // Keep a cache of queried data

        /** @var Folder\Model[] $unitCache */
        $unitCache = [];
        /** @var Folder\Model[] $homepageCache */
        $homepageCache = [];
        /** @var Component_Instance[] $componentCache */
        $componentCache = [];

        // Delete any units that aren't selected

        foreach ($post["unit"] as $unit) {
            if ($unit["import"] != 1) {
                $unitsToDelete = $this->getUnitInstanceMapper()->findByColumnList([
                    "unit_category_id" => $unit["unit_id"],
                    "component_id" => $post["component_id"],
                    "category_id" => $post["homepage"]
                ]);
                $this->getUnitInstanceMapper()->delete($unitsToDelete);
            }
        }

        // Create course outline component on each homepage

        try {
            foreach ($homepages as $homepage) {
                // Get the homepage we are importing into and store it for later
                $homepageCache[$homepage] = $this->getFolderMapper()->findById($homepage);

                try {

                    // Updating existing course outline, just grab it from the database
                    $component = $this->getComponentInstanceMapper()->findById($post["component_id"]);

                    if ($component->getIdentifier() == $homepage
                        && $component->getModule() == 'homepage'
                    ) {
                        $componentCache[$homepage] = $component;
                    } else {
                        // Inserting new course outline, create it on the current homepage
                        $componentCache[$homepage] = $this->injectNewComponent($homepageCache[$homepage], Component_Homepage_CourseOutlineController::class);
                    }

                } catch (\Exception $e) {

                    // Inserting new course outline, create it on the current homepage
                    $componentCache[$homepage] = $this->injectNewComponent($homepageCache[$homepage], Component_Homepage_CourseOutlineController::class);
                }
            }

            // Create new unit instances in each of these pages/components

            foreach ($homepages as $homepage) {

                /* Use same derivation as previous step
                 */
                $allTaskDates = $this->determineTaskDates(
                    $post["unit"],
                    $componentCache[$homepage]->getId()
                );

                $unit_instance_list = new Collection();

                foreach ($allTaskDates['units'] as $currentUnit) {

                    /* TODO: Not sure if caching is much use here
                     * I thought Units couldn't be duped anyway
                     */
                    if (!isset($unitCache[$currentUnit['id']])) {
                        try {
                            $unitCache[$currentUnit['id']] = $this->getFolderMapper()->findById($currentUnit['id']);
                        } catch (MapperExceptionDeleted $e) {
                            // accommodate deleted units
                            $unitCache[$currentUnit['id']] = $e->getDeletedObject();
                        } catch (\Exception $e) {
                            $this->throwError('An unexpected error occurred');
                            return;
                        }
                    }

                    // Work out if we are updating an existing course, or importing a new one

                    try {

                        // Updating an existing unit

                        /** @var UnitInstance $unit_instance */
                        $unit_instance = $this->getUnitInstanceMapper()->findOneByColumnList([
                            "component_id" => $componentCache[$homepage]->getId(),
                            "category_id" => $homepageCache[$homepage]->getId(),
                            "unit_category_id" => $unitCache[$currentUnit['id']]->getId(),
                            "deleted_at" => null,
                        ]);

                        // Update the units open/due date

                        $unit_instance
                            ->setOpenDate(\Alaress_Date_Format::newLocalShort($currentUnit['open_date'])->startOfDay())
                            ->setDueDate(\Alaress_Date_Format::newLocalShort($currentUnit['due_date'])->endOfDay());

                        // Update everything associated with the unit instance

                        $taskInstances = $this->getTaskInstanceMapper()->findByColumn("course_unit_instance_id", $unit_instance->getId());

                        /** @var TaskInstance $taskInstance */
                        foreach ($taskInstances as $taskInstance) {
                            switch ($taskInstance->getType()) {
                                case Task::TYPE_DUE_WORK:

                                    // Due work

                                    $submissionBox = $this->submissionBoxMapper->findById($taskInstance->getSubmissionBox()->getId());
                                    $submissionBox->setDatesFromForm($post["work"][$taskInstance->getId()]);

                                    // Date validation:
                                    // Publish <= Open <= Due <= Late
                                    if ($submissionBox->getPublishDate()->gt($submissionBox->getOpenDate())) {
                                        $this->throwError('A task\'s open date may not be before its publish date.');
                                        return;
                                    }
                                    if ($submissionBox->getOpenDate()->gt($submissionBox->getDueDate())) {
                                        $this->throwError('A task\'s due date may not be before its open date.');
                                        return;
                                    }
                                    if ($submissionBox->getDueDate()->gt($submissionBox->getLateDate())) {
                                        $this->throwError('A task\'s late date may not be before its due date.');
                                        return;
                                    }

                                    if (isset($owners[$homepage])) {
                                        // update the owner
                                        $submissionBox->setOwner($owners[$homepage]);
                                    }

                                    $this->getSubmissionBoxMapper()->save($submissionBox);

                                    break;

                                case Task::TYPE_ONLINE_ASSESS:

                                    // Quizzes

                                    $assessment = $this->getOnlineAssessmentMapper()->findById($taskInstance->getAssessment()->getId());
                                    $assessment->setDatesFromForm($post["quiz"][$taskInstance->getId()]);

                                    // Date validation:
                                    // Start <= Finish
                                    if ($assessment->getStart()->gt($assessment->getFinish())) {
                                        $this->throwError('A task\'s finish date may not be before its start date.');
                                        return;
                                    }

                                    if (isset($owners[$homepage])) {
                                        // update the owner
                                        $assessment->setUser($owners[$homepage]);
                                    }

                                    $this->getOnlineAssessmentMapper()->save($assessment);

                                    break;
                            }
                        }

                        $this->getSubmissionBoxMapper()->findByColumn("task_instance_id", $unit_instance->getId());

                        $unit_instance_list->add($unit_instance);

                    } catch (MapperException $e) {

                        // Adding a new unit into the course.

                        $unit_instance = new UnitInstance();

                        $unit_instance
                            ->setWorkDates($post["work"])// Override due work dates in cloner
                            ->setQuizDates($post["quiz"])// Override quiz dates in cloner
                            ->setWorkOwner(isset($owners[$homepage]) ? $owners[$homepage] : $this->getUser())
                            ->setComponent($componentCache[$homepage])
                            ->setTitle($unitCache[$currentUnit['id']]->getName())
                            ->setCategory($homepageCache[$homepage])
                            ->setUnitCategory($unitCache[$currentUnit['id']])
                            ->setOpenDate(\Alaress_Date_Format::newLocalShort($currentUnit['open_date'])->startOfDay())
                            ->setDueDate(\Alaress_Date_Format::newLocalShort($currentUnit['due_date'])->endOfDay());

                        $unit_instance_list->add($unit_instance);
                    }
                }

                /* TODO: Coursework Date superposition
                 * Apply separate dates for existing and new tasks
                 */
                $this->getUnitInstanceMapper()->save($unit_instance_list);
            }

            /* Redirect back to wherever you came from...
             * ...hopefully (...your confidence is inspiring)
             */
            $this->getResponse()->content->set([
                'format' => 'json',
                'data' => [
                    'redirect' => $this->getSession()->getHistory(2)
                ]
            ]);

        } catch (\InvalidArgumentException $e) {
            // bad dates, most likely
            $this->throwError("Invalid date given");

        } catch (\Exception $e) {
            $this->sendError($e->getMessage());
        }

    }

    /**
     * This GET provides the import form, stepped in at stage 3 (since target and ownership cannot be changed)
     * Modify the Unit information for a previously imported Course
     *
     * @api   GET /course/import/modify/{componentId}
     * @level 160
     *
     * @param int $componentId
     */
    public function importModify($componentId)
    {
        $compendium = [];

        /** @var Component_Instance $compInst */
        $compInst = $this->getComponentInstanceMapper()->findById($componentId);

        /** @var Collection|UnitInstance[] $selectedUnitInstances */
        $selectedUnitInstances = $this->getUnitInstanceMapper()->findForComponent($compInst);

        /** @var Collection $tasks */
        $tasks = $selectedUnitInstances->first()->getTasks();
        if ($tasks->count() > 0) {
            $owner = $this->getUserMapper()->findById($tasks->first()->getOwner()->getId());
        } else {
            // no tasks in this unit: use the session user
            $owner = $this->getUser();
        }

        $course = null;
        foreach ($selectedUnitInstances as $selectedUnitInstance) {
            $compendium[$selectedUnitInstance->getUnitCategory()->getId()]['selected'] = $selectedUnitInstance;
            if (!$course instanceof CourseModel) {
                try {
                    $course = $this->getUnitInstanceMapper()->findCourseForUnit($selectedUnitInstance);
                } catch (MapperException $e) {
                    // This unit doesn't actually exist - get course from next one
                    continue;
                }
            }
        }

        // After testing every unit do I still not have a course? That's an error
        if (!$course instanceof CourseModel) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_RESOURCE_NOT_FOUND);
        }

        $allUnits = $course->getUnits();

        foreach ($allUnits as $unit) {
            $compendium[$unit->getId()]['folder'] = $unit;
        }

        $unitOutput = [];

        foreach ($compendium as $id => $values) {
            /** @var UnitInstance $selected */
            $selected = $values['selected'];
            /** @var Folder\Model $folder */
            $folder = $values['folder'];
            $unitTitle = '';
            $checkable = false;
            if ($folder instanceof Folder\Model) {
                $unitTitle = $folder->getName();
                $checkable = true;
            } elseif ($selected instanceof UnitInstance) {
                $unitTitle = $selected->getTitle();
            }

            $unitOutput[$id] = [
                'id' => $id,
                'name' => $unitTitle,
                'checkable' => $checkable,
            ];

            if (isset($values['selected'])) {
                $unitOutput[$id]['checked'] = true;
                $unitOutput[$id]['openDate'] = $selected->getOpenDate();
                $unitOutput[$id]['dueDate'] = $selected->getDueDate();
            }
        }

        /* Find the destination for selectize
         */
        $allComponents = $this->getUnitInstanceMapper()->findByColumn('component_id', $componentId);

        if ($allComponents->count()) {
            $folderId = $allComponents->first()->getCategory()->getId();
            $destination = $this->getFolderMapper()->findCheaplyById($folderId);
        } else {
            /* TODO: Error; component never existed
             */
            $destination = $this->getFolderMapper()->createModel();
        }

        $this->tpl('import', [
            '_part3' => true,
            'units' => $unitOutput,
            'folder' => $destination,
            'sources' => $course,
            'destinations' => array_combine(['id', 'name'], $destination->findValueLabelPair()),
            'homepage' => $destination->getId(),
            'courseHomepage' => $course->getId(),
            'componentId' => $componentId,
            'owner' => $owner
        ]);
    }


    /**
     * Deletes an import, along with ALL of its associated data
     *
     * @api   POST /import/delete
     * @level 160
     */
    public function importDelete()
    {
        $post = $this->getRequest()->post;

        if (!isset($post['componentId'])) {
            $this->sendError('Component ID not defined');
            return;
        }

        $componentId = $post['componentId'];
        $requiresDelete = false;

        try {
            /** @var Component_Instance $componentInstance */
            $componentInstance = $this->getComponentInstanceMapper()->findById($componentId);
            $requiresDelete = true;
        } catch (MapperException $e) {
            // the component doesn't exist, so just fake it
            $componentInstance = $this->getComponentInstanceMapper()->createModel()->setId($componentId);
        }

        $unitInstances = $this->getUnitInstanceMapper()->findForComponent($componentInstance);

        try {
            $this->getUnitInstanceMapper()->delete($unitInstances);

            if ($requiresDelete) {
                $this->getComponentInstanceMapper()->delete($componentInstance);
            }

            $this->getResponse()->content->set([
                'format' => 'json',
                'data' => true
            ]);


        } catch (\Exception $e) {
            $this->sendError($e->getMessage(), [], 500);
            return;
        }

    }


    /**
     * Save the given template for the given folder
     *
     * @param Folder\Model $model
     * @param int $template
     */
    protected function saveHomepageConfig(Folder\Model $model, $template)
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
     * View a list of courses associated with a homepage
     *
     * @api   GET /course/management/{folderId}
     * @level 160
     *
     * @param int $folderId
     */
    public function management($folderId)
    {
        /* TODO: Exception falls directly on to carpet
         */
        $folder = $this->getFolderMapper()->findById($folderId);

        /* Find all the Courses associated with this Folder, via imported Units
         */
        $allCourses = new Collection();

        $allFolders = $this->getUnitInstanceMapper()->findAllCoursesUsedInFolders([$folderId]);

        if ($allFolders->count()) {
            $allCourses = $allFolders->getOffset($folderId);
        }

        $lineages = [];

        foreach ($allCourses as $i => $course) {
            try {
                // if one of the ancestors is deleted, this will explode
                $lineages[$course->getId()] = $this->getLineage($course);
            } catch (MapperException $e) {
                // this course exists in a deleted ancestor, so strictly speaking it no longer exists either...
                unset($allCourses[$i]);
            }
        }

        /* Find all the Units in this Folder; also includes
         * Units which have had their Course Outlines removed
         */
        $allCourseComponentIds = $this->getUnitInstanceMapper()->findComponentsForCourses($folderId);

        $this->tpl('management', [
            'folder' => $folder,
            'allCourses' => $allCourses,
            'allCourseComponentIds' => $allCourseComponentIds,
            'lineage' => $lineages
        ]);
    }

    /**
     * Restore a Course instance via rebuilding its component
     *
     * @deprecated Course (implementation) existence is not tied to the existence of its component anymore.
     *
     * @api   POST /course/import/restore
     * @level 160
     *
     */
    public function restoreCourse()
    {
        $folderId = $this->getRequest()->post->get('folderId');
        $componentId = $this->getRequest()->post->get('componentId');

        $allUnits = $this->getUnitInstanceMapper()->findByColumn('component_id', $componentId);

        /* Spawn a new Course Builder instance
         */
        $componentDefinition = $this->getComponentDefinitionMapper()->findByColumn(
            'class',
            'Component_Homepage_CourseOutlineController'
        );

        $componentDefinitionId = $componentDefinition->first()->getId();

        $component = new \Component_Instance();
        $component
            ->setDefinitionId($componentDefinitionId)
            ->setModule('homepage')
            ->setIdentifier($folderId)
            ->setPosition('top')
            ->setSequence(1);

        $this->getComponentInstanceMapper()->save($component);

        /* Reassign the new Course Builder instance id to each
         * unit that previously used $componentId
         */
        foreach ($allUnits as $currentUnit) {
            $currentUnit->setComponent($component->getId());

            $this->getUnitInstanceMapper()->save($currentUnit);
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

        /** @var Folder\Controller $folderController */
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

        /** @var Folder\Controller $folderController */
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
        $this->getResponse()->status->set(500);

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
     * Get online assessment mapper
     *
     * @return OnlineAssessmentMapper
     */
    protected function getOnlineAssessmentMapper()
    {
        return $this->onlineAssessmentMapper;
    }

    /**
     * Set online assessment mapper
     *
     * @param OnlineAssessmentMapper $onlineAssessmentMapper
     */
    public function setOnlineAssessmentMapper($onlineAssessmentMapper)
    {
        $this->onlineAssessmentMapper = $onlineAssessmentMapper;
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
     * @return TaskInstanceMapper
     */
    protected function getTaskInstanceMapper()
    {
        return $this->taskInstanceMapper;
    }

    /**
     * @param TaskInstanceMapper $taskInstanceMapper
     */
    public function setTaskInstanceMapper($taskInstanceMapper)
    {
        $this->taskInstanceMapper = $taskInstanceMapper;
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
            return $this->findFolder($e->getDeletedObject()->getParent()->getId());

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
