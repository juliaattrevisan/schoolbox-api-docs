<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

use Schoolbox\Config\ConfigMapper;

class Calendar_Api_EventType_Event_SocsMusic_Default extends Calendar_Api_EventType_Event_Socs_Default
{
    protected function isConfigured(): bool
    {
        return (bool)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENABLED)
            && (bool)$this->configLoader->get(ConfigMapper::NAME_SOCS_IMPORT_MUSIC_LESSONS)
            && (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_HOST) !== ''
            && (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_ID) !== ''
            && (string)$this->configLoader->get(ConfigMapper::NAME_SOCS_ENDPOINT_TOKEN) !== '';
    }

    protected function getConfiguredEventTypeId(): int
    {
        return (int)$this->configLoader->get(ConfigMapper::NAME_SOCS_MUSIC_LESSONS_DEFAULT_EVENT_TYPE);
    }
}
