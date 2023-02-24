<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\LessonPlan\Template;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Mapper\Exception;


class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param Mapper $templateMapper
     * @param Field\Mapper $fieldMapper
     */
    public function __construct(
        private Mapper $templateMapper,
        private Field\Mapper $fieldMapper
    ) {
    }


    /**
     * Provide form
     */
    public function getList(): void
    {
        $this->getResponse()->content->set([
            'template' => '@adminLessonPlanTemplate/list.twig',
            'data' => [
                'pageCurrent' => $this->router->generate('adminLessonPlanTemplate.getList'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminLessonPlanTemplate.getList')),
                'lessonPlanTemplates' => $this->templateMapper->findAll()->sortNaturally('title')
            ]
        ]);
    }


    /**
     * Create a new lesson plan template
     *
     * @return void
     * @throws RouteNotFound
     */
    public function getCreate(): void
    {
        $this->getResponse()->content->set([
            'template' => '@adminLessonPlanTemplate/create.twig',
            'data' => [
                'pageCurrent' => $this->router->generate('adminLessonPlanTemplate.getList'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader()))->generateBreadcrumbs($this->router->generate('adminLessonPlanTemplate.getList')),
                'lessonPlanTemplate' => null
            ]
        ]);
    }


    /**
     * Persist a new lesson plan template
     *
     * @return void
     * @throws \PDOException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    public function postCreate(): void
    {
        $postData = $this->getRequest()->post->getArrayCopy();

        $template = $this->templateMapper->createModel()
            ->setTitle($postData['title'])
            ->setEnabled(true)
            ->setCreatedBy($this->getUser())
            ->setUpdatedBy($this->getUser());

        $this->templateMapper->save($template);

        $numFields = \count($postData['field-new-title']);

        for ($i = 1; $i <= $numFields; $i++) {
            $visibility = \Schoolbox\Role::STAFF;

            if ('on' === $postData['field-new-visibility'][$i]) {
                $visibility = \Schoolbox\Role::STUDENT;
            }

            $field = $this->fieldMapper->createModel()
                ->setTitle($postData['field-new-title'][$i])
                ->setLessonPlanTemplate($template)
                ->setVisibility($visibility)
                ->setSequence($i);

            $this->fieldMapper->save($field);
        }

        $this->getResponse()->redirect->afterPost($this->getRouter()->generate('adminLessonPlanTemplate.getList'));
    }


    /**
     * Update an existing lesson plan template
     *
     * @param Model $lessonPlanTemplate
     * @return void
     */
    public function getUpdate(Model $lessonPlanTemplate): void
    {
    }


    /**
     * Persist changes to an existing lesson plan template
     *
     * @param Model $lessonPlanTemplate
     * @return void
     */
    public function postUpdate(Model $lessonPlanTemplate): void
    {
    }
}
