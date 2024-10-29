<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as AlaressModule;
use Alaress\Web\Controller\Base;
use Schoolbox\Error\Mapper as ErrorMapper;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\RoleMapper;

/**
 * Class Module
 *
 * @package Schoolbox\Filter
 */
class Module extends AlaressModule
{
    /**
     * @var string
     */
    protected $basename = 'filters';

    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $isSuper = new Rule\IsSuperuser();

        $rulePastoral = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasServicePermission(UserAccess::SERVICE_PASTORAL, UserAccess::ACCESS_FULL)
            )
        );

        $loadFactoryFactory = function (array $params) {
            switch ($params['page']) {
                case Page\Model::PAGE_PENDING_ASSESSMENTS:
                case Page\Model::PAGE_CLASS_MARK_BOOK:
                case Page\Model::PAGE_COURSE_MARK_BOOK:
                case Page\Model::PAGE_RUBRIC_MARK_BOOK:
                case Page\Model::PAGE_GRADES:
                case Page\Model::PAGE_UPCOMING_ASSESSMENTS:
                case Page\Model::PAGE_ASSESSMENT_CALENDAR:
                case Page\Model::PAGE_BADGE_LIST:
                case Page\Model::PAGE_AWARD_LIST:
                case Page\Model::PAGE_CLASS_PASTORAL:
                    return ['factoryFactory' => $this->injector->get('filters.factoryFactory')];
                // Other entity types can have their factory factories registered here
            }
            return [];
        };

        // Routes to which users with service permission UserAccess::SERVICE_PASTORAL need access.
        $this->register('GET', '/pastoral/{page:classPastoral}', 'getPageSettingsPastoral', $rulePastoral, $loadFactoryFactory);
        $this->register('POST', '/pastoral/{page:classPastoral}', 'postSavePageSettingsPastoral', $rulePastoral, $loadFactoryFactory);
        $this->register('POST', '/pastoral/{page:classPastoral}/pushDefaultSelections', 'postPushDefaultSelectionsPastoral', $rulePastoral, $loadFactoryFactory);

        $this->register('GET', '', 'getIndex', $isSuper);
        $this->register('GET', '/{page}', 'getPageSettings', $isSuper, $loadFactoryFactory);
        $this->register('POST', '/{page}', 'postSavePageSettings', $isSuper, $loadFactoryFactory);
        $this->register('POST', '/{page}/pushDefaultSelections', 'postPushDefaultSelections', $isSuper, $loadFactoryFactory);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(Mapper::class),
            $this->getMapperFactory()->get(Page\Mapper::class),
            $this->getMapperFactory()->get(Page\Option\Mapper::class),
            $this->getMapperFactory()->get(Page\Option\Defaults\Mapper::class),
            $this->getMapperFactory()->get(Selection\Mapper::class),
            $this->getMapperFactory()->get(RoleMapper::class),
            $this->getMapperFactory()->get(ErrorMapper::class),
            $this->injector->get('l10n'),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
        $this->addTemplatePath($templateLoader, __DIR__ . '/Filters', 'filterDefinitions');
    }

    /**
     * @return \Schoolbox\Mapper|Mapper
     * @throws \InvalidArgumentException
     */
    protected function getFilterMapper()
    {
        return $this->getMapperFactory()->get(Mapper::class);
    }
}
