<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

class Calendar_Api_Config_Connector_SocsMusic extends Calendar_Api_Config_Connector_Socs
{
    protected string $connectorClass = 'SocsMusic';

    public function getDefaultLabel(): string
    {
        return 'SOCS Music Lessons';
    }
}
