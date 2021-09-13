<?php
/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin\Process;

use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * The first half of a Turnitin request
 *
 * Set up the submission box and upload the file
 * When turnitin completes, it tells us via a webook
 * to commence the second half of the Turnitin request
 */
class File
{

    /**
     * @var Turnitin\Api
     */
    private Turnitin\Api $api;


    /**
     * @var UserMapper
     */
    private UserMapper $userMapper;


    /**
     * @var SubmissionBoxMapper
     */
    private SubmissionBoxMapper $boxMapper;


    /**
     * @var SingleFile\Mapper
     */
    private SingleFile\Mapper $ssfMapper;


    /**
     * Constructor.
     *
     * @param Turnitin\Api        $api
     * @param UserMapper          $userMapper
     * @param SubmissionBoxMapper $boxMapper
     * @param SingleFile\Mapper   $ssfMapper
     */
    public function __construct(
        Turnitin\Api $api,
        UserMapper $userMapper,
        SubmissionBoxMapper $boxMapper,
        SingleFile\Mapper $ssfMapper
    ) {
        $this->api = $api;
        $this->userMapper = $userMapper;
        $this->boxMapper = $boxMapper;
        $this->ssfMapper = $ssfMapper;
    }


    /**
     * At this point we have records in turnitin_report that are currently
     * in the PENDING state; these require File processing, which involves
     * creating the Submission, Group and File contents in Turnitin
     *
     * When that's done a secondary process generates the Report info
     *
     * @param Turnitin\Model $model
     * @throws \DebugBar\DebugBarException
     */
    public function process(Turnitin\Model $model): void
    {

        /* Check the file exists
         * Without it, we don't do much at all
         */
        try {
            $ssf = $this->ssfMapper->findById($model->getStudentSubmissionFileId());
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop; file not found
            return;
        }

        /* Create a Turnitin Group
         */
        $this->createGroup($ssf);

        /* Create a Turnitin Submission
         */
//        $this->createSubmission($ssf);

        /* Upload a Student Submission File's contents to the Turnitin Submission
         */

        /* If anything went wrong, set the ERROR state in the db
         * for this model; we'll think of a Replay script later
         */
    }


    /**
     * Create a Turnitin Group, which is synonymous with our Submission Boxes
     *
     * @param SingleFile\Model $ssf
     * @throws \DebugBar\DebugBarException
     * @throws \Schoolbox\Mapper\Exception
     */
    private function createGroup(SingleFile\Model $ssf)
    {
        $box = $this->boxMapper->findById($ssf->getSubmission()->getSubmissionBox()->getId());

        $payload = [
            'name' => $box->getName(),
            'type' => 'ASSIGNMENT'
        ];

        $this->api->retrieve('PUT', "/api/v1/groups/{$box->getId()}", $payload);
    }


    /**
     * Apparently the metadata fields [submitter, owners, group, group_context, original_submitted_time]
     * are optional. But the way they've written it in https://developers.turnitin.com/docs/tca#create-a-submission
     * is kind of confusing:
     *
     * "The owner sub-field and associated metadata are required. The other four subfields are optional
     * and can be omitted completely. If you choose to use a sub-field you must send all metadata
     * associated with it. In addition, group_context information will be saved only if a group is also
     * included in the request."
     *
     *  - There isn't an "owner" subfield, just an "owners" subfield
     *  - There are five subfields, so "the other four" would be submitter, group, group_context, original_submitted_time
     *  - That means "owners" struct, and its fields, is a required subfield in metadata
     *
     * @param SingleFile\Model $ssf
     * @throws \DebugBar\DebugBarException
     */
    private function createSubmission(SingleFile\Model $ssf): void
    {

        /* TODO Maybe should update SingleFile\Mapper to hydrate Owners always, but that's
         *  a pretty sweeping assumption that we needed them everywhere else...
         */
        try {
            $owner = $this->userMapper->findById($ssf->getOwner()->getId());
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop; owner not found
            return;
        }

        /* Method is POST
         */

        /* Set up the payload
         */
        $payload = [
            'owner' => $owner->getId(),
            /* Is this the name of the submission file or the assessment?
             * I think it should be the submission file since you have multiple
             * student submission files per assessment
             */
            'title' => "[{$ssf->getId()}] {$ssf->getFilename()}",
            'metadata' => [
                'owners' => [
                    'id' => $owner->getId(),
                    'given_name' => $owner->getFirstname(),
                    'family_name' => $owner->getLastname(),
                    'email' => $owner->getEmail()
                ]
            ]
        ];

        /* Send it
         */
        new \Alaress_Probe($payload);
    }
}
