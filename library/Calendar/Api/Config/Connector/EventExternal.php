<?php

/**
 * Event source configuration for the external database events.
 *
 * @author    Natalie Bakhtadze <natalie.bakhtadze@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */
class Calendar_Api_Config_Connector_EventExternal extends Calendar_Api_Config_Connector_Abstract
{
    /**
     * Hardcoded to match db
     *
     * @var string
     */
    protected $connectorClass = 'External';

    /**
     * {@inheritdoc}
     */
    public function getDefaultLabel()
    {
        return 'External Events';
    }

    /**
     * Render configs into admin screen. Currently, the only setting is the
     * event type.
     *
     * @param  SitePage $page
     * @return void
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
        $this->renderEventType($page, $this->getLuEventType());
    }

    /**
     * Currently all users have access to these events.
     *
     * @param array $userCampus
     * @param int $userGroup
     * @return bool
     */
    public function hasPermissions($userCampus, $userGroup)
    {
        return true;
    }
}
