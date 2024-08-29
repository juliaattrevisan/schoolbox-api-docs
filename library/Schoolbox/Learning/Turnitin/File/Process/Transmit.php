<?php

/**
 * @copyright   Copyright 2021, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Turnitin\File\Process;

use Psr\Log\LoggerInterface;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Learning\Turnitin;
use Schoolbox\Learning\Turnitin\Api;
use Schoolbox\Learning\Turnitin\Exception\BadEndpoint;
use Schoolbox\Learning\Turnitin\Exception\Conflict;
use Schoolbox\Learning\Turnitin\Exception\Credentials;
use Schoolbox\Learning\Turnitin\LogTrait;
use Schoolbox\Learning\Turnitin\ValidFile;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;

/**
 * The first half of a Turnitin request
 *
 * Set up the submission box and upload the file
 * When turnitin completes, it tells us via a webook
 * to commence the second half of the Turnitin request
 */
class Transmit
{
    use LogTrait;


    /**
     * EULA language for our people to accept
     */
    private const EULA_LANGUAGE = 'en-US';


    /**
     * Collusion check type.
     *
     * Defaults to IMMEDIATELY_AND_DUE_DATE, so people's work are checked
     * on the assessment's due date in addition to right now
     *
     * https://developers.turnitin.com/docs/tca#groups
     */
    public const REPORT_GENERATION_COLLUSION = 'IMMEDIATELY_AND_DUE_DATE';


    /**
     * @var string
     */
    private string $instanceIdentifier;


    /**
     * @var Api
     */
    private Api $api;


    /**
     * @var Turnitin\File\Mapper
     */
    private Turnitin\File\Mapper $turnitinMapper;


    /**
     * @var Turnitin\Group\Mapper
     */
    private Turnitin\Group\Mapper $turnitinGroupMapper;


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
     * @var \Schoolbox\Learning\Assessment\DataModel\MapperProxy
     */
    private \Schoolbox\Learning\Assessment\DataModel\MapperProxy $assessmentMapperProxy;


    /**
     * Constructor.
     *
     * @param string                $instanceIdentifier
     * @param Api                   $api
     * @param Turnitin\File\Mapper  $turnitinMapper
     * @param Turnitin\Group\Mapper $turnitinGroupMapper
     * @param UserMapper            $userMapper
     * @param SubmissionBoxMapper   $boxMapper
     * @param SingleFile\Mapper     $ssfMapper
     * @param \Schoolbox\Learning\Assessment\DataModel\MapperProxy $assessmentMapperProxy
     * @param LoggerInterface|null  $log
     */
    public function __construct(
        string $instanceIdentifier,
        Api $api,
        Turnitin\File\Mapper $turnitinMapper,
        Turnitin\Group\Mapper $turnitinGroupMapper,
        UserMapper $userMapper,
        SubmissionBoxMapper $boxMapper,
        SingleFile\Mapper $ssfMapper,
        \Schoolbox\Learning\Assessment\DataModel\MapperProxy $assessmentMapperProxy,
        ?LoggerInterface $log = null
    ) {
        $this->instanceIdentifier = $instanceIdentifier;
        $this->api = $api;
        $this->turnitinMapper = $turnitinMapper;
        $this->turnitinGroupMapper = $turnitinGroupMapper;
        $this->userMapper = $userMapper;
        $this->boxMapper = $boxMapper;
        $this->ssfMapper = $ssfMapper;
        $this->assessmentMapperProxy = $assessmentMapperProxy;
        $this->log = $log;
    }


    /**
     * At this point we have records in turnitin_report that are currently
     * in the PENDING state; these require File processing, which involves
     * creating the Submission, Group and File contents in Turnitin
     *
     * When that's done a secondary process generates the Report info
     *
     * @param Turnitin\File\Model $model
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    public function process(Turnitin\File\Model $model): void
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
                    'Report %s: Student Submission File %s not found; setting status [%s]',
                    $model->getId(),
                    $model->getStudentSubmissionFileId(),
                    Turnitin\File\Model::STATUS_ERROR
                ),
                Turnitin\File\Model::STATUS_ERROR
            );

            return;
        }

        /* It also has to be a valid file for upload
         *
         * Note: The controller usage of this should have denied invalid mimes
         * but who knows how a record might actually get populated beyond our
         * own intended controller usage (ie. 3rd party imports/syncs)
         */
        if (!(new ValidFile())->isSatisfiedBy($ssf)) {
            $model
                ->setErrorCode('Invalid Filetype')
                ->setErrorMessage('Student Submission File has an invalid mime type');

            $this->changeState(
                $model,
                sprintf(
                    'Report %s: Student Submission File %s %s has an invalid mime type; setting status [%s]',
                    $model->getId(),
                    $ssf->getId(),
                    $ssf->getBasename(),
                    Turnitin\File\Model::STATUS_ERROR
                ),
                Turnitin\File\Model::STATUS_ERROR
            );

            return;
        }

        /* Commencing Purification.
         */
        try {
            /* Find a (submission_box_id, group_id) tuple from turnitin_group
             * If it's there, use that identifier and keep going
             *
             * $group is an array consisting of the following fields which we
             * can dynamically re-construct here without the authentic payload:
             *
             * [
             *      id: $submission_box_id,
             *      name: $submission_box_name
             *      type: self::GROUP_TYPE
             * ]
             *
             * If the group doesn't exist, create one and continue with that
             * You can do this if you check for the tuple in turnitin_group;
             * if it's not there, make a row and continue; otherwise just
             * use the fabricated array described above (turnitin doesn't care
             * if you repeatedly a group, it just updates it)
             */

            /* The box has to exist (i.e. not deleted)
             */
            try {
                $box = $this->boxMapper->findById($ssf->getSubmission()->getSubmissionBox()->getId());
            } catch (\Schoolbox\Mapper\Exception $e) {
                $this->turnitinMapper->save($model
                    ->setStatus(Turnitin\File\Model::STATUS_ERROR)
                    ->setErrorCode('Submission Box Not Found')
                    ->setErrorMessage('Submission Box not found; maybe its record was deleted'));

                return;
            }

            /* Create a Turnitin Group
             */
            $group = $this->createGroup($model, $box, $ssf);

            /* Create a Turnitin Submission
             */
            if (null !== $group) {
                $submission = $this->createSubmission($model, $box, $ssf, $group);

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
                                'Report %s: Submission File Contents for submission file %s %s sent to Turnitin for processing; setting status [%s]',
                                $model->getId(),
                                $ssf->getId(),
                                $ssf->getBasename(),
                                Turnitin\File\Model::STATUS_TRANSMIT_LOADED
                            ),
                            Turnitin\File\Model::STATUS_TRANSMIT_LOADED
                        );
                    }

                    /* There are no further steps we need to conduct here
                     *
                     * Normally turnitin uses a webhook to tell us when it's finished
                     * but we can't trust that will always work
                     */
                }
            }
        } catch (Turnitin\Exception\Conflict $e) {
            /* A non-recoverable situation - you're trying to upload data
             * into a place which is already associated with data
             */
            $errorMessage = sprintf(
                '%s - last status was [%s]',
                $e->getMessage(),
                $model->getStatus()
            );

            $this->logWarning($errorMessage);

            $this->turnitinMapper->save($model
                ->setErrorCode((string)$e->getCode())
                ->setErrorMessage($errorMessage)
                ->setStatus(Turnitin\File\Model::STATUS_ERROR));
        } catch (Turnitin\Exception\ConnectException $e) {
            /* Connection timed out, but could potentially be recovered by
             * cron job, set it to pending whilst it tried to self-recover.
             */
            $this->logWarning($e->getMessage());

            $this->turnitinMapper->save(
                $model
                    ->setErrorCode((string)$e->getCode())
                    ->setErrorMessage($e->getMessage())
                    ->setStatus(Turnitin\File\Model::STATUS_TRANSMIT_PENDING)
            );
        } catch (Turnitin\Exception\Exception $e) {
            /* Potentially recoverable by running transmit forcibly
             * which is what the crontab does anyway
             */
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
     * @param Turnitin\File\Model $model
     * @param string              $message
     * @param string              $status
     * @throws \PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function changeState(Turnitin\File\Model $model, string $message, string $status): void
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
     * Create a Turnitin Group, which is synonymous with our Submission Boxes
     *
     * Apparently you just shove in an identifier, turnitin understands that...
     * ...which seems a little odd, but ok. It puts the onus on us (haha) to
     * manage these. Since our Box Ids are guaranteed to be unique, in it goes
     *
     * @param Turnitin\File\Model $model
     * @param SubmissionBox       $box
     * @param SingleFile\Model    $ssf
     * @return array|null
     * @throws BadEndpoint
     * @throws Conflict
     * @throws Credentials
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createGroup(Turnitin\File\Model $model, SubmissionBox $box, SingleFile\Model $ssf): ?array
    {
        /* Log the state change
         */
        $this->changeState(
            $model,
            sprintf(
                'Report %s: setting up turnitin Group for submission file %s %s; setting status [%s]',
                $model->getId(),
                $ssf->getId(),
                $ssf->getBasename(),
                Turnitin\File\Model::STATUS_TRANSMIT_GROUP
            ),
            Turnitin\File\Model::STATUS_TRANSMIT_GROUP
        );

        /* See if there's a Turnitin Group already associated with the submission box
         * If it already exists, just get its payload instead
         */
        try {
            $group = $this->turnitinGroupMapper->findOneByColumn('submission_box_id', $box->getId(), ['cache' => false]);
        } catch (\Schoolbox\Mapper\Exception $e) {
            /* Create a group if it doesn't already exist
             */
            $this->logDebug(sprintf(
                'Turnitin Group does not exist for submission box %s; creating a new one',
                $box->getId()
            ));

            $group = $this->turnitinGroupMapper->createModel()
                ->setSubmissionBoxId($box->getId())
                ->setStatus(Turnitin\Group\Model::STATUS_TRANSMIT_PENDING);
        }

        /* Record exists, but doesn't have an identifier yet
         * This occurs when the transmitter runs before the group processor
         * It might happen if you create submissions *then* turn on Similarity Check
         */
        if ('' === (string)$group->getGroupId()) {
            $groupId = (new Turnitin\Group\IdCodec())->encode($box->getId(), $this->instanceIdentifier);

            /* Fish out due dates from the official Assessment table
             */
            $assessment = $this->assessmentMapperProxy->findById($box->getAssessmentId());

            $newGroup = $this->api->retrieve('PUT', "/api/v1/groups/{$groupId}", [
                'name' => $box->getName(),
                'type' => Turnitin\Group\Process\Group::GROUP_TYPE,
                'due_date' => $assessment->getDueAt()->getZuluTime(),
                'report_generation' => self::REPORT_GENERATION_COLLUSION,
            ]);

            $group->setGroupId($newGroup['id']);

            $this->turnitinGroupMapper->save($group);

            return $newGroup;
        }

        /* Find an existing one
         * 404 Not Found occurs when Group no longer exists in turnitin, but should have
         */
        $this->logDebug(sprintf(
            'Turnitin Group already exists for submission box %s; reusing it',
            $box->getId()
        ));

        return $this->api->retrieve('GET', "/api/v1/groups/{$group->getGroupId()}", []);
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
     * @param Turnitin\File\Model $model
     * @param SubmissionBox       $box
     * @param SingleFile\Model    $ssf
     * @param array               $group
     * @return array|null
     * @throws BadEndpoint
     * @throws Conflict
     * @throws Credentials
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function createSubmission(Turnitin\File\Model $model, SubmissionBox $box, SingleFile\Model $ssf, array $group): ?array
    {

        /* Log the state change
         */
        $this->changeState(
            $model,
            sprintf(
                'Report %s: setting up turnitin Submission for submission file %s %s; setting status [%s]',
                $model->getId(),
                $ssf->getId(),
                $ssf->getBasename(),
                Turnitin\File\Model::STATUS_TRANSMIT_SUBMISSION
            ),
            Turnitin\File\Model::STATUS_TRANSMIT_SUBMISSION
        );

        /* TODO Maybe should update SingleFile\Mapper to hydrate Owners always, but that's
         *  a pretty sweeping assumption that we needed them everywhere else...
         *
         * The student submission file owner has to exist (i.e. not deleted)
         */
        try {
            $owner = $this->userMapper->findById($ssf->getOwner()->getId());
        } catch (Deleted $e) {
            /** @var User $owner */
            $owner = $e->getDeletedObject();
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->turnitinMapper->save($model
                ->setStatus(Turnitin\File\Model::STATUS_ERROR)
                ->setErrorCode('Student Submission File Owner Not Found')
                ->setErrorMessage('Student Submission File owner not found'));

            return null;
        }

        /* Try to get the info for Group Context
         *
         * Owners aren't hydrated out of the boxMapper but I don't think they were supposed to
         * be hydrated like that; something to do with swamp
         */
        try {
            $boxOwner = $this->userMapper->findById($box->getOwner()->getId());
        } catch (Deleted $e) {
            /** @var User $boxOwner */
            $boxOwner = $e->getDeletedObject();
        } catch (\Schoolbox\Mapper\Exception $e) {
            $this->turnitinMapper->save($model
                ->setStatus(Turnitin\File\Model::STATUS_ERROR)
                ->setErrorCode('Submission Box Owner Not Found')
                ->setErrorMessage('Submission Box owner not found'));

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
            'accepted_timestamp' => \Alaress_Date_Format::now()->getZuluTime(),
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
            'title' => $ssf->getFilename(),
            'owner_default_permission_set' => 'LEARNER',
            /* Historical documents get processed with extract_text_only true
             * (it's false by default)
             */
            'extract_text_only' => $model->getHistorical(),
            'metadata' => [
                'owners' => [
                    [
                        'id' => $owner->getId(),
                        'given_name' => $owner->getFirstname(),
                        'family_name' => $owner->getLastname(),
                        'email' => $owner->getEmail()
                    ]
                ],
                'group' => $group,
                'group_context' => [
                    'id' => $box->getAssessmentModel()->getAssessmentContext()->getFolder()->getId(),
                    'name' => $box->getAssessmentModel()->getAssessmentContext()->getFolder()->getNameWithCodes(),
                    'owners' => [
                        [
                            'id' => $boxOwner->getId(),
                            'given_name' => $boxOwner->getFirstname(),
                            'family_name' => $boxOwner->getLastname(),
                            'email' => $boxOwner->getEmail()
                        ]
                    ]
                ]
            ]
        ];

        return $this->api->retrieve('POST', "/api/v1/submissions", $payload);
    }


    /**
     * Upload a file's contents to turnitin into the given turnitin submission identifier
     *
     * @param Turnitin\File\Model $model
     * @param SingleFile\Model    $ssf
     * @param array               $submission
     * @return array|null
     * @throws BadEndpoint
     * @throws Conflict
     * @throws Credentials
     * @throws \DomainException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \JsonException
     * @throws \PDOException
     * @throws \RuntimeException
     * @throws \Schoolbox\Mapper\Exception
     * @throws \UnexpectedValueException
     */
    private function uploadFile(Turnitin\File\Model $model, SingleFile\Model $ssf, array $submission): ?array
    {
        /* Log the state change
         */
        $this->changeState(
            $model,
            sprintf(
                'Report %s: setting up turnitin Submission File Contents for submission file %s %s; setting status [%s]',
                $model->getId(),
                $ssf->getId(),
                $ssf->getBasename(),
                Turnitin\File\Model::STATUS_TRANSMIT_UPLOADING
            ),
            Turnitin\File\Model::STATUS_TRANSMIT_UPLOADING
        );

        // TODO inject filesystem proxy
        $payload = [
            'filename' => $ssf->getBasename(),
            'contents' => StorageMapper::getInstance()->getStorageFilesystemProxy()->read($ssf)
        ];

        return $this->api->retrieve('UPLOAD', "/api/v1/submissions/{$submission['id']}/original", $payload);
    }
}
