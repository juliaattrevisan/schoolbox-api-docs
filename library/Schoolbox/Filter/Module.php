<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use Alaress\Authorizer\Rule\IsSuperuser;
use \Alaress\Module\Module as AlaressModule;
use Aura\Di\ContainerInterface;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Filter\Filters;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\L10n\RosettaStone;


class Module extends AlaressModule
{

    /**
     * @param ContainerInterface $injector
     * @param string             $basename
     */
    public function __construct(ContainerInterface $injector, $basename = '')
    {
        parent::__construct($injector, '' === $basename ? 'filters' : $basename);
    }


    /**
     * @inheritdoc
     * @throws NotFound If invalid roleType
     * @throws \InvalidArgumentException
     * @throws ServiceNotFound
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
                    /** @var RosettaStone $rosettaStone */
                    return ['factoryFactory' => $this->getInjector()->get('filters.factoryFactory')];
                // Other entity types can have their factory factories registered here
            }
            return [];
        };


        $this->register('GET', '', 'getIndex', $isSuper);
        $this->register('GET', '/{page}', 'getPageSettings', $isSuper, $loadFactoryFactory);
        $this->register('POST', '/{page}', 'postSavePageSettings', $isSuper, $loadFactoryFactory);
    }


    /**
     * @inheritdoc
     * @throws \InvalidArgumentException If mapper class not found
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class, [
            'filterMapper'   => $this->getMapperFactory()->getLazy(Mapper::class),
            'pageMapper'     => $this->getMapperFactory()->getLazy(Page\Mapper::class),
            'optionsMapper'  => $this->getMapperFactory()->getLazy(Page\Option\Mapper::class),
            'defaultsMapper' => $this->getMapperFactory()->getLazy(Page\Option\Defaults\Mapper::class),
            'rosettaStone'   => $injector->lazyGet('l10n'),
        ]));
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
