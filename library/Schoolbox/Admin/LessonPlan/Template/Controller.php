<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\LessonPlan\Template;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param Mapper $templateMapper
     */
    public function __construct(private Mapper $templateMapper)
    {
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
}
