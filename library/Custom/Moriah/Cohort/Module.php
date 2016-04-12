<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Moriah\Cohort;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use External_UserInterface;
use Schoolbox\Resource\Folder;
use Schoolbox\Role;
use Schoolbox\UserMapper;
use Twig_Loader_Chain;

/**
 * Module definition for Moriah's custom cohort report.
 *
 * @package Custom\Moriah\Cohort
 */
class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'customMoriahCohort';

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var External_UserInterface
     */
    protected $externalDb;

    /**
     * Constructor
     *
     * @param ContainerInterface     $injector
     * @param UserMapper             $userMapper
     * @param External_UserInterface $externalDb
     */
    public function __construct(
        ContainerInterface $injector,
        UserMapper $userMapper,
        External_UserInterface $externalDb
    ) {
        parent::__construct($injector);
        $this->userMapper = $userMapper;
        $this->externalDb = $externalDb;
    }

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'courseList', $basePath, '/course-list/{studentId}');
    }

    /**
     * Only staff and a child's parents may access a cohort report.
     *
     * Students may NOT access their own cohort report.
     *
     * @inheritdoc
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission(
            $authorizer,
            '(?:courseList)',
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
                new Rule\IsParentOfUser($this->externalDb, $this->userMapper, 'studentId')
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
            '(?:courseList)',
            function ($params) {
                return ['student' => $this->userMapper->findById($params['studentId']),];
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
        $injector->set('controller:'.$this->basename, $injector->lazyNew(Controller::class));
    }
}
