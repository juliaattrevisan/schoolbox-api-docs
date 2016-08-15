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
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Role;
use \Twig_Loader_Chain;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Assessment\Term\Model as Term;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
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
            'POST' => [ ]
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
            )
        ;

        $ruleRegexp = '(?:getDue|getGrades|getGradesForSubject)';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                $ruleRegexp,
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\IsUserOrParentOfUser($this->externalDb, $this->userMapper, 'studentId')
                )
            )
        ;
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
                    $class = FolderMapper::getInstance()->findById($params['classId']);
                } catch (MapperException $e){
                    $class = new Folder();
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
                    $student = UserMapper::getInstance()->findById($params['userId']);
                } catch (MapperException $e){
                    $student = new User();
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
                    $args['student'] = UserMapper::getInstance()->findById($params['studentId']);
                } catch (MapperException $e){
                    $args['student'] = new User();
                }
                try {
                    $args['term'] = TermMapper::getInstance()->findOneByColumn('code',$params['termCode']);
                } catch (MapperException $e){
                    $args['term'] = new Term();
                }
                if(array_key_exists('subjectId', $params)){
                    try {
                        $args['subject'] = FolderMapper::getInstance()->findById($params['subjectId']);
                    } catch (MapperException $e){
                        $args['subject'] = new Folder();
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
            'controller:'.$this->basename,
            $injector->lazyNew(Controller::class, [
                'errorMapper' => ErrorMapper::getInstance(),
            ])
        );
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
     * @param  UserMapper $userMapper
     * @return static
     */
    public function setUserMapper(UserMapper $userMapper)
    {
        $this->userMapper = $userMapper;
        return $this;
    }
}
