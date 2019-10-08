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
use Aura\Di\Container;
use Aura\Di\ContainerInterface;
use Calendar_Api_Config_Mapper;
use Calendar_Api_EventType_Event_Schoolbox_Campus;
use InvalidArgumentException;
use Schoolbox\CampusMapper;
use Schoolbox\Filter;
use Schoolbox\Filter\Filters\Assessment\FactoryFactory as FilterFactoryFactory;
use Schoolbox\L10n\RosettaStone;
use Schoolbox\Learning\Assessment\DataModel as Assessment;
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
    }


    /**
     * @inheritdoc
     */
    public function initControllers(ContainerInterface $injector): void
    {
        $injector->set('controller:' . $this->basename, $injector->lazyNew(Controller::class));
    }


    /**
     * @inheritdoc
     */
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'assessmentDataSource'  => $injector->lazyGet('assessmentDataSource'),
            'visibility'            => $injector->lazy(static function () {
                return new Visibility(Access::getInstance());
            }),
            'filterFactoryFactory'  => $injector->lazy(function () use ($injector) {
                /** @var RosettaStone $rosettaStone */
                $rosettaStone = $injector->get('l10n');
                return new FilterFactoryFactory(
                    $this->getMapperFactory(),
                    $rosettaStone
                );
            }),
            'assessmentIdFinder'    => $injector->lazy(function () {
                /** @var Filter\Mapper $filterMapper */
                $filterMapper = $this->getMapperFactory()->get(Filter\Mapper::class);
                return Filter\Filters\Assessment\IdFinder::getInstance(
                    $filterMapper,
                    $this->getMapperFactory()->get(Assessment\Mapper::class)
                );
            }),
            'filterSelectionMapper' => $this->getMapperFactory()->getLazy(Filter\Selection\Mapper::class),
            'campusMapper'          => $this->getMapperFactory()->getLazy(CampusMapper::class),
            'calendarConfig'        => $injector->lazy(function () {
                return $this->getMapperFactory()->get(Calendar_Api_Config_Mapper::class)
                                                ->findOneForClass(Calendar_Api_EventType_Event_Schoolbox_Campus::class);
            }),
        ];
    }
}
