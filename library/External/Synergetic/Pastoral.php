<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\External\Exception as ExternalException;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Notif\SilentNotif;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Pastoral\Model as Pastoral;
use Schoolbox\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\DocumentClassification\Mapper as PastoralDocumentClassificationMapper;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccess;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Category\Mapper as PastoralCategoryMapper;
use Schoolbox\Pastoral\Category\Model as PastoralCategory;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Comment\Model as PastoralComment;
use Schoolbox\Pastoral\Log\Mapper as PastoralLogMapper;
use Schoolbox\Pastoral\Log\Model as PastoralLog;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Severity\Model as PastoralSeverity;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Type\Model as PastoralType;
use Schoolbox\Pastoral\DocumentClassification\Mapper as DocumentClassificationMapper;
use Schoolbox\Pastoral\DocumentClassification\Model as DocumentClassification;
use Schoolbox\Pastoral\UploadQueue\Model as UploadQueue;

class External_Synergetic_Pastoral implements External_PastoralInterface
{
    use External_MssqlConnector;
    use External_Synergetic_ValidateExternalIdTrait;

    // This is a magic Synergetic string that gets set as the contents of a pastoral record when there are no private contents

    /** @var string $blankPrivateRecordContents */
    private $blankPrivateRecordContents = "No Private Details Entered";

    // We will use these array to cache any users and pastoral care types/categories that we come across

    /** @var array $userCache */
    private $userCache = [];

    /** @var array $typeCache */
    private $typeCache = [];

    /** @var array $categoryCache */
    private $categoryCache = [];

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

    /** @var PastoralCategoryMapper $pastoralCategoryMapper */
    protected $pastoralCategoryMapper;

    /** @var PastoralAccessMapper $pastoralAccessMapper */
    protected $pastoralAccessMapper;

    /** @var PastoralAttachmentMapper $pastoralAttachmentMapper */
    protected $pastoralAttachmentMapper;

    /** @var PastoralSeverityMapper $pastoralSeverityMapper */
    protected $pastoralSeverityMapper;

    /** @var string $schema */
    private $schema;

    /**
     * Constructor
     */
    public function __construct()
    {
        // This connects to the Synergetic database and stores an IConnection in `$this->connector` variable

        $this->setupDatabase();

        $externalPing = new External_Ping();

        // The upload location varies depending on the version of Synergetic

        if ($externalPing->getVersion() == "68") {
            $this->schema = "media";
        } else {
            $this->schema = "SynergyOneMedia.dbo";
        }

        // Grab mapper instances

        $this->setUserMapper(UserMapper::getInstance());
        $this->setPastoralMapper(PastoralMapper::getInstance());
        $this->setPastoralAccessMapper(PastoralAccessMapper::getInstance());
        $this->setPastoralDocumentClassificationMapper(PastoralDocumentClassificationMapper::getInstance());
        $this->setPastoralCommentMapper(PastoralCommentMapper::getInstance());
        $this->setPastoralLogMapper(PastoralLogMapper::getInstance());
        $this->setPastoralTypeMapper(PastoralTypeMapper::getInstance());
        $this->setPastoralCategoryMapper(PastoralCategoryMapper::getInstance());
        $this->setPastoralAccessMapper(PastoralAccessMapper::getInstance());
        $this->setPastoralSeverityMapper(PastoralSeverityMapper::getInstance());
    }

    /**
     * @inheritdoc
     */
    public function getPastoral(Pastoral $pastoral)
    {
        $query =
            "
            SELECT  PastoralCareSeq,
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
            FROM    tPastoralCare
            WHERE   PastoralCareSeq = :externalId
        ";

        return $this->connector->executeQuery($query)->fetch();
    }

    /**
     * @inheritdoc
     */
    public function importPastoral($user, $defaultOwner, $importParentAccess)
    {
        // Given the size of the result set here (in some schools 100,000+ records) we need to use an iterator to process the results, otherwise
        // PHP is going to crap out with memory problems in some schools. Unfortunately we also need to do queries inside the iterator- this is not
        // possible without 'MultipleActiveResultSets' enabled in the database connector- which is disabled by default due to performance issues.
        //
        // In this case we don't care about the performance as its a background process- so we will reconnect to SQL with MultipleActiveResultSets
        // enabled thus enabling us to iterate inside an iterator.

        $this->connector->enableMultipleActiveResultSets();

        $owner = null;
        $student = null;

        // Give the import 30 minutes to complete and bump up the memory limit just to make sure we don't bomb out during the import

        ini_set('memory_limit', '2048M');
        set_time_limit(60 * 30);

        // Silence pastoral notification while importing so we don't spam every person in Schoolbox

        $silence = new SilentNotif();
        $this->getPastoralMapper()->setNotifier($silence);
        $this->getPastoralCommentMapper()->setNotifier($silence);

        // For some bizarre reason the Mapper doesn't have access to the session data at this point, so we've got to give it to it?

        $this->getPastoralLogMapper()->setSessionData($_SESSION);

        // Get the last import ID from the database, all log entries in the database will be associated with this number

        $importRecord = $this->getPastoralLogMapper()->findAll()->sortBy("importId", "desc")->first();

        if ($importRecord instanceof PastoralLog) {
            $importId = $importRecord->getImportId() + 1;
        } else {
            $importId = 1;
        }

        // Log start of import

        $log = $this->pastoralLogMapper->createModel();
        $log->setImportId($importId);
        $log->setUserId($user->getId());
        $log->setPastoralId(null);
        $log->setHeading("Starting pastoral import");
        $log->setStudentName(null);
        $log->setCreatorName($user->getFullName());
        $log->setResult("Parent contact importing " . ($importParentAccess ? 'enabled' : 'disabled'));
        $this->getPastoralLogMapper()->save($log);

        // Only load a severity if it is enabled by policy

        if ($this->config->get('pastoral_severity')) {
            // Default all imported items to the lowest priority as there is no concept of this in Synergetic

            try {
                $severity = $this->getPastoralSeverityMapper()->findOneByColumn("order", 0);
            } catch (Exception $e) {
                // Pastoral severity was not found, this should never happen as it is a mandatory field in Schoolbox. We will just create a default one called
                // "Low" in case it every does happen

                $severity = $this->getPastoralSeverityMapper()->createModel();
                $severity->setOrder(0);
                $severity->setName("Low");
                $this->getPastoralSeverityMapper()->save($severity);
            }
        } else {
            $severity = null;
        }

        // Retrieve an iterator for all current students pastoral care records from Synergetic

        // Sorting by student name as it makes searching the import log much easier.

        $query =
            "
            SELECT        dbo.tPastoralCare.PastoralCareSeq, 
                          dbo.tPastoralCare.ID, 
                          dbo.tPastoralCare.ClassCode, 
                          dbo.tPastoralCare.ItemDate, 
                          dbo.tPastoralCare.MethodCode,
                          dbo.tPastoralCare.Details, 
                          dbo.tPastoralCare.PrivateDetails, 
                          dbo.tPastoralCare.InitiatorCode, 
                          dbo.tPastoralCare.TypeCode, 
                          dbo.tPastoralCare.CategoryCode,
                          dbo.tPastoralCare.ClassificationCode, 
                          dbo.tPastoralCare.IsFollowUpFlag, 
                          dbo.tPastoralCare.FollowUpDate, 
                          dbo.tPastoralCare.ConfidentialFlag,
                          dbo.tPastoralCare.CreatedDate, 
                          dbo.tPastoralCare.CreatedByID, 
                          dbo.tPastoralCare.ModifiedDate, 
                          dbo.tPastoralCare.ModifiedByID,
                          dbo.tPastoralCare.EmailSentDate, 
                          dbo.tPastoralCare.EmailSentByID, 
                          dbo.tPastoralCare.DuplicateFromSeq, 
                          dbo.tPastoralCare.SubjectClassesSeq,
                          dbo.luPastoralCareType.Description AS TypeDescription, 
                          Community_1.Surname AS StudentLastname, 
                          Community_1.Given1 AS StudentFirstname,
                          dbo.Community.Surname AS OwnerLastname, 
                          dbo.Community.Given1 AS OwnerFirstname
            FROM          dbo.tPastoralCare 
            INNER JOIN    dbo.luPastoralCareType ON dbo.tPastoralCare.TypeCode = dbo.luPastoralCareType.Code 
            INNER JOIN    dbo.Community ON dbo.tPastoralCare.CreatedByID = dbo.Community.ID 
            INNER JOIN    dbo.Community AS Community_1 ON dbo.tPastoralCare.ID = Community_1.ID
            INNER JOIN    dbo.vStudents ON dbo.vStudents.ID = dbo.tPastoralCare.ID
            INNER JOIN    dbo.FileSemesters ON 
                              dbo.FileSemesters.FileYear = dbo.vStudents.FileYear AND
                              dbo.FileSemesters.FileSemester = dbo.vStudents.FileSemester AND
                              dbo.FileSemesters.SystemCurrentFlag = 1
            ORDER BY      Community_1.Surname, 
                          Community_1.Given1, 
                          dbo.tPastoralCare.PastoralCareSeq
        ";

        $records = $this->connector->executeQueryIterator($query);

        foreach ($records as $record) {

            $heading = substr(trim($record["Details"]), 0, 50);

            $logRecord = $this->pastoralLogMapper->createModel();

            try {

                // If we can't find the student in Schoolbox- there is not point going any further with this record

                $student = $this->getSchoolboxUser($record["ID"]);
                $owner = $this->getSchoolboxUser($record["CreatedByID"]) ?: $defaultOwner;

                $logRecord->setImportId($importId);
                $logRecord->setUserId($user->getId());
                $logRecord->setHeading($heading);

                if (!$student instanceof User) {

                    // The student of the pastoral care record is not available in Schoolbox, skip this record

                    $logRecord->setResult("SKIPPED: Student does not have a user record in Schoolbox");
                    $this->getPastoralLogMapper()->save($logRecord);

                    continue;
                }

                $logRecord->setCreatorName($owner->getFullName());
                $logRecord->setStudentName($student->getFullName());
                $logRecord->setResult("Importing");
                $this->getPastoralLogMapper()->save($logRecord);

                // If this pastoral care record already exists in Schoolbox, delete it so we don't end
                // up with duplicate records. Could update the record, but its contents may have deviated
                // it was originally imported, and deleting will at least keep that history

                $this->getPastoralMapper()->deleteByColumn("external_id", $record["PastoralCareSeq"]);

                $pastoralRecord = $this->getPastoralMapper()->createModel();
                $pastoralRecord->setExternalId($record["PastoralCareSeq"]);
                $pastoralRecord->setOwner($owner);
                $pastoralRecord->setStudent($student);
                $pastoralRecord->setHeading($heading);
                $pastoralRecord->setBody($record["Details"]);
                $pastoralRecord->setType($this->getPastoralType($record));
                $pastoralRecord->setCategory($this->getPastoralCategory($record));
                $pastoralRecord->setPrivate($record["ConfidentialFlag"]);

                if ($this->config->get('pastoral_severity')) {
                    $pastoralRecord->setSeverity($severity);
                }

                // Only create public record if its not blank

                if (!empty(trim($record["Details"]))) {
                    $this->getPastoralMapper()->save($pastoralRecord);
                } else {
                    // Public record blank, skip it

                    $log = $this->getPastoralLogMapper()->createModel();
                    $log->setImportId($importId);
                    $log->setUserId($user->getId());
                    $log->setPastoralId($pastoralRecord->getId());
                    $log->setCreatorName($owner->getFullName());
                    $log->setStudentName($student->getFullName());
                    $log->setHeading("*** SKIPPING BLANK PUBLIC PASTORAL - PastoralCareSeq #{$record["PastoralCareSeq"]} ***");
                    $log->setResult('OK');
                    $this->getPastoralLogMapper()->save($log);
                }

                // Retrieve anybody that was listed as a contact in Synergetic and give them access to the record

                $audienceIterator = $this->connector->executeQueryIterator(
                    "SELECT ID FROM PastoralCareContacts WHERE PastoralCareSeq = :pastoralCareSeq",
                    ['pastoralCareSeq' => $record["PastoralCareSeq"]]
                );

                // Cache the access records in case we need to create identical ones for the private record, will

                $accessRecords = [];

                foreach ($audienceIterator as $audience) {

                    $audienceUser = $this->getSchoolboxUser($audience["ID"]);

                    if ($audienceUser instanceof User) {
                        // If the school does not want parents given access to pastoral records, then don't add them

                        if (!empty(trim($record["Details"]))) {

                            if ($importParentAccess || $audienceUser->getRole()->isStaff()) {

                                $accessRecord = $this->getPastoralAccessMapper()->createModel();
                                $accessRecord->setPastoral($pastoralRecord);
                                $accessRecord->setUser($audienceUser);
                                $this->getPastoralAccessMapper()->save($accessRecord);

                                $log = $this->getPastoralLogMapper()->createModel();
                                $log->setImportId($importId);
                                $log->setUserId($user->getId());
                                $log->setPastoralId($pastoralRecord->getId());
                                $log->setCreatorName($owner->getFullName());
                                $log->setStudentName($student->getFullName());
                                $log->setHeading("*** GRANTING ACCESS - {$audienceUser->getFullName()} ***");
                                $accessRecords[] = $accessRecord;
                            } else {
                                $log->setHeading("**** SKIPPING ACCESS - {$audienceUser->getFullName()} ***");
                            }

                            $log->setResult('OK');
                            $this->getPastoralLogMapper()->save($log);
                        }
                    }
                }


                // Check if the Synergetic pastoral record had private content that was different to its public content

                if ($record["Details"] != $record["PrivateDetails"] &&                          // Private contents differ from the public contents
                    !empty(trim($record["PrivateDetails"])) &&                                  // Private contents aren't blank
                    $record["PrivateDetails"] != $this->blankPrivateRecordContents              // Private contents aren't the magic blank string
                ) {
                    // We need to create a separate private record. I'll do this by just blanking out the ID on the existing record, changing the
                    // relevant data and re-saving it. Blanking the ID makes the mapper thing its a new record.

                    $pastoralRecord->setId(null);
                    $pastoralRecord->setHeading(substr(trim($record["PrivateDetails"]), 0, 50));
                    $pastoralRecord->setBody($record["PrivateDetails"]);
                    $pastoralRecord->setPrivate(true);
                    $this->getPastoralMapper()->save($pastoralRecord);

                    /** @var PastoralAccess $accessRecord */
                    foreach ($accessRecords as $accessRecord) {
                        $accessRecord->setId(null);
                        $accessRecord->setPastoral($pastoralRecord);
                        $this->getPastoralAccessMapper()->save($accessRecord);
                    }
                }

                $logRecord->setPastoralId($pastoralRecord->getId());
                $logRecord->setResult('OK');
                $this->getPastoralLogMapper()->save($logRecord);

            } catch (Exception $e) {

                // If ANYTHING goes wrong during the import we need to log it. The import is running in a detached state, so there is no
                // other way to communicate with the user, so an exception would leave the user thinking the import is still running.

                $logRecord->setResult('ERROR');
                $this->getPastoralLogMapper()->save($logRecord);

                $log = $this->getPastoralLogMapper()->createModel();
                $log->setImportId($importId);
                $log->setUserId($user->getId());
                $log->setHeading($e->getMessage());
                $log->setResult('Error');
                $this->getPastoralLogMapper()->save($log);
            }
        }

        $this->connector->disableMultipleActiveResultSets();

        $log = $this->getPastoralLogMapper()->createModel();
        $log->setImportId($importId);
        $log->setUserId($user->getId());
        $log->setHeading("Import Completed");
        $log->setResult("Completed");
        $this->getPastoralLogMapper()->save($log);
    }

    /**
     * @inheritdoc
     */
    public function createPastoral(Pastoral $pastoral)
    {
        // Shut up notifications- we don't want it to send an update notification
        // just because we are saving the external ID back onto the pastoral record

        $silence = new SilentNotif();

        $this->getPastoralMapper()->setNotifier($silence);
        $this->getPastoralCommentMapper()->setNotifier($silence);

        // Annoyingly the use of self::COLUMN_CREATED_TIMESTAMP does not populate the model with the creation date after saving
        // so we need to reload the model from the database to get the date it was created

        // Get the information necessary to create the query

        /** @var Pastoral $pastoral */
        $pastoral = $this->getPastoralMapper()->findById($pastoral->getId());

        // Get the students Synergetic ID

        $studentExternalId = $this->getUserMapper()->findByIdWithFallback($pastoral->getStudent()->getId())->getExternalId();

        // If we don't have a valid external ID- this is not going to work

        if (!$this->isExternalIdValid($studentExternalId)) {
            return false;
        }

        // Execute the query

        $query =
            "
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
                ClassificationCode
            ) VALUES (
                :studentExternalId, 
                :createdAt,
                :publicBody,
                :privateBody,
                :pastoralTypeCode,
                :pastoralCategoryCode,
                :createdDate,
                :createdOwnerId,
                :modifiedDate,
                :modifiedOwnerId,
                '',
                '',
                -1,
                :classificationCode
            )
        ";

        $privateBody = "{$pastoral->getHeading()}\r\n\r\n{$pastoral->getBody()}";
        $publicBody = $pastoral->getPrivate() ? $pastoral->getHeading() : $privateBody;

        // Retrieve pastoral type

        if (empty($pastoral->getType())) {
            $pastoralTypeCode = '';
        } else {
            $pastoralTypeCode = empty($pastoral->getType()->getExternalCode()) ? '' : $pastoral->getType()->getExternalCode();
        }

        // Retrieve pastoral document category

        if (empty($pastoral->getCategory())) {
            $pastoralCategoryCode = '';
        } else {
            $pastoralCategoryCode = empty($pastoral->getCategory()->getExternalCode()) ? '' : $pastoral->getCategory()->getExternalCode();
        }

        // Retrieve pastoral document classification based on the pastoral type

        if (empty($pastoral->getType())) {
            $documentClassificationCode = '';
        } else if (empty($pastoral->getType()->getDocumentClassification())) {
            $documentClassificationCode = '';
        } else {

            try {
                $documentClassification = PastoralDocumentClassificationMapper::getInstance()->findById($pastoral->getType()->getDocumentClassification()->getId());
                $documentClassificationCode = $documentClassification->getExternalCode();
            } catch (MapperException $e) {
                $documentClassificationCode = '';
            }
        }

        $data =  [
            'studentExternalId' => $studentExternalId,
            'createdAt' => $pastoral->getCreatedAt()->getDatabase(),
            'publicBody' => substr($publicBody, 0, 2000),
            'privateBody' => substr($privateBody, 0, 2000),
            'pastoralTypeCode' => substr($pastoralTypeCode, 0, 15),
            'pastoralCategoryCode' => substr($pastoralCategoryCode, 0, 15),
            'classificationCode' => substr($documentClassificationCode, 0, 15),
            'createdDate' => $pastoral->getCreatedAt()->getDatabase(),
            'createdOwnerId' => $pastoral->getOwner()->getExternalId(),
            'modifiedDate' => $pastoral->getCreatedAt()->getDatabase(),
            'modifiedOwnerId' => $pastoral->getOwner()->getExternalId()
        ];

        $result = $this->connector->executeQuery($query, $data);

        $pastoral->setExternalId($this->connector->lastInsertId());
        $this->getPastoralMapper()->save($pastoral);
        $this->updatePastoralCareContacts($pastoral);

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function createComment(PastoralComment $comment)
    {
        // Annoyingly the use of self::COLUMN_CREATED_TIMESTAMP does not populate the model with the creation date after saving
        // so we need to reload the model from the database to get the date it was created

        /** @var PastoralComment $comment */
        $comment = $this->getPastoralCommentMapper()->findById($comment->getId());

        try {
            /** @var Pastoral $pastoral */
            $pastoral = $this->getPastoralMapper()->findById($comment->getPastoral()->getId());

            /** @var User $owner */
            $owner = $this->getUserMapper()->findByIdWithFallback($comment->getOwner()->getId());

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
            'modifiedOwnerId' => $comment->getOwner()->getExternalId()
        ]);

        $comment->setExternalId($this->connector->lastInsertId());
        $this->getPastoralCommentMapper()->save($comment);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function deleteComment(PastoralComment $comment)
    {
        if ($this->isExternalIdValid($comment->getExternalId())) {
            return $this->connector->executeQuery(
                "DELETE FROM PastoralCareComments WHERE PastoralCareCommentsSeq = :externalId",
                ['externalId' => $comment->getExternalId()]
            );
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function updatePastoral(Pastoral $pastoral)
    {
        // Annoyingly the use of self::COLUMN_UPDATED_TIMESTAMP does not populate the model with the updated date after saving
        // so we need to reload the model from the database to get the date it was modified

        /** @var Pastoral $pastoral */
        $pastoral = $this->getPastoralMapper()->findById($pastoral->getId());

        // If we don't have a valid external ID- this is not going to work

        if (!$this->isExternalIdValid($pastoral->getExternalId())) {
            return false;
        }

        // Execute the query

        $query =
        "
            UPDATE      tPastoralCare 
            SET         Details = :publicBody,
                        PrivateDetails = :privateBody,
                        TypeCode = :pastoralTypeCode,
                        CategoryCode = :pastoralCategoryCode,
                        ModifiedDate = :modifiedDate,
                        ClassificationCode = :classificationCode,
                        ModifiedById = :modifiedOwnerId
            WHERE       PastoralCareSeq = :externalId
        ";

        $privateBody = "{$pastoral->getHeading()}\r\n\r\n{$pastoral->getBody()}";
        $publicBody = $pastoral->getPrivate() ? $pastoral->getHeading() : $privateBody;

        // Retrieve pastoral type

        if (empty($pastoral->getType())) {
            $pastoralTypeCode = '';
        } else {
            $pastoralTypeCode = empty($pastoral->getType()->getExternalCode()) ? '' : $pastoral->getType()->getExternalCode();
        }

        // Retrieve pastoral document category

        if (empty($pastoral->getCategory())) {
            $pastoralCategoryCode = '';
        } else {
            $pastoralCategoryCode = empty($pastoral->getCategory()->getExternalCode()) ? '' : $pastoral->getCategory()->getExternalCode();
        }

        // Retrieve pastoral document classification based on the pastoral type

        if (empty($pastoral->getType())) {
            $documentClassificationCode = '';
        } else if (empty($pastoral->getType()->getDocumentClassification())) {
            $documentClassificationCode = '';
        } else {

            try {
                $documentClassification = PastoralDocumentClassificationMapper::getInstance()->findById($pastoral->getType()->getDocumentClassification()->getId());
                $documentClassificationCode = $documentClassification->getExternalCode();
            } catch (MapperException $e) {
                $documentClassificationCode = '';
            }
        }

        $data = [
            'externalId' => $pastoral->getExternalId(),
            'publicBody' => substr($publicBody, 0, 2000),
            'privateBody' => substr($privateBody, 0, 2000),
            'pastoralTypeCode' => substr($pastoralTypeCode, 0, 15),
            'pastoralCategoryCode' => substr($pastoralCategoryCode, 0, 15),
            'classificationCode' => substr($documentClassificationCode, 0, 15),
            'modifiedDate' => $pastoral->getUpdatedAt()->getDatabase(),
            'modifiedOwnerId' => $pastoral->getOwner()->getExternalId()
        ];

        $result = $this->connector->executeQuery($query, $data);

        $this->updatePastoralCareContacts($pastoral);

        return $result;
    }

    /**
     * Updates pastoral care contacts associated with a record in Synergetic
     *
     * @param Pastoral $pastoral
     * @return bool
     */
    private function updatePastoralCareContacts(Pastoral $pastoral)
    {
        // If we don't have a valid external ID- this is not going to work

        if (!$this->isExternalIdValid($pastoral->getExternalId())) {
            return false;
        }

        // Blow away any existing contacts in Synergetic

        $this->connector->executeQuery("DELETE FROM PastoralCareContacts WHERE PastoralCareSeq = :externalId",
            ['externalId' => $pastoral->getExternalId()]
        );

        // Get users that have been given specific access to the record

        $access = $this->getPastoralAccessMapper()->findByColumn('pastoral_id', $pastoral->getId());

        $order = 1;

        /** @var PastoralAccess $record */
        foreach ($access as $record) {
            if ($record->getUser() instanceof User) {
                if ($this->isExternalIdValid($record->getUser()->getExternalId())) {
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
                        'email' => $record->getUser()->getEmail(),
                        'userId' => $record->getUser()->getExternalId()
                    ]);

                    $order++;
                }
            }
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

        return true;
    }

    // Helper functions for retrieving Schoolbox data that matches Synergetic strings and caching the result

    /**
     * Finds/creates a pastoral care type that matches one from Synergetics database
     *
     * @param array $record Pastoral care record
     * @return PastoralType
     */
    private function getPastoralType($record)
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
     * Finds/creates a pastoral care category that matches one from Synergetics database
     *
     * @param array $record Pastoral care record
     * @return PastoralCategory
     */
    private function getPastoralCategory($record)
    {
        // Check the cache first

        if (!isset($this->categoryCache[$record["CategoryDescription"]])) {

            // Didn't find a cached record. Try to retrieve an existing pastoral category from Schoolbox

            // Get the pastoral type that this category belongs to

            $pastoralType = $this->getPastoralType($record);

            try {
                $category = $this->getPastoralCategoryMapper()->findOneByColumnList([
                    'name' => $record["CategoryDescription"],
                    'pastoral_type_id' => $pastoralType->getId()
                ]);
            } catch (MapperException $e) {

                // The records pastoral category doesn't exist in Schoolbox, create one that matches

                $category = $this->pastoralCategoryMapper->createModel();
                $category->setName($record["CategoryDescription"]);
                $category->setExternalCode($record["CategoryCode"]);
                $category->setPastoralType($pastoralType);
                $this->getPastoralCategoryMapper()->save($category);
            }

            $this->categoryCache[$record["CategoryCode"]] = $category;
        }

        return $this->categoryCache[$record["CategoryCode"]];
    }

    /**
     * Finds a schoolbox user by their external ID, returning null if not found
     *
     * @param int $externalId Synergetic ID of person we want to find in Schoolbox
     * @return null|User
     */
    private function getSchoolboxUser($externalId)
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
     * @param $pastoralMapper
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
     * @return $this
     */
    public function setPastoralLogMapper($pastoralLogMapper)
    {
        $this->pastoralLogMapper = $pastoralLogMapper;
        return $this;
    }

    /**
     * @return PastoralCategoryMapper
     */
    public function getPastoralCategoryMapper()
    {
        return $this->pastoralCategoryMapper;
    }

    /**
     * @param $pastoralCategoryMapper
     * @return $this
     */
    public function setPastoralCategoryMapper($pastoralCategoryMapper)
    {
        $this->pastoralCategoryMapper = $pastoralCategoryMapper;
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
     * @return $this
     */
    public function setPastoralDocumentClassificationMapper($pastoralDocumentClassificationMapper)
    {
        $this->pastoralDocumentClassificationMapper = $pastoralDocumentClassificationMapper;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function uploadPastoralDocument(UploadQueue $upload)
    {
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

        // This whole thing is a bit of an ass, unfortunately Synergetic stores its DocMan files in
        // the actual database- which means we need to shove the file into a SQL query as a hex string
        // which is totally inefficient and very slow :(

        // Temporarily increase the memory limit and timeout so we can get the file across to Synergetic

        ini_set('memory_limit', '2048M');
        set_time_limit(0);

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
            INSERT INTO {$this->schema}.tDocuments
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
            'filename' =>               [ 'value' => $storage->getFilename() ],
            'classificationCode' =>     [ 'value' => $classificationCode ],
            'createdById' =>            [ 'value' => $externalId ],
            'modifiedById' =>           [ 'value' => $externalId ],
            'document' =>               [ 'value' => $storage->readStream(), 'type' => PDO::PARAM_LOB ],
            'createdDate' =>            [ 'value' => $date ],
            'modifiedDate' =>           [ 'value' => $date ],
            'sourceDate' =>             [ 'value' => $sourceDate ],
            'documentType' =>           [ 'value' => $extension ],
        ]);

        if (!$query->execute()) {
            throw new ExternalException('Error uploading pastoral document to Synergetic database.');
        }

        $documentReferencesSeq = $this->connector->lastInsertId();

        // Add a document reference to the student so it shows up on their normal community record

        $query = $this->connector->prepareQuery
        (
            "INSERT INTO {$this->schema}.DocumentReferences (ID, DocumentReferencesSeq) VALUES (:id, :documentReferencesSeq);",
            [
                'id' => $studentExternalId,
                'documentReferencesSeq' => $documentReferencesSeq
            ]
        );

        if (!$query->execute()) {
            throw new ExternalException('Error linking pastoral document to record in Synergetic database.');
        }

        // Specifically link it to their pastoral record as well

        $sql =
            "
            INSERT INTO {$this->schema}.tDocumentReferences
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
            FROM    {$this->schema}.tDocumentReferences 
            WHERE   tDocumentsSeq = :documentReferencesSeq2
        ";

        $query = $this->connector->prepareQuery($sql, [
            'pastoralExternalId' => $pastoralExternalId,
            'documentReferencesSeq' => $documentReferencesSeq,
            'documentReferencesSeq2' => $documentReferencesSeq
        ]);

        if (!$query->execute()) {
            throw new ExternalException('Error linking pastoral document to record in Synergetic database.');
        }

        return $documentReferencesSeq;
    }
}
