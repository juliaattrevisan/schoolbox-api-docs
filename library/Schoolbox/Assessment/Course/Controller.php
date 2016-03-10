<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Alaress\Web\Controller\Base as BaseController;

use Schoolbox\Collection;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Browser\AbstractTreeBuilder;
use Schoolbox\Resource\Browser\Resources\TreeBuilder;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Assessment\Course\Mapper as CourseMapper;
use Schoolbox\Assessment\Course\Import\Import as CourseImport;
use Schoolbox\Permission\Access\Access;

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
     * Post request to spawn a new unit
     *
     * @api   /course/unit/new
     * @level 160
     */
    public function newUnit()
    {
        $post = $this->getRequest()->post;

        $name  = $post->get('name');
        $index = $post->get('i', 1);

        // TODO persist new folder to DB, including course builder template

        $data = [
            'unit'  => $name,
            'i'     => $index,
            'icons' => [ 'task', 'due work', 'quiz' ]
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
     * @api   /course/parent
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
     * Retrieve fragment with units in the selected course, and checkboxes/dates for selection
     *
     */
    public function importUnits()
    {
        $homepageId = $this->getRequest()->post["importFolders"];
        $courseHomepageId = $this->getRequest()->post["importCourse"];

        $folderMapper = FolderMapper::getInstance();

        $this->getResponse()->content->set([
            'template' => "@course/unitImportForm.twig",
            'format' => 'nochrome',
            'data' => [
                'homepageId'        => $homepageId,
                'courseHomepageId'  => $courseHomepageId,
                'units'             => $folderMapper->findByParentId($courseHomepageId)
            ]
        ]);
    }

    /**
     * Import a course into a component
     *
     * @throws \Alaress\Authorizer\Failure\Unauthorized
     **/
    public function importUnit()
    {
        // Test permissions. Must have write permission on the destination page and read permission
        // on the course we are trying to import.

        if (Access::getInstance()->getFolderAccess($this->getUser(), [$this->getRequest()->post["homepage"]]) < WRITE_ACCESS ||
            Access::getInstance()->getFolderAccess($this->getUser(), [$this->getRequest()->post["course_homepage"]]) < READ_ACCESS)
        {
            throw new \Alaress\Authorizer\Failure\Unauthorized;
        }

        // Get the homepage we are importing into

        $homepage = FolderMapper::getInstance()->findById($this->getRequest()->post["homepage"]);

        // Create the component

        $componentDefinition   = \Component_DefinitionMapper::getInstance()->findByColumn('class', 'Component_Homepage_CourseOutlineController');
        $componentDefinitionId = $componentDefinition->first()->getId();

        $component = new \Component_Instance();
        $component
            ->setDefinitionId($componentDefinitionId)
            ->setModule('homepage')
            ->setIdentifier($homepage->getId())
            ->setPosition('top')
            ->setSequence(1);

        \Component_InstanceMapper::getInstance()->save($component);

        // And create a new unit instance for each one selected by the user selected

        $unit_instance_list = new Collection();

        foreach ($this->getRequest()->post["unit"] as $unit)
        {
            $import = !empty($unit['import']);

            if ($import)
            {
                // Save the unit into the course.

                $unit_homepage = FolderMapper::getInstance()->findById($unit['unit_id']);
                $unit_instance = new UnitInstance();

                $unit_instance
                    ->setComponent($component)
                    ->setTitle($unit_homepage->getName())
                    ->setCategory($homepage)
                    ->setUnitCategory($unit_homepage)
                    ->setOpenDate(\Alaress_Date_Format::newLocalShort($unit['open_date']))
                    ->setDueDate(\Alaress_Date_Format::newLocalShort($unit['due_date']));

                $unit_instance_list->add($unit_instance);
            }
        }

        UnitInstanceMapper::getInstance()->save($unit_instance_list);

        // Not quite sure what to do here, probably going to be an XHR request and going to be used by the mage.
        // Currently just sending back to homepage for testing.

        $this->getResponse()->redirect->afterPost("/homepage/{$this->getRequest()->post["homepage"]}");
    }
}
