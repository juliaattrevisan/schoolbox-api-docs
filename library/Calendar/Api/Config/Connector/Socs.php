<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

/**
 * Calendar connector configuration for SOCS calendar events.
 */
class Calendar_Api_Config_Connector_Socs extends Calendar_Api_Config_Connector_Abstract
{
    public const string CONNECTOR_CLASS = 'Socs';

    protected string $connectorClass = self::CONNECTOR_CLASS;

    /**
     * Return the default label used for the SOCS calendar source.
     */
    public function getDefaultLabel(): string
    {
        return 'SOCS Events';
    }

    /**
     * Render SOCS connector options in the legacy calendar connector UI.
     */
    public function render($page)
    {
        $page->setVar([
            'CONNECTOR_ID' => $this->getId(),
            'CONNECTOR_LABEL' => Scrub::text($this->getLabel()),
            'CONNECTOR_CLASS' => Scrub::text($this->getConnectorClass()),
        ]);
        $page->setVar('has_multiple_options_block', '');
        $this->renderEventType($page, $this->getLuEventType());
    }

    /**
     * SOCS calendar visibility is filtered by event campus rather than connector permissions.
     */
    public function hasPermissions($userCampus, $userGroup)
    {
        return true;
    }
}
