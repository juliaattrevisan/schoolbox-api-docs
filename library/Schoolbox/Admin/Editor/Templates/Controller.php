<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Editor\Templates;

use Alaress\Database\Predicate\InvalidColumnException;
use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Role;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param ErrorMapper $errorMapper
     * @param Mapper $categoryMapper
     */
    public function __construct(
        private ErrorMapper $errorMapper,
        private Mapper $categoryMapper,
    ) {
    }


    /**
     * Provide form
     */
    public function getList(): void
    {
        $this->response = $this->responseFromTemplate(
            '@adminEditorTemplates/list.twig',
            [
                'pageCurrent' => $this->routeGenerator->generate('adminEditorTemplates.getList'),
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('adminEditorTemplates.getList')),
                'categories' => json_encode($this->categoryMapper->findAll()->sortNaturally('type')),
            ]
        );
    }

}
