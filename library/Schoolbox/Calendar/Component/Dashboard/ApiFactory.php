<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Calendar\Component\Dashboard;

use Calendar\Api\ModuleChecker;

/**
 * Class ApiFactory
 *
 * A unique implementation of Calendar_Api_Factory
 *
 * Finds which subscriptions are enabled
 */
class ApiFactory
{

    /**
     * A list of all the possible event types to display
     *
     * @var array|null
     */
    private ?array $allTypes = null;


    /**
     * @var ModuleChecker
     */
    private ModuleChecker $moduleChecker;


    /**
     * Constructor
     */
    public function __construct(ModuleChecker $moduleChecker)
    {
        $this->moduleChecker = $moduleChecker;
    }


    /**
     * Get a list of all the calendar subscriptions that were enabled
     *
     * @return array
     */
    public function generateAvailableSubscriptions(): array
    {
        /* Only compute this once
         */
        if (null === $this->allTypes) {
            $this->allTypes = [];

            $allConfigRecords = \Calendar_Api_Config_Mapper::getInstance()->getAllConfigRecords();

            /* These configs are base64-encode()'d to get around a weird issue with unserialize
             * http://stackoverflow.com/questions/4264270/unserialize-sometimes-returns-false
             */
            foreach ($allConfigRecords as $currentConfig) {
                $config = $currentConfig->decodeConfig();

                try {
                    /* Also check if the module was enabled as part of a Plan
                     * Some modules won't be available in the current schoolbox Plan
                     */
                    if ($this->moduleChecker->isEnabled($config)) {
                        /* Update $config so it knows the calendar_connector id from whence it came
                         * This is used in produce() and passed to each unique/default implementation
                         */
                        $config->setId($currentConfig->getId());

                        $this->allTypes[] = $config;
                    }
                } catch (\TypeError $e) {
                    // I will not buy this record; it is scratched
                }
            }
        }

        return $this->allTypes;
    }
}