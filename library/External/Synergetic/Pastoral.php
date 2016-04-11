<?php
/**
 *
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 */

use Schoolbox\UserMapper;
use Schoolbox\Pastoral\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Type\Model as PastoralTypeModel;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccessModel;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverityModel;
use Alaress\DebugBar\Instance as DebugBar;

class External_Synergetic_Pastoral implements External_PastoralInterface
{
    /**
     * @var MSSQL
     */
    private $database;

    /** @var UserMapper $userMapper */
    protected $userMapper;

    /** @var PastoralMapper $pastoralMapper */
    protected $pastoralMapper;

    /** @var PastoralTypeMapper $pastoralTypeMapper */
    protected $pastoralTypeMapper;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    protected $pastoralAccessMapper;

    /** @var PastoralAttachmentMapper $pastoralAttachmentMapper */
    protected $pastoralAttachmentMapper;

    /** @var PastoralSeverityMapper $pastoralSeverityMapper */
    protected $pastoralSeverityMapper;

    /**
     * @return UserMapper
     */
    public function getUserMapper() {
        return $this->userMapper;
    }

    /**
     * @param $userMapper
     * @return $this
     */
    public function setUserMapper($userMapper) {
        $this->userMapper = $userMapper;
        return $this;
    }

    /**
     * @return PastoralMapper
     */
    public function getPastoralMapper() {
        return $this->pastoralMapper;
    }

    /**
     * @param $pastoralMapper
     * @return $this
     */
    public function setPastoralMapper($pastoralMapper) {
        $this->pastoralMapper = $pastoralMapper;
        return $this;
    }

    /**
     * @return PastoralTypeMapper
     */
    public function getPastoralTypeMapper() {
        return $this->pastoralTypeMapper;
    }

    /**
     * @param $pastoralTypeMapper
     * @return $this
     */
    public function setPastoralTypeMapper($pastoralTypeMapper) {
        $this->pastoralTypeMapper = $pastoralTypeMapper;
        return $this;
    }

    /**
     * @return PastoralAccessMapper
     */
    public function getPastoralAccessMapper() {
        return $this->pastoralAccessMapper;
    }

    /**
     * @param $pastoralAccessMapper
     * @return $this
     */
    public function setPastoralAccessMapper($pastoralAccessMapper) {
        $this->pastoralAccessMapper = $pastoralAccessMapper;
        return $this;
    }

    /**
     * @return PastoralAttachmentMapper
     */
    public function getPastoralAttachmentMapper() {
        return $this->pastoralAttachmentMapper;
    }

    /**
     * @param $pastoralAttachmentMapper
     * @return $this
     */
    public function setPastoralAttachmentMapper($pastoralAttachmentMapper) {
        $this->pastoralAttachmentMapper = $pastoralAttachmentMapper;
        return $this;
    }

    /**
     * @return PastoralSeverityMapper
     */
    public function getPastoralSeverityMapper() {
        return $this->pastoralSeverityMapper;
    }

    /**
     * @param $pastoralSeverityMapper
     * @return $this
     */
    public function setPastoralSeverityMapper($pastoralSeverityMapper) {
        $this->pastoralSeverityMapper = $pastoralSeverityMapper;
        return $this;
    }    

    /**
     * Constructor

     * @throws Exception
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setPastoralMapper(PastoralMapper::getInstance());
        $this->setPastoralTypeMapper(PastoralTypeMapper::getInstance());
        $this->setPastoralAccessMapper(PastoralAccessMapper::getInstance());
        $this->setPastoralSeverityMapper(PastoralSeverityMapper::getInstance());

        if (DebugBar::isEnabled()) {
            $this->getDatabase()->installCollector();
            /** @var \DebugBar\DataCollector\DataCollectorInterface $collector */
            $collector = $this->getDatabase()->getCollector();
            if (!DebugBar::getInstance()->hasCollector($collector->getName())) {
                DebugBar::getInstance()->addCollector($collector);
            }
        }
    }

    /**
     * Returns the pastoral record if found in remote system
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return array
     */
    public function getPastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        $query = "SELECT PastoralCareSeq,
                          ID,
                          ClassCode,
                          ItemDate,
                          MethodCode,
                          Details,
                          PrivateDetails,
                          InitiatorCode,
                          TypeCode,
                          CategoryCode,
                          ClassificationCode,
                          IsFollowUpFlag,
                          FollowUpDate,
                          ConfidentialFlag,
                          CreatedDate,
                          CreatedByID,
                          ModifiedDate,
                          ModifiedByID,
                          EmailSentDate,
                          EmailSentByID,
                          DuplicateFromSeq,
                          SubjectClassesSeq
                  FROM tPastoralCare
                  WHERE PastoralCareSeq = " . $this->getPastoralIdentifier($pastoral);

        return $this->getDatabase()->getAll($query);
    }

    /**
     * Returns the external ID used to identify this pastoral record
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return string
     */
    private function getPastoralIdentifier(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        return ($pastoral->getExternalId());
    }

    /**
     * Does an import of all pastoral care records stored in Synergetic
     */
    public function importPastoral()
    {
        // Get all pastoral care records from Synergetic

        $query = "
SELECT        dbo.tPastoralCare.PastoralCareSeq, dbo.tPastoralCare.ID, dbo.tPastoralCare.ClassCode, dbo.tPastoralCare.ItemDate, dbo.tPastoralCare.MethodCode,
                         dbo.tPastoralCare.Details, dbo.tPastoralCare.PrivateDetails, dbo.tPastoralCare.InitiatorCode, dbo.tPastoralCare.TypeCode, dbo.tPastoralCare.CategoryCode,
                         dbo.tPastoralCare.ClassificationCode, dbo.tPastoralCare.IsFollowUpFlag, dbo.tPastoralCare.FollowUpDate, dbo.tPastoralCare.ConfidentialFlag,
                         dbo.tPastoralCare.CreatedDate, dbo.tPastoralCare.CreatedByID, dbo.tPastoralCare.ModifiedDate, dbo.tPastoralCare.ModifiedByID,
                         dbo.tPastoralCare.EmailSentDate, dbo.tPastoralCare.EmailSentByID, dbo.tPastoralCare.DuplicateFromSeq, dbo.tPastoralCare.SubjectClassesSeq,
                         dbo.luPastoralCareType.Description AS TypeDescription, Community_1.Surname AS StudentLastname, Community_1.Given1 AS StudentFirstname,
                         dbo.Community.Surname AS OwnerLastname, dbo.Community.Given1 AS OwnerFirstname
FROM            dbo.tPastoralCare INNER JOIN
                         dbo.luPastoralCareType ON dbo.tPastoralCare.TypeCode = dbo.luPastoralCareType.Code INNER JOIN
                         dbo.Community ON dbo.tPastoralCare.CreatedByID = dbo.Community.ID INNER JOIN
                         dbo.Community AS Community_1 ON dbo.tPastoralCare.ID = Community_1.ID
        ";

        $records = $this->getDatabase()->getAll($query);

        $owner = null;
        $student = null;

        // Give the import 5 minutes to complete

        set_time_limit(60 * 5);

        try
        {
            // Default all imported items to the lowest priority as there is no concept of this in Synergetic

            /** @var PastoralSeverityModel $severity */
            $severity = $this->getPastoralSeverityMapper()->findOneByColumn("order", 0);
        }
        catch (\Exception $e)
        {
            // Pastoral severity was not found, this should never happen, but we will just create a default one called
            // "Low" in case it does every happen

            $severity = new PastoralSeverityModel();
            $severity->setOrder(0);
            $severity->setName("Low");
            $this->getPastoralSeverityMapper()->Save($severity);
        }

        $imported = [];
        $skipped = [];
        $userCache = [];

        foreach ($records as $record)
        {
            // Delete any existing external record

            $this->getPastoralMapper()->deleteByColumn("external_id", $record["PastoralCareSeq"]);

            // Retrieve type

            try
            {
                $type = $this->getPastoralTypeMapper()->findOneByColumn('name', $record["TypeDescription"]);
            }
            catch (\Exception $e)
            {
                $type = new PastoralTypeModel();
                $type->setName($record["TypeDescription"]);
                $this->getPastoralTypeMapper()->save($type);
            }

            // Lookup

            try
            {
                $record["CreatedDate"] = date("d/m/Y h:i:sA", strtotime($record["CreatedDate"]));
                $record["ModifiedDate"] = date("d/m/Y h:i:sA", strtotime($record["ModifiedDate"]));

                if ($record["Details"] != $record["PrivateDetails"])
                    $record["heading"] = substr($record["PrivateDetails"], 0, 50);
                else
                    $record["heading"] = substr($record["Details"], 0, 50);

                $owner = null;
                $student = null;

                if (!isset($userCache[$record["CreatedByID"]]))
                    $userCache[$record["CreatedByID"]] = $this->getUserMapper()->findByExternalId($record["CreatedByID"]);

                $owner = $userCache[$record["CreatedByID"]];

                if (!isset($userCache[$record["ID"]]))
                    $userCache[$record["ID"]] = $this->getUserMapper()->findByExternalId($record["ID"]);

                $student = $userCache[$record["ID"]];

                $record["heading"] = substr($record["Details"], 0, 50);
                $pastoralRecord = new PastoralModel();
                $pastoralRecord->setExternalId($record["PastoralCareSeq"]);
                $pastoralRecord->setHeading($record["heading"]);
                $pastoralRecord->setBody($record["Details"]);
                $pastoralRecord->setType($type);
                $pastoralRecord->setStudent($student);
                $pastoralRecord->setOwner($owner);
                $pastoralRecord->setSeverity($severity);
                $pastoralRecord->setPrivate($record["ConfidentialFlag"]);
                $pastoralRecord->setCreatedAt(\Alaress_Date_Format::newLocal($record["CreatedDate"]));
                $pastoralRecord->setUpdatedAt(\Alaress_Date_Format::newLocal($record["ModifiedDate"]));
                $this->getPastoralMapper()->save($pastoralRecord);
                $imported[] = $record;

                $audience_query = "SELECT ID FROM PastoralCareContacts WHERE PastoralCareSeq = {$record["PastoralCareSeq"]}";
                $audience_records = $this->getDatabase()->getAll($audience_query);

                if (is_array($audience_records))
                foreach ($audience_records as $audience_record)
                {
                    try {
                        if (!isset($userCache[$audience_record["ID"]]))
                            $userCache[$audience_record["ID"]] = $this->getUserMapper()->findByExternalId($audience_record["ID"]);

                        $accessRecord = new PastoralAccessModel();
                        $accessRecord->setPastoralId($pastoralRecord->getId());
                        $accessRecord->setUser($userCache[$audience_record["ID"]]);
                        $this->getPastoralAccessMapper()->save($accessRecord);
                    } catch (\Exception $e) {
                    }
                }

                if ($record["Details"] != $record["PrivateDetails"] && trim($record["PrivateDetails"]) != "")
                {
                    // Need to create a separate private record

                    $record["heading"] = substr($record["PrivateDetails"], 0, 50);
                    $pastoralRecordPrivate = new PastoralModel();
                    $pastoralRecordPrivate->setExternalId($record["PastoralCareSeq"]);
                    $pastoralRecordPrivate->setHeading($record["heading"]);
                    $pastoralRecordPrivate->setBody($record["PrivateDetails"]);
                    $pastoralRecordPrivate->setType($type);
                    $pastoralRecordPrivate->setStudent($student);
                    $pastoralRecordPrivate->setOwner($owner);
                    $pastoralRecordPrivate->setPrivate(true);
                    $pastoralRecordPrivate->setSeverity($severity);
                    $pastoralRecordPrivate->setCreatedAt(\Alaress_Date_Format::newLocal($record["CreatedDate"]));
                    $pastoralRecordPrivate->setUpdatedAt(\Alaress_Date_Format::newLocal($record["ModifiedDate"]));
                    $this->getPastoralMapper()->save($pastoralRecordPrivate);
                    $record["private"] = 1;
                    $imported[] = $record;

                    if (is_array($audience_records))
                    foreach ($audience_records as $audience_record)
                    {
                        try {
                            if (!isset($userCache[$audience_record["ID"]]))
                                $userCache[$audience_record["ID"]] = $this->getUserMapper()->findByExternalId($audience_record["ID"]);

                            $accessRecord = new PastoralAccessModel();
                            $accessRecord->setPastoralId($pastoralRecordPrivate->getId());
                            $accessRecord->setUser($userCache[$audience_record["ID"]]);
                            $this->getPastoralAccessMapper()->save($accessRecord);
                        } catch (\Exception $e) {
                        }
                    }
                }
            }
            catch (\Exception $e)
            {
                // The owner or student of the pastoral care record is not available in Schoolbox, skip this record

                if ($owner == null)
                    $record['reason'] = "Creator of record does not have a user record in Schoolbox";
                else if ($student == null)
                    $record['reason'] = "Student does not have a user record in Schoolbox";

                $skipped[] = $record;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped
        ];
    }

    /**
     * Creates a new pastoral record in the external database
     *
     * @param \Schoolbox\Pastoral\Pastoral
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return bool
     */
    public function createPastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        // Get the information necessary to create the query

        // External student ID
        
        $id = $this->getUserMapper()->findById($pastoral->getStudent()->getId())->getExternalId();

        // Set the item date to the post date

        $item_date_alaress = \Alaress_Date_Format::newLocal($pastoral->getCreatedAt());
        $item_date = $item_date_alaress->getYear() . "-" . $item_date_alaress->getMonth() . "-" . $item_date_alaress->getDay(true);

        // If the message is marked as private, do not put in public details

        if ($pastoral->getPrivate()) {
            $details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "'";
        } else {
            $details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "\n\n" . str_replace("'", "''", $pastoral->getBodyText()) . "'";
        }

        $private_details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "\n\n" . str_replace("'", "''", $pastoral->getBodyText()) . "'";

        // Pastoral event type code

        $type = $this->getPastoralTypeMapper()->findById($pastoral->getType()->getId());
        $type_code = "'" . $type->getExternalCode() . "'";

        // Created/modified dates

        $created_date_alaress = \Alaress_Date_Format::newLocal($pastoral->getCreatedAt());
        $created_date = $created_date_alaress->getYear() . "-" . $created_date_alaress->getMonth() . "-" . $created_date_alaress->getDay(true);
        $created_by_id = $pastoral->getOwner()->getExternalId();

        // If there is no modified date, give it the created date

        if ($pastoral->getUpdatedAt() != null) {
            $modified_date_alaress = \Alaress_Date_Format::newLocal($pastoral->getUpdatedAt());
            $modified_date = "'" . $modified_date_alaress->getYear() . "-" . $modified_date_alaress->getMonth() . "-" . $modified_date_alaress->getDay(true) . "'";
            $modified_by_id = $pastoral->getOwner()->getExternalId();
        } else {
            $modified_date_alaress = \Alaress_Date_Format::newLocal($pastoral->getCreatedAt());
            $modified_date = "'". $modified_date_alaress->getYear() . "-" . $modified_date_alaress->getMonth() . "-" . $modified_date_alaress->getDay(true) . "'";
            $modified_by_id = $pastoral->getOwner()->getExternalId();
        }

        // Execute the query

        $query = "INSERT INTO tPastoralCare (
                    ID,
                    ItemDate,
                    Details,
                    PrivateDetails,
                    TypeCode,
                    CreatedDate,
                    CreatedById,
                    ModifiedDate,
                    ModifiedById,
                    ClassCode,
                    MethodCode,
                    EmailSentByID
                ) VALUES (
                    {$id},
                    {$item_date},
                    {$details},
                    {$private_details},
                    {$type_code},
                    {$created_date},
                    {$created_by_id},
                    {$modified_date},
                    {$modified_by_id},
                    '',
                    '',
                    -1
                )";

        $this->getDatabase()->query($query);
        $result = $this->getDatabase()->query("SELECT SCOPE_IDENTITY() AS insert_id");
        $row = mssql_fetch_object($result);

        $pastoral->setExternalId($row->insert_id);
        $this->getPastoralMapper()->save($pastoral);

        // TODO: Upload attachments to DocMan (SynergyOneMedia)

        /*
        $attachments = $this->getPastoralAttachmentMapper()->findByPastoralId($pastoral->getId());

        foreach ($attachments as $attachment) {

            $resource = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($attachment->getHash());
            $content = bin2hex($content);
            $document = "0x".$content;

            $this->getDatabase()->query("INSERT INTO tDocuments (Description, SourceRed, Document) VALUES ('Text', 'Imported from Schoolbox', '{$document}')");
        }
        */

        return $result;
    }


    /**
     * Updates an existing pastoral record
     *
     * @param \Schoolbox\Pastoral\Pastoral\Model $pastoral
     * @return bool
     */
    public function updatePastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        // Get the information necessary to create the query

        $external_id = $pastoral->getExternalId();

        if (is_int($external_id)) {

            // External student ID

            $id = $this->getUserMapper()->findById($pastoral->getStudent()->getId())->getExternalId();

            // Set the item date to the post date

            $item_date = "'" . date("Y-m-d H:i:s", strtotime($pastoral->getCreatedAt())) . "'";

            // If the message is marked as private, do not put in public details

            if ($pastoral->getPrivate()) {
                $details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "'";
            } else {
                $details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "\n\n" . str_replace("'", "''", $pastoral->getBodyText()) . "'";
            }

            $private_details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "\n\n" . str_replace("'", "''", $pastoral->getBodyText()) . "'";

            // Pastoral event type code

            $type = $this->getPastoralTypeMapper()->findById($pastoral->getType()->getId());
            $type_code = "'" . $type->getExternalCode() . "'";

            // Created/modified dates

            $created_date = "'" . date("Y-m-d H:i:s", strtotime($pastoral->getCreatedAt())) . "'";
            $created_by_id = $pastoral->getOwner()->getExternalId();

            // If there is no modified date, give it the created date

            if ($pastoral->getUpdatedAt() != null) {
                $modified_date = "'" . date("Y-m-d H:i:s", strtotime($pastoral->getUpdatedAt())) . "'";
                $modified_by_id = $pastoral->getOwner()->getExternalId();
            } else {
                $modified_date = "'" . date("Y-m-d H:i:s", strtotime($pastoral->getCreatedAt())) . "'";
                $modified_by_id = $pastoral->getOwner()->getExternalId();
            }

            // Execute the query

            $query = "UPDATE tPastoralCare SET
                        ID = {$id},
                        ItemDate = {$item_date},
                        Details = {$details},
                        PrivateDetails = {$private_details},
                        TypeCode = {$type_code},
                        CreatedDate = {$created_date},
                        CreatedById = {$created_by_id},
                        ModifiedDate = {$modified_date},
                        ModifiedById = {$modified_by_id},
                        ClassCode = '',
                        MethodCode = '',
                        EmailSentByID = -1
                        WHERE PastoralCareSeq = {$external_id}
            ";

            $result = $this->getDatabase()->query($query);
            return $result;
        }

        // TODO: Upload attachments to DocMan (SynergyOneMedia)

        /*
        $attachments = $this->getPastoralAttachmentMapper()->findByPastoralId($pastoral->getId());

        foreach ($attachments as $attachment) {

            $resource = Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash($attachment->getHash());
            $content = bin2hex($content);
            $document = "0x".$content;

            $this->getDatabase()->query("INSERT INTO tDocuments (Description, SourceRed, Document) VALUES ('Text', 'Imported from Schoolbox', '{$document}')");
        }
        */

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deletePastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        if (trim($pastoral->getExternalId()) != '') {
            $result = $this->getDatabase()->query("DELETE FROM tPastoralCare WHERE PastoralCareSeq = " . $pastoral->getExternalId());
            return $result;
        } else {

            // Nothing to delete

            return true;
        }
    }

    /**
     * @param MSSQL $database
     */
    public function setDatabase($database)
    {
        $this->database = $database;
    }

    /**
     * @return MSSQL
     */
    public function getDatabase()
    {
        return $this->database;
    }
}