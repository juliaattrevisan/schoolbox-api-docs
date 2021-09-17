<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin\Process;

use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Turnitin;
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
     * Group type in Turnitin
     */
    private const GROUP_TYPE = 'ASSIGNMENT';


    /**
     * EULA language for our people to accept
     */
    private const EULA_LANGUAGE = 'en-US';


    /**
     * @var Turnitin\Api
     */
    private Turnitin\Api $api;


    /**
     * @var Turnitin\Mapper
     */
    private Turnitin\Mapper $turnitinMapper;


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
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $log;


    /**
     * Constructor.
     *
     * @param Turnitin\Api         $api
     * @param Turnitin\Mapper      $turnitinMapper
     * @param UserMapper           $userMapper
     * @param SubmissionBoxMapper  $boxMapper
     * @param SingleFile\Mapper    $ssfMapper
     * @param LoggerInterface|null $log
     */
    public function __construct(
        Turnitin\Api $api,
        Turnitin\Mapper $turnitinMapper,
        UserMapper $userMapper,
        SubmissionBoxMapper $boxMapper,
        SingleFile\Mapper $ssfMapper,
        ?LoggerInterface $log = null
    ) {
        $this->api = $api;
        $this->turnitinMapper = $turnitinMapper;
        $this->userMapper = $userMapper;
        $this->boxMapper = $boxMapper;
        $this->ssfMapper = $ssfMapper;
        $this->log = $log;
    }


    /**
     * At this point we have records in turnitin_report that are currently
     * in the PENDING state; these require File processing, which involves
     * creating the Submission, Group and File contents in Turnitin
     *
     * When that's done a secondary process generates the Report info
     *
     * @param Turnitin\Model $model
     * @throws Turnitin\Exception\Credentials
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \Schoolbox\Mapper\Exception\Deleted
     * @throws \UnexpectedValueException
     */
    public function process(Turnitin\Model $model): void
    {
        /* These are schoolbox errors, not turnitin errors
         * Try to exit early
         */

        /* The student submission file exists (i.e. not deleted)
         * This is a mandatory ingredient for all future steps
         * If we die here, we all die
         */
        try {
            $ssf = $this->ssfMapper->findById($model->getStudentSubmissionFileId());
        } catch (\Schoolbox\Mapper\Exception $e) {
            $model
                ->setErrorCode('Student Submission File Not Found')
                ->setErrorMessage('Student Submission File not found; maybe its record was deleted');

            $this->changeState(
                $model,
                sprintf(
                    'Report %s: Student Submission File %s not found; setting to status %s',
                    $model->getId(),
                    $model->getStudentSubmissionFileId(),
                    Turnitin\Model::STATUS_ERROR
                ),
                Turnitin\Model::STATUS_ERROR
            );

            return;
        }

        /* It also has to be a valid file for upload
         *
         * Note: The controller usage of this should have denied invalid mimes
         * but who knows how a record might actually get populated beyond our
         * own intended controller usage (ie. 3rd party imports/syncs)
         */
        if (!(new Turnitin\ValidFile())->isSatisfiedBy($ssf)) {
            $model
                ->setErrorCode('Invalid Filetype')
                ->setErrorMessage('Student Submission File has an invalid mime type');

            $this->changeState(
                $model,
                sprintf(
                    'Report %s: Student Submission File %s %s has an invalid mime type; setting to status %s',
                    $model->getId(),
                    $ssf->getId(),
                    $ssf->getBasename(),
                    Turnitin\Model::STATUS_ERROR
                ),
                Turnitin\Model::STATUS_ERROR
            );

            return;
        }

        /* Commencing Purification.
         */
        try {
            /* Create a Turnitin Group
             */
            $group = $this->createGroup($model, $ssf);

            /* TODO: Remember the group_id in turnitin_group (later)
             */

            /* Create a Turnitin Submission
             */
            if (null !== $group) {
                $submission = $this->createSubmission($model, $ssf, $group);

                /* Upload a Student Submission File's contents to the Turnitin Submission
                 */
                if (null !== $submission) {
                    /* Remember the turnitin_submission_id
                     */
                    $model->setTurnitinSubmissionId($submission['id']);
                    $this->turnitinMapper->save($model);

                    /* Continue with file upload itself
                     */
                    $upload = $this->uploadFile($model, $ssf, $submission);

                    /* Once we uploaded, we wait for turnitin
                     */
                    if (null !== $upload) {
                        $this->changeState(
                            $model,
                            sprintf(
                                'Report %s: Submission File Contents for submission file %s %s sent to Turnitin for processing; setting to status %s',
                                $model->getId(),
                                $ssf->getId(),
                                $ssf->getBasename(),
                                Turnitin\Model::STATUS_TRANSMIT_LOADED
                            ),
                            Turnitin\Model::STATUS_TRANSMIT_LOADED
                        );
                    }

                    /* There are no further steps we need to conduct here
                     *
                     * Normally turnitin uses a webhook to tell us when it's finished
                     * but we can't trust that will always work
                     */
                }
            }
        } catch (Turnitin\Exception\BadEndpoint | Turnitin\Exception\Credentials | \DomainException $e) {
            $this->logWarning($e->getMessage());

            $this->turnitinMapper->save($model
                ->setErrorCode((string)$e->getCode())
                ->setErrorMessage($e->getMessage()));
        }
    }


    /**
     * Log to Logger if one was provided (used in cli tools)
     * Change the state at the same time
     *
     * @param Turnitin\Model $model
     * @param string         $message
     * @param string         $status
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function changeState(Turnitin\Model $model, string $message, string $status): void
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

        $this->turnitinMapper->save($model);
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


    /**
     * Create a Turnitin Group, which is synonymous with our Submission Boxes
     *
     * Apparently you just shove in an identifier, turnitin understands that...
     * ...which seems a little odd, but ok. It puts the onus on us (haha) to
     * manage these. Since our Box Ids are guaranteed to be unique, in it goes
     *
     * @param Turnitin\Model   $model
     * @param SingleFile\Model $ssf
     * @return array|null
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
    private function createGroup(Turnitin\Model $model, SingleFile\Model $ssf): ?array
    {
        /* Log the state change
         */
        $this->changeState(
            $model,
            sprintf(
                'Report %s: setting up turnitin Group for submission file %s %s to status %s',
                $model->getId(),
                $ssf->getId(),
                $ssf->getBasename(),
                Turnitin\Model::STATUS_TRANSMIT_GROUP
            ),
            Turnitin\Model::STATUS_TRANSMIT_GROUP
        );

        /* The box has to exist (i.e. not deleted)
         */
        try {
            $box = $this->boxMapper->findById($ssf->getSubmission()->getSubmissionBox()->getId());
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->turnitinMapper->save($model
                ->setStatus(Turnitin\Model::STATUS_ERROR)
                ->setErrorCode('Submission Box Not Found')
                ->setErrorMessage('Submission Box not found; maybe its record was deleted'));

            return null;
        }

        /* Got through that? Keep going
         */
        $payload = [
            'name' => $box->getName(),
            'type' => self::GROUP_TYPE
        ];

        return $this->api->retrieve('PUT', "/api/v1/groups/{$box->getId()}", $payload);
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
     * @param Turnitin\Model   $model
     * @param SingleFile\Model $ssf
     * @param array            $group
     * @return array|null
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
    private function createSubmission(Turnitin\Model $model, SingleFile\Model $ssf, array $group): ?array
    {

        /* Log the state change
         */
        $this->changeState(
            $model,
            sprintf(
                'Report %s: setting up turnitin Submission for submission file %s %s to status %s',
                $model->getId(),
                $ssf->getId(),
                $ssf->getBasename(),
                Turnitin\Model::STATUS_TRANSMIT_SUBMISSION
            ),
            Turnitin\Model::STATUS_TRANSMIT_SUBMISSION
        );

        /* TODO Maybe should update SingleFile\Mapper to hydrate Owners always, but that's
         *  a pretty sweeping assumption that we needed them everywhere else...
         *
         * The student submission file owner has to exist (i.e. not deleted)
         */
        try {
            $owner = $this->userMapper->findById($ssf->getOwner()->getId());
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->turnitinMapper->save($model
                ->setStatus(Turnitin\Model::STATUS_ERROR)
                ->setErrorCode('Student Submission File Owner Not Found')
                ->setErrorMessage('Student Submission File owner not found; maybe their record was deleted'));

            return null;
        }

        /* Status 451 is used to do some EULA business
         * {"status":451,"message":"47 has not accepted a EULA"}
         *
         * TODO Accept it on their behalf, because that dialog would get old real fast
         *  Anyway, that datetime format is specific, don't mess with it
         */
        $eulaResult = $this->api->retrieve('POST', '/api/v1/eula/v1beta/accept', [
            'user_id' => $owner->getId(),
            'accepted_timestamp' => gmdate("Y-m-d\TH:i:s\Z"),
            'language' => self::EULA_LANGUAGE
        ]);

        /* TODO maybe some error checking, but I haven't seen it die yet
         */

        /* Set up the payload
         *
         * Example result:
         * {"owner":"47","title":"[106] Online Submission","status":"CREATED","id":"d756ffe5-1a61-4bf9-b7f7-1fc7950d70a3","created_time":"2021-09-13T06:17:48.685Z"}
         */
        $payload = [
            'owner' => $owner->getId(),
            /* Is this the name of the submission file or the assessment?
             * I think it should be the submission file since you have multiple
             * student submission files per assessment
             */
            'title' => "[{$ssf->getId()}] {$ssf->getFilename()}",
            'owner_default_permission_set' => 'LEARNER',
            'metadata' => [
                'owners' => [
                    [
                        'id' => $owner->getId(),
                        'given_name' => $owner->getFirstname(),
                        'family_name' => $owner->getLastname(),
                        'email' => $owner->getEmail()
                    ]
                ],
                'group' => $group
            ]
        ];

        return $this->api->retrieve('POST', "/api/v1/submissions", $payload);
    }


    /**
     * Upload a file's contents to turnitin into the given turnitin submission identifier
     *
     * @param Turnitin\Model   $model
     * @param SingleFile\Model $ssf
     * @param array            $submission
     * @return array|null
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
    private function uploadFile(Turnitin\Model $model, SingleFile\Model $ssf, array $submission): ?array
    {
        /* Log the state change
         */
        $this->changeState(
            $model,
            sprintf(
                'Report %s: setting up turnitin Submission File Contents for submission file %s %s to status %s',
                $model->getId(),
                $ssf->getId(),
                $ssf->getBasename(),
                Turnitin\Model::STATUS_TRANSMIT_UPLOADING
            ),
            Turnitin\Model::STATUS_TRANSMIT_UPLOADING
        );

        $payload = [
            'filename' => $ssf->getBasename(),
            'contents' => $ssf->read()
        ];

        return $this->api->retrieve('UPLOAD', "/api/v1/submissions/{$submission['id']}/original", $payload);
    }
}
