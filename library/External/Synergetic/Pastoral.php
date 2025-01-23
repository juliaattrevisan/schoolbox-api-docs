<?php

/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Alaress\Database\Exception as DatabaseException;
use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Html2Text\Html2Text;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\External\Exception as ExternalException;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Attachment\Model as PastoralAttachment;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Comment\Model as PastoralComment;
use Schoolbox\Pastoral\DocumentClassification\Mapper as PastoralDocumentClassificationMapper;
use Schoolbox\Pastoral\DocumentClassification\Model as PastoralDocumentClassification;
use Schoolbox\Pastoral\Log\Mapper as PastoralLogMapper;
use Schoolbox\Pastoral\Log\Model as PastoralLog;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Pastoral\RecordAction\Mapper as RecordActionMapper;
use Schoolbox\Pastoral\Revision\Mapper as PastoralRevisionMapper;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\SubType\Mapper as PastoralSubTypeMapper;
use Schoolbox\Pastoral\SubType\Model as PastoralSubType;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\UploadQueue\Model as UploadQueue;
use Schoolbox\Realtime\Schedule\Trigger\Test\MockPropagator;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

class External_Synergetic_Pastoral implements External_PastoralInterface
{
    use External_MssqlConnector;
    use External_Synergetic_ValidateExternalIdTrait;

    /**
     * A style for converting data types
     *
     * @var int
     * @see https://docs.microsoft.com/en-us/sql/t-sql/functions/cast-and-convert-transact-sql?view=sql-server-2017
     */
    private const STYLE_ODBC_CANONICAL_WITH_MILLISECONDS = 121; // yyyy-mm-dd hh:mi:ss.mmm(24h);

    // Mappers
    protected const MODULE_CODE = 'SPC';

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

    /** @var PastoralRevisionMapper $pastoralRevisionMapper */
    protected $pastoralRevisionMapper;

    // The media database schema varies between v67/68 of Synergetic, this will hold the schema name we should use

    /** @var string $schemaMedia */
    private $schemaMedia;

    // Cached objects for use during importing

    /** @var array $userCache Cache of users for use during importing */
    private $userCache = [];

    /** @var array $typeCache Cache of pastoral types for use during importing */
    private array $typeCache = [];

    /** @var int|null sequence */
    private ?int $typeSequence = 0;

    /** @var array $subtypeCache Cache of pastoral subtypes for use during importing */
    private array $subtypeCache = [];

    /** @var int|null sequence */
    private ?int $subtypeSequence = 0;

    // This is a magic Synergetic string that gets set as the contents of a pastoral record when there are no private contents

    const BLANK_PRIVATE_RECORD = "No Private Details Entered";

    /**
     * @const Character encoding for fields within this database.
     *        Required when converting field values from Schoolbox to Synergetic.
     * TODO: consider sharing with the one in Markbook.php
     */
    const CHARACTER_ENCODING = 'UTF-8';

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

        if ($externalPing->getVersion() > 67) {
            $this->schemaMedia = "media";
        } else {
            $this->schemaMedia = "SynergyOneMedia.dbo";
        }

        // Grab mapper instances
        $this->userMapper                           = UserMapper::getInstance();
        $this->pastoralMapper                       = PastoralMapper::getInstance();
        $this->pastoralAccessMapper                 = PastoralAccessMapper::getInstance();
        $this->pastoralDocumentClassificationMapper = PastoralDocumentClassificationMapper::getInstance();
        $this->pastoralCommentMapper                = PastoralCommentMapper::getInstance();
        $this->pastoralLogMapper                    = PastoralLogMapper::getInstance();
        $this->pastoralTypeMapper                   = PastoralTypeMapper::getInstance();
        $this->pastoralSubTypeMapper                = PastoralSubTypeMapper::getInstance();
        $this->pastoralSeverityMapper               = PastoralSeverityMapper::getInstance();
        $this->pastoralRevisionMapper               = PastoralRevisionMapper::getInstance();
        $this->pastoralAttachmentMapper             = PastoralAttachmentMapper::getInstance();
    }

    /**
     * Returns a query for finding pastoral records in external system
     *
     * @return string
     */
    protected function getSqlPastoralSelect(): string
    {
        $style = self::STYLE_ODBC_CANONICAL_WITH_MILLISECONDS;

        return "
        SELECT        dbo.tPastoralCare.PastoralCareSeq,
                      dbo.tPastoralCare.ID,
                      dbo.tPastoralCare.ClassCode,
                      CONVERT(varchar, dbo.tPastoralCare.ItemDate, {$style}) AS ItemDate,
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
                      CONVERT(varchar, dbo.tPastoralCare.CreatedDate, {$style}) AS CreatedDate,
                      dbo.tPastoralCare.CreatedByID,
                      CONVERT(varchar, dbo.tPastoralCare.ModifiedDate, {$style}) AS ModifiedDate,
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
        INNER JOIN    dbo.{$this->getStudentViewName()} ON dbo.{$this->getStudentViewName()}.ID = dbo.tPastoralCare.ID
        INNER JOIN    dbo.FileSemesters ON
                          dbo.FileSemesters.FileYear = dbo.{$this->getStudentViewName()}.FileYear AND
                          dbo.FileSemesters.FileSemester = dbo.{$this->getStudentViewName()}.FileSemester AND
                          dbo.FileSemesters.SystemCurrentFlag = 1
                          {$this->getExtraFileSemJoinClause()}
    ";
    }


    /**
     * Returns the name of the database view containing student information
     *
     * @return string
     */
    protected function getStudentViewName(): string
    {
        return 'vStudentsAll';
    }


    /**
     * Returns any extra clause to apply to the JOIN from `FileSemesters` to the student database view
     *
     * @return string
     */
    protected function getExtraFileSemJoinClause(): string
    {
        return "
            AND NOT ({$this->getStudentViewName()}.StudentStatusSynergyMeaning IN ('LEFT', 'LEAVING')
            AND DATEDIFF(DAY, {$this->getStudentViewName()}.StudentLeavingDate, GETDATE()) > 0)
        ";
    }
    /**
     * @inheritdoc
     */
    public function getPastoral(Pastoral $pastoral): array
    {
        $query = "{$this->getSqlPastoralSelect()} WHERE dbo.tPastoralCare.PastoralCareSeq = :externalId";

        return $this->connector->executeQuery($query)->fetch() ?: [];
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
    public function importPastoral(
        User $user,
        User $defaultOwner,
        bool $importParentAccess,
        bool $importRecords,
        bool $importAttachments,
        bool $deleteRecords
    ): void {
        // Bump up the memory limit just to make sure we don't bomb out

        ini_set('memory_limit', '2048M');

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
        $log->setCreatedAt(Alaress_Date_Format::now(new \Alaress\Date\Clock()));
        $this->pastoralLogMapper->save($log);

        if ($importRecords) {
            // Only load a severity if it is enabled by policy

            $severity = $this->getPastoralSeverity();

            // Retrieve an iterator for all current students pastoral care records from Synergetic sorted by student name as
            // it makes searching the import log much easier.

            $highestPastoralSeq = 0;

            // Keep the pastoral records we create so we can add audience members and remove the need for MARS

            $pastoralRecordsCreated = [];

            do {
                $queryRecords = "
                    {$this->getSqlPastoralSelect()}
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

                    $heading = mb_substr(trim($record["Details"]), 0, 50, self::CHARACTER_ENCODING);

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
                    $logRecord->setCreatedAt(Alaress_Date_Format::now(new \Alaress\Date\Clock()));

                    try {
                        $student = $this->getSchoolboxUser($record["ID"]);
                        $owner = $this->getSchoolboxUser($record["CreatedByID"]) ?: $defaultOwner;

                        if (!$student instanceof User) {
                            // The student of the pastoral care record is not available in Schoolbox, skip this record

                            $logRecord->setResult("Skipped Unknown Student ({$record["PastoralCareSeq"]})");
                            $this->pastoralLogMapper->save($logRecord);
                            continue;
                        }

                        $logRecord->setCreatorName($owner->getFullName());
                        $logRecord->setStudentName($student->getFullName());

                        //Find existing records, if they exist you may either delete them or skip them depending on flag
                        $existingRecords = $this->pastoralMapper->findByColumn('external_id', $record["PastoralCareSeq"]);
                        if (count($existingRecords)) {
                            if ($deleteRecords) {
                                // If this pastoral care record already exists in Schoolbox, delete it so we don't end up with duplicate
                                // records. Could update the record, but its contents may have deviated it was originally imported, and
                                // deleting will at least keep that history
                                $this->pastoralMapper->deleteByColumn("external_id", $record["PastoralCareSeq"]);
                            } else {
                                $logRecord->setResult("Skipped record already exists ({$record["PastoralCareSeq"]})");
                                $this->pastoralLogMapper->save($logRecord);
                                continue;
                            }
                        }

                        $logRecord->setResult("Importing ({$record["PastoralCareSeq"]})");
                        $this->pastoralLogMapper->save($logRecord);

                        // Only create record if both public and private aren't blank

                        if (!empty($record["Details"]) || !empty($record["PrivateDetails"])) {
                            /** @var Pastoral $pastoralRecord */
                            $pastoralRecord = $this->pastoralMapper->createModel();
                            $pastoralRecord->setExternalId($record["PastoralCareSeq"]);
                            $pastoralRecord->setOwner($owner);
                            $pastoralRecord->setStudent($student);
                            $pastoralRecord->setHeading($heading);
                            $pastoralRecord->setBodyPublic("<p>{$record["Details"]}</p>");
                            $pastoralRecord->setBodyPrivate("<p>{$record["PrivateDetails"]}</p>");
                            $this->setPastoralType($pastoralRecord, $record, $owner);
                            $createdDate = new Alaress_Date_Format($record['CreatedDate'], Alaress_Date_Format::DATABASE_FORMAT_MS);
                            $occurredDate = new Alaress_Date_Format($record['ItemDate'], Alaress_Date_Format::DATABASE_FORMAT_MS);
                            $pastoralRecord->setCreatedAt($createdDate);
                            $pastoralRecord->setOccurredOn($occurredDate);
                            if ($record['ModifiedByID']) {
                                $modifier = $this->getSchoolboxUser($record['ModifiedByID']) ?: $defaultOwner;
                                $pastoralRecord->setModifiedBy($modifier);
                            } else {
                                $pastoralRecord->setModifiedBy($defaultOwner);
                            }

                            $modifiedDate = $createdDate;
                            if ($record['ModifiedDate']) {
                                $modifiedDate = new Alaress_Date_Format($record['ModifiedDate'], Alaress_Date_Format::DATABASE_FORMAT_MS);
                                $pastoralRecord->setUpdatedAt($modifiedDate);
                            }

                            if ($this->config->get('pastoral_severity')) {
                                $pastoralRecord->setSeverity($severity);
                            }

                            $this->pastoralMapper->save($pastoralRecord);

                            // Because updated_at is clobbered:
                            $this->pastoralMapper->resetUpdatedTimestamp($pastoralRecord, $modifiedDate);

                            // Need to do the same for the revision too
                            foreach ($this->pastoralRevisionMapper->findForRecord($pastoralRecord) as $revision) {
                                $this->pastoralRevisionMapper->resetUpdatedTimestamp($revision, $modifiedDate);
                            }

                            $logRecord->setResult("OK");
                            $logRecord->setPastoralId($pastoralRecord->getId());

                            $pastoralRecordsCreated[] = $pastoralRecord;

                            /* Only add attachments if the option is on,
                             * and there's an externalId
                             */
                            if ($importAttachments && null !== $pastoralRecord->getExternalId()) {
                                // Import attachments from Synergetic DocMan

                                $queryDocuments = $this->connector->getQueryIterator("
                                    SELECT ref.RelatedSeq as external_id,
                                           doc.tDocumentsSeq as document_external_id,
                                           doc.Description as [filename],
                                           DocumentType as extension,
                                           [Document] as [blob]
                                    FROM   {$this->schemaMedia}.tDocuments doc
                                    JOIN   {$this->schemaMedia}.tDocumentReferences ref on ref.tDocumentsSeq = doc.tDocumentsSeq
                                    WHERE  RelatedSeqModuleCode = 'SPC'
                                    AND ref.RelatedSeq = {$pastoralRecord->getExternalId()}
                                ");

                                foreach ($queryDocuments as $document) {
                                    try {
                                        $filename = tempnam(sys_get_temp_dir(), 'pastoral_attachment');
                                        file_put_contents($filename, $document["blob"]);

                                        /** @var StorageMapper $storageMapper */
                                        $storageMapper = StorageMapper::getInstance();
                                        $storageObject = $storageMapper->accept("{$document['filename']}.{$document['extension']}", $filename, false);

                                        $attachment = $this->pastoralAttachmentMapper->createModel();
                                        $attachment->setPastoral($pastoralRecord);
                                        $attachment->setStorage($storageObject);
                                        $attachment->setRealname($storageObject->getFilename());
                                        $attachment->setPrivate(true);
                                        $attachment->setExternalId($document['document_external_id']);
                                        $this->pastoralAttachmentMapper->save($attachment);

                                        $log = $this->pastoralLogMapper->createModel();
                                        $log->setImportId($importId);
                                        $log->setUserId($user->getId());
                                        $log->setPastoralId($pastoralRecord->getId());
                                        $log->setCreatorName($pastoralRecord->getOwner()->getFullName());
                                        $log->setStudentName($pastoralRecord->getStudent()->getFullName());
                                        $log->setHeading("Imported Attachment '{$document["filename"]}'");
                                        $log->setResult("OK");
                                        $log->setCreatedAt(Alaress_Date_Format::now(new \Alaress\Date\Clock()));
                                        $this->pastoralLogMapper->save($log);

                                        // temp file will be removed by storage manager, but just in case
                                        if (file_exists($filename)) {
                                            unlink($filename);
                                        }
                                    } catch (MapperException $e) {
                                        // Pastoral record for this attachment wasn't found?
                                    }
                                }
                            }
                        } else {
                            // Skip blank records

                            $logRecord->setResult("Skipped Blank Record ({$record["PastoralCareSeq"]})");
                        }
                    } catch (DatabaseException $e) {
                        // If ANYTHING goes wrong during the import we need to log it. The import is running in a detached
                        // state, so there is no other way to communicate with the user, so an exception would leave the user
                        // thinking the import is still running.

                        $logRecord->setResult("Import Error: {$e->getMessage()}");
                    }

                    $this->pastoralLogMapper->save($logRecord);
                }
            } while (count($records) > 0);

            // Import audience members for each record we created

            foreach ($pastoralRecordsCreated as $pastoralRecord) {
                $queryAudience = $this->connector->executeQuery(
                    '
                    SELECT  ID
                    FROM    PastoralCareContacts
                    WHERE   PastoralCareSeq = :pastoralCareSeq
                ',
                    ['pastoralCareSeq' => $pastoralRecord->getExternalId()]
                );

                $audienceRecords = $queryAudience->fetchAll();

                $queryAudience->getPDOStatement()->errorCode();

                // Cache the access records in case we need to create identical ones for the private record, will

                foreach ($audienceRecords as $audience) {
                    /** @var PastoralLog $logAccess */
                    $logAccess = $this->pastoralLogMapper->createModel();
                    $logAccess->setImportId($importId);
                    $logAccess->setUserId($user->getId());
                    $logAccess->setPastoralId($pastoralRecord->getId());
                    $logAccess->setCreatorName($pastoralRecord->getOwner()->getFullName());
                    $logAccess->setStudentName($pastoralRecord->getStudent()->getFullName());
                    $logAccess->setCreatedAt(Alaress_Date_Format::now(new \Alaress\Date\Clock()));

                    $audienceUser = $this->getSchoolboxUser($audience["ID"]);

                    if ($audienceUser instanceof User) {
                        // If the school does not want parents given access to pastoral records, then don't add them

                        if ($importParentAccess || $audienceUser->getRole()->isStaff()) {
                            /** @var PastoralAccess $accessRecord */
                            $accessRecord = $this->pastoralAccessMapper->createModel();
                            $accessRecord->setPastoral($pastoralRecord);
                            $accessRecord->setAccess(PastoralAccessMapper::ACCESS_PUBLIC);
                            $accessRecord->setUser($audienceUser);
                            $this->pastoralAccessMapper->save($accessRecord);

                            $logAccess->setResult("Audience: {$audienceUser->getFullName()}");
                        } else {
                            $logAccess->setResult("Skipped Audience: {$audienceUser->getFullName()}");
                        }
                    } else {
                        $logAccess->setResult("Error Audience: {$audience["ID"]}");
                    }

                    $this->pastoralLogMapper->save($logAccess);
                }
            }
        }

        $log = $this->pastoralLogMapper->createModel();
        $log->setImportId($importId);
        $log->setUserId($user->getId());
        $log->setHeading("Import Completed");
        $log->setResult("Completed");
        $log->setCreatedAt(Alaress_Date_Format::now(new \Alaress\Date\Clock()));

        $this->pastoralLogMapper->save($log);
    }

    // Pastoral record synchronization

    /**
     * @inheritdoc
     */
    public function syncPastoral(Pastoral $pastoral, bool $prependTitle = false): bool
    {
        $this->assertExternalCodeValid($pastoral);

        // Get insert/update query based on:
        // * the absence/presence of an external ID on the pastoral record
        // * if it exists, verify the record actually exists: if it doesn't,
        //   recreate it
        $isCreate = empty($pastoral->getExternalId()) || !$this->exists($pastoral);
        if ($isCreate) {
            // Ensure that the external ID is cleared, so a parameter is not
            // created for it in an insert.
            $pastoral->setExternalId(null);
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

        $inOuterTransaction = $this->connector->inTransaction();
        try {
            // TODO: this check should not be necessary, but without this, this throws nested transaction exception
            if (!$inOuterTransaction) {
                $this->connector->beginTransaction();
            }
            $params = $this->getQueryParameters($pastoral, $prependTitle);
            if ($this->connector->executeQuery($query, $params)) {
                // If this is a new record we need to store the ID of the newly created Synergetic record

                if ($isCreate) {
                    // gets pastoralCareSeq for the inserted record, and set it to SB pastoral table
                    // lastInsertId() does not work because of DB trigger attached to this table
                    $pastoralCareSeq = $this->connector->executeQuery(
                        "SELECT PastoralCareSeq FROM tPastoralCare
                         WHERE
                            ID = :studentExternalId AND
                            ItemDate = :itemDate AND
                            TypeCode = :pastoralTypeCode AND
                            CategoryCode = :pastoralSubTypeCode AND
                            CreatedDate = :createdDate AND
                            CreatedById = :createdOwnerId AND
                            ClassificationCode = :classificationCode
                        ORDER BY PastoralCareSeq DESC",
                        [
                            $params['studentExternalId'],
                            $params['itemDate'],
                            $params['pastoralTypeCode'],
                            $params['pastoralSubTypeCode'],
                            $params['createdDate'],
                            $params['createdOwnerId'],
                            $params['classificationCode'],
                        ]
                    )->fetchColumn();

                    if (!$pastoralCareSeq) {
                        if (!$inOuterTransaction) {
                            $this->connector->rollBack();
                        }
                        throw new ExternalException('Failed to get pastoralCareSeq for pastoral: ' . $pastoral->getId());
                    }

                    try {
                        // Avoid changing updated_at in schoolbox pastoral records when we complete a synchronisation
                        // into the third party.  If $pastoral->save() was used here, the last time the record was changed
                        // by an author in schoolbox would be overwritten with the time that a synchronisation occurred.
                        $this->pastoralMapper->setExternalIdWithoutChangingUpdatedAt($pastoralCareSeq, $pastoral->getId());

                        // But still need to set the external ID in the object,
                        // for later processes (e.g. pastoral action sync).
                        $pastoral->setExternalId($pastoralCareSeq);
                    } catch (UnexpectedValueException) {
                        if (!$inOuterTransaction) {
                            $this->connector->rollBack();
                        }
                        return false;
                    }
                }

                // Export the permissions/audience of the pastoral record to Synergetic

                $this->updatePastoralCareContacts($pastoral);
                if ($this->config->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
                    $this->updateActions($pastoral);
                }

                if (!$inOuterTransaction) {
                    $this->connector->commit();
                }
                return true;
            }
        } catch (ExternalException $e) {
            if (!$inOuterTransaction) {
                $this->connector->rollBack();
            }
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

        $this->connector->executeQuery(
            "
                DELETE FROM     PastoralCareContacts
                WHERE           PastoralCareSeq = :externalId
            ",
            ['externalId' => $pastoral->getExternalId()]
        );

        // Retrieve all access records stored in Schoolbox
        $access = $this->pastoralAccessMapper->findByColumn('pastoral_id', $pastoral->getId(), ['cache' => false]);

        $order = 1;

        /** @var PastoralAccess $accessRecord */
        foreach ($access as $accessRecord) {
            $user = $accessRecord->getUser();
            if ($user instanceof User) {
                if ($this->isExternalIdValid($user->getExternalId()) && $this->exists($pastoral)) {
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
                        'order' => $order,
                        'email' => $user->getEmail() ?: '',
                        'userId' => $user->getExternalId(),
                    ]);

                    $order++;
                }
            }
        }

        return true;
    }

    /**
     * Inserts `$pastoral`'s actions into Synergetic.
     *
     * A pastoral record action may only be inserted into Synergetic if _all_
     * of the following conditions are met:
     * * the action's code (`lu_pastoral_action.external_code`)
     *   matches a Synergetic external code (`luPastoralCareActionType.Code`)
     * * the record's type's code (`lu_pastoral_type.external_code`)
     *   matches a Synergetic external code (`luPastoralCareType.Code`)
     * * the record has a subtype
     * * the record's subtype's code (`lu_pastoral_subtype.external_code`)
     *   matches a Synergetic external code (`luPastoralCareCategory.Code`)
     * * the action is available for the record's type and subtype
     *   (`luPastoralCareAction` has a row with the `PastoralCareTypeCode`,
     *   `PastoralCareCategoryCode` and `PastoralCareActionTypeCode` matching
     *   the record's type's code, record's subtype's code and action's code)
     *
     * Insertion of actions have the limitation of only being able to be added:
     * they may not be removed, nor may they be marked as done.
     * (Synergetic appears to offer no ability to mark actions as done, anyway.)
     *
     * @param Pastoral $pastoral
     * @throws MapperException
     */
    private function updateActions(Pastoral $pastoral)
    {
        // Find PastoralCareTypeCode from associated actions and store pastoral_record_action.id => PastoralCareActionTypeCode
        $recordActionTypeCodes = [];
        foreach ($pastoral->getRecordActions() as $recordAction) {
            if ($recordAction->getAction()->getExternalCode()) {
                $recordActionTypeCodes[$recordAction->getId()] = $recordAction->getAction()->getExternalCode();
            }
        }

        // If no PastoralCareTypeCode found, do nothing
        if (empty($recordActionTypeCodes)) {
            return;
        }


        // Find luPastoralCareActionSeq (look up table) of combination of
        // type (PastoralCareTypeCode), subtype (PastoralCareCategoryCode) and action(s)
        // of this record, which is not yet associated in Synergetic with this record
        $actionCodeBindings = PredicateBuilder::getArrayBindings($recordActionTypeCodes, 'actionCode');

        $query  = "
            SELECT actions.PastoralCareActionsSeq,
                   lookup.luPastoralCareActionSeq,
                   PastoralCareActionTypeCode
            FROM luPastoralCareAction lookup
            LEFT JOIN PastoralCareActions actions
              ON actions.luPastoralCareActionSeq = lookup.luPastoralCareActionSeq
              AND actions.PastoralCareSeq = :PastoralCareSeq
            WHERE lookup.PastoralCareTypeCode = :PastoralCareTypeCode
              AND lookup.PastoralCareCategoryCode = :PastoralCareCategoryCode
              AND lookup.PastoralCareActionTypeCode IN ({$actionCodeBindings['placeholders']})
              AND actions.PastoralCareSeq IS NULL
        ";
        $params = [
            'PastoralCareTypeCode'     => $pastoral->getType()->getExternalCode(),
            'PastoralCareCategoryCode' => $pastoral->getSubType()?->getExternalCode(),
            'PastoralCareSeq'          => $pastoral->getExternalId(),
        ];
        $params = array_merge($params, $actionCodeBindings['bindings']);

        $luPastoralCareActions = $this->connector->executeQuery($query, $params)->fetchAll();
        // No predefined combination of type (PastoralCareTypeCode), subtype (PastoralCareCategoryCode) and action(s)
        // found in the look up table luPastoralCareAction
        if (empty($luPastoralCareActions)) {
            return;
        }

        // Insert new actionSeqs (not removing, as we don't want to remove actions created via Synergetic
        $newSeqs     = array_column($luPastoralCareActions, 'luPastoralCareActionSeq');
        $insertQuery = "
            INSERT INTO PastoralCareActions (PastoralCareSeq, luPastoralCareActionSeq, IndexOrder)
            VALUES (
                :PastoralCareSeq,
                :luPastoralCareActionSeq,
                COALESCE((SELECT MAX(IndexOrder) + 1 FROM PastoralCareActions WHERE PastoralCareSeq = :PastoralCareSeq2), 1)
            )
        ";

        // Switch on ANSI_WARNINGS for this place only
        $isAnsiWarningsOn = (bool)$this->connector->executeQuery('SELECT (@@OPTIONS & 8)')->fetchColumn();
        if (!$isAnsiWarningsOn) {
            $this->connector->executeDirectly('SET ANSI_WARNINGS ON');
        }

        foreach ($newSeqs as $newSeq) {
            $insertParams = [
                'PastoralCareSeq'         => $pastoral->getExternalId(),
                'PastoralCareSeq2'        => $pastoral->getExternalId(),
                'luPastoralCareActionSeq' => $newSeq,
            ];
            $this->connector->executeQuery($insertQuery, $insertParams);
        }

        // And switch them off again
        if (!$isAnsiWarningsOn) {
            $this->connector->executeDirectly('SET ANSI_WARNINGS OFF');
        }

        // Get PastoralCareActionSeq and update SB pastoral record action external_id
        $newSeqBindings = PredicateBuilder::getArrayBindings($newSeqs, 'newSeqs');
        $codeToSeqMap   = array_combine(array_column($luPastoralCareActions, 'PastoralCareActionTypeCode'), $newSeqs);

        $selectQuery        = "
            SELECT PastoralCareActionsSeq, luPastoralCareActionSeq
              FROM PastoralCareActions
              WHERE PastoralCareSeq = :PastoralCareSeq
                AND luPastoralCareActionSeq IN ({$newSeqBindings['placeholders']})
        ";
        $selectParams       = array_merge(['PastoralCareSeq' => $pastoral->getExternalId()], $newSeqBindings['bindings']);
        $insertedActionSeqs = $this->connector->executeQuery($selectQuery, $selectParams)->fetchAll();
        $insertedActionSeqs = array_combine(
            array_column($insertedActionSeqs, 'luPastoralCareActionSeq'),
            array_column($insertedActionSeqs, 'PastoralCareActionsSeq')
        );

        /** @var RecordActionMapper $recordActionMapper */
        $recordActionMapper = $this->pastoralMapper->getJoinMapper('recordActions');
        foreach ($pastoral->getRecordActions() as $recordAction) {
            if (key_exists($recordAction->getAction()->getExternalCode(), $codeToSeqMap)) {
                $luPastoralCareActionSeq = $codeToSeqMap[$recordAction->getAction()->getExternalCode()];
                $recordAction->setExternalId($insertedActionSeqs[$luPastoralCareActionSeq]);
                $recordActionMapper->save($recordAction);
            }
        }
    }

    /**
     * @inheritdoc
     */
    public function exists(Pastoral $pastoral): bool
    {
        $externalId = $pastoral->getExternalId();
        if (is_null($externalId)) {
            return false;
        }

        $query = "SELECT COUNT(*) FROM tPastoralCare WHERE PastoralCareSeq = :externalId";
        $result = $this->connector->executeQuery($query, ['externalId' => $externalId]);
        return (bool)$result->fetchColumn();
    }

    /**
     * Checks whether external code of types are valid
     *
     * @param Pastoral $pastoral
     * @return void
     * @throws ExternalException if any of these things are not valid:
     *                           * record's student's external ID
     *                           * record's owner's external ID
     *                           * record's type or subtype
     */
    private function assertExternalCodeValid(Pastoral $pastoral)
    {
        if (!$this->isExternalIdValid($pastoral->getStudent()->getExternalId())) {
            throw new ExternalException(
                sprintf(
                    'Pastoral record %d student external ID "%s" is invalid',
                    $pastoral->getId(),
                    $pastoral->getStudent()->getExternalId(),
                )
            );
        }

        if (!$this->isExternalIdValid($pastoral->getOwner()->getExternalId())) {
            throw new ExternalException(
                sprintf(
                    'Pastoral record %d owner external ID "%s" is invalid',
                    $pastoral->getId(),
                    $pastoral->getOwner()->getExternalId(),
                )
            );
        }

        if (
            $pastoral->getType()
            && !empty($pastoral->getType()->getExternalCode())
            && !array_key_exists($pastoral->getType()->getExternalCode(), $this->getTypeList())
        ) {
            throw new ExternalException(
                sprintf(
                    'Pastoral record %d type code "%s" is invalid',
                    $pastoral->getId(),
                    $pastoral->getType()->getExternalCode(),
                )
            );
        }

        if (
            $pastoral->getSubType()
            && !empty($pastoral->getSubType()->getExternalCode())
            && !array_key_exists($pastoral->getSubType()->getExternalCode(), $this->getSubTypeList())
        ) {
            throw new ExternalException(
                sprintf(
                    'Pastoral record %d sub-type code "%s" is invalid',
                    $pastoral->getId(),
                    $pastoral->getSubType()->getExternalCode(),
                )
            );
        }
    }

    // Comment synchronization

    /**
     * @inheritdoc
     */
    public function createComment(PastoralComment $comment): bool
    {
        try {
            /** @var Pastoral $pastoral */
            $pastoral = $this->pastoralMapper->findById($comment->getPastoral()->getId());

            /** @var User $owner */
            $owner = $this->userMapper->findById($pastoral->getOwner()->getId());
        } catch (MapperException $e) {
            // Can't find either the pastoral record or the authors Synergetic ID
            return false;
        }

        // Can only do this if the pastoral record exists in synergetic, and has an external ID
        if (
            !$this->exists($pastoral)
            || !$this->isExternalIdValid($pastoral->getExternalId())
            || !$this->isExternalIdValid($owner->getExternalId())
        ) {
            return false;
        }

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

        try {
            $this->connector->beginTransaction();
            $this->connector->executeQuery($query, [
                'externalId' => $pastoral->getExternalId(),
                'externalId2' => $pastoral->getExternalId(),
                'comment' => $comment->getBody(),
                'createdDate' => $comment->getCreatedAt()->getDatabase(),
                'createdOwnerId' => $comment->getOwner()->getExternalId(),
                'modifiedDate' => $comment->getCreatedAt()->getDatabase(),
                'modifiedOwnerId' => $comment->getOwner()->getExternalId(),
            ]);

            // gets PastoralCareCommentsSeq for the inserted record and set it to SB pastoral_comment table
            // lastInsertId() does not work because of DB trigger attached to this table
            $pastoralCareCommentsSeq = $this->connector->executeQuery(
                "SELECT PastoralCareCommentsSeq
                 FROM PastoralCareComments
                 WHERE
                    PastoralCareSeq = :externalId AND
                    CreatedDate = :createdDate AND
                    CreatedById = :createdOwnerId
                ORDER BY PastoralCareCommentsSeq DESC",
                [
                    'externalId'     => $pastoral->getExternalId(),
                    'createdDate'    => $comment->getCreatedAt()->getDatabase(),
                    'createdOwnerId' => $comment->getOwner()->getExternalId(),
                ]
            )->fetchColumn();
            $this->connector->commit();

            $comment->setExternalCode($pastoralCareCommentsSeq);

            // Stop pastoral notifier firing on this update, otherwise they receive both a create/update
            // notification every time someone creates a pastoral record
            $propagator = $this->pastoralCommentMapper->getEventPropagator();
            $this->pastoralCommentMapper->setEventPropagator(new MockPropagator());
            $this->pastoralCommentMapper->save($comment);

            // Restore the proper propagator
            $this->pastoralCommentMapper->setEventPropagator($propagator);
            return true;
        } catch (DatabaseException $e) {
            $this->connector->rollBack();
            // most likely that corresponding pastoral record is deleted in Synergetic
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteComment(PastoralComment $comment): bool
    {
        if ($this->isExternalIdValid($comment->getExternalCode())) {
            $query = $this->connector->executeQuery(
                "DELETE FROM PastoralCareComments WHERE PastoralCareCommentsSeq = :external_code",
                ['external_code' => $comment->getExternalCode()]
            );
            return $query->rowCount() > 0;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function deletePastoral(Pastoral $pastoral): bool
    {
        // Option to delete pastoral records from Synergetic has to be explicitly allowed in the schools configuration
        if ($this->config->get(ConfigMapper::NAME_PASTORAL_EXTERNAL_DELETE)) {
            // Make sure the pastoral record has a valid external ID
            if ($this->isExternalIdValid($pastoral->getExternalId())) {
                // These must be done in order due to FK constraints in Synergetic database

                // Delete actions
                if ($this->config->get(ConfigMapper::NAME_PASTORAL_ACTION_ENABLED)) {
                    $this->connector->executeQuery(
                        "DELETE FROM PastoralCareActions WHERE PastoralCareSeq = :externalId",
                        ['externalId' => $pastoral->getExternalId()]
                    );
                }

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
     * @param UploadQueue $upload
     * @return bool
     * @throws ExternalException
     * @throws InvalidArgumentException
     * @throws MapperException
     * @throws MapperException\Deleted
     * @throws PDOException
     */
    public function uploadPastoralDocument(UploadQueue $upload): bool
    {
        // Save the file into to the database
        $externalId = $upload->getExternalId();
        $studentExternalId = $upload->getStudentExternalId();

        /* If there wasn't a meaningful $studentExternalId, exit early
         * There's nothing we can do
         */
        if ('' === (string)$studentExternalId) {
            throw new \InvalidArgumentException(sprintf(
                "Student external id not provided for upload external id %s",
                $upload->getExternalId()
            ));
        }

        // Temporarily increase the memory limit so we can get the file across to Synergetic
        ini_set('memory_limit', '2048M');
//        set_time_limit(0);

        // This whole thing is a bit of an ass, unfortunately Synergetic stores its DocMan files in
        // the actual database- which means we need to shove the file into a SQL query as a hex string
        // which is totally inefficient and very slow :(

        // Hydrating pastoral data to get Type.
        $pastoral = $this->pastoralMapper->findById($upload->getPastoral()->getId());
        $storage = $upload->getStorage();

        $pastoralAttachment = $this->pastoralAttachmentMapper->findById($upload->getPastoralAttachmentId());
        $documentClassification = (
            $pastoralAttachment->getPrivate()
            ? $pastoral->getType()->getDocumentClassificationConfidential() // Note this object will not be hydrated.
            : $pastoral->getType()->getDocumentClassification() // Note this object will not be hydrated.
        );

        if ($documentClassification instanceof PastoralDocumentClassification) {
            try {
                // Replace unhydrated object with hydrated object.
                $documentClassification = $this->pastoralDocumentClassificationMapper->findById(
                    $documentClassification->getId()
                );
            } catch (BaseMapper\Exception $e) {
                // noop: leave document classification as null
            }
        }

        $date = date("Y-m-d H:i:s");
        $sourceDate = $storage->getCreatedAt()->getDatabase();
        $extension = strtoupper($storage->getExtension());
        $pastoralExternalId = $pastoral->getExternalId();

        // Documents get uploaded with a classification code that is based on the pastoral records type
        if ($documentClassification instanceof PastoralDocumentClassification) {
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
                CONVERT(varbinary(max), :document, 2),
                :createdDate,
                :modifiedDate,
                :sourceDate,
                :documentType
            );
        ";

        $filesystemProxy = StorageMapper::getInstance()->getStorageFilesystemProxy();
        $query           = $this->connector->prepareQuery(
            $sql,
            [
                'filename' => ['value' => $upload->getRealname()],
                'classificationCode' => ['value' => $classificationCode],
                'createdById' => ['value' => $externalId],
                'modifiedById' => ['value' => $externalId],
                'document' => ['value' => bin2hex($filesystemProxy->read($storage))],
                'createdDate' => ['value' => $date],
                'modifiedDate' => ['value' => $date],
                'sourceDate' => ['value' => $sourceDate],
                'documentType' => ['value' => $extension],
            ]
        );

        try {
            $this->connector->beginTransaction();
            if (!$query->execute()) {
                $this->connector->rollBack();
                throw new ExternalException('Error uploading pastoral document to Synergetic database.');
            }

            // gets tDocumentsSeq for the inserted record
            // lastInsertId() does not work because of DB trigger attached to this table
            $documentReferencesSeq = $this->connector->executeQuery(
                "SELECT tDocumentsSeq FROM media.tDocuments WHERE
                Description = :filename AND
                ClassificationCode = :classificationCode AND
                DocumentCreatedByID = :createdById AND
                DocumentCreatedDate = :createdDate AND
                SourceDate = :sourceDate AND
                DocumentType = :documentType
                ORDER BY tDocumentsSeq DESC",
                [
                    'filename'           => ['value' => $upload->getRealname()],
                    'classificationCode' => ['value' => $classificationCode],
                    'createdById'        => ['value' => $externalId],
                    'createdDate'        => ['value' => $date],
                    'sourceDate'         => ['value' => $sourceDate],
                    'documentType'       => ['value' => $extension],
                ]
            )->fetchColumn();

            if (empty($documentReferencesSeq)) {
                $this->connector->rollBack();
                throw new ExternalException('Error linking pastoral document to record in Synergetic database.');
            }

            // Add a document reference to the student so it shows up on their normal community record
            $query = $this->connector->prepareQuery(
                "
                INSERT INTO {$this->schemaMedia}.DocumentReferences (ID, DocumentReferencesSeq)
                VALUES (:id, :documentReferencesSeq);
            ",
                [
                    'id'                    => $studentExternalId,
                    'documentReferencesSeq' => $documentReferencesSeq,
                ]
            );

            if (!$query->execute()) {
                $this->connector->rollBack();
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
                    :relatedSeqModuleCode,
                    COALESCE(MAX(SortOrder) + 1, 0),
                    :pastoralExternalId
            FROM    {$this->schemaMedia}.tDocumentReferences
            WHERE   tDocumentsSeq = :documentReferencesSeq2
        ";

            $query = $this->connector->prepareQuery(
                $sql,
                [
                    'pastoralExternalId'     => $pastoralExternalId,
                    'documentReferencesSeq'  => $documentReferencesSeq,
                    'documentReferencesSeq2' => $documentReferencesSeq,
                    'relatedSeqModuleCode'   => self::MODULE_CODE
                ]
            );

            if (!$query->execute()) {
                $this->connector->rollBack();
                throw new ExternalException('Error linking pastoral document to record in Synergetic database.');
            }
            $this->connector->commit();
            $pastoralAttachment = $this->pastoralAttachmentMapper->findById($upload->getPastoralAttachmentId());
            $pastoralAttachment->setExternalId($documentReferencesSeq);
            $this->pastoralAttachmentMapper->save($pastoralAttachment);
        } catch (DatabaseException $e) {
            $this->connector->rollBack();
            throw new ExternalException('Error linking pastoral document to record in Synergetic database:' . $e->getTraceAsString());
        }
        return true;
    }

    /**
     * Deletes documents from tDocuments which are no longer attached to the pastoral record
     * @param PastoralAttachment $pastoralAttachment
     * @return bool
     * @throws ExternalException
     */
    public function deletePastoralDocument(PastoralAttachment $pastoralAttachment): bool
    {
        if (!$pastoralAttachment->getExternalId() || !$pastoralAttachment->getPastoral()->getExternalId()) {
            return true;
        }

        // Count references for the file
        $parameters = ['tDocumentSeq' => $pastoralAttachment->getExternalId()];
        // tDocumentReferences table
        $sql         = "SELECT COUNT(*) FROM {$this->schemaMedia}.tDocumentReferences
                        WHERE tDocumentsSeq = :tDocumentSeq";
        $docRefCount = (int)$this->connector->executeQuery($sql, $parameters)->fetchColumn();
        // DocumentReferences table
        $sql             = "SELECT COUNT(*) FROM {$this->schemaMedia}.DocumentReferences
                            WHERE DocumentReferencesSeq = :tDocumentSeq";
        $studentRefCount = (int)$this->connector->executeQuery($sql, $parameters)->fetchColumn();

        $this->connector->beginTransaction();

        try {
            // delete DocumentReferences (student reference), only if there's only one reference
            if ($docRefCount === 1 && $studentRefCount === 1) {
                $this->connector->executeQuery(
                    "DELETE FROM {$this->schemaMedia}.DocumentReferences
                            WHERE DocumentReferencesSeq = :tDocumentSeq
                            AND ID = :studentId",
                    [
                        'tDocumentSeq' => $pastoralAttachment->getExternalId(),
                        'studentId'           => $pastoralAttachment->getPastoral()->getStudent()->getExternalId(),
                    ]
                );
            }

            // delete tDocumentReferences (pastoral reference)
            $this->connector->executeQuery(
                "DELETE FROM {$this->schemaMedia}.tDocumentReferences
                               WHERE tDocumentsSeq = :tDocumentSeq
                                 AND RelatedSeqModuleCode = :RelatedSeqModuleCode
                                 AND RelatedSeq = :RelatedSeq",
                [
                    'tDocumentSeq'         => $pastoralAttachment->getExternalId(),
                    'RelatedSeqModuleCode' => self::MODULE_CODE,
                    'RelatedSeq'           => $pastoralAttachment->getPastoral()->getExternalId(),
                ]
            );

            // Delete tDocuments, only if there's only one reference to this document
            if ($docRefCount === 1 && $studentRefCount === 1) {
                $this->connector->executeQuery(
                    "DELETE FROM {$this->schemaMedia}.tDocuments WHERE tDocumentsSeq = :tDocumentSeq",
                    ['tDocumentSeq' => $pastoralAttachment->getExternalId()]
                );
            }
        } catch (DatabaseException $e) {
            $this->connector->rollBack();
            throw new ExternalException("Error delete pastoral attachment: {$pastoralAttachment->getExternalId()}" . $e->getTraceAsString());
        }
        $this->connector->commit();
        return true;
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

            /** @var PastoralSeverity $severity */
            $severity = $this->pastoralSeverityMapper->findAll(['limit' => 1])->first();
            if (!$severity) {
                // Pastoral severity was not found, this should never happen as it is a mandatory field in Schoolbox.
                // We will just create a default one called "Low" in case it every does happen

                /** @var PastoralSeverity $severity */
                $severity = $this->pastoralSeverityMapper->createModel();
                $severity->setSequence(1);
                $severity->setName("Low");
                $this->pastoralSeverityMapper->save($severity);
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
     * @param bool     $prependTitle
     * @return array
     * @throws ExternalException
     * @throws MapperException
     */
    private function getQueryParameters($pastoral, bool $prependTitle = false)
    {
        // Get the students Synergetic ID

        $studentExternalId = $this->userMapper->findByIdWithFallback(
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
            // validate code
            $pastoralTypeCode =
                array_key_exists($pastoral->getType()->getExternalCode(), $this->getTypeList())
                    ? $pastoral->getType()->getExternalCode()
                    : '';
        }

        // Retrieve pastoral subtype, if there is no type as well we need to blank this out

        if (empty($pastoralTypeCode) || empty($pastoral->getSubType())) {
            $pastoralSubTypeCode = '';
        } else {
            $pastoralSubTypeCode =
                array_key_exists($pastoral->getSubType()->getExternalCode(), $this->getSubTypeList())
                    ? $pastoral->getSubType()->getExternalCode()
                    : '';
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
                    $documentClassificationCode = $documentClassification->getExternalCode() ?? '';
                } catch (MapperException $e) {
                    $documentClassificationCode = '';
                }
            }
        }

        $this->htmlConverter->setHtml($pastoral->getBodyPublic());

        /* Some schools don't like prepending the heading since it can contain metadata
         * that shouldn't be revealed to students. Some schools are okay with that, but
         * some schools aren't; hence, a per-school configuration as per SA-16383
         */
        if ($prependTitle) {
            $bodyPublicText = $pastoral->getHeading() . "\r\n" . $this->htmlConverter->getText();
        } else {
            $bodyPublicText = $this->htmlConverter->getText();
        }

        $this->htmlConverter->setHtml($pastoral->getBodyPrivate());
        $bodyPrivateText = $this->htmlConverter->getText();

        // Return an array containing the parameters for the insert/update query

        $params = [
            'studentExternalId' => $studentExternalId,
            'itemDate' => $pastoral->getRecordDate()->getDatabase(),
            'publicBody' => mb_substr($bodyPublicText, 0, 8000, self::CHARACTER_ENCODING),
            'privateBody' => mb_substr($bodyPrivateText, 0, 8000, self::CHARACTER_ENCODING),
            'pastoralTypeCode' => mb_substr($pastoralTypeCode, 0, 15, self::CHARACTER_ENCODING),
            'pastoralSubTypeCode' => mb_substr($pastoralSubTypeCode, 0, 15, self::CHARACTER_ENCODING),
            'classificationCode' => mb_substr($documentClassificationCode, 0, 15, self::CHARACTER_ENCODING),
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
                $this->userCache[$externalId] = $this->userMapper->findByExternalId($externalId);
            } catch (MapperException $e) {
                $this->userCache[$externalId] = null;
            }
        }

        return $this->userCache[$externalId];
    }

    /**
     * Finds/creates a pastoral care type that matches one from Synergetics database
     *
     * @param Pastoral $pastoral
     * @param array    $record Pastoral care record
     * @param User     $owner
     * @return void
     * @throws MapperException
     */
    protected function setPastoralType(Pastoral $pastoral, array $record, User $owner): void
    {
        if (empty($this->typeCache)) {
            $types              = $this->pastoralTypeMapper->findAll();
            $this->typeSequence = max($types->getPropertyValues('sequence'));
            foreach ($types as $type) {
                $this->typeCache[$type->getName()] = $type;
                $subtypeMaxSequence                = $type->getSubTypes()->count() === 0 ?: max($type->getSubTypes()->getPropertyValues('sequence'));
                $this->subtypeSequence             = $this->subtypeSequence > $subtypeMaxSequence ?: $subtypeMaxSequence;
                foreach ($type->getSubTypes() as $subtype) {
                    $this->subtypeCache[$type->getName()][$subtype->getName()] = $subtype;
                }
            }
        }

        if (isset($this->typeCache[$record['TypeDescription']])) {
            $type = $this->typeCache[$record['TypeDescription']];
            if (!$type->getExternalCode()) {
                $type->setExternalCode($record['TypeCode']);
                $this->pastoralTypeMapper->save($type);
            }
        } else {
            // Didn't find a cached record. Try to retrieve an existing pastoral type from Schoolbox
            // The records pastoral type doesn't exist in Schoolbox, create one that matches
            $type = $this->pastoralTypeMapper->createModel();
            $type->setName($record['TypeDescription']);
            $type->setExternalCode($record['TypeCode']);
            $type->setSequence(++$this->typeSequence);
            $type->setUpdatedBy($owner->getId());
            $this->pastoralTypeMapper->save($type);

            $this->typeCache[$record['TypeDescription']] = $type;
        }

        $subtype = null;
        if (isset($this->subtypeCache[$type->getName()][$record['CategoryDescription']])) {
            $subtype = $this->subtypeCache[$type->getName()][$record['CategoryDescription']];
            if (!$subtype->getExternalCode()) {
                $subtype->setExternalCode($record['CategoryCode']);
                $this->pastoralSubTypeMapper->save($subtype);
            }
        } elseif (!empty($record['CategoryDescription'])) {
            // Didn't find a cached record. Try to retrieve an existing pastoral type from Schoolbox
            // The records pastoral category doesn't exist in Schoolbox, create one that matches

            /** @var PastoralSubType $subtype */
            $subtype = $this->pastoralSubTypeMapper->createModel();
            $subtype->setName($record["CategoryDescription"]);
            $subtype->setExternalCode($record['CategoryCode']);
            $subtype->setPastoralType($type);
            $subtype->setSequence(++$this->subtypeSequence);
            $subtype->setUpdatedBy($owner->getId());
            $this->pastoralSubTypeMapper->save($subtype);

            $this->subtypeCache[$type->getName()][$record['CategoryDescription']] = $subtype;
        }

        $pastoral->setType($type);
        $pastoral->setSubType($subtype);
    }

    /**
     * Gets available pastoral types
     * @return array
     */
    public function getTypeList(): array
    {
        $query              = "
                    SELECT Code
                    FROM dbo.luPastoralCareType
                    WHERE ActiveFlag = 1
                      AND Code != ''";
        $pastoralTypes      = $this->getConnector()->executeQuery($query)->fetchAll();
        $pastoralTypeLookup = [];
        foreach ($pastoralTypes as $pastoralType) {
            $pastoralTypeLookup[$pastoralType['Code']] = [
                'key'   => $pastoralType['Code'],
                'value' => $pastoralType['Code'],
            ];
        }
        return $pastoralTypeLookup;
    }

    /**
     * Gets available pastoral subtypes
     * @return array
     */
    public function getSubtypeList(): array
    {
        $query              = "
                    SELECT Code
                    FROM dbo.luPastoralCareCategory
                    WHERE ActiveFlag = 1
                      AND Code != ''";
        $pastoralTypes      = $this->getConnector()->executeQuery($query)->fetchAll();
        $pastoralTypeLookup = [];
        foreach ($pastoralTypes as $pastoralType) {
            $pastoralTypeLookup[$pastoralType['Code']] = [
                'key'   => $pastoralType['Code'],
                'value' => $pastoralType['Code'],
            ];
        }
        return $pastoralTypeLookup;
    }

    /**
     * Gets available pastoral document classifications
     * @return array
     */
    public function getDocumentClassificationList(): array
    {
        $query            = "
                    SELECT Code
                    FROM dbo.luDocumentClassification
                    WHERE ActiveFlag = 1
                      AND Code != ''
        ";
        $exDocClass       = $this->getConnector()->executeQuery($query)->fetchAll();
        $exDocClassLookup = [];
        foreach ($exDocClass as $docClass) {
            $exDocClassLookup[$docClass['Code']] = [
                'key'   => $docClass['Code'],
                'value' => $docClass['Code'],
            ];
        }
        return $exDocClassLookup;
    }

    /**
     * Gets available pastoral actions
     * @return array
     */
    public function getActionList(): array
    {
        $query            = "
                    SELECT Code
                    FROM dbo.luPastoralCareActionType
                    WHERE ActiveFlag = 1
                      AND Code != ''
        ";
        $exDocClass       = $this->getConnector()->executeQuery($query)->fetchAll();
        $exDocClassLookup = [];
        foreach ($exDocClass as $docClass) {
            $exDocClassLookup[$docClass['Code']] = [
                'key'   => $docClass['Code'],
                'value' => $docClass['Code'],
            ];
        }
        return $exDocClassLookup;
    }
}
