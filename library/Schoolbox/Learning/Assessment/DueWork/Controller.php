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
use Schoolbox\Application\Exception\NotFound;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\Rubric;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper;
use Schoolbox\Collection;
use Schoolbox\Error;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as DueWorkSubmissionFile;
use Schoolbox\Learning\Assessment\DueWork\Submission\MapperProxy as DueWorkResponseMapper;
use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Learning\Assessment\Participant;
use Schoolbox\Learning\Assessment\Transactor;
use Schoolbox\Learning\Response\TemporaryResponse;
use Schoolbox\Permission\Access\Access;
use Schoolbox\Resource\Attachment\Collector\CollectorFactory;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\UserMapper;
use \Schoolbox\Learning\Assessment\DueWork\Timeline\Full as FullTimeline;

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
        DueWorkResponseMapper $dueWorkResponseMapper
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $access, $transactor, $bakery);
        $this->resultSetMapper       = $resultSetMapper;
        $this->rubricMapper          = $rubricMapper;
        $this->workTypeMapper        = $workTypeMapper;
        $this->dueWorkResponseMapper = $dueWorkResponseMapper;
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
        /* TODO: Quickmark screen
         */
        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment),
            'assessment' => $assessment,
        ];

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/fragments/dueWork/status/staff.twig",
                $twigData
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

        /* TODO: Don't use temporary data
         * Ram in some temporary responses so that I
         * can make the Submission History section
         */
        (new TemporaryResponse())->produceResponse($assessment);

        $twigData = [
            'breadcrumbs' => $this->getBakery()->produceFromAssessment($assessment)->add([ $assessment->getTitle(), '' ]),
            'currentUser' => $this->getUser(),
            'student'     => $student,
            'assessment'  => $assessment,
            'timeline'    => (new FullTimeline($assessment))->getTimeline(),

            /* Rubrics data
             */
            'rubricContext' => (new RubricContext())->generate($assessment, $student, $this->getUser()),
        ];

        try {
            // TODO this should be called 'assert', not 'is'
            $assessment->isAcceptSubmission();
            $twigData['submissionsBlocked'] = false;
        } catch (DomainException $e) {
            $twigData['submissionsBlocked'] = $this->getErrorMapper()->generateException($e->getCode())->getMessage();
        }

        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                "@learningAssessment/fragments/dueWork/summary/student.twig",
                $twigData
            )
        );
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

        /* Work out if we are going to accept responses
         */
        try {
            $assessment->isAcceptSubmission();

        } catch (DomainException $e) {
            throw $this->getErrorMapper()->generateException($e->getCode());
        }

        /* Otherwise continue
         */
        $latestResponse = null;
        $editorContents = '';

        /* Find all the records spawned by $participant
         *
         * Order them by submitted time to find the most recent, and use that
         */

        /** @var Submission\File\Model $submission */
        $allResponses = $participant->getAllResponses();

        /* Attach files if any exist
         */
        $zoneFactory = new Assessment\Attachzone\Factory();
        $zone = $zoneFactory->buildForAssessmentType($assessment);

        /* Assume the most recent record is the latest submission
         */
        if ($allResponses->count()) {
            /** @var Submission\Model $latestResponse */
            $latestResponse = $participant->findMostRecentResponse();

            // Display the files that are a part of latest submission
            foreach ($latestResponse->getAllFiles() as $file) {
                /* Load in the wizzywig instead of a file attachment,
                 * if the submission uses the wizzywig editor
                 */
                if ($file->getEditableInline()) {
                    $editorContents .= $file->getStorage()->read();
                } else {
                    // add it to the danger zone
                    $zone->attachFiles([$file->getHash()]);
                }
            }

            // Is the latest response a draft? Update it if so, otherwise create a new one.
            // (The files in the latest submission were added regardless, so a student can
            //  create a new draft from their most recent live submission.)
            if (!$latestResponse->isDraft()) {
                $latestResponse = null;
            }
        }

        // TODO: what is this I don't even
        if (!$this->getUser()->isSuperuser()) {
            $folderIds = [];

            /* Sometimes subjectCollection is null ie. when superuser
             * [subject_collection:protected] =>
             */
            if (count($this->getUser()->getSubjectCollection())) {
                foreach ($this->getUser()->getSubjectCollection() as $currentSubject) {
                    $folderIds[] = $currentSubject['id'];
                }
            }

            if (!in_array($assessment->getContext()->getFolder()->getId(), $folderIds)) {
                throw $this->getErrorMapper()->generateException(Error\Mapper::ERROR_PERM_PAGE_VIEW);
            }
        }

        /* Pump into twig land
         */
        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/fragments/dueWork/createSubmission.twig', [
            'breadcrumbs'    => $this->getBakery()->produceFromAssessment($assessment)->add(['Create Response for ' . $assessment->getTitle(), '']),
            'assessment'     => $assessment,
            'student'        => $student,
            'currentUser'    => $this->getUser(),
            'attachzone'     => $zone->getTwigContext(),
            'response'       => $latestResponse,
            'editorContents' => $editorContents,
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

        /* Work out if we are going to accept responses
         */
        try {
            $assessment->isAcceptSubmission();
        }
        catch (DomainException $e) {

            /* When asynchronously saving and suddenly you're not allowed to
             * ie. when you've run out of time and you left this screen on
             */
            if ($rq->isXhr()) {
                $result['success'] = false;
                $result['message'] = $this->getErrorMapper()->getMessageById($e->getCode());
                // Probably don't need id
//                $result['id']      = 72;

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
        $response->attachOnlineSubmissionContent($body);

        // Make one of those factories that builds storages from hashes
        $factory = new CollectorFactory();
        if (!empty($rq->post['attachment'])) {
            foreach ($factory->build($rq->post['attachment'])->getAll() as $attachment) {
                $response->getAllFiles()->add(
                    (new DueWorkSubmissionFile())
                        ->setStorage($attachment)
                        ->setFilename($attachment->getFilename())
                        ->setEditableInline(false)
                );
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
        // create an empty Rubric with appropriate Indicators
        $rubric = $this->getRubricMapper()->createForContext($assessment->getContext());
        $rubric->setSubmissionBox(new SubmissionBox()); // TODO fix this with...something
        $assessment->setRubric($rubric);

        $zoneFactory = new Assessment\Attachzone\Factory();

        // setup some data...
        $data = [
            'acceptingResponses'=> true,
            'attachzone'      => $zoneFactory->buildForAssessmentType($assessment)->getTwigContext(),
            'allWorkTypes'    => $this->getWorkTypeMapper()->findAll(),
            'allMarkTypes'    => $this->getAllMarkTypes($assessment),
            'allGradeSchemes' => $this->getAllGradeSchemes(),
        ];

        // ...append rubric-related data...
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
            if (!$rs[$result->getResultSetId()] instanceof Collection) {
                $rs[$result->getResultSetId()] = new Collection();
            }
            $rs[$result->getResultSetId()][$result->getValue()] = $result;
        }

        $data['rubricResultSets'] = $rs;

        return $data;
    }


    /**
     * Decide which URL to send the client back to
     *
     * @param IAssessable $assessment
     * @param string      $defaultReturnUrl
     * @return string
     */
    protected function determineRedirect(IAssessable $assessment, $defaultReturnUrl = '')
    {
        // if your context is a project, redirect back to its status screen
        if ($assessment->getContext() instanceof Assessment\Project) {
            return $this->getRouter()->generate('learningAssessment.getStatus', [ 'assessmentId' => $assessment->getContext()->getId() ]);
        }

        return $defaultReturnUrl;
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
         * TODO: Save this setting lol
         * For now the only utility here is to update Skip Unsubmitted setting
         */

        /** @var \Schoolbox\Learning\Assessment\DueWork\Model $assessment */
        if ((int)$this->getRequest()->post['showSubmittedOnly']) {
            $assessment->showOnlySubmittedStudents();
        }
        else {
            $assessment->showAllSubmissibleStudents();
        }

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
