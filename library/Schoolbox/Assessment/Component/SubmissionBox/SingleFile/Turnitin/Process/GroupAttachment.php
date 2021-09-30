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
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $log;


    /**
     * Constructor.
     *
     * @param Api                  $api
     * @param Turnitin\Mapper      $turnitinMapper
     * @param LoggerInterface|null $log
     */
    public function __construct(
        Api $api,
        Turnitin\Mapper $turnitinMapper,
        ?LoggerInterface $log = null
    ) {
        $this->api = $api;
        $this->turnitinMapper = $turnitinMapper;
        $this->log = $log;
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
        $groupAttachmentIds = $groupAttachments->indexBy('turnitin_group_attachment_id')->keys();

        /* Find a list of the existing group attachments
         * then compare it to the list of current submission box attachments
         */
        $existingAttachments = $this->api->retrieve('GET',"/api/v1/groups/{$groupId}/attachments", []);

        foreach ($existingAttachments as $existingAttachment) {
            if (\in_array($existingAttachment['id'], $groupAttachmentIds, true)) {
                /* Keep these and process them if you need;
                 * they should be here
                 */
            } else {
                /* Delete these group attachments out of turnitin
                 * They've been removed from schoolbox side
                 */
            }
        }

        /*  - keep the intersection
         *  - delete the excess group attachments from turnitin
         *  - add the new ones
         */

    }

    public function process()
    {
        $this->createGroupAttachment();

        $this->uploadFile();
    }


    /**
     * Find all the existing group attachments for the group_id
     * corresponding to a submission box
     */
    public function getExisting() {
    }

    public function createGroupAttachment() {}

    public function uploadFile() {}

    public function changeState() {}

    public function logWarning() {}

    public function logDebug() {}
}