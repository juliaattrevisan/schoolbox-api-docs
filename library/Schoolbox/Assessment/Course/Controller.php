<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Web\Controller\Base as BaseController;
use Alaress\Authorizer\Failure;
use Aura\Di\Exception\ServiceNotFound;
use Component_DefinitionMapper;
use Component_Instance;
use Component_InstanceMapper;
use RangeException;
use Schoolbox\Curriculum;
use Schoolbox\Collection;
use Schoolbox\Assessment\Component\OnlineAssessmentMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Browser\AbstractTreeBuilder;
use Schoolbox\Resource\Browser\Resources\TreeBuilder;
use Schoolbox\Resource\Category;
use Schoolbox\Resource\Folder\Code;
use Schoolbox\Resource\Folder;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Assessment\Course\Config\Model as CourseConfigModel;
use Schoolbox\Assessment\Course\Mapper as CourseMapper;
use Schoolbox\Assessment\Course\Import\Import as CourseImport;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Assessment\Course\Task;
use Schoolbox\Assessment\Course\Task\Instance as TaskInstance;
use Schoolbox\Assessment\Course\Task\InstanceMapper as TaskInstanceMapper;

class Controller extends BaseController
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

    /** @var Access */
    protected $access;

    /**
     * Set dependencies
     */
    public function __construct()
    {
        $this->setCourseMapper(CourseMapper::getInstance());
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
        ];

        if ($this->getRequest()->isXhr()) {
            $content['format'] = 'nochrome';
        }

        $this->getResponse()->content->set($content);
    }


    /**
     * Form to modify a course
     *
     * @api   GET /course/modify/{id}
     * @level 160
     * @param int $id
     * @param string $name
     */
    public function modifyCourse($id = null, $name = null)
    {
        $data = [];

        if ($id) {
            $folder = $this->getCourseMapper()->findById($id);
            $folder->setParent($this->getCourseMapper()->findById($folder->getParent()->getId()));

            $data['folder']    = $folder;
            $data['units']     = $this->getCourseMapper()->findByParent($id);

            foreach ($data['units'] as $unit) {
                $data['tasks'][$unit->getId()] = $this->getTaskMapper()->findByCategory($unit);
                $data['returnUrl'][$unit->getId()] = $this->getReturnUrl($id, $unit->getId());
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
     * @param $name
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
        $parent = null;
        $errors = [];
        $post   = $this->getRequest()->post;
        $name   = trim($post->get('name'));

        if (!$name) {
            $errors[] = 'give the course a name';
            $name = 'this course';
        }

        try {
            $parentId = $post->get('parent');
            if ($parentId === "0" || $parentId > 0) {
                $parent = $this->getCourseMapper()->findById($parentId);
            } else {
                // cause a snap if no (or invalid) parent was supplied
                throw new BaseMapper\Exception("Invalid parent nominated");
            }


        } catch (BaseMapper\Exception $e) {

            $errors[] = "select a faculty or class for $name to live in";
        }

        // if there were any validation problems, throw that back
        if ($errors) {
            $this->sendError("Could not save", $errors);
            return;
        }

        // basic validation passed: prepare to save!

        // was an ID sent through?
        $id = trim($post->get('id'));

        // extract codes
        $codes = $post->get('curriculumCodes', []);

        if ($id) {
            try {
                // do checks to make sure it exists
                $this->getCourseMapper()->findById($id);

            } catch (BaseMapper\Exception $e) {
                $this->sendError("Could not save because ".$e->getMessage());
                return;
            }
        }

        try {
            /** @var Folder\Controller $folderController */
            $folderController = $this->getController('folder');

            // Need:
            // base config
            // course config
            $rolePermissions = [];
            foreach ($this->getCourseConfigMapper()->findByColumn('page', 'course') as $courseConfig) {
                /** @var CourseConfigModel $courseConfig */
                $rolePermissions[$courseConfig->getRoleId()] = $courseConfig->getPermission();
            }


            $folder = $folderController->persistFolder([
                // the basics
                'categoryID'      => $id,
                'name'            => $name,
                'parent'          => $parent->getId(),
                'curriculumCodes' => $codes,

                // configurable things
                'inherit'         => $this->getConfigLoader()->get('course_builder_default_course_page_inherit'),
                'template'        => $this->getConfigLoader()->get('course_builder_default_course_page_template'),
                'group'           => $rolePermissions,

                // leave these always off
                'recursive'       => false, // LEAVE THIS
                'code'            => null,  // LEAVE THIS

            ], $this->getUser());

            $this->getResponse()->content->set([
                'format' => 'json',
                'data'   => [ 'id' => $folder->getId() ]
            ]);

            return;

        } catch (ServiceNotFound $e) {
            // quite simple not able to find things we need. bye bye.
            $this->sendError("Could not save because of an internal error.", [], 500);

        } catch (\Exception $e) {
            // whatever the exception, recover from it as best you can
            $this->sendError("Could not save because " . $e->getMessage());
        }

    }


    /**
     * Send a neat and tidy error back to the client
     *
     * @param string $problem
     * @param array  $resolutions
     * @param int    $statusCode
     * @return void
     */
    protected function sendError($problem, array $resolutions = [], $statusCode = 400)
    {
        if (count($resolutions)) {
            $problem .= ". You need to";
        }

        $this->getResponse()->status->set($statusCode);

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

        // TODO get permission and template configs
        try {
            /** @var Folder\Controller $folderController */
            $folderController = $this->getController('folder');

            $folder = $folderController->persistFolder([
                // the basics (note lack of ID and Codes)
                'name'            => $name,
                'parent'          => $post['id'],

                // configurable things
                'inherit'         => true,  // TODO config inheritance
                'template'        => -1,    // TODO config template
                'group'           => [],    // TODO config role permissions [ $roleId => $permissionLevel ]

                // leave these always off
                'recursive'       => false, // LEAVE THIS
                'code'            => null,  // LEAVE THIS

            ], $this->getUser());

            $data = [
                'unit'  => [
                    'id'   => $folder->getId(),
                    'name' => $folder->getName()
                ],
                'returnUrl' => [
                    $folder->getId() => $this->getReturnUrl($post['id'], $folder->getId())
                ],
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
        $data  = $this->getCourseMapper()->findCourseList($query->get('query'));

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

        $component = $query->get('component');
        $folder    = $query->get('folder');

        if ($folder) {
            $options['folder']['id'] = $folder;
            $options['component']['id'] = $component ?: "0.0"; // TODO replace: this is a hack to make the old import page work without an explicit component instance ID
        }

        $this->tpl($template, array_merge($data, $options));
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
        //  - stop at, and exclude, all Courses (folder that has > 0 children with > 0 tasks) (apparently this point is up for debate..?)
        //  - continue to exhaustion

        $tb = new TreeBuilder($this->getCourseMapper(), $this->getTaskMapper(), $this->getUser());

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
     * Import a Course into a set of Folders (classes)
     *
     * @param $courseId
     * @param $classCode
     */
    public function import($courseId, $classCode)
    {
        $importer = new CourseImport();

        $this->tpl('import', $importer->importSelectize($courseId, $classCode));
    }

    /**
     * Get a list of all the Folders which should
     * receive the selected Course
     */
    public function importClasses()
    {
        $importer = new CourseImport();

        $this->getResponse()->content->set([
            'format' => 'json',
            'data' => $importer->importClasses($this->getRequest()->query->get('query'))
        ]);
    }

    /**
     * Get the Units and Tasks for a particular Course
     *
     * @param $courseId
     * @return array
     */
    public function importResults($courseId)
    {
        $importer = new CourseImport();

        $this->tpl('import/results', $importer->findUnitsAndTasks($courseId));
    }

    /**
     * Retrieve fragment with units in the selected course, and checkboxes/dates for selection
     */
    public function importUnits()
    {
        $homepage       = trim($this->getRequest()->post["importFolders"]);
        $courseHomepage = trim($this->getRequest()->post["importCourse"]);

        try {
            $this->getCourseMapper()->findCourseById($courseHomepage);

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
            // Everything kosher, proceed with the mage
            $this->getResponse()->content->set([
                'template' => "@course/unitImportForm.twig",
                'format' => 'nochrome',
                'data' => [
                    'homepage' => $homepage,
                    'courseHomepage' => $courseHomepage,
                    'units' => $this->getCourseMapper()->findByParentId($courseHomepage)
                ]
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
        /** @var Category[] $unitCache */
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
                $unitCache[$unit['unit_id']] = $this->getCourseMapper()->findById($unit['unit_id']);
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
                if ($current->getAssessment()->getId()) {
                    $class = 'OnlineAssessment';
                    $detail = $this->getOnlineAssessmentMapper()->findById($current->getAssessment()->getId());
                } else {
                    if ($current->getSubmissionBox()->getId()) {
                        $class = 'SubmissionBox';
                        $detail = $this->getSubmissionBoxMapper()->findById($current->getSubmissionBox()->getId());
                    } else {
                        $class = 'Task';
                        $detail = null;
                    }
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
     * Retrieve friend with a list of tasks, quizzes and due work (and relevant date entry boxes) associated with
     * the selected course
     *
     * @throws Unauthorized
     **/
    public function importTasks()
    {
        if (trim($this->getRequest()->post["homepage"]) == "")
        {
            // The homepage ID has gone missing

            $this->throwError('No class code was specified');
        }
        else if (trim($this->getRequest()->post["course_homepage"]) == "")
        {
            // The course homepage ID has gone missing

            $this->throwError('No course was specified');
        }
        else
        {
            $homepages = explode(",", $this->getRequest()->post["homepage"]);

            // Test permissions. Must have write permission on the destination page and read permission
            // on the course we are trying to import.

            // Test course page read access

            if ($this->getAccess()->getFolderAccess($this->getUser(), [$this->getRequest()->post["course_homepage"]]) < READ_ACCESS) {
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

            foreach ($this->getRequest()->post["unit"] as $unit) {
                if (!empty($unit['import'])) {
                    $foundUnit = true;

                    /* Add date validation
                     */
                    try {
                        \Alaress_Date_Format::newLocalShort($unit['open_date']);
                        \Alaress_Date_Format::newLocalShort($unit['due_date']);
                    }
                    catch (\Exception $e) {
                        $errorDate = true;
                        break;
                    }
                }
            }

            // Make sure at least one unit was specified

            if (!$foundUnit) {
                $this->throwError('You must select at least one unit to import');
            }
            else if ($errorDate) {
                // Make sure dates were specified
                $this->throwError('You must specify a valid open and close date for each unit you are importing');
            }
            else {

                /* Figger out when each task should open, due, late, etc
                 * A superposition of Task Instances over Task Abstracts
                 *
                 * (a Task Abstract is one specified in the original Unit
                 * but has not yet been imported)
                 */
                $allTaskDates = $this->determineTaskDates(
                    $this->getRequest()->post["unit"],
                    $this->getRequest()->post->get('componentId')
                );

                $this->getResponse()->content->set
                ([
                    'template' => "@course/unitImportResults.twig",
                    'format' => 'nochrome',
                    'data' => [
                        'homepage' => $this->getRequest()->post["homepage"],
                        'componentId' => $this->getRequest()->post["componentId"],
                        'courseHomepage' => $this->getRequest()->post["course_homepage"],
                        'unitDates' => $this->getRequest()->post["unit"],
                        'units' => $allTaskDates['units']
                    ]
                ]);
            }
        }
    }

    /**
     * Do the actual import and create all relevant database entries
     */
    public function importSave()
    {
        // Validate dates before we save (nothing to do for tasks)

        // Validate due work

        if (isset($this->getRequest()->post["work"])) {
            foreach ($this->getRequest()->post["work"] as $work) {
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

        if (isset($this->getRequest()->post["quiz"])) {
            foreach ($this->getRequest()->post["quiz"] as $quiz) {
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

        $homepages = explode(",", $this->getRequest()->post["homepage"]);

        // Test permissions for each homepage. Must have write permission on the destination page and read permission
        // on the course we are trying to import.

        // Test course page read access

        if ($this->getAccess()->getFolderAccess($this->getUser(), [$this->getRequest()->post["course_homepage"]]) < READ_ACCESS) {
            throw new Failure\Unauthorized();
        }

        // Test each homepage for write access

        foreach ($homepages as $homepage) {
            if ($this->getAccess()->getFolderAccess($this->getUser(), [$homepage]) < WRITE_ACCESS) {
                throw new Failure\Unauthorized();
            }
        }

        // Keep a cache of queried data

        /** @var Category[] $unitCache */
        $unitCache = [];
        /** @var Category[] $homepageCache */
        $homepageCache = [];
        /** @var Component_Instance[] $componentCache */
        $componentCache = [];

        // Delete any units that aren't selected

        foreach ($this->getRequest()->post["unit"] as $unit) {
            if ($unit["import"] != 1) {
                $unitsToDelete = $this->getUnitInstanceMapper()->findByColumnList([
                    "unit_category_id" => $unit["unit_id"],
                    "component_id" => $this->getRequest()->post["component_id"],
                    "category_id" => $this->getRequest()->post["homepage"]
                ]);
                $this->getUnitInstanceMapper()->delete($unitsToDelete);
            }
        }

        // Create course outline component on each homepage

        try {
            foreach ($homepages as $homepage) {
                // Get the homepage we are importing into and store it for later

                $homepageCache[$homepage] = $this->getFolderMapper()->findById($homepage);

                // Work out if we are updating an existing course, or importing a new one

                try {

                    // Updating existing course outline, just grab it from the database

                    $componentCache[$homepage] = $this->getComponentInstanceMapper()->findById(
                        $this->getRequest()->post["component_id"]
                    );

                } catch (\Exception $e) {

                    // Inserting new course outline, create it on the current homepage

                    $componentDefinition = $this->getComponentDefinitionMapper()->findByColumn(
                        'class',
                        'Component_Homepage_CourseOutlineController'
                    );

                    $componentDefinitionId = $componentDefinition->first()->getId();

                    $componentCache[$homepage] = new \Component_Instance();
                    $componentCache[$homepage]
                        ->setDefinitionId($componentDefinitionId)
                        ->setModule('homepage')
                        ->setIdentifier($homepage)
                        ->setPosition('top')
                        ->setSequence(1);

                    $this->getComponentInstanceMapper()->save($componentCache[$homepage]);
                }
            }

            // Create new unit instances in each of these pages/components

            foreach ($homepages as $homepage) {

                /* Use same derivation as previous step
                 */
                $allTaskDates = $this->determineTaskDates(
                    $this->getRequest()->post["unit"],
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
                            "component_id" => $this->getRequest()->post["component_id"],
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

                                    $due_date = \Alaress_Date_Format::newLocalShort($this->getRequest()->post["work"][$taskInstance->getId()]['due_date']);
                                    $late_date = \Alaress_Date_Format::newLocalShort($this->getRequest()->post["work"][$taskInstance->getId()]['late_date']);
                                    $open_date = \Alaress_Date_Format::newLocalShort($this->getRequest()->post["work"][$taskInstance->getId()]['open_date']);
                                    $publish_date = \Alaress_Date_Format::newLocalShort($this->getRequest()->post["work"][$taskInstance->getId()]['publish_date']);

                                    $submissionBox->setDueDate($due_date->endOfDay());
                                    $submissionBox->setLateDate($late_date->endOfDay());
                                    $submissionBox->setOpenDate($open_date->startOfDay());
                                    $submissionBox->setPublishDate($publish_date->startOfDay());
                                    $this->getSubmissionBoxMapper()->save($submissionBox);

                                    break;

                                case Task::TYPE_ONLINE_ASSESS:

                                    // Quizzes

                                    $assessment = $this->getOnlineAssessmentMapper()->findById($taskInstance->getAssessment()->getId());

                                    $start_date = \Alaress_Date_Format::newLocalShort($this->getRequest()->post["quiz"][$taskInstance->getId()]['start_date']);
                                    $finish_date = \Alaress_Date_Format::newLocalShort($this->getRequest()->post["quiz"][$taskInstance->getId()]['finish_date']);

                                    $assessment->setStart($start_date->startOfDay());
                                    $assessment->setFinish($finish_date->endOfDay());
                                    $this->getOnlineAssessmentMapper()->save($assessment);

                                    break;
                            }
                        }

                        $this->getSubmissionBoxMapper()->findByColumn("task_instance_id", $unit_instance->getId());

                        $unit_instance_list->add($unit_instance);

                    } catch (\Exception $e) {

                        // Adding a new unit into the course.

                        $unit_instance = new UnitInstance();

                        $unit_instance
                            ->setWorkDates($this->getRequest()->post["work"]) // Override due work dates in cloner
                            ->setQuizDates($this->getRequest()->post["quiz"]) // Override quiz dates in cloner
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
        }
        catch (\InvalidArgumentException $e)
        {
            // bad dates, most likely
            $this->throwError("Invalid date given");
        }
    }

    /**
     * Modify the Unit information for a previously imported Course
     *
     * @param $componentId
     */
    public function importModify($componentId)
    {
        $compendium = [];

        /** @var Component_Instance $compInst */
        $compInst = $this->getComponentInstanceMapper()->findById($componentId);

        /** @var Collection|UnitInstance[] $selectedUnitInstances */
        $selectedUnitInstances = $this->getUnitInstanceMapper()->findForComponent($compInst);

        foreach ($selectedUnitInstances as $selectedUnitInstance) {
            $compendium[$selectedUnitInstance->getUnitCategory()->getId()]['selected'] = $selectedUnitInstance;
        }

        /** @var Folder\Model $course */
        $course   = $this->getUnitInstanceMapper()->findCourseForUnit($selectedUnitInstances->first());

        /** @var Collection|Folder\Model[] $allUnits */
        $allUnits = $this->getCourseMapper()->findAllChildren($course->getId());

        foreach ($allUnits as $unit) {
            $compendium[$unit->getId()]['folder'] = $unit;
        }

        $unitOutput = [];

        foreach ($compendium as $id => $values) {
            $unitOutput[$id] = [
                'id' => $id,
                'name' => $values['folder']->getName(),
            ];

            if (isset($values['selected'])) {
                $unitOutput[$id]['checked'] = true;
                $unitOutput[$id]['openDate'] = $values['selected']->getOpenDate();
                $unitOutput[$id]['dueDate']  = $values['selected']->getDueDate();
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
        }

        $this->tpl('import', [
            '_part2'  => true,
            'units'   => $unitOutput,
            'folder'  => $destination,
            'sources' => $course,
            'destinations' => array_combine(['id','name'], $destination->findValueLabelPair()),
            'homepage' => $destination->getId(),
            'courseHomepage' => $course->getId(),
            'componentId' => $componentId,
        ]);
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
     * Delete unit from course
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
     * @param $folderId
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

        /* Find all the Units in this Folder; also includes
         * Units which have had their Course Outlines removed
         */
        $allCourseComponentIds = $this->getUnitInstanceMapper()->findComponentsForCourses($folderId);

        $this->tpl('management', [
            'folder' => $folder,
            'allCourses' => $allCourses,
            'allCourseComponentIds' => $allCourseComponentIds,
        ]);
    }

    /**
     * Restore a Course instance via rebuilding its component
     *
     * @param $folderId
     * @param $componentId
     */
    public function restoreCourse($folderId, $componentId)
    {
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

        $this->getResponse()->redirect->afterPost($this->getRouteIfAuthorized('course.management', ['folderId' => $folderId]));
    }

    /**
     * Delete a course by blowing away all its units
     */
    public function deleteCourse()
    {
        $courseId = $this->getRequest()->post->get('courseId');

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
    private function getCourseMapper()
    {
        return $this->courseMapper;
    }

    /**
     * Set course mapper
     *
     * @param CourseMapper $courseMapper
     */
    private function setCourseMapper($courseMapper)
    {
        $this->courseMapper = $courseMapper;
    }

    /**
     * Get folder mapper
     *
     * @return FolderMapper
     */
    private function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * Set folder mapper
     *
     * @param FolderMapper $folderMapper
     */
    private function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }

    /**
     * Get task mapper
     *
     * @return TaskMapper
     */
    private function getTaskMapper()
    {
        return $this->taskMapper;
    }

    /**
     * Set task mapper
     *
     * @param TaskMapper $taskMapper
     */
    private function setTaskMapper($taskMapper)
    {
        $this->taskMapper = $taskMapper;
    }


    /**
     * Get component instance mapper
     *
     * @return Component_InstanceMapper
     */
    private function getComponentInstanceMapper()
    {
        return $this->componentInstanceMapper;
    }

    /**
     * Set component instance mapper
     *
     * @param Component_InstanceMapper $componentInstanceMapper
     */
    private function setComponentInstanceMapper($componentInstanceMapper)
    {
        $this->componentInstanceMapper = $componentInstanceMapper;
    }

    /**
     * Get component definition mapper
     *
     * @return Component_DefinitionMapper
     */
    private function getComponentDefinitionMapper()
    {
        return $this->componentDefinitionMapper;
    }

    /**
     * Set component definition mapper
     *
     * @param Component_DefinitionMapper $componentDefinitionMapper
     */
    private function setComponentDefinitionMapper($componentDefinitionMapper)
    {
        $this->componentDefinitionMapper = $componentDefinitionMapper;
    }

    /**
     * Get unit instance mapper
     *
     * @return UnitInstanceMapper
     */
    private function getUnitInstanceMapper()
    {
        return $this->unitInstanceMapper;
    }

    /**
     * Set unit instance mapper
     *
     * @param UnitInstanceMapper $unitInstanceMapper
     */
    private function setUnitInstanceMapper($unitInstanceMapper)
    {
        $this->unitInstanceMapper = $unitInstanceMapper;
    }

    /**
     * Set task mapper
     *
     * @param SubmissionBoxMapper $submissionBoxMapper
     */
    private function setSubmissionBoxMapper($submissionBoxMapper)
    {
        $this->submissionBoxMapper = $submissionBoxMapper;
    }

    /**
     * Get submission box mapper
     *
     * @return SubmissionBoxMapper
     */
    private function getSubmissionBoxMapper()
    {
        return $this->submissionBoxMapper;
    }

    /**
     * Get online assessment mapper
     *
     * @return OnlineAssessmentMapper
     */
    private function getOnlineAssessmentMapper()
    {
        return $this->onlineAssessmentMapper;
    }

    /**
     * Set online assessment mapper
     *
     * @param OnlineAssessmentMapper $onlineAssessmentMapper
     */
    private function setOnlineAssessmentMapper($onlineAssessmentMapper)
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
    public function getCourseConfigMapper()
    {
        return $this->courseConfigMapper;
    }

    /**
     * @param  CourseConfigMapper $courseConfigMapper
     * @return $this
     */
    public function setCourseConfigMapper($courseConfigMapper)
    {
        $this->courseConfigMapper = $courseConfigMapper;
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
    public function getTaskInstanceMapper()
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
}
