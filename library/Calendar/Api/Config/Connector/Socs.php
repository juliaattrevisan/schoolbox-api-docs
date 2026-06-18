<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

class Calendar_Api_Config_Connector_Socs extends Calendar_Api_Config_Connector_Abstract
{
    protected string $connectorClass = 'Socs';

    public function getDefaultLabel(): string
    {
        return 'SOCS Events';
    }

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

    public function hasPermissions($userCampus, $userGroup)
    {
        return true;
    }
}
