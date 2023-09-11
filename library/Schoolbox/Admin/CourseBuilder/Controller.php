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
use Schoolbox\Assessment\Course\Template\Mapper as CourseTemplateMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\RoleMapper;
use Schoolbox\Resource\HomepageTemplateMapper;

class Controller extends Base
{
    /**
     * Constructor
     *
     * @param ConfigMapper           $configMapper
     * @param CourseConfigMapper     $courseConfigMapper
     * @param HomepageTemplateMapper $homepageTemplateMapper
     * @param RoleMapper             $roleMapper
     * @param CourseTemplateMapper   $courseTemplateMapper
     */
    public function __construct(
        private ConfigMapper $configMapper,
        private CourseConfigMapper $courseConfigMapper,
        private HomepageTemplateMapper $homepageTemplateMapper,
        private RoleMapper $roleMapper,
        private CourseTemplateMapper $courseTemplateMapper
    ) {
    }

    /**
     * Show options
     */
    public function getIndex()
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

        // Render this thing out
        $this->response = $this->responseFromTemplate(
            '@adminCourseBuilder/index.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminCourseBuilder.getIndex'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->routeGenerator->generate('adminCourseBuilder.getIndex')),
                'roles' => $this->roleMapper->findAll(),
                'permissions' => $permissions,
                'availableTemplates' => $templates,
                'courseTemplates'   => $this->courseTemplateMapper->findAll(),
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

        // Update course templates
        $templates = $this->courseTemplateMapper->createCollection();
        // get list of all posted template groups
        foreach (json_decode($this->getRequestPost()->get('templates', ''), true) as $templateGroup) {
            // iterate over template groups
            // if id is larger than 1 then grab that from the db and update that entry
            if (1 <= $templateGroup['id']) {
                $template = $this->courseTemplateMapper->findById($templateGroup['id']);
            } else {
                // if id is less than 1 then spawn a new entry and set the values
                $template = $this->courseTemplateMapper->createModel();
            }
            // set the new values
            $template->setName($templateGroup['name']);
            $template->setCourseTemplate($this->homepageTemplateMapper->findById($templateGroup['courseTemplate']));
            $template->setUnitTemplate($this->homepageTemplateMapper->findById($templateGroup['unitTemplate']));
            $templates->add($template);
        }
        // save the template groups
        $this->courseTemplateMapper->save($templates);

        // Update default inheritance
        $courseTemplate = $this->configMapper->findOneByColumn("name", "course_builder_default_course_page_inherit");
        $courseTemplate->setValue((string)(int)$this->requestPost->get('courseInherit'));
        $this->configMapper->save($courseTemplate);

        $unitTemplate = $this->configMapper->findOneByColumn("name", "course_builder_default_unit_page_inherit");
        $unitTemplate->setValue((string)(int)$this->requestPost->get('unitInherit'));
        $this->configMapper->save($unitTemplate);

        // Success, just return nothing. no errors occurred...
        $this->response = $this->responseEmpty();
    }

    /**
     * Soft delete the selected course template.
     * This does nothing to the courses that are using this template.
     * But will prevent any future courses from using this template.
     *
     * @api DELETE /adminv2/course-builder/delete/{templateId:\d+}
     *
     * @param $template
     * @return void
     * @throws \Schoolbox\Mapper\Exception
     */
    public function deleteTmpl($template): void
    {
        // ensure there is still 1 option left...
        if (1 === $this->courseTemplateMapper->countAll()) {
            $this->sendJsonError('Must have 1 Course Template available', 409);
            return; //exit early
        }

        //Purge the template
        $this->courseTemplateMapper->delete($template);
        $this->response = $this->responseEmpty();
    }
}
