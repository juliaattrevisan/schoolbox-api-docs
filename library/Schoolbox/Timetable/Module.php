<?php

/**
 * Module definition for the timetable component.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Timetable;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Aura\Router\Router;
use Alaress\Twig\Loader\IChainLoader;
use External_Timetable;

class Module extends BaseModule
{

    protected $basename = 'timetable';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this
            ->addRoute($router, 'dateForTimetableDay', $basePath, '/dateForTimetableDay')
            ->addValues(array('format' => '.json'))
        ;
    }

    /**
     * Currently, any logged in user may access any part of the timetable module.
     *
     * {@inheritdoc}
     */
    public function initRoutePermissions(Authorizer $authorizer)
    {
        $this->addRoutePermission($authorizer, '.*', new Rule\IsLoggedIn());
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        /** @var External_Timetable $timetable */
        $timetable = $this->getExternalTimetable();
        return new Controller($timetable);
    }

    /**
     * @inheritdoc
     */
    public function initTemplatePaths(IChainLoader $templateLoader)
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }
}
