<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\LessonPlan\Template;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Mapper\Exception\NotFound;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'adminLessonPlanTemplate';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Superusers only
         */
        $getPerms = $postPerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser()
            )
        );

        $preloadLessonPlanTemplate = function ($params) {
            return $this->preloadLessonPlanTemplate($params);
        };

        $this->register('GET', '', 'getList', $getPerms);
        $this->register('GET', '/create', 'getCreate', $getPerms);
        $this->register('POST', '/create', 'postCreate', $postPerms);
        $this->register('GET', '/update/{lessonPlanTemplateId}', 'getUpdate', $getPerms, $preloadLessonPlanTemplate);
        $this->register('POST', '/update/{lessonPlanTemplateId}', 'postUpdate', $postPerms, $preloadLessonPlanTemplate);
        $this->register('DELETE', '/delete/{lessonPlanTemplateId}', 'delete', $postPerms, $preloadLessonPlanTemplate);
        $this->register('POST', '/toggle/{lessonPlanTemplateId}', 'postToggleEnableState', $postPerms, $preloadLessonPlanTemplate);
    }



    /**
     * Preload a lesson plan template
     *
     * @param array $params
     * @return array
     * @throws NotFound
     * @throws \InvalidArgumentException
     */
    private function preloadLessonPlanTemplate(array $params): array
    {
        if (\array_key_exists('lessonPlanTemplate', $params)) {
            $template = $params['lessonPlanTemplate'];
        } else {
            try {
                $template = $this->getMapperFactory()->get(Mapper::class)->findById($params['lessonPlanTemplateId']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                throw new NotFound('Unknown lesson plan template.', 0, $e);
            }
        }
        return [
            'lessonPlanTemplate' => $template
        ];
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Admin\LessonPlan\Template\Mapper::class),
            $this->getMapperFactory()->get(\Schoolbox\Admin\LessonPlan\Template\Field\Mapper::class),
        );
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
