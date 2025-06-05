<?php

/**
 * @author      Michael Heydon <michael.heydon@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\Editor\Templates;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Admin\Editor\Templates\Category\ModelToArray;

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
        private Category\Mapper $categoryMapper,
    ) {
    }


    /**
     * Display list of editor template categories
     */
    public function getList(): void
    {

        $categories = $this->categoryMapper->findAll()->sortNaturally('type');
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

}
