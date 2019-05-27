<?php
/**
 * Module
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Custom\Diocesan\AchievementReport;

use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Alaress\Web\Router\PathMatcher;
use Alaress\Twig\Loader\IChainLoader;
use Custom\Diocesan\AchievementReport\Controller as AchievementReportController;
use Group;
use Schoolbox\Mapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    protected $basename = 'achievementreport';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath): void
    {
        $router->addGet('achievementreport.selector', $basePath . '/{externalUserId}');

        $router->addGet('achievementreport.schoolGrades', $basePath . '/schoolGrades/{externalUserId}{/year}')
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

        $router->addGet('achievementreport.comments', $basePath . '/comments/{externalUserId}{/year}')
            ->addTokens(array(
                'year' => '\d{4}'
            ));

        $router->addGet('achievementreport.standardisedTest', $basePath . '/standardisedTest/{externalUserId}{/year}')
            ->addTokens(array(
                'year' => '\d{4}'
            ));

    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePreloads(PathMatcher $matcher): void
    {
        $matcher->add(
            '/^achievementreport\.selector/',
            function ($params) {
                return $this->getPreloads($params);
            }
        );

        $matcher->add(
            '/^achievementreport\.schoolGrades/',
            function ($params) {
                return $this->getPreloads($params);
            }
        );

        $matcher->add(
            '/^achievementreport\.ib/',
            function ($params) {
                return $this->getPreloads($params);
            }
        );

        $matcher->add(
            '/^achievementreport\.ncea/',
            function ($params) {
                return $this->getPreloads($params);
            }
        );

        $matcher->add(
            '/^achievementreport\.comments/',
            function ($params) {
                return $this->getPreloads($params);
            }
        );

        $matcher->add(
            '/^achievementreport\.standardisedTest/',
            function ($params) {
                return $this->getPreloads($params);
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
     * @throws \Exception
     */
    public function getPreloads($params): array
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
            $user = $this->getUserMapper()->findByExternalId($params['externalUserId']);
        } catch (Mapper\Exception $e) {
            error(31);
            exit();
        }

        /* Teachers, students and parents of those students have access to a student's report
         * Everyone else does not
         */
        $groupHandler = new Group();

        /** @var User $sessionUser */
        $sessionUser = $_SESSION['user'];

        /* If you're not a superuser, staff, student, or parent of specified student, get out
         */
        if (!(
            $sessionUser->isSuperuser() ||
            $sessionUser->getRole()->isStaff() ||
            $sessionUser->getId() === $user->getId() ||
            $groupHandler->isParentOfChild($user->getId())
        )) {
            error(19);
        }

        return [
            'db' => \MSSQL::getInstance(),
            'externalUser' => $externalUser,
            'user' => $user
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/SchoolGrades', 'schoolGrades');
        $this->addTemplatePath($templateLoader, __DIR__ . '/IB', 'ib');
        $this->addTemplatePath($templateLoader, __DIR__ . '/NCEA', 'ncea');
        $this->addTemplatePath($templateLoader, __DIR__ . '/Comments', 'comments');
        $this->addTemplatePath($templateLoader, __DIR__ . '/StandardisedTest', 'standardisedTest');
        $this->addTemplatePath($templateLoader, __DIR__ . '/Templates', 'templates');
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector): void
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
     * @throws \InvalidArgumentException
     */
    protected function getUserMapper(): UserMapper
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }
} 
