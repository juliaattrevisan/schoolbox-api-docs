<?php

namespace Custom\Btac\Grades;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Mapper;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Twig_Loader_Chain;
use Alaress\Web\Router\PathMatcher;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Assessment\Term\Mapper as TermMapper;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;

class Module extends BaseModule
{
    protected $basename = 'customBtacGrades';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'getGrades', $basePath, '/{userId}{/termCode}');
        $this->addGetRoute($router, 'getGradesYear', $basePath, '/{userId}/year/{year}');
    }

    /**
     * These pages may be accessed by:
     *
     * * superusers
     *
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission(
            $authorizer,
            '(?:getGrades|getGradesYear)',
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
            '(?:getGrades|getGradesYear)',
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
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:' . $this->basename,
            $injector->lazyNew(Controller::class, [
                'errorMapper'           => $this->getMapperFactory()->getLazy(ErrorMapper::class),
                'defaultGradeGroup'     => $this->getInjector()->get('defaultGradeGroup'),
                'assessmentMapperProxy' => $injector->lazyGet('assessmentDataSource')
            ])
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
     * @return Mapper|UserMapper
     */
    protected function getUserMapper()
    {
        return $this->getMapperFactory()->get(UserMapper::class);
    }

    /**
     * @return Mapper|ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->getMapperFactory()->get(ErrorMapper::class);
    }

    /**
     * @return Mapper|TermMapper
     */
    protected function getTermMapper()
    {
        return $this->getMapperFactory()->get(TermMapper::class);
    }

    /**
     * @return Mapper|FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->getMapperFactory()->get(FolderMapper::class);
    }
}
