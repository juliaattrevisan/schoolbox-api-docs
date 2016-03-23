<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

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
use Schoolbox\Mapper;
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

class Controller extends BaseController
{

    /** @var FolderMapper */
    protected $folderMapper = null;

    /** @var SubmissionBoxMapper */
    protected $submissionBoxMapper = null;

    /** @var OnlineAssessmentMapper */
    protected $onlineAssessmentMapper = null;

    /** @var TaskMapper */
    protected $taskMapper = null;

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

            $data['folder'] = $folder;
            $data['units']  = $this->getCourseMapper()->findByParent($id);

            foreach ($data['units'] as $unit) {
                $data['tasks'][$unit->getId()] = $this->getTaskMapper()->findByCategory($unit);
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
                throw new Mapper\Exception("Invalid parent nominated");
            }


        } catch (Mapper\Exception $e) {

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

            } catch (Mapper\Exception $e) {
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
     * Post request to spawn a new unit
     *
     * @api   POST /course/unit/new
     * @level 160
     */
    public function newUnit()
    {
        $post = $this->getRequest()->post;

        $name  = trim($post->get('name'));
        $index = $post->get('i', 1);

        if (!$name) {
            $this->sendError('Could not create unit', [ 'give the unit a name' ]);
            return;
        }

        // TODO persist new folder to DB, including course builder template

        $data = [
            'unit'  => [
                'id'   => 'unknown',
                'name' => $name
            ],
            'i'     => $index,
            'icons' => [ 'task', 'due work', 'quiz' ],
            '_writable' => true,
            '_expanded' => true
        ];
        $this->tpl('unit', $data);
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
     * Retrieve friend with a list of tasks, quizzes and due work (and relevant date entry boxes) associated with
     * the selected course
     *
     * @throws \Alaress\Authorizer\Failure\Unauthorized
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
                throw new Failure\Unauthorized();
            }

            // Test each homepage for write access

            foreach ($homepages as $homepage) {
                if ($this->getAccess()->getFolderAccess($this->getUser(), [$homepage]) < WRITE_ACCESS) {
                    throw new Failure\Unauthorized();
                }
            }

            // Check all required dates have been specified

            $errorDate = false;
            $foundUnit = false;

            foreach ($this->getRequest()->post["unit"] as $unit) {
                if (!empty($unit['import'])) {
                    $foundUnit = true;
                    if (trim($unit['open_date']) == '' || trim($unit['due_date']) == '') {
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
                $this->throwError('You must specify an open and close date for each unit you are importing');
            }
            else {

                /** @var Category[] $unitCache */
                $unitCache = [];

                /* Make a local cache for unit information
                 */
                foreach ($this->getRequest()->post["unit"] as $unit) {
                    if (!empty($unit['import']) && !isset($unitCache[$unit['unit_id']])) {
                        $unitCache[$unit['unit_id']] = $this->getCourseMapper()->findById($unit['unit_id']);
                    }
                }

                // Find all tasks, due work, and quizzes associated with the selected units.

                $units = new Collection();

                foreach ($unitCache as $key => $unitCurrent)
                {
                    $unit = [];
                    $unit['id'] = $unitCurrent->getId();
                    $unit['name'] = $unitCache[$unitCurrent->getId()]->getName();
                    $unit['items'] = new Collection();

                    $temp = new Collection();

                    foreach ($this->getRequest()->post["unit"] as $unitDate) {
                        if ($unitDate["unit_id"] == $unitCurrent->getId()) {
                            $unit['open_date'] = $unitDate['open_date'];
                            $unit['due_date'] = $unitDate['due_date'];
                        }
                    }

                    // Tasks

                    if (!isset($taskCache[$unitCurrent->getId()])) {
                        $taskCache[$unitCurrent->getId()] = $this->taskMapper
                            ->findByColumnList([
                                'category_id' => $unitCache[$unitCurrent->getId()]->getId(),
                                'assess_id' => null
                            ])
                            ->sortBy('title', 'asc') ;
                    }

                    $temp->merge($taskCache[$unitCurrent->getId()]);

                    // Due work

                    if (!isset($submissionCache[$unitCurrent->getId()])) {
                        $submissionCache[$unitCurrent->getId()] = $this->submissionBoxMapper
                            ->findByColumn('category', $unitCurrent->getId());
                    }

                    $temp->merge($submissionCache[$unitCurrent->getId()]);

                    // Quizzes

                    if (!isset($quizCache[$unitCurrent->getId()])) {
                        $quizCache[$unitCurrent->getId()] = $this->onlineAssessmentMapper
                            ->findByColumn('category_id', $unitCurrent->getId());
                    }

                    $temp->merge($quizCache[$unitCurrent->getId()]);

                    /* Unify all three of Tasks, Due Work and Quizzes
                     * into one mega list
                     *
                     * Sort by Created At for now, until manual sequencing
                     * is added to the ui so teachers can sort it themselves
                     */
                    $temp->sortBy('created', 'asc');

                    foreach($temp as $current) {
                        $reflection = new \ReflectionClass($current);
                        $class = $reflection->getShortName();

                        $unit['items']->add([
                            'class' => $class,
                            'item' => $current
                        ]);
                    }

                    $units->add($unit);
                }

                $this->getResponse()->content->set
                ([
                    'template' => "@course/unitImportResults.twig",
                    'format' => 'nochrome',
                    'data' => [
                        'homepage' => $this->getRequest()->post["homepage"],
                        'courseHomepage' => $this->getRequest()->post["course_homepage"],
                        'unitDates' => $this->getRequest()->post["unit"],
                        'units' => $units
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

        foreach ($this->getRequest()->post["work"] as $work) {
            if (trim($work['publish_date']) == '') {
                $this->throwError('You must enter a publish date for each due work item before you can proceed.');
                return;
            } else if (trim($work['due_date']) == '') {
                $this->throwError('You must enter a due date for each due work item before you can proceed.');
                return;
            }

            // Accepting submissions, must have the other two dates

            if ($work['submissions'] == '1') {
                if (trim($work['open_date']) == '') {
                    $this->throwError('You must enter an open date for each due work item that accepts submissions before you can proceed.');
                    return;
                } else if (trim($work['late_date']) == '') {
                    $this->throwError('You must enter a late date for each due work item that accepts submissions before you can proceed.');
                    return;
                }
            }
        }

        // Validate quizzes

        foreach ($this->getRequest()->post["quiz"] as $quiz) {

            if (trim($quiz['start_date']) == '') {
                $this->throwError('You must enter a start date for each quiz before you can proceed.');
                return;
            } else if (trim($quiz['finish_date']) == '') {
                $this->throwError('You must enter a finish date for each quiz before you can proceed.');
                return;
            }
        }

        // Parse homepages out of CSV format into an array

        $homepages = explode(",", $this->getRequest()->post["homepage"]);

        // Test permissions for each homepage. Must have write permission on the destination page and read permission
        // on the course we are trying to import.

        // Test course page read access

        if (Access::getInstance()->getFolderAccess($this->getUser(), [$this->getRequest()->post["course_homepage"]]) < READ_ACCESS) {
            throw new \Alaress\Authorizer\Failure\Unauthorized;
        }

        // Test each homepage for write access

        foreach ($homepages as $homepage) {
            if (Access::getInstance()->getFolderAccess($this->getUser(), [$homepage]) < WRITE_ACCESS) {
                throw new \Alaress\Authorizer\Failure\Unauthorized;
            }
        }

        // Keep a cache of queried data

        /** @var Category[] $unitCache */
        $unitCache = [];

        /** @var Category[] $homepageCache */
        $homepageCache = [];

        /** @var Component_Instance[] $componentCache */
        $componentCache = [];

        // Create course outline component on each homepage

        try {

            foreach ($homepages as $homepage) {
                // Get the homepage we are importing into and store it for later

                $homepageCache[$homepage] = $this->getFolderMapper()->findById($homepage);

                // Create course outline component on the current page

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

            // Create new unit instances in each of these pages/components

            foreach ($homepages as $homepage) {
                $unit_instance_list = new Collection();

                foreach ($this->getRequest()->post["unit"] as $unit) {
                    $import = !empty($unit['import']);

                    if ($import) {
                        // Save the unit into the course.

                        try {
                            if (!isset($unitCache[$unit['unit_id']])) {
                                $unitCache[$unit['unit_id']] = $this->getFolderMapper()->findById($unit['unit_id']);
                            }
                        } catch (\Exception $e) {
                            $this->throwError('An unexpected error occurred');
                            return;
                        }

                        if (isset($unitCache[$unit['unit_id']])) {
                            $unit_instance = new UnitInstance();

                            $unit_instance
                                ->setWorkDates($this->getRequest()->post["work"])// Override due work dates in cloner
                                ->setQuizDates($this->getRequest()->post["quiz"])// Override quiz dates in cloner
                                ->setComponent($componentCache[$homepage])
                                ->setTitle($unitCache[$unit['unit_id']]->getName())
                                ->setCategory($homepageCache[$homepage])
                                ->setUnitCategory($unitCache[$unit['unit_id']])
                                ->setOpenDate(\Alaress_Date_Format::newLocalShort($unit['open_date']))
                                ->setDueDate(\Alaress_Date_Format::newLocalShort($unit['due_date']));

                            $unit_instance_list->add($unit_instance);
                        }
                    }
                }

                $this->getUnitInstanceMapper()->save($unit_instance_list);
            }
        }
        catch (\InvalidArgumentException $e)
        {
            // bad dates, most likely
            $this->throwError("Invalid date given");
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
        } catch (Mapper\Exception $e) {
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

        /* Find all the Courses assocated with this Folder, via imported Units
         */
        $allCourses = new Collection();

        $allFolders = $this->getUnitInstanceMapper()->findAllCoursesUsedInFolders([$folderId]);

        if ($allFolders->count()) {
            $allCourses = $allFolders->getOffset($folderId);
        }

        /* A list of Units which have had their Course Outlines removed
         */
        $allRestorableCourses = $this->getUnitInstanceMapper()->findRestorableCourses($folderId);

        $this->tpl('management', [
            'folder' => $folder,
            'allCourses' => $allCourses,
            'allRestorableCourses' => $allRestorableCourses,
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
     * @return Mapper
     */
    private function getCourseMapper()
    {
        return $this->courseMapper;
    }

    /**
     * Set course mapper
     *
     * @param Mapper $courseMapper
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
     * @return null
     */
    private function getTaskMapper()
    {
        return $this->taskMapper;
    }

    /**
     * Set task mapper
     *
     * @param null $taskMapper
     */
    private function setTaskMapper($taskMapper)
    {
        $this->taskMapper = $taskMapper;
    }


    /**
     * Get component instance mapper
     *
     * @return null
     */
    private function getComponentInstanceMapper()
    {
        return $this->componentInstanceMapper;
    }

    /**
     * Set component instance mapper
     *
     * @param null $componentInstanceMapper
     */
    private function setComponentInstanceMapper($componentInstanceMapper)
    {
        $this->componentInstanceMapper = $componentInstanceMapper;
    }

    /**
     * Get component definition mapper
     *
     * @return null
     */
    private function getComponentDefinitionMapper()
    {
        return $this->componentDefinitionMapper;
    }

    /**
     * Set component definition mapper
     *
     * @param null $componentDefinitionMapper
     */
    private function setComponentDefinitionMapper($componentDefinitionMapper)
    {
        $this->componentDefinitionMapper = $componentDefinitionMapper;
    }

    /**
     * Get unit instance mapper
     *
     * @return null
     */
    private function getUnitInstanceMapper()
    {
        return $this->unitInstanceMapper;
    }

    /**
     * Set unit instance mapper
     *
     * @param null $unitInstanceMapper
     */
    private function setUnitInstanceMapper($unitInstanceMapper)
    {
        $this->unitInstanceMapper = $unitInstanceMapper;
    }

    /**
     * Set task mapper
     *
     * @param null $submissionBoxMapper
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
     * @param null $onlineAssessmentMapper
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
}
