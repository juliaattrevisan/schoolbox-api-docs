<?php
/**
 *
 * @author      Damian Sloane <dsloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 */

use Schoolbox\Notif\SilentNotif;
use Schoolbox\UserMapper;
use Schoolbox\Pastoral\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Type\Model as PastoralTypeModel;
use Schoolbox\Pastoral\Category\Mapper as PastoralCategoryMapper;
use Schoolbox\Pastoral\Category\Model as PastoralCategoryModel;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccessModel;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverityModel;
use Schoolbox\Pastoral\Log\Mapper as PastoralLogMapper;
use Schoolbox\Pastoral\Log\Model as PastoralLogModel;

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

    /** @var PastoralLogMapper $pastoralMapper */
    protected $pastoralLogMapper;

    /** @var PastoralCommentMapper $pastoralCommentMapper */
    protected $pastoralCommentMapper;

    /** @var PastoralTypeMapper $pastoralTypeMapper */
    protected $pastoralTypeMapper;

    /** @var PastoralCategoryMapper $pastoralCategoryMapper */
    protected $pastoralCategoryMapper;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    protected $pastoralAccessMapper;

    /** @var PastoralAttachmentMapper $pastoralAttachmentMapper */
    protected $pastoralAttachmentMapper;

    /** @var PastoralSeverityMapper $pastoralSeverityMapper */
    protected $pastoralSeverityMapper;

    /**
     * Constructor

     * @throws Exception
     */
    public function __construct()
    {
        $this->setDatabase(MSSQL::getInstance());
        $this->setUserMapper(UserMapper::getInstance());
        $this->setPastoralMapper(PastoralMapper::getInstance());
        $this->setPastoralAccessMapper(PastoralAccessMapper::getInstance());
        $this->setPastoralCommentMapper(PastoralCommentMapper::getInstance());
        $this->setPastoralLogMapper(PastoralLogMapper::getInstance());
        $this->setPastoralTypeMapper(PastoralTypeMapper::getInstance());
        $this->setPastoralCategoryMapper(PastoralCategoryMapper::getInstance());
        $this->setPastoralAccessMapper(PastoralAccessMapper::getInstance());
        $this->setPastoralSeverityMapper(PastoralSeverityMapper::getInstance());
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
     *
     * @param int $userId                           The Schoolbox ID of the person doing the import
     * @param \Schoolbox\User $defaultOwner         The fallback user when the owner of the pastoral record cannot be found
     */
    public function importPastoral($userId, $defaultOwner)
    {
        $silence = new SilentNotif();

        $this->getPastoralMapper()->setNotifier($silence);
        $this->getPastoralAccessMapper()->setNotifier($silence);
        $this->getPastoralCommentMapper()->setNotifier($silence);

        /** @var \Schoolbox\Collection $results */
        $results = $this->getPastoralLogMapper()->findAll()->sortBy("importId", "desc");

        if ($results->count() > 0) {
            $importId = $results->first()->getImportId() + 1;
        } else {
            $importId = 1;
        }

        // Get all pastoral care records from Synergetic for current students only

        $query =
        "
            SELECT       dbo.tPastoralCare.PastoralCareSeq, dbo.tPastoralCare.ID, dbo.tPastoralCare.ClassCode, dbo.tPastoralCare.ItemDate, dbo.tPastoralCare.MethodCode,
                         dbo.tPastoralCare.Details, dbo.tPastoralCare.PrivateDetails, dbo.tPastoralCare.InitiatorCode, dbo.tPastoralCare.TypeCode, dbo.tPastoralCare.CategoryCode,
                         dbo.tPastoralCare.ClassificationCode, dbo.tPastoralCare.IsFollowUpFlag, dbo.tPastoralCare.FollowUpDate, dbo.tPastoralCare.ConfidentialFlag,
                         dbo.tPastoralCare.CreatedDate, dbo.tPastoralCare.CreatedByID, dbo.tPastoralCare.ModifiedDate, dbo.tPastoralCare.ModifiedByID,
                         dbo.tPastoralCare.EmailSentDate, dbo.tPastoralCare.EmailSentByID, dbo.tPastoralCare.DuplicateFromSeq, dbo.tPastoralCare.SubjectClassesSeq,
                         dbo.luPastoralCareType.Description AS TypeDescription, Community_1.Surname AS StudentLastname, Community_1.Given1 AS StudentFirstname,
                         dbo.Community.Surname AS OwnerLastname, dbo.Community.Given1 AS OwnerFirstname
            FROM         dbo.tPastoralCare 
            INNER JOIN   dbo.luPastoralCareType ON dbo.tPastoralCare.TypeCode = dbo.luPastoralCareType.Code 
            INNER JOIN   dbo.Community ON dbo.tPastoralCare.CreatedByID = dbo.Community.ID 
            INNER JOIN   dbo.Community AS Community_1 ON dbo.tPastoralCare.ID = Community_1.ID
            WHERE        dbo.tPastoralCare.ID IN (SELECT ID FROM dbo.vStudents)
        ";

        /** @var MssqlResultIterator $records */
        $records = $this->getDatabase()->getIterator($query);

        $owner = null;
        $student = null;

        // Give the import 30 minutes to complete and bump up the memory limit to handle large recordsets

        ini_set('memory_limit', '2048M');
        set_time_limit(60 * 30);

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

        $userCache = [];
        $typeCache = [];

        $records->next();

        while ($records->valid())
        {
            $record = $records->current();

            // Delete any existing external record

            $this->getPastoralMapper()->deleteByColumn("external_id", $record["PastoralCareSeq"]);

            // Retrieve type

            if (!isset($typeCache[$record["TypeDescription"]])) {
                try
                {
                    $type = $this->getPastoralTypeMapper()->findOneByColumn('name', $record["TypeDescription"]);
                }
                catch (\Exception $e)
                {
                    // Type doesn't exist in Schoolbox, create it
                    
                    $type = new PastoralTypeModel();
                    $type->setName($record["TypeDescription"]);
                    $type->setExternalCode($record["TypeCode"]);
                    $this->getPastoralTypeMapper()->save($type);
                }

                $typeCache[$record["TypeDescription"]] = $type;
            }
            else {
                $type = $typeCache[$record["TypeDescription"]];
            }

            // Retrieve category

            try
            {
                $category = $this->getPastoralCategoryMapper()->findOneByColumn('external_code', $record["CategoryCode"]);
            }
            catch (\Exception $e)
            {
                // Category doesn't exist in Schoolbox, create it
                
                $category = new PastoralCategoryModel();
                $category->setName($record["CategoryDescription"]);
                $category->setExternalCode($record["CategoryCode"]);
                $category->setPastoralTypeId($type->getId());
                $this->getPastoralCategoryMapper()->save($category);
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

                try {
                    if (!isset($userCache[$record["CreatedByID"]]))
                        $userCache[$record["CreatedByID"]] = $this->getUserMapper()->findByExternalId($record["CreatedByID"]);
                    $owner = $userCache[$record["CreatedByID"]];
                } catch (\Exception $e) {
                    $owner = $defaultOwner;
                }

                if (!isset($userCache[$record["ID"]]))
                    $userCache[$record["ID"]] = $this->getUserMapper()->findByExternalId($record["ID"]);

                $student = $userCache[$record["ID"]];

                $record["heading"] = substr($record["Details"], 0, 50);
                $pastoralRecord = new PastoralModel();
                $pastoralRecord->setExternalId($record["PastoralCareSeq"]);
                $pastoralRecord->setHeading($record["heading"]);
                $pastoralRecord->setBody($record["Details"]);
                $pastoralRecord->setType($type);
                $pastoralRecord->setCategory($category);
                $pastoralRecord->setStudent($student);
                $pastoralRecord->setOwner($owner);
                $pastoralRecord->setSeverity($severity);
                $pastoralRecord->setPrivate($record["ConfidentialFlag"]);
                $pastoralRecord->setCreatedAt(\Alaress_Date_Format::newLocal($record["CreatedDate"]));
                $pastoralRecord->setUpdatedAt(\Alaress_Date_Format::newLocal($record["ModifiedDate"]));
                $this->getPastoralMapper()->save($pastoralRecord);

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

                if ($record["Details"] != $record["PrivateDetails"] && trim($record["PrivateDetails"]) != "" && $record["PrivateDetails"] != "No Private Details Entered")
                {
                    // Need to create a separate private record

                    $record["heading"] = substr($record["PrivateDetails"], 0, 50);
                    $pastoralRecordPrivate = new PastoralModel();
                    $pastoralRecordPrivate->setExternalId($record["PastoralCareSeq"]);
                    $pastoralRecordPrivate->setHeading($record["heading"]);
                    $pastoralRecordPrivate->setBody($record["PrivateDetails"]);
                    $pastoralRecordPrivate->setType($type);
                    $pastoralRecordPrivate->setCategory($category);
                    $pastoralRecordPrivate->setStudent($student);
                    $pastoralRecordPrivate->setOwner($owner);
                    $pastoralRecordPrivate->setPrivate(true);
                    $pastoralRecordPrivate->setSeverity($severity);
                    $pastoralRecordPrivate->setCreatedAt(\Alaress_Date_Format::newLocal($record["CreatedDate"]));
                    $pastoralRecordPrivate->setUpdatedAt(\Alaress_Date_Format::newLocal($record["ModifiedDate"]));
                    $this->getPastoralMapper()->save($pastoralRecordPrivate);
                    $record["private"] = 1;

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

                $record['reason'] = "Imported";

                $log = new PastoralLogModel();
                $log->setUserId($userId);
                $log->setPastoralId($pastoralRecord->getId());
                $log->setImportId($importId);
                $log->setHeading($record["heading"]);
                $log->setStudentName($student->getFirstname() . " " . $student->getLastname());
                $log->setCreatorName($owner->getFirstname() . " " . $owner->getLastname());
                $log->setCreatedAt(\Alaress_Date_Format::now());
                $log->setResult($record['reason']);
                $this->getPastoralLogMapper()->save($log);
            }
            catch (\Exception $e)
            {
                // The student of the pastoral care record is not available in Schoolbox, skip this record

                $log = new PastoralLogModel();
                $log->setUserId($userId);
                $log->setImportId($importId);
                $log->setHeading($record["heading"]);
                $log->setStudentName($record["StudentFirstname"] . " " . $record["StudentLastname"]);
                $log->setCreatorName($owner->getFirstname() . " " . $owner->getLastname());
                $log->setCreatedAt(\Alaress_Date_Format::now());
                $log->setResult("Student does not have a user record in Schoolbox");
                $this->getPastoralLogMapper()->save($log);
            }

            $records->next();
        }

        $log = new \Schoolbox\Pastoral\Log\Model();
        $log->setUserId($userId);
        $log->setImportId($importId);
        $log->setHeading("Import Completed");
        $log->setResult("Completed");
        $log->setCreatedAt(\Alaress_Date_Format::now());
        $this->getPastoralLogMapper()->save($log);
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
            $details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "\r\n\r\n" . str_replace("'", "''", $pastoral->getBodyText()) . "'";
        }

        $private_details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "\r\n\r\n" . str_replace("'", "''", $pastoral->getBodyText()) . "'";

        // Pastoral event type code

        $type = $this->getPastoralTypeMapper()->findById($pastoral->getType()->getId());
        $type_code = "'" . $type->getExternalCode() . "'";

        // Pastoral category code

        if ($pastoral->getCategory() == null)
            $category_code = "''";
        else {
            $category = $this->getPastoralCategoryMapper()->findById($pastoral->getCategory()->getId());
            $category_code = "'" . $category->getExternalCode() . "'";
        }

        // Created/modified dates

        $created_date_alaress = \Alaress_Date_Format::newLocal($pastoral->getCreatedAt());
        $created_date = $created_date_alaress->getYear() . "-" . $created_date_alaress->getMonth() . "-" . $created_date_alaress->getDay(true);
        $created_by_id = $pastoral->getOwner()->getExternalId();

        // Execute the query

        $query = "INSERT INTO tPastoralCare (
                    ID,
                    ItemDate,
                    Details,
                    PrivateDetails,
                    TypeCode,
                    CategoryCode,
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
                    {$category_code},
                    {$created_date},
                    {$created_by_id},
                    {$created_date},
                    {$created_by_id},
                    '',
                    '',
                    -1
                )";

        $this->getDatabase()->query($query);
        $result = $this->getDatabase()->query("SELECT SCOPE_IDENTITY() AS insert_id");
        $row = mssql_fetch_object($result);

        $pastoral->setExternalId($row->insert_id);
        $this->getPastoralMapper()->save($pastoral);

        $this->updatePastoralCareContacts($pastoral);

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
     * Creates a new pastoral comment in the external database
     *
     * @param \Schoolbox\Pastoral\Comment
     * @param \Schoolbox\Pastoral\Comment\Model $comment
     * @return bool
     */
    public function createComment(\Schoolbox\Pastoral\Comment\Model $comment)
    {
        // Get the information necessary to create the query

        // Can only do this if the pastoral record has an external ID

        /** @var PastoralModel $pastoral */
        $pastoral = $this->getPastoralMapper()->findOneByColumn("id", $comment->getPastoralId());

        if (is_int($pastoral->getExternalId()))
        {
            $body = str_replace("'", "''", $comment->getBody());

            $created_date_alaress = \Alaress_Date_Format::newLocal($comment->getCreatedAt());
            $created_date = $created_date_alaress->getYear() . "-" . $created_date_alaress->getMonth() . "-" . $created_date_alaress->getDay(true);
            $created_by_id = $comment->getOwner()->getExternalId();

            // Execute the query

            $query = "INSERT INTO PastoralCareComments (
                            PastoralCareSeq,
                            IndexOrder,
                            Comment,
                            CreatedDate,
                            CreatedById,
                            ModifiedDate,
                            ModifiedById
                        ) VALUES (
                            {$pastoral->getExternalId()},
                            COALESCE((SELECT MAX(IndexOrder) + 1 FROM PastoralCareComments WHERE PastoralCareSeq = {$pastoral->getExternalId()}), 1),
                            '{$body}',
                            {$created_date},
                            {$created_by_id},
                            {$created_date},
                            {$created_by_id}
                        )";

            $this->getDatabase()->query($query);
            $result = $this->getDatabase()->query("SELECT SCOPE_IDENTITY() AS insert_id");
            $row = mssql_fetch_object($result);

            $comment->setExternalId($row->insert_id);
            $this->getPastoralCommentMapper()->save($comment);

            return $result;
        }
        return false;
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

            $item_date_alaress = \Alaress_Date_Format::newLocal($pastoral->getCreatedAt());
            $item_date = $item_date_alaress->getYear() . "-" . $item_date_alaress->getMonth() . "-" . $item_date_alaress->getDay(true);

            // If the message is marked as private, do not put in public details

            if ($pastoral->getPrivate()) {
                $details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "'";
            } else {
                $details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "\r\n\r\n" . str_replace("'", "''", $pastoral->getBodyText()) . "'";
            }

            $private_details = "'" . str_replace("'", "''", $pastoral->getHeading()) . "\r\n\r\n" . str_replace("'", "''", $pastoral->getBodyText()) . "'";

            // Pastoral event type code

            $type = $this->getPastoralTypeMapper()->findById($pastoral->getType()->getId());
            $type_code = "'" . $type->getExternalCode() . "'";

            // Pastoral category code

            $category = $this->getPastoralCategoryMapper()->findById($pastoral->getCategory()->getId());
            $category_code = "'" . $category->getExternalCode() . "'";

            // Modified date

            if ($pastoral->getUpdatedAt() != null)
                $modified_date_alaress = \Alaress_Date_Format::newLocal($pastoral->getUpdatedAt());
            else
                $modified_date_alaress = \Alaress_Date_Format::newLocal($pastoral->getCreatedAt());

            $modified_date = "'" . $modified_date_alaress->getYear() . "-" . $modified_date_alaress->getMonth() . "-" . $modified_date_alaress->getDay(true) . "'";
            $modified_by_id = $pastoral->getOwner()->getExternalId();

            // Execute the query

            $query = "UPDATE tPastoralCare SET
                        ID = {$id},
                        ItemDate = {$item_date},
                        Details = {$details},
                        PrivateDetails = {$private_details},
                        TypeCode = {$type_code},
                        CategoryCode = {$category_code},
                        ModifiedDate = {$modified_date},
                        ModifiedById = {$modified_by_id},
                        ClassCode = '',
                        MethodCode = '',
                        EmailSentByID = -1
                        WHERE PastoralCareSeq = {$external_id}
            ";

            $result = $this->getDatabase()->query($query);

            $this->updatePastoralCareContacts($pastoral);

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

        return false;
    }

    /**
     * Updates pastoral care contacts in Synergetic
     *
     * @param PastoralModel $pastoral
     */
    public function updatePastoralCareContacts(\Schoolbox\Pastoral\Pastoral\Model $pastoral) {

        $external_id = $pastoral->getExternalId();

        // Pummel old contacts for the pastoral care record
        $query = "DELETE FROM PastoralCareContacts WHERE PastoralCareSeq = {$external_id}";
        $this->getDatabase()->query($query);

        // Get users that have been given specific access to the record

        $access = $this->getPastoralAccessMapper()->findByPastoralId($pastoral->getId());

        $order = 1;

        /** @var PastoralAccessModel $record */
        foreach ($access as $record) {
            if ($user = $record->getUser()) {
                if ($user->getExternalId()) {
                    $query = "
                        INSERT INTO PastoralCareContacts
                        (
                          PastoralCareSeq,
                          IndexOrder,
                          EmailFlag,
                          Email,
                          ID
                        )
                        VALUES
                        (
                          {$external_id},
                          {$order},
                          0,
                          '{$user->getEmail()}',
                          {$user->getExternalId()}
                        );
                    ";
                    $this->getDatabase()->query($query);
                    $order++;
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function deletePastoral(\Schoolbox\Pastoral\Pastoral\Model $pastoral)
    {
        if (trim($pastoral->getExternalId()) != '') {
            $result = $this->getDatabase()->query("DELETE FROM PastoralCareContacts WHERE PastoralCareSeq = " . $pastoral->getExternalId());

            if ($result)
                $result = $this->getDatabase()->query("DELETE FROM PastoralCareComments WHERE PastoralCareSeq = " . $pastoral->getExternalId());
            else
                return false;

            if ($result)
                $result = $this->getDatabase()->query("DELETE FROM tPastoralCare WHERE PastoralCareSeq = " . $pastoral->getExternalId());
            else
                return false;

            return $result;
        } else {

            // Nothing to delete

            return true;
        }
    }


    /**
     * @inheritDoc
     */
    public function deleteComment(\Schoolbox\Pastoral\Comment\Model $comment)
    {
        if (trim($comment->getExternalId()) != '') {
            $result = $this->getDatabase()->query("DELETE FROM PastoralCareComments WHERE PastoralCareCommentsSeq = " . $comment->getExternalId());
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
     * @return PastoralLogMapper
     */
    public function getPastoralLogMapper() {
        return $this->pastoralLogMapper;
    }

    /**
     * @param $pastoralLogMapper
     * @return $this
     */
    public function setPastoralLogMapper($pastoralLogMapper) {
        $this->pastoralLogMapper = $pastoralLogMapper;
        return $this;
    }

    /**
     * @return PastoralCategoryMapper
     */
    public function getPastoralCategoryMapper() {
        return $this->pastoralCategoryMapper;
    }

    /**
     * @param $pastoralCategoryMapper
     * @return $this
     */
    public function setPastoralCategoryMapper($pastoralCategoryMapper) {
        $this->pastoralCategoryMapper = $pastoralCategoryMapper;
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
     * @return PastoralCommentMapper
     */
    public function getPastoralCommentMapper() {
        return $this->pastoralCommentMapper;
    }

    /**
     * @param $pastoralCommentMapper
     * @return $this
     */
    public function setPastoralCommentMapper($pastoralCommentMapper) {
        $this->pastoralCommentMapper = $pastoralCommentMapper;
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
}
