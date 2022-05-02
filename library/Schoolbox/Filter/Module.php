<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use Alaress\Authorizer\Rule\IsSuperuser;
use Alaress\Module\Module as AlaressModule;
use Alaress\Web\Controller\Base;
use Psr\Container\ContainerInterface;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Filter\Filters;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\RoleMapper;

/**
 * Class Module
 *
 * @package Schoolbox\Filter
 */
class Module extends AlaressModule
{

    /**
     * @param ContainerInterface $injector
     * @param string             $basename
     */
    public function __construct(ContainerInterface $injector, string $basename = '')
    {
        parent::__construct($injector, '' === $basename ? 'filters' : $basename);
    }


    /**
     * @inheritdoc
     */
    protected function registerRoutes(): void
    {
        $isSuper = new IsSuperuser();

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
                    return ['factoryFactory' => $this->injector->get('filters.factoryFactory')];
                // Other entity types can have their factory factories registered here
            }
            return [];
        };


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
