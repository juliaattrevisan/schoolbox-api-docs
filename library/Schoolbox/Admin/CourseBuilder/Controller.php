<?php

/**
 * Course builder admin page
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Admin\CourseBuilder;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Assessment\Course\Config\Mapper as CourseConfigMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Resource\HomepageTemplateMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Controller extends Base
{
    /**
     * A ConfigMapper instance
     *
     * @var ConfigMapper
     */
    private $configMapper;

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
     * A FolderMapper instance
     *
     * @var FolderMapper
     */
    private $folderMapper;

    /**
     * Constructor
     *
     * @param ConfigMapper           $configMapper
     * @param CourseConfigMapper     $courseConfigMapper
     * @param HomepageTemplateMapper $homepageTemplateMapper
     * @param RoleMapper             $roleMapper
     * @param FolderMapper           $folderMapper
     */
    public function __construct(
        ConfigMapper $configMapper,
        CourseConfigMapper $courseConfigMapper,
        HomepageTemplateMapper $homepageTemplateMapper,
        RoleMapper $roleMapper,
        FolderMapper $folderMapper
    ) {
        $this->configMapper = $configMapper;
        $this->courseConfigMapper = $courseConfigMapper;
        $this->homepageTemplateMapper = $homepageTemplateMapper;
        $this->roleMapper = $roleMapper;
        $this->folderMapper = $folderMapper;
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

        $coursePermissions = $this->courseConfigMapper->findByColumn("page", "course");

        foreach ($coursePermissions as $permission) {
            $permissions['course'][$permission->getRoleId()] = [];
            $permissions['course'][$permission->getRoleId()][$permission->getPermission()] = 'checked';
        }

        $unitPermissions = $this->courseConfigMapper->findByColumn("page", "unit");

        foreach ($unitPermissions as $permission) {
            $permissions['unit'][$permission->getRoleId()] = [];
            $permissions['unit'][$permission->getRoleId()][$permission->getPermission()] = 'checked';
        }

        $templates = [];
        foreach ($this->homepageTemplateMapper->findAll()->sortBy("name", "asc") as $template) {
            $templates[] = [
                'id'   => $template->getId(),
                'name' => $template->getName(),
            ];
        }

        $courseTemplates = \Schoolbox\Assessment\Course\Template\Mapper::getInstance();
        $all = $courseTemplates->findAll();


        // Render this thing out
        $this->response = $this->responseFromTemplate(
            '@adminCourseBuilder/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminCourseBuilder.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->routeGenerator->generate('adminCourseBuilder.getIndex')),
                'tab' => $tab != '' ? $tab : 'course',
                'roles' => $this->roleMapper->findAll(),
                'permissions' => $permissions,
                'availableTemplates' => $templates,
                'courseTemplates'   => $courseTemplates->findAll(),
                'courseTemplate' => $this->getConfigLoader()->get('course_builder_default_course_page_template'),
                'unitTemplate' => $this->getConfigLoader()->get('course_builder_default_unit_page_template'),
                'courseInherit' => $this->getConfigLoader()->get('course_builder_default_course_page_inherit'),
                'unitInherit' => $this->getConfigLoader()->get('course_builder_default_unit_page_inherit')
            ],
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

        $this->courseConfigMapper->deleteByColumn('page', 'course');
        $this->courseConfigMapper->deleteByColumn('page', 'unit');

        // Add course permissions back

        foreach ($this->requestPost->get('course_group', []) as $roleId => $permission) {
            $model = $this->courseConfigMapper->createModel();
            $model->setPage('course');
            $model->setPermission($permission);
            $model->setRoleId($roleId);
            $this->courseConfigMapper->save($model);
        }

        // Add unit permissions back

        foreach ($this->requestPost->get('unit_group', []) as $roleId => $permission) {
            $model = $this->courseConfigMapper->createModel();
            $model->setPage('unit');
            $model->setPermission($permission);
            $model->setRoleId($roleId);
            $this->courseConfigMapper->save($model);
        }

        // Update default page templates

        $courseTemplate = $this->configMapper->findOneByColumn('name', 'course_builder_default_course_page_template');
        $courseTemplate->setValue(implode(',', $this->requestPost['courseTemplate']));
        $this->configMapper->save($courseTemplate);

        $unitTemplate = $this->configMapper->findOneByColumn('name', 'course_builder_default_unit_page_template');
        $unitTemplate->setValue(implode(',', $this->requestPost['unitTemplate']));
        $this->configMapper->save($unitTemplate);

        // Update default inheritance

        $courseTemplate = $this->configMapper->findOneByColumn("name", "course_builder_default_course_page_inherit");
        $courseTemplate->setValue((string)(int)$this->requestPost->get('courseInherit'));
        $this->configMapper->save($courseTemplate);

        $unitTemplate = $this->configMapper->findOneByColumn("name", "course_builder_default_unit_page_inherit");
        $unitTemplate->setValue((string)(int)$this->requestPost->get('unitInherit'));
        $this->configMapper->save($unitTemplate);

        // Send them back to the admin page

        $this->response = $this->redirectAfterPost('/adminv2/course-builder/' . $this->requestPost["tab"]);
    }
}
