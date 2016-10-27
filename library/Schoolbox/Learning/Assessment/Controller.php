<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment;

use Alaress\Web\Controller\Base;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeScheme;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeSchemeMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\FileMapper as SubmissionFileMapper;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\File as SubmissionFile;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Attachzone\Factory as ZoneFactory;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\User as User;
use Schoolbox\UserMapper;
use \Alaress_Date_Format;
use \Alaress_Date_NullDate;
use \Schoolbox\Resource\Folder\Model as Folder;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use \Schoolbox\Learning\Assessment\Summator\Mapping as SummatorMapping;
use \Schoolbox\Learning\Assessment\DataModel\MapperProxy as AssessmentMapperProxy;
use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\DueWork\Type as DueWorkType;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use \Schoolbox\Learning\Assessment\Task\Model as Task;
use \Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
use \Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
use \Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use \Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Resource\Attachment\Zone;

use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Learning\Course\Unit\ImplementationMapperProxy as UnitImplementationMapperProxy;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Learning\Course\Unit\SpecificationMapperProxy as UnitSpecificationMapperProxy;

use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;

use \Alaress\Web\Controller\Exception as ControllerException;

class Controller extends Base
{
    /**
     * @var ErrorMapper
     */
    protected $errorMapper;

    /**
     * @var FolderMapper
     */
    protected $folderMapper;

    /**
     * @var SubmissionFileMapper
     */
    protected $submissionFileMapper;

    /**
     * @var AssessmentMapperProxy
     */
    protected $assessmentMapperProxy;

    /**
     * @var UnitSpecificationMapperProxy
     */
    protected $unitSpecificationMapperProxy;

    /**
     * @var UnitImplementationMapperProxy
     */
    protected $unitImplementationMapperProxy;

    /**
     * @var Participant\DataSource
     */
    protected $participantDataSource;

    /**
     * Controller constructor.
     * @param FolderMapper $folderMapper
     * @param ErrorMapper $errorMapper
     * @param SubmissionFileMapper $submissionFileMapper
     * @param AssessmentMapperProxy $assessmentMapperProxy
     * @param UnitSpecificationMapperProxy $unitSpecificationMapperProxy
     * @param UnitImplementationMapperProxy $unitImplementationMapperProxy
     * @param Participant\DataSource $participantDataSource
     */
    public function __construct(
        FolderMapper $folderMapper,
        ErrorMapper $errorMapper,
        SubmissionFileMapper $submissionFileMapper,
        AssessmentMapperProxy $assessmentMapperProxy,
        UnitSpecificationMapperProxy $unitSpecificationMapperProxy,
        UnitImplementationMapperProxy $unitImplementationMapperProxy,
        Participant\DataSource $participantDataSource
    ) {
        $this->setErrorMapper($errorMapper);
        $this->setFolderMapper($folderMapper);
        $this->setSubmissionFileMapper($submissionFileMapper);
        $this->setAssessmentMapperProxy($assessmentMapperProxy);
        $this->setUnitSpecificationMapperProxy($unitSpecificationMapperProxy);
        $this->setUnitImplementationMapperProxy($unitImplementationMapperProxy);
        $this->setParticipantDataSource($participantDataSource);
    }

    /**
     * Generates a form to create an Assessment in the specified Folder
     *
     * @api /learning/assessment/folder/{folderId}{/assessmentTypeTitle}
     * @level 165
     * @param Folder $folder
     * @param IAssessable|null $assessment
     */
    public function getSelectAssessmentInFolder(Folder $folder, IAssessable $assessment = null)
    {
        /* Pump into twig-land
         */
        $this->getSelectAssessmentData($folder, $assessment);
    }

    /**
     * Generates a form to create an Assessment in the specified Project
     *
     * @api /learning/assessment/project/{projectId}{/assessmentTypeTitle}
     * @level 165
     * @param Project $project
     * @param IAssessable $assessment
     */
    public function getSelectAssessmentInProject(Project $project, IAssessable $assessment = null)
    {
        /* Die early if you're trying to ram a project into a project
         * We specifically don't allow it
         *
         * Instead, redirect to a safe place
         */
        if ($assessment instanceof Project) {
            $data = ['projectId' => $project->getId()];
            $redirect = $this->getRouter()->generate('learningAssessment.getSelectAssessmentInProject', $data);
            $this->getResponse()->redirect->to($redirect);
        }

        /* Pump into twig-land
         */
        $this->getSelectAssessmentData($project, $assessment);
    }

    /**
     * Generates a form to create an Assessment in the specified Unit Implementation
     *
     * @api /learning/assessment/unit/{unitId}{/assessmentTypeTitle}
     * @level 165
     * @param UnitImplementation $unitImpl
     * @param IAssessable|null $assessment
     */
    public function getSelectAssessmentInUnitImplementation(UnitImplementation $unitImpl, IAssessable $assessment = null)
    {
        /* Pump into twig-land
         */
        $this->getSelectAssessmentData($unitImpl, $assessment);
    }

    /**
     * Generates a form to create an Assessment in the specified Unit Specification
     *
     * @api /learning/assessment/unitspec/{unitSpecId}{/assessmentTypeTitle}
     * @level 165
     * @param UnitSpecification $unitSpec
     * @param IAssessable $assessment
     */
    public function getSelectAssessmentInUnitSpecification(UnitSpecification $unitSpec, IAssessable $assessment = null)
    {
        /* Pump into twig-land
         */
        $this->getSelectAssessmentData($unitSpec, $assessment);
    }

    /**
     * Common logic to generate a form to create an IAssessable in the specified AssessmentContext
     *
     * @param AssessmentContext $context
     * @param IAssessable $assessment = null
     */
    private function getSelectAssessmentData(AssessmentContext $context, IAssessable $assessment = null)
    {
        $zoneFactory = new ZoneFactory();
        $zone = $zoneFactory->buildForAssessmentType($assessment);

        /* Set defaults */
        if($assessment != null && empty($assessment->getId())) {
            $assessment
                ->setPublishAt(new Alaress_Date_Format(new \DateTime('00:00:00')))
                ->setOpenAt(new Alaress_Date_Format(new \DateTime('00:00:00')))
                ->setDueAt(new Alaress_Date_Format(new \DateTime('+1 day 23:59:59')))
                ->setCloseAt(new Alaress_Date_Format(new \DateTime('+2 day 23:59:59')))
                ->setWeight(0);
        }

        /* The usual info everything requires
         */
        $data = [
            'context'         => $context,
            'contextType'     => AssessmentContextType::objectToString($context),
            'baseContextType' => AssessmentContextType::objectToString($context->getBaseContext()),
            'breadcrumbs'     => (new BreadcrumbFactory($this->getRouter()))->produceFromContext($context, $assessment),
            'attachzone'      => $zone->getTwigContext(),
            'assessment'      => $assessment,
            'folder'          => $context->getFolder(),
            'curriculumNodes' => $context->getFolder()->getCurriculumNodes(),
            'types' => [
                ['id' => 'due-work', 'value' => AssessmentType::TYPE_DUE_WORK, 'label' => 'Due Work'],
                ['id' => 'task',     'value' => AssessmentType::TYPE_TASK,     'label' => 'Task'],
                ['id' => 'quiz',     'value' => AssessmentType::TYPE_QUIZ,     'label' => 'Quiz'],
            ]
        ];
        // TODO, 'types' should just be the 3 objects, and twig can do the rest
        // TODO (see icon macro for 'id', assessment.typeName for 'label', types.objectToString($assessment) for 'value')

        // Do caller request a returnUrl?
        $returnUrl = $this->getRequest()->query->get('return_url');

        if ($returnUrl){
            $data['returnUrl'] = $returnUrl;
        }

        if ($assessment){
            $data = array_merge($data, [
                'allMarkTypes'   => $this->getAllMarkTypes($assessment),
                'assessmentType' => AssessmentType::objectToString($assessment),
            ]);
        }

        /* Used to hide a ui element, preventing Projects being an option
         * to spawn inside a Project context
         */
        if (!$context instanceof Project) {
            $item = ['id' => 'project',  'value' => AssessmentType::TYPE_PROJECT,  'label' => 'Project'];
            array_unshift($data['types'], $item);
        }

        /* Special treatment for projects for now
         */
        if ($assessment instanceof Project) {
            $data['allSummators'] = new SummatorMapping();
        }

        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/create.twig', $data));
    }

    /**
     * Return a HTML fragment to display in Step-2 for the AssessmentType selected in Step-1
     *
     * @api /learning/assessment/select
     * @level 165
     */
    public function postSelectAssessment()
    {
        /* TODO: Check against ($contextId, $contextType) as well
         */
        try {
            $folder = $this->getFolderMapper()->findById($this->getRequest()->post['folderId']);
        }
        catch (MapperException $e) {
            $this->sendError('Could not create assessment', [ 'select a valid folder' ]);
            return;
        }

        $assessmentType = $this->getRequest()->post['assessmentType'];

        /* Common data among all assessmentType fragments
         */
        $types = new AssessmentType();

        /* Set defaults */
        $assessment = $types->stringToObject($assessmentType)
                ->setPublishAt(new Alaress_Date_Format(new \DateTime('00:00:00')))
                ->setOpenAt(new Alaress_Date_Format(new \DateTime('00:00:00')))
                ->setDueAt(new Alaress_Date_Format(new \DateTime('+1 day 23:59:59')))
                ->setCloseAt(new Alaress_Date_Format(new \DateTime('+2 day 23:59:59')))
                ->setWeight(0);

        if($assessment instanceof DueWork){
            $assessment
                ->useRubric()
                ->setAcceptsMultiple();
        }

        $data = [
            'attachzone'      => (new ZoneFactory())->buildForAssessment($assessment)->getTwigContext(),
            'assessment'      => $assessment,
            'assessmentType'  => $types->objectToString($assessment),
            'curriculumNodes' => $folder->getCurriculumNodes(),
            'allMarkTypes'    => $this->getAllMarkTypes($assessment)
        ];

        /* Special treatment for projects for now
         */
        if ($assessment instanceof Project) {
            $data['allSummators'] = new SummatorMapping();
        }

        /* Select the fragment to return
         */
        $this->getResponse()->content->set($this->buildUnthemedHtmlOutput("@learningAssessment/fragments/{$types->objectToString($assessment)}/create.twig", $data));
    }

    /**
     * Save the Assessment's data
     *
     * This saves the combination of common+assessmentType-specific details
     *
     * @api POST /learning/assessment/create
     * @level 165
     */
    public function postCreateAssessment()
    {
        try {
            $assessmentInfo = $this->persistAssessment();
        }
        catch (ControllerException $e) {
            $this->sendError($e);

            /* Return Early to let mage deal with error messages
             */
            return;
        }

        /** @var IAssessable $assessment */
        $assessment = $assessmentInfo['assessment'];

        /* If you spawned a project or an assessable within a project
         * Redirect to project summary screen
         *
         * Otherwise return to whatever context you came from
         */
        if ($assessment instanceof Project) {
            $returnUrl = $this->getRouter()->generate('learningAssessment.getSummary', ['assessmentId' => $assessment->getId()]);
        }
        elseif ($assessment->getContext() instanceof Project) {
            $returnUrl = $this->getRouter()->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getContext()->getId()]);
        }
        elseif (strlen($this->getRequest()->post['returnUrl'])) {
            $returnUrl = $this->getRequest()->post['returnUrl'];
        }
        else {
            $returnUrl = $assessmentInfo['returnUrl'];
        }

        $data = [
            'returnUrl' => $returnUrl
        ];

        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }

    /**
     * Update an existing Assessment's details
     *
     * @param IAssessable $assessment
     */
    public function getUpdateAssessment(IAssessable $assessment)
    {
        $types = new AssessmentType();

        $zoneFactory = new ZoneFactory();
        $zone = $zoneFactory->buildForAssessment($assessment);

        /* Twig payload
         */
        $data = [
            'breadcrumbs'     => (new BreadcrumbFactory($this->getRouter()))->produceFromAssessment($assessment)->add(['Update', '']),
            'assessment'      => $assessment,
            'assessmentType'  => AssessmentType::objectToString($assessment),
            'attachzone'      => $zone->getTwigContext(),
            'folder'          => $assessment->getContext()->getFolder(),
            'curriculumNodes' => $assessment->getContext()->getFolder()->getCurriculumNodes(),
            'allMarkTypes'    => $this->getAllMarkTypes($assessment),
            'allGradeSchemes' => $this->getAllGradeSchemes(),
        ];

        /* Special treatment for projects for now
         */
        if ($assessment instanceof Project) {
            $summatorMapping = new SummatorMapping();

            $data['types']         = $types;
            $data['selectedSummator'] = $summatorMapping->decompose($assessment->getSummator());
            $data['allSummators']  = $summatorMapping;
        }

        $template = "@learningAssessment/fragments/{$types->objectToString($assessment)}/update.twig";
        $this->getResponse()->content->set($this->buildHtmlOutput($template, $data));

    }

    /**
     * Save the data when creating a specific kind of Assessment
     *
     * This happens after the common details are saved and after
     * you've chosen one of project, due work, task, or quiz
     *
     * @param IAssessable $assessment
     */
    public function postUpdateAssessment(IAssessable $assessment)
    {
        try {
            $assessmentInfo = $this->persistAssessment($this->getAssessmentMapperProxy()->findById($assessment->getId()));
        }
        catch (ControllerException $e) {
            $this->sendError($e);

            /* Return Early to let mage deal with error messages
             */
            return;
        }

        /* Redirect to project summary screen instead, if you spawned a project
         * Otherwise return to context
         */
        if ($assessmentInfo['assessment'] instanceof Project) {
            $returnUrl = $this->getRouter()->generate('learningAssessment.getStatus', ['assessmentId' => $assessmentInfo['assessment']->getId()]);
        }
        else if ($assessmentInfo['assessment']->getContext() instanceof Project) {
            $returnUrl = $this->getRouter()->generate('learningAssessment.getStatus', ['assessmentId' => $assessmentInfo['assessment']->getContext()->getId()]);
        }
        else {
            $returnUrl = $assessmentInfo['returnUrl'];
        }

        $data = [
            'returnUrl' => $returnUrl
        ];

        $this->getResponse()->content->set($this->buildJsonOutput($data));
    }

    /**
     * Generic method for create/update of an assessment.
     *
     * persistAssessment() CANNOT make any assumptions about redirects
     * Redirects should be dealt with by actual controller methods, since
     * the returnUrl is manipulated by javascript in particular contexts
     *
     * @param IAssessable $assessment If null, create a new one from the form - otherwise, update this
     * @throws ControllerException
     * @return array
     */
    protected function persistAssessment(IAssessable $assessment = null)
    {
        /* If an assessment couldn't be produced, bail out
         * and let mage see the errors set by $this->sendError
         *
         * Controller must return early for that to happen
         */
        try {

            /* Figure out what kind of assessment was chosen
             * and spawn a new instance of it to manipulate
             */
            $assessmentFactory = new Factory($this->getUser(), $this->getRequest()->post);
            $assessment = $assessmentFactory->produce($assessment);

            /* Manually do a check
             */
            if (!$assessment->isValidDates()) {
                throw new ControllerException('Could not create assessment', ['double-check the date fields you provided']);
            }

            /* Save the $asseessment
             * Try to figure out what the redirect destination should be
             * Throw an error if some kind of context configuration is wrong
             */
            $transactor = new Transactor(
                $assessment,
                $this->getFolderMapper(),
                $this->getUnitImplementationMapperProxy(),
                $this->getUnitSpecificationMapperProxy(),
                $this->getAssessmentMapperProxy(),
                $this->getRouter()
            );

            $transactionSummary = $transactor->transact();
        }
        catch (\DomainException $e) {
            throw new ControllerException('Could not create assessment', [$e->getMessage()]);
        }
        catch (\InvalidArgumentException $e) {
            throw new ControllerException('Could not create assessment', [$e->getMessage()]);
        }
        catch (\UnexpectedValueException $e) {
            throw new ControllerException('Could not create assessment', [$e->getMessage()]);
        }

        return $transactionSummary;
    }


    /**
     * Delete the given entity. Deletion is considered to be an idempotent method,
     * so do not return an error on repeat attempts to delete an entity.
     *
     * On success, respond with '204 No Content' and a Location header to the list
     * On failure, respond appropriately
     *
     * @api POST /learning/assessment/{assessmentId}/delete
     * @level 165
     * @param IAssessable $assessment Will be null if already deleted
     * @return void
     */
    public function postDeleteAssessment(IAssessable $assessment)
    {
        // Already deleted?
        if($assessment->isDeleted()){
            $this->getResponse()->status->setCode(204);
        } else {
            $this->getAssessmentMapperProxy()->delete($assessment);
        }

        $folder = $assessment->getContext()->getFolder();
        $returnUrl = $this->getRequest()->post['returnUrl'];

        // Did caller explicitly specify a returnUrl ?
        if ($returnUrl) {
            $redirect = $returnUrl;
        } else {
            // Did we delete an item from a Project ?
            if($assessment->getContext() instanceof Project){
                $data = ['assessmentId' => $assessment->getContext()->getId()];
                $redirect = $this->getRouter()->generate('learningAssessment.getStatus', $data);
            }
            // Must be from a Folder, UnitImpl, or UnitSpec
            else {
                $redirect = "/homepage/{$folder->getId()}";
            }
        }

        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput(['returnUrl' => $redirect]));
        } else {
            $this->getResponse()->redirect->to($redirect);
        }
    }

    /**
     * Redirect method
     *
     * Sends you to a status or summary screen depending on who is
     * currently logged in and what they are viewing
     *
     * @api GET /learning/assessment/:assessmentId
     * @param IAssessable $assessment
     */
    public function getAssessment(IAssessable $assessment)
    {
        /* If you're a staff, get redirected to a Status overview
         */
        if ($this->getUser()->getRole()->isStaff()) {
            $this->getResponse()->redirect->to($this->getRouter()->generate('learningAssessment.getStatus', ['assessmentId' => $assessment->getId()]));
            return;
        }
        /* If you're a student, get redirected to your specific view
         * /learning/assessment/:assessmentId/:studentId
         */
        else if ($this->getUser()->getRole()->isStudent()) {
            $this->getResponse()->redirect->to($this->getRouter()->generate('learningAssessment.getSummary', ['assessmentId' => $assessment->getId(), 'studentId' => $this->getUser()->getId()]));
            return;
        }

        /* Everyone else sees The Nothing
         */
        throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
    }

    /**
     * This gives you a summary of progress made within an Assessment
     *
     * This place is Staff Only
     *
     * @api GET /learning/assessment/:assessmentId/status
     * @param IAssessable $assessment
     */
    public function getStatus(IAssessable $assessment)
    {
        /* Staff Only
         */
        if (!$this->getUser()->getRole()->isStaff() && !$this->getUser()->isSuperuser()) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }

        /* There are no Summary views for Tasks; 404 them instead
         */
        if ($assessment instanceof Task) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }
        /* TODO: Quiz needs to redirect to whatever their intro screen is
         */
        else if ($assessment instanceof Quiz) {

        }
        /* If you're a staff and you're viewing a project,
         * You get to see all the assessments and all the participants
         */
        else if ($assessment instanceof Project) {

            // Load participants manually
            $assessment->setAllParticipants($this->getParticipantDataSource()->findForAssessment($assessment));

            // Calculate summary totals for display purposes
            $participants = $assessment->getAllParticipants();

            $counts = [];
            $assessableCounts = [];
            foreach ($participants as $participant) {
                $counts[$participant->getUser()->getId()] = [
                    'total' => 0,
                    'totalCompleted' => 0,
                ];
            }

            // This isn't necessarily a project.
            foreach ($assessment->getAssessables() as $subAssessment) {
                $assessableCounts[$subAssessment->getId()]['total'] = 0;

                // Populate the participants for every single sub-assessment
                // TODO can this be performed more efficiently?
                $subAssessment->setAllParticipants($this->getParticipantDataSource()->findForAssessment($subAssessment));
                foreach ($subAssessment->getAllParticipants() as $participant) {
                    $counts[$participant->getUser()->getId()]['total']++;
                    $assessableCounts[$subAssessment->getId()]['total']++;
                    if ($participant->getAllResponses()->count() > 0) {
                        $counts[$participant->getUser()->getId()]['totalCompleted']++;
                        $assessableCounts[$subAssessment->getId()]['totalCompleted']++;
                    }
                }
            }

            /* Manufacture a universal returnUrl
             * Return to summary screen with this suffix
             */
            $baseUrl = $this->getRouteIfAuthorized('learningAssessment.getSummary', ['assessmentId' => $assessment->getId()]);
            $returnUrlSuffix = '?return_url=' . (new Redirect())->encode($baseUrl);

            $types = new AssessmentType();
            $twigData = [
                'breadcrumbs' => (new BreadcrumbFactory($this->getRouter()))->produceFromAssessment($assessment),
                'counts' => $counts,
                'assessableCounts' => $assessableCounts,
                'assessment' => $assessment,
                'currentUser' => $this->getUser(),
                'returnUrlSuffix' => $returnUrlSuffix
            ];

            $this->getResponse()->content->set(
                $this->buildHtmlOutput(
                    "@learningAssessment/fragments/{$types->objectToString($assessment)}/status/staff.twig",
                    $twigData
                )
            );
        }
        /* If you're a staff and you're viewing a due work,
         * You get to see all participants, quickmark them, and links to getSummary()
         */
        else if ($assessment instanceof DueWork) {
            $types = new AssessmentType();

            /* TODO: Quickmark screen
             */
            $twigData = [
                'breadcrumbs' => (new BreadcrumbFactory($this->getRouter()))->produceFromAssessment($assessment),
                'assessment' => $assessment,
            ];

            $this->getResponse()->content->set(
                $this->buildHtmlOutput(
                    "@learningAssessment/fragments/{$types->objectToString($assessment)}/status/staff.twig",
                    $twigData
                )
            );
        }
        /* Everything else, 404
         */
        else {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }
    }

    /**
     * Display appropriate landing page based on role
     *
     * @api   GET /learning/assessment/:assessmentId/:userId
     * @level 165
     * @param IAssessable $assessment
     * @param User $student
     */
    public function getSummary(IAssessable $assessment, User $student)
    {
        /* TODO load $participant in Module.php
         */

        /* There are no Summary views for Tasks; 404 them instead
         */
        if ($assessment instanceof Task) {
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }
        /* TODO: Quiz needs to redirect to whatever their intro screen is
         */
        else if ($assessment instanceof Quiz) {
        }
        /* If you're a staff and you're viewing a due work for a particular student
         * see the details of the due work and have a Submit On Behalf Of button available
         * OR
         * If you're a student and you're viewing a due work for yourself
         * see the details of the due work and have a Submit button available
         * OR
         * If you're a parent
         * You don't get a button
         */
        else if ($assessment instanceof DueWork) {
            $types = new AssessmentType();

            // Load participants manually
            $assessment->setAllParticipants($this->getParticipantDataSource()->findForAssessment($assessment));

            /* Figure out if this student is actually participating
             */
            try {
                $assessment->getParticipant($student);
            }
            catch (\UnexpectedValueException $e) {
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
            }

            $twigData          = [
                'breadcrumbs' => (new BreadcrumbFactory($this->getRouter()))->produceFromAssessment($assessment)->add([$assessment->getTitle(), '']),
                'currentUser' => $this->getUser(),
                'student' => $student,
                'assessment'  => $assessment,
            ];

            $this->getResponse()->content->set(
                $this->buildHtmlOutput(
                    "@learningAssessment/fragments/{$types->objectToString($assessment)}/summary/student.twig",
                    $twigData
                )
            );
        }
        /* If you're a staff and you're viewing a project for a particular student
         * you get to see that student's progress in the project
         * OR
         * If you're a student and you're viewing a project
         * so you get to see your own progress in that project
         */
        else if ($assessment instanceof Project) {
            if ($this->getUser()->getRole()->isStaff() || $this->getUser()->getRole()->isStudent()) {
                $types = new AssessmentType();

                // Load participants manually
                $assessment->setAllParticipants($this->getParticipantDataSource()->findForAssessment($assessment));

                $counts = [];

                // Calculate summary totals for display purposes
                foreach($types->getStringValues() as $type){
                    if (AssessmentType::TYPE_PROJECT == $type){
                        continue;
                    }

                    $counts[$type] = 0;
                    $counts["{$type}Completed"] = 0;
                }

                $counts['total'] = 0;
                $counts['totalCompleted'] = 0;

                $assessables = $assessment->getAssessables();

                /** @var IAssessable $assessable */
                foreach ($assessables as $assessable) {
                    // Load participants manually
                    // TODO: Do it faster and cheaperer
                    // $assessable->setAllParticipants($this->getParticipantDataSource()->findForAssessment($assessable));

                    try {
                        $participant = $assessable->getParticipant($student);
                    } catch (\UnexpectedValueException $e) {
                        // This student isn't a participant.
                        // This is fine - when PBL is implemented not all students
                        // will participate in all sub-assessments
                        continue;
                    }

                    // If the student has submitted at least one response we will assume it is completed
                    $counts[$types->objectToString($assessable)]++;
                    $counts['total']++;

                    if ($participant->getAllResponses()->count() > 0) {
                        $counts["{$types->objectToString($assessable)}Completed"]++;
                        $counts['totalCompleted']++;
                    }
                }

                // Ideally make this an instance var instead of instantiating all the time
                // Except you can't, $this->getRouter() returns null in __construct()?
                $twigData = [
                    'breadcrumbs' => (new BreadcrumbFactory($this->getRouter()))->produceFromAssessment($assessment),
                    'currentUser' => $this->getUser(),
                    'student' => $student,
                    'assessment' => $assessment,
                    'counts' => $counts,
                ];

                $this->getResponse()->content->set($this->buildHtmlOutput("@learningAssessment/fragments/{$types->objectToString($assessment)}/summary/student.twig", $twigData));
            }
            /* For parents, do other things?
             */
        }
        else {

            /* Everything else, 404
             */
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }
    }

    /**
     * Generic method for create/update of an assessment.
     *
     * @param $postData
     * @param IAssessable|null $existingAssessment
     * @return IAssessable $assessment
     * @throws ControllerException
     */
    protected function produceAssessment($postData, IAssessable $existingAssessment = null)
    {
        $assessment = null;
        try {
            $assessmentFactory = new Factory($this->getUser(), $postData);
            $assessment = $assessmentFactory->produce($existingAssessment);

        }
        catch (\DomainException $e) {
            throw new ControllerException('Could not create assessment', [$e->getMessage()]);
        }
        catch (\InvalidArgumentException $e) {
            throw new ControllerException('Could not create assessment', [$e->getMessage()]);
        }
        catch (\UnexpectedValueException $e) {
            throw new ControllerException('Could not create assessment', [$e->getMessage()]);
        }

        return $assessment;
    }

    /**
     * Displays a form enabling a student to create Submission for a Due Work
     *
     * @api   GET /learning/assessment/:assessmentId/:participantId/submit
     * @level 165
     * @param DueWork $assessment With the given student's IResponses or IFeedback attached
     * @param User $student
     */
    public function getCreateSubmission(DueWork $assessment, User $student)
    {
        /* Work out if we are going to accept responses
         */
        try {
            $assessment->isAcceptSubmission();
        }
        catch (\DomainException $e) {
            throw $this->getErrorMapper()->generateException($e->getCode());
        }

        /* Otherwise continue
         */
        $onlineSubmissionFilenames = ['Online Submission', 'online-submission'];
        $latestResponse = null;
        $editorContents = '';

        /* Find all the records spawned by $participant
         *
         * Order them by submitted time to find the most recent, and use that
         *
         * TODO: Replace SubmissionFileMapper() usage
         */

        /** @var SubmissionFile $submission */
        $allResponses = $this->getSubmissionFileMapper()->findByColumnList([
            'owner' => $student->getId(),
            'box_id' => 72
        ], [
            'order' => [
                'submitted' => 'desc'
            ]
        ]);

        /* Assume the most recent record is the latest submission
         */
        if ($allResponses->count()) {
            $latestResponse = $allResponses->first();

            /* Load in the wizzywig instead of a file attachment,
             * if the submission uses the wizzywig editor and the
             * submission is still a draft
             */
            if (in_array($latestResponse->getStorage()->getFilename(), $onlineSubmissionFilenames)) {
                if ($latestResponse->getDraft()) {
                    $editorContents = $latestResponse->getStorage()->read();
                }
            }
        }

        /* Attach files if any exist
         */
        $zoneFactory = new ZoneFactory();
        $zone = $zoneFactory->buildForAssessmentType($assessment);

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
                throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PERM_PAGE_VIEW);
            }
        }

        /* Pump into twig land
         */
        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/fragments/dueWork/createSubmission.twig', [
            'breadcrumbs'    => (new BreadcrumbFactory($this->getRouter()))->produceFromAssessment($assessment)->add(['Create Response for ' . $assessment->getTitle(), '']),
            'assessment'     => $assessment,
            'participant'    => $student,
            'currentUser'    => $this->getUser(),
            'attachzone'     => $zone->getTwigContext(),
            'response'       => $latestResponse,
            'editorContents' => $editorContents,
        ]));
    }

    /**
     * Save a Submission to a Due Work
     *
     * @api POST /learning/assessment/:assessmentId/:participantId/submit
     * @param DueWork $assessment
     * @param User $student
     * @throws \Aura\Web\Exception\InvalidStatusCode
     */
    public function postCreateSubmission(DueWork $assessment, User $student)
    {
        /* Work out if we are going to accept responses
         */
        try {
            $assessment->isAcceptSubmission();
        }
        catch (\DomainException $e) {

            /* When asynchronously saving and suddenly you're not allowed to
             * ie. when you've run out of time and you left this screen on
             */
            if ($this->getRequest()->isXhr()) {
                $result['success'] = false;
                $result['message'] = $this->getErrorMapper()->getMessageById($e->getCode());
                $result['id']      = 72;

                $this->getResponse()->status->setCode(403);
                $this->getResponse()->content->set($this->buildJsonOutput($result));
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
        $isFinal = !$_POST['saveDraft'];
        $body = $_POST['body'];

        /* Load an existing response to update
         */
        if ($this->getRequest()->post['responseId']) {

            /* TODO: Implement with Mr Sannang's data sources
             */

            /** @var DueWorkSubmission $response */
            $response = $this->getSubmissionFileMapper()->findById($this->getRequest()->post['responseId']);
//            $response->setUpdatedAt(Alaress_Date_Format::now());
        }
        /* Create a new response
         */
        else {
            $response = new DueWorkSubmission();
            $response
                ->setAssessable($assessment)
                ->setSubmittedBy($creator)
                ->setSubmittedOnBehalfOf($student)
                ->attachOnlineSubmissionContent($body)
                ->setCreatedAt(Alaress_Date_Format::now())
                ->setUpdatedAt(Alaress_Date_Format::now());
        }

        /* If we're saving the final version of the submission
         * (ie. not a draft) ram the current datetime in
         */
        if ($isFinal) {

            /* TODO: this will die because it's not using an actual mapper/model
             */
            $response->setSubmittedAt(Alaress_Date_Format::now());
        }

        /* TODO: Do the actual saving
         */

        /* Saving drafts from the wizzywig
         */
        if ($this->getRequest()->isXhr()) {
            $result['success'] = true;
            $result['message'] = 'OK';
            $result['id']      = 72;

            $this->getResponse()->status->setCode(200);
            $this->getResponse()->content->set($this->buildJsonOutput($result));
        }
        else {
            // redirect after saving to Due Work Summary For Particular Student
            $this->getResponse()->redirect->to(
                $this->getRouter()->generate(
                    'learningAssessment.getStudentAssessment',
                    [
                        'assessmentId' => $assessment->getId(),
                        'studentId'    => $student->getId(),
                    ]
                )
            );
        }
    }

    /* Utility methods - these can probably be refactored elsewhere
     */

     /**
     * All the possible Mark Types to choose from for this particular $assessmentType
     *
     * @param IAssessable $assessable
     * @return array
     */
    private function getAllMarkTypes(IAssessable $assessable)
    {
        return MarkSchemeFactory::getAvailableMarkTypes($assessable);;
    }

    /**
     * Gets all grading schemes.
     *
     * TODO DI this if you feel like it
     *
     * @return GradeScheme[]|Collection
     */
    private function getAllGradeSchemes()
    {
        return GradeSchemeMapper::getInstance()->findAllWithGradeLevels();
    }

//    /**
//     * Displays information regarding an assessment
//     *
//     * @api   GET /learning/assessment/:assessmentId/status
//     * @level 165
//     * @param IAssessable $assessmentId
//     * @todo implement IAssessable interface
//     */
//    public function getStatus($assessmentId)
//    {
//        // this is a teacher page to show a list of students (cohort list)
//        // with each student summarised (single column of the markbook)
//        // same data structure as mark book, for a single assessable
//
//        // Replaces /submission/viewSubmission/:dueWorkId
//        //       eg /submission/viewSubmission/56
//        // Replaces /task/progressReport/:folderId/:unitId
//        //       eg /task/progressReport/177/11
//
//
//        // ==================== test URLs: ======================
//        // http://192.168.36.10/learning/assessment/1/status   - project with 1 quiz, 1 dueWork + 3 tasks
//        // http://192.168.36.10/learning/assessment/2/status   - quiz
//        // http://192.168.36.10/learning/assessment/3/status   - dueWork
//        // http://192.168.36.10/learning/assessment/4/status   - task1
//        // http://192.168.36.10/learning/assessment/5/status   - task2
//        // http://192.168.36.10/learning/assessment/6/status   - task3
//
//        // TODO - generate data
//
//        $this->getResponse()->content->set($this->buildHtmlOutput('@learningAssessment/status.twig', [
//            'breadcrumbs'      => self::getDummyBreadcrumbs(),
//            'cohortList'       => self::getDummyCohortList(),
//            'assessment'       => self::getDummyAssessment($assessmentId),
//        ]));
//    }

    /**
     * Staff member view that displays a specific student's progress in relation to a specified assessment
     *
     * @api   GET /learning/assessment/:assessmentId/:studentId
     * @level 165
     * @param IAssessable $assessment
     * @param User $student
     * @todo implement IAssessable interface
     */
    public function getStudentAssessment(IAssessable $assessment, User $student)
    {
        // TODO - replace with method arg objects
        $project = self::getDummyAssessment(7);

        // Calculate summary totals for display purposes
        $counts = [];
        foreach(AssessmentType::getStringValues() as $assessmentType){
            if(AssessmentType::TYPE_PROJECT == $assessmentType){
                continue;
            }
            $counts[$assessmentType] = 0;
            $counts["{$assessmentType}Completed"] = 0;
        }
        $counts['total'] = 0;
        $counts['totalCompleted'] = 0;

        $assessables = $project->getAssessables();

        /** @var IAssessable $assessable */
        foreach ($assessables as $assessable) {

            $participant = $assessable->getParticipant($student);

            $type = AssessmentType::objectToString($assessable);

            $counts[$type]++;
            $counts['total']++;

            // If the student has submitted at least one response we will assume it is completed

            if ($participant->getAllResponses()->count() > 0) {
                $counts["{$type}Completed"]++;
                $counts['totalCompleted']++;
            }
        }

        $twigData = [
            'breadcrumbs' => self::getDummyBreadcrumbs(),
            'student' => $student,
            'assessment' => $project,
            'counts' => $counts
        ];

        $this->getResponse()->content->set($this->buildHtmlOutput("@learningAssessment/summary/staff.twig", $twigData));
    }

    /* Getters/Setters
     */

    /**
     * @return ErrorMapper
     */
    protected function getErrorMapper()
    {
        return $this->errorMapper;
    }

    /**
     * @param ErrorMapper $errorMapper
     * @return static
     */
    protected function setErrorMapper(ErrorMapper $errorMapper)
    {
        $this->errorMapper = $errorMapper;
        return $this;
    }

    /**
     * @return SubmissionFileMapper
     */
    protected function getSubmissionFileMapper()
    {
        return $this->submissionFileMapper;
    }

    /**
     * @param SubmissionFileMapper $submissionFileMapper
     * @return static
     */
    protected function setSubmissionFileMapper(SubmissionFileMapper $submissionFileMapper)
    {
        $this->submissionFileMapper = $submissionFileMapper;
        return $this;
    }

    /**
     * @return FolderMapper
     */
    protected function getFolderMapper()
    {
        return $this->folderMapper;
    }

    /**
     * @param FolderMapper $folderMapper
     */
    protected function setFolderMapper($folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }

    /**
     * @return AssessmentMapperProxy
     */
    protected function getAssessmentMapperProxy()
    {
        return $this->assessmentMapperProxy;
    }

    /**
     * @param AssessmentMapperProxy $assessmentMapperProxy
     */
    protected function setAssessmentMapperProxy(AssessmentMapperProxy $assessmentMapperProxy)
    {
        $this->assessmentMapperProxy = $assessmentMapperProxy;
    }

    /**
     * @return UnitSpecificationMapperProxy
     */
    protected function getUnitSpecificationMapperProxy()
    {
        return $this->unitSpecificationMapperProxy;
    }

    /**
     * @param UnitSpecificationMapperProxy $unitSpecificationMapperProxy
     */
    protected function setUnitSpecificationMapperProxy(UnitSpecificationMapperProxy $unitSpecificationMapperProxy)
    {
        $this->unitSpecificationMapperProxy = $unitSpecificationMapperProxy;
    }

    /**
     * @return UnitImplementationMapperProxy
     */
    public function getUnitImplementationMapperProxy()
    {
        return $this->unitImplementationMapperProxy;
    }

    /**
     * @param UnitImplementationMapperProxy $unitImplementationMapperProxy
     * @return static
     */
    public function setUnitImplementationMapperProxy(UnitImplementationMapperProxy $unitImplementationMapperProxy)
    {
        $this->unitImplementationMapperProxy = $unitImplementationMapperProxy;
        return $this;
    }

    /**
     * @return Participant\DataSource
     */
    public function getParticipantDataSource()
    {
        return $this->participantDataSource;
    }

    /**
     * @param Participant\DataSource $participantDataSource
     * @return static
     */
    public function setParticipantDataSource(Participant\DataSource $participantDataSource)
    {
        $this->participantDataSource = $participantDataSource;
        return $this;
    }



    // ================ DUMMY DATA GENERATION ===============================================


    /**
     * Temp method to generate dummy breadcrumbs
     *
     * @todo remove this method when no longer required
     * @return array
     */
    public static function getDummyBreadcrumbs(){
        return [
            [
                'href'  => '/learning/assessment/1/status',
                'label' => 'FirstCrumb',
            ],
            [
                'href'  => '/learning/assessment/2/status',
                'label' => 'SecondCrumb',
            ],
            [
                'href'  => '/learning/assessment/3/status',
                'label' => 'ActiveCrumb',
            ],
        ];
    }

    /**
     * Temp method to return a list of students is a class.
     * @todo remove when done
     * @return Collection|User[]
     */
    public static function getDummyCohortList(){
        $internalIds = [
            230,
            239,
            242,
            245,
        ];
        return UserMapper::getInstance()->findByColumn('id', $internalIds);
    }

    /**
     * Temp method to generate dummy assessment
     *
     * @param $assessmentId      1 => project (7 => object)
     *                           2 => dueWork
     *                           3 => quiz
     *                           4 => task1
     *                           5 => task2
     *                           6 => task3
     * @todo remove this method when no longer required
     * @return array|\Schoolbox\Learning\Assessment\Project
     */
    public static function getDummyAssessment($assessmentId){
        $creator = UserMapper::getInstance()->findById(151);    // Zane Smith (teacher)

        $assessment = [
            'id'              => $assessmentId,
            'publish_date'    => new Alaress_Date_Format('2016/08/01','Y/m/d'),
            'open_date'       => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'due_date'        => new Alaress_Date_Format('2016/08/15','Y/m/d'),
            'close_date'      => new Alaress_Date_Format('2016/08/22','Y/m/d'),
            'weight'          => 40,
            'creator'         => $creator,
            'mark_visibility' => 2,
            'responses'       => self::getDummyResponses($assessmentId),
            'created_at'      => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at'      => new Alaress_Date_Format('2016/08/15','Y/m/d'),
            'deleted_at'      => new Alaress_Date_NullDate(),
        ];

        switch($assessmentId){
            case 7: // project (object)

                $hodes = UserMapper::getInstance()->findById(230);
                $sberryhill = UserMapper::getInstance()->findById(15);
                $slucier = UserMapper::getInstance()->findById(16);
                $bsalem = UserMapper::getInstance()->findById(32);
                $asaxon = UserMapper::getInstance()->findById(17);
                $celia = UserMapper::getInstance()->findById(3);
                $somodio = UserMapper::getInstance()->findById(150);

                $summatorTest = new \Schoolbox\Learning\Assessment\Test\SummatorTest();

                $project = new Project(new Minimum());
                $project->setTitle('mProject');
                $project->setDescription('This is a project. It contains assessables');
                $project->setMarkScheme(new Percentage());
                $project->setId($assessmentId);
                $project->setPublishAt(new Alaress_Date_Format('2016/08/01','Y/m/d'));
                $project->setOpenAt(new Alaress_Date_Format('2016/08/08','Y/m/d'));
                $project->setDueAt(new Alaress_Date_Format('2016/11/08','Y/m/d'));
                $project->setCloseAt(new Alaress_Date_Format('2016/11/09','Y/m/d'));
                $project->setWeight(40);
                $project->setCreator($creator);

                $project->getAllParticipants()
                    ->add(new Participant($hodes, $project))
                    ->add(new Participant($sberryhill, $project))
                    ->add(new Participant($slucier, $project))
                    ->add(new Participant($bsalem, $project))
                    ->add(new Participant($asaxon, $project))
                    ->add(new Participant($celia, $project))
                    ->add(new Participant($somodio, $project));

                $dueWork = self::spawnDueWork();
                $dueWork->setContext($project);

                $quiz = self::spawnQuiz();
                $quiz->setContext($project);

                $task = self::spawnTask();
                $task->setContext($project);

                $project->setAssessables((new Collection())
                    ->add($dueWork)
                    ->add($quiz)
                    ->add($task)
                    ->add($task)
                    ->add($task)
                );

                return $project;

            case 1: // project
                $assessment = array_merge($assessment, [
                    'assessment_type' => 'project',
                    'title'           => 'mProject',
                    'description'     => 'This is a project. It contains assessables',
                    'mark_scheme'     => self::getDummyMarkScheme(6),
                ]);

                return array_merge($assessment, [
                    'mark_summator' => [
                        'calculateMark' => 85.00,
                    ],
                    'assessables'   => [
                        self::getDummyAssessment(2),
                        self::getDummyAssessment(3),
                        self::getDummyAssessment(4),
                        self::getDummyAssessment(5),
                        self::getDummyAssessment(6),
                    ],
                ]);

            case 2: // dueWork
                $assessment = array_merge($assessment, [
                    'assessment_type' => 'dueWork',
                    'title'           => 'mDueWork',
                    'description'     => 'This is a dueWork. It does not contain assessables, but has extra dueWork-related info',
                    'mark_scheme'     => self::getDummyMarkScheme(2),
                ]);
                return array_merge($assessment, [
                    'accept_submission'  => true,
                    'multi_submission'   => true,
                    'skip_unsubmitted'   => 0,
                    'work_type'          => WorkTypeMapper::getInstance()->findById(1),
                    'files'              => [
                        self::getDummyDueWorkFile(1),
                        self::getDummyDueWorkFile(2),
                    ],
                ]);
            case 3: // quiz
                $assessment = array_merge($assessment, [
                    'assessment_type' => 'quiz',
                    'title'           => 'mQuiz',
                    'description'     => 'This is a quiz. It does not contain assessables, but has extra quiz-related info',
                    'mark_scheme'     => self::getDummyMarkScheme(1),
                ]);
                return array_merge($assessment, [
                    'sections' => [
                        self::getDummyQuizSection(1),
                        self::getDummyQuizSection(2),
                    ],
                    'attempts' => [
                        self::getDummyQuizAttempt(1),
                        self::getDummyQuizAttempt(2),
                        self::getDummyQuizAttempt(3),
                    ]
                ]);
            case 4: // task1
                $assessment = array_merge($assessment, [
                    'assessment_type' => 'task',
                    'title'           => 'mTask1',
                    'description'     => 'This is a task. It does not contain assessables, but has extra task-related info',
                    'mark_scheme'     => self::getDummyMarkScheme(3),
                ]);
                return array_merge($assessment, [
                    'link'            => 'https://www.youtube.com/watch?v=vShCnTY1-T0',
                    'isSelfMarkable'  => false,
                ]);
            case 5: // task2
                $assessment = array_merge($assessment, [
                    'assessment_type' => 'task',
                    'title'           => 'mTask2',
                    'description'     => 'This is a task. It does not contain assessables, but has extra task-related info',
                    'mark_scheme'     => self::getDummyMarkScheme(4),
                ]);
                return array_merge($assessment, [
                    'link'            => '',
                    'isSelfMarkable'  => true,
                ]);
            case 6: // task3
            default:
                $assessment = array_merge($assessment, [
                    'assessment_type' => 'task',
                    'title'           => 'mTask3',
                    'description'     => 'This is a task. It does not contain assessables, but has extra task-related info',
                    'mark_scheme'     => self::getDummyMarkScheme(5),
                ]);
                return array_merge($assessment, [
                    'link'            => 'https://www.youtube.com/watch?v=vShCnTY1-T0',
                    'isSelfMarkable'  => true,
                ]);
        }
    }

    /**
     * Temp method to generate dummy student responses
     *
     * @param $assessmentId      1 => project
     *                           2 => dueWork
     *                           3 => quiz
     *                           4 => task1
     *                           5 => task2
     *                           6 => task3
     * @todo remove this method when no longer required
     * @return array
     */
    public static function getDummyResponses($assessmentId)
    {
        switch($assessmentId){
            case 1: // project
                return array_merge(
                    self::getDummyResponses(2),
                    self::getDummyResponses(3),
                    self::getDummyResponses(4),
                    self::getDummyResponses(5),
                    self::getDummyResponses(6)
                );
            case 2: // dueWork
                return [
                    self::getDummySubmission(1),
                    self::getDummySubmission(2),
                    self::getDummySubmission(3),
                ];
            case 3: // quiz
                return [
                    self::getDummyQuizAttempt(1),
                    self::getDummyQuizAttempt(2),
                    self::getDummyQuizAttempt(3),
                ];
            case 4: // task1
                return [ ];
            case 5: // task2
                return [
                    self::getDummyTaskCompletion(1),
                ];
            case 6: // task3
                return [
                    self::getDummyTaskCompletion(1),
                    self::getDummyTaskCompletion(2),
                ];
        }

        return [];
    }


    /**
     *
     * @param int $dueWorkFileId   enum {1,2}
     * @return array
     */
    public static function getDummyDueWorkFile($dueWorkFileId)
    {
        $dueWorkFile = [
            'id'      => $dueWorkFileId,
            'created_at' => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
            'deleted_at' => new Alaress_Date_NullDate(),
        ];
        switch($dueWorkFileId) {
            case 1:
                return array_merge($dueWorkFile, [
                    'hash'     => '8dc741fccc7a612bc0643caa0f1ced422497f3ae',
                    'fileName' => 'Hiking this weekend',
                ]);
            case 2:
            default;
                return array_merge($dueWorkFile, [
                    'hash'     => 'd4e7439b046c63787429176aec1efc930209f5c3',
                    'fileName' => 'articleday3',
                ]);
        }
    }


    /**
     * Generates a dummy dueWork Submission
     *
     * @param int $dueWorkFeedbackId    enum {1,2,3,4}
     * @todo remove when done
     * @return array
     */
    public static function getDummyDueWorkFeedback($dueWorkFeedbackId)
    {
        $dueWorkFeedback = [
            'id'           => $dueWorkFeedbackId,
            'feedbackType' => 'dueWorkFeedback',
            'reviewer'     => UserMapper::getInstance()->findById(151),
            'created_at'   => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at'   => new Alaress_Date_Format('2016/08/15','Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];
        switch($dueWorkFeedbackId) {
            case 1:
                return array_merge($dueWorkFeedback, [
                    'mark'         => 'A+',
                    'norm_mark'    => 95.00000,
                    'published_at' => new Alaress_Date_NullDate(),
                    'comment'      => 'Excellent work',
                    'files'        => [
                        self::getDummyDueWorkFeedbackFile(1),
                    ],
                ]);
            case 2:
                return array_merge($dueWorkFeedback, [
                    'mark'         => 'C',
                    'norm_mark'    => 70.00000,
                    'published_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                    'comment'      => 'You are going ok',
                    'files' => [
                        self::getDummyDueWorkFeedbackFile(2),
                        self::getDummyDueWorkFeedbackFile(3),
                    ],
                ]);
            case 3:
                return array_merge($dueWorkFeedback, [
                    'mark'         => 'F',
                    'norm_mark'    => 30.00000,
                    'published_at' => new Alaress_Date_NullDate(),
                    'comment'      => 'You need help (this is a draft)',
                    'files'        => [ ],
                ]);
            case 4:
            default:
                return array_merge($dueWorkFeedback, [
                'mark'             => 'F',
                'norm_mark'        => 30.00000,
                'published_at'     => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                'comment'          => 'You need help (this is published)',
                'files'            => [ ],
            ]);
        }
    }


    /**
     * @param int $dueWorkFeedbackFileId   enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummyDueWorkFeedbackFile($dueWorkFeedbackFileId)
    {
        $dueWorkFeedbackFile = [
            'id'           => $dueWorkFeedbackFileId,
            'created_at'   => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at'   => new Alaress_Date_Format('2016/08/15','Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];
        switch($dueWorkFeedbackFileId){
            case 1:
                return array_merge($dueWorkFeedbackFile, [
                    'crocodocUUID' => null,
                    'hash'         => '1e5b92268bf8f2370c5cf9e6526f1f35c3b329f1',
                    'fileName'     => 'ClassQuestions',
                ]);
            case 2:
                return array_merge($dueWorkFeedbackFile, [
                    'crocodocUUID' => '75ac23be-4210-43cf-93e1-77c172afab78',
                    'hash'         => '4b50da92d887f9e05804f7425125b5064f5cb385',
                    'fileName'     => 'quantum-teleportation',
                ]);
            case 3:
            default:
                return array_merge($dueWorkFeedbackFile, [
                    'crocodocUUID' => '04943e5c-ade8-4e1b-8e54-80c77c6a66f1',
                    'hash'         => '5bdd49d040562d9418ec4fd34a49c78a9eacf08b',
                    'fileName'     => 'questions-for-walter',
                ]);
        }
    }


    /**
     * Generates a dummy dueWork Submission
     *
     * @param int $dueWorkSubmissionId    enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummySubmission($dueWorkSubmissionId)
    {
        $submission = [
            'id'           => $dueWorkSubmissionId,
            'responseType' => 'dueWorkSubmission',
            'created_at'   => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];
        switch ($dueWorkSubmissionId) {
            case 1:
                return array_merge($submission, [
                    'submitted_by'  => UserMapper::getInstance()->findById(230),
                    'submitted_for' => UserMapper::getInstance()->findById(245),
                    'updated_at'   => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                    'files'         => [
                        self::getDummyDueWorkSubmissionFile(1),
                        self::getDummyDueWorkSubmissionFile(2),
                    ],
                    'feedbacks'     => [
                        self::getDummyDueWorkFeedback(1),
                    ],
                ]);
            case 2:
                return array_merge($submission, [
                    'submitted_by'  => UserMapper::getInstance()->findById(230),
                    'submitted_for' => UserMapper::getInstance()->findById(245),
                    'updated_at'   => new Alaress_Date_Format('2016/08/16','Y/m/d'),
                    'files'         => [
                        self::getDummyDueWorkSubmissionFile(1),
                        self::getDummyDueWorkSubmissionFile(2),
                    ],
                    'feedbacks'     => [
                        self::getDummyDueWorkFeedback(1),
                    ],
                ]);
            case 3:
            default;
                return array_merge($submission, [
                    'submitted_by'  => UserMapper::getInstance()->findById(230),
                    'submitted_for' => UserMapper::getInstance()->findById(245),
                    'updated_at'   => new Alaress_Date_Format('2016/08/17','Y/m/d'),
                    'files'         => [
                        self::getDummyDueWorkSubmissionFile(1),
                        self::getDummyDueWorkSubmissionFile(2),
                    ],
                    'feedbacks'     => [
                        self::getDummyDueWorkFeedback(1),
                    ],
                ]);
        }
    }

    /**
     * Get dummy dueWork Submission File
     *
     * @param int $dueWorkSubmissionFileId      enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummyDueWorkSubmissionFile($dueWorkSubmissionFileId)
    {
        $dueWorkSubmissionFile = [
            'id'           => $dueWorkSubmissionFileId,
            'created_at'   => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at'   => new Alaress_Date_Format('2016/08/17','Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];

        switch($dueWorkSubmissionFileId) {
            case 1:
                return array_merge($dueWorkSubmissionFile, [
                    'crocodocUUID' => 'e367160c-0202-4034-83f7-e4643a953bbb',
                    'hash'         => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                    'fileName'     => 'portfolio',
                    'plagScan'     => [ ],
                    'storage'      => self::getDummyStorage(1),
                ]);
            case 2:
                return array_merge($dueWorkSubmissionFile, [
                    'crocodocUUID' => '75ac23be-4210-43cf-93e1-77c172afab78',
                    'hash'         => '4b50da92d887f9e05804f7425125b5064f5cb385',
                    'fileName'     => 'quantum-teleportation',
                    'plagScan'     => [
                        self::getDummyPlagScan(1),
                    ],
                    'storage'      => self::getDummyStorage(2),
                ]);
            case 3:
            default:
                return array_merge($dueWorkSubmissionFile, [
                    'crocodocUUID' => null,
                    'hash'         => '1e5b92268bf8f2370c5cf9e6526f1f35c3b329f1',
                    'filename'     => 'ClassQuestions',
                    'plagScan'     => [
                        self::getDummyPlagScan(2),
                    ],
                    'storage'      => self::getDummyStorage(3),
                ]);
        }
    }

    /**
     * @param int $storageId        enum {1,2,3,4,5,6,7}
     * @return array
     */
    public static function getDummyStorage($storageId){
        $storage = [
            'id'           => $storageId,
            'mimetype'     => self::getDummyMimeType($storageId),
            'created_at'   => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at'   => new Alaress_Date_Format('2016/08/17','Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];
        switch($storageId){
            case 1:
                return array_merge($storage, [
                    'extension'  => 'docx',
                    'filename'   => 'someFile',
                    'basename'   => 'someFile.docx',
                    'mime'       => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'size'       => 25000,
                    'hash'       => '4b50da92d887f9e05804f7425125b5064f5cb385',
                ]);
            case 2:
                return array_merge($storage, [
                    'extension'  => 'gif',
                    'filename'   => 'abc',
                    'basename'   => 'abc.gif',
                    'mime'       => 'image/gif',
                    'size'       => 25000,
                    'hash'       => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
            case 3:
                return array_merge($storage, [
                    'extension'  => 'mpeg',
                    'filename'   => 'myVideo',
                    'basename'   => 'myVideo.mpeg',
                    'mime'       => 'video/mpeg',
                    'size'       => 25000,
                    'hash'       => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
            case 4:
                return array_merge($storage, [
                    'extension'  => 'mpeg',
                    'filename'   => 'mAudio',
                    'basename'   => 'mAudio.mpeg',
                    'mime'       => 'audio/mpeg',
                    'size'       => 25000,
                    'hash'       => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
            case 5:
                return array_merge($storage, [
                    'extension'  => 'html',
                    'filename'   => 'index',
                    'basename'   => 'index.html',
                    'mime'       => 'text/html',
                    'size'       => 4000,
                    'hash'       => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
            case 6:
            default:
                return array_merge($storage, [
                    'extension'  => 'zip',
                    'filename'   => 'mArchive',
                    'basename'   => 'mArchive.zip',
                    'mime'       => 'application/x-7z-compressed',
                    'size'       => 25000,
                    'hash'       => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
        }
    }

    /**
     * @param int $mimeTypeId        enum {1,2,3,4,5,6,7}
     * @return array
     */
    public static function getDummyMimeType($mimeTypeId){
        $mimeType = [
            'id'                => $mimeTypeId,
            'actualMaxFileSize' => 1000000,
            'maxFileSize'       => 1000000,
            'enable'            => true,
            'isEnabled'         => true,
            'deleted'           => false,
            'isDeleted'         => false,
        ];
        switch($mimeTypeId){
            case 1:
                return array_merge($mimeType, [
                    'fileTypeId'        => 46,
                    'class'             => 'docx',
                    'description'       => 'Microsoft Word 2007 Document',
                    'iconFile'          => 'msword_doc.png',
                    'iconClass'         => 'msworddoc',
                    'inline'            => false,
                    'mime'              => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'isAudio'           => false,
                    'isVideo'           => false,
                    'isImage'           => false,
                    'type'              => 'application',
                ]);
            case 2:
                return array_merge($mimeType, [
                    'fileTypeId'        => 1,
                    'class'             => 'gif',
                    'description'       => 'GIF Image',
                    'iconFile'          => 'image_gif.png',
                    'iconClass'         => 'imagegif',
                    'inline'            => true,
                    'mime'              => 'image/gif',
                    'isAudio'           => false,
                    'isVideo'           => false,
                    'isImage'           => true,
                    'type'              => 'image',
                ]);
            case 3:
                return array_merge($mimeType, [
                    'fileTypeId'        => 27,
                    'class'             => 'video',
                    'description'       => 'MPEG Video',
                    'iconFile'          => 'video_alt.png',
                    'iconClass'         => 'videoalt',
                    'inline'            => false,
                    'mime'              => 'video/mpeg',
                    'isAudio'           => false,
                    'isVideo'           => true,
                    'isImage'           => false,
                    'type'              => 'video',
                ]);
            case 4:
                return array_merge($mimeType, [
                    'fileTypeId'        => 23,
                    'class'             => 'mpeg',
                    'description'       => 'MPEG Audio',
                    'iconFile'          => 'sound_mp3.png',
                    'iconClass'         => 'soundmp3',
                    'inline'            => false,
                    'mime'              => 'audio/mpeg',
                    'isAudio'           => true,
                    'isVideo'           => false,
                    'isImage'           => false,
                    'type'              => 'audio',
                ]);
            case 5:
                return array_merge($mimeType, [
                    'fileTypeId'        => 2,
                    'class'             => 'html',
                    'description'       => 'HTML Document',
                    'iconFile'          => 'html.png',
                    'iconClass'         => 'html',
                    'inline'            => true,
                    'mime'              => 'text/html',
                    'isAudio'           => false,
                    'isVideo'           => false,
                    'isImage'           => false,
                    'type'              => 'text',
                ]);
            case 6:
            default:
                return array_merge($mimeType, [
                    'fileTypeId'        => 80,
                    'class'             => 'zip',
                    'description'       => '7-Zip',
                    'iconFile'          => 'archive.png',
                    'iconClass'         => 'archive',
                    'inline'            => false,
                    'mime'              => 'application/x-7z-compressed',
                    'isAudio'           => false,
                    'isVideo'           => false,
                    'isImage'           => false,
                    'type'              => 'application',
                ]);
        }
    }

    /**
     * Generate a dummy plag scan doc
     *
     * @param int $plagScanId    enum {1,2}
     * @todo remove when done
     * @return array
     */
    public static function getDummyPlagScan($plagScanId){
        $plagScan = [
            'id'           => $plagScanId,
            'created_at'   => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at'   => new Alaress_Date_Format('2016/08/17','Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];
        switch($plagScanId){
            case 1:
                return array_merge($plagScan, [
                    'plagscan_id' => 4396294,
                    'status'      => 3,
                    'score'       => 99,
                    'html'        => '<html><head><title>Hello</title></head><body><p>This is plagscan 1</p></body></html>',
                    'deleted_at'  => null,
                ]);
            case 2:
            default:
                return array_merge($plagScan, [
                    'plagscan_id' => 4396541,
                    'status'      => 3,
                    'score'       => 0.1,
                    'html'        => '<html><head><title>Hello</title></head><body><p>This is some content for plagscan 2</p></body></html>',
                    'deleted_at'  => null,
                ]);
        }
    }


    /**
     * Temp method to generate dummy mark scheme
     *
     * @param $scheme    1 => percentage
     *                   2 => letterGrade
     *                   3 => totalMark
     *                   4 => none
     *                   5 => Completion
     * @todo remove this method when no longer required
     * @return array
     */
    public static function getDummyMarkScheme($scheme){
        switch($scheme){
            case 1:
                return [
                    'name'             => 'Percentage',
                    'getHumanReadable' => '32 %',
                    'getNormalised'    => 32.000000000,
                ];
            case 2:
                return [
                    'name'             => 'LetterGrade',
                    'gradeGroup'       => [
                        'id'     => 3,
                        'name'   => 'Competency',
                        'grades' => [
                            [
                                'id'          => 13,
                                'lower_bound' => 0,
                                'raw_grade'   => 20,
                                'caption'     => 'Needs Attention',
                            ],
                            [
                                'id'          => 14,
                                'lower_bound' => 40,
                                'raw_grade'   => 53,
                                'caption'     => 'Acceptable',
                            ],
                            [
                                'id'          => 15,
                                'lower_bound' => 60,
                                'raw_grade'   => 68,
                                'caption'     => 'Good',
                            ],
                            [
                                'id'          => 16,
                                'lower_bound' => 75,
                                'raw_grade'   => 88,
                                'caption'     => 'Very Good',
                            ],
                            [
                                'id'          => 17,
                                'lower_bound' => 90,
                                'raw_grade'   => 95,
                                'caption'     => 'Excellent',
                            ],
                        ],
                    ],
                    'getHumanReadable' => 'Very Good',
                    'getNormalised'    => 88.000000,
                ];
            case 3:
                return [
                    'name'             => 'TotalMark',
                    'maxMark'          => 10,
                    'getHumanReadable' => '6 / 10',
                    'getNormalised'    => 60.00000000,
                ];
            case 4:
                return [
                    'name'             => 'None',
                    'getHumanReadable' => null,
                    'getNormalised'    => null,
                ];
            case 5:
                return [
                    'name'             => 'Completion',
                    'status'           => 2,
                    'getHumanReadable' => 'Teacher Confirmed',
                    'getNormalised'    => null,
                ];
            case 6:
            default:
                return [
                    'name'             => 'LetterGrade',
                    'gradeGroup'       => [
                        'id'     => 1,
                        'name'   => 'Standard',
                        'grades' => [
                            [
                                'id'          => 1,
                                'lower_bound' => 0,
                                'raw_grade'   => 20,
                                'caption'     => 'F',
                            ],
                            [
                                'id'          => 1,
                                'lower_bound' => 50,
                                'raw_grade'   => 55,
                                'caption'     => 'E',
                            ],
                            [
                                'id'          => 2,
                                'lower_bound' => 60,
                                'raw_grade'   => 65,
                                'caption'     => 'D',
                            ],
                            [
                                'id'          => 3,
                                'lower_bound' => 70,
                                'raw_grade'   => 75,
                                'caption'     => 'C',
                            ],
                            [
                                'id'          => 4,
                                'lower_bound' => 80,
                                'raw_grade'   => 85,
                                'caption'     => 'B',
                            ],
                            [
                                'id'          => 5,
                                'lower_bound' => 90,
                                'raw_grade'   => 95,
                                'caption'     => 'A',
                            ],
                        ],
                    ],
                    'getHumanReadable' => 'B',
                    'getNormalised'    => 85.000000,
                ];
        }
    }


    /**
     * Create a dummy quiz section
     *
     * @param $sectionId
     * @return array
     */
    public static function getDummyQuizSection($sectionId){
        switch($sectionId){
            case 1:
                return [
                    'id'            => 1,
                    'title'         => 'mFirstSection',
                    'description'   => 'This is the first section of this quiz',
                    'random'        => 0,
                    'pool'          => 0,
                    'questions'     => [
                        self::getDummyQuizQuestion(1,true),
                    ],
                    'created_at' => new Alaress_Date_Format('2016/08/08','Y/m/d'),
                    'updated_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                    'deleted_at' => new Alaress_Date_NullDate(),
                ];
            case 2:
            default:
                return [
                    'id'            => 2,
                    'title'         => 'mSecondSection',
                    'description'   => 'This is the second section of this quiz',
                    'random'        => 0,
                    'pool'          => 0,
                    'questions'     => [
                        self::getDummyQuizQuestion(2,true),
                        self::getDummyQuizQuestion(3,true),
                    ],
                    'created_at' => new Alaress_Date_Format('2016/08/08','Y/m/d'),
                    'updated_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                    'deleted_at' => new Alaress_Date_NullDate(),
                ];
        }
    }



    /**
     * Generates a dummy quiz attempt for the specified user
     *
     * @param int $attemptId      enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummyQuizAttempt($attemptId){
        $attempt= [
            'id'          => $attemptId,
            'responseType'=> 'quizAttempt',
            'start'       => new Alaress_Date_Format('2012/07/11 12:02:39','Y/m/d H:m:s'),
            'finish'      => new Alaress_Date_Format('2012/07/12 12:05:20','Y/m/d H:m:s'),
            'reviewed_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
            'deleted_at'  => new Alaress_Date_NullDate(),
        ];

        switch($attemptId){
            case 1:
                $attempt['feedbacks'] = [
                    self::getDummyQuizAnswer(1,true),
                ];
                return $attempt;
            case 2:
                $attempt['feedbacks'] = [
                    self::getDummyQuizAnswer(2,true),
                    self::getDummyQuizAnswer(3,true),
                    self::getDummyQuizAnswer(4,true),
                ];
                return $attempt;
            case 3:
            default:
                $attempt['feedbacks'] = [
                    self::getDummyQuizAnswer(5,true),
                    self::getDummyQuizAnswer(6,true),
                    self::getDummyQuizAnswer(7,true),
                ];
                return $attempt;
        }
    }

    /**
     * Generates a dummy question, along with its solutions
     *
     * @param int $questionId            enum {1,2}
     * @param bool $nestAnswers          whether to nest answers inside question
     * @todo remove when done
     * @return array
     */
    public static function getDummyQuizQuestion($questionId, $nestAnswers)
    {
        $question = [
            'type'       => self::getDummyQuizQuestionType(1),
            'marks'      => 1.00,
            'config'     => null,
            'created_at' => new Alaress_Date_Format('2016/08/08','Y/m/d'),
            'updated_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
            'deleted_at' => new Alaress_Date_NullDate(),
        ];

        switch ($questionId) {
            case 1:
                $question['id'] = 1;
                $question['question'] = '<div class="ff4">They used to say that a child conceived in love has a greater chance</div><div class="ff4">of... What?</div><div class="ff4">';
                $question['solution'] = [
                    'id'         => 1,
                    'solution'   => 'Happiness',
                    'created_at' => new Alaress_Date_Format('2016/08/08','Y/m/d'),
                    'updated_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                    'deleted_at' => new Alaress_Date_NullDate(),
                ];
                $question['answers'] = [
                    self::getDummyQuizAnswer(1,false),
                    self::getDummyQuizAnswer(2,false),
                    self::getDummyQuizAnswer(5,false),
                ];
                return $question;
            case 2:
                $question['id']       = 2;
                $question['question'] = '<<p>What is the weather in Bangalore right now?</p>';
                $question['solution'] = [
                    'id'         => 2,
                    'solution'   => 'Cloudy with a chance of meatballs',
                    'created_at' => new Alaress_Date_Format('2016/08/08','Y/m/d'),
                    'updated_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                    'deleted_at' => new Alaress_Date_NullDate(),
                ];
                $question['answers'] = [
                    self::getDummyQuizAnswer(3,false),
                    self::getDummyQuizAnswer(6,false),
                ];
                return $question;
            case 3:
            default:
                $question['id']       = 3;
                $question['question'] = '<<p>What is the color of the sky at night?</p>';
                $question['solution'] = [
                    'id'         => 3,
                    'solution'   => 'Black',
                    'created_at' => new Alaress_Date_Format('2016/08/08','Y/m/d'),
                    'updated_at' => new Alaress_Date_Format('2016/08/15','Y/m/d'),
                    'deleted_at' => new Alaress_Date_NullDate(),
                ];
            $question['answers'] = [
                self::getDummyQuizAnswer(4,false),
                self::getDummyQuizAnswer(7,false),
            ];
                return $question;

        }
    }


    /**
     * Generates data for a questionType
     *
     * @param int $questionType
     * @todo remove when done
     * @return array
     */
    public static function getDummyQuizQuestionType($questionType){
        switch ($questionType){
            case 1:
            default:
                return [
                    'id'                => $questionType,
                    'classname'         => 'Short',
                    'filename'          => 'short',
                    'description'       => 'Short Answer',
                    'moodle'            => 'shortanswer',
                    'automated_marking' => false,
                    'availability'      => 'all',
                    'has_legend'        => false,
                    'has_config'        => false,
                ];
        }
    }

    /**
     * Returns a QuizAnswer. (Formerly known as 'QuizResponse')
     * This represents a student answer to a question. Their answer may be right or wrong.
     *
     * @param int $answerId       enum {1,2,3,4,5,6,7}
     * @param bool $nestQuestion  Whether to return nested questions for this answer
     * @todo remove when not needed
     * @return array
     */
    public static function getDummyQuizAnswer($answerId, $nestQuestion)
    {
        $answer = [
            'id'           => $answerId,
            'feedbackType' => 'quizAnswer',
            'updated_at'   => new Alaress_Date_NullDate(),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];

        switch($answerId){
            case 1:
                return array_merge($answer, [
                    'answer'   => 'Sadness',
                    'comment'  => '',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(1,false) : null,
                ]);
            case 2:
                return array_merge($answer, [
                    'answer'   => 'Happiness',
                    'comment'  => 'Very good',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(1,false) : null,
                ]);
            case 3:
                return array_merge($answer, [
                    'answer'   => 'I think it is raining',
                    'comment'  => 'No you are wrong',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(2,false) : null,
                ]);
            case 4:
                return array_merge($answer, [
                    'answer'   => 'Blue',
                    'comment'  => 'Not quite',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(3,false) : null,
                ]);
            case 5:
                return array_merge($answer, [
                    'answer'   => 'Happiness',
                    'comment'  => 'Good work',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(1,false) : null,
                ]);
            case 6:
                return array_merge($answer, [
                    'answer'   => 'I think it is sunny',
                    'comment'  => 'Pretty close',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(2,false) : null,
                ]);
            case 7:
                return array_merge($answer, [
                    'answer'   => 'Yellow',
                    'comment'  => '',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(3,false) : null,
                ]);
        }

        return [];
    }

    /**
     * Generate a dummy task completion
     *
     * @param int $taskCompletionId    enum {1,2}
     * @todo remove when done
     * @return array
     */
    public static function getDummyTaskCompletion($taskCompletionId){
        $taskCompletion = [
            'id'           => $taskCompletionId,
            'responseType' => 'taskCompletion',
        ];
        switch($taskCompletionId){
            case 1:
                return array_merge($taskCompletion, [
                    'student'   => UserMapper::getInstance()->findById(230),
                    'feedbacks' => [
                        self::getDummyTaskFeedback(1),
                    ]
                ]);
            case 2:
            default:
            return array_merge($taskCompletion, [
                'student'   => UserMapper::getInstance()->findById(245),
                'feedbacks' => [
                    self::getDummyTaskFeedback(2),
                    self::getDummyTaskFeedback(3),
                ]
            ]);
        }
    }


    /**
     * Generate a dummy task feedback
     *
     * @param int $taskFeedbackId   enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummyTaskFeedback($taskFeedbackId){
        $taskFeedback = [
            'id'           => $taskFeedbackId,
            'feedbackType' => 'taskFeedback',
        ];
        switch($taskFeedbackId){
            case 1:
                return array_merge($taskFeedback, [
                    'reviewer' => UserMapper::getInstance()->findById(230),
                ]);
            case 2:
                return array_merge($taskFeedback, [
                    'reviewer' => UserMapper::getInstance()->findById(245),
                ]);
            case 3:
            default:
                return array_merge($taskFeedback, [
                    'reviewer' => UserMapper::getInstance()->findById(151),
                ]);
        }
    }









    /** These are dummy assessable objects used to test the assessment summary, nothing elegant here */

    /**
     * Spawn a Due Work
     *
     * @return DueWork
     */
    protected function spawnDueWork()
    {
        $hodes = (new User)->setId(230)->setFirstname('David')->setLastname('Rhodes');
        $sberryhill = (new User)->setId(15)->setFirstname('Scott')->setLastname('Berryhill');
        $slucier = (new User)->setId(16)->setFirstname('Sam')->setLastname('Lucier');
        $bsalem = (new User)->setId(32)->setFirstname('Bobby')->setLastname('Salem');
        $asaxon = (new User)->setId(17)->setFirstname('Anita')->setLastname('Saxon');
        $celia = (new User)->setId(3)->setFirstname('Celia')->setLastname('Glenn');
        $somodio = (new User)->setId(150)->setFirstname('Anelie')->setLastname('Somodio');

        $id = 2;
        $title = 'Enter the Realm of Terroarrrr';
        $description = 'Diablo was found in the land of terroarrrrr! Find him, and whack him!';
        $typeName = 'Essay';
        $dateFormat = 'Y-m-d H:i:s';
        $dateInit = '2016-09-08 11:30:00';
        $dueWork = new DueWork();
        $dueWork
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setType((new DueWorkType())
                ->setId(1)
                ->setName($typeName))
            ->setCreator($somodio)
            ->setAllParticipants((new Collection())
                ->add(new Participant($hodes, $dueWork))
                ->add(new Participant($sberryhill, $dueWork))
                ->add(new Participant($slucier, $dueWork))
                ->add(new Participant($bsalem, $dueWork))
                ->add(new Participant($asaxon, $dueWork))
                ->add(new Participant($celia, $dueWork))
                ->add(new Participant($somodio, $dueWork))
            )
            ->setWeight(5)
            ->setAcceptOnline(true)
            ->setAcceptsMultiple(true)
            ->setPublishAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(1))
            ->setOpenAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(1))
            ->setDueAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(57))
            ->setCloseAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(60))
            ->setCreatedAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(1))
            ->setUpdatedAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(1));

        $submission = new DueWorkSubmission();
        $submission
            ->setId(553)
            ->setAssessable($dueWork)
            ->setAllFiles(
                (new Collection())->add(
                    \Schoolbox\Resource\Storage\Mapper::getInstance()->findByHash('4b50da92d887f9e05804f7425125b5064f5cb385')
                )
            )
            ->setSubmittedBy($hodes)
            ->setSubmittedOnBehalfOf($hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-01 09:00:00', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = (new DueWorkFeedback())
            ->setId(3385)
            ->setAssessable($dueWork)
            ->setNormalisedMark(-3)
            ->setReviewer($celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-03 14:15:16', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:14:15', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:14:12', \Alaress_Date_Format::DATABASE_FORMAT));

        if (rand(1,3) <= 1) {
            $dueWork->getParticipant($hodes)->getAllResponses()->add($submission);
            $dueWork->getParticipant($hodes)->getAllFeedbacks()->add($feedback);
        }
        if (rand(1,3) <= 1) {
            $dueWork->getParticipant($sberryhill)->getAllResponses()->add($submission);
            $dueWork->getParticipant($sberryhill)->getAllFeedbacks()->add($feedback);
        }
        if (rand(1,3) <= 1) {
            $dueWork->getParticipant($slucier)->getAllResponses()->add($submission);
            $dueWork->getParticipant($slucier)->getAllFeedbacks()->add($feedback);
        }
        if (rand(1,3) <= 1) {
            $dueWork->getParticipant($bsalem)->getAllResponses()->add($submission);
            $dueWork->getParticipant($bsalem)->getAllFeedbacks()->add($feedback);
        }
        if (rand(1,3) <= 1) {
            $dueWork->getParticipant($asaxon)->getAllResponses()->add($submission);
            $dueWork->getParticipant($asaxon)->getAllFeedbacks()->add($feedback);
        }
        if (rand(1,3) <= 1) {
            $dueWork->getParticipant($celia)->getAllResponses()->add($submission);
            $dueWork->getParticipant($celia)->getAllFeedbacks()->add($feedback);
        }
        if (rand(1,3) <= 1) {
            $dueWork->getParticipant($somodio)->getAllResponses()->add($submission);
            $dueWork->getParticipant($somodio)->getAllFeedbacks()->add($feedback);
        }


        $submission->getAllFeedback()->add($feedback);

        /* Freefloating Feedback
         *
         * This Feedback isn't associated with a particular Submission,
         * just the Assessable itself
         */
        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4223)
            ->setAssessable($dueWork)
            ->setNormalisedMark(37)
            ->setReviewer($celia)
            ->setAllFiles(new Collection())
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 22:22:13', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-01 13:19:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-02 14:44:42', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4236)
            ->setAssessable($dueWork)
            ->setNormalisedMark(100)
            ->setComment('meow - this is the most recently published feedback, despite the ordering in which this appears')
            ->setReviewer($celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 23:43:43', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 23:41:41', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 23:41:41', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($hodes)->getAllFeedbacks()->add($floatingFeedback);

        $floatingFeedback = (new DueWorkFeedback())
            ->setId(4225)
            ->setAssessable($dueWork)
            ->setNormalisedMark(10)
            ->setReviewer($celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-06 23:23:25', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 23:23:23', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 23:23:21', \Alaress_Date_Format::DATABASE_FORMAT));

        $dueWork->getParticipant($hodes)->getAllFeedbacks()->add($floatingFeedback);

        return $dueWork;
    }

    /**
     * Spawn a Task
     */
    protected function spawnTask()
    {
        $hodes = (new User)->setId(230)->setFirstname('David')->setLastname('Rhodes');
        $sberryhill = (new User)->setId(15)->setFirstname('Scott')->setLastname('Berryhill');
        $slucier = (new User)->setId(16)->setFirstname('Sam')->setLastname('Lucier');
        $bsalem = (new User)->setId(32)->setFirstname('Bobby')->setLastname('Salem');
        $asaxon = (new User)->setId(17)->setFirstname('Anita')->setLastname('Saxon');
        $celia = (new User)->setId(3)->setFirstname('Celia')->setLastname('Glenn');
        $somodio = (new User)->setId(150)->setFirstname('Anelie')->setLastname('Somodio');

        $id = 1234;
        $title = 'Read the Horadric Scrolls';
        $description = 'There is a book by Deckard Cain which you must read before searching for Diablo.';
        $dateFormat = 'Y-m-d H:i:s';
        $dateInit = '2016-09-08 11:30:00';

        $task = new Task();
        $task
            ->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($celia)
            ->setAllParticipants((new Collection())
                ->add(new Participant($hodes, $task))
                ->add(new Participant($sberryhill, $task))
                ->add(new Participant($slucier, $task))
                ->add(new Participant($bsalem, $task))
                ->add(new Participant($asaxon, $task))
                ->add(new Participant($celia, $task))
                ->add(new Participant($somodio, $task))
            )->setWeight(4)
            ->setPublishAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(1))
            ->setOpenAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(1))
            ->setDueAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(57))
            ->setCloseAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(60))
            ->setCreatedAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(1))
            ->setUpdatedAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(1));

        $completion = (new TaskCompletion);
        $completion
            ->setId(883)
            ->setAssessable($task)
            ->setSubmittedBy($hodes)
            ->setSubmittedOnBehalfOf($hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-06 10:01:22', \Alaress_Date_Format::DATABASE_FORMAT));

        $feedback = new TaskFeedback();
        $feedback
            ->setId(87)
            ->setAssessable($task)
            ->setComment('This is the most recently published feedback')
            ->setNormalisedMark(999999)
            ->setReviewer($celia)
            ->setPublishedAt(new \Alaress_Date_Format('2016-09-15 01:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-07 19:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $floatingFeedback = new TaskFeedback();
        $floatingFeedback
            ->setId(89)
            ->setAssessable($task)
            ->setNormalisedMark(8888888)
            ->setReviewer($celia)
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-07 19:03:20', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-07 19:03:20', \Alaress_Date_Format::DATABASE_FORMAT));

        $completion->getAllFeedback()->add($feedback);

        if (rand(1,2) <= 1) {
            $task->getParticipant($hodes)->getAllResponses()->add($completion);
            $task->getParticipant($hodes)->getAllFeedbacks()->add($feedback);
            $task->getParticipant($hodes)->getAllFeedbacks()->add($floatingFeedback);
        }
        if (rand(1,2) <= 1) {
            $task->getParticipant($sberryhill)->getAllResponses()->add($completion);
            $task->getParticipant($sberryhill)->getAllFeedbacks()->add($feedback);
            $task->getParticipant($sberryhill)->getAllFeedbacks()->add($floatingFeedback);
        }
        if (rand(1,2) <= 1) {
            $task->getParticipant($slucier)->getAllResponses()->add($completion);
            $task->getParticipant($slucier)->getAllFeedbacks()->add($feedback);
            $task->getParticipant($slucier)->getAllFeedbacks()->add($floatingFeedback);
        }
        if (rand(1,2) <= 1) {
            $task->getParticipant($bsalem)->getAllResponses()->add($completion);
            $task->getParticipant($bsalem)->getAllFeedbacks()->add($feedback);
            $task->getParticipant($bsalem)->getAllFeedbacks()->add($floatingFeedback);
        }
        if (rand(1,2) <= 1) {
            $task->getParticipant($asaxon)->getAllResponses()->add($completion);
            $task->getParticipant($asaxon)->getAllFeedbacks()->add($feedback);
            $task->getParticipant($asaxon)->getAllFeedbacks()->add($floatingFeedback);
        }
        if (rand(1,2) <= 1) {
            $task->getParticipant($celia)->getAllResponses()->add($completion);
            $task->getParticipant($celia)->getAllFeedbacks()->add($feedback);
            $task->getParticipant($celia)->getAllFeedbacks()->add($floatingFeedback);
        }
        if (rand(1,2) <= 1) {
            $task->getParticipant($somodio)->getAllResponses()->add($completion);
            $task->getParticipant($somodio)->getAllFeedbacks()->add($feedback);
            $task->getParticipant($somodio)->getAllFeedbacks()->add($floatingFeedback);
        }

        return $task;
    }

    /**
     * Spawn a Quiz.
     * * Quiz has 5 questions each of 5 marks, for total of 25.0
     * * Quiz has 1 attempt of 4 questions worth 20.0 marks. He was given 12.0 marks total. ie 60.0
     *
     * For now, since we are not updating the Quiz DB schema, a QuizAttempt has 0:1 QuizFeedback
     *
     * @return Quiz
     */
    protected function spawnQuiz()
    {
        $hodes = (new User)->setId(230)->setFirstname('David')->setLastname('Rhodes');
        $sberryhill = (new User)->setId(15)->setFirstname('Scott')->setLastname('Berryhill');
        $slucier = (new User)->setId(16)->setFirstname('Sam')->setLastname('Lucier');
        $bsalem = (new User)->setId(32)->setFirstname('Bobby')->setLastname('Salem');
        $asaxon = (new User)->setId(17)->setFirstname('Anita')->setLastname('Saxon');
        $celia = (new User)->setId(3)->setFirstname('Celia')->setLastname('Glenn');
        $somodio = (new User)->setId(150)->setFirstname('Anelie')->setLastname('Somodio');

        $id = 3;
        $title = 'How did you find The Prime Evil?';
        $description = 'A quick pop quiz about how you died your way to Diablo.';

        $responses = [
            [
                'mark' => 1, // Question 1
                'available' => 5,
            ],
            [
                'mark' => 1, // Question 2
                'available' => 5,
            ],
            [
                'mark' => 5, // Question 3
                'available' => 5,
            ],
            [
                'mark' => 5, // Question 4
                'available' => 5,
            ],
        ];
        $marksGiven = $marksAvailable = 0;
        foreach($responses as $response){
            $marksGiven += $response['mark'];
            $marksAvailable += $response['available'];
        }
        $dateFormat = 'Y-m-d H:i:s';
        $dateInit = '2016-09-08 11:30:00';

        $quiz = new Quiz();
        $quiz->setId($id)
            ->setTitle($title)
            ->setDescription($description)
            ->setCreator($somodio)
            ->setWeight(8)

            ->setAllParticipants((new Collection())
                ->add(new Participant($hodes, $quiz))
                ->add(new Participant($sberryhill, $quiz))
                ->add(new Participant($slucier, $quiz))
                ->add(new Participant($bsalem, $quiz))
                ->add(new Participant($asaxon, $quiz))
                ->add(new Participant($celia, $quiz))
                ->add(new Participant($somodio, $quiz))
            )


            ->setPublishAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(1))
            ->setOpenAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(1))
            ->setDueAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(57))
            ->setCloseAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(60))
            ->setCreatedAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(1))
            ->setUpdatedAt((new \Alaress_Date_Format($dateInit, $dateFormat))->addDays(1));

        $attempt = (new QuizAttempt);
        $attempt
            ->setId(8655)
            ->setAssessable($quiz)
            ->setSubmittedBy($hodes)
            ->setSubmittedOnBehalfOf($hodes)
            ->setCreatedAt(new \Alaress_Date_Format('2016-11-15 03:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-15 03:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setMarksAvailable($marksAvailable)
            ->setMarksGiven($marksGiven);

/*
        $feedback = new QuizReview();
        $feedback
            ->setId(99759)
            ->setAssessable($quiz)
            ->setNormalisedMark(IMarkScheme::NORMALISED_MARK_MAX * $marksGiven / $marksAvailable)
            ->setReviewer($celia)
            ->setResponse($attempt)
            ->setPublishedAt(new \Alaress_Date_Format('2016-08-15 03:27:33', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setCreatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT))
            ->setUpdatedAt(new \Alaress_Date_Format('2016-08-08 21:03:19', \Alaress_Date_Format::DATABASE_FORMAT));

        $attempt->getAllFeedback()->add($feedback);
*/
        if (rand(1,2) <= 1)
        $quiz->getParticipant($hodes)->getAllResponses()->add($attempt);
        if (rand(1,2) <= 1)
        $quiz->getParticipant($sberryhill)->getAllResponses()->add($attempt);
        if (rand(1,2) <= 1)
        $quiz->getParticipant($slucier)->getAllResponses()->add($attempt);
        if (rand(1,2) <= 1)
        $quiz->getParticipant($bsalem)->getAllResponses()->add($attempt);
        if (rand(1,2) <= 1)
        $quiz->getParticipant($asaxon)->getAllResponses()->add($attempt);
        if (rand(1,2) <= 1)
        $quiz->getParticipant($celia)->getAllResponses()->add($attempt);
        if (rand(1,2) <= 1)
        $quiz->getParticipant($somodio)->getAllResponses()->add($attempt);


        //$quiz->getParticipant($hodes)->getAllFeedbacks()->add($feedback);

        return $quiz;
    }
}
