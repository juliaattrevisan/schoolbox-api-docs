<?php

/**
 * Mapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan;

use PDOStatement;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Collection;
use Schoolbox\File\Mimetype;
use Schoolbox\File\MimetypeMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\v3\Connector;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Model as Plagscan;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\Resource\Storage\Model as Storage;
use Schoolbox\User;
use Sentry\State\HubInterface;

/**
 * @method Model findOneByColumn($columnName, $columnValue, array $options = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * An instance of the Plagscan Connector
     * which is where files end up
     *
     * @var Connector
     */
    private $connector;

    /**
     * A registry of allowed extension-mime tuples
     *
     * @var ValidFiles
     */
    private $validFiles;

    /**
     * Mapping.
     */
    protected function mapping(): void
    {
        $this->hasTable('plagscan_document');
        $this->hasModel(Plagscan::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('student_submission_file_id', self::TYPE_INTEGER);
        $this->uses('plagscan_id', self::TYPE_INTEGER);
        $this->uses('status', self::TYPE_INTEGER);
        $this->uses('score', self::TYPE_STRING);
        $this->joins('storage_hash', StorageMapper::class, 'hash', null, null, 'storage', 'storage');
        $this->uses('interactive_url', self::TYPE_STRING);
        $this->uses('privatised_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * @inheritdoc
     */
    protected function getSelectString(): string
    {
        $storageMapper = $this->getJoinMapper('storage');

        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('storage', 's')},
                        {$storageMapper->getJoinColumnString('allowedFileExtension', 'mime')}
            FROM        {$this->getTable()}
            LEFT JOIN   {$this->getJoinSql('storage', '', 's', true)}
            LEFT JOIN   {$storageMapper->getJoinSql('allowedFileExtension', 's', 'mime')}
        ";
    }

    /**
     * @inheritdoc
     */
    protected function hydrateSelect(PDOStatement $statement): GenericCollection
    {
        // Return attachments fully hydrated by default
        $collection = $this->createCollection();

        /** @var MimetypeMapper $mimeMapper */
        $mimeMapper = $this->getJoinMapper('storage')->getJoinMapper('allowedFileExtension');

        foreach ($statement as $row) {
            $model = $this->hydrate($row);

            // Hydrate storage object
            /** @var Storage $storage */
            $storage = $this->hydrateJoin($row, 'storage', 's');

            if ($storage instanceof Storage) {
                // Hydrate a mime type if we can find one
                /** @var Mimetype $mime */
                $mime = $mimeMapper->hydrate($row, 'mime');

                if ($mime instanceof Mimetype) {
                    $storage->setMimetype($mime);
                }
                $model->setStorage($storage);
            }
            $collection->add($model);
        }

        return $collection;
    }

    /**
     * Hook method
     */
    protected function setup()
    {
        $this->validFiles = new ValidFiles();

        /* If plagscan credentials exist, set up all the other stuff
         */
        $plagscanUsername = $this->getConfigLoader()->get('plagscan_username');
        $plagscanClientId = $this->getConfigLoader()->get('plagscan_client_id');
        $plagscanApiKey   = $this->getConfigLoader()->get('plagscan_api_key');

        /* All three data pieces are required to have existed
         * This is the build order for plagscan to work
         *
         * Username + password = plagscan account admin
         * plagscan account admin = generate api key + find out your client id
         * api key + client id = access token
         * access token + api url = plagscan data
         */
        if (strlen($plagscanApiKey) && strlen($plagscanClientId) && strlen($plagscanUsername)) {
            try {
                $this->connector = new Connector($plagscanClientId, $plagscanApiKey, self::getDi()->get('filesystem.storageProxy'));
            } catch (\DomainException $e) {
                /* Could not spawn for some reason, but due to mapper usage
                 * we don't want to detonate - just quietly not use plagscan
                 * Let's send the error to sentry though
                 */
                $this->logConnectorException($e);
            } catch (RuntimeException $e) {
                /* Could not spawn because plagscan api is down
                 */
                $this->logConnectorException(
                    new RuntimeException(
                        "Error: Plagscan unavailable. Could not generate access token.",
                        $e->getCode(),
                        $e
                    )
                );
            }
        }
    }

    /**
     * If the Gateway spawned, plagscan is enabled for the $user specified
     *
     * Assuming the Gateway spawned:
     * - Students and parents can see similarity results allowed via config
     * - Staff and Superusers can always see similarity results
     *
     * @param User $user
     * @return bool
     */
    public function isEnabled(User $user): bool
    {
        /* Did the Gateway spawn properly
         * If so, there is a functioning username/apikey
         */
        if ($this->getConnector()) {
            /* Staffs and super can always see similarity results
             */
            if ($user->isSuperuser() || $user->getRole()->isStaff()) {
                return true;
            }

            /* If the $user is a student or a parent, check a config var too
             */
            if ($this->getConfigLoader()->get('similarity_check_student_visible') && ($user->getRole()->isStudent() || $user->getRole()->isParent())) {
                return true;
            }
        }

        return false;
    }


    /**
     * Save a plagscan stub, so the ui can handle immediate feedback
     * rather than wait for the asynchronous stuff to do its thang
     *
     * @param SubmissionFile $file
     * @return array
     * @throws MapperException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function saveNewPlagscanStub(SubmissionFile $file): array
    {
        /* If gateway never spawned, plagscan not configured
         */
        if (!$this->getConnector()) {
            return [];
        }

        /* Invalid filetype
         */
        if (!$this->validFiles->isPlagscannable($file->getBasename())) {
            return [];
        }

        /** @var Plagscan $plagscan */
        $plagscan = $this->createModel();
        $plagscan->setPlagscanId(null);
        $plagscan->setStatus(Plagscan::STATUS_QUEUED);
        $plagscan->setStudentSubmissionFileId($file->getId());
        $plagscan->setCreatedAt(\Alaress_Date_Format::now());
        $plagscan->setUpdatedAt(\Alaress_Date_Format::now());

        return parent::save($plagscan);
    }


    /**
     * Save a Plagscan payload into db
     *
     * @param SubmissionFile $file
     * @param string         $filename
     *
     * @return array|bool|int[]
     * @throws MapperException
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \UnexpectedValueException
     */
    public function saveNewPlagscan(SubmissionFile $file, $filename)
    {
        /* If gateway never spawned, plagscan not configured
         */
        if (!$this->getConnector()) {
            return [];
        }

        /* Invalid filetype
         */
        if (!$this->validFiles->isPlagscannable($file->getBasename())) {
            return [];
        }

        /* Privatise all previous incarnations of this (assessment, student) tuple to
         * prevent documents from being scanned against older versions of themselves
         */
        $previousPlagscans = $this->findPreviousIncarnationsOf($file->getId());

        /** @var Plagscan $currentPlagscan */
        foreach ($previousPlagscans as $currentPlagscan) {
            $this->privatise($currentPlagscan);
        }

        /* Update stubble record
         *
         * This should help prevent multiple-plagscan-per-file scenario, which
         * initiates dupe files in Inline Grader and Submission History
         */
        /** @var Plagscan $plagscan */
        try {
            $plagscan = $this->findOneByColumn('student_submission_file_id', $file->getId(), ['cache' => false]);
        } catch (MapperException $e) {
            /* No plagscan stub existed for this file
             * Spawn one and continue
             */
            $plagscan = $this->createModel();
            $plagscan->setStudentSubmissionFileId($file->getId());
            $plagscan->setCreatedAt(\Alaress_Date_Format::now());
        }

        $plagscan->setStatus(Plagscan::STATUS_QUEUED);
        $plagscan->setUpdatedAt(\Alaress_Date_Format::now());

        // We save it early so if it fails we at least have a record
        parent::save($plagscan);

        /* Send it to plag
         */

        try {
            $plagscanId = $this->getConnector()->transmit($file, $filename);
            $plagscan->setPlagscanId($plagscanId);
            $plagscan->setStatus(Plagscan::STATUS_PROCESSING);
        } catch (\DomainException $e) {
            // Something went unrecoverably wrong, so remove the document from the queue
            $plagscan->setStatus(Plagscan::STATUS_UNSCANNABLE);
            $this->logConnectorException($e);
        } catch (RuntimeException $e) {
            // Plagscan is unavailable or otherwise behaving unexpectedly - try again later
            // Status is already queued, no need to change that
            $this->logConnectorException(
                new RuntimeException(
                    "Error: Plagscan unavailable. Could not generate access token.",
                    $e->getCode(),
                    $e
                )
            );
            // TODO is this check necessary - can't we assume plagscanID is empty if we're in the catch?
            // TODO need to return [] in other catch as well
            // @phpstan-ignore-next-line: this _can_ be empty
            if (empty($plagscanId)) {
                return [];
            }
        }

        return parent::save($plagscan);
    }


    /**
     * Derive a plagscan filename that's used in Plagscan's systems
     * to identify files that people upload
     *
     * Current scheme is to use
     * <schoolbox user id>_<firstname>_<lastname>_<assessment id>
     *
     * @param SubmissionFile $submissionFile
     * @return null|string
     * @throws \PDOException
     */
    public function plagscanFilename(SubmissionFile $submissionFile): ?string
    {
        $query = '
            SELECT u.id, u.firstname, u.lastname, sb.assessment_id
            FROM student_submission_file sf
            INNER JOIN student_submission ss ON sf.student_submission_id = ss.id
            INNER JOIN submission_box sb on ss.box_id = sb.id
            INNER JOIN user u ON ss.owner = u.id
            WHERE sf.id = :submissionFileId
        ';

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'submissionFileId' => $submissionFile->getId()
        ]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            return implode('_', array_values($row));
        }

        return null;
    }


    /**
     * Find previous incarnations of $studentSubmissionFileId
     * that haven't already been privatised
     *
     * This identifies assessment/owner ids and finds all the plagscan
     * instances relevant to anything authored by owner in assessment
     *
     * @param int $studentSubmissionFileId
     * @return Collection
     * @throws MapperException
     * @throws \PDOException
     */
    public function findPreviousIncarnationsOf($studentSubmissionFileId): Collection
    {
        $plagscanDocuments = new Collection();

        $query = '
            SELECT student_submission.`id`, `owner`, `box_id`
            FROM student_submission_file
            INNER JOIN student_submission ON student_submission_file.`student_submission_id` = student_submission.`id`
            WHERE student_submission_file.`id` = :studentSubmissionFileId
        ';

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'studentSubmissionFileId' => $studentSubmissionFileId
        ]);

        $ownerDetails = $statement->fetch(\PDO::FETCH_ASSOC);

        /* Owner and box_id exists
         */
        if ($ownerDetails) {
            $query = "
                SELECT {$this->getColumnString()}
                FROM {$this->getTable()}
                INNER JOIN student_submission_file ON {$this->getTable()}.`student_submission_file_id` = student_submission_file.`id`
                INNER JOIN student_submission ON student_submission.`id` = student_submission_file.`student_submission_id`
                WHERE {$this->getTable()}.`privatised_at` IS NULL
                AND student_submission.`owner` = :ownerId
                AND student_submission.`box_id` = :boxId
                AND student_submission.`id` < :submissionId
            ";

            $statement = $this->getDatabase()->prepare($query);
            $statement->execute([
                'ownerId' => $ownerDetails['owner'],
                'boxId' => $ownerDetails['box_id'],
                'submissionId' => $ownerDetails['id'],
            ]);

            /* De-dupe all the found plagscans
             *
             * Probably don't need to do this, but I want to be sure
             * given I have to curl this id to plagscan.com every time
             */
            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $plagscanDocuments->setOffset($row['plagscan_id'], $this->hydrate($row));
            }
        }

        return $plagscanDocuments;
    }


    /**
     * Privatise a particular plagscan id
     * in both our db and in plagscan's
     *
     * @param Plagscan $plagscan
     *
     * @throws MapperException
     * @throws \DomainException
     * @throws \InvalidArgumentException
     */
    public function privatise(Plagscan $plagscan): void
    {
        try {
            /* If this plagscan record has a plagscanId, privatise it
             *
             * Otherwise we are waiting to obtain it and can't operate on it yet,
             * though the next time someone submits it'll privatise all previous
             * incarnations again anyway. This might be an opportunity for two
             * files in the same ancestry to co-exist as part of the comparison
             * pool due to race conditions in a third party, but I don't think this
             * is easily solved.
             */
            if ($plagscan->getPlagscanId()) {
                /* Privatise in plagscan.com then update our records too
                 */
                $this->getConnector()->privatise($plagscan->getPlagscanId());

                $plagscan->setPrivatisedAt(\Alaress_Date_Format::now());
                $this->save($plagscan);
            }
        } catch (RuntimeException $e) {
            /* Noop - could not privatise in plagscan
             *
             * Leave unchanged if plagscan refuses to do it on their end (jleckie, 6:43pm @ June 1)
             * This allows you to re-encounter documents being checked against older versions of themselves
             * but can't do much to stop it if plagscan screws us
             */
            $this->logConnectorException(
                new RuntimeException('Could not privatise document in Plagscan.', $e->getCode(), $e)
            );
        }
    }

    /**
     * Logs exception raised by the Plagscan connector to Sentry (if possible).
     *
     * @param \Exception $ce
     * @return void
     */
    private function logConnectorException(\Exception $ce): void
    {
        try {
            /** @var HubInterface $sentry */
            $sentry = self::getDi()->get('sentry');
        } catch (NotFoundExceptionInterface | ContainerExceptionInterface $e) {
            return;
        }
        $sentry->captureException($ce);
    }


    /* Getters/Setters
     */

    /**
     * @return null|Connector
     */
    public function getConnector(): ?v3\Connector
    {
        return $this->connector;
    }
}
