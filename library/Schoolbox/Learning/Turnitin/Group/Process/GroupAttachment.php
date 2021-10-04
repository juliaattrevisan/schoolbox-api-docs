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

/**
 * Take a Submission Box File from schoolbox and set it up in Turnitin,
 * setting up its Group Attachment record and uploading the file contents
 * just like we do for File.php; since transmission can break at any step
 * we need a way to just force it in
 */
class GroupAttachment
{

    /**
     * Group type in Turnitin
     */
    private const GROUP_TYPE = 'ASSIGNMENT';


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
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $log;


    /**
     * Constructor.
     *
     * @param Turnitin\Api                     $api
     * @param Turnitin\Group\Mapper            $turnitinGroupMapper
     * @param Turnitin\Group\Attachment\Mapper $turnitinGroupAttachmentMapper
     * @param SubmissionBoxMapper              $submissionBoxMapper
     * @param SubmissionBox\SingleFile\Mapper  $submissionBoxFileMapper
     * @param LoggerInterface|null             $log
     */
    public function __construct(
        Turnitin\Api $api,
        Turnitin\Group\Mapper $turnitinGroupMapper,
        Turnitin\Group\Attachment\Mapper $turnitinGroupAttachmentMapper,
        SubmissionBoxMapper $submissionBoxMapper,
        SubmissionBox\SingleFile\Mapper $submissionBoxFileMapper,
        ?LoggerInterface $log = null
    ) {
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
            $groupId = $this->generateTurnitinGroupId($turnitinGroup->getSubmissionBoxId());

            $this->changeGroupState(
                $turnitinGroup,
                sprintf(
                    'Group %s: setting up turnitin Group for submission box %s, setting status %s',
                    $turnitinGroup->getId(),
                    $turnitinGroup->getSubmissionBoxId(),
                    Turnitin\Group\Model::STATUS_TRANSMIT_GROUP
                ),
                Turnitin\Group\Model::STATUS_TRANSMIT_GROUP
            );

            $group = $this->api->retrieve('PUT', "/api/v1/groups/{$groupId}", [
                'name' => $this->submissionBoxMapper->findById($turnitinGroup->getSubmissionBoxId())->getName(),
                'type' => self::GROUP_TYPE
            ]);

            $turnitinGroup->setGroupId($group['id']);

            $this->turnitinGroupMapper->save($turnitinGroup);
        }

        return $turnitinGroup;
    }


    /**
     * A scheme for building group identifiers in turnitin
     * Classically, it's the same as our submission box ids, but we have to distinguish
     * them from staging and production servers for a school since they'll share a
     * turnitin account. This means that activities conduced in staging might pollute
     * the live, and vice versa.
     */
    private function generateTurnitinGroupId(int $submissionBoxId): string
    {
        /* TODO concat with config.site_type and chuck into a codec, I suppose
         */
        return (string)$submissionBoxId;
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
                'Group %s: setting up turnitin Group Attachment containers for submission box %s, setting status %s',
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
            'Submission box %s has %s files associated with it at the moment (%s) but turnitin knows about %s (%s)',
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

            if (null === $turnitinGroupAttachments->getOffset($submissionBoxFileId)) {
                $groupAttachmentId = $this->api->retrieve('POST', "/api/v1/groups/{$turnitinGroup->getGroupId()}/attachments", [
                    'title' => $submissionBoxFile->getBasename()
                ]);

                /* Set it to pending, so we know we need to upload its guts
                 */
                $newGroupAttachment = $this->turnitinGroupAttachmentMapper->createModel()
                    ->setSubmissionBoxFileId($submissionBoxFileId)
                    ->setTurnitinGroupId($turnitinGroup->getGroupId())
                    ->setTurnitinGroupAttachmentId($groupAttachmentId['id'])
                    ->setStatus(Turnitin\Group\Attachment\Model::STATUS_TRANSMIT_PENDING);

                $this->logDebug(sprintf(
                    'Adding new submission box file %s to turnitin',
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
                } catch (\DomainException | Turnitin\Exception\BadEndpoint $e) {
                    if (404 === $e->getCode()) {
                        /* 404 Not Found occurs when you tell turnitin to delete something it doesn't have
                         * This can happen if your transmit script screws up and it half-does something
                         * In this case it doesn't mean very much afaict, you're trying to get rid of something
                         * that doesn't exist anyway - just warn and continue
                         */
                        $this->logWarning(sprintf(
                            'Tried to delete %s from turnitin, but it is not there anyway - skipping',
                            $turnitinGroupAttachment->getTurnitinGroupAttachmentId()
                        ));
                    } elseif (204 === $e->getCode()) {
                        /* 204 No Content returned if you succeeded
                         */
                        $this->logDebug(sprintf(
                            'Removing old submission box file %s (%s) from turnitin',
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
        $this->changeGroupState(
            $turnitinGroup,
            sprintf(
                'Group %s: uploading file contents into corresponding turnitin Group Attachment containers for submission box %s, setting status %s',
                $turnitinGroup->getId(),
                $turnitinGroup->getSubmissionBoxId(),
                Turnitin\Group\Model::STATUS_TRANSMIT_UPLOADING
            ),
            Turnitin\Group\Model::STATUS_TRANSMIT_UPLOADING
        );

        foreach ($turnitinGroupAttachments as $turnitinGroupAttachment) {
            $this->uploadFile($turnitinGroup, $turnitinGroupAttachment, $submissionBoxFiles);
        }

        $this->changeGroupState(
            $turnitinGroup,
            sprintf(
                'Group %s: upload complete for submission box %s, setting status %s',
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
                'Group Attachment %s: setting up turnitin Group Attachment Contents for submission box file %s %s; setting status %s',
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

        $this->api->retrieve('UPLOAD', "/api/v1/groups/{$turnitinGroup->getGroupId()}/attachments/{$turnitinGroupAttachment->getTurnitinGroupAttachmentId()}/original", $payload);

        $this->changeGroupAttachmentState(
            $turnitinGroupAttachment,
            sprintf(
                'Group Attachment %s: upload complete for submission box file %s %s; setting status %s',
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


    /**
     * Log to Logger if one was provided (used in cli tools)
     *
     * @param string $message
     */
    private function logDebug(string $message): void
    {
        if (null !== $this->log) {
            $this->log->debug($message);
        }
    }


    /**
     * Specifically log a warning to the cli
     *
     * @param string $message
     */
    private function logWarning(string $message): void
    {
        if (null !== $this->log) {
            $this->log->warning($message);
        }
    }
}
