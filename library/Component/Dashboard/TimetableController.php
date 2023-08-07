<?php

/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\External\Importer\Endpoint\ValidUsesSudsTimetable;
use Schoolbox\MapperFactory;
use Schoolbox\User;

/**
 * Fulfils the Dashboard Timetable component in one of two ways:
 *   - with data from a live connector
 *   - or with data from a SUDS Timetable connector
 */
class Component_Dashboard_TimetableController extends Component_Dashboard_ControllerAbstract
{
    /**
     * Choose which data acquisition/data rendering approach to use
     * since Dashboard Timetable component fulfils either
     *
     * @var Component_Dashboard_Timetable_ComponentInterface
     */
    private Component_Dashboard_Timetable_ComponentInterface $strategy;


    /**
     * @param Component_Instance $instance
     * @param User               $user
     * @param SitePage           $sitepage
     * @param External_Timetable $externalTimetable
     */
    public function __construct(
        protected Component_Instance $instance,
        protected \Schoolbox\User $user,
        protected SitePage $sitepage,
        private External_Timetable $externalTimetable
    ) {
        parent::__construct($instance, $user, $sitepage);
    }


    /**
     * Prepares data for the render function. Business logic.
     *
     * @return bool Whether to continue on and attempt Component render
     * @throws InvalidArgumentException
     * @throws PDOException
     * @throws \DebugBar\DebugBarException
     */
    public function execute(): bool
    {
        if ($this->getUser()->getHideTimetable()) {
            return false;
        }

        /* Choose which data acquisition/data rendering approach to use
         * since Dashboard Timetable component fulfils either
         *
         * Each of these strats encompasses all it needs to procure data
         * and render it accordingly
         */
        if (
            (new ValidUsesSudsTimetable())->isSatisfiedBy(
                (string)$this->getConfigLoader()->get('external_type'),
                (string)$this->getConfigLoader()->get('schooldata_import_data_source'),
                (string)$this->getConfigLoader()->get('timetable_view'),
            )
        ) {
            /* Instead of dependency injecting through the constructor,
             * dig out this ancient ritual from the deepest Abstract
             */
            /** @var MapperFactory $mapperFactory */
            $mapperFactory = $this->sitepage->getDi()->get('mapperFactory');

            /** @var \Schoolbox\Calendar\EventTypeMapper $eventTypeMapper */
            $eventTypeMapper = $mapperFactory->get(\Schoolbox\Calendar\EventTypeMapper::class);

            /** @var \Schoolbox\External\ClassEntryMapper $classEntryMapper */
            $classEntryMapper = $mapperFactory->get(\Schoolbox\External\ClassEntryMapper::class);

            /** @var \Schoolbox\External\ClassInvolvementMapper $classInvolvementMapper */
            $classInvolvementMapper = $mapperFactory->get(\Schoolbox\External\ClassInvolvementMapper::class);

            /** @var \Schoolbox\External\Event\Mapper $eventMapper */
            $eventMapper = $mapperFactory->get(\Schoolbox\External\Event\Mapper::class);

            /** @var \Schoolbox\External\Event\Overrides\Mapper $eventOverridesMapper */
            $eventOverridesMapper = $mapperFactory->get(\Schoolbox\External\Event\Overrides\Mapper::class);

            /** @var \Timetable_ColourMapper $timetableColourMapper */
            $timetableColourMapper = $mapperFactory->get(\Timetable_ColourMapper::class);

            /** @var \Schoolbox\External\Profile\Staff\Mapper $staffProfileMapper */
            $staffProfileMapper = $mapperFactory->get(\Schoolbox\External\Profile\Staff\Mapper::class);

            $this->strategy = new Component_Dashboard_Timetable_SudsStrategy(
                $this->instance,
                $this->getUser(),
                $this->getConfigLoader(),
                $this->getAccess(),
                $this->getFolderMapper(),
                $eventTypeMapper,
                $classEntryMapper,
                $classInvolvementMapper,
                $eventMapper,
                $eventOverridesMapper,
                $timetableColourMapper,
                $staffProfileMapper,
            );
        } else {
            $this->strategy = new Component_Dashboard_Timetable_CyclicStrategy(
                $this->getConfigLoader(),
                $this->externalTimetable,
                $this->getUser(),
                $this->getFolderMapper(),
                $this->getAccess(),
                $this->instance
            );
        }

        $this->strategy->execute();

        return true;
    }


    /**
     * @param string $templateHandle
     * @return bool
     * @throws Exception
     */
    public function renderTemplate($templateHandle): bool
    {
        $renderPayload = $this->strategy->renderTemplate($templateHandle);

        if ($renderPayload) {
            return $this->renderTwigTemplate(
                $templateHandle,
                $renderPayload['template'],
                $renderPayload['data'],
                false,
                false,
                false,
                false
            );
        }

        return false;
    }
}
