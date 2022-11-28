<?php

/**
 * @author      Alex Tworowsky <alex.tworowsky@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\External\Importer\Endpoint\ValidUsesSudsTimetable;
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
            )
        ) {
            $this->strategy = new Component_Dashboard_Timetable_SudsStrategy(
                $instance,
                $this->getUser(),
                $this->getConfigLoader(),
                \Schoolbox\Calendar\EventTypeMapper::getInstance(),
                \Schoolbox\External\ClassEntryMapper::getInstance(),
                \Schoolbox\External\ClassInvolvementMapper::getInstance(),
                \Schoolbox\External\Event\Mapper::getInstance(),
                \Schoolbox\External\Event\Overrides\Mapper::getInstance(),
                \Timetable_ColourMapper::getInstance(),
                \Schoolbox\External\Profile\Staff\Mapper::getInstance(),
            );
        } else {
            $this->strategy = new Component_Dashboard_Timetable_CyclicStrategy(
                $this->getConfigLoader(),
                $this->externalTimetable,
                $this->getUser(),
                $this->getFolderMapper(),
                $this->getAccess(),
                $instance
            );
        }
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
