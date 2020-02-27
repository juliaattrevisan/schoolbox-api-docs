#!/usr/bin/php
<?php
/**
 * @author      Steve Chan <steve.chan@schoolbox.com.au>
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        http://www.schoolbox.com.au/
 */
declare(strict_types=1);

use Alaress\Database\Connector\IConnector;
use Alaress\Database\Statement\Query\IQuery;
use Schoolbox\Application\CliTool;
use Schoolbox\Collection;
use Schoolbox\Mapper;
use Schoolbox\Pastoral;
use Schoolbox\Resource\Storage;
use Schoolbox\Role;
use Schoolbox\RoleMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../../conf/core/require.php';

/**
 * CLI Tool
 */
$app = new class extends CliTool\App
{
    // --help: print help
    private const OPT_LONG_HELP = 'help';


    /**
     * Result set query
     */
    private const QUERY = "
        SELECT
            dr.ID AS studentId,
            t.Description AS title,
            t.DocumentCreatedByID AS ownerId,
            t.DocumentCreatedDate AS createdAt,
            t.DocumentModifiedDate AS updatedAt,
            t.SourcePath AS filepath,
            t.[Document],
            LOWER(t.DocumentType) AS ext
        FROM media.pvDocumentReferences AS dr
        INNER JOIN media.tDocuments AS t ON t.tDocumentsSeq = dr.tDocumentsSeq
        WHERE (dr.tDocumentsSeq IS NOT NULL) AND t.ClassificationCode = 'LearningSupport'
    ";


    /**
     * All imported pastoral records will have this type
     */
    private const PASTORAL_TYPE = 'Learning Support';


    /**
     * All imported pastoral records will have this subtype
     */
    private const PASTORAL_SUBTYPE = 'Reports and Files';


    /**
     * Fallback id, and integer
     */
    private const OPT_LONG_FALLBACK_USER_ID = 'fallback:';


    /**
     * @var UserMapper
     */
    private $userMapper;


    /**
     * @var Pastoral\Mapper
     */
    private $pastoralMapper;


    /**
     * @var Pastoral\Access\Mapper
     */
    private $pastoralAccessMapper;


    /**
     * @var Pastoral\Type\Mapper
     */
    private $pastoralTypeMapper;


    /**
     * @var Pastoral\SubType\Mapper
     */
    private $pastoralSubTypeMapper;


    /**
     * @var Pastoral\Attachment\Mapper
     */
    private $pastoralAttachmentMapper;


    /**
     * @var RoleMapper
     */
    private $roleMapper;


    /**
     * @var Storage\Mapper
     */
    private $storageMapper;


    /**
     * @var IConnector|null
     */
    private $externalDatabase;


    /**
     * @var User
     */
    private $fallbackOwner;


    /**
     * @var Pastoral\Type\Model
     */
    private $pastoralType;


    /**
     * @var Pastoral\SubType\Model
     */
    private $pastoralSubType;


    /**
     * @var Collection
     */
    private $allRoles;


    /** @inheritdoc */
    public function getLongOpts(): array
    {
        return [
            self::OPT_LONG_HELP,
            self::OPT_LONG_FALLBACK_USER_ID
        ];
    }


    /**
     * Run the cli tool
     */
    public function run(): void
    {
        $fallbackId = $this->getOpt(self::OPT_LONG_FALLBACK_USER_ID);

        if ($this->getOpt(self::OPT_LONG_HELP) || !(bool)$fallbackId) {
            echo <<<HELP
DCC Import pastoral records with attachments.

You need to configure the following:
  - pastoral fallback user id
  - the Pastoral Type "Learning Support" in /adminv2/lists/pastoral-category
  - the Pastoral SubType "Reports and Files" in /adminv2/lists/pastoral-category

Usage: pastoral-import.php --fallback {id} [--perform]

HELP;
            exit;
        }

        /* Mappers
         */
        $this->userMapper = UserMapper::getInstance();
        $this->pastoralMapper = Pastoral\Mapper::getInstance();
        $this->pastoralAccessMapper = Pastoral\Access\Mapper::getInstance();
        $this->pastoralTypeMapper = Pastoral\Type\Mapper::getInstance();
        $this->pastoralSubTypeMapper = Pastoral\SubType\Mapper::getInstance();
        $this->pastoralAttachmentMapper = Pastoral\Attachment\Mapper::getInstance();
        $this->roleMapper = RoleMapper::getInstance();
        $this->storageMapper = Storage\Mapper::getInstance();

        /* Set up external db connection
         */
        $this->setExternalDb();

        /* Find out if someone set the Pastoral Fallback User ID
         * If it's not set, that'll be a problem
         */
        $this->setFallbackOwner((int)$fallbackId);

        /* Find the Pastoral Type "Learning Support" - it has to be called that exactly
         * If that isn't set, that'll be a problem
         */
        $this->setPastoralType();

        /* Find the Pastoral SubType "Reports and Files" - it has to be called that exactly
         * If that isn't set, that'll be a problem
         */
        $this->setPastoralSubType();

        /* Find all the Staff roles; if none exist, don't import anything
         * (nobody can see them?)
         */
        $this->setStaffRoles();

        /* Process the resultset
         */
        $this->processData();
    }


    /**
     * Set up external db connection
     *
     * @return void
     */
    private function setExternalDb(): void
    {
        $this->externalDatabase = Schoolbox\Database\Factory::getInstanceExternal();

        if (!$this->externalDatabase) {
            $this->getLogger()->critical('Null external database for DCC Pastoral Importer; exiting');

            exit();
        }
    }


    /**
     * Find out if someone set the Pastoral Fallback User ID
     * If it's not set, that'll be a problem
     *
     * @param int $fallbackOwnerId
     * @return void
     */
    private function setFallbackOwner(int $fallbackOwnerId): void
    {
        try {
            $this->fallbackOwner = $this->userMapper->findById($fallbackOwnerId);
        } catch (Mapper\Exception $e) {
            $this->getLogger()->critical(sprintf(
                'Pastoral Fallback User ID %s does not exist in schoolbox',
                $fallbackOwnerId
            ));

            exit();
        }
    }


    /**
     * Find the Pastoral Type "Learning Support" - it has to be called that exactly
     * If that isn't set, that'll be a problem
     *
     * @return void
     */
    private function setPastoralType(): void
    {
        try {
            $this->pastoralType = $this->pastoralTypeMapper->findOneByColumn('name', self::PASTORAL_TYPE);
        } catch (Mapper\Exception $e) {
            $this->getLogger()->critical(sprintf(
                'Pastoral Type "%s" does not exist in schoolbox',
                self::PASTORAL_TYPE
            ));

            exit();
        }
    }


    /**
     * Find the Pastoral SubType "Reports and Files" - it has to be called that exactly
     * If that isn't set, that'll be a problem
     *
     * @return void
     */
    private function setPastoralSubType(): void
    {
        try {
            $this->pastoralSubType = $this->pastoralSubTypeMapper->findOneByColumn('name', self::PASTORAL_SUBTYPE);
        } catch (Mapper\Exception $e) {
            $this->getLogger()->critical(sprintf(
                'Pastoral SubType "%s" does not exist in schoolbox',
                self::PASTORAL_SUBTYPE
            ));

            exit();
        }
    }


    /**
     * Find all the Staff roles; if none exist, don't import anything
     * (nobody can see them?)
     *
     * @return void
     */
    private function setStaffRoles(): void
    {
        $this->allRoles = $this->roleMapper->findByColumn('group_type', Role::STAFF);

        if (!$this->allRoles->count()) {
            $this->getLogger()->critical(sprintf(
                'No Staff roles found in schoolbox'
            ));

            exit();
        }
    }


    /**
     * Derive a filename from the file path specified in the result set
     * This seems to be a windows machine to use the other slash instead
     *
     * @param string $filepath
     * @return false|string
     */
    private function getFilename(string $filepath)
    {
        $slashPos = strrpos($filepath, '\\');

        return substr($filepath, $slashPos+1);
    }



    /**
     * Process all the data
     *
     * @return void
     */
    private function processData(): void
    {

        /* Remember where we started this import; the timestamps will not be truthful in this import
         */
        $firstId = null;

        $statement = $this->externalDatabase->prepareQuery(self::QUERY);
        $statement->execute();

        $summary = [
            'success' => 0,
            'failure' => 0
        ];

        /* Otherwise continue assigning details per pastoral record
         */
        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {

            /* If the student of the record doesn't exist, skip them with a warning
             */
            try {
                $student = $this->userMapper->findByExternalId($row['studentId'], Role::STUDENT);
            } catch (Mapper\Exception $e) {
                $this->getLogger()->warning(sprintf(
                    'Student %s was not found in schoolbox; skipping their pastoral record "%s"',
                    $row['studentId'],
                    $row['title']
                ));

                $summary['failure']++;

                continue;
            }

            /* If the assigned user(s) couldn't be found, use a fallback user
             * FIXME The owner of the pastoral records is currently not defined
             *  The school needs to tell me where to find their pastoral record owners
             *  Their query doesn't include it
             */
            $owner = $this->fallbackOwner;

            try {
                $owner = $this->userMapper->findByExternalId($row['ownerId'], Role::STAFF);
            } catch (Mapper\Exception $e) {
                $this->getLogger()->warning(sprintf(
                    'Owner external id %s was not found in schoolbox; attempting to assign fallback user id "%s"',
                    $row['ownerId'],
                    $this->fallbackOwner->getId()
                ));
            }

            /* Log it
             */
            $this->getLogger()->info(sprintf(
                'Preparing record "%s" for student %s, owned by %s %s',
                $row['title'],
                $row['studentId'],
                $owner->getFullName(),
                $owner->getExternalId()
            ));

            /* Manufacture a pastoral record
             * Assign values to pastoral record
             */
            $record = $this->pastoralMapper->createModel()
                ->setStudent($student)
                ->setHeading($row['title'])
                ->setBodyPublic('')
                ->setBodyPrivate('')
                ->setOwner($owner)
                ->setType($this->pastoralType)
                ->setSubType($this->pastoralSubType);

            /* Save it
             */
            if ($this->perform()) {
                $this->pastoralMapper->save($record);

                /* Remember where we started, in case we manually need to undo all this
                 */
                if (null === $firstId) {
                    $firstId = $record->getId();
                }
            }

            /* Update idiosyncractically and specifically with the provided timestamps
             */
            $this->getLogger()->info(sprintf(
                'Assigning dates to pastoral record: created_at %s, updated_at %s',
                $row['createdAt'],
                $row['updatedAt']
            ));

            if ($this->perform()) {
                /** @var IQuery $updateStatement */
                $updateStatement = $this->getDi()->get('databaseInternal')->prepareQuery("
                    UPDATE {$this->pastoralMapper->getTable()}
                    SET created_at = :createdAt,
                    updated_at = :updatedAt
                    WHERE id = :recordId
                ");

                $updateStatement->execute([
                    'createdAt' => $row['createdAt'],
                    'updatedAt' => $row['updatedAt'],
                    'recordId'  => $record->getId()
                ]);
            }

            /* Accept raw data into storage->accept()
             */
            $filename = $this->getFilename($row['filepath']);

            if (!$filename) {
                $this->getLogger()->warning(sprintf(
                    'Cannot determine filename for path; skipping: %s',
                    $row['filepath']
                ));

                $summary['failure']++;

                continue;
            }

            $attachment = $this->storageMapper->acceptStream($filename, $row['Document'], true);

            /* Assign the storage file to the pastoral record
             */
            $recordAttachment = $this->pastoralAttachmentMapper->createModel()
                ->setPastoral($record)
                ->setStorage($attachment)
                ->setPrivate(true);

            if ($this->perform()) {
                $this->pastoralAttachmentMapper->save($recordAttachment);
            }

            /* Apply permissions; all staff-level Role Ids can see all pastoral records
             */
            foreach ($this->allRoles as $role) {
                $this->getLogger()->info(sprintf(
                    'Assigning permission to role %s for record "%s"',
                    $role->getName(),
                    $row['title']
                ));

                $accessRecord = $this->pastoralAccessMapper->createModel()
                    ->setPastoral($record)
                    ->setAccess(Pastoral\Access\Mapper::ACCESS_PUBLIC)
                    ->setRole($role);

                if ($this->perform()) {
                    $this->pastoralAccessMapper->save($accessRecord);
                }
            }

            /* Log it
             */
            $this->getLogger()->info(sprintf(
                'Imported record "%s" for student %s, owned by %s %s',
                $row['title'],
                $row['studentId'],
                $owner->getFullName(),
                $owner->getExternalId()
            ));

            $summary['success']++;
        }

        /* I guess this is the upshot
         */
        $this->getLogger()->info(sprintf(
            'Saved %s records successfully. There were %s failures.',
            $summary['success'],
            $summary['failure']
        ));

        if ($this->perform()) {
            $this->getLogger()->info(sprintf(
                'First record in this import batch has pastoral id %s',
                $firstId
            ));
        }

        if (!$this->perform()) {
            $this->getLogger()->info('Planning finished.');
        }
    }
};

$options = [CliTool\Kernel::OPTION_LEVEL => Monolog\Logger::DEBUG];

/** @noinspection PhpUnhandledExceptionInspection */
(new Schoolbox\Application\CliTool\Kernel($app, $options))->runApp();
