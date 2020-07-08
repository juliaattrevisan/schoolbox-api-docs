<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
namespace Schoolbox\Learning\Markbook;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\Container;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Resource\Folder;
use Aura\Di\ContainerInterface;
use Schoolbox\Role;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Assessment\Term;
use Schoolbox\UserMapper;
use Schoolbox\Error;


class Module extends BaseModule
{
    protected $basename = 'learningMarkbook';

    /**
     * @inheritdoc
     * @throws \InvalidArgumentException
     * @throws NotFound If a class was specified but it does not exist
     */
    public function registerRoutes()
    {
        $preload = function ($params) {
            $args = [];

            // Must preload folder
            try {
                $args['folder'] = $this->getFolderMapper()->findById($params['folderId']);
            } catch (Mapper\Exception $e){
                throw new NotFound("No folder found for id: {$params['folderId']}");
            }

            // Preload term if it's there
            if (isset($params['termCode'])) {
                try {
                    $args['term'] = $this->getTermMapper()->findOneByColumn('code', $params['termCode']);
                } catch (Mapper\Exception $e){
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
                new Rule\HasRole(Role::STAFF),
                new Rule\HasHomepageAccess(
                    UserAccess::ACCESS_WRITE,
                    static function (array $params) {
                        return $params['folderId'];
                    },
                    Access::getInstance()
                )
            )
        );

        // TODO - add 'getMarkbookForUnitImportSpec' to be used in 'outline/unit.twig' to jump to specific unit import
        $this->register('GET',  '/class/{folderId:\d+}',                 'getMarkbookForClass',        $permissions, $preload);
        $this->register('POST', '/class/{folderId:\d+}',                 'postMarkbookForClass',       $permissions, $preload);
        $this->register('GET',  '/class/{folderId:\d+}/term/{termCode}', 'getMarkbookForClassAndTerm', $permissions, $preload);
        $this->register('GET',  '/class/{folderId:\d+}/export',          'getMarkbookExportForClass',  $permissions, $preload);
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
     * @throws \InvalidArgumentException If class not found
     * @throws ServiceNotFound
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->getBasename(),
            $injector->lazyNew(Controller::class, [
                'assessmentMapperProxy'      => $injector->lazyGet('assessmentDataSource'),
                'termMapper'                 => $this->getMapperFactory()->getLazy(Term\Mapper::class),
                'access'                     => $this->getMapperFactory()->getLazy(Access::class),// Has a static 'getInstance()' method
                'assessmentIdFinder'         => $injector->lazyGet('filters.assessmentIdFinder'),
                'filterFactoryFactory'       => $injector->lazyGet('filters.factoryFactory'),
            ])
        );
    }

    /* Getters / Setters
     */

    /**
     * @return Mapper|UserMapper
     * @throws \InvalidArgumentException If class not found
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return Mapper|Folder\Mapper
     * @throws \InvalidArgumentException If class not found
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(Folder\Mapper::class);
    }

    /**
     * @return Mapper|Term\Mapper
     * @throws \InvalidArgumentException If class not found
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(Term\Mapper::class);
    }

    /**
     * @return Mapper|Error\Mapper
     * @throws \InvalidArgumentException If class not found
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(Error\Mapper::class);
    }
}
