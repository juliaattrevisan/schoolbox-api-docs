<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Custom\Diocesan\AchievementReport;

use Alaress\Authorizer\Authorizer;
use Alaress\Module\Module as BaseModule;
use Alaress\Authorizer\Rule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module extends BaseModule {

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        // All of these URLs relate to the resource ID, not a booking ID.
        // Therefore, they should probably be moved to the item module.
        $router->addGet('achievementreport.schoolGrades', $basePath . '/schoolgrades/{externalUserId}{/year}')
            ->addTokens(array(
                'year' => '\d{4}'
            ));
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        // Add the booking item to params, if ID is in the route.
        $matcher->add(
            '/^achievementreport\.schoolGrades/',
            function($params) {
                $externalUserHandler = new \External_User();
                $externalUser = $externalUserHandler->getStudent($params['externalUserId']);

                /* TODO: When selected user doesn't exist in external db
                 */
                if (!$externalUser) {
                    error(2);
                }

                /* TODO: When selected user doesn't exist in schoolbox
                 */
                try {
                    $userMapper = new \Schoolbox\UserMapper();
                    $user = $userMapper->findOneByColumn('synergy_id', $params['externalUserId']);
                }
                catch (\Exception $e) {
                    error(2);
                }

                $db = new \MSSQL();

                return array(
                    'db' => $db,
                    'externalUser' => $externalUser,
                    'user' => $user
                );
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $loader = new Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__.'/Templates', 'achievementreport');
        $loader->addPath(__DIR__.'/SchoolGrades', 'schoolgrades');
        $templateLoader->addLoader($loader);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:achievementreport',
            $injector->lazyNew('Custom\\Diocesan\\AchievementReport\\Controller')
        );
    }
} 