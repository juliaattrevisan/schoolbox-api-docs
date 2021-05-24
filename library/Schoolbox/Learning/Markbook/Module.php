<?php

/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Markbook;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Aura\Di\Exception\ServiceNotFound;
use InvalidArgumentException;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Term;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Folder;
use Schoolbox\Learning\Authorizer\Rule\IsInstructor;

class Module extends BaseModule
{
    protected $basename = 'learningMarkbook';

    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     * @throws NotFound If a class was specified but it does not exist
     */
    public function registerRoutes()
    {
        $preload = function ($params) {
            $args = [];

            // Must preload folder
            try {
                $args['folder'] = $this->getFolderMapper()->findById($params['folderId']);
            } catch (Mapper\Exception $e) {
                throw new NotFound("No folder found for id: {$params['folderId']}");
            }

            // Preload term if it's there
            if (isset($params['termCode'])) {
                try {
                    $args['term'] = $this->getTermMapper()->findOneByColumn('code', $params['termCode']);
                } catch (Mapper\Exception $e) {
                    throw new NotFound("No term found for code: {$params['termCode']}");
                }
            }
            return $args;
        };

        $permissions = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsConfigOn('class_markbook_enable', $this->getConfig()),
            new Rule\Any(
                new Rule\IsSuperuser(),
                new IsInstructor(Access::getInstance())
            )
        );

        // TODO - add 'getMarkbookForUnitImportSpec' to be used in 'outline/unit.twig' to jump to specific unit import
        $this->register('GET', '/class/{folderId:\d+}', 'getMarkbookForClass', $permissions, $preload);
        $this->register('POST', '/class/{folderId:\d+}', 'postMarkbookForClass', $permissions, $preload);
        $this->register('GET', '/class/{folderId:\d+}/term/{termCode}', 'getMarkbookForClassAndTerm', $permissions, $preload);
        $this->register('GET', '/class/{folderId:\d+}/export', 'getMarkbookExportForClass', $permissions, $preload);
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }

    /**
     * @inheritdoc
     * @param Container $injector
     * @throws InvalidArgumentException If class not found
     * @throws ServiceNotFound
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->getBasename(),
            $injector->lazyNew(Controller::class, [
                'assessmentMapperProxy' => $injector->lazyGet('assessmentDataSource'),
                'termMapper'            => $this->getMapperFactory()->getLazy(Term\Mapper::class),
                // Access has a static 'getInstance()' method
                'access'                => $this->getMapperFactory()->getLazy(Access::class),
                'assessmentIdFinder'    => $injector->lazyGet('filters.assessmentIdFinder'),
                'filterFactoryFactory'  => $injector->lazyGet('filters.factoryFactory'),
            ])
        );
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper|Folder\Mapper
     * @throws InvalidArgumentException If class not found
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }

    /**
     * @return Mapper|Term\Mapper
     * @throws InvalidArgumentException If class not found
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(Term\Mapper::class);
    }
}
