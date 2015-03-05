<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\LearningAnalytics;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;
use Schoolbox\Permission\Access\UserAccess;

use Schoolbox\Resource\CategoryMapper as FolderMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Assessment\Course\UnitInstance;
use Schoolbox\Assessment\Course\UnitInstanceMapper;
use Group;

class Module extends BaseModule {

    /**
     * Basic routes for this module
     *
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        // All of these URLs relate to the resource ID, not a booking ID.
        // Therefore, they should probably be moved to the item module.
        $router->addGet('learningAnalytics.index', "{$basePath}/{folderId}/{unitId}");
    }

    /**
     * Inject params into controller
     *
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        /* Inject ingredients into learningAnalytics/index controller method
         */
        $matcher->add(
            '/^learningAnalytics\.index/',
            function($params) {

                /* Load folder
                 */
                $folderMapper = new FolderMapper();

                try {
                    $folder = $folderMapper->findById($params['folderId']);
                    $folderMapper->loadJoinedModel('codes', $folder, array('set_method' => 'setCodes'));
                }
                catch (Exception $e) {
                    error(159);
                }

                /* Load unit
                 */
                $unitInstanceMapper = new UnitInstanceMapper();

                $unit = false;
                $allUnits = $unitInstanceMapper->findForCategoryId($folder->getId());

                foreach($allUnits as $currentUnit) {
                    if ($currentUnit->getId() == $params['unitId']) {
                        $unit = $currentUnit;

                        break;
                    }
                }

                if (!($unit instanceof UnitInstance)) {
                    error(6);
                }

                /* Check permissions
                 */
                $group = new Group();

                if ($group->getPermission($folder->getId()) < WRITE_ACCESS) {
                    error(6);
                }

                /* Inject
                 */
                return array(
                    'folder' => $folder,
                    'unit' => $unit,
                    'allUnits' => $allUnits
                );
            }
        );
    }

    /**
     * Template locations for this module
     *
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $loader = new Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__.'/templates', 'learningAnalytics');
        $templateLoader->addLoader($loader);
    }

    /**
     * Set up controllers
     *
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:learningAnalytics',
            $injector->lazyNew('Schoolbox\\LearningAnalytics\\Controller')
        );
    }
}