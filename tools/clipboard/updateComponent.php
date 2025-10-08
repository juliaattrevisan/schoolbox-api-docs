#!/usr/bin/php
<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

/**
 * This script is to fix Fixture component pointing to deleted team after V23.0 upgrade
 */
$app = new class extends CliTool\App {
    /**
     * Run the cli tool
     */
    public function run(): void
    {
        $this->deprecationNotice("SA-19334");

        if ($this->perform()) {
            $this->getLogger()->notice('Running in perform mode (-p): changes will be written to database');
        } else {
            $this->getLogger()->notice('Running in dry-run mode: use -p to write changes to database');
        }

        // component definition id
        $query                 = "
            SELECT `id` FROM `component_definition` WHERE `msg_namespace_slug` = 'sports_fixtures';
        ";
        $componentDefinitionId = $this->getDatabaseInternal()->executeQuery($query)->fetchColumn();

        if (!$componentDefinitionId) {
            $this->getLogger()->error('Unable to get component definition id');
            exit();
        }

        // component config definition id
        $query                       = "
            SELECT id FROM `component_config_definition` WHERE `component_definition_id` = :componentDefinitionId and `key` = 'team';
        ";
        $componentConfigDefinitionId = $this->getDatabaseInternal()->executeQuery($query, ['componentDefinitionId' => $componentDefinitionId])->fetchColumn();

        if (!$componentConfigDefinitionId) {
            $this->getLogger()->error('Unable to get component config definition id');
            exit();
        }

        // get all teams currently assigned to fixture component and external_id is not set
        $query = "
            SELECT `component_config_instance`.`id` AS `config_id`,
                   `sportsfixtures_team`.`id` AS `team_id`,
                   `sportsfixtures_team`.`division_id` AS `division_id`,
                   `sportsfixtures_division`.`name` AS `division_name`,
                   `sportsfixtures_team`.`name` AS `team_name`,
                   `sportsfixtures_team`.`deleted_at` AS `deleted_date`,
                   `sportsfixtures_team`.`external_id` AS `external_id`
            FROM `component_config_instance`
                JOIN `sportsfixtures_team` ON `sportsfixtures_team`.`id` = `component_config_instance`.`value`
                JOIN `sportsfixtures_division` ON `sportsfixtures_division`.`id` = `sportsfixtures_team`.`division_id`
            WHERE `component_config_definition_id` = :componentConfigDefinitionId
              AND `sportsfixtures_team`.`external_id` IS NULL;
        ";

        $statement = $this->getDatabaseInternal()->executeQuery($query, ['componentConfigDefinitionId' => $componentConfigDefinitionId]);
        $rows      = $statement->fetchAll();

        // find newly imported team with the same name and the same division name
        $findTeamQuery = '
                SELECT `sportsfixtures_team`.`id` AS `team_id`,
                       `sportsfixtures_division`.`name` AS `division_name`
                FROM `sportsfixtures_team`
                    JOIN `sportsfixtures_division` ON `sportsfixtures_division`.`id` = `sportsfixtures_team`.`division_id`
                WHERE `sportsfixtures_team`.`name` = :teamName
                  AND `sportsfixtures_division`.`name` = :divisionName
                  AND `sportsfixtures_team`.`external_id` IS NOT NULL';

        $fixComponentQuery = 'UPDATE `component_config_instance` SET `value` = :newTeamId WHERE `id` = :configId';

        foreach ($rows as $row) {
            $this->getLogger()->info(sprintf("Component config instance ID: %d, Current Team ID %d", $row['config_id'], $row['team_id']));
            $newTeam = $this->getDatabaseInternal()->executeQuery($findTeamQuery, [
                'teamName'     => $row['team_name'],
                'divisionName' => $row['division_name'],
            ])->fetch();

            if (!$newTeam) {
                $this->getLogger()->warning("Could not find new team. Not fixing this component");
                continue;
            }
            $this->getLogger()->info(sprintf("Updating the team id from %d to %d", $row['team_id'], $newTeam['team_id']));
            if ($this->perform()) {
                $this->getDatabaseInternal()->executeQuery($fixComponentQuery, [
                    'newTeamId' => $newTeam['team_id'],
                    'configId'  => $row['config_id'],
                ]);
            }
        }

        $this->deprecationNotice("SA-19334");
    }

    /**
     * @return bool|null
     */
    public function runAsRoot(): ?bool
    {
        return null;
    }
};

/* OPTION_ERRORS => E_ALL causes all notices and errors to fatally crash your script
 *
 * You should generally try to fix those, even though in some special cases php will throw notices
 * for its own expected behaviours which will inadvertently cause this to fail
 */
$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG, CliTool\Kernel::OPTION_ERRORS => E_ALL,];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
