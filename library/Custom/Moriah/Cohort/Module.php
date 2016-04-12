<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Moriah\Cohort;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Schoolbox\Resource\Folder;
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
     * @inheritdoc
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this->addGetRoute($router, 'courseList', $basePath, '/course-list/{studentId}');
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
