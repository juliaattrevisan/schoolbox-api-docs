<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

class Calendar_Api_Config_Connector_SocsMusic extends Calendar_Api_Config_Connector_Socs
{
    public const string CONNECTOR_CLASS = 'SocsMusic';

    protected string $connectorClass = self::CONNECTOR_CLASS;

    public function getDefaultLabel(): string
    {
        return 'SOCS Music Lessons';
    }
}
