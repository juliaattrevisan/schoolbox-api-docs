#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {

    public function runAsRoot(): ?bool
    {
        // doesn't matter
        return null;
    }


    public function run(): void
    {
        if ($this->did22_0_4_sqlUpgradesFail()) {
            $this->getLogger()->notice("22.0.4 upgrades missing, re-running");
            $this->run22_0_4_sqlUpgrades();
        } else {
            $this->getLogger()->notice("22.0.4 upgrades appear present, skipping");
        }
    }

    private function did22_0_4_sqlUpgradesFail(): bool
    {
        $sql   = "
            SELECT COUNT(*) FROM config WHERE name = 'ups_cron_run_time'
        ";
        $count = (string)$this->getDatabaseInternal()->executeQuery($sql)->fetch(PDO::FETCH_COLUMN);

        return $count === '0';
    }

    private function run22_0_4_sqlUpgrades(): void
    {
        $this->addLissEventTypes();
        $this->addCronHour();
        $this->eportfolioConfig();
        $this->intellischoolHomepageComponent();
        $this->parentDueworkAccessText();
    }

    private function addLissEventTypes(): void
    {
        # Create a hole for external_liss_exclusions
        $sql = "
UPDATE config c
INNER JOIN lu_config_type lct on c.type_id = lct.id
SET c.sequence = c.sequence + 2
WHERE lct.type = 'External DB'
AND c.sequence > (SELECT c2.sequence FROM config c2 WHERE c2.name='external_liss_calendar_owner' LIMIT 1);

-- A config field containing event types
-- (a csv separated string)
-- These event types are skipped during imports

INSERT INTO `config`
    (`name`, `value`, `title`, `description`, `options`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
       ('external_liss_exclusions',
        'Duty, Study, RollClass',
        'External LISS Event Exclusions',
        'These event types will be excluded from import; separate multiple with commas.',
        '',
        (SELECT id FROM lu_config_field WHERE field = 'input'),
        (SELECT id FROM lu_config_type WHERE type = 'External DB'),
        'true',
        'false',
        'false',
        'settings',
        (SELECT c.sequence+1 FROM config c WHERE c.name='external_liss_calendar_owner' LIMIT 1)
    );

-- A config field containing event types
-- This event type is assigned to events which don't have one

INSERT INTO `config`
    (`name`, `value`, `title`, `description`, `options`, `field_id`, `type_id`, `editable`, `hidden`, `required`, `module`, `sequence`)
VALUES
       ('external_liss_default_event_type',
        '',
        'External LISS Default Event Type',
        'LISS events that did not have a type will be assigned the one you specify here. Leave it blank to skip importing events which do not have a type.',
        '',
        (SELECT id FROM lu_config_field WHERE field = 'input'),
        (SELECT id FROM lu_config_type WHERE type = 'External DB'),
        'true',
        'false',
        'false',
        'settings',
        (SELECT c.sequence+2 FROM config c WHERE c.name='external_liss_calendar_owner' LIMIT 1)
    );

-- Fix calendar owner appearance

UPDATE `config` SET `title` = 'External LISS Calendar Owner' WHERE (`name` = 'external_liss_calendar_owner');

        ";

        $this->getDatabaseInternal()->executeQuery($sql);
    }


    private function addCronHour(): void
    {
        # Migrate UPS run times from Infra (puppet yaml file) to Product (config table)
        $sql = "
INSERT INTO `config` (
    `name`,
    `value`,
    `title`,
    `description`,
    `field_id`,
    `type_id`,
    `editable`,
    `required`,
    `module`,
    `sequence`
) VALUES (
    'ups_cron_run_time',
    -- These times have been parsed from puppet YAML files.
    (SELECT * FROM
        (SELECT
             CASE
                -- staging instances
                WHEN value = 'stg' THEN (
                    SELECT * FROM (
                        SELECT CASE
                            WHEN value = 'canbggs' THEN '04:00'
                            ELSE '22:00'
                        END
                        FROM `config` WHERE name = 'site_school_code') t2
                    )
                -- production instances
                WHEN value = 'prod' THEN (
                    SELECT * FROM (
                        SELECT CASE
                            WHEN value = 'fgs'     THEN '15:00'
                            WHEN value = 'lgs'     THEN '02:00'
                            WHEN value = 'fct'     THEN '03:00'
                            WHEN value = 'kgsl'    THEN '04:00'
                            WHEN value = 'spags'   THEN '22:00'
                            WHEN value = 'tsc'     THEN '06:00'
                            WHEN value = 'mggs'    THEN '02:00'
                            WHEN value = 'wcs'     THEN '05:00'
                            WHEN value = 'wes'     THEN '04:00'
                            WHEN value = 'dcl'     THEN '01:00'
                            WHEN value = 'tlcmild' THEN '01:00'
                            WHEN value = 'isascis' THEN '02:00'
                            WHEN value = 'htlc'    THEN '01:00'
                            WHEN value = 'shcl'    THEN '02:00'
                            WHEN value = 'ccgsl'   THEN '14:00'
                            WHEN value = 'saags'   THEN '04:00'
                            WHEN value = 'lhags'   THEN '05:00'
                            WHEN value = 'smags'   THEN '04:00'
                            WHEN value = 'luc'     THEN '01:00'
                            ELSE '22:00'
                        END
                        FROM `config` WHERE name = 'site_school_code') t3
                    )
                ELSE '22:00'
            END
        FROM `config` WHERE name = 'site_type') t1
    ),
    'User Provisioning System daily run time',
    'Time of day to schedule User Provisioning System. Please allow 30min for changes to this field to take effect.',
    (SELECT id FROM lu_config_field WHERE field = 'timepicker'),
    (SELECT id FROM lu_config_type WHERE `type` = 'External DB'),
    'true',
    'false',
    'settings',
    75
);

        ";
        $this->getDatabaseInternal()->executeQuery($sql);
    }


    private function eportfolioConfig(): void
    {
        // -- add eportfolio to config type table so we can use the /settings/EPortfolio route
        $sql = "
INSERT INTO lu_config_type (type, sequence)
VALUES ('ePortfolio', 21);

-- add eportfolio subject / groups config
INSERT INTO config (name, value, title, description, field_id, options, type_id, editable, hidden, required,
                    module, sequence)
VALUES ('eportfolio_show_subjects_and_groups', '1', 'Show Subjects and Groups on ePortfolio',
        'If toggled to ON, a students classes and group membership will be featured on the ePortfolio homepage and be visible to all users.',
        (select id from lu_config_field where field = 'boolean'), null, (select id from lu_config_type where type = 'ePortfolio'), 'true', 'false', 'false', 'settings', null);

        ";
        $this->getDatabaseInternal()->executeQuery($sql);
    }


    private function intellischoolHomepageComponent(): void
    {
        $sql = "
INSERT INTO component_definition (name, class, description, module, component_category, enabled, visible,
                                            multiple, msg_namespace_slug, msg_namespace_slug_suffix, config_copy)
VALUES ('Intellischool Launcher', 'Schoolbox\\Intellischool\\Component\\Homepage\\Controller',
        'Displays the Intellischool dashboard on homepages', 'homepage', 1, 1, 'false', 'false', null, null, null);

-- all homepage components require at least 1 config value to function...
INSERT INTO component_config_definition (component_definition_id, `key`, default_value, label, description)
VALUES ( (SELECT id from component_definition where class = 'Schoolbox\\Intellischool\\Component\\Homepage\\Controller')
        , 'componentname', 'Intellischool Launcher', 'Heading', null);

        ";
        $this->getDatabaseInternal()->executeQuery($sql);
    }


    private function parentDueworkAccessText(): void
    {
        $sql = "
UPDATE config
    SET title = 'Hide Due Work from Parents',
    description = 'If toggled to ''ON'', parents will be prevented from being able to access the Due work calendar via the My Students Component.  They will also not receive notifications about due work.'
WHERE name = 'restrict_parent_duework_access'
        ";
        $this->getDatabaseInternal()->executeQuery($sql);
    }
};

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app))->runApp();
