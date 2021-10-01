<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\Group\Process;

use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox\SingleFile\Mapper as SingleFileMapper;
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
        randomly dumping this for a moreParamSecond()

            /* We need this api
             */
                                  $api = new \Schoolbox\Learning\Turnitin\Api(
                                      $configLoader->get('site_version'),
                                      $configLoader->get('turnitin_api_host'),
                                      $configLoader->get('turnitin_api_key')
                                  );


                                  $files = SingleFileMapper::getInstance()->findByColumn('box_id', $submissionBox->getId(), ['cache' => false]);
        /* TODO Find existing turnitin_group_attachment records for these $file ids
         *  and if you find one already in turnitin_group_attachment,
         *  don't create a new stub - just use the existing one
         */
        $fileIds = $files->getPropertyValues('id');

        $turnitinGroupAttachments = $turnitinGroupAttachmentMapper->findByColumn('submission_box_file_id', $fileIds);
        $turnitinGroupAttachmentIds = $turnitinGroupAttachments->getPropertyValues('turnitinGroupAttachmentId');

        /* TODO once you find the union of new stubs vs existing attachment
         *  records, you'll have a full set of turnitin_group_attachment_ids
         *  associated with the current submission box's files
         */

        /* TODO Then use the differential to figure out which turnitin ids to remove
         *  since you've already added the new ones and the existing ones haven't
         *  been touched in any meaningful way.
         */

        /* TODO When you delete them out of turnitin
         *  also softdelete them from turnitin_group_attachment
         */

        /* TODO Now you're left with a bunch of turnitin_group_attachment records
         *  to process; they can be in mixed states, so just let the groupattachment
         *  transmitter chew through them
         */

        /* TODO It'd be easier to just delete all of them out of turnitin
         *  and just reupload the new set...but that's very costly sometimes
         *  OR make this a manual step
         */

        /* Set up a turnitin stub record
         * This allows it to be picked up and processed by an async script later
         * if it doesn't start processing via the $detachedFunction
         */
//                $models = [];
//
//                foreach ($files as $currentFile) {
//                    $model = $turnitinMapper->saveStub($currentFile);
//
//                    if ($model) {
//                        $models[] = $model;
//                    }
//                }
//
//                        /* Set up detached function so turnitin can continue in the background
//                         */
//                        $detachedFunction = static function () use ($api, $models) {
//
//                            /* Set up a record processor
//                             */
//                            $processor = (new Turnitin\Process\File(
//                                $api,
//                                Turnitin\Mapper::getInstance(),
//                                UserMapper::getInstance(),
//                                SubmissionBoxMapper::getInstance(),
//                                SingleFile\Mapper::getInstance()
//                            ));
//
//                            /* Shank them all up
//                             */
//                            foreach ($models as $model) {
//                                $processor->process($model);
//                            }
//                        };
    }

    public function uploadFile() {}

    public function changeState() {}

    public function logWarning() {}

    public function logDebug() {}
}