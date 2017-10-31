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

use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Alaress\Twig\Loader\IChainLoader;
use Twig_Loader_Filesystem;
use Custom\Diocesan\AchievementReport\Controller as AchievementReportController;

use Schoolbox\UserMapper;

class Module extends BaseModule
{
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
        $matcher->add(
            '/^achievementreport\.selector/',
            function ($params) {
                return $this->getEverything($params);
            }
        );

        $matcher->add(
            '/^achievementreport\.schoolGrades/',
            function ($params) {
                return $this->getEverything($params);
            }
        );

        $matcher->add(
            '/^achievementreport\.ib/',
            function ($params) {
                return $this->getEverything($params);
            }
        );

        $matcher->add(
            '/^achievementreport\.ncea/',
            function ($params) {
                return $this->getEverything($params);
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
    public function getEverything($params)
    {
        $externalUserHandler = $this->getExternalUser();
        $externalUser = $externalUserHandler->getStudent($params['externalUserId']);

        /* When selected user doesn't exist in external db
         */
        if (!$externalUser) {
            error(31);
        }

        /* When selected user doesn't exist in schoolbox
         */
        try {
            $userMapper = $this->getUserMapper();
            $user = $userMapper->findOneByColumn('synergy_id', $params['externalUserId']);
            $user = $userMapper->findById($user->getId());
        } catch (\Exception $e) {
            error(31);
            exit();
        }

        /* Teachers and parents of children have access to a report
         * Everyone else does not
         */
        $groupHandler = new \Group();

        /** @var \Schoolbox\User $sessionUser */
        $sessionUser = $_SESSION['user'];

        /* If you're not a superuser, staff, or parent of specified user, get out
         */
        if (!(
            $sessionUser->isSuperuser() ||
            $sessionUser->getRole()->isStaff() ||
            $groupHandler->isParentOfChild($user->getId())
        )) {
            error(19);
        }

        return array(
            'db' => \MSSQL::getInstance(),
            'externalUser' => $externalUser,
            'user' => $user
        );
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $loader = new Twig_Loader_Filesystem(array());
        $loader->addPath(__DIR__ . '/SchoolGrades', 'schoolgrades');
        $loader->addPath(__DIR__ . '/IB', 'ib');
        $loader->addPath(__DIR__ . '/NCEA', 'ncea');
        $loader->addPath(__DIR__ . '/Templates', 'templates');
        $templateLoader->addLoader($loader);
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:achievementreport',
            $injector->lazyNew(AchievementReportController::class)
        );
    }

    /* Getters / Setters
     */

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }
} 
