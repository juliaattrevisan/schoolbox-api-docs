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
use Schoolbox\RoleMapper;
use Schoolbox\Resource\HomepageTemplateMapper;

class Controller extends Base
{
    const INHERIT_ON = 1;
    const INHERIT_OFF = 0;

    /**
     * A CourseConfigMapper instance
     *
     * @var CourseConfigMapper
     */
    private $courseConfigMapper;

    /**
     * A HomepageTemplateMapper instance
     *
     * @var HomepageTemplateMapper
     */
    private $homepageTemplateMapper;

    /**
     * A RoleMapper instance
     *
     * @var RoleMapper
     */
    private $roleMapper;

    /**
     * A ConfigMapper instance
     *
     * @var ConfigMapper
     */
    private $configMapper;

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        $this->setCourseConfigMapper(CourseConfigMapper::getInstance());
        $this->setHomepageTemplateMapper(HomepageTemplateMapper::getInstance());
        $this->setRoleMapper(RoleMapper::getInstance());
        $this->setConfigMapper(ConfigMapper::getInstance());
    }

    /**
     * Show options
     *
     * @param string $tab
     */
    public function getIndex($tab = '')
    {

        // Get default permissions for course and unit pages

        $permissions = [];
        $permissions['course'] = [];
        $permissions['unit'] = [];

        $coursePermissions = $this->getCourseConfigMapper()->findByColumn("page", "course");

        foreach ($coursePermissions as $permission) {
            $permissions['course'][$permission->getRoleId()] = [];
            $permissions['course'][$permission->getRoleId()][$permission->getPermission()] = 'checked';
        }

        $unitPermissions = $this->getCourseConfigMapper()->findByColumn("page", "unit");

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
                    'roles' => $this->getRoleMapper()->findAll(),
                    'permissions' => $permissions,
                    'templates' => $this->getHomepageTemplateMapper()->findAll()->sortBy("name", "asc"),
                    'courseTemplate' => $this->getConfigMapper()->get('course_builder_default_course_page_template'),
                    'unitTemplate' => $this->getConfigMapper()->get('course_builder_default_unit_page_template'),
                    'courseInherit' => $this->getConfigMapper()->get('course_builder_default_course_page_inherit'),
                    'unitInherit' => $this->getConfigMapper()->get('course_builder_default_unit_page_inherit')
                ),
            )
        );
    }

    /**
     * Apply Role perms and template selection after saving configuration settings
     *
     * @throws \Schoolbox\Mapper\Exception
     */
    public function postSave()
    {
        // Blow away old permissions, could probably be more graceful and update

        $this->getCourseConfigMapper()->deleteByColumn("page", "course");
        $this->getCourseConfigMapper()->deleteByColumn("page", "unit");

        // Add course permissions back

        foreach ($this->getRequest()->post["course_group"] as $roleId => $permission) {
            $model = $this->getCourseConfigMapper()->createModel();
            $model->setPage("course");
            $model->setPermission($permission);
            $model->setRoleId($roleId);
            $this->getCourseConfigMapper()->save($model);
        }

        // Add unit permissions back

        foreach ($this->getRequest()->post["unit_group"] as $roleId => $permission) {
            $model = $this->getCourseConfigMapper()->createModel();
            $model->setPage("unit");
            $model->setPermission($permission);
            $model->setRoleId($roleId);
            $this->getCourseConfigMapper()->save($model);
        }

        // Update default page templates

        $courseTemplate = $this->getConfigMapper()->findOneByColumn("name", "course_builder_default_course_page_template");
        $courseTemplate->setValue($this->getRequest()->post["courseTemplate"]);
        $this->getConfigMapper()->save($courseTemplate);

        $unitTemplate = $this->getConfigMapper()->findOneByColumn("name", "course_builder_default_unit_page_template");
        $unitTemplate->setValue($this->getRequest()->post["unitTemplate"]);
        $this->getConfigMapper()->save($unitTemplate);

        // Update default inheritance

        $courseTemplate = $this->getConfigMapper()->findOneByColumn("name", "course_builder_default_course_page_inherit");
        $courseTemplate->setValue($this->selectInheritance($this->getRequest()->post["courseInherit"]));
        $this->getConfigMapper()->save($courseTemplate);

        $unitTemplate = $this->getConfigMapper()->findOneByColumn("name", "course_builder_default_unit_page_inherit");
        $unitTemplate->setValue($this->selectInheritance($this->getRequest()->post["unitInherit"]));

        $this->getConfigMapper()->save($unitTemplate);

        // Send them back to the admin page

        $this->getResponse()->redirect->afterPost('/adminv2/course-builder/' .$this->getRequest()->post["tab"]);
    }

    /**
     * Decide whether inheritance we selected
     *
     * @param $value
     * @return int
     */
    private function selectInheritance($value)
    {
        if ($value == 1) {
            return self::INHERIT_ON;
        }

        return self::INHERIT_OFF;
    }

    /* Getters/Setters
     */

    /**
     * Get course config mapper
     *
     * @return CourseConfigMapper
     */
    private function getCourseConfigMapper()
    {
        return $this->courseConfigMapper;
    }

    /**
     * Set course config mapper
     * 
     * @param CourseConfigMapper $courseConfigMapper
     */
    private function setCourseConfigMapper($courseConfigMapper)
    {
        $this->courseConfigMapper = $courseConfigMapper;
    }

    /**
     * Get homepage template mapper
     *
     * @return HomepageTemplateMapper
     */
    private function getHomepageTemplateMapper()
    {
        return $this->homepageTemplateMapper;
    }

    /**
     * Set homepage template mapper
     *
     * @param HomepageTemplateMapper $homepageTemplateMapper
     */
    private function setHomepageTemplateMapper($homepageTemplateMapper)
    {
        $this->homepageTemplateMapper = $homepageTemplateMapper;
    }

    /**
     * Get Role Mapper
     *
     * @return RoleMapper
     */
    private function getRoleMapper()
    {
        return $this->roleMapper;
    }

    /**
     * Set Role Mapper
     *
     * @param RoleMapper $roleMapper
     */
    private function setRoleMapper($roleMapper)
    {
        $this->roleMapper = $roleMapper;
    }

    /**
     * Get Config Mapper
     *
     * @return ConfigMapper
     */
    private function getConfigMapper()
    {
        return $this->configMapper;
    }

    /**
     * Set Config Mapper
     *
     * @param ConfigMapper $configMapper
     */
    private function setConfigMapper($configMapper)
    {
        $this->configMapper = $configMapper;
    }
}
