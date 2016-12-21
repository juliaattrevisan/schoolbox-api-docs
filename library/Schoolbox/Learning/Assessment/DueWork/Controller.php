<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork;

use Alaress_Date_Format;
use DomainException;
use DueWork_ErrorMapper;
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Participant\DataSource as ParticipantMapper;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as DueWorkSubmissionFile;
use Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy as DueWorkResponseMapper;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\Mapper as PlagscanMapper;
use Schoolbox\Learning\Assessment\Timeline\Full as FullTimeline;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecification;
use Schoolbox\Assessment\Rubric\Descriptor as RubricDescriptor;

class Controller extends Assessment\Controller
{

    /** @var Rubric\ResultMapper */
    private $resultSetMapper;

    /** @var Rubric\RubricMapper */
    private $rubricMapper;

    /** @var WorkTypeMapper */
    private $workTypeMapper;

    /** @var DueWorkResponseMapper */
    private $dueWorkResponseMapper;

    /** @var ParticipantMapper */
    private $participantMapper;

    /**
     * @param UserMapper                    $userMapper
     * @param Error\Mapper                  $errorMapper
     * @param MapperProxy                   $assessmentMapper
     * @param Access                        $access
     * @param Transactor                    $transactor
     * @param Assessment\Breadcrumb\Factory $bakery
     * @param Rubric\RubricMapper           $rubricMapper
     * @param Rubric\ResultMapper           $resultSetMapper
     * @param WorkTypeMapper                $workTypeMapper
     * @param DueWorkResponseMapper         $dueWorkResponseMapper
     * @param ParticipantMapper             $participantMapper
     */
    public function __construct(
        UserMapper $userMapper,
        Error\Mapper $errorMapper,
        MapperProxy $assessmentMapper,
        Access $access,
        Transactor $transactor,
        Assessment\Breadcrumb\Factory $bakery,
        Rubric\RubricMapper $rubricMapper,
        Rubric\ResultMapper $resultSetMapper,
        WorkTypeMapper $workTypeMapper,
        DueWorkResponseMapper $dueWorkResponseMapper,
        ParticipantMapper $participantMapper
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $access, $transactor, $bakery);
        $this->resultSetMapper       = $resultSetMapper;
        $this->rubricMapper          = $rubricMapper;
        $this->workTypeMapper        = $workTypeMapper;
        $this->dueWorkResponseMapper = $dueWorkResponseMapper;
        $this->participantMapper     = $participantMapper;
    }


    /**
     * This gives you a summary of progress made within an Assessment
     * It is the consolidation of pages previously known as "quickmark" and "quiz review"
     *
     * This place is Staff Only
     *
     * @api GET /learning/assessment/:assessmentId/status
     * @param IAssessable $assessment
     */
    public function getStatus(IAssessable $assessment)
    {
        $twigData = [
            'errors'      => DueWork_ErrorMapper::getInstance()->listDisplayValues(),
            'type'        => Assessment\AssessmentType::objectToString($assessment),
            'markScheme'  => $this->deriveSchemeName($assessment->getMarkScheme()),
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),
            'assessment'  => $assessment,
            'currentUser' => $this->getUser(),
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
     * @api   GET /learning/assessments/:assessmentId/:userId
     * @level 165
     *
     * @param Participant $participant
     * @throws NotFound
     */
    public function getSummary(Participant $participant)
    {
        $this->assertParticipantAccess($participant);

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

        /** @var Submission\File\Model $submission */
        $allResponses = $participant->getAllResponses();

        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add([ $assessment->getTitle(), '' ]),
            'participant' => $participant,
            'currentUser' => $this->getUser(),
            'timeline'    => (new FullTimeline($participant))->getForUser($this->getUser()),

            /* Plagscan status
             */
            'plagscanEnabled' => PlagscanMapper::getInstance()->isEnabled($this->getUser()),

            /* Rubrics data
             */
            'rubricContext' => (new RubricContext($this->rubricMapper))->generate($assessment, $student, $this->getUser()),
        ];

        try {
            $assessment->assertAcceptSubmission($allResponses);
            $twigData['submissionsBlocked'] = false;
        } catch (DomainException $e) {
            $twigData['submissionsBlocked'] = $this->getErrorMapper()->generateException($e->getCode())->getMessage();
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/fragments/dueWork/summary/student.twig",
                $twigData, "{$assessment->getTitle()} - {$student->getFullName()}", 'submission'
            )
        );
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
            $this->participantMapper->loadParticipationIntoAssessment($assessment);

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
        $this->participantMapper->loadParticipationIntoAssessment($assessment);

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
            $this->rubricMapper->applyStudentMark($rubric, $participant->getUser(), true);

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
     */
    public function getCreateResponseForm(Participant $participant)
    {
        $this->assertParticipantAccess($participant);

        /** @var Model $assessment */
        $assessment = $participant->getAssessable();
        $student    = $participant->getUser();

        /* Find all the records spawned by $participant
         *
         * Order them by submitted time to find the most recent, and use that
         */

        /** @var Submission\File\Model $submission */
        $allResponses = $participant->getAllResponses();

        /* Work out if we are going to accept responses
         */
        try {

            /* assertAcceptSubmission() requires a list of responses to decide outcome
             */
            $assessment->assertAcceptSubmission($allResponses);

        } catch (DomainException $e) {
            throw $this->getErrorMapper()->generateException($e->getCode());
        }

        /* Otherwise continue
         */
        $latestResponse = null;
        $editorContents = '';
        $dangerZone = null;

        $attachZoneFactory = new Assessment\Attachzone\Factory();

        /* Assume the most recent record is the latest submission
         */
        if ($allResponses->count()) {
            /** @var Submission\Model $latestResponse */
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
                $dangerZone = $attachZoneFactory->buildForDueWorkResponse(new Submission\Model());
            }
        }
        else {

            /* Init a dangerZone that is ready to accept files for
             * the first ever draft
             */
            $dangerZone = $attachZoneFactory->buildForDueWorkResponse(new Submission\Model());
        }

        /* Pump into twig land
         */
        // For some reason I can't use get/setFlash?
        $errorMessage = $this->getSession()->getSegment(static::class)->get('errorMessage');
        $this->getSession()->getSegment(static::class)->set('errorMessage', null);
        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/fragments/dueWork/createSubmission.twig', [
            'breadcrumbs'    => $this->getBakery()->produceFromAssessment($assessment, $this->getUser())->add(['Create Response for ' . $assessment->getTitle(), '']),
            'assessment'     => $assessment,
            'student'        => $student,
            'currentUser'    => $this->getUser(),
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
     * @api POST /learning/assessments/:assessmentId/:participantId/submit
     * @param Participant $participant
     * @return void
     */
    public function postCreateResponse(Participant $participant)
    {
        $this->assertParticipantAccess($participant);

        $rq = $this->getRequest();
        $rs = $this->getResponse();

        /** @var Model $assessment */
        $assessment = $participant->getAssessable();

        /* Find all the records spawned by $participant
         *
         * Order them by submitted time to find the most recent, and use that
         */

        /** @var Submission\File\Model $submission */
        $allResponses = $participant->getAllResponses();

        /* Work out if we are going to accept responses
         */
        try {

            /* assertAcceptSubmission() requires a list of responses to decide outcome
             */
            $assessment->assertAcceptSubmission($allResponses);
        }
        catch (DomainException $e) {

            /* When asynchronously saving and suddenly you're not allowed to
             * ie. when you've run out of time and you left this screen on
             */
            if ($rq->isXhr()) {
                $result['success'] = false;
                $result['message'] = $this->getErrorMapper()->getMessageById($e->getCode());

                $rs->status->setCode(403);
                $rs->content->set($this->buildJsonOutput($result));
                return;
            }

            throw $this->getErrorMapper()->generateException($e->getCode());
        }

        /* Creator is the currently-logged-in user
         * Participant is the actual owner of the Response
         *
         * Usually the same person, unless you're a teacher creating a Response
         * on behalf of a student (teacher is creator, student is participant)
         */
        $creator = $this->getUser();

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
            $response = new DueWorkSubmission();
            $response
                ->setSubmittedBy($creator)
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
                $attachedFile = (new DueWorkSubmissionFile())
                    ->setStorage($attachment)
                    ->setFilename($attachment->getFilename())
                    ->setEditableInline(false);

                /* Stack it up
                 */
                $response->getAllFiles()->add($attachedFile);
            }
        }

        /* If we're saving the final version of the submission
         * (ie. not a draft) ram the current datetime in
         */
        if ($isFinal) {
            $response->setSubmittedAt(Alaress_Date_Format::now());
        }

        /* Do the actual saving
         */
        $this->getDueWorkResponseMapper()->save($response);

        /* Save stubble record and send each file in the Submission to Plagscan
         * if the Submission was saved successfully
         *
         * Only plagscan when actually submitting final
         * Only plagscan non-online-submission files (for some reason, plag won't do html files)
         */
        $plagscanMapper = PlagscanMapper::getInstance();

        if ($isFinal && $response->getId()) {
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
                function() use ($plagscanMapper, $isFinal, $response) {
                    if ($isFinal && $response->getId()) {
                        foreach ($response->getAllFiles() as $currentFile) {
                            $plagscanMapper->saveNewPlagscan($currentFile);
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


    /**
     * @param Model|IAssessable $assessment
     * @return array
     */
    protected function getAssessmentTwigData(IAssessable $assessment)
    {
        $zoneFactory = new Assessment\Attachzone\Factory();

        // setup some data...
        $data = [
            'acceptingResponses'=> true,
            'attachzone'      => $zoneFactory->buildForAssessment($assessment)->getTwigContext(),
            'allWorkTypes'    => $this->getWorkTypeMapper()->findAll(),
            'allMarkTypes'    => $this->getAllMarkTypes($assessment),
            'allGradeSchemes' => $this->getAllGradeSchemes(),
        ];

        // ...append rubric-related data...
        if($assessment->getId()){
            $getParentCriteria = $assessment->getContext() instanceof UnitSpecification;
            $editMode = true;
            $rubric = $this->rubricMapper->findByDueWork($assessment, $getParentCriteria, $editMode);
        } else {
            $rubric = $this->rubricMapper->createForContext($assessment->getContext());
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
            $submitBox = \Schoolbox\Assessment\Component\SubmissionBoxMapper::getInstance()->findOneByColumn('assessment_id', $assessment->getId());
            $submitBox->setSkipUnsubmitted($skipUnsubmitted);
            \Schoolbox\Assessment\Component\SubmissionBoxMapper::getInstance()->save($submitBox);
        }
        catch (\Schoolbox\Mapper\Exception $e) {

            /* noop - submission box doesn't exist
             */
        }

        $this->getResponse()->status->setCode(204);
        $this->getResponse()->content->set($this->buildJsonOutput(null));
        return;
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
     * @return WorkTypeMapper
     */
    protected function getWorkTypeMapper()
    {
        return $this->workTypeMapper;
    }


    /**
     * @param WorkTypeMapper $workTypeMapper
     * @return static
     */
    protected function setWorkTypeMapper($workTypeMapper)
    {
        $this->workTypeMapper = $workTypeMapper;
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
