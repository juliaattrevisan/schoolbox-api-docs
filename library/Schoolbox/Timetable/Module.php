<?php
/**
 * Module definition for the timetable component.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Timetable;

use Alaress\Authorizer\Authorizer;
use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Router\PathMatcher;
use Aura\Di\ContainerInterface;
use Aura\Router\Router;
use Component_Instance;
use Component_InstanceMapper;
use External_Timetable;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\Permission\Access\RoleAccessMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Storage;
use Twig_Loader_Chain;
use Twig_Loader_Filesystem;

class Module extends BaseModule
{
    /**
     * Dependency on external timetable database.
     *
     * @var External_Timetable
     */
    protected $externalDb;

    protected $basename = 'timetable';

    /**
     * {@inheritdoc}
     */
    public function initRoutes(Router $router, $basePath)
    {
        $this
            ->addRoute($router, 'dateForTimetableDay', $basePath, 'dateForTimetableDay')
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
        $this->addRoutePermissionForBasename($authorizer, '.*', new Rule\IsLoggedIn());
    }

    /**
     * {@inheritdoc}
     */
    public function initControllers(ContainerInterface $injector)
    {
        $injector->set(
            'controller:'.$this->basename,
            $injector->lazyNew(
                Controller::class,
                ['externalTimetable' => $this->getExternalDb()]
            )
        );
    }

    /**
     * @param  External_Timetable $externalDb
     * @return $this
     */
    public function setExternalDb($externalDb)
    {
        $this->externalDb = $externalDb;
        return $this;
    }

    /**
     * @return External_Timetable
     */
    public function getExternalDb()
    {
        return $this->externalDb;
    }
}
