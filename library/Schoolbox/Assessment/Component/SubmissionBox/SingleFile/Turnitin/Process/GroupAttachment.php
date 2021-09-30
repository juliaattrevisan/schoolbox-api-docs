<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Component\SubmissionBox\SingleFile\Turnitin\Process;

use Psr\Log\LoggerInterface;
use Schoolbox\Learning\Turnitin\Api;
use Schoolbox\Assessment\Component\SubmissionBox\SingleFile\Turnitin;
use Schoolbox\Learning\Turnitin\Group\Mapper as TurnitinGroupMapper;

/**
 * Take a Group Attachment from schoolbox and set it up in Turnitin,
 * setting up the Group Attachment record and uploading the file contents
 * just like we do for File.php; since transmission can break at any step
 * we need a way to just force it in
 */
class GroupAttachment
{

    /**
     * @var Api
     */
    private Api $api;


    /**
     * @var Turnitin\Mapper
     */
    private Turnitin\Mapper $turnitinMapper;


    /**
     * @var TurnitinGroupMapper
     */
    private TurnitinGroupMapper $turnitinGroupMapper;


    /**
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $log;


    /**
     * Constructor.
     *
     * @param Api                  $api
     * @param Turnitin\Mapper      $turnitinMapper
     * @param TurnitinGroupMapper  $turnitinGroupMapper
     * @param LoggerInterface|null $log
     */
    public function __construct(
        Api $api,
        Turnitin\Mapper $turnitinMapper,
        TurnitinGroupMapper $turnitinGroupMapper,
        ?LoggerInterface $log = null
    ) {
        $this->api = $api;
        $this->turnitinMapper = $turnitinMapper;
        $this->turnitinGroupMapper = $turnitinGroupMapper;
        $this->log = $log;
    }


    public function process(Turnitin\Model $model)
    {
        $this->createGroupAttachment($model);

        $this->uploadFile();
    }


    public function processDiff(\Schoolbox\Collection $files)
    {
        /* Find the $groupId corresponding to the submission box
         * Create one if it doesn't already exist (e.g. on a new Due Work)
         */
        $groupId = 1;

        /* Find all the group_attachment records corresponding to $files
         * This gives you their turnitin_group_attachment_ids, if they exist
         */
        $groupAttachments = $this->turnitinMapper->findByColumn('submission_box_file_id', $files->indexBy('id')->keys());
        $groupAttachmentIds = $groupAttachments->indexBy('turnitinGroupAttachmentId')->keys();

        /* Find a list of the existing group attachments
         * then compare it to the list of current submission box attachments
         */
        $existingAttachments = $this->api->retrieve('GET',"/api/v1/groups/{$groupId}/attachments", []);

        foreach ($existingAttachments as $existingAttachment) {
            $existingAttachmentIds[] = $existingAttachment['id'];
        }

        /* Differential equation
         */
        print_r((new Differential())->generate($groupAttachmentIds, $existingAttachmentIds));

    }



    /**
     * Send them up
     */
    public function createGroupAttachment(Turnitin\Model $model) {

    }

    public function uploadFile() {}

    public function changeState() {}

    public function logWarning() {}

    public function logDebug() {}
}