#!/usr/bin/php
<?php

use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\UploadQueue\Mapper as PastoralUploadQueueMapper;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../../conf/core/require.php';

$app = new class extends CliTool\App {

    private const OPT_LONG_FILE = 'file:';
    private const OPT_LONG_RUN = 'run';

    private const ACCESS_LEVEL_PRIVATE = 2;


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_FILE,
            self::OPT_LONG_RUN,
        ];
    }

    /** @inheritDoc */
    public function run(): void
    {
        $performUpdate = $this->getOpt(self::OPT_LONG_RUN);

        $db = $this->getDatabaseInternal();

        $inputCsv = $this->getOpt(self::OPT_LONG_FILE);
        $scriptRunTime = Alaress_Date_Format::now();

        if (($handle = fopen($inputCsv, "r")) !== FALSE) {
        } else {
            $this->getLogger()->info("no input file");
            exit;
        }

        $headers = fgetcsv($handle, 1000, ",");
        $headers = array_flip($headers);

        /* Iterate over each csv row */
        while(($row = fgetcsv($handle, 1000, ",")) !== FALSE) {

            /* GET OLD_GROUP */

            $oldGroupName = $row[$headers['Old Group Name']];

            $oldGroup = $this->getGroupFromName($oldGroupName, $db);

            if(empty($oldGroup)) {
                continue;
            }

            $this->getLogger()->info("Migrating records from group ".$oldGroup['name']);

            /* GET NEW GROUP */

            $newGroupName = $row[$headers['New Group Name']];

            $this->getLogger()->info("Attempting to add to ".$newGroupName);

            $newGroup =  $this->getGroupFromName($newGroupName, $db);

            $addToHouse = $row[$headers['Add to House Group']];

            if(empty($newGroupName) && empty($addToHouse)){
                continue;
            }

            /*Get records matching the filters supplied in the row */

            $pastoralAccessRecords = $this->getPastoralAccessRecords($db, $row, $headers, $oldGroup);

            $this->getLogger()->info(count($pastoralAccessRecords)." to be migrated");

            if(count($pastoralAccessRecords)) {
                foreach($pastoralAccessRecords as $pastoralAccess){
                    $groupsToAdd = [];
                    $groupsToAdd[] = $newGroup;
                    if($addToHouse == 1){
                        $housePastoralGroupName = $this->buildHousePastoralGroupName($pastoralAccess);
                        $houseGroup = $this->getGroupFromName($housePastoralGroupName, $db);

                        if(!empty($houseGroup)) {
                            $groupsToAdd[] = $houseGroup;
                        }
                    }

                    /* INSERT ACCESS FOR NEW GROUPS */
                    foreach($groupsToAdd as $group){

                        $this->getLogger()->info("Creating new pastoral access records for pastoral record (".
                            $pastoralAccess['pastoral_id']."):");

                        /* Check for existing record so no dupe */
                        $params = [
                            'pastoralId'    => $pastoralAccess['pastoral_id'],
                            'groupId'         => $group['id'],
                        ];

                        $query = '  SELECT * FROM pastoral_access
                                WHERE pastoral_id = :pastoralId
                                AND group_id = :groupId
                                AND deleted_at IS NULL';

                        if($db->executeQuery($query, $params)->rowCount() == 0) {

                            $params = [
                                'pastoralId'    => $pastoralAccess['pastoral_id'],
                                'groupId'       => $group['id'],
                                'scriptRunTime' => $scriptRunTime->getDatabase(),
                                'accessLevel'   => $pastoralAccess['access']
                            ];

                            if($performUpdate) {
                                $query = '  INSERT INTO pastoral_access
                                    VALUES (NULL, :pastoralId, :groupId, NULL, NULL, :scriptRunTime, NULL, :accessLevel)';
                                $db->executeQuery($query, $params);
                                $this->getLogger()->info("row inserted");
                            }

                            $log = "Insert new pastoral access record for pastoral record ";
                            $log .=  $pastoralAccess['pastoral_id']." (".$pastoralAccess['heading'].")"."\n";
                            $log .=  "Was in pastoral group ";
                            $log .=   $row[$headers['Old Group Name']]."\n";
                            $log .= "Now in group ";
                            $log .=   $group['name']." (".$group['id'].")\n";

                            $this->getLogger()->info($log);

                        } else {
                            $this->getLogger()->info("duplicate");
                        }
                    }

                    if($row[$headers['Delete Old Group Access']] == 1 || $row[$headers['Delete Old Group Access']] == "Y") {

                        $log =  "Removing access for group ";
                        $log .=  $oldGroup['name'];
                        $this->getLogger()->info($log);

                        if($performUpdate) {
                            $query = 'UPDATE pastoral_access
                              SET deleted_at = :scriptRunTime
                              WHERE id = :pastoralAccessId';

                            $db->executeQuery($query,[
                                'scriptRunTime'        => $scriptRunTime->getDatabase(),
                                'pastoralAccessId'     => $pastoralAccess['pastoral_access_id']
                            ]);

                        }
                    }
                }
            }
        }
    }

    function buildHousePastoralGroupName($pastoralAccess)
    {
        $campusPrefix = strtok($pastoralAccess['campus_name'], "_");
        $house = strtok($pastoralAccess['house_name'], " ");
        return $campusPrefix." ".$house. " Heads of House";
    }

    function getGroupFromName($name, $db)
    {
        $query = 'SELECT id, name FROM pastoral_group pg
            WHERE pg.name = :name AND  pg.deleted_at IS NULL';

        $groupCount = $db->executeQuery($query, ['name' => $name])->rowCount();

        if($groupCount != 1) {
            if($groupCount > 1) {
                $this->getLogger()->info("More than 1 group with name ".$name);
            } else {
                $this->getLogger()->info("No group with name ".$name);
            }
            $this->getLogger()->info("Skipping row");
        }

        return $db->executeQuery($query, ['name' => $name])->fetch();
    }

    function getPastoralAccessRecords($db, $row, $headers, $oldGroup)
    {
        /* GET PASTORAL_ACCESS WITH THAT GROUP, AND STUDENT YEAR AND CAMPUS ASSIGNED*/
        $query = '  SELECT pa.id as pastoral_access_id, pa.pastoral_id, pa.access, c.name as campus_name,
                        h.house as house_name, heading
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
            'accessLevelPrivate'    => self::ACCESS_LEVEL_PRIVATE,
            'oldGroupId'            => $oldGroup['id']
        ];

        if(!empty($row[$headers['Campus Name']])) {
            $query .= ' AND c.name IN (:campusName)';
            $params['campusName'] = $row[$headers['Campus Name']];
        }

        if(!empty($row[$headers['Student Year Levels']])) {
            $query .= ' AND s.year IN (:studentYears)';
            $params['studentYears'] = $row[$headers['Student Year Levels']];
        }


        return $db->executeQuery($query,$params)->fetchAll();
    }

};

$kernel = new CliTool\Kernel($app);
$kernel->runApp();
