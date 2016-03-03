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
use Schoolbox\Collection;
use Schoolbox\Assessment\Course\Mapper as CourseMapper;

class Controller extends BaseController
{
    /** @var Folder\Mapper */
    protected $folderMap;

    /** @var Folder\Model */
    protected $facultyTop;


    /**
     */
    public function __construct()
    {
        $this->folderMap = Folder\Mapper::getInstance();

        // don't search lineage back beyond here
        // TODO look this up from config
        $this->facultyTop = $this->folderMap->findById(29);

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
            $folder = $this->folderMap->findById($id);
            $folder->setParent($this->folderMap->findById($folder->getParent()->getId()));

            $data['folder'] = $folder;
            $data['units']  = $this->folderMap->findByParent($id);
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
     */
    public function courseList() {
        $courseMapper = new CourseMapper();
        $this->tpl('courseList', $courseMapper->findCourseList());
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
        //  - start at facultyTop,
        //  - recurse into every folder for which the user has WRITE access
        //  - stop at, and exclude, all Courses (folder that has > 0 children with > 0 tasks) (apparently this point is up for debate..?)
        //  - continue to exhaustion

        $tb = new TreeBuilder(Folder\Mapper::getInstance(), TaskMapper::getInstance(), UserAccessMapper::getInstance(), RoleAccessMapper::getInstance(), $this->getUser());

        $data = [
            'id'   => $this->facultyTop->getId(),
            'text' => $this->facultyTop->getName()
        ];

        // recurse, passing $data by reference
        $data = $this->recursiveTreeBuild($tb, $data);

        $data = [
            'branch' => $data
        ];

        $this->tpl('parentList', $data);
    }



}
