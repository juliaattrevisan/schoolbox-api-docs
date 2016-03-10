<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Alaress\Web\Controller\Base as BaseController;

use OutOfBoundsException;
use RangeException;
use Schoolbox\Curriculum;
use Schoolbox\Collection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Browser\AbstractTreeBuilder;
use Schoolbox\Resource\Browser\Resources\TreeBuilder;
use Schoolbox\Resource\Folder\Code;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Assessment\Course\Mapper as CourseMapper;
use Schoolbox\Assessment\Course\Import\Import as CourseImport;
use Schoolbox\Resource\HomepageConfigMapper;
use Schoolbox\Resource\HomepageTemplate;
use Schoolbox\Resource\HomepageTemplateMapper;

class Controller extends BaseController
{
    /**
     * A CourseMapper instance
     *
     * @var CourseMapper
     */
    private $courseMapper = null;

    /**
     * A FolderMapper instance
     *
     * @var FolderMapper
     */
    private $folderMapper = null;

    /**
     * A TaskMapper instance
     *
     * @var null
     */
    private $taskMapper = null;

    /**
     */
    public function __construct()
    {
        $this->setCourseMapper(CourseMapper::getInstance());
        $this->setFolderMapper(FolderMapper::getInstance());
        $this->setTaskMapper(TaskMapper::getInstance());
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
     */
    public function modifyCourse($id = null)
    {
        $data = [];

        if ($id) {
            $folder = $this->getCourseMapper()->findById($id);
            $folder->setParent($this->getCourseMapper()->findById($folder->getParent()->getId()));

            $data['folder'] = $folder;
            $data['units']  = $this->getCourseMapper()->findByParent($id);

            foreach ($data['units'] as $unit) {
                $data['tasks'][$unit->getId()] = TaskMapper::getInstance()->findByCategory($unit);
            }
        }

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
        $this->modifyCourse(null);
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

        // TODO get permission and template configs
        try {
            $folder = $this->saveAFolderAndAllItsParts([
                // the basics
                'categoryID'      => $id,
                'name'            => $name,
                'parent'          => $parent->getId(),
                'curriculumCodes' => $codes,

                // configurable things
                'inherit'         => true,  // TODO config inheritance
                'template'        => -1,    // TODO config template
                'group'           => [],    // TODO config role permissions [ $roleId => $permissionLevel ]

                // leave these always off
                'recursive'       => false, // LEAVE THIS
                'code'            => null,  // LEAVE THIS

            ]);

            $this->getResponse()->content->set([
                'format' => 'json',
                'data'   => [ 'id' => $folder->getId() ]
            ]);

            return;

        } catch (\Exception $e) {
            // whatever the exception, recover from it as best you can
            $this->sendError("Could not save because " . $e->getMessage());
        }

    }


    /**
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

        $tb = new TreeBuilder(Folder\Mapper::getInstance(), TaskMapper::getInstance(), $this->getUser());

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
     * This is public for now because /cms/categoryForm.php needs to reference it
     *
     * @param array $postData       the array structure we'll use to construct this folder
     * @throws OutOfBoundsException if you lack permission to perform functions
     * @throws RangeException       if the database fails to save your folder
     * @return Folder\Model         representing the freshly saved folder
     */
    public function saveAFolderAndAllItsParts(array $postData)
    {
        $model = $this->getCourseMapper()->createModel()
                              ->setName($postData['name'])
                              ->setParent(
                                  $this->getCourseMapper()->createModel()
                                               ->setId($postData['parent'])
                              );

        if ($postData["inherit"] == 1) {
            $model->enableInherit();
        } else {
            $model->disableInherit();
        }

        // Make sure the user has permission to perform the action
        if (is_numeric($postData['categoryID'])) {
            // check this user has write access
            if (Access::getInstance()->getFolderAccess($this->getUser(), [ $postData['categoryID'] ]) < UserAccess::ACCESS_WRITE) {
                throw new OutOfBoundsException("I find your lack of access disturbing.");
            }
            $model->setId($postData['categoryID']);

        } elseif (Access::getInstance()->getFolderAccess($this->getUser(), [ $postData['parent'] ]) < UserAccess::ACCESS_WRITE) {
            // check this user has access to the parent folder
            throw new OutOfBoundsException("I find your lack of access disturbing.");
        }

        if ($postData['inherit'] == 1) {

            // If inheriting permissions we need to remove any existing class codes...
            Code\Mapper::getInstance()->deleteByColumn('categoryID', $postData['categoryID']);

            // ...and user access permissions...
            UserAccessMapper::getInstance()->deleteByColumn("sid", $postData["categoryID"]);

            // ...and role permissions
            RoleAccessMapper::getInstance()->deleteByColumn("cid", $postData["categoryID"]);

        } else {
            // apply codes
            $this->setCodesOnFolder($model, $postData['code'], $postData['curriculumCodes']);
        }

        $affectedIds = $this->getCourseMapper()->save($model);

        if ($affectedIds === false) {
            // something went wrong
            throw new RangeException("Failed to save folder.");
        }

        if ($postData["inherit"] == 0) {
            // only allow recursive permissions to supers
            $recursive = $this->getUser()->isSuperuser() && $postData['recursive'];

            // If rolepermission collection is defined, reapply permissions (possibly recursively)
            RoleAccessMapper::getInstance()->savePermissions($model, $postData['group'], $recursive);
        }

        // Recurse inheritance
        $this->recurseInheritance($model);

        // HomepageConfig definition
        $this->saveHomepageConfig($model, $postData['template']);

        return $model;
    }


    /**
     * Put the given code lists onto the given folder
     *
     * @param Folder\Model $model
     * @param string       $classCodeString
     * @param array        $curriculumCodes
     * @throws Mapper\Exception
     */
    protected function setCodesOnFolder(Folder\Model $model, $classCodeString, array $curriculumCodes)
    {
        $codeCollection = Code\Mapper::getInstance()->createCollection();

        // Only superusers get to change class codes
        if ($this->getUser()->isSuperuser()) {
            foreach (explode(',', $classCodeString) as $code) {
                $trimmedCode = trim($code);
                if (strlen($trimmedCode)) {
                    $codeModel = Code\Mapper::getInstance()->createModel();
                    $codeModel->setCode($trimmedCode);
                    $codeCollection->add($codeModel);
                }
            }
            $model->setCodes($codeCollection);
        }

        // Delete category codes here, allowing them to be recreated when the category gets saved.
        // (I'm not confident that deleting cat codes in the postSave method
        //  regardless of whether there are any won't break somewhere else)
        if ($this->getUser()->isSuperuser() && $model->getId() && count($codeCollection) === 0) {
            Code\Mapper::getInstance()->deleteByColumn('categoryID', $model->getId());
        }

        // Only privileged users (teachers?) get to change curriculum codes?
        if (isset($curriculumCodes) && count($curriculumCodes)) {
            $model->setCurriculumNodes(
                Curriculum\Node\Mapper::getInstance()->findByColumn('id', $curriculumCodes)
            );

        } elseif ($model->getId()) {
            $existingModel = $this->getCourseMapper()->findById($model->getId());
            $model->setCurriculumNodes($existingModel->getCurriculumNodes());

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
        $confMapper = HomepageConfigMapper::getInstance();
        $tplMapper  = HomepageTemplateMapper::getInstance();

        $homepageConfig = $confMapper->createModel()->setCategory($model);

        // if homepage is to be removed
        try {
            if ($template == HomepageTemplate::HOMEPAGE_REMOVE) {
                $confMapper->emptyHomepage($homepageConfig);
                $confMapper->delete($homepageConfig);

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
     * Retrieve fragment with units in the selected course, and checkboxes/dates for selection
     *
     */
    public function importUnits()
    {
        $homepageId       = trim($this->getRequest()->post["importFolders"]);
        $courseHomepageId = trim($this->getRequest()->post["importCourse"]);

        try {
            $this->getCourseMapper()->findCourseById($courseHomepageId);

        } catch (MapperException $e) {
            // the nominated "course" is not actually a course...
            $this->throwError('The selected source is not a course');
            return;
        }

        if ($homepageId == "" && $courseHomepageId == "")
        {
            // They didn't select anything

            $this->throwError('You must select at least one class and a course to import before you can proceed');
        }
        else if ($homepageId == "")
        {
            // They didn't select a class code

            $this->throwError('You must select at least one class before you can proceed');
        }
        else if ($courseHomepageId == "")
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
                    'homepageId' => $homepageId,
                    'courseHomepageId' => $courseHomepageId,
                    'units' => $this->getCourseMapper()->findByParentId($courseHomepageId)
                ]
            ]);
        }
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

    /**
     * Imports a course into a list of homepages
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
        else {

            $homepages = explode(",", $this->getRequest()->post["homepage"]);

            // Test permissions. Must have write permission on the destination page and read permission
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

            // Check all required dates have been specified

            $error = false;
            $foundUnit = false;

            foreach ($this->getRequest()->post["unit"] as $unit) {
                $import = !empty($unit['import']);

                if ($import) {
                    $foundUnit = true;
                    if (trim($unit['open_date']) == '' || trim($unit['due_date']) == '') {
                        $this->throwError('You must specify an open and close date for each unit you are importing');
                        $error = true;
                        break;
                    }
                }
            }

            // Make sure at least one unit was specified

            if (!$foundUnit) {
                $this->throwError('You must select at least one unit to import');
            }
            else
            {
                // Create the component on each homepage

                $unitCache = [];
                $homepageCache = [];
                $componentCache = [];

                foreach ($homepages as $homepageId) {
                    // Get the homepage we are importing into and store it for later

                    $homepageCache[$homepageId] = FolderMapper::getInstance()->findById($homepageId);

                    $componentDefinition = \Component_DefinitionMapper::getInstance()->findByColumn('class',
                        'Component_Homepage_CourseOutlineController');
                    $componentDefinitionId = $componentDefinition->first()->getId();

                    $componentCache[$homepageId] = new \Component_Instance();
                    $componentCache[$homepageId]
                        ->setDefinitionId($componentDefinitionId)
                        ->setModule('homepage')
                        ->setIdentifier($homepageId)
                        ->setPosition('top')
                        ->setSequence(1);

                    \Component_InstanceMapper::getInstance()->save($componentCache[$homepageId]);
                }

                if ($error) {
                    return;
                }
                try {
                    // Create new unit instances on each homepage

                    foreach ($homepages as $homepageId) {
                        $unit_instance_list = new Collection();

                        foreach ($this->getRequest()->post["unit"] as $unit) {
                            $import = !empty($unit['import']);

                            if ($import) {
                                // Save the unit into the course.

                                if (!isset($unitCache[$unit['unit_id']])) {
                                    $unitCache[$unit['unit_id']] = FolderMapper::getInstance()->findById($unit['unit_id']);
                                }
                                $unit_instance = new UnitInstance();

                                $unit_instance
                                    ->setComponent($componentCache[$homepageId])
                                    ->setTitle($unitCache[$unit['unit_id']]->getName())
                                    ->setCategory($homepageCache[$homepageId])
                                    ->setUnitCategory($unitCache[$unit['unit_id']])
                                    ->setOpenDate(\Alaress_Date_Format::newLocalShort($unit['open_date']))
                                    ->setDueDate(\Alaress_Date_Format::newLocalShort($unit['due_date']));

                                $unit_instance_list->add($unit_instance);
                            }
                        }

                        UnitInstanceMapper::getInstance()->save($unit_instance_list);
                    }

                    // Return the next fragment...

                    $this->getResponse()->content->set([
                        'format' => 'file',
                        'data' => 'Insert next fragment here'
                    ]);

                } catch (\InvalidArgumentException $e) {
                    // bad dates, most likely
                    $this->throwError("Invalid date given");
                }
            }
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

}
