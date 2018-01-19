<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork;

use Alaress_Date_Format;
use Aura\Di\Exception\ServiceNotFound;
use Aura\Router\Exception\RouteNotFound;
use DomainException;
use DueWork_ErrorMapper;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DueWork\Response\ValidSelfReflection;
use Schoolbox\Learning\Assessment\WorkType\Mapper as WorkTypeMapper;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Learning\Assessment\DueWork\Response\Model as Submission;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantMapper;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile;
use Schoolbox\Learning\Assessment\DueWork\Response\MapperProxy as DueWorkResponseMapper;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\Mapper as PlagscanMapper;
use Schoolbox\Learning\Assessment\Timeline\Full as FullTimeline;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Resource\Storage\IStorage;
use Schoolbox\Resource\Storage\Zip\Zip;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Assessment\Rubric\Descriptor as RubricDescriptor;
use Schoolbox\Assessment\Term;
use Schoolbox\Application\Exception as ApplicationException;
use Schoolbox\Learning\InlineGrader\Kami;

class Controller extends Assessment\Controller
{
    /** @var Rubric\ResultMapper */
    private $resultSetMapper;

    /** @var Rubric\RubricMapper */
    private $rubricMapper;

    /** @var DueWorkResponseMapper */
    private $dueWorkResponseMapper;

    /** @var ParticipantMapper */
    private $participantMapper;

    /** @var SingleFile\Mapper */
    private $submissionFileMapper;

    /** @var Kami\Connector  */
    private $kamiConnector;

    /** @var Kami\Mapper  */
    private $kamiMapper;

    /**
     * @param UserMapper                    $userMapper
     * @param Error\Mapper                  $errorMapper
     * @param MapperProxy                   $assessmentMapper
     * @param WorkTypeMapper                $workTypeMapper
     * @param Access                        $access
     * @param Transactor                    $transactor
     * @param Assessment\Breadcrumb\Factory $bakery
     * @param Rubric\RubricMapper           $rubricMapper
     * @param Rubric\ResultMapper           $resultSetMapper
     * @param DueWorkResponseMapper         $dueWorkResponseMapper
     * @param ParticipantMapper             $participantMapper
     * @param Term\Mapper                   $termMapper
     * @param SingleFile\Mapper             $submissionFileMapper
     * @param Kami\Connector                $kamiConnector
     * @param Kami\Mapper                   $kamiMapper
     * @param Participant\DataSource        $participantDataSource
     */
    public function __construct(
        UserMapper $userMapper,
        Error\Mapper $errorMapper,
        MapperProxy $assessmentMapper,
        WorkTypeMapper $workTypeMapper,
        Access $access,
        Transactor $transactor,
        Assessment\Breadcrumb\Factory $bakery,
        Rubric\RubricMapper $rubricMapper,
        Rubric\ResultMapper $resultSetMapper,
        DueWorkResponseMapper $dueWorkResponseMapper,
        ParticipantMapper $participantMapper,
        Term\Mapper $termMapper,
        SingleFile\Mapper $submissionFileMapper,
        Kami\Connector $kamiConnector,
        Kami\Mapper $kamiMapper,
        Participant\DataSource $participantDataSource
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $workTypeMapper, $access, $transactor, $bakery, $termMapper, $participantDataSource);
        $this->resultSetMapper       = $resultSetMapper;
        $this->rubricMapper          = $rubricMapper;
        $this->dueWorkResponseMapper = $dueWorkResponseMapper;
        $this->participantMapper     = $participantMapper;
        $this->submissionFileMapper  = $submissionFileMapper;
        $this->kamiConnector         = $kamiConnector;
        $this->kamiMapper            = $kamiMapper;
    }

    /**
     * Determine if there are any submission files available for download on the assessment
     * @param IAssessable $assessment
     * @return bool
     */
    private function hasDownloadableSubmissionFiles($assessment)
    {
        // Grab all participants in the assessment

        /** @var Participant $participant */
        foreach ($assessment->getAllParticipants() as $participant) {
            // Iterate all their responses

            /** @var Submission $response */
            foreach ($participant->getAllResponses() as $response) {
                // If the response is a submission...

                if ($response instanceof Submission && !$response->isDraft()) {
                    if ($response->getAllFiles()->count() > 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * This gives you a summary of progress made within an Assessment
     * It is the consolidation of pages previously known as "quickmark" and "quiz review"
     *
     * This place is Staff Only
     *
     * @api GET /learning/assessment/:assessmentId/status
     * @param IAssessable $assessment
     * @throws \InvalidArgumentException If assessment due date is null.
     */
    public function getStatus(IAssessable $assessment)
    {
        // Only display download link if there are actually files to download

        $twigData = [
            'errors'         => DueWork_ErrorMapper::getInstance()->listDisplayValues(),
            'type'           => Assessment\AssessmentType::objectToString($assessment),
            'markSchemeName' => $this->deriveSchemeName($assessment->getMarkScheme()),
            'breadcrumbs'    => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([$assessment->getTitle(), '']),
            'assessment'     => $assessment,
            'download'       => $this->hasDownloadableSubmissionFiles($assessment),
            'rubricMarks'    => Rubric\MarkMapper::getInstance()->findMarkedByAssessment($assessment->getId()),
            'interactionLocked' => (new Assessment\LockedChecker($this->getTermMapper()))->isLockedForInteractions($assessment),
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/dueWork/status.twig",
                $twigData, "Status of {$assessment->getTitle()}", 'submission'
            )
        );
    }


    /**
     * Display appropriate landing page based on role
     *
     * @api      GET /learning/assessments/:assessmentId/:userId
     * @level    165
     *
     * @param IAssessable $assessment
     * @throws ServiceNotFound
     * @internal param Participant $participant
     */
    public function getPreview(IAssessable $assessment)
    {

        if($assessment->getUseRubric()){
            $assessment->setRubric($this->rubricMapper->findByDueWork($assessment));
        }

        /* Find all the records spawned by $participant
         *
         * Order them by submitted time to find the most recent, and use that
         */

        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),

            /* Rubrics data
             */
            'rubricContext' => (new RubricContext($this->rubricMapper))->generateBasic($assessment, $this->getUser()),
            'assessment'   => $assessment,

        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/dueWork/summary/preview.twig",
                $twigData, "{$assessment->getTitle()}", 'submission'
            )
        );
    }


    /**
     * Display appropriate landing page based on role
     *
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @level 165
     *
     * @param Participant $participant
     * @throws NotFound
     */
    public function getSummary(Participant $participant)
    {
        /** @var Model $assessment */
        $assessment = $participant->getAssessable();
        $student    = $participant->getUser();

        if($assessment->getUseRubric()){
            $assessment->setRubric($this->rubricMapper->findByDueWork($assessment));
        }


        /* Find all the records spawned by $participant
         *
         * Order them by submitted time to find the most recent, and use that
         */

        /** @var SubmissionFile $submission */
        $allResponses = $participant->getAllResponses();

        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),
            'participant' => $participant,
            'timeline'    => (new FullTimeline($participant))->getForUser($this->getUser()),

            /* Plagscan status
             */
            'plagscanEnabled' => PlagscanMapper::getInstance()->isEnabled($this->getUser()),

            /* Rubrics data
             */
            'rubricContext' => (new RubricContext($this->rubricMapper))->generate($assessment, $student, $this->getUser(), $this->getAccess()),
        ];

        try {
            $assessment->assertAcceptSubmission($allResponses);
            $twigData['submissionsBlocked'] = false;
        } catch (DomainException $e) {
            $twigData['submissionsBlocked'] = $this->getErrorMapper()->generateException($e->getCode())->getMessage();
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/dueWork/summary/student.twig",
                $twigData, "{$assessment->getTitle()} - {$student->getFullName()}", 'submission'
            )
        );
    }

    /**
     * Recursive directory delete
     * @param $dir
     */
    private function deleteDirectory($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") {
                        $this->deleteDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    /**
     * Sanitize a string for use in filename
     *
     * @param string $string
     * @return string
     */
    private function sanitizeForFilename($string) {
        return preg_replace("/[^A-Za-z0-9. ]/", '_', $string);
    }

    /**
     * Download all student submissions for a given assessable
     * @api   GET /learning/assessments/:assessmentId/submissions/download
     * @level 165
     * @param IAssessable $assessment
     */
    public function downloadSubmissions(IAssessable $assessment)
    {
        // Retrieve all participants

        $participants = $assessment->getAllParticipants();

        // Cache users

        /** @var User[] $users */
        $users = [];

        // Create a zip file to hold the downloaded submissions

        $titleSanitized = $this->sanitizeForFilename($assessment->getTitle());
        $dateNow = \Alaress_Date_Format::now()->getUsingFormat('Ymd-His');

        $zip = new Zip("{$titleSanitized}-{$dateNow}.zip");

        // Iterate participants and get their responses

        /** @var Participant $participant */
        foreach ($participants as $participant)
        {
            // Get the users details and generate an appropriate folder name with the date/time stamp of the submission

            if (!isset($users[$participant->getUser()->getId()])) {
                $users[$participant->getUser()->getId()] = $this->getUserMapper()->findByIdWithFallback($participant->getUser()->getId());
            }

            $responses = $participant->getAllResponses();

            // Iterate all their responses

            /** @var Submission $response */
            foreach ($responses as $response)
            {
                // If the response is a submission...

                if ($response instanceof Submission && !$response->isDraft())
                {
                    $lastNameSanitized = $this->sanitizeForFilename($users[$participant->getUser()->getId()]->getLastname());
                    $firstNameSanitized = $this->sanitizeForFilename($users[$participant->getUser()->getId()]->getFirstname());

                    $directory = sprintf("%s_%s_%s-%s",
                        $lastNameSanitized,
                        $firstNameSanitized,
                        $users[$participant->getUser()->getId()]->getId(),
                        $response->getSubmittedAt()->getUsingFormat('Ymd-His')
                    );

                    // ...iterate all files in that submission and add them to the ZIP archive

                    $files = $response->getAllFiles();

                    /** @var SubmissionFile $file */
                    foreach ($files as $file)
                    {
                        if ($file->exists()) {
                            $filenameSanitized = $this->sanitizeForFilename($file->getBasename());
                            $zipFilename = sprintf("%s/%s", $directory, $filenameSanitized);
                            $zip->addStream($file->readStream(), $zipFilename);
                        }
                    }
                }
            }
        }

        // Flush the ZIP file to before deleting the temporary files- otherwise we end up with an empty file

        $zip->persist();

        // Download the sucker

        $zip->download();
    }

    /**
     * Shows a rubric overview
     *
     * @api   GET /learning/assessments/:assessmentId/overview
     * @level 165
     * @param IAssessable|Model $assessment
     */
    public function getOverview(IAssessable $assessment)
    {
        $data = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())
                ->add([
                    $assessment->getTitle(),
                    $this->getRouter()->generate('learningAssessment.getStatus', [ 'assessmentId' => $assessment->getId() ]) ])
                ->add(['Rubric Report','']),
            'dueWork'     => $assessment,
        ];

        if($assessment->getUseRubric()){
            $rubric = $this->rubricMapper->findByDueWork($assessment);
            $assessment = $this->participantMapper->loadParticipationIntoAssessment($assessment);

            $data = array_merge($data, [
                'headers'  => $this->getRubricHeaders($rubric),
                'marks'    => $this->getMarks($rubric, $assessment->getAllParticipants()),
            ]);
        }

        $this->getResponse()->content->set([
            'title'       => 'Rubric Report',
            'template'    => '@learningAssessment/dueWork/overview.twig',
            'data'        => $data
        ]);
    }

    /**
     * Downloads a rubric overview
     *
     * @api   GET /learning/assessments/:assessmentId/overview/export?format=csv
     * @level 165
     * @param IAssessable|Model $assessment
     */
    public function downloadOverview(IAssessable $assessment)
    {
        if(!$assessment->getUseRubric()) {
            throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_PAGE_NOT_FOUND);
        }

        $rubric = $this->rubricMapper->findByDueWork($assessment);
        $assessment = $this->participantMapper->loadParticipationIntoAssessment($assessment);

        $basename = (new \Url($assessment->getTitle()))->getSlug();
        $content = $this->array2csv($this->collateResultSquare($rubric, $assessment->getAllParticipants()));
        $headers = $this->getResponse()->headers;

        $headers->set('Pragma', ' ');
        $headers->set('Cache-Control', ' ');
        $headers->set('Content-Type', 'text/csv');
        $headers->set('Content-Length', strlen($content));

        $headers->set('Content-Disposition', "attachment; filename=\"$basename-results.csv\"");

        $this->getResponse()->content->set([
            'format' => 'file',
            'data'   => $content
        ]);
    }

    /**
     * @param Rubric\Rubric $rubric
     * @return string[][] Indexed by group.name then criterion.id
     */
    protected function getRubricHeaders(Rubric\Rubric $rubric)
    {
        $headers = [];

        foreach ($rubric->getTotalPerGroup() as $group) {
            foreach ($group->getAllCriteria() as $criterion) {
                $headers[$group->getName()][$criterion->getId()] = $criterion->getName();
            }
        }
        return $headers;
    }

    /**
     * @param Rubric\Rubric $rubric
     * @param \ICollection|Participant[] $participants
     * @return RubricDescriptor[][] Grouped in sets indexed by user.id
     */
    protected function getMarks(Rubric\Rubric $rubric, \ICollection $participants)
    {
        $marks = [];

        foreach ($participants as $participant) {
            $studentId = $participant->getUser()->getId();

            // this will modify the "selection" of descriptors in the grid
            (new Rubric\GridGenerator($this->rubricMapper, $rubric, $participant->getUser()))->applyStudentMark(true);

            foreach ($rubric->getSumPerGroup() as $group) {
                foreach ($group->getAllCriteria() as $criterion) {
                    $marks[$studentId][$criterion->getId()] = $criterion->getSelectedDescriptors()->first();
                }
            }
        }
        return $marks;
    }

    /**
     * @param Rubric\Rubric $rubric
     * @param Collection|Participant[] $participants
     * @return array
     */
    protected function collateResultSquare(Rubric\Rubric $rubric, Collection $participants)
    {
        // array inside an array that's *nearly* empty...
        $data = [['Name', 'External ID']];

        // put header row in array
        foreach ($this->getRubricHeaders($rubric) as $grouping => $criteria) {
            foreach ($criteria as $id => $criterion) {
                $data[0][] = "$grouping - $criterion";
                $data[0][] = '';
            }
        }
        $data[0][] = 'Total';

        /**
         * Collate by id, each sub-collection will contain only 1 User element
         *
         * @var Collection[] $participantsGrouped
         */
        $participantsGrouped = $participants->groupByCallback(function(Participant $participant){
            return $participant->getUser()->getId();
        })->getAll();

        foreach ($this->getMarks($rubric, $participants) as $studentId => $marks) {
            /** @var Participant $participant */
            $participant = $participantsGrouped[$studentId]->first();
            $studentName = $participant->getUser()->formatName('{lastname}, {firstname}');

            $row = [$studentName, $participant->getUser()->getExternalId()];
            $sum = '';

            if (count($marks)) {
                foreach ($marks as $mark) {
                    if ($mark instanceof RubricDescriptor) {
                        $row[] = $mark->getText();
                        $row[] = $mark->getValue();
                        $sum += $mark->getValue();
                    } else {
                        $row[] = $mark;
                        $row[] = '';
                    }
                }
                $row[] = $sum;

            } else {
                $row = array_merge($row, array_pad([], count($data[0]) - 1, ''));
            }
            $data[] = $row;
        }

        return $data;
    }

    /**
     * The array data should obviously be in a CSV-compatible format (ie. rectangle)
     *
     * @param array $array
     * @return string
     */
    protected function array2csv(array $array)
    {
        $fp = fopen('php://temp', 'w');
        foreach ($array as $row) {
            fputcsv($fp, $row);
        }
        rewind($fp);
        $contents = stream_get_contents($fp);
        fclose($fp);
        return $contents;
    }

    /**
     * Displays a form enabling a student to create Submission for a Due Work
     *
     * @api   GET /learning/assessment/:assessmentId/:participantId/submit
     * @level 165
     * @param Participant $participant
     * @throws ServiceNotFound If unable to locate HTML web Output in DI
     */
    public function getCreateResponseForm(Participant $participant)
    {
        /** @var Model $assessment */
        $assessment = $participant->getAssessable();
        $student    = $participant->getUser();

        /* Find all the records spawned by $participant
         *
         * Order them by submitted time to find the most recent, and use that
         */

        /** @var SubmissionFile $submission */
        $allResponses = $participant->getAllResponses();

        /* Otherwise continue
         */
        $latestResponse = null;
        $editorContents = '';
        $dangerZone = null;

        $attachZoneFactory = new Assessment\Attachzone\Factory();

        /* Assume the most recent record is the latest submission
         */
        if ($allResponses->count()) {
            /** @var Submission $latestResponse */
            $latestResponse = $participant->findMostRecentResponse();

            /* If the latest response is a draft, collect all its contents for editing
             */
            if ($latestResponse->isDraft()) {

                /* Files attached to the draft submission
                 * Attach files if any exist
                 */
                $dangerZone = $attachZoneFactory->buildForDueWorkResponse($latestResponse);

                /* Load in the wizzywig instead of a file attachment,
                 * if the $file uses the wizzywig editor
                 */
                foreach ($latestResponse->getAllFiles() as $file) {
                    if ($file->getEditableInline()) {
                        $editorContents = $file->read();
                    }
                }
            }
            else {

                /* Otherwise, spawn an entirely new draft
                 * I would not expect it to recycle files from my previous Submitted submission
                 */
                $latestResponse = null;
                $dangerZone = $attachZoneFactory->buildForDueWorkResponse(new Submission());
            }
        }
        else {

            /* Init a dangerZone that is ready to accept files for
             * the first ever draft
             */
            $dangerZone = $attachZoneFactory->buildForDueWorkResponse(new Submission());
        }

        /* Pump into twig land
         */
        // For some reason I can't use get/setFlash?
        $errorMessage = $this->getSession()->getSegment(static::class)->get('errorMessage');
        $this->getSession()->getSegment(static::class)->set('errorMessage', null);
        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/dueWork/createSubmission.twig', [
            'breadcrumbs'    => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add(['Create Response for ' . $assessment->getTitle(), '']),
            'assessment'     => $assessment,
            'student'        => $student,
            'attachzone'     => $dangerZone ? $dangerZone->getTwigContext() : null,
            'response'       => $latestResponse,
            'editorContents' => $editorContents,
            'policy'         => $this->getConfigLoader()->get('lms_student_policy'),
            'errorMessage'   => $errorMessage,
        ]));
    }


    /**
     * Save a Response, on behalf of a User, for a particular Assessment
     *
     * @api POST /learning/assessments/:assessmentId/:studentId/submit
     * @param Participant $participant
     * @return void
     * @throws RouteNotFound If unable to generate a specified Route
     * @throws \PDOException If unable to save the Response
     * @throws ApplicationException\Exception If not accepting submissions
     * @throws MapperException\Deleted If specified Response is deleted
     * @throws MapperException If unable to create new Response object
     * @throws \LogicException If files are not saved prior to insertion into kami upload queue
     */
    public function postCreateResponse(Participant $participant): void
    {
        $rq = $this->getRequest();
        $rs = $this->getResponse();

        /** @var Model $assessment */
        $assessment = $participant->getAssessable();

        /* Set in javascript when automatically saving a draft
         * Careful the negation
         */
        $isFinal = !$rq->post['saveDraft'];
        $body    = $rq->post['body'];

        if ($rq->post['responseId']) {
            // Load an existing response to update
            $response = $this->getDueWorkResponseMapper()->findById($rq->post['responseId']);
        } else {
            // Create a new response
            $response = $this->dueWorkResponseMapper->createModel()
                ->setSubmittedBy($this->getUser())
                ->setSubmittedOnBehalfOf($participant->getUser())
                ->setCreatedAt(Alaress_Date_Format::now())
                ->setUpdatedAt(Alaress_Date_Format::now());
        }
        $response->setAssessable($assessment);

        // Replace files with what was actually submitted
        $response->setAllFiles(new Collection());

        /* Add the online submission to this response's list of files
         * only if there is content in it.
         */
        if (strlen(trim(strip_tags($body)))) {
            $response->attachOnlineSubmissionContent($body);
        }

        // Make one of those factories that builds storages from hashes
        $factory = new CollectorFactory();
        if (!empty($rq->post['attachment'])) {

            // Validate attachment list can actually be built
            try {
                /** @var IStorage[] $attachments */
                $attachments = $factory->build($rq->post['attachment'])->getAll();

            } catch (\InvalidArgumentException $e) {
                $errorMessage = 'One or more files were not able to be attached';
                if ($this->getRequest()->isXhr()) {
                    $result['success'] = false;
                    $result['message'] = $errorMessage;

                    $rs->status->setCode(403);
                    $rs->content->set($this->buildJsonOutput($result));
                } else {
                    // Show some error message?
                    $this->getSession()->getSegment(static::class)->set('errorMessage', $errorMessage);
                    $rs->redirect->to(
                        $this->getRouter()->generate(
                            'learningAssessment.getCreateResponseForm',
                            [
                                'assessmentId' => $assessment->getId(),
                                'studentId'    => $participant->getUser()->getId(),
                            ]
                        )
                    );
                }
                return;
            }

            foreach ($attachments as $attachment) {

                /* Don't stack the online-submission.html file here, it should never
                 * appear in the attached files list at all (it should always be
                 * loaded into the wizzywig)
                 */
                $attachedFile = $this->submissionFileMapper->createModel()
                    ->setStorage($attachment)
                    ->setFilename($attachment->getFilename())
                    ->setEditableInline(false);

                /* Stack it up
                 */
                $response->getAllFiles()->add($attachedFile);
            }
        }

        /* If we're saving the final version of the submission (ie. not a draft) ram the current datetime in
         * Only need to check selfReflectable. selfReflectable is a prerequisite for selfAssessment, but the
         * behaviour is the same in both selfReflectable and selfAssessment scenarios for setting up a new
         * draft submission.
         */
        $selfReflectable = (new ValidSelfReflection())->isSatisfiedBy($this->getUser(), $participant);

        if ($isFinal) {

            /* submitted_at is determined after the next step when creating a self-assessed or self-reflected
             * response. Otherwise, proceed normally
             */
            if (!$selfReflectable) {
                $response->setSubmittedAt(Alaress_Date_Format::now());
            }
        }

        /* Do the actual saving
         */
        $this->getDueWorkResponseMapper()->save($response);

        // If not a draft, check if any files should be uploaded to Kami
        if ($isFinal) {
            $this->enqueueKamiUploads($response->getAllFiles());
        }


        /* Save stubble record and send each file in the Submission to Plagscan
         * if the Submission was saved successfully
         *
         * Only plagscan when actually submitting final
         * Only plagscan non-online-submission files (for some reason, plag won't do html files)
         */
        $plagscanMapper = PlagscanMapper::getInstance();

        /* Plagscans should only spawn upon saving a Submitted IResponse instance
         * This screen is shared between self-reflection and non-self-reflection steps
         * Self-reflection's plagscan stubs saving .......happens elsewhere
         */
        if ($isFinal && !$selfReflectable && $response->getId()) {
            foreach ($response->getAllFiles() as $currentFile) {
                if (!$currentFile->getEditableInline()) {
                    $plagscanMapper->saveNewPlagscanStub($currentFile);
                }
            }
        }

        /* Asynchronously shove it into plagscan so we don't make the user wait ages
         * for it to read 1 byte at a time >_<
         */
        $this->getResponse()->content->set([
            'postOutput' => [
                function() use ($plagscanMapper, $isFinal, $selfReflectable, $response) {
                    if ($isFinal && !$selfReflectable && $response->getId()) {
                        foreach ($response->getAllFiles() as $currentFile) {
                            $filename = $plagscanMapper->plagscanFilename($currentFile);

                            $plagscanMapper->saveNewPlagscan($currentFile, $filename);
                        }
                    }
                }
            ],
        ]);

        /* Saving drafts from the wizzywig
         */
        if ($rq->isXhr()) {
            $result['success'] = true;
            $result['message'] = 'OK';
            $result['id']      = $response->getId();

            $rs->status->setCode(200);
            $rs->content->set($this->buildJsonOutput($result));

        } else {

            /* Go to the next step of Self-Assessment/Self-Reflection if appropriate
             */
            if ($selfReflectable) {
                $rs->redirect->to(
                    $this->getRouter()->generate(
                        'learningDueWorkResponse.getParticularResponseForSelfReflection',
                        [
                            'assessmentId' => $assessment->getId(),
                            'studentId'    => $participant->getUser()->getId(),
                            'responseId'   => $response->getId()
                        ]
                    )
                );
            }
            else {
                // redirect after saving to Due Work Summary For Particular Student
                $rs->redirect->to(
                    $this->getRouter()->generate(
                        'learningAssessment.getSummary',
                        [
                            'assessmentId' => $assessment->getId(),
                            'studentId'    => $participant->getUser()->getId(),
                        ]
                    )
                );
            }
        }
    }


    /**
     * Acknowledge receipt of (potentially offline) submission from dueWork status page
     *
     * Creates an empty SOBO. Needs a different route to postCreateResponse()
     * to cater for different auth rules.
     *
     * @param Participant $participant
     * @api   POST /learning/assessments/:assessmentId/:studentId/received
     * @level 17.0
     */
    public function postStatusAcknowledge(Participant $participant)
    {
        $assessment = $participant->getAssessable();
        try {
            $response = $this->persistEmptySobo($participant);
            $data = [
                'success'       => true,
                'id'            => $response->getId(),
                'submittedLate' => $assessment->isOverdue() || $assessment->isPastClose(),
            ];
            $code = 200;
        } catch (\PDOException $e) {
            $data = [
                'success' => false,
                'message' => $e->getMessage(),
            ];
            $code = 403;
        }
        $this->getResponse()->status->set($code);
        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }


    /**
     * Acknowledge receipt of (potentially offline) submission
     *
     * Creates an empty SOBO. Needs a different route to postCreateResponse()
     * to cater for different auth rules.
     *
     * @param Participant $participant
     * @api   POST /learning/assessments/:assessmentId/:studentId/ack
     * @level 17.0
     */
    public function postPendingAcknowledge(Participant $participant)
    {
        try {
            $this->persistEmptySobo($participant);
            $this->getResponse()->content->set($this->buildUnthemedHtmlOutput('@learning/fragments/toMarkParticipant.twig', [
                'participant' => $participant,
                'assessment'  => $participant->getAssessable(),
            ]));
            $code = 200;
        } catch (\PDOException $e) {
            $this->getResponse()->content->set($this->buildJsonOutput([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
            $code = 403;
        }
        $this->getResponse()->status->set($code);
    }


    /**
     * Common logic for 'dueWork status' and 'pending assessments' pages 'acknowledgement of submission receipt'.
     *
     * @param Participant $participant
     * @return Submission
     * @throws \PDOException If something went wrong in the save process
     */
    private function persistEmptySobo(Participant $participant)
    {
        $assessment = $participant->getAssessable();
        $response   = $this->dueWorkResponseMapper->createModel()
            ->setAssessable($assessment)
            ->setSubmittedBy($this->getUser())
            ->setSubmittedOnBehalfOf($participant->getUser())
            ->setSubmittedAt(Alaress_Date_Format::now())
            ->setCreatedAt(Alaress_Date_Format::now())
            ->setUpdatedAt(Alaress_Date_Format::now());

        $this->dueWorkResponseMapper->save($response);
        $participant->getAllResponses()[$response->getId()] = $response;

        return $response;
    }


    /**
     * @param Model|IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment)
    {
        $zoneFactory = new Assessment\Attachzone\Factory();

        // setup some data...
        $data = [
            'acceptingResponses' => true,
            'attachzone'         => $zoneFactory->buildForAssessment($assessment)->getTwigContext(),
            'allMarkTypes'       => $this->getAllMarkTypes($assessment),
            'allGradeSchemes'    => $this->getAllGradeSchemes(),
            'selfReflectTypes'   => [
                'none'    => Model::SELF_REFLECTION_NONE,
                'reflect' => Model::SELF_REFLECTION_REFLECT_ONLY,
                'assess'  => Model::SELF_REFLECTION_FULL,
            ],
        ];

        // ...append rubric-related data...
        if($assessment->getId()){
            $getParentCriteria = $assessment->getBaseContext() instanceof UnitSpecification;
            $editMode = true;
            $rubric = $this->rubricMapper->findByDueWork($assessment, $getParentCriteria, $editMode);
        } else {
            $rubric = $this->rubricMapper->createForContext($assessment->getBaseContext());
        }
        $assessment->setRubric($rubric);


        $data = array_merge($data, $this->generateRubricTwigData($assessment));

        // ...and scene.
        return $data;
    }


    /**
     * Common logic when generating Rubrics
     *
     * @param Model $dueWork
     * @return array
     */
    private function generateRubricTwigData(Model $dueWork)
    {
        $data = [];

        if (!$dueWork->getRubric() instanceof Rubric\Rubric) {
            throw new \InvalidArgumentException("Rubric unspecified");
        }

        $data['rubric'] = $dueWork->getRubric()->toContext();

        $rubricZone = new Zone('rubric');
        // JS file.type isn't set reliably enough to use text/csv mime filter
        $rubricZone->setFileVar('rb_attach[]')->setAttachPrompt('Import a rubric');//->setMimeFilter('text/csv');
        $data['rubricAttachZone'] = $rubricZone->getTwigContext();

        $resultSets = $this->getResultSetMapper()->findAll();

        $rs = new Collection();

        foreach ($resultSets as $result) {
            if (!$rs[$result->getResultSetId()] instanceof \ICollection) {
                $rs[$result->getResultSetId()] = $this->getResultSetMapper()->createCollection();
            }
            $rs[$result->getResultSetId()][$result->getValue()] = $result;
        }

        $data['rubricResultSets'] = $rs;

        // Do any students have marks for this rubric?
        if($dueWork->getRubric()->getSubmissionBox() instanceof SubmissionBox ) {
            $levelIds = Rubric\LevelMapper::getInstance()->findIdsByColumn('box_id', $dueWork->getRubric()->getSubmissionBox()->getId());
            $descriptorIds = Rubric\DescriptorMapper::getInstance()->findIdsByColumn('level_id', $levelIds);
            $countMarkIds = Rubric\MarkMapper::getInstance()->countByColumnList([
                'descriptor_id' => $descriptorIds,
                'valid'         => true
            ]);

            $data['rubricHasMarks'] = $countMarkIds > 0;
        }
        else {
            $data['rubricHasMarks'] = false;
        }

        return $data;
    }


    /**
     * Update particular fields of an existing IAssessable
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function patchUpdateAssessment(IAssessable $assessment)
    {
        /* TODO: Make this more generalised
         * For now the only utility here is to update Skip Unsubmitted setting
         */
        /** @var \Schoolbox\Learning\Assessment\DueWork\Model $assessment */
        if ((int)$this->getRequest()->post['showSubmittedOnly']) {
            $assessment->showOnlySubmittedStudents();
            $skipUnsubmitted = true;
        }
        else {
            $assessment->showAllSubmissibleStudents();
            $skipUnsubmitted = false;
        }

        /* Persist checkbox setting
         */
        try {
            /** @var SubmissionBox $submitBox */
            $submitBox = SubmissionBoxMapper::getInstance()->findOneByColumn('assessment_id', $assessment->getId());
            $submitBox->setSkipUnsubmitted($skipUnsubmitted);
            SubmissionBoxMapper::getInstance()->save($submitBox);
        }
        catch (MapperException $e) {
            // noop - submission box doesn't exist
        }

        $this->getResponse()->status->setCode(204);
        $this->getResponse()->content->set($this->buildJsonOutput(null));
        return;
    }


    /**
     * Enqueue any eligible files for upload to Kami.
     *
     * Must be called *after* the files are saved to ensure their IDs exist
     *
     * @param Collection|SubmissionFile[] $files
     * @return GenericCollection|Kami\Model[]
     * @throws \PDOException
     * @throws \LogicException If files have not yet been saved
     */
    private function enqueueKamiUploads(Collection $files): GenericCollection
    {
        $kamiModels = $this->kamiMapper->createCollection();

        // Short-circuit if Kami is not enabled
        if (!$this->kamiConnector->isEnabled()) {
            return $kamiModels;
        }

        // Check files have been saved so Kami docs can point to them
        $fileIds = $files->map(function (SubmissionFile $file) {
            if (empty($file->getId())) {
                throw new \LogicException("File '{$file->getBasename()}' has not been saved, cannot process");
            }
            return $file->getId();
        });

        // Check if any files have already been queued for kami upload
        $existingKamiModels = $this->kamiMapper->findByColumn('student_submission_file_id', $fileIds);
        $existingFileIds    = $existingKamiModels->map(function (Kami\Model $kamiModel) {
            return $kamiModel->getStudentSubmissionFile()->getId();
        })->getAll();
        $existingFileIds    = array_combine($existingFileIds, $existingFileIds);

        foreach ($files as $file) {
            if ($file->getEditableInline()) {
                // For now, we ignore the WYSIWYG editor contents.
                continue;
            }
            if (array_key_exists($file->getId(), $existingFileIds)) {
                // Don't re-enqueue if already queued
                continue;
            }
            if (!array_key_exists(strtolower($file->getExtension()), Kami\Model::ALLOWED_FILE_TYPES)) {
                // Don't enqueue if extension not eligible for Kami
                continue;
            }
            // File is eligible, and not yet queued.
            $kamiModel = $this->kamiMapper->createModel()
                                          ->setStudentSubmissionFile($file)
                                          ->setStatusQueued();
            $this->kamiMapper->save($kamiModel);
            $kamiModels[$kamiModel->getId()] = $kamiModel;
        }
        return $kamiModels;
    }

    /* Getters/Setters
     */

    /**
     * @return Rubric\ResultMapper
     */
    protected function getResultSetMapper()
    {
        return $this->resultSetMapper;
    }


    /**
     * @param Rubric\ResultMapper $resultSetMapper
     * @return static
     */
    protected function setResultSetMapper($resultSetMapper)
    {
        $this->resultSetMapper = $resultSetMapper;
        return $this;
    }


    /**
     * @return Rubric\RubricMapper
     */
    protected function getRubricMapper()
    {
        return $this->rubricMapper;
    }


    /**
     * @param Rubric\RubricMapper $rubricMapper
     * @return static
     */
    protected function setRubricMapper($rubricMapper)
    {
        $this->rubricMapper = $rubricMapper;
        return $this;
    }



    /**
     * @return DueWorkResponseMapper
     */
    protected function getDueWorkResponseMapper()
    {
        return $this->dueWorkResponseMapper;
    }


    /**
     * @param DueWorkResponseMapper $dueWorkResponseMapper
     * @return static
     */
    protected function setDueWorkResponseMapper($dueWorkResponseMapper)
    {
        $this->dueWorkResponseMapper = $dueWorkResponseMapper;
        return $this;
    }

}
