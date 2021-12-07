<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Component\Dashboard;

use Component_Dashboard_ControllerAbstract;
use Schoolbox\Calendar\Config\Campus;
use Schoolbox\Calendar\Config\EventType;
use Schoolbox\Calendar\Config\Filter;
use Schoolbox\Calendar\Config\Group;
use Schoolbox\Calendar\Config\SourceList;

/**
 * Dashboard calendar component.
 */
class Controller extends Component_Dashboard_ControllerAbstract
{
    /**
     * @param String $templateHandle
     *
     * @return bool
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function renderTemplate($templateHandle)
    {
        /* TODO: Groups and Campus probably can remain empty
         */
        $groups = (new Group($this->getUser(), \Schoolbox\Resource\Folder\Mapper::getInstance()))->generate();
        $campus = (new Campus($this->getUser(), \Schoolbox\CampusMapper::getInstance()))->generate();

        /* These are the available options from which you can chooze
         */
        $eventType = (new EventType(\Schoolbox\Calendar\EventTypeMapper::getInstance()))->generate();
        $sourceList = (new SourceList(\Calendar_Api_Config_Mapper::getInstance(), new \Calendar\Api\ModuleChecker($this->getConfigLoader())))->generate();

        /* TODO Get these from the component config
         */
        $persistedFilters = null;

        /* Shank them into the filter class which does filter things
         */
        $filters = (new Filter($persistedFilters))->generate($campus->getData(), $groups->getData(), $eventType->getData(), $sourceList->getData());

        return $this->renderTwigTemplate(
            $templateHandle,
            '@calendar/component/dashboard.twig',
            [
                'component' => $this,
                'user' => $this->getUser(),
                'filters' => $filters
            ],
            false
        );
    }
}
