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
use Schoolbox\Resource\HomepageTemplateMapper;

class Controller extends Base
{
    public function getIndex($tab = '')
    {
        $configMapper = ConfigMapper::getInstance();
        $homepageTemplateMapper = HomepageTemplateMapper::getInstance();
        $roleMapper = RoleMapper::getInstance();
        $courseConfigMapper = CourseConfigMapper::getInstance();

        // Get default permissions for course and unit pages

        $permissions = [];
        $permissions['course'] = [];
        $permissions['unit'] = [];

        $coursePermissions = $courseConfigMapper->findByColumn("page", "course");

        foreach ($coursePermissions as $permission) {
            $permissions['course'][$permission->getRoleId()] = [];
            $permissions['course'][$permission->getRoleId()][$permission->getPermission()] = 'checked';
        }

        $unitPermissions = $courseConfigMapper->findByColumn("page", "unit");

        foreach ($unitPermissions as $permission) {
            $permissions['unit'][$permission->getRoleId()] = [];
            $permissions['unit'][$permission->getRoleId()][$permission->getPermission()] = 'checked';
        }

        // Render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@adminCourseBuilder/index.twig',
                'data'     => array(
                    'pageCurrent' => basename($this->getRequest()->query->get('page')),
                    'tab' => $tab != '' ? $tab : 'course',
                    'roles' => $roleMapper->findAll(),
                    'permissions' => $permissions,
                    'templates' => $homepageTemplateMapper->findAll()->sortBy("name", "asc"),
                    'courseTemplate' => $configMapper->get('default_course_page_template'),
                    'unitTemplate' => $configMapper->get('default_unit_page_template'),
                    'courseInherit' => $configMapper->get('default_course_page_inherit'),
                    'unitInherit' => $configMapper->get('default_unit_page_inherit')
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

        foreach ($this->getRequest()->post["course_group"] as $roleId => $permission) {
            $model = new CourseConfigModel();
            $model->setPage("course");
            $model->setPermission($permission);
            $model->setRoleId($roleId);
            CourseConfigMapper::getInstance()->save($model);
        }

        // Add unit permissions back

        foreach ($this->getRequest()->post["unit_group"] as $roleId => $permission) {
            $model = new CourseConfigModel();
            $model->setPage("unit");
            $model->setPermission($permission);
            $model->setRoleId($roleId);
            CourseConfigMapper::getInstance()->save($model);
        }

        // Update default page templates

        $courseTemplate = ConfigMapper::getInstance()->findOneByColumn("name", "default_course_page_template");
        $courseTemplate->setValue($this->getRequest()->post["courseTemplate"]);
        ConfigMapper::getInstance()->save($courseTemplate);

        $unitTemplate = ConfigMapper::getInstance()->findOneByColumn("name", "default_unit_page_template");
        $unitTemplate->setValue($this->getRequest()->post["unitTemplate"]);
        ConfigMapper::getInstance()->save($unitTemplate);

        // Update default inheritance

        $courseTemplate = ConfigMapper::getInstance()->findOneByColumn("name", "default_course_page_inherit");
        $courseTemplate->setValue($this->getRequest()->post["courseInherit"]);
        ConfigMapper::getInstance()->save($courseTemplate);

        $unitTemplate = ConfigMapper::getInstance()->findOneByColumn("name", "default_unit_page_inherit");
        $unitTemplate->setValue($this->getRequest()->post["unitInherit"]);
        ConfigMapper::getInstance()->save($unitTemplate);

        // Send them back to the admin page

        $this->getResponse()->redirect->afterPost('/adminv2/course-builder/' .$this->getRequest()->post["tab"]);
    }
}
