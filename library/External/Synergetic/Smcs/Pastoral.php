<?php
/**
 *
 * @author      Doug MacKenzie <doug.mackenzie@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\User;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Pastoral\Log\Model as PastoralLog;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;

class External_Smcs_Pastoral_Class extends External_Synergetic_Pastoral
{
    /** @var string $sqlPastoralSelect */
    private $sqlPastoralSelect = "
        SELECT        dbo.tPastoralCare.PastoralCareSeq, 
                      dbo.tPastoralCare.ID, 
                      dbo.tPastoralCare.ClassCode, 
                      dbo.tPastoralCare.ItemDate, 
                      dbo.tPastoralCare.MethodCode,
                      dbo.tPastoralCare.Details, 
                      dbo.tPastoralCare.PrivateDetails, 
                      dbo.tPastoralCare.InitiatorCode, 
                      dbo.tPastoralCare.TypeCode,
                      dbo.luPastoralCareType.Description AS TypeDescription,
                      dbo.tPastoralCare.CategoryCode,
                      dbo.luPastoralCareCategory.Description AS CategoryDescription,
                      dbo.tPastoralCare.ClassificationCode, 
                      dbo.tPastoralCare.IsFollowUpFlag, 
                      dbo.tPastoralCare.FollowUpDate, 
                      dbo.tPastoralCare.CreatedDate, 
                      dbo.tPastoralCare.CreatedByID, 
                      dbo.tPastoralCare.ModifiedDate, 
                      dbo.tPastoralCare.ModifiedByID,
                      dbo.tPastoralCare.EmailSentDate, 
                      dbo.tPastoralCare.EmailSentByID, 
                      dbo.tPastoralCare.DuplicateFromSeq, 
                      dbo.tPastoralCare.SubjectClassesSeq,
                      Community_1.Surname AS StudentLastname, 
                      Community_1.Given1 AS StudentFirstname,
                      dbo.Community.Surname AS OwnerLastname, 
                      dbo.Community.Given1 AS OwnerFirstname
        FROM          dbo.tPastoralCare 
        LEFT JOIN     dbo.luPastoralCareType ON dbo.tPastoralCare.TypeCode = dbo.luPastoralCareType.Code
        LEFT JOIN     dbo.luPastoralCareCategory ON dbo.tPastoralCare.CategoryCode = dbo.luPastoralCareCategory.Code
        INNER JOIN    dbo.Community ON dbo.tPastoralCare.CreatedByID = dbo.Community.ID 
        INNER JOIN    dbo.Community AS Community_1 ON dbo.tPastoralCare.ID = Community_1.ID
        INNER JOIN    dbo.uvStudents ON dbo.uvStudents.ID = dbo.tPastoralCare.ID
        INNER JOIN    dbo.FileSemesters ON 
                          dbo.FileSemesters.FileYear = dbo.uvStudents.FileYear AND
                          dbo.FileSemesters.FileSemester = dbo.uvStudents.FileSemester AND
                          dbo.FileSemesters.SystemCurrentFlag = 1
    ";

    /**
     * @inheritdoc
     */
    public function getPastoral(Pastoral $pastoral)
    {
        $query = "{$this->sqlPastoralSelect} WHERE dbo.tPastoralCare.PastoralCareSeq = :externalId";

        return $this->connector->executeQuery($query)->fetch();
    }

    /**
     * @inheritdoc
     */
    public function importPastoral(User $user, User $defaultOwner, $importParentAccess)
    {
        // Give the import 30 minutes to complete and bump up the memory limit just to make sure we don't bomb out

        ini_set('memory_limit', '2048M');
        set_time_limit(30 * 60);

        // Log start of import

        $importId = $this->getNewImportId();

        $log = $this->pastoralLogMapper->createModel();
        $log->setImportId($importId);
        $log->setUserId($user->getId());
        $log->setPastoralId(null);
        $log->setHeading("Pastoral import started ({$user->getFullName()})");
        $log->setStudentName(null);
        $log->setCreatorName(null);
        $log->setResult("Parent Importing " . ($importParentAccess ? 'Enabled' : 'Disabled'));
        $this->getPastoralLogMapper()->save($log);

        // Only load a severity if it is enabled by policy

        $severity = $this->getPastoralSeverity();

        // Retrieve an iterator for all current students pastoral care records from Synergetic sorted by student name as
        // it makes searching the import log much easier.

        $highestPastoralSeq = 0;

        // Keep the pastoral records we create so we can add audience members and remove the need for MARS

        $pastoralRecordsCreated = [];

        do {
            $queryRecords =
                "
                {$this->sqlPastoralSelect}
                WHERE         PastoralCareSeq > {$highestPastoralSeq}
                ORDER BY      dbo.tPastoralCare.PastoralCareSeq
            ";

            // Chunk the import without using the iterator, because its failing miserably for some reason

            $queryRecords = $this->connector->executeQuery(str_replace("SELECT", "SELECT TOP 5", $queryRecords));
            $records = $queryRecords->fetchAll();

            foreach ($records as $record) {
                // Capture the pastoral sequence so we can iterate

                $highestPastoralSeq = $record["PastoralCareSeq"];


                // Remove the Synergetic magic blank value

                $record["Details"] = trim(($record["Details"]));

                if ($record["Details"] == self::BLANK_PRIVATE_RECORD) {
                    $record["Details"] = '';
                }

                $this->htmlConverter->setHtml($record["Details"]);
                $record["Details"] = trim($this->htmlConverter->getText());
                $record["Details"] = html_entity_decode($record["Details"], ENT_QUOTES);

                $this->htmlConverter->setHtml($record["PrivateDetails"]);
                $record["PrivateDetails"] = trim($this->htmlConverter->getText());
                $record["PrivateDetails"] = html_entity_decode($record["PrivateDetails"], ENT_QUOTES);

                if ($record["PrivateDetails"] == self::BLANK_PRIVATE_RECORD) {
                    $record["PrivateDetails"] = '';
                }

                // Create a heading from the public details in Synergetic

                $heading = substr(trim($record["Details"]), 0, 50);

                if (empty($heading)) {
                    $heading = "Untitled Synergetic Record (Seq: {$record["PastoralCareSeq"]})";
                }

                // Create a new log entry

                $logRecord = $this->pastoralLogMapper->createModel();
                $logRecord->setImportId($importId);
                $logRecord->setUserId($user->getId());
                $logRecord->setHeading($heading);
                $logRecord->setStudentName("{$record["StudentLastname"]}, {$record["StudentFirstname"]}");
                $logRecord->setCreatorName("{$record["OwnerLastname"]}, {$record["OwnerFirstname"]}");

                try {
                    $student = $this->getSchoolboxUser($record["ID"]);
                    $owner = $this->getSchoolboxUser($record["CreatedByID"]) ?: $defaultOwner;

                    if (!$student instanceof User) {
                        // The student of the pastoral care record is not available in Schoolbox, skip this record

                        $logRecord->setResult("Skipped Unknown Student ({$record["PastoralCareSeq"]})");
                        $this->getPastoralLogMapper()->save($logRecord);

                        continue;
                    }

                    $logRecord->setCreatorName($owner->getFullName());
                    $logRecord->setStudentName($student->getFullName());
                    $logRecord->setResult("Importing ({$record["PastoralCareSeq"]})");
                    $this->getPastoralLogMapper()->save($logRecord);

                    // If this pastoral care record already exists in Schoolbox, delete it so we don't end up with duplicate
                    // records. Could update the record, but its contents may have deviated it was originally imported, and
                    // deleting will at least keep that history

                    $this->getPastoralMapper()->deleteByColumn("external_id", $record["PastoralCareSeq"]);

                    // Only create record if both public and private aren't blank

                    if (!empty($record["Details"]) || !empty($record["PrivateDetails"])) {
                        /** @var Pastoral $pastoralRecord */
                        $pastoralRecord = $this->getPastoralMapper()->createModel();
                        $pastoralRecord->setExternalId($record["PastoralCareSeq"]);
                        $pastoralRecord->setOwner($owner);
                        $pastoralRecord->setStudent($student);
                        $pastoralRecord->setHeading($heading);
                        $pastoralRecord->setBodyPublic("<p>{$record["Details"]}</p>");
                        $pastoralRecord->setBodyPrivate("<p>{$record["PrivateDetails"]}</p>");
                        $pastoralRecord->setType($this->getPastoralType($record));
                        $pastoralRecord->setSubType($this->getPastoralSubType($record));

                        if ($this->config->get('pastoral_severity')) {
                            $pastoralRecord->setSeverity($severity);
                        }

                        $this->getPastoralMapper()->save($pastoralRecord);
                        $logRecord->setResult("OK");
                        $logRecord->setPastoralId($pastoralRecord->getId());

                        $pastoralRecordsCreated[] = $pastoralRecord;
                    } else {
                        // Skip blank records

                        $logRecord->setResult("Skipped Blank Record ({$record["PastoralCareSeq"]})");
                    }
                } catch (Exception $e) {
                    // If ANYTHING goes wrong during the import we need to log it. The import is running in a detached
                    // state, so there is no other way to communicate with the user, so an exception would leave the user
                    // thinking the import is still running.

                    $logRecord->setResult("Import Error: {$e->getMessage()}");
                }

                $this->getPastoralLogMapper()->save($logRecord);
            }
        } while (count($records) > 0);

        // Import audience members for each record we created

        foreach ($pastoralRecordsCreated as $pastoralRecord) {
            $queryAudience = $this->connector->executeQuery(
                "
                    SELECT  ID 
                    FROM    PastoralCareContacts 
                    WHERE   PastoralCareSeq = :pastoralCareSeq
                ",
                ['pastoralCareSeq' => $pastoralRecord->getExternalId()]
            );

            $audienceRecords = $queryAudience->fetchAll();

            $queryAudience->getPDOStatement()->errorCode();

            // Cache the access records in case we need to create identical ones for the private record, will

            foreach ($audienceRecords as $audience) {
                /** @var PastoralLog $logAccess */
                $logAccess = $this->getPastoralLogMapper()->createModel();
                $logAccess->setImportId($importId);
                $logAccess->setUserId($user->getId());
                $logAccess->setPastoralId($pastoralRecord->getId());
                $logAccess->setCreatorName($pastoralRecord->getOwner()->getFullName());
                $logAccess->setStudentName($pastoralRecord->getStudent()->getFullName());

                $audienceUser = $this->getSchoolboxUser($audience["ID"]);

                if ($audienceUser instanceof User) {

                    // If the school does not want parents given access to pastoral records, then don't add them

                    if ($importParentAccess || $audienceUser->getRole()->isStaff()) {
                        /** @var PastoralAccess $accessRecord */
                        $accessRecord = $this->getPastoralAccessMapper()->createModel();
                        $accessRecord->setPastoral($pastoralRecord);
                        $accessRecord->setAccess(PastoralAccessMapper::ACCESS_PUBLIC);
                        $accessRecord->setUser($audienceUser);
                        $this->getPastoralAccessMapper()->save($accessRecord);

                        $logAccess->setResult("Audience: {$audienceUser->getFullName()}");
                    } else {
                        $logAccess->setResult("Skipped Audience: {$audienceUser->getFullName()}");
                    }

                } else {
                    $logAccess->setResult("Error Audience: {$audience["ID"]}");
                }

                $this->getPastoralLogMapper()->save($logAccess);
            }
        }

        $log = $this->getPastoralLogMapper()->createModel();
        $log->setImportId($importId);
        $log->setUserId($user->getId());
        $log->setHeading("Import Completed");
        $log->setResult("Completed");
        $this->getPastoralLogMapper()->save($log);
    }
}