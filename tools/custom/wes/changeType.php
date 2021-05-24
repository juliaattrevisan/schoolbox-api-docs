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
        $db = $this->getDatabaseInternal();

        $inputCsv = $this->getOpt(self::OPT_LONG_FILE);

        if (($handle = fopen($inputCsv, "r")) !== false) {
        } else {
            $this->getLogger()->info("no input file");
            exit;
        }

        $headers = fgetcsv($handle, 1000, ",");
        $headers = array_flip($headers);


        /* Iterate over each csv row */
        while (($row = fgetcsv($handle, 1000, ",")) !== false) {
            /* First lets make sure that the types and subtypes exist and match*/

            $currentTypeName = $row[$headers['Current Type']];
            $currentSubtypeName = $row[$headers['Current Subtype']];

            $currentIds = $this->getTypeIdFromName($currentTypeName, $currentSubtypeName, $db);

            if ($currentIds == null) {
                continue;
            }

            /* And the new types */

            $newTypeName = $row[$headers['New Type']];
            $newSubtypeName = $row[$headers['New Subtype']];

            $newIds = $this->getTypeIdFromName($newTypeName, $newSubtypeName, $db);

            if ($newIds == null) {
                continue;
            }

            $this->getLogger()->info("Migrating records with type '" . $currentTypeName . "' and subtype '" . $currentSubtypeName . "' to type '" . $newTypeName . "' and subtype '" . $newSubtypeName . "'");

            if (self::OPT_LONG_RUN) {
                $query = 'UPDATE pastoral
                        SET type_id = :newTypeId,
                        subtype_id = :newSubtypeId,
                        updated_at = now()
                        WHERE type_id = :currentTypeId
                       AND ';

                if ($currentIds[0]['subtype_id'] == null) {
                    $query .=  "subtype_id IS :currentSubtypeId";
                } else {
                    $query .=  "subtype_id = :currentSubtypeId";
                }

                $params = [
                    'newTypeId' => $newIds[0]['type_id'],
                    'newSubtypeId' => $newIds[0]['subtype_id'],
                    'currentTypeId' => $currentIds[0]['type_id'],
                    'currentSubtypeId' => $currentIds[0]['subtype_id']
                ];

                $db->executeQuery($query, $params)->getSql();
            }
        }
    }

    function getTypeIdFromName($typeName, $subtypeName, $db)
    {
        $query = 'SELECT pt.id as type_id, pst.id as subtype_id FROM lu_pastoral_type pt
            LEFT JOIN lu_pastoral_subtype pst on pst.pastoral_type_id = pt.id
            WHERE pt.name = :typeName AND pt.deleted_at IS NULL
            AND (pst.name = :subtypeName OR pst.name is null) AND pst.deleted_at IS NULL';

        $ids =  $db->executeQuery($query, ['typeName' => $typeName, 'subtypeName' => $subtypeName])->fetchAll();

        if (count($ids) == 0) {
            $this->getLogger()->info("Invalid Type/Subtype, type " . "'" . $typeName . "' and subtype '" . $subtypeName . "'");
            return null;
        } else if (count($ids) > 1) {
            $this->getLogger()->info("More than 1 combination of" . "'" . $typeName . "' and subtype '" . $subtypeName . "'");
            return null;
        }

        $this->getLogger()->info("Found combination of" . "'" . $typeName . "' and subtype '" . $subtypeName . "'");
        return $ids;
    }

    function getRecordIdsFromTypeName($typeName, $subtypeName, $db)
    {
        $query = 'SELECT p.id FROM pastoral p
            JOIN lu_pastoral_type pt on  pt.id = p.type_id
            LEFT JOIN lu_pastoral_subtype pst on pst.id = p.subtype_id and pt.id = pst.pastoral_type_id
            WHERE pt.name = :typeName AND pt.deleted_at IS NULL
            AND (pst.name = :subtypeName OR pst.name is null) AND pst.deleted_at IS NULL';


        return $db->executeQuery($query, ['typeName' => $typeName, 'subtypeName' => $subtypeName])->fetchAll();
    }
};

$kernel = new CliTool\Kernel($app);
$kernel->runApp();
