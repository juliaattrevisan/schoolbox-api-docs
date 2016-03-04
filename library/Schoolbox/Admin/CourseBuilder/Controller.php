<?php
/**
 * Course builder admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Admin\CourseBuilder;

use Alaress\Web\Controller\Base;
use Schoolbox\User;
use Schoolbox\Resource\Storage;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Assessment\Course\Config\Model as CourseConfigModel;
use Schoolbox\RoleMapper;
use \Schoolbox\Admin\Navigation as AdminNavigation;

class Controller extends Base
{
    public function getIndex()
    {
        // Get default permissions for course and unit pages

        $permissions = [];
        $permissions['course'] = [];
        $permissions['unit'] = [];

        $coursePermissions = CourseConfigMapper::getInstance()->findByColumn("page", "course");

        foreach ($coursePermissions as $permission) {
            $permissions['course'][$permission->getRoleId()] = [];
            $permissions['course'][$permission->getRoleId()][$permission->getPermission()] = 'checked';
        }

        $unitPermissions = CourseConfigMapper::getInstance()->findByColumn("page", "unit");

        foreach ($unitPermissions as $permission) {
            $permissions['unit'][$permission->getRoleId()] = [];
            $permissions['unit'][$permission->getRoleId()][$permission->getPermission()] = 'checked';
        }

        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminCourseBuilder/index.twig',
                'data'     => array(
                    'navigation' => AdminNavigation::getNavigation(),
                    'tab' => $_GET["tab"] != '' ? $_GET["tab"] : 'course',
                    'roles' => RoleMapper::getInstance()->findAll(),
                    'permissions' => $permissions,
                    'courseTemplate' => ConfigMapper::getInstance()->get('default_course_page_template'),
                    'unitTemplate' => ConfigMapper::getInstance()->get('default_unit_page_template')
                ),
            )
        );
    }

    public function postSave()
    {
        // Blow away old permissions, could probably be more graceful and update

        CourseConfigMapper::getInstance()->deleteByColumn("page", "course");
        CourseConfigMapper::getInstance()->deleteByColumn("page", "unit");

        // Add course permissions back

        foreach ($_POST["course_group"] as $roleId => $permission) {
            $model = new CourseConfigModel();
            $model->setPage("course");
            $model->setPermission($permission);
            $model->setRoleId($roleId);
            CourseConfigMapper::getInstance()->save($model);
        }

        // Add unit permissions back

        foreach ($_POST["unit_group"] as $roleId => $permission) {
            $model = new CourseConfigModel();
            $model->setPage("unit");
            $model->setPermission($permission);
            $model->setRoleId($roleId);
            CourseConfigMapper::getInstance()->save($model);
        }

        // Update default page templates

        $courseTemplate = ConfigMapper::getInstance()->findOneByColumn("name", "default_course_page_template");
        $courseTemplate->setValue($_POST["template_course"]);
        ConfigMapper::getInstance()->save($courseTemplate);

        $unitTemplate = ConfigMapper::getInstance()->findOneByColumn("name", "default_unit_page_template");
        $unitTemplate->setValue($_POST["template_unit"]);
        ConfigMapper::getInstance()->save($unitTemplate);

        // Send them back to the admin page

        $this->getResponse()->redirect->afterPost('/adminv2/course-builder/?tab=' .$_POST["tab"]);
    }
}
