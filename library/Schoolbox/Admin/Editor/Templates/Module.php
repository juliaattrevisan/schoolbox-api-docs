<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Editor\Templates;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Admin\Editor\Templates\Category\Mapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = "adminEditorTemplates";

    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Superusers and those with the full design service permission can access the templates.
         */
        $perms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_DESIGN, UserAccess::ACCESS_FULL)
            )
        );

        $preloadCategory = function ($params) {
            return $this->preloadCategory($params);
        };

        $preloadTemplate = function ($params) {
            return $this->preloadTemplate($params);
        };

        $this->register('GET', '', 'getList', $perms);
        $this->register('GET', '/{categoryId}/create', 'getCreate', $perms, $preloadCategory);
        $this->register('POST', '/{categoryId}/create', 'postCreate', $perms, $preloadCategory);
        $this->register('GET', '/update/{templateId}', 'getUpdate', $perms, $preloadTemplate);
        $this->register('POST', '/update/{templateId}', 'postUpdate', $perms, $preloadTemplate);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(\Schoolbox\Error\Mapper::class),
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Editor\Templates\Mapper::class),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * Preload a category for template creation
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Mapper\Exception\NotFound
     * @throws \InvalidArgumentException
     */
    private function preloadCategory(array $params): array
    {
        if (\array_key_exists('category', $params)) {
            $category = $params['category'];
        } else {
            try {
                $category = $this->getMapperFactory()->get(Mapper::class)->findById($params['categoryId']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                throw new NotFound('Unknown category id submitted.', 0, $e);
            }
        }
        return [
            'category' => $category
        ];
    }

    /**
     * Preload an existing template
     *
     * @param array $params
     * @return array
     * @throws \Schoolbox\Mapper\Exception\NotFound
     * @throws \InvalidArgumentException
     */
    private function preloadTemplate(array $params): array
    {
        if (\array_key_exists('template', $params)) {
            $template = $params['template'];
        } else {
            try {
                $template = $this->getMapperFactory()->get(\Schoolbox\Editor\Templates\Mapper::class)->findById($params['templateId']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                throw new NotFound('Unknown template id submitted.', 0, $e);
            }
        }
        return [
            'template' => $template
        ];
    }
}
