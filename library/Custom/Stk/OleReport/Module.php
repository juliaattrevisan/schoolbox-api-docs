<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Stk\OleReport;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Role;
use Alaress\Twig\Loader\IChainLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;

/**
 * Module definition for SaintKentigern's custom OLE report.
 *
 * @package Custom\Stk\OleReport
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'customStkOleReport';

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'reportList', $basePath, '/{studentId}');
        $this->addGetRoute($router, 'sendReport', $basePath, '/{studentId}/{reportId}/sendReport');
    }

    /**
     * @inheritdoc
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '(?:reportList)',
            function ($params) {
                try {
                    $student = $this->getUserMapper()->findById($params['studentId']);
                } catch (MapperException $e) {
                    $student = $this->getUserMapper()->createModel();
                }
                return ['student' => $student,];
            }
        );

        $this->addRoutePreload(
            $matcher,
            '(?:sendReport)',
            function ($params) {
                try {
                    $student = $this->getUserMapper()->findById($params['studentId']);
                } catch (MapperException $e) {
                    $student = $this->getUserMapper()->createModel();
                }
                return [
                    'student' => $student,
                    'reportId' => $params['reportId']
                ];
            }
        );
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
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'database' => $injector->lazyGet('databaseExternal'),
            ])
        );
    }

    /**
     * These pages may be accessed by:
     *
     * * superusers
     * * staff
     * * the child themselves
     * * the child's parents
     *
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $ruleRegexp = '(?:reportList|sendReport)';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                $ruleRegexp,
                new Rule\LazyCallback(function () {
                    return new Rule\Any(
                        new Rule\IsSuperuser(),
                        new Rule\HasRole(Role::STAFF),
                        new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'studentId')
                    );
                })
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
