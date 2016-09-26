<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment;

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
use \External_UserInterface;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningAssessment';


    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [

                /* Select an assessment
                 *
                 */
                '/assessment/select/{folderId}' => 'getSelectAssessment',

                /* This is the second half containing assessable-specific data
                 */
                '/assessment/{assessmentId}/update' => 'getUpdateAssessment',

                /* Add assessments to a Project
                 */
                '/assessment/{assessmentId}/new/{assessmentType}' => 'getCreateAssessmentWithinProject',


                '/assessment/{assessmentId}/status'              => 'getStatus',
                '/assessment/{assessmentId}/summary/{studentId}' => 'getSummary',
                '/assessment/{assessmentId}/submit/{studentId}'  => 'getSubmitForm',
            ],
            'POST' => [

                /* Create an assessment
                 */
                '/assessment/select' => 'postSelectAssessment',

                /* This is the second half containing assessable-specific data
                 */
                '/assessment/{assessmentId}/update' => 'postUpdateAssessment',

                /* Add assessments to a Project
                 */
                '/assessment/{assessmentId}/new/{assessmentType}' => 'postCreateAssessmentWithinProject',

                /* Delete an assessment
                 */
                '/assessment/{assessmentId}/delete' => 'postDeleteAssessment',
            ]
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }
    }

    /**
     * @inheritdoc
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '(?:getStatus|getSummary|getSubmitForm)',
            function ($params) {

                // TODO. Hydrate specified IAssessable
                $args['assessment'] = $params['assessmentId'];

                if (array_key_exists('studentId', $params)) {
                    try {
                        $args['student'] = $this->getUserMapper()->findById($params['studentId']);
                    } catch (MapperException $e) {
                        $args['student'] = $this->getUserMapper()->createModel();
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
                'folderMapper' => $this->getMapperFactory()->getLazy(FolderMapper::class),
                'errorMapper' => $this->getMapperFactory()->getLazy(ErrorMapper::class),
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
        $ruleRegexp = '(?:getStatus)';
        $this->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn());

        $ruleRegexp = '(?:getSummary|getSubmitForm)';
        $this
            ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
            ->addRoutePermission(
                $authorizer,
                $ruleRegexp,
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'studentId')
                )
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
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }
}
