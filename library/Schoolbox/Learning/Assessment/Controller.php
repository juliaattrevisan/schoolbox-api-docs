<?php
/**
 * @author    Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */
namespace Schoolbox\Learning\Assessment;

use Aura\Web\Request;
use DomainException;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Model as GradeScheme;
use Schoolbox\Assessment\Component\SubmissionBox\GradeLevel\Group\Mapper as GradeSchemeMapper;
use Schoolbox\Assessment\Component\SubmissionBox\WorkType;
use Schoolbox\Assessment\Component\SubmissionBox\WorkTypeMapper;
use Schoolbox\Collection;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Learning\Assessment\Summator\Minimum;
use Schoolbox\Learning\AssessmentContext;
use Schoolbox\Learning\MarkScheme\Factory as MarkSchemeFactory;
use Schoolbox\Learning\MarkScheme\Percentage;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access;
use Schoolbox\User as User;
use Schoolbox\UserMapper;
use \Alaress_Date_Format;
use \Alaress_Date_NullDate;
use \Schoolbox\Learning\Assessment\DueWork\Model as DueWork;
use \Schoolbox\Learning\Assessment\DueWork\WorkType as DueWorkType;
use \Schoolbox\Learning\Assessment\DueWork\Feedback\Model as DueWorkFeedback;
use \Schoolbox\Learning\Assessment\DueWork\Submission\Model as DueWorkSubmission;
use \Schoolbox\Learning\Assessment\Task\Model as Task;
use \Schoolbox\Learning\Assessment\Task\Completion\Model as TaskCompletion;
use \Schoolbox\Learning\Assessment\Task\Feedback\Model as TaskFeedback;
use \Schoolbox\Learning\Assessment\Quiz\Model as Quiz;
use \Schoolbox\Learning\Assessment\Quiz\Attempt\Model as QuizAttempt;
use Schoolbox\Resource\Attachment\Zone;
use Schoolbox\Learning\Assessment\Breadcrumb\Factory as BreadcrumbFactory;

abstract class Controller extends ListController implements IAssessmentController
{

    /** @var BreadcrumbFactory */
    protected $bakery;

    /**
     * Controller constructor.
     *
     * @param UserMapper            $userMapper
     * @param ErrorMapper           $errorMapper
     * @param DataModel\MapperProxy $assessmentMapper
     * @param Access                $access
     * @param Transactor            $transactor
     * @param BreadcrumbFactory     $bakery
     */
    public function __construct(
        UserMapper $userMapper,
        ErrorMapper $errorMapper,
        DataModel\MapperProxy $assessmentMapper,
        Access $access,
        Transactor $transactor,
        BreadcrumbFactory $bakery
    ) {
        parent::__construct($userMapper, $errorMapper, $assessmentMapper, $access, $transactor);
        $this->bakery = $bakery;
    }

    /**
     * Get the form for creating a new assessment in a particular context
     *
     * @api   GET /learning/{contextType}/{contextId}/assessments/new?type
     * @level 165
     *
     * @param AssessmentContext $context
     */
    public function getCreateAssessmentForm(AssessmentContext $context)
    {
        $type = $this->getRequest()->query->get('type');

        // build a stub
        $assessment = AssessmentType::stringToObject($type, $context);

        $data = $this->getAssessmentAsTwigArray($context, $assessment);

        // Did the caller request a returnUrl?
        $returnUrl = $this->getRequest()->query->get(Redirect::RETURN_URL);

        if ($returnUrl) {
            $data[Redirect::RETURN_URL] = $returnUrl;
        }

        // either output the fragment to the mage, or use the synchronous form
        if ($this->getRequest()->isXhr()) {
            $content = $this->buildUnthemedHtmlOutput("@learningAssessment/fragments/$type/create.twig", $data);

        } else {
            $content = $this->buildHtmlOutput('@learningAssessment/create.twig', $data);
        }

        $this->getResponse()->content->set($content);
    }


    /**
     * Get the form to update an existing Assessment's details
     *
     * @api /learning/assessments/:assessmentId/modify
     * @level 165
     * @param IAssessable $assessment
     */
    public function getUpdateAssessmentForm(IAssessable $assessment)
    {
        $data = $this->getAssessmentAsTwigArray($assessment->getContext(), $assessment);
        $data['returnUrlName'] = Redirect::RETURN_URL;
        $data['returnUrl'] = $this->getRequest()->query->get(Redirect::RETURN_URL);

        $template = "@learningAssessment/update.twig";
        $this->getResponse()->content->set($this->buildHtmlOutput($template, $data));
    }

    /**
     * Save the data when creating a specific kind of Assessment
     *
     * This happens after the common details are saved and after
     * you've chosen one of project, due work, task, or quiz
     *
     * @api   POST /learning/assessments/:assessmentId
     * @level 165
     *
     * @param IAssessable $assessment
     */
    public function postUpdateAssessment(IAssessable $assessment)
    {
        /* Modify particular fields
         */
        if ($this->getRequest()->server['HTTP_X_HTTP_METHOD_OVERRIDE']) {
            $this->patchUpdateAssessment($assessment);
            return;
        }

        /* Regular update
         */
        try {
            $assessmentInfo = $this->persistAssessment(
                $this->getUser(),
                $this->getRequest()->post,
                $assessment
            );
            $internalReturnUrl = $assessmentInfo[Redirect::RETURN_URL];
        }
        catch (DomainException $e) {
            $this->sendError('could not save assessment', [ $e->getMessage() ]);

            /* Return Early to let mage deal with error messages
             */
            return;
        }

        $postReturnUrl = $this->getRequest()->post->get(Redirect::RETURN_URL);

        if ($postReturnUrl) {
            $returnUrl = Redirect::decode($postReturnUrl);
        } else {
            $returnUrl = $this->determineRedirect($assessment, $internalReturnUrl);
        }

        // we're done here
        $this->redirect($returnUrl);
    }

    /**
     * Delete the given assessment.
     * Deletion is considered to be an idempotent method, so do not return an error on repeat attempts to delete an entity.
     *
     * On success, respond with '204 No Content' and a Location header to the list
     * On failure, respond appropriately
     *
     * @api   POST /learning/assessments/{assessmentId}/delete
     * @level 165
     * @param IAssessable $assessment
     * @return void
     */
    public function postDeleteAssessment(IAssessable $assessment)
    {
        // Already deleted?
        if ($assessment->isDeleted()) {
            $this->getResponse()->status->setCode(204);
        } else {
            $this->getAssessmentMapper()->delete($assessment);
        }

        $folder = $assessment->getContext()->getFolder();
        $postReturnUrl = $this->getRequest()->query[Redirect::RETURN_URL];

        /* Check for the presence of a returnUrl passed on GET request as well
         */

        // Did caller explicitly specify a returnUrl ?
        if ($postReturnUrl) {
            $returnUrl = Redirect::decode($postReturnUrl);
        } else {
            $returnUrl = $this->determineRedirect($assessment, "/homepage/{$folder->getId()}");
        }

        if ($this->getRequest()->isXhr()) {
            $this->getResponse()->content->set($this->buildJsonOutput([
                Redirect::RETURN_URL => $returnUrl
            ]));
        }
        else {
            $this->redirect($returnUrl);
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
        /* If you're a student, get redirected to the summary
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
     * @param AssessmentContext $context
     * @param IAssessable       $assessment
     * @return array
     */
    private function getAssessmentAsTwigArray(AssessmentContext $context, IAssessable $assessment)
    {
        if (!$assessment->getId()) {
            // new assessment: set default values
            $assessment
                ->setPublishAt(new Alaress_Date_Format(new \DateTime('00:00:00')))
                ->setOpenAt(new Alaress_Date_Format(new \DateTime('00:00:00')))
                ->setDueAt(new Alaress_Date_Format(new \DateTime('+1 day 23:59:59')))
                ->setCloseAt(new Alaress_Date_Format(new \DateTime('+2 day 23:59:59')))
                ->setWeight(0);
        }

        $contextType = AssessmentContextType::objectToString($context) . 's';

        /* The usual info everything requires
         */
        $data = [
            'context'         => $context,
            'contextType'     => $contextType,
            'baseContextType' => AssessmentContextType::objectToString($context->getBaseContext()),
            'breadcrumbs'     => $this->getBakery()->produceFromContext($context, $assessment),
            'assessment'      => $assessment,
            'folder'          => $context->getFolder(),
            'curriculumNodes' => $context->getFolder()->getCurriculumNodes(),
            'assessmentType'  => AssessmentType::objectToString($assessment),
        ];

        $data = array_merge($data, $this->getAssessmentTwigData($assessment));

        return $data;
    }

    /**
     * Return whether or not the given user is allowed to see the given participation
     *
     * @param User        $user
     * @param Participant $participant
     * @return bool
     */
    protected function userCanViewParticipation(User $user, Participant $participant)
    {
        $student = $participant->getUser();

        // staff can always see
        if ($user->getRole()->isStaff()) {
            return true;
        }

        // student can see own
        if ($student->getId() == $user->getId()) {
            return true;
        }

        // filter guests, and other students
        if (!$user->getRole()->isParent()) {
            return false;
        }

        // parent can see ... IF config allows
        if ($this->getConfigLoader()->get('restrict_student_submission')) {
            return false;
        }

        if ($this->getConfigLoader()->get('restrict_parent_duework_access')) {
            return false;
        }

        // parents are limited to their own children (this is expensive so definitely do this last!)
        $student->loadParents();
        if ($student->isChildOf($user)) {
            return true;
        }

        // failed all checks: bounce 'em!
        return false;
    }

    /**
     * Assert that the current session user is allowed to see the given participation
     * TODO consider pushing this up into the module
     *
     * @param Participant $participant
     */
    protected function assertParticipantAccess(Participant $participant)
    {
        if (!$this->userCanViewParticipation($this->getUser(), $participant)) {
            // could probably throw a 403, but that would give away the fact that, yes, the student is a participant of that assessment
            throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_GENERIC_NOT_FOUND);
        }
    }

    /**
     * All the possible Mark Types to choose from for this particular $assessmentType
     *
     * @param IAssessable $assessable
     * @return array
     */
    protected function getAllMarkTypes(IAssessable $assessable)
    {
        return MarkSchemeFactory::getAvailableMarkTypes($assessable);
    }

    /**
     * Gets all work types for due work.
     *
     * TODO DI this if you feel like it
     *
     * @return WorkType[]|Collection
     */
    protected function getAllWorkTypes()
    {
        return WorkTypeMapper::getInstance()->findAll();
    }

    /**
     * Gets all grading schemes.
     *
     * TODO DI this if you feel like it
     *
     * @return GradeScheme[]|Collection
     */
    protected function getAllGradeSchemes()
    {
        return GradeSchemeMapper::getInstance()->findAllWithGradeLevels();
    }

    /**
     * @return BreadcrumbFactory
     */
    protected function getBakery()
    {
        return $this->bakery;
    }

    /**
     * @param IAssessable $assessment
     * @return array
     */
    protected abstract function getAssessmentTwigData(IAssessable $assessment);

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
        if ($assessment->getContext() instanceof Project) {
            return $this->getRouter()->generate('learningAssessment.getStatus', [ 'assessmentId' => $assessment->getContext()->getId() ]);
        }
        return "/homepage/{$assessment->getContext()->getBaseContext()->getFolder()->getId()}";
    }

    /**
     * Update particular fields of an existing IAssessable
     *
     * Default Abstract implementation does nothing, Concrete class to override.
     *
     * @param IAssessable $assessment
     * @return void
     */
    public function patchUpdateAssessment(IAssessable $assessment)
    {
    }



    // ================ DUMMY DATA GENERATION ===============================================


    /**
     * Temp method to generate dummy breadcrumbs
     *
     * @todo remove this method when no longer required
     * @return array
     */
    public static function getDummyBreadcrumbs()
    {
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
    public static function getDummyCohortList()
    {
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
     * @param $assessmentId 1 => project (7 => object)
     *                           2 => dueWork
     *                           3 => quiz
     *                           4 => task1
     *                           5 => task2
     *                           6 => task3
     * @todo remove this method when no longer required
     * @return array|\Schoolbox\Learning\Assessment\Project
     */
    public static function getDummyAssessment($assessmentId)
    {
        $creator = UserMapper::getInstance()->findById(151);    // Zane Smith (teacher)

        $assessment = [
            'id'              => $assessmentId,
            'publish_date'    => new Alaress_Date_Format('2016/08/01', 'Y/m/d'),
            'open_date'       => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
            'due_date'        => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
            'close_date'      => new Alaress_Date_Format('2016/08/22', 'Y/m/d'),
            'weight'          => 40,
            'creator'         => $creator,
            'mark_visibility' => 2,
            'responses'       => self::getDummyResponses($assessmentId),
            'created_at'      => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
            'updated_at'      => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
            'deleted_at'      => new Alaress_Date_NullDate(),
        ];

        switch ($assessmentId) {
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
                $project->setPublishAt(new Alaress_Date_Format('2016/08/01', 'Y/m/d'));
                $project->setOpenAt(new Alaress_Date_Format('2016/08/08', 'Y/m/d'));
                $project->setDueAt(new Alaress_Date_Format('2016/11/08', 'Y/m/d'));
                $project->setCloseAt(new Alaress_Date_Format('2016/11/09', 'Y/m/d'));
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
                    'accept_submission' => true,
                    'multi_submission'  => true,
                    'skip_unsubmitted'  => 0,
                    'work_type'         => WorkTypeMapper::getInstance()->findById(1),
                    'files'             => [
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
                    'link'           => 'https://www.youtube.com/watch?v=vShCnTY1-T0',
                    'isSelfMarkable' => false,
                ]);
            case 5: // task2
                $assessment = array_merge($assessment, [
                    'assessment_type' => 'task',
                    'title'           => 'mTask2',
                    'description'     => 'This is a task. It does not contain assessables, but has extra task-related info',
                    'mark_scheme'     => self::getDummyMarkScheme(4),
                ]);
                return array_merge($assessment, [
                    'link'           => '',
                    'isSelfMarkable' => true,
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
                    'link'           => 'https://www.youtube.com/watch?v=vShCnTY1-T0',
                    'isSelfMarkable' => true,
                ]);
        }
    }

    /**
     * Temp method to generate dummy student responses
     *
     * @param $assessmentId 1 => project
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
        switch ($assessmentId) {
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
                return [];
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
     * @param int $dueWorkFileId enum {1,2}
     * @return array
     */
    public static function getDummyDueWorkFile($dueWorkFileId)
    {
        $dueWorkFile = [
            'id'         => $dueWorkFileId,
            'created_at' => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
            'updated_at' => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
            'deleted_at' => new Alaress_Date_NullDate(),
        ];
        switch ($dueWorkFileId) {
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
     * @param int $dueWorkFeedbackId enum {1,2,3,4}
     * @todo remove when done
     * @return array
     */
    public static function getDummyDueWorkFeedback($dueWorkFeedbackId)
    {
        $dueWorkFeedback = [
            'id'           => $dueWorkFeedbackId,
            'feedbackType' => 'dueWorkFeedback',
            'reviewer'     => UserMapper::getInstance()->findById(151),
            'created_at'   => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
            'updated_at'   => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];
        switch ($dueWorkFeedbackId) {
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
                    'published_at' => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
                    'comment'      => 'You are going ok',
                    'files'        => [
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
                    'files'        => [],
                ]);
            case 4:
            default:
                return array_merge($dueWorkFeedback, [
                    'mark'         => 'F',
                    'norm_mark'    => 30.00000,
                    'published_at' => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
                    'comment'      => 'You need help (this is published)',
                    'files'        => [],
                ]);
        }
    }


    /**
     * @param int $dueWorkFeedbackFileId enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummyDueWorkFeedbackFile($dueWorkFeedbackFileId)
    {
        $dueWorkFeedbackFile = [
            'id'         => $dueWorkFeedbackFileId,
            'created_at' => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
            'updated_at' => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
            'deleted_at' => new Alaress_Date_NullDate(),
        ];
        switch ($dueWorkFeedbackFileId) {
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
     * @param int $dueWorkSubmissionId enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummySubmission($dueWorkSubmissionId)
    {
        $submission = [
            'id'           => $dueWorkSubmissionId,
            'responseType' => 'dueWorkSubmission',
            'created_at'   => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];
        switch ($dueWorkSubmissionId) {
            case 1:
                return array_merge($submission, [
                    'submitted_by'  => UserMapper::getInstance()->findById(230),
                    'submitted_for' => UserMapper::getInstance()->findById(245),
                    'updated_at'    => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
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
                    'updated_at'    => new Alaress_Date_Format('2016/08/16', 'Y/m/d'),
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
                    'updated_at'    => new Alaress_Date_Format('2016/08/17', 'Y/m/d'),
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
     * @param int $dueWorkSubmissionFileId enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummyDueWorkSubmissionFile($dueWorkSubmissionFileId)
    {
        $dueWorkSubmissionFile = [
            'id'         => $dueWorkSubmissionFileId,
            'created_at' => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
            'updated_at' => new Alaress_Date_Format('2016/08/17', 'Y/m/d'),
            'deleted_at' => new Alaress_Date_NullDate(),
        ];

        switch ($dueWorkSubmissionFileId) {
            case 1:
                return array_merge($dueWorkSubmissionFile, [
                    'crocodocUUID' => 'e367160c-0202-4034-83f7-e4643a953bbb',
                    'hash'         => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                    'fileName'     => 'portfolio',
                    'plagScan'     => [],
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
     * @param int $storageId enum {1,2,3,4,5,6,7}
     * @return array
     */
    public static function getDummyStorage($storageId)
    {
        $storage = [
            'id'         => $storageId,
            'mimetype'   => self::getDummyMimeType($storageId),
            'created_at' => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
            'updated_at' => new Alaress_Date_Format('2016/08/17', 'Y/m/d'),
            'deleted_at' => new Alaress_Date_NullDate(),
        ];
        switch ($storageId) {
            case 1:
                return array_merge($storage, [
                    'extension' => 'docx',
                    'filename'  => 'someFile',
                    'basename'  => 'someFile.docx',
                    'mime'      => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'size'      => 25000,
                    'hash'      => '4b50da92d887f9e05804f7425125b5064f5cb385',
                ]);
            case 2:
                return array_merge($storage, [
                    'extension' => 'gif',
                    'filename'  => 'abc',
                    'basename'  => 'abc.gif',
                    'mime'      => 'image/gif',
                    'size'      => 25000,
                    'hash'      => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
            case 3:
                return array_merge($storage, [
                    'extension' => 'mpeg',
                    'filename'  => 'myVideo',
                    'basename'  => 'myVideo.mpeg',
                    'mime'      => 'video/mpeg',
                    'size'      => 25000,
                    'hash'      => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
            case 4:
                return array_merge($storage, [
                    'extension' => 'mpeg',
                    'filename'  => 'mAudio',
                    'basename'  => 'mAudio.mpeg',
                    'mime'      => 'audio/mpeg',
                    'size'      => 25000,
                    'hash'      => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
            case 5:
                return array_merge($storage, [
                    'extension' => 'html',
                    'filename'  => 'index',
                    'basename'  => 'index.html',
                    'mime'      => 'text/html',
                    'size'      => 4000,
                    'hash'      => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
            case 6:
            default:
                return array_merge($storage, [
                    'extension' => 'zip',
                    'filename'  => 'mArchive',
                    'basename'  => 'mArchive.zip',
                    'mime'      => 'application/x-7z-compressed',
                    'size'      => 25000,
                    'hash'      => '90dd4fb4f06041fdf26b15fdf7a45b5699bf5833',
                ]);
        }
    }

    /**
     * @param int $mimeTypeId enum {1,2,3,4,5,6,7}
     * @return array
     */
    public static function getDummyMimeType($mimeTypeId)
    {
        $mimeType = [
            'id'                => $mimeTypeId,
            'actualMaxFileSize' => 1000000,
            'maxFileSize'       => 1000000,
            'enable'            => true,
            'isEnabled'         => true,
            'deleted'           => false,
            'isDeleted'         => false,
        ];
        switch ($mimeTypeId) {
            case 1:
                return array_merge($mimeType, [
                    'fileTypeId'  => 46,
                    'class'       => 'docx',
                    'description' => 'Microsoft Word 2007 Document',
                    'iconFile'    => 'msword_doc.png',
                    'iconClass'   => 'msworddoc',
                    'inline'      => false,
                    'mime'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'isAudio'     => false,
                    'isVideo'     => false,
                    'isImage'     => false,
                    'type'        => 'application',
                ]);
            case 2:
                return array_merge($mimeType, [
                    'fileTypeId'  => 1,
                    'class'       => 'gif',
                    'description' => 'GIF Image',
                    'iconFile'    => 'image_gif.png',
                    'iconClass'   => 'imagegif',
                    'inline'      => true,
                    'mime'        => 'image/gif',
                    'isAudio'     => false,
                    'isVideo'     => false,
                    'isImage'     => true,
                    'type'        => 'image',
                ]);
            case 3:
                return array_merge($mimeType, [
                    'fileTypeId'  => 27,
                    'class'       => 'video',
                    'description' => 'MPEG Video',
                    'iconFile'    => 'video_alt.png',
                    'iconClass'   => 'videoalt',
                    'inline'      => false,
                    'mime'        => 'video/mpeg',
                    'isAudio'     => false,
                    'isVideo'     => true,
                    'isImage'     => false,
                    'type'        => 'video',
                ]);
            case 4:
                return array_merge($mimeType, [
                    'fileTypeId'  => 23,
                    'class'       => 'mpeg',
                    'description' => 'MPEG Audio',
                    'iconFile'    => 'sound_mp3.png',
                    'iconClass'   => 'soundmp3',
                    'inline'      => false,
                    'mime'        => 'audio/mpeg',
                    'isAudio'     => true,
                    'isVideo'     => false,
                    'isImage'     => false,
                    'type'        => 'audio',
                ]);
            case 5:
                return array_merge($mimeType, [
                    'fileTypeId'  => 2,
                    'class'       => 'html',
                    'description' => 'HTML Document',
                    'iconFile'    => 'html.png',
                    'iconClass'   => 'html',
                    'inline'      => true,
                    'mime'        => 'text/html',
                    'isAudio'     => false,
                    'isVideo'     => false,
                    'isImage'     => false,
                    'type'        => 'text',
                ]);
            case 6:
            default:
                return array_merge($mimeType, [
                    'fileTypeId'  => 80,
                    'class'       => 'zip',
                    'description' => '7-Zip',
                    'iconFile'    => 'archive.png',
                    'iconClass'   => 'archive',
                    'inline'      => false,
                    'mime'        => 'application/x-7z-compressed',
                    'isAudio'     => false,
                    'isVideo'     => false,
                    'isImage'     => false,
                    'type'        => 'application',
                ]);
        }
    }

    /**
     * Generate a dummy plag scan doc
     *
     * @param int $plagScanId enum {1,2}
     * @todo remove when done
     * @return array
     */
    public static function getDummyPlagScan($plagScanId)
    {
        $plagScan = [
            'id'         => $plagScanId,
            'created_at' => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
            'updated_at' => new Alaress_Date_Format('2016/08/17', 'Y/m/d'),
            'deleted_at' => new Alaress_Date_NullDate(),
        ];
        switch ($plagScanId) {
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
     * @param $scheme 1 => percentage
     *                   2 => letterGrade
     *                   3 => totalMark
     *                   4 => none
     *                   5 => Completion
     * @todo remove this method when no longer required
     * @return array
     */
    public static function getDummyMarkScheme($scheme)
    {
        switch ($scheme) {
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
    public static function getDummyQuizSection($sectionId)
    {
        switch ($sectionId) {
            case 1:
                return [
                    'id'          => 1,
                    'title'       => 'mFirstSection',
                    'description' => 'This is the first section of this quiz',
                    'random'      => 0,
                    'pool'        => 0,
                    'questions'   => [
                        self::getDummyQuizQuestion(1, true),
                    ],
                    'created_at'  => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
                    'updated_at'  => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
                    'deleted_at'  => new Alaress_Date_NullDate(),
                ];
            case 2:
            default:
                return [
                    'id'          => 2,
                    'title'       => 'mSecondSection',
                    'description' => 'This is the second section of this quiz',
                    'random'      => 0,
                    'pool'        => 0,
                    'questions'   => [
                        self::getDummyQuizQuestion(2, true),
                        self::getDummyQuizQuestion(3, true),
                    ],
                    'created_at'  => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
                    'updated_at'  => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
                    'deleted_at'  => new Alaress_Date_NullDate(),
                ];
        }
    }


    /**
     * Generates a dummy quiz attempt for the specified user
     *
     * @param int $attemptId enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummyQuizAttempt($attemptId)
    {
        $attempt = [
            'id'           => $attemptId,
            'responseType' => 'quizAttempt',
            'start'        => new Alaress_Date_Format('2012/07/11 12:02:39', 'Y/m/d H:m:s'),
            'finish'       => new Alaress_Date_Format('2012/07/12 12:05:20', 'Y/m/d H:m:s'),
            'reviewed_at'  => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
            'deleted_at'   => new Alaress_Date_NullDate(),
        ];

        switch ($attemptId) {
            case 1:
                $attempt['feedbacks'] = [
                    self::getDummyQuizAnswer(1, true),
                ];
                return $attempt;
            case 2:
                $attempt['feedbacks'] = [
                    self::getDummyQuizAnswer(2, true),
                    self::getDummyQuizAnswer(3, true),
                    self::getDummyQuizAnswer(4, true),
                ];
                return $attempt;
            case 3:
            default:
                $attempt['feedbacks'] = [
                    self::getDummyQuizAnswer(5, true),
                    self::getDummyQuizAnswer(6, true),
                    self::getDummyQuizAnswer(7, true),
                ];
                return $attempt;
        }
    }

    /**
     * Generates a dummy question, along with its solutions
     *
     * @param int $questionId enum {1,2}
     * @param bool $nestAnswers whether to nest answers inside question
     * @todo remove when done
     * @return array
     */
    public static function getDummyQuizQuestion($questionId, $nestAnswers)
    {
        $question = [
            'type'       => self::getDummyQuizQuestionType(1),
            'marks'      => 1.00,
            'config'     => null,
            'created_at' => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
            'updated_at' => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
            'deleted_at' => new Alaress_Date_NullDate(),
        ];

        switch ($questionId) {
            case 1:
                $question['id'] = 1;
                $question['question'] = '<div class="ff4">They used to say that a child conceived in love has a greater chance</div><div class="ff4">of... What?</div><div class="ff4">';
                $question['solution'] = [
                    'id'         => 1,
                    'solution'   => 'Happiness',
                    'created_at' => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
                    'updated_at' => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
                    'deleted_at' => new Alaress_Date_NullDate(),
                ];
                $question['answers'] = [
                    self::getDummyQuizAnswer(1, false),
                    self::getDummyQuizAnswer(2, false),
                    self::getDummyQuizAnswer(5, false),
                ];
                return $question;
            case 2:
                $question['id'] = 2;
                $question['question'] = '<<p>What is the weather in Bangalore right now?</p>';
                $question['solution'] = [
                    'id'         => 2,
                    'solution'   => 'Cloudy with a chance of meatballs',
                    'created_at' => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
                    'updated_at' => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
                    'deleted_at' => new Alaress_Date_NullDate(),
                ];
                $question['answers'] = [
                    self::getDummyQuizAnswer(3, false),
                    self::getDummyQuizAnswer(6, false),
                ];
                return $question;
            case 3:
            default:
                $question['id'] = 3;
                $question['question'] = '<<p>What is the color of the sky at night?</p>';
                $question['solution'] = [
                    'id'         => 3,
                    'solution'   => 'Black',
                    'created_at' => new Alaress_Date_Format('2016/08/08', 'Y/m/d'),
                    'updated_at' => new Alaress_Date_Format('2016/08/15', 'Y/m/d'),
                    'deleted_at' => new Alaress_Date_NullDate(),
                ];
                $question['answers'] = [
                    self::getDummyQuizAnswer(4, false),
                    self::getDummyQuizAnswer(7, false),
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
    public static function getDummyQuizQuestionType($questionType)
    {
        switch ($questionType) {
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
     * @param int $answerId enum {1,2,3,4,5,6,7}
     * @param bool $nestQuestion Whether to return nested questions for this answer
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

        switch ($answerId) {
            case 1:
                return array_merge($answer, [
                    'answer'   => 'Sadness',
                    'comment'  => '',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(1, false) : null,
                ]);
            case 2:
                return array_merge($answer, [
                    'answer'   => 'Happiness',
                    'comment'  => 'Very good',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(1, false) : null,
                ]);
            case 3:
                return array_merge($answer, [
                    'answer'   => 'I think it is raining',
                    'comment'  => 'No you are wrong',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(2, false) : null,
                ]);
            case 4:
                return array_merge($answer, [
                    'answer'   => 'Blue',
                    'comment'  => 'Not quite',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(3, false) : null,
                ]);
            case 5:
                return array_merge($answer, [
                    'answer'   => 'Happiness',
                    'comment'  => 'Good work',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(1, false) : null,
                ]);
            case 6:
                return array_merge($answer, [
                    'answer'   => 'I think it is sunny',
                    'comment'  => 'Pretty close',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(2, false) : null,
                ]);
            case 7:
                return array_merge($answer, [
                    'answer'   => 'Yellow',
                    'comment'  => '',
                    'question' => $nestQuestion ? self::getDummyQuizQuestion(3, false) : null,
                ]);
        }

        return [];
    }

    /**
     * Generate a dummy task completion
     *
     * @param int $taskCompletionId enum {1,2}
     * @todo remove when done
     * @return array
     */
    public static function getDummyTaskCompletion($taskCompletionId)
    {
        $taskCompletion = [
            'id'           => $taskCompletionId,
            'responseType' => 'taskCompletion',
        ];
        switch ($taskCompletionId) {
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
     * @param int $taskFeedbackId enum {1,2,3}
     * @todo remove when done
     * @return array
     */
    public static function getDummyTaskFeedback($taskFeedbackId)
    {
        $taskFeedback = [
            'id'           => $taskFeedbackId,
            'feedbackType' => 'taskFeedback',
        ];
        switch ($taskFeedbackId) {
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
    private function spawnDueWork()
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
            ->setWorkType((new DueWorkType())
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

        if (rand(1, 3) <= 1) {
            $dueWork->getParticipant($hodes)->getAllResponses()->add($submission);
            $dueWork->getParticipant($hodes)->getAllFeedbacks()->add($feedback);
        }
        if (rand(1, 3) <= 1) {
            $dueWork->getParticipant($sberryhill)->getAllResponses()->add($submission);
            $dueWork->getParticipant($sberryhill)->getAllFeedbacks()->add($feedback);
        }
        if (rand(1, 3) <= 1) {
            $dueWork->getParticipant($slucier)->getAllResponses()->add($submission);
            $dueWork->getParticipant($slucier)->getAllFeedbacks()->add($feedback);
        }
        if (rand(1, 3) <= 1) {
            $dueWork->getParticipant($bsalem)->getAllResponses()->add($submission);
            $dueWork->getParticipant($bsalem)->getAllFeedbacks()->add($feedback);
        }
        if (rand(1, 3) <= 1) {
            $dueWork->getParticipant($asaxon)->getAllResponses()->add($submission);
            $dueWork->getParticipant($asaxon)->getAllFeedbacks()->add($feedback);
        }
        if (rand(1, 3) <= 1) {
            $dueWork->getParticipant($celia)->getAllResponses()->add($submission);
            $dueWork->getParticipant($celia)->getAllFeedbacks()->add($feedback);
        }
        if (rand(1, 3) <= 1) {
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
    private function spawnTask()
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

        if (rand(1, 2) <= 1) {
            $task->getParticipant($hodes)->getAllResponses()->add($completion);
            $task->getParticipant($hodes)->getAllFeedbacks()->add($feedback);
            $task->getParticipant($hodes)->getAllFeedbacks()->add($floatingFeedback);
        }
        if (rand(1, 2) <= 1) {
            $task->getParticipant($sberryhill)->getAllResponses()->add($completion);
            $task->getParticipant($sberryhill)->getAllFeedbacks()->add($feedback);
            $task->getParticipant($sberryhill)->getAllFeedbacks()->add($floatingFeedback);
        }
        if (rand(1, 2) <= 1) {
            $task->getParticipant($slucier)->getAllResponses()->add($completion);
            $task->getParticipant($slucier)->getAllFeedbacks()->add($feedback);
            $task->getParticipant($slucier)->getAllFeedbacks()->add($floatingFeedback);
        }
        if (rand(1, 2) <= 1) {
            $task->getParticipant($bsalem)->getAllResponses()->add($completion);
            $task->getParticipant($bsalem)->getAllFeedbacks()->add($feedback);
            $task->getParticipant($bsalem)->getAllFeedbacks()->add($floatingFeedback);
        }
        if (rand(1, 2) <= 1) {
            $task->getParticipant($asaxon)->getAllResponses()->add($completion);
            $task->getParticipant($asaxon)->getAllFeedbacks()->add($feedback);
            $task->getParticipant($asaxon)->getAllFeedbacks()->add($floatingFeedback);
        }
        if (rand(1, 2) <= 1) {
            $task->getParticipant($celia)->getAllResponses()->add($completion);
            $task->getParticipant($celia)->getAllFeedbacks()->add($feedback);
            $task->getParticipant($celia)->getAllFeedbacks()->add($floatingFeedback);
        }
        if (rand(1, 2) <= 1) {
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
    private function spawnQuiz()
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
                'mark'      => 1, // Question 1
                'available' => 5,
            ],
            [
                'mark'      => 1, // Question 2
                'available' => 5,
            ],
            [
                'mark'      => 5, // Question 3
                'available' => 5,
            ],
            [
                'mark'      => 5, // Question 4
                'available' => 5,
            ],
        ];
        $marksGiven = $marksAvailable = 0;
        foreach ($responses as $response) {
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
        if (rand(1, 2) <= 1) {
            $quiz->getParticipant($hodes)->getAllResponses()->add($attempt);
        }
        if (rand(1, 2) <= 1) {
            $quiz->getParticipant($sberryhill)->getAllResponses()->add($attempt);
        }
        if (rand(1, 2) <= 1) {
            $quiz->getParticipant($slucier)->getAllResponses()->add($attempt);
        }
        if (rand(1, 2) <= 1) {
            $quiz->getParticipant($bsalem)->getAllResponses()->add($attempt);
        }
        if (rand(1, 2) <= 1) {
            $quiz->getParticipant($asaxon)->getAllResponses()->add($attempt);
        }
        if (rand(1, 2) <= 1) {
            $quiz->getParticipant($celia)->getAllResponses()->add($attempt);
        }
        if (rand(1, 2) <= 1) {
            $quiz->getParticipant($somodio)->getAllResponses()->add($attempt);
        }


        //$quiz->getParticipant($hodes)->getAllFeedbacks()->add($feedback);

        return $quiz;
    }

    /**
     * Insert an attach zone into the template
     *
     * @param Collection $attachments|IStorage
     * @return array
     */
    private function getAttachZone($attachments = null)
    {
        $zone = new Zone('attachment');
        $zone
            ->setThumbnailSize(64)
            ->setModalTitle('Select attachment')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload attachment')
            ->setMimeFilter('*/*')
            ->setReadOnly(false)
            ->setMulti(true);

        if ($attachments != null)
            foreach ($attachments as $attachment)
                $zone->attachFiles(array($attachment->getHash()));

        return $zone->getTwigContext();
    }
}
