<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Calendar\Events;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Web\Controller\Base;
use Calendar_Api_Config_Mapper;
use Calendar_Api_EventType_Event_Schoolbox_Campus;
use InvalidArgumentException;
use Schoolbox\CampusMapper;
use Schoolbox\Filter;
use Schoolbox\Learning\Assessment\Visibility;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Role;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'learningAssessmentCalendarEvents';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function registerRoutes(): void
    {
        // Only staff can visit the page and request a report
        $routePerms = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\HasRole(Role::STAFF)
        );

        $this->register('GET', '', 'getEvents', $routePerms);
        $this->register('GET', '/grouped', 'getGroupedEvents', $routePerms);
    }


    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->injector->get('assessmentDataSource'),
            new Visibility($this->getMapperFactory()->get(Access::class)),
            $this->injector->get('filters.assessmentIdFinder'),
            $this->injector->get('filters.factoryFactory'),
            $this->getMapperFactory()->get(Filter\Selection\Mapper::class),
            $this->getMapperFactory()->get(CampusMapper::class),
            $this->getMapperFactory()->get(Calendar_Api_Config_Mapper::class)
                ->findOneForClass(Calendar_Api_EventType_Event_Schoolbox_Campus::class),
        );
    }
}
