<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Role;
use \Twig_Loader_Chain;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\UserMapper;
use Schoolbox\Error\Mapper as ErrorMapper;
use \External_UserInterface;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learning';

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [
                '/pending'                                => 'getPendingAssessments',
                '/pending/{userId}'                       => 'getPendingAssessmentsForUser',
                '/classes'                                => 'getClasses',
                '/markbook/{classId}'                     => 'getMarkbook',
                '/due/{studentId}'                        => 'getDue',
                '/grades/{userId}/{termCode}'             => 'getGrades',
                '/grades/{userId}/{termCode}/{subjectId}' => 'getGradesForSubject',
            ],
            'POST' => []
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $ruleRegexp = '(?:getMarkbook|getPendingAssessments|getClasses)';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn());

        $ruleRegexp = '(?:getPendingAssessmentsForUser)';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                $ruleRegexp,
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\IsUser('userId')
                )
            );

        $ruleRegexp = '(?:getDue|getGrades|getGradesForSubject)';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                $ruleRegexp,
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'userId')
                )
            );
    }


    /**
     * @inheritdoc
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '(?:getPendingAssessmentsForUser|getDue)',
            function ($params) {
                    try {
                        $user = $this->getUserMapper()->findById($params['userId']);
                    } catch (MapperException $e) {
                        $user = $this->getUserMapper()->createModel();
                    }
                return [
                    'user' => $user,
                ];
            }
        );


        $this->addRoutePreload(
            $matcher,
            '(?:getMarkbook)',
            function ($params) {
                try {
                    $class = $this->getFolderMapper()->findById($params['classId']);
                } catch (MapperException $e) {
                    $class = $this->getFolderMapper()->createModel();
                }
                return [
                    'class' => $class,
                ];
            }
        );




        $this->addRoutePreload(
            $matcher,
            '(?:getGrades|getGradesForSubject)',
            function ($params) {
                try {
                    $args['user'] = $this->getUserMapper()->findById($params['userId']);
                } catch (MapperException $e) {
                    $args['user'] = $this->getUserMapper()->createModel();
                }
                try {
                    $args['term'] = $this->getTermMapper()->findOneByColumn('code', $params['termCode']);
                } catch (MapperException $e) {
                    $args['term'] = $this->getTermMapper()->createModel();
                }
                if (array_key_exists('subjectId', $params)) {
                    try {
                        $args['subject'] = $this->getFolderMapper()->findById($params['subjectId']);
                    } catch (MapperException $e) {
                        $args['subject'] = $this->getFolderMapper()->createModel();
                    }
                }
                return $args;
            }
        );
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
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
                'errorMapper' => $this->getMapperFactory()->getLazy(ErrorMapper::class),
            ])
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

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }

    /**
     * @return TermMapper
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
