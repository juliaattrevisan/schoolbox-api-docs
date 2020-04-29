<?php

/**
 * Calendar_Api_Config_Connector_Schoolbox
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
class Calendar_Api_Config_Connector_Schoolbox extends Calendar_Api_Config_Connector_Abstract
{
    /**
     * Hardcoded to match db
     *
     * @var string
     */
    protected $connectorClass = 'Schoolbox';

    /**
     * {@inheritdoc}
     */
    public function getDefaultLabel()
    {
        return 'Schoolbox Events';
    }

    /**
     * Render configs into admin screen
     *
     * @param SitePage $page
     *
     * @return mixed|void
     */
    public function render($page)
    {
        $page->setVar(
            array(
                'CONNECTOR_ID'    => $this->getId(),
                'CONNECTOR_LABEL' => Scrub::text($this->getLabel()),
                'CONNECTOR_CLASS' => Scrub::text($this->getConnectorClass()),
            )
        );

        $page->setVar('has_multiple_options_block', '');

        // Should be only for submission events.
        if ($this->getEventType() === 'Submission') {
            $this->renderEventType($page, $this->getLuEventType());
        }
    }

    /**
     * Check if current user has permissions to see this event
     *
     * @param array $userCampus
     * @param int $userGroup
     *
     * @return bool
     */
    public function hasPermissions($userCampus, $userGroup)
    {
        return true;
    }
}
