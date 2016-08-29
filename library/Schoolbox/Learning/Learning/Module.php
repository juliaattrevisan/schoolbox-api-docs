<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Learning;

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
     * @var External_UserInterface
     */
    protected $externalDb;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var FolderMapper
     */
    protected $folderMapper;

    /**
     * @var TermMapper
     */
    protected $termMapper;

    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [
                ''                                           => 'getPendingAssessments',
                '/markbook/{classId}'                        => 'getMarkbook',
                '/due/{studentId}'                           => 'getDue',
                '/grades/{studentId}/{termCode}'             => 'getGrades',
                '/grades/{studentId}/{termCode}/{subjectId}' => 'getGradesForSubject',
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
        $ruleRegexp = '(?:getMarkbook)';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                $ruleRegexp,
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF)
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
                    new Rule\IsUserOrParentOfUser($this->getExternalDb(), $this->getUserMapper(), 'studentId')
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
            '(?:getDue)',
            function ($params) {
                try {
                    $student = $this->getUserMapper()->findById($params['userId']);
                } catch (MapperException $e) {
                    $student = $this->getUserMapper()->createModel();
                }
                return [
                    'student' => $student,
                ];
            }
        );


        $this->addRoutePreload(
            $matcher,
            '(?:getGrades|getGradesForSubject)',
            function ($params) {
                try {
                    $args['student'] = $this->getUserMapper()->findById($params['studentId']);
                } catch (MapperException $e) {
                    $args['student'] = $this->getUserMapper()->createModel();
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
                'errorMapper' => $this->getErrorMapper(),
            ])
        );
    }

    /* Getters / Setters
     */

    /**
     * @return External_UserInterface
     */
    protected function getExternalDb()
    {
        return $this->externalDb;
    }

    /**
     * @param  External_UserInterface $externalDb
     * @return static
     */
    public function setExternalDb($externalDb)
    {
        $this->externalDb = $externalDb;
        return $this;
    }

    /**
     * @return UserMapper
     */
    protected function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param  UserMapper $userMapper
     * @return static
     */
    public function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param  FolderMapper $folderMapper
     * @return static
     */
    public function setFolderMapper(FolderMapper $folderMapper)
    {
        $this->folderMapper = $folderMapper;
        return $this;
    }

    /**
     * @return TermMapper
     */
    protected function getTermMapper()
    {
        return $this->termMapper;
    }

    /**
     * @param  TermMapper $termMapper
     * @return static
     */
    public function setTermMapper(TermMapper $termMapper)
    {
        $this->termMapper = $termMapper;
        return $this;
    }

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param  ErrorMapper $errorMapper
     * @return static
     */
    public function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }
}
