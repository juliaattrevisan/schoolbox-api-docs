<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Feedback;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Role;
use \Twig_Loader_Chain;
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
    protected $basename = 'learningFeedback';

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
                '/assessment/{assessmentId}/feedback/{studentId}' => 'getAssessmentFeedback',
                '/feedback/{feedbackId}'                          => 'getFeedback',
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
        $ruleRegexp = '(?:getAssessmentFeedback)';
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
            );

        $ruleRegexp = '(?:getFeedback)';
        $this->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn());
    }


    /**
     * @inheritdoc
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '(?:getAssessmentFeedback)',
            function ($params) {
                // TODO. Hydrate specified IAssessable
                $assessment = [$params['assessmentId']];
                try {
                    $student = UserMapper::getInstance()->findById($params['studentId']);
                } catch (MapperException $e) {
                    $student = new User();
                }
                return [
                    'assessment' => $assessment,
                    'student'    => $student,
                ];
            }
        );

        $this->addRoutePreload(
            $matcher,
            '(?:getFeedback)',
            function ($params) {
                // TODO. Hydrate specified Feedback object
                $feedback = [$params['feedbackId']];
                return [
                    'feedback' => $feedback,
                ];
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
