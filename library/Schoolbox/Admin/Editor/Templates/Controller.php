<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Editor\Templates;

use Alaress\Web\Controller\Base;
use Alaress\Web\Exception\NotFound;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Editor\Templates\Category\ModelToArray;
use Schoolbox\Admin\Editor\Templates\Category\Mapper as CategoryMapper;
use Schoolbox\Editor\Templates\Mapper as TemplateMapper;
use Schoolbox\Editor\Templates\Model as TemplateModel;
use Schoolbox\Error\Mapper as ErrorMapper;

use function SSNepenthe\ColorUtils\name;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param CategoryMapper $categoryMapper
     * @param TemplateMapper $templateMapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private CategoryMapper $categoryMapper,
        private TemplateMapper $templateMapper,
    ) {
    }

    /**
     * Display list of editor template categories
     */
    public function getList(): void
    {
        // Get the categories from the mapper and transform each category into an array
        $categories = $this->categoryMapper->findAll()->sortNaturally('name');
        $translator = new ModelToArray();

        $this->response = $this->responseFromTemplate(
            '@adminEditorTemplates/list.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminEditorTemplates.getList'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminEditorTemplates.getList')),
                'categories' => $categories->map($translator),
            ]
            );
    }

    /**
     * Create a new editor template
     *
     * @param object $category
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getCreate(object $category): void
    {
        $this->response = $this->responseFromTemplate(
            '@adminEditorTemplates/create.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminEditorTemplates.getList'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminEditorTemplates.getList')),
                    [['Create ' . ucwords(str_replace('-', ' ', $category->getName())) . ' Template', null]]
                ),
                'category' => $category,
                'template' => null,
            ]
        );
    }

    /**
     * Persist a new editor template
     *
     * @param object $category
     * @return void
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \PDOException
     * @throws \Schoolbox\Application\Exception\Exception
     * @throws \UnexpectedValueException
     */
    public function postCreate(object $category): void
    {
        $postData = $this->requestPost->getArrayCopy();

        // Validate required fields
        if (empty(trim((string)($postData['title'] ?? '')))) {
            throw $this->errorMapper->generateException(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }
        if (empty(trim((string)($postData['body'] ?? '')))) {
            throw $this->errorMapper->generateException(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        // Create new template
        $template = $this->templateMapper->createModel()
            ->setCategoryId($category->getId())
            ->setTitle(trim((string)$postData['title']))
            ->setDescription(trim((string)($postData['description'] ?? '')))
            ->setBody(trim((string)($postData['body'] ?? '')))
            ->setCreatedBy($this->getUser()->getId())
            ->setUpdatedBy($this->getUser()->getId());

        $this->templateMapper->save($template);

        // Redirect back to list
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminEditorTemplates.getList') . '?category=' . $category->getName());

    }

    /**
     * To update an editor template
     *
     * @param object $template
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function getUpdate(object $template): void
    {
        $category = $this->categoryMapper->findById($template->getCategoryId());

        $this->response = $this->responseFromTemplate(
            '@adminEditorTemplates/create.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminEditorTemplates.getList'),
                'allBreadcrumbs' => array_merge(
                    (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminEditorTemplates.getList')),
                    [['Update ' . $template->getTitle(), null]]
                ),
                'category' => $category,
                'template' => $template,
            ]
        );
    }

    /**
     * Update an editor template
     *
     * @param object $template
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     */
    public function postUpdate(object $template): void
    {
        $postData = $this->requestPost->getArrayCopy();

        // Validate required fields
        if (empty(trim((string)($postData['title'] ?? '')))) {
            throw $this->errorMapper->generateException(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }
        if (empty(trim((string)($postData['body'] ?? '')))) {
            throw $this->errorMapper->generateException(\Schoolbox\Error\Mapper::ERROR_INFORMATION_MISSING);
        }

        // Update the existing template
        $template
            ->setTitle(trim((string)$postData['title']))
            ->setDescription(trim((string)($postData['description'] ?? '')))
            ->setBody(trim((string)($postData['body'] ?? '')))
            ->setUpdatedBy($this->getUser()->getId());

        $this->templateMapper->save($template);

        // Redirect back to list
        $category = $this->categoryMapper->findById($template->getCategoryId());
        $this->response = $this->redirectAfterPost($this->routeGenerator->generate('adminEditorTemplates.getList') . '?category=' . $category->getName());
    }
}
