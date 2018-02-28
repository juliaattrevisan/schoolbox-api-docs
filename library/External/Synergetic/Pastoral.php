<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Html2Text\Html2Text;
use Schoolbox\External\Exception as ExternalException;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Comment\Model as PastoralComment;
use Schoolbox\Pastoral\DocumentClassification\Mapper as DocumentClassificationMapper;
use Schoolbox\Pastoral\DocumentClassification\Mapper as PastoralDocumentClassificationMapper;
use Schoolbox\Pastoral\DocumentClassification\Model as DocumentClassification;
use Schoolbox\Pastoral\Log\Mapper as PastoralLogMapper;
use Schoolbox\Pastoral\Log\Model as PastoralLog;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\SubType\Model as PastoralSubType;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Type\Model as PastoralType;
use Schoolbox\Pastoral\UploadQueue\Model as UploadQueue;
use Schoolbox\User;
use Schoolbox\UserMapper;

class External_Synergetic_Pastoral implements External_PastoralInterface
{
    use External_MssqlConnector;
    use External_Synergetic_ValidateExternalIdTrait;

    // Mappers

    /** @var UserMapper $userMapper */
    protected $userMapper;

    /** @var PastoralMapper $pastoralMapper */
    protected $pastoralMapper;

    /** @var PastoralDocumentClassificationMapper $pastoralDocumentClassificationMapper */
    protected $pastoralDocumentClassificationMapper;

    /** @var PastoralLogMapper $pastoralMapper */
    protected $pastoralLogMapper;

    /** @var PastoralCommentMapper $pastoralCommentMapper */
    protected $pastoralCommentMapper;

    /** @var PastoralTypeMapper $pastoralTypeMapper */
    protected $pastoralTypeMapper;

    /** @var PastoralSubTypeMapper $pastoralSubTypeMapper */
    protected $pastoralSubTypeMapper;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    protected $pastoralAccessMapper;

    /** @var PastoralAttachmentMapper $pastoralAttachmentMapper */
    protected $pastoralAttachmentMapper;

    /** @var PastoralSeverityMapper $pastoralSeverityMapper */
    protected $pastoralSeverityMapper;

    // The media database schema varies between v67/68 of Synergetic, this will hold the schema name we should use

    /** @var string $schemaMedia */
    private $schemaMedia;

    // Cached objects for use during importing

    /** @var array $userCache Cache of users for use during importing */
    private $userCache = [];

    /** @var array $typeCache Cache of pastoral types for use during importing */
    private $typeCache = [];

    /** @var array $pastoralTypeCache Cache of pastoral subtypes for use during importing */
    private $pastoralTypeCache = [];

    // This is a magic Synergetic string that gets set as the contents of a pastoral record when there are no private contents

    const BLANK_PRIVATE_RECORD = "No Private Details Entered";

    // Deduplicate query

    /** @var string $sqlPastoralSelect */
    protected $sqlPastoralSelect = "
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
        INNER JOIN    dbo.vStudents ON dbo.vStudents.ID = dbo.tPastoralCare.ID
        INNER JOIN    dbo.FileSemesters ON 
                          dbo.FileSemesters.FileYear = dbo.vStudents.FileYear AND
                          dbo.FileSemesters.FileSemester = dbo.vStudents.FileSemester AND
                          dbo.FileSemesters.SystemCurrentFlag = 1
    ";

    /** @var Html2Text $htmlConverter */
    protected $htmlConverter;

    /**
     * Constructor
     */
    public function __construct()
    {
        // This connects to the Synergetic database and stores an IConnection in `$this->connector` variable

        $this->setupDatabase();

        $this->htmlConverter = new Html2Text();

        $externalPing = new External_Ping();

        // The upload location varies depending on the version of Synergetic

        if ($externalPing->getVersion() == "68") {
            $this->schemaMedia = "media";
        } else {
            $this->schemaMedia = "SynergyOneMedia.dbo";
        }

        // Grab mapper instances

        $this->setUserMapper(UserMapper::getInstance());
        $this->setPastoralMapper(PastoralMapper::getInstance());
        $this->setPastoralAccessMapper(PastoralAccessMapper::getInstance());
        $this->setPastoralDocumentClassificationMapper(PastoralDocumentClassificationMapper::getInstance());
        $this->setPastoralCommentMapper(PastoralCommentMapper::getInstance());
        $this->setPastoralLogMapper(PastoralLogMapper::getInstance());
        $this->setPastoralTypeMapper(PastoralTypeMapper::getInstance());
        $this->setPastoralSubTypeMapper(PastoralSubTypeMapper::getInstance());
        $this->setPastoralAccessMapper(PastoralAccessMapper::getInstance());
        $this->setPastoralSeverityMapper(PastoralSeverityMapper::getInstance());
    }

    /**
     * @inheritdoc
     */
    public function getPastoral(Pastoral $pastoral)
    {
        $query = "{$this->sqlPastoralSelect} WHERE dbo.tPastoralCare.PastoralCareSeq = :externalId";

        return $this->connector->executeQuery($query)->fetch();
    }

    /**
     * Create a unique ID for each import. Just using the current timestamp.
     *
     * @return int
     */
    protected function getNewImportId()
    {
        return (int)date("U");
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

    // Pastoral record synchronization

    /**
     * @inheritdoc
     */
    public function syncPastoral(Pastoral $pastoral)
    {
        /** @var Pastoral $pastoral */
        $pastoral = $this->reloadModel($pastoral);

        // Get insert/update query based on the absence/presence of an external ID on the pastoral record

        if (empty($pastoral->getExternalId())) {
            $query = "
                INSERT INTO tPastoralCare (
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
                    EmailSentByID,
                    ClassificationCode,
                    ConfidentialFlag
                ) VALUES (
                    :studentExternalId, 
                    :itemDate,
                    :publicBody,
                    :privateBody,
                    :pastoralTypeCode,
                    :pastoralSubTypeCode,
                    :createdDate,
                    :createdOwnerId,
                    :modifiedDate,
                    :modifiedOwnerId,
                    '',
                    '',
                    -1,
                    :classificationCode,
                    1
                )
            ";
        } else {
            $query = "
                UPDATE      tPastoralCare 
                SET         ID = :studentExternalId,
                            ItemDate = :itemDate,
                            Details = :publicBody,
                            PrivateDetails = :privateBody,
                            TypeCode = :pastoralTypeCode,
                            CategoryCode = :pastoralSubTypeCode,
                            CreatedDate = :createdDate,
                            CreatedById = :createdOwnerId,
                            ModifiedDate = :modifiedDate,
                            ModifiedById = :modifiedOwnerId,
                            ClassificationCode = :classificationCode,
                            ConfidentialFlag = 1
                WHERE       PastoralCareSeq = :externalId
            ";
        }

        try {
            if ($this->connector->executeQuery($query, $this->getQueryParameters($pastoral))) {
                // If this is a new record we need to store the ID of the newly created Synergetic record

                if (empty($pastoral->getExternalId())) {
                    // Stop pastoral notifier firing on this update, otherwise they receive both a create/update
                    // notification every time someone creates a pastoral record

                    $pastoral->setExternalId($this->connector->lastInsertId());
                    $this->getPastoralMapper()->save($pastoral);
                }

                // Export the permissions/audience of the pastoral record to Synergetic

                $this->updatePastoralCareContacts($pastoral);

                return true;
            }
        } catch (ExternalException $e) {
            return false;
        }

        return false;
    }

    /**
     * Updates pastoral care contacts associated with a record in Synergetic
     *
     * @param Pastoral $pastoral
     *
     * @return bool
     */
    private function updatePastoralCareContacts(Pastoral $pastoral)
    {
        // Blow away any existing contacts in Synergetic

        $this->connector->executeQuery("
                DELETE FROM     PastoralCareContacts 
                WHERE           PastoralCareSeq = :externalId
            ",
            ['externalId' => $pastoral->getExternalId()]
        );

        // Retrieve all access records stored in Schoolbox

        $access = $this->getPastoralAccessMapper()->findByColumn('pastoral_id', $pastoral->getId());

        $order = 1;

        /** @var PastoralAccess $accessRecord */
        foreach ($access as $accessRecord) {
            $user = $accessRecord->getUser();
            if ($user instanceof User) {
                if ($this->isExternalIdValid($user->getExternalId())) {
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
                          :externalId,
                          :order,
                          0,
                          :email,
                          :userId
                        );
                    ";

                    $this->connector->executeQuery($query, [
                        'externalId' => $pastoral->getExternalId(),
                        'order' => $order++,
                        'email' => $user->getEmail(),
                        'userId' => $user->getExternalId(),
                    ]);

                    $order++;
                }
            }
        }

        return true;
    }

    // Comment synchronization

    /**
     * @inheritdoc
     */
    public function createComment(PastoralComment $comment)
    {
        // Annoyingly the use of self::COLUMN_CREATED_TIMESTAMP does not populate the model with the creation date after saving
        // so we need to reload the model from the database to get the date it was created

        try {
            /** @var PastoralComment $comment */
            $comment = $this->reloadModel($comment);

            /** @var Pastoral $pastoral */
            $pastoral = $this->getPastoralMapper()->findById($comment->getPastoral()->getId());

            /** @var User $owner */
            $owner = $this->userMapper->findById($pastoral->getOwner()->getId());
        } catch (MapperException $e) {
            // Can't find either the pastoral record or the authors Synergetic ID

            return false;
        }

        // Can only do this if the pastoral record has an external ID
        if (!$this->isExternalIdValid($pastoral->getExternalId()) || !$this->isExternalIdValid($owner->getExternalId())) {
            return false;
        }

        // Execute the query

        $query =
            "
            INSERT INTO PastoralCareComments 
            (
                PastoralCareSeq,
                IndexOrder,
                Comment,
                CreatedDate,
                CreatedById,
                ModifiedDate,
                ModifiedById
            ) VALUES (
                :externalId,
                COALESCE((SELECT MAX(IndexOrder) + 1 FROM PastoralCareComments WHERE PastoralCareSeq = :externalId2), 1),
                :comment,
                :createdDate,
                :createdOwnerId,
                :modifiedDate,
                :modifiedOwnerId
            )
        ";

        $result = $this->connector->executeQuery($query, [
            'externalId' => $pastoral->getExternalId(),
            'externalId2' => $pastoral->getExternalId(),
            'comment' => $comment->getBody(),
            'createdDate' => $comment->getCreatedAt()->getDatabase(),
            'createdOwnerId' => $comment->getOwner()->getExternalId(),
            'modifiedDate' => $comment->getCreatedAt()->getDatabase(),
            'modifiedOwnerId' => $comment->getOwner()->getExternalId(),
        ]);

        $comment->setExternalCode($this->connector->lastInsertId());
        $this->getPastoralCommentMapper()->save($comment);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function deleteComment(PastoralComment $comment)
    {
        if ($this->isExternalIdValid($comment->getExternalCode())) {
            return $this->connector->executeQuery(
                "DELETE FROM PastoralCareComments WHERE PastoralCareCommentsSeq = :external_code",
                ['external_code' => $comment->getExternalCode()]
            );
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deletePastoral(Pastoral $pastoral)
    {
        // Option to delete pastoral records from Synergetic has to be explicitly allowed in the schools configuration

        if ($this->config->get('pastoral_external_delete')) {
            // Make sure the pastoral record has a valid external ID

            if ($this->isExternalIdValid($pastoral->getExternalId())) {
                // These must be done in order due to FK constraints in Synergetic database

                // Delete contacts

                $this->connector->executeQuery(
                    "DELETE FROM PastoralCareContacts WHERE PastoralCareSeq = :externalId",
                    ['externalId' => $pastoral->getExternalId()]
                );

                // Delete comments

                $this->connector->executeQuery(
                    "DELETE FROM PastoralCareComments WHERE PastoralCareSeq = :externalId",
                    ['externalId' => $pastoral->getExternalId()]
                );

                // Delete the pastoral care record itself now that its dependencies are gone

                $this->connector->executeQuery(
                    "DELETE FROM tPastoralCare WHERE PastoralCareSeq = :externalId",
                    ['externalId' => $pastoral->getExternalId()]
                );

                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function uploadPastoralDocument(UploadQueue $upload)
    {
        // Temporarily increase the memory limit and timeout so we can get the file across to Synergetic

        ini_set('memory_limit', '2048M');
        set_time_limit(0);

        // This whole thing is a bit of an ass, unfortunately Synergetic stores its DocMan files in
        // the actual database- which means we need to shove the file into a SQL query as a hex string
        // which is totally inefficient and very slow :(

        $pastoral = $upload->getPastoral();
        $storage = $upload->getStorage();

        $pastoralDocumentClassificationMapper = DocumentClassificationMapper::getInstance();

        if ($pastoral->getType()->getDocumentClassification() instanceof DocumentClassification) {
            $documentClassification = $pastoralDocumentClassificationMapper->findById(
                $pastoral->getType()->getDocumentClassification()->getId()
            );
        } else {
            $documentClassification = null;
        }

        // Save the file into to the database

        $externalId = $upload->getExternalId();
        $studentExternalId = $upload->getStudentExternalId();
        $date = date("Y-m-d H:i:s");
        $sourceDate = $storage->getCreatedAt()->getDatabase();
        $extension = strtoupper($storage->getExtension());
        $pastoralExternalId = $pastoral->getExternalId();

        // Documents get uploaded with a classification code that is based on the pastoral records type

        if ($documentClassification instanceof DocumentClassification) {
            $classificationCode = $documentClassification->getExternalCode();
        } else {
            $classificationCode = '';
        }

        // Implicit CONVERT(VARBINARY, :document, 1) is a temporary hack due to a bug in PDO_SQLSRV driver, without this you
        // will receive an MSSQL error about type conversion in spite of passing the data as PDO:PARAM_LOB

        $sql = "
            INSERT INTO {$this->schemaMedia}.tDocuments
            (
                Description,
                ClassificationCode,
                DocumentCreatedByID,
                DocumentModifiedByID,
                Document,
                DocumentCreatedDate,
                DocumentModifiedDate,
                SourceDate,           
                DocumentType
            )
            VALUES
            (
                :filename,
                :classificationCode,
                :createdById,
                :modifiedById,
                :document,
                :createdDate,
                :modifiedDate,
                :sourceDate,
                :documentType
            );            
        ";

        $query = $this->connector->prepareQuery($sql, [
            'filename' => ['value' => $storage->getFilename()],
            'classificationCode' => ['value' => $classificationCode],
            'createdById' => ['value' => $externalId],
            'modifiedById' => ['value' => $externalId],
            'document' => ['value' => $storage->readStream(), 'type' => PDO::PARAM_LOB],
            'createdDate' => ['value' => $date],
            'modifiedDate' => ['value' => $date],
            'sourceDate' => ['value' => $sourceDate],
            'documentType' => ['value' => $extension],
        ]);

        if (!$query->execute()) {
            throw new ExternalException('Error uploading pastoral document to Synergetic database.');
        }

        $documentReferencesSeq = $this->connector->lastInsertId();

        // Add a document reference to the student so it shows up on their normal community record

        $query = $this->connector->prepareQuery(
            "
                INSERT INTO {$this->schemaMedia}.DocumentReferences (ID, DocumentReferencesSeq) 
                VALUES (:id, :documentReferencesSeq);
            ",
            [
                'id' => $studentExternalId,
                'documentReferencesSeq' => $documentReferencesSeq,
            ]
        );

        if (!$query->execute()) {
            throw new ExternalException('Error linking pastoral document to record in Synergetic database.');
        }

        // Specifically link it to their pastoral record as well

        $sql =
            "
            INSERT INTO {$this->schemaMedia}.tDocumentReferences
            (
                CommunityID,
                tDocumentsSeq,
                SynDatabaseCode,
                RelatedSeqModuleCode,
                SortOrder,
                RelatedSeq
            )
            SELECT  0,
                    :documentReferencesSeq,
                    '',
                    'SPC',
                    COALESCE(MAX(SortOrder) + 1, 0),
                    :pastoralExternalId
            FROM    {$this->schemaMedia}.tDocumentReferences 
            WHERE   tDocumentsSeq = :documentReferencesSeq2
        ";

        $query = $this->connector->prepareQuery($sql, [
            'pastoralExternalId' => $pastoralExternalId,
            'documentReferencesSeq' => $documentReferencesSeq,
            'documentReferencesSeq2' => $documentReferencesSeq,
        ]);

        if (!$query->execute()) {
            throw new ExternalException('Error linking pastoral document to record in Synergetic database.');
        }

        return $documentReferencesSeq;
    }

    // Helper functions below

    /**
     * Return the pastoral severity to use on imported records
     *
     * @return null|PastoralSeverity
     */
    protected function getPastoralSeverity()
    {
        if ($this->config->get('pastoral_severity')) {
            // Default all imported items to the lowest priority as there is no concept of this in Synergetic

            try {
                /** @var PastoralSeverity $severity */
                $severity = $this->getPastoralSeverityMapper()->findOneByColumn("order", 0);
            } catch (Exception $e) {
                // Pastoral severity was not found, this should never happen as it is a mandatory field in Schoolbox.
                // We will just create a default one called "Low" in case it every does happen

                /** @var PastoralSeverity $severity */
                $severity = $this->getPastoralSeverityMapper()->createModel();
                $severity->setOrder(0);
                $severity->setName("Low");
                $this->getPastoralSeverityMapper()->save($severity);
            }

            return $severity;
        }

        // If severity is disabled we will save NULL into the records instead

        return null;
    }

    /**
     * Return an array ready to shunt into the Synergetic DB query
     *
     * @param Pastoral $pastoral
     *
     * @return array
     * @throws ExternalException
     */
    private function getQueryParameters($pastoral)
    {
        // Get the students Synergetic ID

        $studentExternalId = $this->getUserMapper()->findByIdWithFallback(
            $pastoral->getStudent()->getId()
        )->getExternalId();

        // If we don't have a valid external ID- this is not going to work

        if (!$this->isExternalIdValid($studentExternalId)) {
            throw new ExternalException('Unable to locate valid Synergetic ID for student');
        }

        // Retrieve pastoral type, if none we need to store a blank string (not null)

        if (empty($pastoral->getType())) {
            $pastoralTypeCode = '';
        } else {
            $pastoralTypeCode =
                empty($pastoral->getType()->getExternalCode())
                    ? ''
                    : $pastoral->getType()->getExternalCode();
        }

        // Retrieve pastoral subtype, if there is no type as well we need to blank this out

        if (empty($pastoralTypeCode)) {
            $pastoralSubTypeCode = '';
        } else {
            $pastoralSubTypeCode =
                empty($pastoral->getSubType()->getExternalCode())
                    ? ''
                    : $pastoral->getSubType()->getExternalCode();
        }

        // Retrieve our "external document classification" based on the pastoral type

        if (empty($pastoral->getType())) {
            $documentClassificationCode = '';
        } else {
            if (empty($pastoral->getType()->getDocumentClassification())) {
                $documentClassificationCode = '';
            } else {
                try {
                    $documentClassification = PastoralDocumentClassificationMapper
                        ::getInstance()
                        ->findById(
                            $pastoral->getType()->getDocumentClassification()->getId()
                        );
                    $documentClassificationCode = $documentClassification->getExternalCode();
                } catch (MapperException $e) {
                    $documentClassificationCode = '';
                }
            }
        }

        $this->htmlConverter->setHtml($pastoral->getBodyPublic());
        $bodyPublicText = $this->htmlConverter->getText();
        $bodyPublicText = html_entity_decode($bodyPublicText, ENT_QUOTES);

        $this->htmlConverter->setHtml($pastoral->getBodyPrivate());
        $bodyPrivateText = $this->htmlConverter->getText();
        $bodyPrivateText = html_entity_decode($bodyPrivateText, ENT_QUOTES);


        // Return an array containing the parameters for the insert/update query

        $params = [
            'studentExternalId' => $studentExternalId,
            'itemDate' => $pastoral->getCreatedAt()->getDatabase(),
            'publicBody' => substr($bodyPublicText, 0, 2000),
            'privateBody' => substr($bodyPrivateText, 0, 2000),
            'pastoralTypeCode' => substr($pastoralTypeCode, 0, 15),
            'pastoralSubTypeCode' => substr($pastoralSubTypeCode, 0, 15),
            'classificationCode' => substr($documentClassificationCode, 0, 15),
            'createdDate' => $pastoral->getCreatedAt()->getDatabase(),
            'createdOwnerId' => $pastoral->getOwner()->getExternalId(),
            'modifiedDate' => $pastoral->getCreatedAt()->getDatabase(),
            'modifiedOwnerId' => $pastoral->getOwner()->getExternalId(),
        ];

        // If this already has an external ID we are doing an update, so require an extra parameter

        if (!empty($pastoral->getExternalId())) {
            $params['externalId'] = $pastoral->getExternalId();
        }

        return $params;
    }

    /**
     * This is an ugly hack...
     * Annoyingly the use of self::COLUMN_UPDATED_TIMESTAMP does not repopulate the model with the updated date after
     * saving so this just reloads the object passed from the database to get the date it was modified
     *
     * @param object $object
     *
     * @return object
     * @throws ExternalException
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    private function reloadModel($object)
    {
        if ($object == null) {
            return $object;
        }

        $modelClass = get_class($object);

        // Grab an instance of the objects mapper

        /** @var string $mapperClass */
        $mapperClass = str_replace('Model', 'Mapper', $modelClass);

        if (!class_exists($mapperClass) || $mapperClass == $modelClass) {
            throw new ExternalException("Unable to locate mapper for object ({$mapperClass})");
        }

        /** @var BaseMapper $mapperInstance */
        $mapperInstance = call_user_func($mapperClass . '::getInstance');

        return $mapperInstance->findOneByColumn($mapperInstance->getIdentifierColumn(), $object->getId(), [], ['cache' => false]);
    }

    /**
     * Finds a schoolbox user by their external ID, returning null if not found
     *
     * @param string $externalId Synergetic ID of person we want to find in Schoolbox
     *
     * @return null|User
     */
    protected function getSchoolboxUser($externalId)
    {
        // Check the cache first

        if (!isset($this->userCache[$externalId])) {
            try {
                $this->userCache[$externalId] = $this->getUserMapper()->findByExternalId($externalId);
            } catch (MapperException $e) {
                $this->userCache[$externalId] = null;
            }
        }

        return $this->userCache[$externalId];
    }

    /**
     * Finds/creates a pastoral care type that matches one from Synergetics database
     *
     * @param array $record Pastoral care record
     *
     * @return PastoralType
     */
    protected function getPastoralType($record)
    {
        // Check the cache first

        if (!isset($this->typeCache[$record["TypeDescription"]])) {
            // Didn't find a cached record. Try to retrieve an existing pastoral type from Schoolbox

            try {
                $type = $this->getPastoralTypeMapper()->findOneByColumn('name', $record["TypeDescription"]);
            } catch (MapperException $e) {
                // The records pastoral type doesn't exist in Schoolbox, create one that matches

                $type = $this->getPastoralTypeMapper()->createModel();
                $type->setName($record["TypeDescription"]);
                $type->setExternalCode($record["TypeCode"]);
                $this->getPastoralTypeMapper()->save($type);
            }

            $this->typeCache[$record["TypeDescription"]] = $type;
        }

        return $this->typeCache[$record["TypeDescription"]];
    }

    /**
     * Finds/creates a pastoral care category that matches one from the Synergetic database
     *
     * @param array $record Pastoral care record
     *
     * @return PastoralSubType
     * @throws MapperException
     */
    protected function getPastoralSubType(array $record)
    {
        // Check the cache first

        if (!isset($this->pastoralTypeCache[$record['CategoryCode']])) {
            // Didn't find a cached record. Try to retrieve an existing pastoral type from Schoolbox

            $pastoralType = $this->getPastoralType($record);

            try {
                $category = $this->getPastoralSubTypeMapper()->findOneByColumnList([
                    'name' => $record["CategoryDescription"],
                    'pastoral_type_id' => $pastoralType->getId(),
                ]);
            } catch (MapperException $e) {
                // The records pastoral category doesn't exist in Schoolbox, create one that matches

                $category = $this->pastoralSubTypeMapper->createModel();
                $category->setName($record["CategoryDescription"]);
                $category->setExternalCode($record["CategoryCode"]);
                $category->setPastoralType($pastoralType);
                $this->getPastoralSubTypeMapper()->save($category);
            }

            $this->pastoralTypeCache[$record["CategoryCode"]] = $category;
        }

        return $this->pastoralTypeCache[$record["CategoryCode"]];
    }

    // Getters & Setters

    /**
     * @return UserMapper
     */
    public function getUserMapper()
    {
        return $this->userMapper;
    }

    /**
     * @param $userMapper
     *
     * @return $this
     */
    public function setUserMapper($userMapper)
    {
        $this->userMapper = $userMapper;

        return $this;
    }

    /**
     * @return PastoralMapper
     */
    public function getPastoralMapper()
    {
        return $this->pastoralMapper;
    }

    /**
     * @param PastoralMapper $pastoralMapper
     *
     * @return $this
     */
    public function setPastoralMapper($pastoralMapper)
    {
        $this->pastoralMapper = $pastoralMapper;

        return $this;
    }

    /**
     * @return PastoralTypeMapper
     */
    public function getPastoralTypeMapper()
    {
        return $this->pastoralTypeMapper;
    }

    /**
     * @param $pastoralTypeMapper
     *
     * @return $this
     */
    public function setPastoralTypeMapper($pastoralTypeMapper)
    {
        $this->pastoralTypeMapper = $pastoralTypeMapper;

        return $this;
    }

    /**
     * @return PastoralLogMapper
     */
    public function getPastoralLogMapper()
    {
        return $this->pastoralLogMapper;
    }

    /**
     * @param $pastoralLogMapper
     *
     * @return $this
     */
    public function setPastoralLogMapper($pastoralLogMapper)
    {
        $this->pastoralLogMapper = $pastoralLogMapper;

        return $this;
    }

    /**
     * @return PastoralSubTypeMapper
     */
    public function getPastoralSubTypeMapper()
    {
        return $this->pastoralSubTypeMapper;
    }

    /**
     * @param $pastoralSubTypeMapper
     *
     * @return $this
     */
    public function setPastoralSubTypeMapper($pastoralSubTypeMapper)
    {
        $this->pastoralSubTypeMapper = $pastoralSubTypeMapper;

        return $this;
    }

    /**
     * @return PastoralAccessMapper
     */
    public function getPastoralAccessMapper()
    {
        return $this->pastoralAccessMapper;
    }

    /**
     * @param $pastoralAccessMapper
     *
     * @return $this
     */
    public function setPastoralAccessMapper($pastoralAccessMapper)
    {
        $this->pastoralAccessMapper = $pastoralAccessMapper;

        return $this;
    }

    /**
     * @return PastoralAttachmentMapper
     */
    public function getPastoralAttachmentMapper()
    {
        return $this->pastoralAttachmentMapper;
    }

    /**
     * @param $pastoralAttachmentMapper
     *
     * @return $this
     */
    public function setPastoralAttachmentMapper($pastoralAttachmentMapper)
    {
        $this->pastoralAttachmentMapper = $pastoralAttachmentMapper;

        return $this;
    }

    /**
     * @return PastoralCommentMapper
     */
    public function getPastoralCommentMapper()
    {
        return $this->pastoralCommentMapper;
    }

    /**
     * @param $pastoralCommentMapper
     *
     * @return $this
     */
    public function setPastoralCommentMapper($pastoralCommentMapper)
    {
        $this->pastoralCommentMapper = $pastoralCommentMapper;

        return $this;
    }

    /**
     * @return PastoralSeverityMapper
     */
    public function getPastoralSeverityMapper()
    {
        return $this->pastoralSeverityMapper;
    }

    /**
     * @param $pastoralSeverityMapper
     *
     * @return $this
     */
    public function setPastoralSeverityMapper($pastoralSeverityMapper)
    {
        $this->pastoralSeverityMapper = $pastoralSeverityMapper;

        return $this;
    }

    /**
     * @return PastoralDocumentClassificationMapper
     */
    public function getPastoralDocumentClassificationMapper()
    {
        return $this->pastoralDocumentClassificationMapper;
    }

    /**
     * @param $pastoralDocumentClassificationMapper
     *
     * @return $this
     */
    public function setPastoralDocumentClassificationMapper($pastoralDocumentClassificationMapper)
    {
        $this->pastoralDocumentClassificationMapper = $pastoralDocumentClassificationMapper;

        return $this;
    }
}
