<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * Fixtures calendar subscription
 */
class Calendar_Api_Config_Connector_Fixtures extends Calendar_Api_Config_Connector_Abstract
{
    /**
     * Hardcoded to match db
     *
     * @var string
     */
    protected string $connectorClass = 'Schoolbox';


    /**
     * {@inheritdoc}
     */
    public function getDefaultLabel(): string
    {
        return 'Fixtures';
    }


    /**
     * Render configs into admin screen
     *
     * @param SitePage $page
     *
     * @return void
     */
    public function render($page): void
    {
        $page->setVar([
            'CONNECTOR_ID'    => $this->getId(),
            'CONNECTOR_LABEL' => Scrub::text($this->getLabel()),
            'CONNECTOR_CLASS' => Scrub::text($this->getConnectorClass()),
        ]);

        $page->setVar('has_multiple_options_block', '');
        $this->renderEventType($page, $this->getLuEventType());
    }


    /**
     * Check if current user has permissions to see this event
     *
     * @param array $userCampus
     * @param int $userGroup
     *
     * @return bool
     */
    public function hasPermissions($userCampus, $userGroup): bool
    {
        return true;
    }
}
