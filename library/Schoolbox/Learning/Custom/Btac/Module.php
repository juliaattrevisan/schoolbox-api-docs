<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Custom\Btac;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric\RubricMapper;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Mapper;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Permission\Access\UserAccess;
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
use \Timetable_ColourMapper;

class Module extends \Schoolbox\Learning\Module
{
    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $routes = [
            'GET' => [
                '/grades-year/{userId}/year/{year}'       => 'getGradesYear',                // student && year
            ],
            'POST' => []
        ];

        foreach ($routes['GET'] as $path => $alias) {
            $this->addGetRoute($router, $alias, $basePath, $path);
        }

        foreach ($routes['POST'] as $path => $alias) {
            $this->addPostRoute($router, $alias, $basePath, $path);
        }

        parent::initRoutes($router, $basePath);
    }

    /**
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission(
                $authorizer,
                '(?:getGradesYear)',
                new Rule\Any(
                    new Rule\IsSuperuser(),
                    new Rule\HasRole(Role::STAFF),
                    new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'userId')
                )
            );

        parent::initRoutePermissions($authorizer);
    }

    /**
     * @inheritdoc
     */
    public function initRoutePreloads(PathMatcher $matcher)
    {
        $this->addRoutePreload(
            $matcher,
            '(?:getGradesYear)',
            function ($params) {
                if (array_key_exists('userId', $params)) {
                    try {
                        $args['viewee'] = $this->getUserMapper()->findById($params['userId']);
                    } catch (MapperException $e) {
                        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_USER_NOT_FOUND);
                    }
                }

                try {
                    if ($params['termCode']) {
                        $args['term'] = $this->getTermMapper()->findOneByColumn('code', $params['termCode']);
                    } else {
                        $args['term'] = $this->getTermMapper()->findForToday();
                    }

                } catch (MapperException $e) {
                    /* If nonexistent term is specified, 404
                     */
                    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
                }

                if (array_key_exists('subjectId', $params)) {
                    try {
                        $args['subject'] = $this->getFolderMapper()->findById($params['subjectId']);
                    } catch (MapperException $e) {

                        /* If nonexistent subject is specified, 404
                         */
                        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
                    }
                }

                return $args;
            }
        );

        parent::initRoutePreloads($matcher);
    }


    /**
     * @inheritdoc
     */
    public function initTemplatePaths(Twig_Loader_Chain $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/../../templates');
    }

    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set('controller:' . $this->basename,
            $injector->lazyNew(Controller::class, array_merge(parent::getControllerServices(), []))
        );
    }
}
