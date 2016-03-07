<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Course;

use Alaress\Web\Controller\Base as BaseController;

use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Resource\Browser\AbstractTreeBuilder;
use Schoolbox\Resource\Browser\Resources\TreeBuilder;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Assessment\Course\Mapper as CourseMapper;

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
     */
    public function __construct()
    {
        $this->setCourseMapper(CourseMapper::getInstance());
        $this->setFolderMapper(FolderMapper::getInstance());
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
            'data'     => $data,
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

        $tb = new TreeBuilder(Folder\Mapper::getInstance(), TaskMapper::getInstance(), UserAccessMapper::getInstance(), RoleAccessMapper::getInstance(), $this->getUser());

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
     * Get a list of all the Folders which should
     * receive the selected Course
     */
    public function importClasses()
    {
        $data = [];
        $allFolders = $this->getFolderMapper()->findByName($this->getRequest()->query->get('query'));

        /* Swap keys for selectize compatibility -_-
         */
        foreach ($allFolders as $currentFolder) {
            $data[] = [
                'id' => $currentFolder->getId(),
                'name' => $currentFolder->getName()
            ];
        }

        echo json_encode($data);
        exit();
    }

    /**
     * Import a Course into a set of Folders (classes)
     *
     * @param $courseId
     */
    public function import($courseId, $classId)
    {
        $data = [

            /* Course Folder
             */
            'folder' => null,

            /* All Units associated with this Course
             */
            'units' => [],

            /* All Tasks associated with Units
             */
            'tasks' => [],

            /* Folder to which this Course shall be applied
             */
            'destinations' => []
        ];

        /* A course was already chosen
         */
        try {
            if ($courseId) {
                $folder = $this->getCourseMapper()->findById($courseId);
                $folder->setParent($this->getCourseMapper()->findById($folder->getParent()->getId()));

                $data['folder'] = $folder;
                $data['units']  = $this->getCourseMapper()->findByParent($courseId);

                foreach ($data['units'] as $unit) {
                    $data['tasks'][$unit->getId()] = TaskMapper::getInstance()->findByCategory($unit);
                }
            }
        }
        catch (\Exception $e) {

            /* TODO: No course found
             */
        }

        /* A class was already chosen
         */
        try {
            if ($classId) {
                $folder = $this->getFolderMapper()->findById($classId);
                $pair = $folder->findValueLabelPair();

                $data['destinations'] = [
                    'id' => $pair['value'],
                    'name' => $pair['label'],
                ];
            }
        }
        catch (\Exception $e) {

            /* TODO: No destination found
             */
        }

        $this->tpl('import', $data);
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
}
