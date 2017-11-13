<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter;

use Alaress\Authorizer\Rule\IsSuperuser;
use \Alaress\Module\Module as AlaressModule;
use Aura\Di\ContainerInterface;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Filter\Filters;
use Alaress\Twig\Loader\IChainLoader;


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
     * @throws \ReflectionException If the filter definition class cannot be found in code base
     */
    protected function registerRoutes()
    {
        $isSuper = new IsSuperuser();

        $loadFactoryFactory = function (array $params) {
            switch ($params['page']) {
                case Page\Model::PAGE_PENDING_ASSESSMENTS:
                case Page\Model::PAGE_MARK_BOOK:
                case Page\Model::PAGE_GRADES:
                    return ['factoryFactory' => new Filters\Assessment\FactoryFactory($this->getMapperFactory())];
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
