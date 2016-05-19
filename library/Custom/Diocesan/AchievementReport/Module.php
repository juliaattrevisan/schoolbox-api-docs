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

use Schoolbox\UserMapper;

class Module extends BaseModule {
    protected $basename = 'achievementreport';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $router->addGet('achievementreport.selector', $basePath . '/{externalUserId}');

        $router->addGet('achievementreport.schoolGrades', $basePath . '/schoolgrades/{externalUserId}{/year}')
            ->addTokens(array(
                'year' => '\d{4}'
            ));

        $router->addGet('achievementreport.ib', $basePath . '/ib/{externalUserId}{/year}')
            ->addTokens(array(
                'year' => '\d{4}'
            ));

        $router->addGet('achievementreport.ncea', $basePath . '/ncea/{externalUserId}{/year}')
            ->addTokens(array(
                'year' => '\d{4}'
            ));
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        /* For php < 5.4
         */
        $self = $this;

        $matcher->add(
            '/^achievementreport\.selector/',
            function($params) use ($self) {
                return $self->getEverything($params);
            }
        );

        $matcher->add(
            '/^achievementreport\.schoolGrades/',
            function($params) use ($self) {
                return $self->getEverything($params);
            }
        );

        $matcher->add(
            '/^achievementreport\.ib/',
            function($params) use ($self) {
                return $self->getEverything($params);
            }
        );

        $matcher->add(
            '/^achievementreport\.ncea/',
            function($params) use ($self) {
                return $self->getEverything($params);
            }
        );
    }

    /**
     * Macro method
     *
     * public because of php version
     *
     * @param $params
     * @return array
     */
    public function getEverything($params) {
        $externalUserHandler = new \External_User();
        $externalUser = $externalUserHandler->getStudent($params['externalUserId']);

        /* When selected user doesn't exist in external db
         */
        if (!$externalUser) {
            error(31);
        }

        /* When selected user doesn't exist in schoolbox
         */
        try {
            $userMapper = new UserMapper();
            $user = $userMapper->findOneByColumn('synergy_id', $params['externalUserId']);
            $user = $userMapper->findById($user->getId());
        }
        catch (\Exception $e) {
            error(31);
        }

        /* Teachers and parents of children have access to a report
         * Everyone else does not
         */
        $groupHandler = new \Group();

        $isSuperuser = $_SESSION['user']->isSuperuser();
        $isStaff = $_SESSION['user']->getRole()->isStaff();
        $isParentOfChild = $groupHandler->isParentOfChild($user->getId());

        /* If you're none of the above, get out
         */
        if (!$isSuperuser && !$isStaff && !$isParentOfChild) {
            error(19);
        }

        return array(
            'db' => new \MSSQL(),
            'externalUser' => $externalUser,
            'user' => $user
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
        $loader->addPath(__DIR__.'/SchoolGrades', 'schoolgrades');
        $loader->addPath(__DIR__.'/IB', 'ib');
        $loader->addPath(__DIR__.'/NCEA', 'ncea');
        $loader->addPath(__DIR__.'/Templates', 'templates');
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