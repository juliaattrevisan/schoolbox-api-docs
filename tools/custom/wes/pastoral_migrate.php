#!/usr/bin/php
<?php

/**
 * Script to roll over pastoral care records as specified in a csv file with columns:
 * Old Group Name, Campus Name, Student Year Levels, New Group Name, Add to House Group, Delete Old Group Access
 *
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Alaress\Database\Connector\IConnector;
use Schoolbox\Application\CliKernelAbstract;
use Schoolbox\Application\CliTool;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

$app = new class extends CliTool\App {

    private const OPT_LONG_FILE = 'file:';

    private array $groupNameIdMap = [];

    private array $yearNameIdMap = [];

    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_FILE,
        ];
    }

    /** @inheritDoc */
    public function run(): void
    {
        $db = $this->getDatabaseInternal();

        $inputCsv      = $this->getOpt(self::OPT_LONG_FILE);
        $scriptRunTime = Alaress_Date_Format::now();

        if (!file_exists($inputCsv)) {
            $this->getLogger()->warning('Please provide a valid filename with --file option.');
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        $handle = fopen($inputCsv, "r");

        if (!$handle) {
            $this->getLogger()->warning('Failed to open the file.');
            exit(CliKernelAbstract::EXIT_STATUS_OK);
        }

        if (!$this->perform()) {
            $this->getLogger()->info('Running in dry-run mode: run with the `-p` flag to perform the migration.');
        }

        // populate group name - id map
        $this->setGroupNameIdMap();

        // populate year name - id map
        $this->setYearNameIdMap();

        $headers = fgetcsv($handle, 1000, ",");
        $headers = array_flip($headers);

        /* Iterate over each csv row */
        while (($row = fgetcsv($handle, 1000, ",")) !== false) {
            /* GET OLD_GROUP */

            $oldGroupName = $row[$headers['Old Group Name']];

            if (!key_exists($oldGroupName, $this->groupNameIdMap)) {
                $this->getLogger()->warning("Old group $oldGroupName does not exist");
                continue;
            }

            $this->getLogger()->info("Migrating records from group $oldGroupName");

            /* GET NEW GROUP */

            $newGroupName = $row[$headers['New Group Name']];
            $this->getLogger()->info("Attempting to add to " . $newGroupName);
            $addToHouse = (bool)$row[$headers['Add to House Group']];

            if ($addToHouse && !key_exists($newGroupName, $this->groupNameIdMap)) {
                $this->getLogger()->warning("New group $newGroupName does not exist");
                continue;
            }

            /* Get records matching the filters supplied in the row */

            $pastoralAccessRecords = $this->getPastoralAccessRecords($db, $row, $headers, $this->groupNameIdMap[$oldGroupName]);

            $this->getLogger()->info(count($pastoralAccessRecords) . " to be migrated");
            $newGroupId = $this->groupNameIdMap[$newGroupName];

            if (count($pastoralAccessRecords)) {
                foreach ($pastoralAccessRecords as $pastoralAccess) {
                    $groupsToAdd   = [];
                    $groupsToAdd[] = $newGroupId;
                    if ($addToHouse == 1) {
                        $housePastoralGroupName = $this->buildHousePastoralGroupName($pastoralAccess);
                        $houseGroup             = $this->groupNameIdMap[$housePastoralGroupName];

                        if (!empty($houseGroup)) {
                            $groupsToAdd[] = $houseGroup;
                        }
                    }

                    /* INSERT ACCESS FOR NEW GROUPS */
                    foreach ($groupsToAdd as $groupId) {
                        $this->getLogger()->info(
                            "Creating new pastoral access records for "
                            . "pastoral record ({$pastoralAccess['pastoral_id']}) student id ({$pastoralAccess['student_id']}):"
                        );

                        /* Check for existing record so no dupe */
                        $params = [
                            'pastoralId' => $pastoralAccess['pastoral_id'],
                            'groupId'    => $groupId,
                        ];

                        $query = '  SELECT * FROM pastoral_access
                                WHERE pastoral_id = :pastoralId
                                AND group_id = :groupId
                                AND deleted_at IS NULL';

                        if ($db->executeQuery($query, $params)->rowCount() == 0) {
                            $params = [
                                'pastoralId'    => $pastoralAccess['pastoral_id'],
                                'groupId'       => $groupId,
                                'scriptRunTime' => $scriptRunTime->getDatabase(),
                                'accessLevel'   => $pastoralAccess['access'],
                            ];

                            if ($this->perform()) {
                                $query = '  INSERT INTO pastoral_access
                                    VALUES (NULL, :pastoralId, :groupId, NULL, NULL, :scriptRunTime, NULL, :accessLevel)';
                                $db->executeQuery($query, $params);
                                $this->getLogger()->info("row inserted");
                            }

                            $log = "Insert new pastoral access record for pastoral record ";
                            $log .= $pastoralAccess['pastoral_id'] . " (" . $pastoralAccess['heading'] . ")" . "\n";
                            $log .= "Was in pastoral group $oldGroupName \n";
                            $log .= "Now in group ";
                            $log .= array_search($groupId, $this->groupNameIdMap) . " ($groupId)\n";

                            $this->getLogger()->info($log);
                        } else {
                            $this->getLogger()->info("Already exists");
                        }
                    }

                    if ($row[$headers['Delete Old Group Access']] == 1 || $row[$headers['Delete Old Group Access']] == "Y") {
                        $this->getLogger()->info("Removing access for group: $oldGroupName");

                        if ($this->perform()) {
                            $query = 'UPDATE pastoral_access
                              SET deleted_at = :scriptRunTime
                              WHERE id = :pastoralAccessId';

                            $db->executeQuery(
                                $query,
                                [
                                    'scriptRunTime'    => $scriptRunTime->getDatabase(),
                                    'pastoralAccessId' => $pastoralAccess['pastoral_access_id'],
                                ]
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Generates pastoral group name from campus and house
     * @param array $pastoralAccess
     * @return string
     */
    private function buildHousePastoralGroupName($pastoralAccess): string
    {
        $campusPrefix = strtok($pastoralAccess['campus_name'], "_");
        $house        = strtok($pastoralAccess['house_name'], " ");
        return $campusPrefix . " " . $house . " Heads of House";
    }

    /**
     * Stores existing pastoral group name - id map
     */
    private function setGroupNameIdMap(): void
    {
        $query  = 'SELECT id, name FROM pastoral_group pg WHERE pg.deleted_at IS NULL';
        $groups = $this->getDatabaseInternal()->executeQuery($query, [])->fetchAll();
        foreach ($groups as $group) {
            $this->groupNameIdMap[$group['name']] = $group['id'];
        }
    }

    /**
     * Stores existing year level name - id map
     */
    private function setYearNameIdMap(): void
    {
        $query      = 'SELECT id, name FROM lu_year_level yl WHERE yl.deleted_at IS NULL';
        $yearLevels = $this->getDatabaseInternal()->executeQuery($query, [])->fetchAll();
        foreach ($yearLevels as $yearLevel) {
            $this->yearNameIdMap[$yearLevel['name']] = $yearLevel['id'];
        }
    }

    /**
     * Gets pastoral access with the group, year and campus
     * @param IConnector $db
     * @param array      $row
     * @param array      $headers
     * @param int        $oldGroupId
     * @return array
     */
    private function getPastoralAccessRecords($db, $row, $headers, $oldGroupId): array
    {
        /* GET PASTORAL_ACCESS WITH THAT GROUP, AND STUDENT YEAR AND CAMPUS ASSIGNED*/
        $query = '  SELECT pa.id as pastoral_access_id, pa.pastoral_id, pa.access, c.name as campus_name,
                        h.house as house_name, heading, p.student_id
                        FROM pastoral_access pa
                        JOIN  pastoral p on p.id = pa.pastoral_id
                        JOIN user s on s.id = p.student_id
                        JOIN userCampus sc on sc.uid = s.id
                        JOIN luCampus c on c.id = sc.campus
                        LEFT JOIN house h on h.id = s.house
                        WHERE pa.group_id = :oldGroupId
                        AND pa.access IN (:accessLevelPrivate)
                        AND pa.deleted_at IS NULL';

        $params = [
            'accessLevelPrivate' => PastoralAccessMapper::ACCESS_PRIVATE,
            'oldGroupId'         => $oldGroupId,
        ];

        if (!empty($row[$headers['Campus Name']])) {
            $query                .= ' AND c.name IN (:campusName)';
            $params['campusName'] = $row[$headers['Campus Name']];
        }

        if (!empty($row[$headers['Student Year Levels']])) {
            $yearLevelName = $row[$headers['Student Year Levels']];
            if (!key_exists($yearLevelName, $this->yearNameIdMap)) {
                $this->getLogger()->warning("Unable to find year level: $yearLevelName");
                return [];
            }
            $query                  .= ' AND s.year_id IN (:studentYears)';
            $params['studentYears'] = $this->yearNameIdMap[$yearLevelName];
        }


        return $db->executeQuery($query, $params)->fetchAll();
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runApp();
