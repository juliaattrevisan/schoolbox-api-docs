<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Group\Process;

use Psr\Log\LoggerInterface;
use Schoolbox\Collection;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Turnitin\LogTrait;

/**
 * Take a set of Submission Box Files from schoolbox and set it up in Turnitin,
 * setting up its Group Attachment record and uploading the file contents
 * just like we do for File.php; since transmission can break at any step
 * we need a way to just force it in
 *
 * This happens by finding the Submission Box and getting its list of files
 * at runtime, then micromanaging the diff with turnitin. It does this specifically
 * to avoid bottlenecking course imports and due work modifies, which would
 * result in hammering the Turnitin api and maybe desyncing files due to failures
 * with no recourse.
 *
 * Here, it follows the same flavour as Turnitin files, giving you a chance
 * to debug and reprocess them independently of their due works etc.
 */
class Group
{
    use LogTrait;


    /**
     * Group type in Turnitin
     */
    public const GROUP_TYPE = 'ASSIGNMENT';


    /**
     * @var string
     */
    private string $instanceIdentifier;


    /**
     * @var Turnitin\Api
     */
    private Turnitin\Api $api;


    /**
     * @var Turnitin\Group\Mapper
     */
    private Turnitin\Group\Mapper $turnitinGroupMapper;


    /**
     * @var Turnitin\Group\Attachment\Mapper
     */
    private Turnitin\Group\Attachment\Mapper $turnitinGroupAttachmentMapper;


    /**
     * @var SubmissionBoxMapper
     */
    private SubmissionBoxMapper $submissionBoxMapper;


    /**
     * @var SubmissionBox\SingleFile\Mapper
     */
    private SubmissionBox\SingleFile\Mapper $submissionBoxFileMapper;


    /**
     * Constructor.
     *
     * @param string                           $instanceIdentifier
     * @param Turnitin\Api                     $api
     * @param Turnitin\Group\Mapper            $turnitinGroupMapper
     * @param Turnitin\Group\Attachment\Mapper $turnitinGroupAttachmentMapper
     * @param SubmissionBoxMapper              $submissionBoxMapper
     * @param SubmissionBox\SingleFile\Mapper  $submissionBoxFileMapper
     * @param LoggerInterface|null             $log
     */
    public function __construct(
        string $instanceIdentifier,
        Turnitin\Api $api,
        Turnitin\Group\Mapper $turnitinGroupMapper,
        Turnitin\Group\Attachment\Mapper $turnitinGroupAttachmentMapper,
        SubmissionBoxMapper $submissionBoxMapper,
        SubmissionBox\SingleFile\Mapper $submissionBoxFileMapper,
        ?LoggerInterface $log = null
    ) {
        $this->instanceIdentifier = $instanceIdentifier;
        $this->api = $api;
        $this->turnitinGroupMapper = $turnitinGroupMapper;
        $this->turnitinGroupAttachmentMapper = $turnitinGroupAttachmentMapper;
        $this->submissionBoxMapper = $submissionBoxMapper;
        $this->submissionBoxFileMapper = $submissionBoxFileMapper;
        $this->log = $log;
    }


    /**
     * Set up the turnitin Group Attachment containers for a submission box's set of files
     *
     * @param Turnitin\Group\Model $turnitinGroup
     * @throws Turnitin\Exception\BadEndpoint
     * @throws Turnitin\Exception\Credentials
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function process(Turnitin\Group\Model $turnitinGroup): void
    {
        $model = $this->createGroup($turnitinGroup);

        $submissionBoxFiles = $this->createGroupAttachments($model);

        /* Now send up all the group attachments' binary data
         */
        $turnitinGroupAttachments = $this->turnitinGroupAttachmentMapper->findByColumnList([
            'status' => Turnitin\Group\Attachment\Model::STATUS_TRANSMIT_PENDING,
            'submission_box_file_id' => $submissionBoxFiles->getPropertyValues('id')
        ]);

        /* Index by submission box file id, for faster lookupping
         */
        $this->uploadAttachments($turnitinGroup, $turnitinGroupAttachments, $submissionBoxFiles->indexBy('id'));
    }


    /**
     * Create a Turnitin Group for this submission box if it doesn't already exist
     *
     * @param Turnitin\Group\Model $turnitinGroup
     * @return Turnitin\Group\Model
     * @throws Turnitin\Exception\BadEndpoint
     * @throws Turnitin\Exception\Credentials
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createGroup(Turnitin\Group\Model $turnitinGroup): Turnitin\Group\Model
    {
        if ('' === (string)$turnitinGroup->getGroupId()) {
            /* Use a formal syntax for the turnitin group identifier
             */
            $groupId = (new Turnitin\Group\IdCodec())
                ->encode($turnitinGroup->getSubmissionBoxId(), $this->instanceIdentifier);

            $this->changeGroupState(
                $turnitinGroup,
                sprintf(
                    'Group %s: setting up Turnitin Group for submission box %s, setting status [%s]',
                    $turnitinGroup->getId(),
                    $turnitinGroup->getSubmissionBoxId(),
                    Turnitin\Group\Model::STATUS_TRANSMIT_GROUP
                ),
                Turnitin\Group\Model::STATUS_TRANSMIT_GROUP
            );

            $box = $this->submissionBoxMapper->findById($turnitinGroup->getSubmissionBoxId());

            $group = $this->api->retrieve('PUT', "/api/v1/groups/{$groupId}", [
                'name' => $box->getName(),
                'type' => self::GROUP_TYPE,
                'due_date' => gmdate("Y-m-d\TH:i:s\Z", $box->getDueDate()->getUnixTimeStamp()),
                'report_generation' => Turnitin\File\Process\Transmit::REPORT_GENERATION_COLLUSION,
            ]);

            $turnitinGroup->setGroupId($group['id']);

            $this->turnitinGroupMapper->save($turnitinGroup);
        }

        return $turnitinGroup;
    }


    /**
     * Figure out all the Group Attachment containers that we need to set up in turnitin
     * This adds new attachment containers into turnitin
     * This removes attachments that shouldn't be there anymore
     * Anything that already exists inside turnitin remains untouched/unchanged
     *
     * @param Turnitin\Group\Model $turnitinGroup
     * @return Collection
     * @throws Turnitin\Exception\BadEndpoint
     * @throws Turnitin\Exception\Credentials
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createGroupAttachments(Turnitin\Group\Model $turnitinGroup): Collection
    {
        $this->changeGroupState(
            $turnitinGroup,
            sprintf(
                'Group %s: setting up Turnitin Group Attachment containers for submission box %s; setting status [%s]',
                $turnitinGroup->getId(),
                $turnitinGroup->getSubmissionBoxId(),
                Turnitin\Group\Model::STATUS_TRANSMIT_GROUP_ATTACHMENT
            ),
            Turnitin\Group\Model::STATUS_TRANSMIT_GROUP_ATTACHMENT
        );

        $submissionBoxFiles = $this->submissionBoxFileMapper->findByColumn('box_id', $turnitinGroup->getSubmissionBoxId());

        /* Find existing turnitin_group_attachment records for these $file ids
         * and if you find one already in turnitin_group_attachment,
         * don't create a new stub - just use the existing one
         */
        $submissionBoxFileIds = $submissionBoxFiles->getPropertyValues('id');

        $turnitinGroupAttachments = $this->turnitinGroupAttachmentMapper
            ->findByColumn('turnitin_group_id', $turnitinGroup->getGroupId())
            ->indexBy('submissionBoxFileId');

        $this->logDebug(sprintf(
            'Group %s: submission box %s has %s files associated with it at the moment (%s) but Turnitin knows about %s (%s)',
            $turnitinGroup->getId(),
            $turnitinGroup->getSubmissionBoxId(),
            \count($submissionBoxFileIds),
            implode(', ', $submissionBoxFileIds),
            $turnitinGroupAttachments->count(),
            implode(', ', $turnitinGroupAttachments->getPropertyValues('submissionBoxFileId'))
        ));

        /* Put the new ones in
         * This way everything will have a turnitin group attachment identifier
         *
         * If a file id doesn't have a record in turnitin_group_attachments
         * It can't exist in turnitin; create a record for it and send it up
         *
         * If the file id exists, it should already have a group_attachment_id
         * from turnitin; nothing to do
         */
        foreach ($submissionBoxFiles as $submissionBoxFile) {
            $submissionBoxFileId = $submissionBoxFile->getId();

            /* Ensure this is one of the valid filetypes that Turnitin likes
             */
            if (!(new Turnitin\ValidFile())->isSatisfiedBy($submissionBoxFile)) {
                $this->logDebug(sprintf(
                    'Group %s: submission box file %s is an invalid type and is not accepted by Turnitin - skipping',
                    $turnitinGroup->getId(),
                    $submissionBoxFileId,
                ));

                continue;
            }

            if (null === $turnitinGroupAttachments->getOffset($submissionBoxFileId)) {
                $groupAttachmentId = $this->api->retrieve('POST', "/api/v1/groups/{$turnitinGroup->getGroupId()}/attachments", [
                    'title' => $submissionBoxFile->getBasename(),
                    'template' => true
                ]);

                /* Set it to pending, so we know we need to upload its guts
                 */
                $newGroupAttachment = $this->turnitinGroupAttachmentMapper->createModel()
                    ->setSubmissionBoxFileId($submissionBoxFileId)
                    ->setTurnitinGroupId($turnitinGroup->getGroupId())
                    ->setTurnitinGroupAttachmentId($groupAttachmentId['id'])
                    ->setStatus(Turnitin\Group\Attachment\Model::STATUS_TRANSMIT_PENDING);

                $this->logDebug(sprintf(
                    'Group %s: adding new submission box file %s to Turnitin',
                    $turnitinGroup->getId(),
                    $submissionBoxFileId,
                ));

                $this->turnitinGroupAttachmentMapper->save($newGroupAttachment);
            }
        }

        /* Find all the files that used to be here, but have been removed
         * from the Due Work. These are group attachments that previously went into
         * turnitin, but should now be removed
         */
        foreach ($turnitinGroupAttachments as $turnitinGroupAttachment) {
            if (!\in_array($turnitinGroupAttachment->getSubmissionBoxFileId(), $submissionBoxFileIds, true)) {
                try {
                    $this->api->retrieve('DELETE', "/api/v1/groups/{$turnitinGroup->getGroupId()}/attachments/{$turnitinGroupAttachment->getTurnitinGroupAttachmentId()}", []);
                } catch (Turnitin\Exception\Exception $e) {
                    if (404 === $e->getCode()) {
                        /* 404 Not Found occurs when you tell turnitin to delete something it doesn't have
                         * This can happen if your transmit script screws up and it half-does something
                         * In this case it doesn't mean very much afaict, you're trying to get rid of something
                         * that doesn't exist anyway - just warn and continue
                         */
                        $this->logWarning(sprintf(
                            'Group %s: tried to delete %s from Turnitin, but it is not there anyway - skipping',
                            $turnitinGroup->getId(),
                            $turnitinGroupAttachment->getTurnitinGroupAttachmentId()
                        ));
                    } elseif (204 === $e->getCode()) {
                        /* 204 No Content returned if you succeeded
                         */
                        $this->logDebug(sprintf(
                            'Group %s: removing old submission box file %s (%s) from Turnitin',
                            $turnitinGroup->getId(),
                            $turnitinGroupAttachment->getSubmissionBoxFileId(),
                            $turnitinGroupAttachment->getTurnitinGroupAttachmentId()
                        ));
                    } else {
                        /* Anything else is a death
                         */
                        throw $e;
                    }
                }

                /* Softdelete it out of its home
                 */
                $this->turnitinGroupAttachmentMapper->delete($turnitinGroupAttachment);
            }
        }

        /* These files should now exist in turnitin once we're all done later
         * We need their binaries for the next step
         */
        return $submissionBoxFiles;
    }


    /**
     * Upload the files in the given set, transmitting their data to turnitin
     *
     * @param Turnitin\Group\Model $turnitinGroup
     * @param Collection           $turnitinGroupAttachments
     * @param Collection           $submissionBoxFiles
     * @throws Turnitin\Exception\BadEndpoint
     * @throws Turnitin\Exception\Credentials
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    private function uploadAttachments(
        Turnitin\Group\Model $turnitinGroup,
        Collection $turnitinGroupAttachments,
        Collection $submissionBoxFiles
    ): void {
        if ($turnitinGroupAttachments->count()) {
            /* If there are files to upload, tell us
             */
            $this->changeGroupState(
                $turnitinGroup,
                sprintf(
                    'Group %s: uploading file contents into corresponding Turnitin Group Attachment containers for submission box %s, setting status [%s]',
                    $turnitinGroup->getId(),
                    $turnitinGroup->getSubmissionBoxId(),
                    Turnitin\Group\Model::STATUS_TRANSMIT_UPLOADING
                ),
                Turnitin\Group\Model::STATUS_TRANSMIT_UPLOADING
            );

            foreach ($turnitinGroupAttachments as $turnitinGroupAttachment) {
                $this->uploadFile($turnitinGroup, $turnitinGroupAttachment, $submissionBoxFiles);
            }
        } else {
            /* This happens when you modify the Due Work (its status gets reset to Pending)
             * but none of the files changed; nothing to upload, nothing to remove, nothing to do
             */
            $this->logDebug(sprintf(
                'Group %s: all files already exist inside Turnitin; no further changes required',
                $turnitinGroup->getId(),
            ));
        }

        $this->changeGroupState(
            $turnitinGroup,
            sprintf(
                'Group %s: upload complete for submission box %s, setting status [%s]',
                $turnitinGroup->getId(),
                $turnitinGroup->getSubmissionBoxId(),
                Turnitin\Group\Model::STATUS_COMPLETE
            ),
            Turnitin\Group\Model::STATUS_COMPLETE
        );
    }


    /**
     * Upload a single file's binary guts into turnitin
     *
     * @param Turnitin\Group\Model            $turnitinGroup
     * @param Turnitin\Group\Attachment\Model $turnitinGroupAttachment
     * @param Collection                      $submissionBoxFiles
     * @throws Turnitin\Exception\BadEndpoint
     * @throws Turnitin\Exception\Credentials
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \RuntimeException
     */
    private function uploadFile(
        Turnitin\Group\Model $turnitinGroup,
        Turnitin\Group\Attachment\Model $turnitinGroupAttachment,
        Collection $submissionBoxFiles
    ): void {
        $submissionBoxFile = $submissionBoxFiles->getOffset($turnitinGroupAttachment->getSubmissionBoxFileId());

        $this->changeGroupAttachmentState(
            $turnitinGroupAttachment,
            sprintf(
                'Group Attachment %s: setting up Turnitin Group Attachment Contents for submission box file %s %s; setting status [%s]',
                $turnitinGroupAttachment->getId(),
                $submissionBoxFile->getId(),
                $submissionBoxFile->getBasename(),
                Turnitin\Group\Attachment\Model::STATUS_TRANSMIT_UPLOADING
            ),
            Turnitin\Group\Attachment\Model::STATUS_TRANSMIT_UPLOADING
        );

        $payload = [
            'filename' => $submissionBoxFile->getBasename(),
            'contents' => $submissionBoxFile->read()
        ];

        try {
            $this->api->retrieve('UPLOAD', "/api/v1/groups/{$turnitinGroup->getGroupId()}/attachments/{$turnitinGroupAttachment->getTurnitinGroupAttachmentId()}/original", $payload);
        } catch (Turnitin\Exception\Exception $e) {
            if (409 === $e->getCode()) {
                /* 409 is thrown when you try to upload file contents into a container which
                 * already received file contents. This might be okay if it's the same file
                 * contents but it might not be okay if it's a different file contents - this
                 * normally shouldn't happen but it's up to humans to decipher this situation
                 */
                $this->logWarning(sprintf(
                    'Turnitin Group Attachment %s already received file upload contents',
                    $turnitinGroupAttachment->getTurnitinGroupAttachmentId()
                ));

                /* Mostly it should result in noop, imho, since it would occur when
                 * you manually change status to Pending and try to reprocess the same stuff.
                 * In that case it's the same file sending its same guts but it's already done it
                 */
                $turnitinGroupAttachment->setStatus(Turnitin\File\Model::STATUS_COMPLETE);
            } else {
                /* Some other random thing happened
                 */
                $this->logWarning(sprintf(
                    'Turnitin Group Attachment %s: %s %s',
                    $turnitinGroupAttachment->getTurnitinGroupAttachmentId(),
                    $e->getCode(),
                    $e->getMessage()
                ));

                /* All other codes - just report whatever turnitin said, because I have no idea
                 * at this point why it might have died
                 */
                $turnitinGroupAttachment->setStatus(Turnitin\File\Model::STATUS_ERROR);
            }

            /* Save the error code and error message into the usual two columns
             */
            $this->turnitinGroupAttachmentMapper->save($turnitinGroupAttachment
                ->setErrorCode((string)$e->getCode())
                ->setErrorMessage($e->getMessage()));

            return;
        }

        $this->changeGroupAttachmentState(
            $turnitinGroupAttachment,
            sprintf(
                'Group Attachment %s: upload complete for submission box file %s %s; setting status [%s]',
                $turnitinGroupAttachment->getId(),
                $submissionBoxFile->getId(),
                $submissionBoxFile->getBasename(),
                Turnitin\Group\Attachment\Model::STATUS_COMPLETE
            ),
            Turnitin\Group\Attachment\Model::STATUS_COMPLETE
        );
    }


    /**
     * Log to Logger if one was provided (used in cli tools)
     * Change the state at the same time
     *
     * @param Turnitin\Group\Model $model
     * @param string               $message
     * @param string               $status
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function changeGroupState(Turnitin\Group\Model $model, string $message, string $status): void
    {
        $this->logDebug($message);

        /* Clear error messages from previously-successful stages
         * because they are no longer relevant; this allows us to
         * distinguish status states being set in a normal processing
         * or whether something died -whilst- in that state, giving
         * support a diagnosis and resolution option
         */
        $model->setErrorCode(null)
            ->setErrorMessage(null)
            ->setStatus($status);

        $this->turnitinGroupMapper->save($model);
    }


    /**
     * Log to Logger if one was provided (used in cli tools)
     * Change the state at the same time
     *
     * @param Turnitin\Group\Attachment\Model $model
     * @param string                          $message
     * @param string                          $status
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function changeGroupAttachmentState(Turnitin\Group\Attachment\Model $model, string $message, string $status): void
    {
        $this->logDebug($message);

        /* Clear error messages from previously-successful stages
         * because they are no longer relevant; this allows us to
         * distinguish status states being set in a normal processing
         * or whether something died -whilst- in that state, giving
         * support a diagnosis and resolution option
         */
        $model->setErrorCode(null)
            ->setErrorMessage(null)
            ->setStatus($status);

        $this->turnitinGroupAttachmentMapper->save($model);
    }
}
