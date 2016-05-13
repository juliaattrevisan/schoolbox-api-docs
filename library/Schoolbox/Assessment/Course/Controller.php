<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Web\Controller as Base;
use Alaress\Authorizer\Failure;
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
use Schoolbox\Assessment\Course\Mapper as CourseMapper;
use Schoolbox\Collection;
use Schoolbox\Curriculum;
use Schoolbox\Error;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Browser\AbstractTreeBuilder;
use Schoolbox\Resource\Browser\Resources\TreeBuilder;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Ancestry;
use Schoolbox\Resource\Folder\Code;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\Task\Instance as TaskInstance;
use Schoolbox\Assessment\Course\Task\InstanceMapper as TaskInstanceMapper;
use Schoolbox\Role;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

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

    /** @var CourseMapper */
    protected $courseMapper;

    /** @var Unit\Mapper */
    protected $unitMapper;

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
     */
    public function __construct()
    {
        $this->setCourseMapper(CourseMapper::getInstance());
        $this->setUnitMapper(Unit\Mapper::getInstance());
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
     * @param array  $data
     */
    protected function tpl($template, $data = [])
    {
        $content = [
            'template' => "@course/$template.twig",
            'data'     => array_merge($data, ['currentUser' => $this->getUser()]),
            'tab'      => 'course',
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
     * @param string       $controller
     * @return Component_Instance
     */
    protected function injectNewComponent(Folder\Model $unit, $controller = Component_Homepage_CourseBuilderController::class)
    {
        $definitionMapper = $this->getComponentDefinitionMapper();
        $instanceMapper   = $this->getComponentInstanceMapper();

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
     * Form to modify a course
     *
     * @api   GET /course/modify/{id}
     * @level 160
     *
     * @param int    $id
     * @param string $name
     */
    public function modifyCourse($id = null, $name = null)
    {
        $data = [];

        if ($id) {

            // this is the one exception to the rule: you may modify a FOLDER to convert it into a course by adding units.
            // as such, this searches for folders rather than explicitly courses, but said course:
            //   - must live in the faculty root; and
            //   - must not have any course descendants itself
            $folder = $this->getFolderMapper()->findById($id);

            // check ancestry
            if (!$this->getCourseMapper()->mayBeConvertedToCourse($folder)) {
                $this->sendError('Folder chosen may not be converted to a course');
            }

            $folder->setParent($this->getFolderMapper()->findById($folder->getParent()->getId()));

            $instances = [];

            $data['folder'] = $folder;
            $data['access'][$folder->getId()] = $this->getAccess()->getFolderAccess($this->getUser(), [ $folder->getId() ]);
            $data['units']  = $this->getUnitMapper()->findByCourseId($folder->getId());

            foreach ($data['units'] as $unit) {
                $data['access'][$unit->getId()] = $this->getAccess()->getFolderAccess($this->getUser(), [ $unit->getId() ]);
                $data['tasks'][$unit->getId()] = $this->getTaskMapper()->findByCategory($unit);
                $data['returnUrl'][$unit->getId()] = $this->getReturnUrl($id, $unit->getId());
                $data['usages'][$unit->getId() ] = $this->getUnitInstanceMapper()->findAllFoldersUsingCourse($folder)->getColumnValues('id');

                // obtain the Course Builder Component Instance from the homepage.
                $instances[$unit->getId()] = $this->getCourseBuilderInstance($unit);

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

        if (is_array($post['unitname'])) {
            // save unit name changes
            foreach ($post['unitname'] as $unitId => $unitName) {
                $this->getFolderMapper()->save(
                    $this->getFolderMapper()->findById($unitId)->setName($unitName)
                );
            }
        }

        $this->saveCourse($post, $rq->isXhr(), 'course.courseList');
    }


    /**
     * Common logic to create and modify a course
     *
     * @param Values $post
     * @param bool   $xhr
     * @param string $redir
     */
    protected function saveCourse(Values $post, $xhr, $redir)
    {
        $rs = $this->getResponse();

        try {
            // attempt to persist this course based on the given POST data
            $folder = $this->persistCourse($post);

            if ($xhr) {
                // respond to XHR with JSON
                $rs->content->set([
                    'format' => 'json',
                    'data'   => [ 'id' => $folder->getId() ]
                ]);

            } else {
                // respond to synchronous POST with redirect
                $rs->redirect->afterPost($this->getRouteIfAuthorized($redir, [ $folder->getId() ]));
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
     * @return Folder\Model
     */
    protected function persistCourse($post)
    {
        $mode   = BaseMapper::METHOD_CREATE;
        $parent = null;
        $errors = [];
        $name   = trim($post->get('name'));

        if (!$name) {
            $errors[] = 'give the course a name';
            $name = 'this course';
        }

        try {
            $parentId = $post->get('parent');
            if ($parentId === "0" || $parentId > 0) {
                $parent = $this->getFolderMapper()->findById($parentId);
            } else {
                // cause a snap if no (or invalid) parent was supplied
                throw new BaseMapper\Exception("Invalid parent nominated");
            }


        } catch (BaseMapper\Exception $e) {

            $errors[] = "select a faculty or class for $name to live in";
        }

        // if there were any validation problems, throw that back
        if ($errors) {
            throw new Base\Exception("Could not save", $errors);
        }

        // basic validation passed: prepare to save!

        // was an ID sent through?
        $id = trim($post->get('id'));

        // extract codes
        $codes = $post->get('curriculumCodes', []);

        if ($id) {
            try {
                // do checks to make sure it exists
                $folder = $this->getCourseMapper()->findById($id);
                $mode = BaseMapper::METHOD_UPDATE;

            } catch (BaseMapper\Exception $e) {
                throw new Base\Exception("Could not save because " . $e->getMessage());
            }
        }

        try {
            $datums = [
                // the basics
                'categoryID'      => $id,
                'name'            => $name,
                'parent'          => $parent->getId(),
                'curriculumCodes' => $codes,
                'inherit'         => $this->getConfigLoader()->get('course_builder_default_course_page_inherit'),
            ];

            if ($mode == BaseMapper::METHOD_CREATE) {
                $rolePermissions = [];

                foreach ($this->getCourseConfigMapper()->findByColumn('page', 'course') as $courseConfig) {
                    /** @var CourseConfigModel $courseConfig */
                    $rolePermissions[$courseConfig->getRoleId()] = $courseConfig->getPermission();
                }

                $datums = array_merge($datums, [


                    // configurable things
                    'inherit'         => $this->getConfigLoader()->get('course_builder_default_course_page_inherit'),
                    'template'  => $this->getConfigLoader()->get('course_builder_default_course_page_template'),
                    'group'     => $rolePermissions,

                    // leave these always off
                    'recursive' => false, // LEAVE THIS
                    'code'      => null,  // LEAVE THIS

                ]);
            } else {

                /** @var Folder\Model $folder */
                $datums = array_merge($datums, [
                    // configurable things
                    'inherit'         => $folder->getInherit()
                ]);
            }

            $folder = $this->persistFolder($datums);

            return $folder;

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
            $problem     = $problem->getMessage();
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
            'format'   => 'nochrome',
            'data'     => [
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
        $path = $this->getRouteIfAuthorized('course.modifyCourse', [ 'id' => $courseId ]);

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

        $name  = trim($post->get('name'));

        if (!$name) {
            $this->sendError('Could not create unit', [ 'give the unit a name' ]);
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

            $folder = $this->persistFolder([
                // the basics (note lack of ID and Codes)
                'name'            => $name,
                'parent'          => $post['id'],

                // configurable things
                'inherit'         => $this->getConfigLoader()->get('course_builder_default_unit_page_inherit'),
                'template'        => $this->getConfigLoader()->get('course_builder_default_unit_page_template'),
                'group'           => $rolePermissions,

                // leave these always off
                'recursive'       => false, // LEAVE THIS
                'code'            => null,  // LEAVE THIS
            ]);

            $instance  = $this->getCourseBuilderInstance($folder);
            $folderId  = $folder->getId();
            $returnUrl = $this->getReturnUrl($post['id'], $folderId);

            $data = [
                'unit'  => [
                    'id'   => $folderId,
                    'name' => $folder->getName()
                ],
                'returnUrl' => [
                    $folderId => $returnUrl
                ],
                'reveals'   => [ $folderId => new TaskBuilder($instance, null, $returnUrl) ],
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
        if ($this->getAccess()->getFolderAccess($this->getUser(), [ $folder->getId() ]) < UserAccess::ACCESS_WRITE) {
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
    public function courseList() {
        $this->displayCourseList('courseList');
    }


    /**
     * Dump out AJAX'd search results
     * The Filter field allows you to search Course names
     *
     * @api   GET /course/list/ajax
     * @level 160
     */
    public function courseListAjax() {
        $this->displayCourseList('courseListResults');
    }


    /**
     * Sub-method for course list renderings
     *
     * @param string $template
     */
    protected function displayCourseList($template) {

        $query = $this->getRequest()->query;

        $component = $query->get('component');
        $folder    = $query->get('folder');
        $search    = $query->get('query');

        $data  = $this->getCourseData($search);

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
                'hasAdd'        => false,
                'hasTot'        => false,
                'linkToUnits'   => false,
                'hasClose'      => true,
            ];
        }
        else {
            $options = [
                'hasBreadcrumb' => true,
                'hasAdd'        => true,
                'hasTot'        => true,
                'linkToUnits'   => true,
                'hasClose'      => false,
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
        return $this->getAccess()->getFolderAccess($this->getUser(), [ $folderId ]) >= $min;
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
        $allCourses = $this->getCourseMapper()->findCourseListByName($searchParam, [ 'order' => [ 'name' => 'ASC' ] ]);

        $data = [];

        foreach ($allCourses as $currentCourse) {

            // confirm you at least have read access...
            if (!$this->confirmAccess($currentCourse->getId())) {
                continue;
            }

            $data['courses'][] = $currentCourse;

            $allUnits = $this->getUnitMapper()->findByCourseId($currentCourse->getId());

            // Append to unit index, by ID
            foreach ($allUnits as $currentUnit) {
                // confirm you at least have read access...s
                if (!$this->confirmAccess($currentUnit->getId())) {
                    continue;
                }

                $data['units'][$currentCourse->getId()][] = $currentUnit;
            }

            /* Also store the path to this Course
             */
            $data['lineage'][$currentCourse->getId()] = $this->getLineage($currentCourse);
        }

        return $data;
    }


    /**
     * Convenience method
     *
     * @param Folder\Model $course
     * @return Collection
     */
    protected function getLineage(Folder\Model $course)
    {
        return $this->getAncestry()->findParentsCheaply($course, $this->getCourseMapper()->getFacultyNode());
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
        $codes   = $folder->getCurriculumNodes()->getColumnValues('code');

        // find all the folders that match this code, then filter for coarseness
        return $this->getFolderMapper()->findByCurriculumCode($codes)->filter(function ($folder) {
            return $this->courseMapper->verifyCourse($folder);
        });
    }


    /**
     * @param AbstractTreeBuilder $tb
     * @param array               $target
     * @return array
     */
    protected function recursiveTreeBuild(AbstractTreeBuilder $tb, $target)
    {
        $target['children'] = $tb->buildChildNodes($target['id']);

        foreach ($target['children'] as $i => $child) {

            if ($child['type'] == 'course') {
                unset($target['children'][$i]);
                continue;
            }

            if ($child['children']) {
                $target['children'][$i] = $this->recursiveTreeBuild($tb, $child);
            }
        }

        return $target;
    }


    /**
     * List the folders that are allowed to be course parents
     *
     * @api   GET /course/parent
     * @level 160
     */
    public function parentList()
    {
        // the valid list of parents:
        //  - start at facultyTop $this->getCourseMapper()->getFacultyNode(),
        //  - recurse into every folder for which the user has WRITE access
        //  - stop at, and exclude, all Courses (folder that has > 0 units)
        //  - continue to exhaustion

        $tb = new TreeBuilder($this->getFolderMapper(), $this->getUnitMapper(), $this->getUser());

        $facultyNode = $this->getCourseMapper()->getFacultyNode();

        $data = [
            'id'   => $facultyNode->getId(),
            'text' => $facultyNode->getName()
        ];

        // recurse, passing $data by reference
        $data = $this->recursiveTreeBuild($tb, $data);

        $data = [
            'branch' => $data
        ];

        $this->tpl('parentList', $data);
    }

    /**
     * This GET is the start of the import process. Import a Course into a set of Folders (classes)
     *
     * @api   GET /course/import/{courseId}/class/{classCode}
     * @level 160
     *
     * @param int    $courseId
     * @param string $classCode
     */
    public function import($courseId, $classCode)
    {
        $importer = new CourseImport();

        $this->tpl('import', $importer->importSelectize($courseId, $classCode));
    }


    /**
     * This POST is the result of import stage 2
     *
     * @api   POST /course/import/ownership
     * @level 160
     */
    public function importOwnership()
    {
        $ownerships     = $this->getRequest()->post["ownership"];
        $courseHomepage = trim($this->getRequest()->post["importCourse"]);

        // process ownership assignments
        // respond with stage 3: unit selection
        $this->tpl('unitImportForm', [
            'homepage'       => implode(',', array_keys($ownerships)),
            'courseHomepage' => $courseHomepage,
            'units'          => $this->getUnitMapper()->findByCourseId($courseHomepage),
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
        $homepage       = trim($this->getRequest()->post["importFolders"]);
        $courseHomepage = trim($this->getRequest()->post["importCourse"]);

        try {
            $this->getCourseMapper()->findById($courseHomepage);

        } catch (MapperException $e) {
            // the nominated "course" is not actually a course...
            $this->throwError('The selected source is not a course');
            return;
        }

        if ($homepage == "" && $courseHomepage == "")
        {
            // They didn't select anything

            $this->throwError('You must select at least one class and a course to import before you can proceed');
        }
        else if ($homepage == "")
        {
            // They didn't select a class code

            $this->throwError('You must select at least one class before you can proceed');
        }
        else if ($courseHomepage == "")
        {
            // They didn't select a course to import

            $this->throwError('You must select a course to import before you can proceed');
        }
        else
        {
            $targetFolderIds = explode(',', $homepage);

            $folders = $this->getFolderMapper()->findByColumn('id', $targetFolderIds);

            $possibleOwnerships = [];

            foreach ($folders as $folder) {
                $possibleOwnerships[$folder->getId()] = $this->getUserAccessMapper()->findUsersInCategory(
                    $folder, Role::STAFF, UserAccess::ACCESS_WRITE
                );

                if (count($possibleOwnerships[$folder->getId()]) == 0) {
                    // if nobody else was returned, jam the session user in there.
                    $possibleOwnerships[$folder->getId()] = new Collection([ $this->getUser() ]);
                }
            }

            // Everything kosher, proceed with the mage
            // respond with stage 2: ownership
            $this->tpl('import/ownership', [
                'targetFolders'       => $folders,
                'importCourse'        => $courseHomepage,
                'ownershipCandidates' => $possibleOwnerships
            ]);
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
     * @param $unitDates array User-selected dates for a Unit, (via POST, typically)
     * @param $courseComponentId int A componentId related to this course >.<
     * @return array
     */
    private function determineTaskDates($unitDates, $courseComponentId)
    {
        /** @var Folder\Model[] $unitCache */
        $unitCache = [];

        /* Format this into a convenient structure for now
         */
        $preprocessedUnitDates = [];

        foreach ($unitDates as $currentUnitDate) {
            $preprocessedUnitDates[$currentUnitDate['unit_id']] = $currentUnitDate;
        }

        /* Make a local cache for unit information
         */
        foreach ($unitDates as $unit) {
            if ($unit['import'] && !isset($unitCache[$unit['unit_id']])) {
                $unitCache[$unit['unit_id']] = $this->getUnitMapper()->findById($unit['unit_id']);
            }
        }

        // Find all tasks, due work, and quizzes associated with the selected units.

        $units = new Collection();

        foreach ($unitCache as $key => $unitCurrent) {
            $unit          = [];
            $unit['id']    = $unitCurrent->getId();
            $unit['name']  = $unitCache[$unitCurrent->getId()]->getName();
            $unit['items'] = new Collection();

            foreach ($unitDates as $unitDate) {
                if ($unitDate["unit_id"] == $unitCurrent->getId()) {
                    $unit['open_date'] = $unitDate['open_date'];
                    $unit['due_date']  = $unitDate['due_date'];
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
                        'component_id'     => $courseComponentId,
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

                        /* Retain an associative array of dates that we can summon
                         */
                        $key = $unitInstance->getUnitCategory()->getId();

                        $preprocessedUnitDates[$key]['open_date'] = $unitInstance->getOpenDate()->getLocalShort();
                        $preprocessedUnitDates[$key]['due_date']  = $unitInstance->getDueDate()->getLocalShort();

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

            /* Sort by Created At for now, until manual sequencing
             * is added to the ui so teachers can sort it themselves
             */
            $temp->sortBy('created', 'asc');

            foreach ($temp as $current) {
                $class  = 'Task';
                $detail = null;
                try {
                    if ($current->getAssessment()->getId()) {
                        $class  = 'OnlineAssessment';
                        $itemId = $current->getAssessment()->getId();
                        $detail = $this->getOnlineAssessmentMapper()->findById($itemId);
                    } elseif ($current->getSubmissionBox()->getId()) {
                        $class  = 'SubmissionBox';
                        $itemId = $current->getSubmissionBox()->getId();
                        $detail = $this->getSubmissionBoxMapper()->findById($itemId);
                    }
                } catch (\Schoolbox\Mapper\Exception $e) {
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
                        'class'      => $class,
                        'isAbstract' => $isAbstract,
                        'item'       => $current,
                        'detail'     => $detail
                    ]
                );
            }

            $units->add($unit);
        }

        return [
            'units' => $units,
            'preprocessedUnitDates' => $preprocessedUnitDates
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

        if (trim($post["homepage"]) == "")
        {
            // The homepage ID has gone missing

            $this->throwError('No class code was specified');
        }
        else if (trim($post["course_homepage"]) == "")
        {
            // The course homepage ID has gone missing

            $this->throwError('No course was specified');
        }
        else
        {
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
                        $dueDate  = \Alaress_Date_Format::newLocalShort($unit['due_date']);
                        if ($openDate->gt($dueDate)) {
                            $errorDate = true;
                            $this->throwError('The open date must be before the close date');
                            break;
                        }
                    }
                    catch (\Exception $e) {
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
                $allTaskDates = $this->determineTaskDates(
                    $post["unit"],
                    $post->get('componentId')
                );

                // respond with stage 4: coursework list
                $this->tpl('unitImportResults', [
                    'ownerships' => $post['ownerships'],
                    'homepage' => $post["homepage"],
                    'componentId' => $post["componentId"],
                    'courseHomepage' => $post["course_homepage"],
                    'unitDates' => $post["unit"],
                    'units' => $allTaskDates['units']
                ]);
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

        if (isset($post['ownerships']) && is_array($post['ownerships']) && count($post['ownerships'])) {
            // unsquash ownerships
            foreach (explode(',', $post['ownerships']) as $ownership) {
                if (preg_match('/^\\d+:\\d+/', $ownership)) {
                    list ($folderId, $userId) = explode(':', $ownership);
                    try {
                        $owners[$folderId] = $this->getUserMapper()->findById($userId);
                    } catch (\Schoolbox\Mapper\Exception $e) {
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
                    $componentCache[$homepage] = $this->getComponentInstanceMapper()->findById(
                        $post["component_id"]
                    );

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
                            "component_id" => $post["component_id"],
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
                        foreach ($taskInstances as $taskInstance)
                        {
                            switch ($taskInstance->getType())
                            {
                                case Task::TYPE_DUE_WORK:

                                    // Due work

                                    $submissionBox = $this->submissionBoxMapper->findById($taskInstance->getSubmissionBox()->getId());

                                    $due_date = \Alaress_Date_Format::newLocalShort($post["work"][$taskInstance->getId()]['due_date']);
                                    $late_date = \Alaress_Date_Format::newLocalShort($post["work"][$taskInstance->getId()]['late_date']);
                                    $open_date = \Alaress_Date_Format::newLocalShort($post["work"][$taskInstance->getId()]['open_date']);
                                    $publish_date = \Alaress_Date_Format::newLocalShort($post["work"][$taskInstance->getId()]['publish_date']);

                                    $submissionBox->setDueDate($due_date->endOfDay());
                                    $submissionBox->setLateDate($late_date->endOfDay());
                                    $submissionBox->setOpenDate($open_date->startOfDay());
                                    $submissionBox->setPublishDate($publish_date->startOfDay());

                                    if (isset($owners[$homepage])) {
                                        // update the owner
                                        $submissionBox->setOwner($owners[$homepage]);
                                    }

                                    $this->getSubmissionBoxMapper()->save($submissionBox);

                                    break;

                                case Task::TYPE_ONLINE_ASSESS:

                                    // Quizzes

                                    $assessment = $this->getOnlineAssessmentMapper()->findById($taskInstance->getAssessment()->getId());

                                    $start_date = \Alaress_Date_Format::newLocalShort($post["quiz"][$taskInstance->getId()]['start_date']);
                                    $finish_date = \Alaress_Date_Format::newLocalShort($post["quiz"][$taskInstance->getId()]['finish_date']);

                                    $assessment->setStart($start_date->startOfDay());
                                    $assessment->setFinish($finish_date->endOfDay());

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
                            ->setWorkDates($post["work"]) // Override due work dates in cloner
                            ->setQuizDates($post["quiz"]) // Override quiz dates in cloner
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

        foreach ($selectedUnitInstances as $selectedUnitInstance) {
            $compendium[$selectedUnitInstance->getUnitCategory()->getId()]['selected'] = $selectedUnitInstance;
        }

        $course   = $this->getUnitInstanceMapper()->findCourseForUnit($selectedUnitInstances->first());
        $allUnits = $course->getUnits();

        foreach ($allUnits as $unit) {
            $compendium[$unit->getId()]['folder'] = $unit;
        }

        $unitOutput = [];

        foreach ($compendium as $id => $values) {
            /** @var Folder\Model $folder */
            $folder = $values['folder'];

            /** @var UnitInstance $selected */
            $selected = $values['selected'];

            $unitOutput[$id] = [
                'id' => $id,
                'name' => $folder->getName(),
            ];

            if (isset($values['selected'])) {
                $unitOutput[$id]['checked'] = true;
                $unitOutput[$id]['openDate'] = $selected->getOpenDate();
                $unitOutput[$id]['dueDate']  = $selected->getDueDate();
            }
        }

        /* Find the destination for selectize
         */
        $allComponents = $this->getUnitInstanceMapper()->findByColumn('component_id', $componentId);

        if ($allComponents->count()) {
            $folderId = $allComponents->first()->getCategory()->getId();
            $destination = $this->getFolderMapper()->findCheaplyById($folderId);
        }
        else {
            /* TODO: Error; component never existed
             */
            $destination = $this->getFolderMapper()->createModel();
        }

        $this->tpl('import', [
            '_part3'  => true,
            'units'   => $unitOutput,
            'folder'  => $destination,
            'sources' => $course,
            'destinations' => array_combine(['id','name'], $destination->findValueLabelPair()),
            'homepage' => $destination->getId(),
            'courseHomepage' => $course->getId(),
            'componentId' => $componentId,
            'owner'       => $owner
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

        $componentId    = $post['componentId'];
        $requiresDelete = false;

        try {
            $componentInstance = $this->getComponentInstanceMapper()->findById($componentId);
            $requiresDelete    = true;
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
                'data'   => true
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
     * @param int          $template
     */
    protected function saveHomepageConfig(Folder\Model $model, $template)
    {
        $confMapper = $this->getHomepageConfigMapper();
        $tplMapper  = $this->getHomepageTemplateMapper();

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
     * Recursively apply inheritance. Why? Not sure. Cut-Paste from /cms/categoryForm.php
     *
     * @param Folder\Model $model
     */
    protected function recurseInheritance(Folder\Model $model) {
        $folders = $this->getCourseMapper()->findAllChildren($model->getId(), true);
        foreach ($folders as $folder) {
            $folder->setInherit($model->getInherit());
            $this->getCourseMapper()->save($folder);
            $this->recurseInheritance($folder);
        }
    }

    /**
     * Delete unit. This will delete the underlying folder and all its content.
     *
     * @api   POST /course/unit/delete
     * @level 160
     */
    public function deleteUnit()
    {
        $this->getCourseMapper()->deleteUnit($this->getRequest()->post["id"]);

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

        foreach ($allCourses as $course) {
            $lineages[$course->getId()] = $this->getLineage($course);
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
     * Delete a course by blowing away all its units
     *
     * @api   POST /course/delete
     * @level 160
     */
    public function deleteCourse()
    {
        // mmm, this should be a URI param
        $courseId = $this->getRequest()->post->get('courseId');

        // TODO a TODO here would have been nice...
        new \Alaress_Probe($courseId);

        die();
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
     * Get course mapper
     *
     * @return CourseMapper
     */
    protected function getCourseMapper()
    {
        return $this->courseMapper;
    }

    /**
     * Set course mapper
     *
     * @param CourseMapper $courseMapper
     */
    public function setCourseMapper($courseMapper)
    {
        $this->courseMapper = $courseMapper;
    }


    /**
     * @return Unit\Mapper
     */
    public function getUnitMapper()
    {
        return $this->unitMapper;
    }


    /**
     * @param Unit\Mapper $unitMapper
     * @return static
     */
    public function setUnitMapper($unitMapper)
    {
        $this->unitMapper = $unitMapper;
        return $this;
    }


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
    protected function getUserMapper()
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
     * @return Ancestry
     */
    public function getAncestry()
    {
        return $this->getCourseMapper()->getAncestry();
    }

}
