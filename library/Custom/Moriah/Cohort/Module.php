<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Moriah\Cohort;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Resource\Folder;
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
     * Constructor
     *
     * @param ContainerInterface $injector
     * @param UserMapper         $userMapper
     */
    public function __construct(ContainerInterface $injector, UserMapper $userMapper)
    {
        parent::__construct($injector);
        $this->userMapper = $userMapper;
    }

    /**
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'courseList', $basePath, '/course-list/{studentId}');
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
